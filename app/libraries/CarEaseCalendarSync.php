<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class CarEaseCalendarSync
{
    private $cfg;
    private $token;

    public function __construct()
    {
        $path = __DIR__ . '/../config/carease_oauth_local.php';

        // Use local configuration when available (local WAMP development).
        if (is_file($path)) {
            $cfg = require $path;
        } else {
            // Use Render environment variables. The token file matches
            // CarEaseCalendarOAuth so both share the same connection.
            $cfg = [
                'client_id' => getenv('GOOGLE_CLIENT_ID') ?: '',
                'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
                'calendar_id' => getenv('GOOGLE_CALENDAR_ID') ?: '',
                'token_file' => '/var/www/html/runtime/google_calendar_token.json',
            ];
        }

        foreach (['client_id', 'client_secret', 'calendar_id', 'token_file'] as $key) {
            if (empty($cfg[$key]))
                throw new RuntimeException('Missing OAuth configuration: ' . $key);
        }
        $this->cfg = $cfg;
        $this->token = $this->readToken();
    }

    public function connected()
    {
        return is_array($this->token)
            && (!empty($this->token['access_token']) || !empty($this->token['refresh_token']));
    }

    /**
     * Verifies the saved token can still be used, refreshing it when needed.
     */
    public function check()
    {
        $this->accessToken();
        return true;
    }

    public function sync(array $booking, $car = null)
    {
        $token = $this->accessToken();
        $id = (int) ($booking['id'] ?? 0);
        if ($id < 1)
            throw new RuntimeException('Invalid booking for calendar sync.');

        $eventId = $this->eventId($id);
        $base = $this->eventsUrl();
        $headers = ['Authorization: Bearer ' . $token, 'Content-Type: application/json'];
        // Google Calendar treats an all-day event's end date as exclusive, so the
        // rental return day must be pushed forward by one day.
        $endExclusive = (new DateTimeImmutable((string) $booking['return_date']))
            ->modify('+1 day')
            ->format('Y-m-d');
        $event = [
            'id' => $eventId,
            'summary' => 'CarEase Rental #' . $id . ' - ' . ($car ? $car['brand'] . ' ' . $car['name'] : 'Vehicle'),
            'description' => implode("\n", [
                'Booking #' . $id,
                'Status: ' . ($booking['status'] ?? 'unknown'),
                'Client: ' . ($booking['customer_name'] ?? ''),
                'Email: ' . ($booking['email'] ?? ''),
                'Phone: ' . ($booking['phone'] ?? ''),
                'Total: ' . ($booking['total_amount'] ?? ''),
            ]),
            'start' => ['date' => $booking['pickup_date']],
            'end' => ['date' => $endExclusive],
        ];
        $json = json_encode($event, JSON_THROW_ON_ERROR);

        try {
            $this->request('GET', $base . '/' . $eventId, ['Authorization: Bearer ' . $token]);
            $this->request('PUT', $base . '/' . $eventId, $headers, $json);
            return 'updated';
        } catch (RuntimeException $e) {
            if ((int) $e->getCode() !== 404)
                throw $e;
            try {
                $this->request('POST', $base, $headers, $json);
                return 'created';
            } catch (RuntimeException $createError) {
                if ((int) $createError->getCode() !== 409)
                    throw $createError;
                $this->request('PUT', $base . '/' . $eventId, $headers, $json);
                return 'updated';
            }
        }
    }

    public function delete($bookingId)
    {
        $token = $this->accessToken();
        $url = $this->eventsUrl() . '/' . $this->eventId((int) $bookingId);
        try {
            $this->request('DELETE', $url, ['Authorization: Bearer ' . $token]);
            return 'deleted';
        } catch (RuntimeException $e) {
            $code = (int) $e->getCode();
            if ($code === 404 || $code === 410)
                return 'absent';
            throw $e;
        }
    }

    
public function findOrphanedEvents(array $validBookingIds)
{
    $token = $this->accessToken();

    $validIds = array_fill_keys(
        array_map('intval', $validBookingIds),
        true
    );

    $orphaned = [];
    $pageToken = null;

    do {
        $url = $this->eventsUrl() .
            '?maxResults=250&showDeleted=false';

        if ($pageToken) {
            $url .= '&pageToken=' . rawurlencode($pageToken);
        }

        $result = $this->request(
            'GET',
            $url,
            ['Authorization: Bearer ' . $token]
        );

        foreach ($result['items'] ?? [] as $event) {
            $eventId = (string) ($event['id'] ?? '');

            if (!preg_match(
                '/^careasebooking0*([1-9][0-9]*)$/',
                $eventId,
                $matches
            )) {
                continue;
            }

            $bookingId = (int) $matches[1];

            if (!isset($validIds[$bookingId])) {
                $orphaned[] = [
                    'booking_id' => $bookingId,
                    'event_id' => $eventId,
                    'summary' => $event['summary'] ?? ''
                ];
            }
        }

        $pageToken = $result['nextPageToken'] ?? null;

    } while ($pageToken);

    return $orphaned;
}

    private function eventId($id)
    {
        return 'careasebooking' . str_pad((string) $id, 10, '0', STR_PAD_LEFT);
    }

    private function eventsUrl()
    {
        return 'https://www.googleapis.com/calendar/v3/calendars/' . rawurlencode($this->cfg['calendar_id']) . '/events';
    }

    private function readToken()
    {
        $path = $this->cfg['token_file'];
        if (!is_file($path))
            return null;
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    private function writeToken(array $data)
    {
        $path = $this->cfg['token_file'];
        if (file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) === false)
            throw new RuntimeException('Cannot save OAuth token file.');
        @chmod($path, 0600);
        $this->token = $data;
    }

    private function accessToken()
    {
        if (empty($this->token['access_token']))
            throw new RuntimeException('Connect Google Calendar first.');
        if ((int) ($this->token['expires_at'] ?? 0) > time())
            return $this->token['access_token'];
        if (empty($this->token['refresh_token']))
            throw new RuntimeException('Refresh token unavailable. Reconnect Google Calendar.');

        try {
            $fresh = $this->request('POST', 'https://oauth2.googleapis.com/token', [
                'Content-Type: application/x-www-form-urlencoded',
            ], http_build_query([
                'client_id' => $this->cfg['client_id'],
                'client_secret' => $this->cfg['client_secret'],
                'refresh_token' => $this->token['refresh_token'],
                'grant_type' => 'refresh_token',
            ]));
        } catch (RuntimeException $e) {
            if ((int) $e->getCode() === 400)
                throw new RuntimeException('Google authorization has expired or been revoked. Reconnect Google Calendar to sync again.', 401, $e);
            throw $e;
        }
        if (empty($fresh['access_token']))
            throw new RuntimeException('Google did not return an access token.');

        $this->token = array_merge($this->token, $fresh);
        $this->token['expires_at'] = time() + max(0, (int) ($fresh['expires_in'] ?? 3600)) - 90;
        $this->writeToken($this->token);
        return $this->token['access_token'];
    }

    private function request($method, $url, $headers = [], $body = null)
    {
        if (!function_exists('curl_init'))
            throw new RuntimeException('Enable PHP cURL in WAMP.');

        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        // Only pin the custom CA bundle when it exists (local WAMP); otherwise
        // cURL uses the system CA bundle shipped with the PHP Docker image.
        if (is_file('C:/wamp64/certs/cacert.pem')) {
            $options[CURLOPT_CAINFO] = 'C:/wamp64/certs/cacert.pem';
        }

        curl_setopt_array($ch, $options);
        if ($body !== null)
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $decoded = json_decode((string) $raw, true);
        if ($raw === false || $status < 200 || $status >= 300) {
            if ($status === 401)
                throw new RuntimeException('Google authorization has expired or been revoked. Reconnect Google Calendar to sync again.', 401);
            $msg = is_array($decoded)
                ? ($decoded['error_description']
                    ?? ($decoded['error']['message']
                        ?? (is_string($decoded['error'] ?? null) ? $decoded['error'] : 'Request failed')))
                : $error;
            throw new RuntimeException('Google request HTTP ' . $status . ': ' . $msg, $status);
        }
        return is_array($decoded) ? $decoded : [];
    }
}
