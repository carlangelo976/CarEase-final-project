<?php

class CarEaseEmailNotification
{
    private $config;

    public function __construct()
    {
        $path = __DIR__ . '/carease_email_local.php';

        // Use the local credentials file when available (local WAMP
        // development); otherwise fall back to environment variables
        // (Render). Keys match the local file so both paths behave alike.
        if (is_file($path)) {
            $this->config = require $path;
        } else {
            $this->config = [
                'api_key' => getenv('BREVO_API_KEY') ?: getenv('EMAIL_API_KEY') ?: '',
                'sender_email' => getenv('BREVO_SENDER_EMAIL') ?: getenv('EMAIL_SENDER_EMAIL') ?: '',
                'sender_name' => getenv('BREVO_SENDER_NAME') ?: getenv('EMAIL_SENDER_NAME') ?: '',
            ];
        }

        foreach (['api_key', 'sender_email', 'sender_name'] as $key) {
            if (empty($this->config[$key])) {
                throw new RuntimeException('Missing email setting: ' . $key);
            }
        }
        if (!filter_var($this->config['sender_email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid sender email configuration.');
        }
    }

    public function bookingStatus($booking, $car, $newStatus)
    {
        if (!in_array($newStatus, ['approved', 'rejected', 'cancelled', 'completed'], true)) {
            return false;
        }
        $bookingId = (int) ($booking['id'] ?? 0);
        $vehicle = $this->vehicleName($car);
        $subject = 'CarEase Booking #' . $bookingId . ' - ' . ucfirst($newStatus);
        $message = "Hello " . (string) ($booking['customer_name'] ?? 'Customer') . ",\n\n"
            . "Your CarEase booking #" . $bookingId . " status is now " . strtoupper($newStatus) . ".\n\n"
            . "Vehicle: " . $vehicle . "\n"
            . "Pickup: " . (string) ($booking['pickup_date'] ?? '') . "\n"
            . "Return: " . (string) ($booking['return_date'] ?? '') . "\n"
            . "Total: PHP " . (string) ($booking['total_amount'] ?? '') . "\n\n"
            . ($newStatus === 'approved' ? "Note: Approval is not payment confirmation. Follow the rental team's payment instructions.\n\n" : '')
            . "Thank you,\nCarEase Rentals";

        return $this->send($this->recipient($booking), $subject, $message);
    }

    /**
     * Sends the rental agreement so the customer can review and sign it.
     */
    public function contractAgreement($booking, $contract, $car = null)
    {
        $bookingId = (int) ($booking['id'] ?? 0);
        $subject = 'CarEase Rental Agreement #' . $bookingId . ' - Please review and sign';
        $message = "Hello " . (string) ($booking['customer_name'] ?? 'Customer') . ",\n\n"
            . "Your rental agreement is ready. Please review the terms below and sign it from your CarEase account.\n\n"
            . "Contract No: " . (string) ($contract['contract_no'] ?? '') . "\n"
            . "Vehicle: " . $this->vehicleName($car) . "\n"
            . "Pickup: " . (string) ($booking['pickup_date'] ?? '') . "\n"
            . "Return: " . (string) ($booking['return_date'] ?? '') . "\n"
            . "Total: PHP " . (string) ($booking['total_amount'] ?? '') . "\n\n"
            . "AGREEMENT TERMS\n"
            . "----------------------------------------\n"
            . (string) ($contract['terms'] ?? '') . "\n"
            . "----------------------------------------\n\n"
            . "A valid government-issued ID must be verified before the vehicle can be released.\n\n"
            . "Sign here: " . $this->siteUrl('carease/contract/' . $bookingId) . "\n\n"
            . "Thank you,\nCarEase Rentals";

        return $this->send($this->recipient($booking), $subject, $message);
    }

    public function contractSigned($booking, $contract, $car = null)
    {
        $bookingId = (int) ($booking['id'] ?? 0);
        $subject = 'CarEase Rental Agreement #' . $bookingId . ' - Signed';
        $message = "Hello " . (string) ($booking['customer_name'] ?? 'Customer') . ",\n\n"
            . "We received your signed rental agreement.\n\n"
            . "Contract No: " . (string) ($contract['contract_no'] ?? '') . "\n"
            . "Signed by: " . (string) ($contract['customer_signature'] ?? '') . "\n"
            . "Vehicle: " . $this->vehicleName($car) . "\n"
            . "Pickup: " . (string) ($booking['pickup_date'] ?? '') . "\n"
            . "Return: " . (string) ($booking['return_date'] ?? '') . "\n\n"
            . "The vehicle will be released once your valid ID has been verified by our staff.\n\n"
            . "Thank you,\nCarEase Rentals";

        return $this->send($this->recipient($booking), $subject, $message);
    }

    /**
     * Reminds the customer that the vehicle is due back tomorrow.
     */
    public function returnReminder($booking, $car = null)
    {
        $bookingId = (int) ($booking['id'] ?? 0);
        $due = (string) ($booking['return_date'] ?? '');
        $subject = 'CarEase Reminder: return due tomorrow (' . $due . ')';
        $message = "Hello " . (string) ($booking['customer_name'] ?? 'Customer') . ",\n\n"
            . "This is a reminder that your rental is due back TOMORROW, " . $due . ".\n\n"
            . "Booking: #" . $bookingId . "\n"
            . "Vehicle: " . $this->vehicleName($car) . "\n"
            . "Pickup: " . (string) ($booking['pickup_date'] ?? '') . "\n"
            . "Due date: " . $due . "\n\n"
            . "Please return the vehicle on time and complete the post-rental inspection. "
            . "Late returns may incur additional charges.\n\n"
            . "Thank you,\nCarEase Rentals";

        return $this->send($this->recipient($booking), $subject, $message);
    }

    /**
     * Strong notice sent once the booking is past its return date.
     */
    public function overdueNotice($booking, $car = null)
    {
        $bookingId = (int) ($booking['id'] ?? 0);
        $due = (string) ($booking['return_date'] ?? '');
        $subject = 'CarEase OVERDUE NOTICE: return immediately (' . $due . ')';
        $message = "Hello " . (string) ($booking['customer_name'] ?? 'Customer') . ",\n\n"
            . "*** OVERDUE RETURN NOTICE ***\n\n"
            . "Our records show that booking #" . $bookingId . " was due on " . $due . " and has not been returned.\n\n"
            . "Vehicle: " . $this->vehicleName($car) . "\n"
            . "Pickup: " . (string) ($booking['pickup_date'] ?? '') . "\n"
            . "Due date: " . $due . "\n\n"
            . "Please return the vehicle immediately. Additional late-return charges may apply and the "
            . "vehicle is needed for the next reservation.\n\n"
            . "If you have already returned the vehicle, please contact our team so we can update the record.\n\n"
            . "CarEase Rentals";

        return $this->send($this->recipient($booking), $subject, $message);
    }
 
    // EMAIL OTP VERIFICATION
public function sendVerificationOtp($email, $name, $otp)
{
    $subject = 'CarEase Rentals - Email Verification Code';

    $message = "Hello " . $name . ",\n\n"
        . "Thank you for registering with CarEase Rentals!\n\n"
        . "Your verification code is: " . $otp . "\n\n"
        . "This code will expire in 5 minutes.\n"
        . "Do not share this code with anyone.\n\n"
        . "If you did not create an account, you can ignore this email.\n\n"
        . "Thank you,\nCarEase Rentals";

    return $this->send($email, $subject, $message);
}
    private function vehicleName($car)
    {
        $vehicle = trim((string) ($car['brand'] ?? '') . ' ' . (string) ($car['name'] ?? ''));
        return $vehicle === '' ? 'Vehicle' : $vehicle;
    }

    private function recipient($booking)
    {
        $recipient = trim((string) ($booking['email'] ?? ''));
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Booking recipient email is invalid.');
        }
        return $recipient;
    }

    private function siteUrl($path)
    {
        if (function_exists('site_url')) {
            return site_url($path);
        }
        return $path;
    }

    private function send($recipient, $subject, $message)
    {
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Booking recipient email is invalid.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required.');
        }

        $payload = [
            'sender' => ['name' => $this->config['sender_name'], 'email' => $this->config['sender_email']],
            'to' => [['email' => $recipient]],
            'subject' => $subject,
            'textContent' => $message,
        ];
        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => [
                'api-key: ' . $this->config['api_key'],
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($response === false || $httpCode < 200 || $httpCode >= 300) {
            $details = json_decode((string) $response, true);
            $reason = is_array($details) ? ($details['message'] ?? 'API request failed') : ($error ?: 'API request failed');
            throw new RuntimeException('Email API HTTP ' . $httpCode . ': ' . $reason);
        }
        return true;
    }
}
