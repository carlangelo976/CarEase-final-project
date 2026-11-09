<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class CarEaseCalendarOAuth extends Controller
{
    public function __construct()
    {
        parent::__construct();
        if (session_status() !== PHP_SESSION_ACTIVE)
            session_start();
        $this->call->model('CarEaseModel');
    }
    private function guard()
    {
        if (empty($_SESSION['carease_user_id']) || ($_SESSION['carease_role'] ?? '') !== 'admin') {
            http_response_code(403);
            exit('CarEase admin login required.');
        }
    }
    private function cfg()
    {
        $path = __DIR__ . '/../config/carease_oauth_local.php';
        if (!is_file($path))
            throw new RuntimeException('Missing app/config/carease_oauth_local.php');
        $c = require $path;
        foreach (['client_id', 'client_secret', 'redirect_uri', 'calendar_id', 'token_file'] as $key) {
            if (empty($c[$key]))
                throw new RuntimeException('Missing OAuth configuration: ' . $key);
        }
        return $c;
    }
    private function csrf()
    {
        if (empty($_SESSION['carease_csrf']))
            $_SESSION['carease_csrf'] = bin2hex(random_bytes(24));
        return $_SESSION['carease_csrf'];
    }
    private function callGoogle($method, $url, $headers = [], $fields = null)
    {
        if (!function_exists('curl_init'))
            throw new RuntimeException('Enable PHP cURL in WAMP.');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers
        ]);
        if ($fields !== null)
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        $decoded = json_decode((string) $body, true);
        if ($body === false || $status < 200 || $status >= 300) {
            $msg = is_array($decoded) ? ($decoded['error_description'] ?? ($decoded['error']['message'] ?? (is_string($decoded['error'] ?? null) ? $decoded['error'] : 'Request failed'))) : $error;
            throw new RuntimeException('Google request HTTP ' . $status . ': ' . $msg);
        }
        return is_array($decoded) ? $decoded : [];
    }
    private function tokenPath($cfg)
    {
        $path = $cfg['token_file'];
        $dir = dirname($path);
        if (!is_dir($dir))
            throw new RuntimeException('Create private token folder first: ' . $dir);
        return $path;
    }
    private function readToken($cfg)
    {
        $path = $this->tokenPath($cfg);
        if (!is_file($path))
            return null;
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }
    private function saveToken($cfg, $data)
    {
        $path = $this->tokenPath($cfg);
        $existing = $this->readToken($cfg) ?: [];
        if (empty($data['refresh_token']) && !empty($existing['refresh_token']))
            $data['refresh_token'] = $existing['refresh_token'];
        $data['expires_at'] = time() + max(0, (int) ($data['expires_in'] ?? 3600)) - 90;
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (file_put_contents($path, $json, LOCK_EX) === false)
            throw new RuntimeException('Cannot save OAuth token file.');
        @chmod($path, 0600);
    }
    private function accessToken($cfg)
    {
        $token = $this->readToken($cfg);
        if (!$token)
            throw new RuntimeException('Connect Google Calendar first.');
        if (!empty($token['access_token']) && (int) ($token['expires_at'] ?? 0) > time())
            return $token['access_token'];
        if (empty($token['refresh_token']))
            throw new RuntimeException('Refresh token unavailable. Reconnect Google Calendar.');
        $new = $this->callGoogle('POST', 'https://oauth2.googleapis.com/token', ['Content-Type: application/x-www-form-urlencoded'], http_build_query([
            'client_id' => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'refresh_token' => $token['refresh_token'],
            'grant_type' => 'refresh_token'
        ]));
        $this->saveToken($cfg, array_merge($token, $new));
        return $new['access_token'];
    }
    public function index()
    {
        $this->guard();
        $bookings = array_values(array_filter($this->CarEaseModel->bookings() ?: [], static function ($b) {
            return in_array($b['status'], ['pending', 'approved', 'confirmed', 'ongoing', 'overdue', 'completed'], true);
        }));
        $connected = false;
        $notice = $_SESSION['carease_calendar_notice'] ?? '';
        unset($_SESSION['carease_calendar_notice']);
        try {
            $connected = (bool) $this->readToken($this->cfg());
            if ($connected) {
                require_once __DIR__ . '/../libraries/CarEaseCalendarSync.php';
                (new CarEaseCalendarSync())->check();
            }
        } catch (Throwable $e) {
            $connected = false;
            $notice = $e->getMessage();
        }
        $this->call->view('carease/calendar_oauth', [
            'bookings' => $bookings,
            'csrf' => $this->csrf(),
            'connected' => $connected,
            'notice' => $notice,
            'isAdmin' => true,
            'careaseUser' => $_SESSION['carease_name'] ?? null
        ]);
    }
    public function connect()
    {
        $this->guard();
        $cfg = $this->cfg();
        $_SESSION['carease_oauth_state'] = bin2hex(random_bytes(24));
        $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $cfg['client_id'],
            'redirect_uri' => $cfg['redirect_uri'],
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar.events',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $_SESSION['carease_oauth_state']
        ]);
        header('Location: ' . $url);
        exit;
    }
    public function callback()
    {
        $this->guard();
        $state = (string) ($_GET['state'] ?? '');
        if ($state === '' || !hash_equals((string) ($_SESSION['carease_oauth_state'] ?? ''), $state)) {
            http_response_code(403);
            exit('Invalid OAuth state. Please connect again.');
        }
        unset($_SESSION['carease_oauth_state']);
        if (isset($_GET['error'])) {
            $_SESSION['carease_calendar_notice'] = 'Google authorization was declined.';
            redirect('carease/calendar-oauth');
            return;
        }
        $code = (string) ($_GET['code'] ?? '');
        if ($code === '') {
            http_response_code(400);
            exit('Missing Google authorization code.');
        }
        try {
            $cfg = $this->cfg();
            $token = $this->callGoogle('POST', 'https://oauth2.googleapis.com/token', ['Content-Type: application/x-www-form-urlencoded'], http_build_query([
                'code' => $code,
                'client_id' => $cfg['client_id'],
                'client_secret' => $cfg['client_secret'],
                'redirect_uri' => $cfg['redirect_uri'],
                'grant_type' => 'authorization_code'
            ]));
            $this->saveToken($cfg, $token);
            require_once __DIR__ . '/../libraries/CarEaseCalendarSync.php';
            $calendar = new CarEaseCalendarSync();
            $synced = 0;
            $failed = [];
            foreach ($this->CarEaseModel->bookings() ?: [] as $booking) {
                if (!in_array($booking['status'], ['pending', 'approved', 'confirmed', 'ongoing', 'overdue', 'completed'], true))
                    continue;
                try {
                    $calendar->sync($booking, $this->CarEaseModel->car($booking['car_id']));
                    $synced++;
                } catch (Throwable $syncError) {
                    $failed[] = '#' . $booking['id'] . ': ' . $syncError->getMessage();
                }
            }
            $notice = 'Google Calendar connected successfully. Synced ' . $synced . ' booking(s).';
            if ($failed)
                $notice .= ' Failed: ' . implode(' | ', $failed);
            $_SESSION['carease_calendar_notice'] = $notice;
        } catch (Throwable $e) {
            $_SESSION['carease_calendar_notice'] = $e->getMessage();
        }
        redirect('carease/calendar-oauth');
    }
    public function sync()
    {
        $this->guard();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('POST required');
        }
        if (!hash_equals($this->csrf(), (string) ($_POST['csrf'] ?? ''))) {
            http_response_code(403);
            exit('Invalid form token');
        }
        $id = filter_var($_POST['booking_id'] ?? null, FILTER_VALIDATE_INT);
        $booking = $id ? $this->CarEaseModel->booking($id) : null;
        if (!$booking || !in_array($booking['status'], ['pending', 'approved', 'confirmed', 'ongoing', 'overdue', 'completed'], true)) {
            http_response_code(422);
            exit('Only active reservations may be synced.');
        }
        try {
            require_once __DIR__ . '/../libraries/CarEaseCalendarSync.php';
            $calendar = new CarEaseCalendarSync();
            $result = $calendar->sync($booking, $this->CarEaseModel->car($booking['car_id']));
            $_SESSION['carease_calendar_notice'] = $result === 'created'
                ? 'Calendar event created for booking #' . $id
                : 'Calendar event updated for booking #' . $id;
        } catch (Throwable $e) {
            $_SESSION['carease_calendar_notice'] = 'Calendar sync failed: ' . $e->getMessage();
        }
        redirect('carease/calendar-oauth');
    }

    
