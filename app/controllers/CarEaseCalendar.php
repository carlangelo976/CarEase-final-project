<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class CarEaseCalendar extends Controller
{
    private const SYNCED_STATUSES = ['pending', 'approved', 'confirmed', 'ongoing', 'overdue', 'completed'];

    public function __construct()
    {
        parent::__construct();
        $this->call->model('CarEaseModel');
        if (session_status() !== PHP_SESSION_ACTIVE)
            session_start();
    }
    private function guard()
    {
        if (empty($_SESSION['carease_user_id']) || ($_SESSION['carease_role'] ?? '') !== 'admin') {
            $this->reject('CarEase admin login required.', 403);
        }
    }
    private function wantsJson()
    {
        return stripos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false
            || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }
    private function reject($message, $code = 400)
    {
        if ($this->wantsJson())
            $this->json(['status' => false, 'error' => $message], $code);
        http_response_code($code);
        exit($message);
    }
    private function json($data, $code = 200)
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
        exit;
    }
    /**
     * CORS for the Vue front-end running on a different port. Credentials are
     * only echoed for local development hosts so an unrelated site cannot read
     * the response with the admin session attached.
     */
    private function cors()
    {
        $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($origin !== '') {
            $allowedHosts = ['localhost', '127.0.0.1', '::1'];
            if (!empty($_SERVER['HTTP_HOST']))
                $allowedHosts[] = (string) parse_url('http://' . $_SERVER['HTTP_HOST'], PHP_URL_HOST);
            $originHost = (string) parse_url($origin, PHP_URL_HOST);
            if (in_array($originHost, $allowedHosts, true)) {
                header('Access-Control-Allow-Origin: ' . $origin);
                header('Access-Control-Allow-Credentials: true');
                header('Vary: Origin');
            }
        }
        header('Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
    private function token()
    {
        if (empty($_SESSION['carease_csrf']))
            $_SESSION['carease_csrf'] = bin2hex(random_bytes(24));
        return $_SESSION['carease_csrf'];
    }
    private function calendar()
    {
        require_once __DIR__ . '/../libraries/CarEaseCalendarSync.php';
        return new CarEaseCalendarSync();
    }
    private function syncedBookings()
    {
        return array_values(array_filter($this->CarEaseModel->bookings() ?: [], static function ($b) {
            return in_array($b['status'], self::SYNCED_STATUSES, true);
        }));
    }
    public function index()
    {
        $this->guard();
        $connected = false;
        $error = null;
        try {
            $calendar = $this->calendar();
            if ($calendar->connected()) {
                $calendar->check();
                $connected = true;
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
        $message = $_SESSION['carease_calendar_message'] ?? $error;
        unset($_SESSION['carease_calendar_message']);
        $this->call->view('carease/calendar', [
            'bookings' => $this->syncedBookings(),
            'csrf' => $this->token(),
            'message' => $message,
            'connected' => $connected,
            'gcalEmbedUrl' => $this->gcalEmbedUrl(),
            'isAdmin' => true,
            'careaseUser' => $_SESSION['carease_name'] ?? null,
        ]);
    }

    /**
     * Embeddable Google Calendar (month view).
     */
    private function gcalEmbedUrl()
    {
        require_once __DIR__ . '/../libraries/CarEaseGoogleCalendar.php';
        return CarEaseGoogleCalendar::embedUrl();
    }
    public function status()
    {
        $this->cors();
        $this->guard();
        $connected = false;
        $error = null;
        try {
            $calendar = $this->calendar();
            if ($calendar->connected()) {
                $calendar->check();
                $connected = true;
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
        $message = $_SESSION['carease_calendar_message'] ?? '';
        unset($_SESSION['carease_calendar_message']);
        $bookings = [];
        foreach ($this->syncedBookings() as $b) {
            $bookings[] = [
                'id' => (int) $b['id'],
                'customer_name' => $b['customer_name'],
                'pickup_date' => $b['pickup_date'],
                'return_date' => $b['return_date'],
                'status' => $b['status'],
            ];
        }
        $this->json([
            'status' => true,
            'connected' => $connected,
            'csrf' => $this->token(),
            'message' => $error ?: $message,
            'bookings' => $bookings,
        ]);
    }
    public function sync()
    {
        $this->cors();
        $this->guard();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            $this->reject('POST required', 405);
        if (!hash_equals($this->token(), (string) ($_POST['csrf'] ?? '')))
            $this->reject('Invalid CSRF token', 403);
        $id = filter_var($_POST['booking_id'] ?? null, FILTER_VALIDATE_INT);
        $booking = $id ? $this->CarEaseModel->booking($id) : null;
        if (!$booking || !in_array($booking['status'], self::SYNCED_STATUSES, true))
            $this->reject('Only active reservations may be synced.', 422);
        try {
            $result = $this->calendar()->sync($booking, $this->CarEaseModel->car($booking['car_id']));
            $message = $result === 'created'
                ? 'Created Google Calendar event for booking #' . $id . '.'
                : 'Updated Google Calendar event for booking #' . $id . '.';
            if ($this->wantsJson())
                $this->json(['status' => true, 'message' => $message]);
            $_SESSION['carease_calendar_message'] = $message;
        } catch (Throwable $e) {
            $message = 'Calendar sync failed: ' . $e->getMessage();
            if ($this->wantsJson())
                $this->json(['status' => false, 'error' => $message], 500);
            $_SESSION['carease_calendar_message'] = $message;
        }
        redirect('carease/calendar');
    }
    public function syncAll()
    {
        $this->cors();
        $this->guard();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
            $this->reject('POST required', 405);
        if (!hash_equals($this->token(), (string) ($_POST['csrf'] ?? '')))
            $this->reject('Invalid CSRF token', 403);
        $synced = 0;
        $failed = [];
        try {
            $calendar = $this->calendar();
            foreach ($this->syncedBookings() as $booking) {
                try {
                    $calendar->sync($booking, $this->CarEaseModel->car($booking['car_id']));
                    $synced++;
                } catch (Throwable $e) {
                    $failed[] = '#' . $booking['id'] . ': ' . $e->getMessage();
                }
            }
        } catch (Throwable $e) {
            $failed[] = $e->getMessage();
        }
        $message = 'Synced ' . $synced . ' booking(s) to Google Calendar.';
        if ($failed)
            $message .= ' Failed: ' . implode(' | ', $failed);
        if ($this->wantsJson())
            $this->json(['status' => empty($failed), 'synced' => $synced, 'message' => $message]);
        $_SESSION['carease_calendar_message'] = $message;
        redirect('carease/calendar');
    }

    
public function cleanupDelete()
{
    $this->cors();
    $this->guard();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $this->reject('POST required.', 405);
    }

    if (!hash_equals(
        $this->token(),
        (string) ($_POST['csrf'] ?? '')
    )) {
        $this->reject('Invalid CSRF token.', 403);
    }

    // IDs confirmed from the previous cleanup preview.
    $approvedIds = [
        1, 2, 4, 5, 6, 7, 9, 10,
        11, 12, 13, 14, 15, 16,
        17, 18, 19, 20, 21, 22,
        31, 32, 39
    ];

    $deleted = [];
    $skipped = [];
    $failed = [];

    try {
        $calendar = $this->calendar();

        $bookingIds = [];
        foreach ($this->CarEaseModel->bookings() ?: [] as $booking) {
            $bookingIds[] = (int) $booking['id'];
        }

        $orphaned = $calendar->findOrphanedEvents($bookingIds);

        foreach ($orphaned as $event) {
            $id = (int) $event['booking_id'];

            if (!in_array($id, $approvedIds, true)) {
                $skipped[] = $id;
                continue;
            }

            // Check the database again before deleting.
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
            } catch (Throwable $e) {
                $failed[] = [
                    'booking_id' => $id,
                    'error' => $e->getMessage()
                ];
            }
        }

        $this->json([
            'status' => empty($failed),
            'deleted_count' => count($deleted),
            'deleted_booking_ids' => $deleted,
            'skipped_booking_ids' => $skipped,
            'failed' => $failed
        ]);

    } catch (Throwable $e) {
        $this->json([
            'status' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

}
