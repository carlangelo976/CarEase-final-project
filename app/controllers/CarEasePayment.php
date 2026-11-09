<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class CarEasePayment extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->model('CarEaseModel');
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    }

    private function ownerBooking($id)
    {
        if (empty($_SESSION['carease_user_id'])) { redirect('carease/login'); exit; }
        $booking = $this->CarEaseModel->booking((int)$id);
        if (!$booking || (int)$booking['user_id'] !== (int)$_SESSION['carease_user_id']) {
            http_response_code(404); exit('Booking not found.');
        }
        return $booking;
    }

    private function config()
    {
        $cfg = require __DIR__ . '/../config/carease_paymongo_local.php';
        if (empty($cfg['secret_key']) || strpos($cfg['secret_key'], 'sk_test_') !== 0) {
            throw new RuntimeException('Set your PayMongo test secret key in the local config file.');
        }
        return $cfg;
    }

    private function request($method, $path, $body = null)
    {
        $cfg = $this->config();
        if (!function_exists('curl_init')) throw new RuntimeException('Enable PHP cURL extension in WAMP.');
        $url = 'https://api.paymongo.com/v1/' . ltrim($path, '/');
        error_log('CarEase PayMongo URL: ' . $url);
        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => ['Authorization: Basic ' . base64_encode($cfg['secret_key'] . ':'), 'Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_TIMEOUT => 25,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ];
        if ($body !== null) {
            $payload = json_encode($body, JSON_THROW_ON_ERROR);
            error_log('CarEase PayMongo payload: ' . $payload);
            $options[CURLOPT_POSTFIELDS] = $payload;
        }
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($response === false) throw new RuntimeException('PayMongo connection failed: ' . $error);
        $json = json_decode($response, true);
        if ($http < 200 || $http >= 300 || !is_array($json)) {
            error_log('CarEase PayMongo HTTP ' . $http . ': ' . substr($response, 0, 1500));
            throw new RuntimeException('PayMongo request failed (HTTP ' . $http . '). Check WAMP logs.');
        }
        return $json;
    }

    private function csrf()
    {
        if (empty($_SESSION['carease_csrf']) || !hash_equals($_SESSION['carease_csrf'], (string)($_POST['csrf'] ?? ''))) {
            http_response_code(403); exit('Invalid form token.');
        }
    }

    private function methodLabel(array $attrs)
    {
        $src = is_array($attrs['source'] ?? null) ? $attrs['source'] : null;
        if ($src === null || empty($src['type'])) {
            $details = $attrs['payment_method_details'] ?? null;
            if (!is_array($details) && isset($attrs['payment_method'])) {
                $pm = $attrs['payment_method'];
                $details = is_array($pm) ? ($pm['attributes'] ?? $pm) : null;
            }
            $src = is_array($details) ? $details : null;
        }
        $type = $src !== null ? (string)($src['type'] ?? '') : '';
        if ($type === '') return null;
        if (in_array($type, ['card', 'credit_card', 'debit_card'], true)) {
            $brand = trim(strtoupper((string)($src['brand'] ?? '')));
            $last4 = preg_replace('/\D/', '', (string)($src['last4'] ?? ''));
            return ($brand !== '' ? $brand : 'CARD') . ($last4 !== '' ? ' ****' . $last4 : '');
        }
        $names = [
            'gcash' => 'GCash', 'qrph' => 'QR Ph', 'grab_pay' => 'GrabPay',
            'paymaya' => 'Maya (PayMaya)', 'billease' => 'BillEase',
        ];
        return $names[$type] ?? ucwords(str_replace('_', ' ', $type));
    }

    private function confirm($payment)
    {
        if ($payment['status'] === 'paid') return true;
        $session = $this->request('GET', 'checkout_sessions/' . rawurlencode($payment['checkout_session_id']));
        $data = $session['data'] ?? [];
        if (($data['id'] ?? '') !== $payment['checkout_session_id'] || !empty($data['attributes']['livemode'])) {
            throw new RuntimeException('Unexpected checkout session.');
        }
        $attrsSess = $data['attributes'] ?? [];
        if (isset($attrsSess['payment_intent']['attributes']['payments'])) {
            $payments = $attrsSess['payment_intent']['attributes']['payments'] ?? [];
            foreach ($payments as $p) {
                $a = $p['attributes'] ?? $p;
                if (($a['status'] ?? '') === 'paid' && (int)($a['amount'] ?? 0) === (int)$payment['amount_centavos']) {
                    $update = ['status' => 'paid', 'paid_at' => date('Y-m-d H:i:s')];
                    $label = $this->methodLabel($a);
                    if ($label !== null) $update['payment_method'] = substr($label, 0, 64);
                    $this->CarEaseModel->updatePayment($payment['id'], $update);
                    return true;
                }
            }
        } else {
            $payments = $attrsSess['payments'] ?? [];
            foreach ($payments as $p) {
                $a = $p['attributes'] ?? $p;
                if (($a['status'] ?? '') === 'paid' && (int)($a['amount'] ?? 0) === (int)$payment['amount_centavos']) {
                    $update = ['status' => 'paid', 'paid_at' => date('Y-m-d H:i:s')];
                    $label = $this->methodLabel($a);
                    if ($label !== null) $update['payment_method'] = substr($label, 0, 64);
                    $this->CarEaseModel->updatePayment($payment['id'], $update);
                    return true;
                }
            }
        }
        if (($attrsSess['status'] ?? '') === 'expired') {
            $this->CarEaseModel->updatePayment($payment['id'], ['status' => 'expired']);
        }
        return false;
    }

    public function checkout($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('POST required.'); }
        $this->csrf();
        $booking = $this->ownerBooking($id);
        if (!in_array($booking['status'], ['approved', 'confirmed'], true)) {
            http_response_code(422); exit('Booking must be approved before payment.');
        }
        try {
            $rows = $this->CarEaseModel->paymentsForBooking($id) ?: [];
            foreach (array_reverse($rows) as $row) {
                if ($row['status'] === 'paid') { redirect('carease/my-bookings'); return; }
                if ($row['status'] === 'pending') {
                    if ($this->confirm($row)) { redirect('carease/my-bookings'); return; }
                    $latest = $this->CarEaseModel->payment($row['id']);
                    if ($latest['status'] === 'pending') { header('Location: ' . $row['checkout_url']); exit; }
                }
            }
            $amount = (int)round((float)$booking['total_amount'] * 100);
            if ($amount < 100) throw new RuntimeException('Invalid booking amount.');
            $cfg = $this->config();
            $base = site_url('carease/payment/return/' . (int)$id);
            $payload = ['data' => ['attributes' => [
                'line_items' => [[ 'name' => 'CarEase Booking #' . (int)$id, 'amount' => $amount, 'currency' => 'PHP', 'quantity' => 1 ]],
                'payment_method_types' => $cfg['payment_method_types'],
                'success_url' => $base,
                'cancel_url' => site_url('carease/my-bookings'),
                'reference_number' => 'CAREASE-' . (int)$id . '-' . bin2hex(random_bytes(4)),
                'description' => 'Car rental booking #' . (int)$id,
            ]]];
            $created = $this->request('POST', 'checkout_sessions', $payload);
            $sessionId = $created['data']['id'] ?? '';
            $attrs = $created['data']['attributes'] ?? [];
            $url = $attrs['checkout_url'] ?? ($attrs['redirect']['url'] ?? '');
            if (!preg_match('/^cs_[A-Za-z0-9_-]+$/', $sessionId) || !filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_HOST) !== 'checkout.paymongo.com') {
                throw new RuntimeException('Invalid PayMongo checkout response.');
            }
            $this->CarEaseModel->addPayment([
                'booking_id' => (int)$id, 'checkout_session_id' => $sessionId,
                'checkout_url' => $url, 'amount_centavos' => $amount,
                'status' => 'pending', 'created_at' => date('Y-m-d H:i:s')
            ]);
            header('Location: ' . $url); exit;
        } catch (Throwable $e) {
            error_log('CarEase payment: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            http_response_code(502);
            exit('Payment could not be started or verified. Please check PayMongo test configuration and server logs. ' . $e->getMessage());
        }
    }

    public function result($id)
    {
        $booking = $this->ownerBooking($id);
        $rows = $this->CarEaseModel->paymentsForBooking($id) ?: [];
        $paid = false;
        $payment = null;
        foreach (array_reverse($rows) as $row) {
            if ($row['status'] === 'paid') { $paid = true; $payment = $row; break; }
            if ($row['status'] === 'pending') {
                try {
                    if ($this->confirm($row)) {
                        $paid = true;
                        $payment = $this->CarEaseModel->payment($row['id']) ?: $row;
                        break;
                    }
                } catch (Throwable $e) { error_log('CarEase payment verify: ' . $e->getMessage()); }
            }
            if ($payment === null) $payment = $row;
        }
        if (empty($_SESSION['carease_csrf'])) $_SESSION['carease_csrf'] = bin2hex(random_bytes(24));
        $this->call->view('carease/payment_result', [
            'paid' => $paid,
            'bookingId' => (int)$id,
            'payment' => $payment,
            'amount' => $payment !== null
                ? (float)$payment['amount_centavos'] / 100
                : (float)$booking['total_amount'],
            'isAdmin' => (($_SESSION['carease_role'] ?? '') === 'admin'),
            'careaseUser' => $_SESSION['carease_name'] ?? null,
            'csrf' => $_SESSION['carease_csrf'],
        ]);
    }
}