public function cleanupPreview()
{
    $this->guard();

    try {
        require_once __DIR__ .
            '/../libraries/CarEaseCalendarSync.php';

        $calendar = new CarEaseCalendarSync();

        $bookingIds = [];

        foreach ($this->CarEaseModel->bookings() ?: [] as $booking) {
            $bookingIds[] = (int) $booking['id'];
        }

        $orphaned = $calendar->findOrphanedEvents($bookingIds);

        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'total' => count($orphaned),
            'orphaned_events' => $orphaned
        ], JSON_PRETTY_PRINT);

    } catch (Throwable $e) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'error' => $e->getMessage()
        ]);
    }
}


    // Delete only the orphaned events reviewed on November 5, 2026.
    public function cleanupDelete()
    {
        $this->guard();

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            exit('POST required.');
        }

        $submittedCsrf = (string) ($_POST['csrf'] ?? '');
        if ($submittedCsrf === '' || !hash_equals($this->csrf(), $submittedCsrf)) {
            http_response_code(403);
            exit('Invalid form token.');
        }

        header('Content-Type: application/json; charset=utf-8');

        try {
            require_once __DIR__ . '/../libraries/CarEaseCalendarSync.php';
            $calendar = new CarEaseCalendarSync();

            // A fixed allowlist prevents deleting newly discovered events.
            $approvedIds = [
                1, 2, 4, 5, 6, 7, 9, 10, 11, 12,
                13, 14, 15, 16, 17, 18, 19, 20,
                21, 22, 31, 32, 39
            ];

            // Rebuild the preview against current database records.
            $validIds = [];
            foreach ($this->CarEaseModel->bookings() ?: [] as $booking) {
                $validIds[] = (int) $booking['id'];
            }
            $orphaned = $calendar->findOrphanedEvents($validIds);

            $deleted = [];
            $skipped = [];
            $failed = [];

            foreach ($orphaned as $event) {
                $id = (int) ($event['booking_id'] ?? 0);
                $eventId = (string) ($event['event_id'] ?? '');
                $expectedId = 'careasebooking' . str_pad((string) $id, 10, '0', STR_PAD_LEFT);

                if (!in_array($id, $approvedIds, true) || $eventId !== $expectedId) {
                    $skipped[] = $id;
                    continue;
                }

                // Do not remove an event if the booking has reappeared.
                if ($this->CarEaseModel->booking($id)) {
                    $skipped[] = $id;
                    continue;
                }

                try {
                    $result = $calendar->delete($id);
                    if ($result === 'deleted') {
                        $deleted[] = $id;
                    } else {
                        $skipped[] = $id;
                    }
                } catch (Throwable $error) {
                    $failed[] = [
                        'booking_id' => $id,
                        'error' => $error->getMessage()
                    ];
                }
            }

            echo json_encode([
                'success' => count($failed) === 0,
                'deleted_count' => count($deleted),
                'deleted_booking_ids' => $deleted,
                'skipped_booking_ids' => $skipped,
                'failed' => $failed
            ], JSON_PRETTY_PRINT);
        } catch (Throwable $error) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $error->getMessage()
            ]);
        }
    }
}
