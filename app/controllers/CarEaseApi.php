<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class CarEaseApi extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->model('CarEaseModel');

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    private function respond($data, $code = 200)
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        $allowedOrigins = [
            'http://127.0.0.1:5174',
            'http://localhost:5174'
        ];

        if (in_array($origin, $allowedOrigins, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
            header('Vary: Origin');
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        http_response_code($code);
        echo json_encode($data);
    }

    private function csrf()
    {
        if (empty($_SESSION['carease_api_csrf'])) {
            $_SESSION['carease_api_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['carease_api_csrf'];
    }

    private function validCsrf()
    {
        return hash_equals(
            $this->csrf(),
            (string) $this->io->post('csrf')
        );
    }

    private function currentUser()
    {
        if (empty($_SESSION['carease_user_id'])) {
            return null;
        }

        $user = $this->CarEaseModel->user((int) $_SESSION['carease_user_id']);
        if ($user && ($user['role'] ?? '') === 'client' && empty($user['email_verified_at'])) {
            return null;
        }
        return $user;
    }

    private function requireAdmin()
    {
        $user = $this->currentUser();

        if (!$user) {
            $this->respond(['error' => 'Login required'], 401);
            return false;
        }

        if (($user['role'] ?? '') !== 'admin') {
            $this->respond(['error' => 'Admin access required'], 403);
            return false;
        }

        return true;
    }

    // Allow Admin and Staff to manage booking records and statuses.
    // All other admin-only endpoints still use requireAdmin().
    private function requireAdminOrStaff()
    {
        $user = $this->currentUser();

        if (!$user) {
            $this->respond(['error' => 'Login required'], 401);
            return false;
        }

        if (!in_array(($user['role'] ?? ''), ['admin', 'staff'], true)) {
            $this->respond(['error' => 'Admin or Staff access required'], 403);
            return false;
        }

        return true;
    }

    // SESSION

    public function session()
    {
        $record = $this->currentUser();
        if ($record && ($record['role'] ?? '') === 'client' && empty($record['email_verified_at'])) {
            unset($_SESSION['carease_user_id'], $_SESSION['carease_name'], $_SESSION['carease_role']);
            $record = null;
        }
        $user = null;

        if ($record) {
            $user = [
                'id' => (int) $record['id'],
                'name' => $record['full_name'],
                'email' => $record['email'],
                'role' => $record['role'],
                'id_status' => ($record['role'] === 'client') ? ($record['id_status'] ?? 'not_submitted') : null
            ];
        }

        $this->respond([
            'user' => $user,
            'csrf' => $this->csrf()
        ]);
    }

    // LOGIN

    public function login()
    {
        if (!$this->validCsrf()) {
            $this->respond(['error' => 'Invalid CSRF token'], 403);
            return;
        }

        $email = strtolower(trim((string) $this->io->post('email')));
        $password = (string) $this->io->post('password');
        $user = $this->CarEaseModel->userByEmail($email);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->respond(['error' => 'Invalid email or password'], 401);
            return;
        }

        if (($user['role'] ?? '') === 'client' && empty($user['email_verified_at'])) {
            $this->respond(['error' => 'Please verify your email before logging in.', 'verification_required' => true], 403);
            return;
        }

        session_regenerate_id(true);

        $_SESSION['carease_user_id'] = (int) $user['id'];
        $_SESSION['carease_name'] = $user['full_name'];
        $_SESSION['carease_role'] = $user['role'];

        unset($_SESSION['carease_api_csrf']);

        $this->respond([
            'user' => [
                'id' => (int) $user['id'],
                'name' => $user['full_name'],
                'email' => $user['email'],
                'role' => $user['role']
            ],
            'csrf' => $this->csrf()
        ]);
    }

    // REGISTER

    public function register()
    {
        if (!$this->validCsrf()) {
            $this->respond(['error' => 'Invalid CSRF token'], 403);
            return;
        }

        $name = trim((string) $this->io->post('full_name'));
        $email = strtolower(trim((string) $this->io->post('email')));
        $password = (string) $this->io->post('password');
        $confirm = (string) $this->io->post('confirm_password');

        if (
            mb_strlen($name) < 2 ||
            mb_strlen($name) > 120 ||
            preg_match('/[0-9]/u', $name)
        ) {
            $error = 'Enter a valid name without numbers';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match';
        } elseif ($this->CarEaseModel->userByEmail($email)) {
            $error = 'Email already registered';
        }

        if (isset($error)) {
            $this->respond(['error' => $error], 422);
            return;
        }

        try {
            $this->CarEaseModel->addUser([
                'full_name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'client',
                'email_verified_at' => null,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $user = $this->CarEaseModel->userByEmail($email);
            if (!$user) {
                throw new RuntimeException('Created user not found.');
            }
            $this->issueEmailOtp($user);
            $this->respond(['message' => 'Account created. Check your email for the OTP.', 'verification_required' => true], 201);
        } catch (Throwable $e) {
            error_log('CarEase registration OTP: ' . $e->getMessage());
            $this->respond(['error' => 'Could not complete registration or send OTP. If registered, use Resend OTP.'], 500);
        }
    }

    // EMAIL OTP VERIFICATION

    private function issueEmailOtp($user)
    {
        $userId = (int) $user['id'];
        $otp = (string) random_int(100000, 999999);
        $now = date('Y-m-d H:i:s');
        $data = [
            'otp_hash' => password_hash($otp, PASSWORD_DEFAULT),
            'expires_at' => date('Y-m-d H:i:s', time() + 300),
            'attempts' => 0,
            'last_sent_at' => $now
        ];
        $existing = $this->CarEaseModel->emailOtp($userId);
        if ($existing) {
            $this->CarEaseModel->updateEmailOtp($userId, $data);
        } else {
            $data['user_id'] = $userId;
            $data['created_at'] = $now;
            $this->CarEaseModel->addEmailOtp($data);
        }
        require_once __DIR__ . '/../config/carease_email_notification.php';
        $mailer = new CarEaseEmailNotification();
        if (!$mailer->sendVerificationOtp($user['email'], $user['full_name'], $otp)) {
            throw new RuntimeException('OTP email sending failed.');
        }
    }

    public function verifyEmail()
    {
        if (!$this->validCsrf()) {
            $this->respond(['error' => 'Invalid CSRF token'], 403);
            return;
        }
        $email = strtolower(trim((string) $this->io->post('email')));
        $otp = trim((string) $this->io->post('otp'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[0-9]{6}$/', $otp)) {
            $this->respond(['error' => 'Invalid email or OTP.'], 422);
            return;
        }
        $user = $this->CarEaseModel->userByEmail($email);
        if (!$user || ($user['role'] ?? '') !== 'client') {
            $this->respond(['error' => 'Invalid verification request.'], 422);
            return;
        }
        if (!empty($user['email_verified_at'])) {
            $this->respond(['message' => 'Email already verified.']);
            return;
        }
        $record = $this->CarEaseModel->emailOtp((int) $user['id']);
        if (!$record) {
            $this->respond(['error' => 'No OTP found. Please resend.'], 422);
            return;
        }
        if (strtotime($record['expires_at']) <= time()) {
            $this->respond(['error' => 'OTP expired. Please resend.'], 422);
            return;
        }
        if ((int) $record['attempts'] >= 5) {
            $this->respond(['error' => 'Too many attempts. Please resend OTP.'], 429);
            return;
        }
        if (!password_verify($otp, $record['otp_hash'])) {
            $this->CarEaseModel->updateEmailOtp((int) $user['id'], [
                'attempts' => (int) $record['attempts'] + 1
            ]);
            $this->respond(['error' => 'Incorrect OTP.'], 422);
            return;
        }
        try {
            $this->CarEaseModel->updateUser((int) $user['id'], [
                'email_verified_at' => date('Y-m-d H:i:s')
            ]);
            $updated = $this->CarEaseModel->user((int) $user['id']);
            if (!$updated || empty($updated['email_verified_at'])) {
                throw new RuntimeException('Verification status was not saved.');
            }
            $this->CarEaseModel->deleteEmailOtp((int) $user['id']);
            $this->respond(['message' => 'Email verified successfully.']);
        } catch (Throwable $e) {
            error_log('CarEase OTP verify: ' . $e->getMessage());
            $this->respond(['error' => 'Unable to complete verification.'], 500);
        }
    }

    public function resendOtp()
    {
        if (!$this->validCsrf()) {
            $this->respond(['error' => 'Invalid CSRF token'], 403);
            return;
        }
        $email = strtolower(trim((string) $this->io->post('email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->respond(['error' => 'Invalid email.'], 422);
            return;
        }
        $user = $this->CarEaseModel->userByEmail($email);
        // Generic response prevents account discovery through the resend endpoint.
        if (!$user || ($user['role'] ?? '') !== 'client' || !empty($user['email_verified_at'])) {
            $this->respond(['message' => 'If verification is pending, a code can be requested.']);
            return;
        }
        $record = $this->CarEaseModel->emailOtp((int) $user['id']);
        if ($record && !empty($record['last_sent_at']) && time() - strtotime($record['last_sent_at']) < 60) {
            $this->respond(['error' => 'Please wait 60 seconds before resending.'], 429);
            return;
        }
        try {
            $this->issueEmailOtp($user);
            $this->respond(['message' => 'A new verification code has been sent.']);
        } catch (Throwable $e) {
            error_log('CarEase resend OTP: ' . $e->getMessage());
            $this->respond(['error' => 'Unable to resend OTP. Please try again.'], 500);
        }
    }

    // LOGOUT

    public function logout()
    {
        if (empty($_SESSION['carease_user_id'])) {
            $this->respond(['error' => 'Login required'], 401);
            return;
        }

        if (!$this->validCsrf()) {
            $this->respond(['error' => 'Invalid CSRF token'], 403);
            return;
        }

        unset(
            $_SESSION['carease_user_id'],
            $_SESSION['carease_name'],
            $_SESSION['carease_role'],
            $_SESSION['carease_api_csrf']
        );

        session_regenerate_id(true);

        $this->respond([
            'message' => 'Logged out',
            'csrf' => $this->csrf()
        ]);
    }


    // CLIENT VALID ID: Vue API (same users table as PHP portal)
    public function validIdStatus()
    {
        $user = $this->currentUser();
        if (!$user || ($user['role'] ?? '') !== 'client') {
            $this->respond(['error' => 'Client login required'], 401);
            return;
        }
        $this->respond([
            'valid_id' => [
                'id' => (int) $user['id'],
                'id_type' => (string) ($user['id_type'] ?? ''),
                'id_number' => (string) ($user['id_number'] ?? ''),
                'id_status' => (string) ($user['id_status'] ?? 'not_submitted'),
                'id_notes' => (string) ($user['id_notes'] ?? ''),
                'id_verified_at' => $user['id_verified_at'] ?? null,
                'has_photo' => !empty($user['id_image']),
            ]
        ]);
    }

    public function submitValidId()
    {
        $user = $this->currentUser();
        if (!$user || ($user['role'] ?? '') !== 'client') {
            $this->respond(['error' => 'Client login required'], 401);
            return;
        }
        if (!$this->validCsrf()) {
            $this->respond(['error' => 'Invalid CSRF token'], 403);
            return;
        }

        $type = trim((string) ($_POST['id_type'] ?? ''));
        $number = trim((string) ($_POST['id_number'] ?? ''));
        $types = ['Philippine Passport', "Driver's License", 'UMID', 'PhilHealth ID', 'Postal ID', 'PRC ID', 'National ID'];
        if (
            !in_array($type, $types, true) ||
            !preg_match('/^[A-Za-z0-9#\- ]{3,60}$/', $number)
        ) {
            $this->respond(['error' => 'Select a valid ID type and enter a valid ID number.'], 422);
            return;
        }

        $file = $_FILES['id_image'] ?? null;
        if (!$file || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->respond(['error' => 'Please upload an ID photo (JPG, PNG, WEBP or GIF).'], 422);
            return;
        }
        if (
            !is_uploaded_file($file['tmp_name']) ||
            (int) ($file['size'] ?? 0) <= 0 ||
            (int) ($file['size'] ?? 0) > 5 * 1024 * 1024
        ) {
            $this->respond(['error' => 'Invalid ID photo or file larger than 5 MB.'], 422);
            return;
        }
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $imageInfo = @getimagesize($file['tmp_name']);
        $mime = (string) ($imageInfo['mime'] ?? '');
        if (!isset($allowed[$mime])) {
            $this->respond(['error' => 'Only JPG, PNG, WEBP and GIF photos are allowed.'], 422);
            return;
        }
        $dir = ROOT_DIR . 'storage' . DIRECTORY_SEPARATOR . 'carease_ids';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            $this->respond(['error' => 'ID storage folder is not writable.'], 500);
            return;
        }
        $name = 'id-' . (int) $user['id'] . '-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
        $target = $dir . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            $this->respond(['error' => 'Could not save ID photo.'], 500);
            return;
        }
        try {
            $this->CarEaseModel->updateUser((int) $user['id'], [
                'id_type' => $type,
                'id_number' => $number,
                'id_image' => $name,
                'id_status' => 'pending',
                'id_notes' => null,
                'id_verified_at' => null,
            ]);
            $updated = $this->CarEaseModel->user((int) $user['id']);
            if (!$updated || ($updated['id_image'] ?? '') !== $name) {
                throw new RuntimeException('ID submission was not saved.');
            }
            $old = basename((string) ($user['id_image'] ?? ''));
            if ($old && $old !== $name && is_file($dir . DIRECTORY_SEPARATOR . $old)) {
                @unlink($dir . DIRECTORY_SEPARATOR . $old);
            }
            $this->respond(['message' => 'Your ID was submitted for admin verification.']);
        } catch (Throwable $e) {
            @unlink($target);
            error_log('CarEase Vue ID upload: ' . $e->getMessage());
            $this->respond(['error' => 'Unable to save ID verification.'], 500);
        }
    }


    // Vue Admin Valid ID verification endpoints.
    public function adminValidIds()
    {
        if (!$this->requireAdmin())
            return;
        $records = [];
        foreach (($this->CarEaseModel->usersWithValidId() ?: []) as $u) {
            $records[] = [
                'id' => (int) $u['id'],
                'full_name' => (string) ($u['full_name'] ?? ''),
                'email' => (string) ($u['email'] ?? ''),
                'id_type' => (string) ($u['id_type'] ?? ''),
                'id_number' => (string) ($u['id_number'] ?? ''),
                'id_status' => (string) ($u['id_status'] ?? 'none'),
                'id_notes' => (string) ($u['id_notes'] ?? ''),
                'has_photo' => !empty($u['id_image'])
            ];
        }
        $this->respond(['valid_ids' => $records]);
    }

    public function adminVerifyId($id = null)
    {
        if (!$this->requireAdmin())
            return;
        $token = (string) ($_POST['csrf'] ?? '');
        if ($token === '' || !hash_equals($this->csrf(), $token)) {
            $this->respond(['error' => 'Invalid CSRF token'], 403);
            return;
        }
        $user = $this->CarEaseModel->user((int) $id);
        if (!$user || ($user['role'] ?? '') !== 'client') {
            $this->respond(['error' => 'Client not found'], 404);
            return;
        }
        if (($user['id_status'] ?? '') === 'verified') {
            $this->respond(['error' => 'ID is already approved'], 422);
            return;
        }
        $action = (string) ($_POST['action'] ?? '');
        $notes = trim((string) ($_POST['id_notes'] ?? ''));
        if ($action === 'approve') {
            if (empty($user['id_image']) || empty($user['id_type']) || empty($user['id_number'])) {
                $this->respond(['error' => 'Client ID submission is incomplete'], 422);
                return;
            }
            $data = ['id_status' => 'verified', 'id_notes' => null, 'id_verified_at' => date('Y-m-d H:i:s')];
        } elseif ($action === 'reject') {
            if ($notes === '' || strlen($notes) > 255) {
                $this->respond(['error' => 'Rejection reason is required (max 255 characters)'], 422);
                return;
            }
            $data = ['id_status' => 'rejected', 'id_notes' => $notes, 'id_verified_at' => null];
        } else {
            $this->respond(['error' => 'Invalid action'], 422);
            return;
        }
        $this->CarEaseModel->updateUser((int) $id, $data);
        $updated = $this->CarEaseModel->user((int) $id);
        if (!$updated || ($updated['id_status'] ?? '') !== $data['id_status']) {
            $this->respond(['error' => 'Failed to save ID status'], 500);
            return;
        }
        $this->respond(['message' => $action === 'approve' ? 'ID approved successfully' : 'ID rejected successfully']);
    }

    // AVAILABLE CARS

    public function cars()
    {
        $cars = array_values(array_filter(
            $this->CarEaseModel->cars() ?: [],
            function ($car) {
                return ($car['status'] ?? '') === 'available';
            }
        ));

        $this->respond(['cars' => $cars]);
    }

    // CLIENT BOOKINGS


    public function mine()
    {
        $user = $this->currentUser();

        if (!$user) {
            $this->respond([
                'error' => 'Login required'
            ], 401);
            return;
        }

        $uid = (int) $user['id'];
        $cars = [];

        foreach ($this->CarEaseModel->cars() ?: [] as $car) {
            $cars[(int) $car['id']] = $car;
        }

        try {
            $result = [];

            foreach ($this->CarEaseModel->bookings() ?: [] as $booking) {
                if ((int) $booking['user_id'] !== $uid) {
                    continue;
                }

                $booking['car'] =
                    $cars[(int) $booking['car_id']] ?? null;

                // Verify payment records against PayMongo
                // and calculate the booking payment summary.
                $summary = $this->bookingPaymentSummary($booking);

                $booking['payment_status'] =
                    $summary['payment_status'];

                $booking['total_centavos'] =
                    $summary['total_centavos'];

                $booking['downpayment_centavos'] =
                    $summary['downpayment_centavos'];

                $booking['paid_centavos'] =
                    $summary['paid_centavos'];

                $booking['remaining_centavos'] =
                    $summary['remaining_centavos'];

                $booking['has_pending_checkout'] =
                    $summary['has_pending_checkout'];

                    
                $returnRequest = $this->CarEaseModel->returnRequest(
                    (int) $booking['id']
                );

                $booking['return_request'] = $returnRequest ?: null;
                $booking['return_request_status'] = $returnRequest
                    ? (string) $returnRequest['status']
                    : null;


                $result[] = $booking;
            }

            $this->respond([
                'bookings' => $result
            ]);

        } catch (Throwable $e) {
            error_log(
                'CarEase client payment summary: ' .
                $e->getMessage()
            );

            $this->respond([
                'error' =>
                    'Unable to verify booking payments. Please try again.'
            ], 502);
        }
    }


    // CREATE BOOKING - AUTOMATIC CUSTOMER DETAILS

    public function createBooking()
    {
        $user = $this->currentUser();

        if (!$user) {
            $this->respond([
                'error' => 'Please sign in to book a car.'
            ], 401);
            return;
        }

        if (!$this->validCsrf()) {
            $this->respond(['error' => 'Invalid CSRF token'], 403);
            return;
        }

        // Require a verified government-issued ID before creating a booking.
        if (
            ($user['role'] ?? '') !== 'client' ||
            ($user['id_status'] ?? '') !== 'verified'
        ) {
            $this->respond([
                'error' => 'Please verify your Valid ID before booking a vehicle.'
            ], 422);
            return;
        }

        $carId = (int) $this->io->post('car_id');

        // Automatically use logged-in account information.
        $name = trim((string) $user['full_name']);
        $email = trim((string) $user['email']);

        // Customer only enters phone and rental dates.
        $phone = trim((string) $this->io->post('phone'));
        $start = trim((string) $this->io->post('pickup_date'));
        $end = trim((string) $this->io->post('return_date'));

        // Pickup / drop-off locations chosen through Google Maps.
        $pickupLocation = mb_substr(trim((string) $this->io->post('pickup_location')), 0, 255);
        $dropoffLocation = mb_substr(trim((string) $this->io->post('dropoff_location')), 0, 255);

        $pickupLatRaw = $this->io->post('pickup_lat');
        $pickupLngRaw = $this->io->post('pickup_lng');
        $dropoffLatRaw = $this->io->post('dropoff_lat');
        $dropoffLngRaw = $this->io->post('dropoff_lng');

        $pickupLat = is_numeric($pickupLatRaw) ? (float) $pickupLatRaw : null;
        $pickupLng = is_numeric($pickupLngRaw) ? (float) $pickupLngRaw : null;
        $dropoffLat = is_numeric($dropoffLatRaw) ? (float) $dropoffLatRaw : null;
        $dropoffLng = is_numeric($dropoffLngRaw) ? (float) $dropoffLngRaw : null;

        $routeDistance = (int) $this->io->post('route_distance_m');
        $routeDuration = (int) $this->io->post('route_duration_s');

        // Mandatory agreement and drawn signature for new bookings.
        $accepted = (string) $this->io->post('agreement_accepted');
        $signatureImage = trim((string) $this->io->post('signature_image'));

        if ($accepted !== '1') {
            $this->respond(['error' => 'Please accept the rental agreement.'], 422);
            return;
        }

        if (
            strlen($signatureImage) > 400000 ||
            !preg_match('~^data:image/png;base64,([A-Za-z0-9+/]+={0,2})$~D', $signatureImage, $matches)
        ) {
            $this->respond(['error' => 'Please draw a valid signature.'], 422);
            return;
        }

        $signatureBytes = base64_decode($matches[1], true);
        if (
            $signatureBytes === false || strlen($signatureBytes) > 250000 ||
            strlen($signatureBytes) < 100 ||
            substr($signatureBytes, 0, 8) !== "\x89PNG\r\n\x1a\n"
        ) {
            $this->respond(['error' => 'Invalid signature image.'], 422);
            return;
        }

        $imageInfo = @getimagesizefromstring($signatureBytes);
        if (
            !$imageInfo || ($imageInfo['mime'] ?? '') !== 'image/png' ||
            $imageInfo[0] < 100 || $imageInfo[1] < 30 ||
            $imageInfo[0] > 2000 || $imageInfo[1] > 1000
        ) {
            $this->respond(['error' => 'Invalid signature dimensions.'], 422);
            return;
        }

        $car = $this->CarEaseModel->car($carId);

        if (!$car || ($car['status'] ?? '') !== 'available') {
            $this->respond(['error' => 'Car not available.'], 422);
            return;
        }

        $a = DateTimeImmutable::createFromFormat('!Y-m-d', $start);
        $b = DateTimeImmutable::createFromFormat('!Y-m-d', $end);

        if (
            !$a ||
            !$b ||
            $a->format('Y-m-d') !== $start ||
            $b->format('Y-m-d') !== $end ||
            $a < new DateTimeImmutable('today') ||
            $b <= $a
        ) {
            $this->respond([
                'error' => 'Please choose valid future dates.'
            ], 422);
            return;
        }

        $days = (int) $a->diff($b)->days;

        if ($days > 5) {
            $this->respond([
                'error' => 'Maximum rental period is 5 days only.'
            ], 422);
            return;
        }

        if (
            $name === '' ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            !preg_match('/^[0-9+() -]{7,20}$/', $phone)
        ) {
            $this->respond([
                'error' => 'Enter valid name, email and phone.'
            ], 422);
            return;
        }

        foreach ([
            'pickup' => [$pickupLat, $pickupLng],
            'drop-off' => [$dropoffLat, $dropoffLng],
        ] as $label => $point) {
            [$lat, $lng] = $point;

            // Locations are optional, but when one coordinate is sent both
            // must be valid so we never store half a location.
            if ($lat === null && $lng === null) {
                continue;
            }

            if (
                $lat === null || $lng === null ||
                abs($lat) > 90 || abs($lng) > 180
            ) {
                $this->respond([
                    'error' => 'Invalid ' . $label . ' coordinates.'
                ], 422);
                return;
            }
        }

        // Prevent double booking for the same car.
        foreach ($this->CarEaseModel->bookings() ?: [] as $existing) {
            if ((int) $existing['car_id'] !== $carId) {
                continue;
            }

            if (
                !in_array(
                    strtolower((string) $existing['status']),
                    ['pending', 'approved', 'confirmed', 'ongoing', 'overdue'],
                    true
                )
            ) {
                continue;
            }

            if (
                $existing['pickup_date'] < $end &&
                $existing['return_date'] > $start
            ) {
                $this->respond([
                    'error' => 'Car is already booked for these dates.'
                ], 422);
                return;
            }
        }

        $transactionStarted = false;
        try {
            if (!$this->CarEaseModel->beginBookingTransaction()) {
                throw new RuntimeException('Unable to start booking transaction.');
            }
            $transactionStarted = true;

            $id = (int) $this->CarEaseModel->addBooking([
                'user_id' => (int) $user['id'],
                'car_id' => $carId,
                'customer_name' => $name,
                'email' => $email,
                'phone' => $phone,
                'pickup_date' => $start,
                'return_date' => $end,
                'pickup_location' => $pickupLocation !== '' ? $pickupLocation : null,
                'pickup_lat' => $pickupLat,
                'pickup_lng' => $pickupLng,
                'dropoff_location' => $dropoffLocation !== '' ? $dropoffLocation : null,
                'dropoff_lat' => $dropoffLat,
                'dropoff_lng' => $dropoffLng,
                'route_distance_m' => $routeDistance > 0 ? $routeDistance : null,
                'route_duration_s' => $routeDuration > 0 ? $routeDuration : null,
                'total_amount' => number_format(
                    $days * (float) $car['price_per_day'],
                    2,
                    '.',
                    ''
                ),
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ]);

            if ($id <= 0) {
                throw new RuntimeException('Unable to save booking.');
            }

            $booking = $this->CarEaseModel->booking($id);

            if (!$booking) {
                throw new RuntimeException('Saved booking not found.');
            }

            // Create and sign the contract BEFORE returning the booking ID.
            // Existing approval code will reuse this contract, not create a duplicate.
            $contract = $this->createContract($booking, $car);
            if (!$contract || empty($contract['id'])) {
                throw new RuntimeException('Contract could not be created.');
            }

            $now = date('Y-m-d H:i:s');
            $this->CarEaseModel->updateContract((int) $contract['id'], [
                'status' => 'signed',
                'customer_signature' => $name,
                'signature_image' => $signatureImage,
                'signed_at' => $now,
                'updated_at' => $now
            ]);

            $signed = $this->CarEaseModel->contract($id);
            if (
                !$signed || ($signed['status'] ?? '') !== 'signed' ||
                empty($signed['signature_image'])
            ) {
                throw new RuntimeException('Signed contract could not be saved.');
            }

            if (!$this->CarEaseModel->commitBookingTransaction()) {
                throw new RuntimeException('Unable to commit booking transaction.');
            }
            $transactionStarted = false;

            $this->respond([
                'message' => 'Rental request and signed agreement saved successfully.',
                'booking' => $booking
            ], 201);

        } catch (Throwable $e) {
            error_log('CarEase Booking: ' . $e->getMessage());

            if ($transactionStarted) {
                try {
                    $this->CarEaseModel->rollbackBookingTransaction();
                } catch (Throwable $rollbackError) {
                    error_log('CarEase booking rollback: ' . $rollbackError->getMessage());
                }
            }

            $this->respond([
                'error' => 'Unable to save booking. Check backend logs.'
            ], 500);
        }
    }


    // ADMIN STAFF MANAGEMENT

    public function staffAccounts()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $staff = $this->CarEaseModel->staffAccounts() ?: [];

        $result = [];

        foreach ($staff as $account) {
            $result[] = [
                'id' => (int) $account['id'],
                'full_name' => $account['full_name'],
                'email' => $account['email'],
                'role' => $account['role']
            ];
        }

        $this->respond(['staff' => $result]);
    }

    public function createStaff()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        if (!$this->validCsrf()) {
            $this->respond([
                'error' => 'Invalid CSRF token'
            ], 403);
            return;
        }

        $name = trim((string) $this->io->post('full_name'));
        $email = strtolower(trim((string) $this->io->post('email')));
        $password = (string) $this->io->post('password');

        if (
            mb_strlen($name) < 2 ||
            mb_strlen($name) > 120 ||
            preg_match('/[0-9]/u', $name)
        ) {
            $this->respond([
                'error' => 'Enter a valid name without numbers'
            ], 422);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->respond([
                'error' => 'Invalid email address'
            ], 422);
            return;
        }

        if (strlen($password) < 8) {
            $this->respond([
                'error' => 'Password must be at least 8 characters'
            ], 422);
            return;
        }

        if ($this->CarEaseModel->userByEmail($email)) {
            $this->respond([
                'error' => 'Email already registered'
            ], 422);
            return;
        }


        try {
            $created = $this->CarEaseModel->addStaff([
                'full_name' => $name,
                'email' => $email,
                'password_hash' => password_hash(
                    $password,
                    PASSWORD_DEFAULT
                ),
                'created_at' => date('Y-m-d H:i:s')
            ]);

            if (!$created) {
                throw new RuntimeException(
                    'Staff account was not saved.'
                );
            }

            $this->respond([
                'message' => 'Staff account created successfully'
            ], 201);

        } catch (Throwable $e) {
            error_log(
                'CarEase staff creation: ' . $e->getMessage()
            );

            $this->respond([
                'error' => 'Unable to create staff account. Check server logs.'
            ], 500);
        }

    }


    // ADMIN BOOKINGS


    public function adminBookings()
    {
        if (!$this->requireAdminOrStaff()) {
            return;
        }

        try {
            $bookings = $this->CarEaseModel->bookings() ?: [];

            foreach ($bookings as &$booking) {
                $summary = $this->bookingPaymentSummary($booking);

                $booking['payment_status'] =
                    $summary['payment_status'];

                $booking['total_centavos'] =
                    $summary['total_centavos'];

                $booking['downpayment_centavos'] =
                    $summary['downpayment_centavos'];

                $booking['paid_centavos'] =
                    $summary['paid_centavos'];

                $booking['remaining_centavos'] =
                    $summary['remaining_centavos'];

                $booking['has_pending_checkout'] =
                    $summary['has_pending_checkout'];

                    
                $returnRequest = $this->CarEaseModel->returnRequest(
                    (int) $booking['id']
                );

                $booking['return_request_status'] = $returnRequest
                    ? (string) ($returnRequest['status'] ?? '')
                    : null;

            }

            unset($booking);

            $this->respond(['bookings' => $bookings]);

        } catch (Throwable $e) {
            error_log(
                'CarEase admin payment summary: ' .
                $e->getMessage()
            );

            $this->respond([
                'error' => 'Unable to verify booking payments. Check server logs.'
            ], 502);
        }
    }

    // GOOGLE CALENDAR SYNC

    private function calendarSync($booking, $action = 'sync')
    {
        try {
            require_once __DIR__ .
                '/../libraries/CarEaseCalendarSync.php';

            $calendar = new CarEaseCalendarSync();

            if ($action === 'delete') {
                $result = $calendar->delete((int) $booking['id']);

                return [
                    'success' => true,
                    'message' => $result === 'deleted'
                        ? 'Calendar event removed.'
                        : 'No calendar event to remove.'
                ];
            }

            $car = $this->CarEaseModel->car($booking['car_id']);

            if (!$car) {
                throw new RuntimeException(
                    'Vehicle not found for calendar sync.'
                );
            }

            $result = $calendar->sync($booking, $car);

            return [
                'success' => true,
                'message' => 'Calendar event ' . $result . '.'
            ];

        } catch (Throwable $e) {
            error_log(
                'CarEase API Calendar Sync: ' . $e->getMessage()
            );

            return [
                'success' => false,
                'message' => 'Calendar sync failed: ' .
                    $e->getMessage()
            ];
        }
    }

    // RENTAL CONTRACT TERMS

    private function contractTerms($booking, $car)
    {
        $vehicle = trim(
            (string) ($car['brand'] ?? '') . ' ' .
            (string) ($car['name'] ?? '')
        );

        if ($vehicle === '') {
            $vehicle = 'the rented vehicle';
        }

        return
            "1. VEHICLE. The renter agrees to rent " . $vehicle .
            " from CarEase Rentals for the period " .
            $booking['pickup_date'] . " to " .
            $booking['return_date'] . ".\n" .

            "2. VALID ID. The renter must present a valid government-issued ID. The ID must be verified by CarEase staff before the vehicle is released.\n" .

            "3. PAYMENT. The total rental fee is PHP " .
            $booking['total_amount'] .
            ". Payment must be settled before the release of the vehicle unless another arrangement is approved in writing.\n" .

            "4. USE OF VEHICLE. The vehicle must be used only for lawful purposes. Subletting, racing, and transporting illegal goods are strictly prohibited.\n" .

            "5. RETURN. The vehicle must be returned on or before the return date and time at the agreed location, in the same condition as released, with the same fuel level.\n" .

            "6. LATE RETURN. Late returns may incur additional charges. CarEase may send reminder and overdue notices to the renter's registered email address.\n" .

            "7. DAMAGE AND VIOLATIONS. The renter is responsible for traffic violations, fines, tolls, and damage caused during the rental period, subject to the inspection report.\n" .

            "8. CANCELLATION. CarEase may cancel this agreement for invalid documents, non-payment, or violation of these terms.\n" .

            "9. PRIVACY. The renter's ID and contact details are used only for verification, rental records, and notifications related to this booking.\n" .

            "10. ACCEPTANCE. By signing, the renter confirms that they have read, understood, and accepted these terms.";
    }

    // CREATE CONTRACT

    private function createContract($booking, $car)
    {
        $existing = $this->CarEaseModel->contract($booking['id']);

        if ($existing) {
            return $existing;
        }

        if (!$car) {
            throw new RuntimeException('Car not found for contract.');
        }

        $vehicle = trim(
            (string) ($car['brand'] ?? '') . ' ' .
            (string) ($car['name'] ?? '')
        );

        $now = date('Y-m-d H:i:s');

        $data = [
            'booking_id' => (int) $booking['id'],
            'contract_no' => 'CE-' . date('Ymd') . '-' .
                str_pad((string) $booking['id'], 4, '0', STR_PAD_LEFT),
            'customer_name' => (string) $booking['customer_name'],
            'customer_email' => (string) $booking['email'],
            'customer_phone' => (string) $booking['phone'],
            'car_description' => $vehicle === '' ? 'Vehicle' : $vehicle,
            'pickup_date' => (string) $booking['pickup_date'],
            'return_date' => (string) $booking['return_date'],
            'total_amount' => (string) $booking['total_amount'],
            'terms' => $this->contractTerms($booking, $car),
            'status' => 'draft',
            'created_by' => (int) (
                $_SESSION['carease_user_id'] ?? 0
            ) ?: null,
            'created_at' => $now
        ];

        $newId = (int) $this->CarEaseModel->addContract($data);

        if ($newId <= 0) {
            throw new RuntimeException(
                'Could not create rental contract.'
            );
        }

        return $this->CarEaseModel->contractById($newId);
    }

    // EMAIL CONTRACT

    private function emailContract($booking, $contract, $car)
    {
        require_once __DIR__ .
            '/../config/carease_email_notification.php';

        $mailer = new CarEaseEmailNotification();

        $sent = (bool) $mailer->contractAgreement(
            $booking,
            $contract,
            $car
        );

        if ($sent && $contract['status'] !== 'signed') {
            $this->CarEaseModel->updateContract(
                $contract['id'],
                [
                    'status' => 'sent',
                    'updated_at' => date('Y-m-d H:i:s')
                ]
            );
        }

        return $sent;
    }

    // VIEW CONTRACT

    public function getContract($id)
    {
        $user = $this->currentUser();

        if (!$user) {
            $this->respond(['error' => 'Login required'], 401);
            return;
        }

        $booking = $this->CarEaseModel->booking((int) $id);

        if (!$booking) {
            $this->respond(['error' => 'Booking not found'], 404);
            return;
        }

        if (
            ($user['role'] ?? '') !== 'admin' &&
            (int) $booking['user_id'] !== (int) $user['id']
        ) {
            $this->respond(['error' => 'Access denied'], 403);
            return;
        }

        $contract = $this->CarEaseModel->contract((int) $id);

        if (!$contract) {
            $this->respond([
                'error' => 'Rental agreement is not available yet. Please wait for admin approval.'
            ], 404);
            return;
        }

        $this->respond(['contract' => $contract]);
    }

    // SIGN CONTRACT

    public function signContract($id)
    {
        $user = $this->currentUser();

        if (!$user) {
            $this->respond(['error' => 'Login required'], 401);
            return;
        }

        if (!$this->validCsrf()) {
            $this->respond(['error' => 'Invalid CSRF token'], 403);
            return;
        }

        if (($user['role'] ?? '') === 'admin') {
            $this->respond([
                'error' => 'The client must sign the agreement.'
            ], 403);
            return;
        }

        $booking = $this->CarEaseModel->booking((int) $id);

        if (!$booking) {
            $this->respond(['error' => 'Booking not found'], 404);
            return;
        }

        if ((int) $booking['user_id'] !== (int) $user['id']) {
            $this->respond(['error' => 'Access denied'], 403);
            return;
        }

        $contract = $this->CarEaseModel->contract((int) $id);

        if (!$contract) {
            $this->respond([
                'error' => 'The rental agreement is not ready yet.'
            ], 404);
            return;
        }

        if ($contract['status'] === 'signed') {
            $this->respond([
                'error' => 'This agreement has already been signed.'
            ], 422);
            return;
        }

        if (
            !in_array(
                $contract['status'],
                ['draft', 'sent'],
                true
            )
        ) {
            $this->respond([
                'error' => 'This agreement is no longer available for signing.'
            ], 422);
            return;
        }

        if (
            !in_array(
                $booking['status'],
                ['approved', 'confirmed'],
                true
            )
        ) {
            $this->respond([
                'error' => 'Booking must be approved before signing.'
            ], 422);
            return;
        }

        if (($user['id_status'] ?? '') !== 'verified') {
            $this->respond([
                'error' => 'Your valid ID must be verified before signing the agreement.'
            ], 422);
            return;
        }

        $signature = trim(
            (string) $this->io->post('signature')
        );

        if (
            $signature === '' ||
            strcasecmp(
                $signature,
                (string) $user['full_name']
            ) !== 0
        ) {
            $this->respond([
                'error' => 'Type your full name exactly as registered to sign.'
            ], 422);
            return;
        }

        try {
            $this->CarEaseModel->updateContract(
                (int) $contract['id'],
                [
                    'status' => 'signed',
                    'customer_signature' => $signature,
                    'signed_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]
            );

            $signed = $this->CarEaseModel->contract((int) $id);

            if (!$signed || $signed['status'] !== 'signed') {
                throw new RuntimeException(
                    'Contract signature was not saved.'
                );
            }

        } catch (Throwable $e) {
            error_log(
                'CarEase contract signing: ' . $e->getMessage()
            );

            $this->respond([
                'error' => 'Unable to save signed agreement.'
            ], 500);
            return;
        }

        $emailSent = false;

        try {
            require_once __DIR__ .
                '/../config/carease_email_notification.php';

            $mailer = new CarEaseEmailNotification();

            $emailSent = (bool) $mailer->contractSigned(
                $booking,
                $signed,
                $this->CarEaseModel->car($booking['car_id'])
            );

        } catch (Throwable $e) {
            error_log(
                'CarEase signed contract email: ' .
                $e->getMessage()
            );
        }

        $this->respond([
            'message' => $emailSent
                ? 'Agreement signed. A copy was sent to your email.'
                : 'Agreement signed, but email delivery was not confirmed.',
            'contract' => $signed,
            'email_sent' => $emailSent
        ]);
    }

    // ADMIN EMAIL NOTIFICATIONS

    public function runNotifications()
    {
        if (!$this->requireAdmin()) {
            return;
        }

        if (!$this->validCsrf()) {
            $this->respond(['error' => 'Invalid CSRF token'], 403);
            return;
        }

        try {
            require_once __DIR__ .
                '/../libraries/CarEaseNotifications.php';

            $dryRun = (string) $this->io->post('dry_run') === '1';

            $runner = new CarEaseNotifications(
                $this->CarEaseModel,
                $dryRun
            );

            $this->respond([
                'summary' => $runner->run(),
                'dry_run' => $dryRun
            ]);

        } catch (Throwable $e) {
            error_log(
                'CarEase API notifications: ' .
                $e->getMessage()
            );

            $this->respond([
                'error' => 'Notification run failed. Check server logs.'
            ], 500);
        }
    }

    // ADMIN UPDATE BOOKING STATUS

    public function updateBookingStatus($id = null)
    {
        if (!$this->requireAdminOrStaff()) {
            return;
        }

        if (!$this->validCsrf()) {
            $this->respond(['error' => 'Invalid CSRF token'], 403);
            return;
        }

        $id = (int) $id;
        $status = strtolower(
            trim((string) $this->io->post('status'))
        );

        if ($id <= 0) {
            $this->respond(['error' => 'Invalid booking ID'], 422);
            return;
        }

        $booking = $this->CarEaseModel->booking($id);

        if (!$booking) {
            $this->respond([
                'error' => 'Booking not found in database'
            ], 404);
            return;
        }

        $current = strtolower((string) $booking['status']);

        $allowed = [
            'pending' => ['approved', 'rejected', 'cancelled'],
            'approved' => ['confirmed', 'cancelled'],
            'confirmed' => ['ongoing', 'cancelled'],
            'ongoing' => ['overdue', 'completed'],
            'overdue' => ['completed'],
            'completed' => [],
            'rejected' => [],
            'cancelled' => []
        ];

        if (!isset($allowed[$current])) {
            $this->respond([
                'error' => 'Unknown current booking status'
            ], 422);
            return;
        }

        if ($current === $status) {
            $this->respond([
                'message' => 'Booking already has this status',
                'booking' => $booking
            ]);
            return;
        }

        if (!in_array($status, $allowed[$current], true)) {
            $this->respond([
                'error' => 'Cannot change booking from ' .
                    $current . ' to ' . $status
            ], 422);
            return;
        }

        if (
            $status === 'overdue' &&
            date('Y-m-d') <= (string) $booking['return_date']
        ) {
            $this->respond([
                'error' => 'Booking is not overdue yet.'
            ], 422);
            return;
        }

        if ($status === 'ongoing') {
            $client = $this->CarEaseModel->user(
                $booking['user_id']
            );

            $contract = $this->CarEaseModel->contract($id);

            if (
                !$client ||
                ($client['id_status'] ?? '') !== 'verified'
            ) {
                $this->respond([
                    'error' => 'Client ID must be verified before vehicle release.'
                ], 422);
                return;
            }

            if (
                !$contract ||
                ($contract['status'] ?? '') !== 'signed'
            ) {
                $this->respond([
                    'error' => 'Client must sign the rental agreement before vehicle release.'
                ], 422);
                return;
            }
        }

        if ($status === 'ongoing' || $status === 'completed') {
            $needed = $status === 'ongoing' ? 'pre' : 'post';
            $found = false;

            foreach (
                $this->CarEaseModel->inspections($id) ?: []
                as $inspection
            ) {
                // Accept the inspection type names used by different
                // versions of the vehicle inspection form.
                $inspectionType = strtolower(trim((string) (
                    $inspection['inspection_type'] ?? ''
                )));
                $inspectionType = str_replace(['-', ' '], '_', $inspectionType);
                $validTypes = $needed === 'post'
                    ? ['post', 'after', 'after_return', 'post_inspection', 'return']
                    : ['pre', 'before', 'before_rental', 'pre_inspection', 'pickup'];

                if (in_array($inspectionType, $validTypes, true)) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $this->respond([
                    'error' => ucfirst($needed) .
                        '-inspection is required before changing status to ' .
                        $status . '.'
                ], 422);
                return;
            }
        }

        $this->CarEaseModel->updateBooking(
            $id,
            ['status' => $status]
        );

        $updated = $this->CarEaseModel->booking($id);

        if (
            !$updated ||
            strtolower((string) $updated['status']) !== $status
        ) {
            $this->respond([
                'error' => 'Booking status was not saved'
            ], 500);
            return;
        }

        // Automatically accept an existing return request only after
        // the booking has successfully reached completed status.
        // The post-inspection requirement above remains mandatory.
        if ($status === 'completed') {
            try {
                $returnRequest = $this->CarEaseModel->returnRequest($id);
                if ($returnRequest && ($returnRequest['status'] ?? '') === 'pending') {
                    $this->CarEaseModel->updateReturnRequest(
                        (int) $returnRequest['id'],
                        ['status' => 'accepted']
                    );
                    $verifiedReturn = $this->CarEaseModel->returnRequest($id);
                    if (!$verifiedReturn || ($verifiedReturn['status'] ?? '') !== 'accepted') {
                        error_log('CarEase return request acceptance was not saved for booking #' . $id);
                    }
                }
            } catch (Throwable $e) {
                // Booking completion has already succeeded. Log any return-request
                // synchronization failure rather than claiming completion failed.
                error_log('CarEase auto-accept return request #' . $id . ': ' . $e->getMessage());
            }
        }

        $car = $this->CarEaseModel->car($updated['car_id']);
        $contractNotice = '';

        try {
            require_once __DIR__ .
                '/../config/carease_email_notification.php';

            $mailer = new CarEaseEmailNotification();

            $mailer->bookingStatus(
                $updated,
                $car,
                $status
            );

        } catch (Throwable $e) {
            error_log(
                'CarEase booking status email: ' .
                $e->getMessage()
            );
        }

        if ($status === 'approved') {
            try {
                $contract = $this->createContract(
                    $updated,
                    $car
                );

                $emailSent = $this->emailContract(
                    $updated,
                    $contract,
                    $car
                );

                $contractNotice = $emailSent
                    ? 'Rental agreement sent to customer.'
                    : 'Contract created, but email delivery was not confirmed.';

            } catch (Throwable $e) {
                error_log(
                    'CarEase contract creation/email: ' .
                    $e->getMessage()
                );

                $contractNotice =
                    'Contract generation or email failed; check server logs.';
            }

        } elseif (
            in_array(
                $status,
                ['rejected', 'cancelled'],
                true
            )
        ) {
            $contract = $this->CarEaseModel->contract($id);

            if ($contract) {
                try {
                    $this->CarEaseModel->updateContract(
                        $contract['id'],
                        [
                            'status' => 'cancelled',
                            'updated_at' => date('Y-m-d H:i:s')
                        ]
                    );

                } catch (Throwable $e) {
                    error_log(
                        'CarEase contract cancellation: ' .
                        $e->getMessage()
                    );
                }
            }
        }

        $action = in_array(
            $status,
            ['rejected', 'cancelled'],
            true
        ) ? 'delete' : 'sync';

        $calendar = $this->calendarSync(
            $updated,
            $action
        );

        $this->respond([
            'message' => 'Booking status updated successfully',
            'booking' => $updated,
            'calendar' => $calendar['message'],
            'calendar_success' => $calendar['success'],
            'contract_notice' => $contractNotice
        ]);
    }

    // Inspections are stored in MySQL, never only in browser storage.
    public function bookingInspections($id = null)
    {
        if (!$this->requireAdminOrStaff()) return;
        $id = (int) $id;
        if ($id <= 0 || !$this->CarEaseModel->booking($id)) {
            $this->respond(['error' => 'Booking not found'], 404);
            return;
        }
        $this->respond(['inspections' => $this->CarEaseModel->inspections($id) ?: []]);
    }

    public function saveBookingInspection($id = null)
    {
        if (!$this->requireAdminOrStaff()) return;
        if (!$this->validCsrf()) {
            $this->respond(['error' => 'Invalid CSRF token'], 403);
            return;
        }
        $id = (int) $id;
        $booking = $this->CarEaseModel->booking($id);
        if (!$booking) {
            $this->respond(['error' => 'Booking not found'], 404);
            return;
        }
        $type = strtolower(trim((string) $this->io->post('inspection_type')));
        $fuel = (string) $this->io->post('fuel_level');
        $condition = (string) $this->io->post('vehicle_condition');
        $mileageRaw = (string) $this->io->post('mileage');
        $notes = trim((string) $this->io->post('damage_notes'));
        $status = strtolower((string) $booking['status']);
        $allowed = $type === 'pre'
            ? ['approved', 'confirmed']
            : ['ongoing', 'overdue'];
        if (!in_array($type, ['pre', 'post'], true) ||
            !in_array($status, $allowed, true) ||
            !in_array($fuel, ['empty', 'quarter', 'half', 'three_quarter', 'full'], true) ||
            !in_array($condition, ['good', 'minor', 'major'], true) ||
            !ctype_digit($mileageRaw) || (float) $mileageRaw > 99999999 ||
            strlen($notes) > 2000) {
            $this->respond(['error' => 'Invalid inspection details or rental stage'], 422);
            return;
        }
        $mileage = (int) $mileageRaw;
        foreach ($this->CarEaseModel->inspections($id) ?: [] as $existing) {
            if (($existing['inspection_type'] ?? '') === $type) {
                $this->respond(['error' => 'Inspection already recorded'], 409);
                return;
            }
            if ($type === 'post' && ($existing['inspection_type'] ?? '') === 'pre' &&
                $mileage < (int) $existing['mileage']) {
                $this->respond(['error' => 'Return mileage cannot be lower than pickup mileage'], 422);
                return;
            }
        }
        $data = [
            'booking_id' => $id,
            'inspection_type' => $type,
            'fuel_level' => $fuel,
            'mileage' => $mileage,
            'vehicle_condition' => $condition,
            'damage_notes' => $notes,
            'inspected_at' => date('Y-m-d H:i:s')
        ];
        try {
            $this->CarEaseModel->addInspection($data);
            $rows = $this->CarEaseModel->inspections($id) ?: [];
            $saved = null;
            foreach ($rows as $row) {
                if (($row['inspection_type'] ?? '') === $type) $saved = $row;
            }
            if (!$saved) {
                $this->respond(['error' => 'Inspection could not be verified in database'], 500);
                return;
            }
            $this->respond(['message' => 'Inspection saved', 'inspection' => $saved]);
        } catch (Throwable $e) {
            error_log('CarEase inspection save: ' . $e->getMessage());
            $this->respond(['error' => 'Unable to save inspection to database'], 500);
        }
    }

    // PAYMONGO PAYMENT API

    private function paymentRequest($method, $path, $body = null)
    {
        $config = require __DIR__ .
            '/../config/carease_paymongo_local.php';

        $secretKey = $config['secret_key'] ?? '';

        if (strpos($secretKey, 'sk_test_') !== 0) {
            throw new RuntimeException(
                'PayMongo test secret key is missing.'
            );
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException(
                'PHP cURL extension is not enabled.'
            );
        }

        $url = 'https://api.paymongo.com/v1/' .
            ltrim($path, '/');

        $ch = curl_init($url);

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' .
                base64_encode($secretKey . ':'),
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ];

        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] =
                json_encode($body, JSON_THROW_ON_ERROR);
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException(
                'PayMongo connection failed: ' . $error
            );
        }

        $data = json_decode($response, true);

        if (
            $status < 200 ||
            $status >= 300 ||
            !is_array($data)
        ) {
            error_log(
                'CarEase PayMongo HTTP ' . $status .
                ': ' . substr($response, 0, 1500)
            );

            throw new RuntimeException(
                'PayMongo HTTP ' . $status . ': ' .
                substr($response, 0, 1500)
            );
        }

        return $data;
    }

    private function verifyPayment($payment)
    {
        if ($payment['status'] === 'paid') {
            return 'paid';
        }

        if ($payment['status'] !== 'pending') {
            return $payment['status'];
        }

        $session = $this->paymentRequest(
            'GET',
            'checkout_sessions/' .
            rawurlencode($payment['checkout_session_id'])
        );

        $data = $session['data'] ?? [];

        if (
            ($data['id'] ?? '') !==
            $payment['checkout_session_id'] ||
            !empty($data['attributes']['livemode'])
        ) {
            throw new RuntimeException(
                'Invalid checkout session.'
            );
        }

        $attributes = $data['attributes'] ?? [];

        $payments = $attributes['payment_intent']
        ['attributes']['payments']
            ?? $attributes['payments']
            ?? [];

        foreach ($payments as $item) {
            $details = $item['attributes'] ?? $item;

            if (
                ($details['status'] ?? '') === 'paid' &&
                (int) ($details['amount'] ?? 0) ===
                (int) $payment['amount_centavos']
            ) {
                $this->CarEaseModel->updatePayment(
                    $payment['id'],
                    [
                        'status' => 'paid',
                        'paid_at' => date('Y-m-d H:i:s')
                    ]
                );

                return 'paid';
            }
        }

        if (($attributes['status'] ?? '') === 'expired') {
            $this->CarEaseModel->updatePayment(
                $payment['id'],
                ['status' => 'expired']
            );

            return 'expired';
        }

        return 'pending';
    }


    private function bookingPaymentSummary($booking)
    {
        $total = (int) round((float) $booking['total_amount'] * 100);
        $downpayment = (int) round($total * 0.30);
        $paid = 0;
        $pending = false;
        $rows = $this->CarEaseModel->paymentsForBooking((int) $booking['id']) ?: [];

        foreach ($rows as $payment) {
            $status = $this->verifyPayment($payment);
            if ($status === 'paid') {
                $paid += (int) $payment['amount_centavos'];
            } elseif ($status === 'pending') {
                $pending = true;
            }
        }

        $paid = min($paid, $total);
        $remaining = max(0, $total - $paid);
        $paymentStatus = 'unpaid';
        if ($total > 0 && $remaining === 0) {
            $paymentStatus = 'fully_paid';
        } elseif ($paid >= $downpayment && $downpayment > 0) {
            $paymentStatus = 'downpayment_paid';
        } elseif ($paid > 0) {
            $paymentStatus = 'partially_paid';
        }


        return [
            'total_centavos' => $total,
            'downpayment_centavos' => $downpayment,
            'paid_centavos' => $paid,
            'remaining_centavos' => $remaining,
            'has_pending_checkout' => $pending,
            'payment_status' => $paymentStatus
        ];

    }

    public function paymentCheckout($id)
    {
        $user = $this->currentUser();
        if (!$user) {
            $this->respond(['error' => 'Please log in first.'], 401);
            return;
        }
        if (!$this->validCsrf()) {
            $this->respond(['error' => 'Invalid CSRF token.'], 403);
            return;
        }

        $id = (int) $id;
        $booking = $this->CarEaseModel->booking($id);
        if (!$booking || (int) $booking['user_id'] !== (int) $user['id']) {
            $this->respond(['error' => 'Booking not found.'], 404);
            return;
        }

        try {
            $summary = $this->bookingPaymentSummary($booking);
            $total = $summary['total_centavos'];
            $paid = $summary['paid_centavos'];
            $remaining = $summary['remaining_centavos'];
            $downpayment = $summary['downpayment_centavos'];

            if ($total < 100 || $downpayment < 100) {
                $this->respond(['error' => 'Invalid rental amount.'], 422);
                return;
            }
            if ($remaining === 0) {
                $this->respond(['status' => 'paid', 'message' => 'Booking is fully paid.'], 200);
                return;
            }

            // The initial 30% may be paid before admin approval.
            // Further payments require admin approval.
            $status = strtolower((string) $booking['status']);
            $allowed = $paid === 0
                ? ['pending', 'approved', 'confirmed']
                : ['approved', 'confirmed'];
            if (!in_array($status, $allowed, true)) {
                $this->respond(['error' => 'Payment is not allowed for this booking status.'], 422);
                return;
            }

            $amount = $paid === 0 ? $downpayment : $remaining;
            $type = $paid === 0 ? 'downpayment' : 'balance';

            // Never create another checkout while one remains pending.
            // An old full-payment checkout must not be reused for 30%.
            $rows = $this->CarEaseModel->paymentsForBooking($id) ?: [];
            foreach (array_reverse($rows) as $payment) {
                if ($payment['status'] !== 'pending') {
                    continue;
                }
                if ((int) $payment['amount_centavos'] !== $amount) {
                    $this->respond([
                        'error' => 'An earlier checkout for a different amount is still pending. Please wait for it to expire or contact the administrator.'
                    ], 409);
                    return;
                }
                $checkoutUrl = (string) ($payment['checkout_url'] ?? '');
                if (
                    filter_var($checkoutUrl, FILTER_VALIDATE_URL)
                    && parse_url($checkoutUrl, PHP_URL_SCHEME) === 'https'
                    && parse_url($checkoutUrl, PHP_URL_HOST) === 'checkout.paymongo.com'
                ) {
                    $this->respond(['status' => 'pending', 'checkoutUrl' => $checkoutUrl]);
                    return;
                }
                $this->respond(['error' => 'Existing checkout URL is invalid. Contact the administrator.'], 409);
                return;
            }

            $config = require __DIR__ . '/../config/carease_paymongo_local.php';
            $returnUrl = 'https://carease.free.je/#/my-bookings';
            $payload = [
                'data' => [
                    'attributes' => [
                        'line_items' => [
                            [
                                'name' => 'CarEase Booking #' . $id . ' - ' . ($type === 'downpayment' ? '30% Downpayment' : 'Remaining Balance'),
                                'amount' => $amount,
                                'currency' => 'PHP',
                                'quantity' => 1
                            ]
                        ],
                        'payment_method_types' => $config['payment_method_types'],
                        'success_url' => $returnUrl,
                        'cancel_url' => $returnUrl,
                        'reference_number' => 'CAREASE-' . $id . '-' . bin2hex(random_bytes(4)),
                        'description' => 'Car rental booking #' . $id
                    ]
                ]
            ];

            $created = $this->paymentRequest('POST', 'checkout_sessions', $payload);
            $sessionId = (string) ($created['data']['id'] ?? '');
            $attributes = $created['data']['attributes'] ?? [];
            $checkoutUrl = (string) ($attributes['checkout_url'] ?? ($attributes['redirect']['url'] ?? ''));

            if (
                !preg_match('/^cs_[A-Za-z0-9_-]+$/', $sessionId)
                || !filter_var($checkoutUrl, FILTER_VALIDATE_URL)
                || parse_url($checkoutUrl, PHP_URL_SCHEME) !== 'https'
                || parse_url($checkoutUrl, PHP_URL_HOST) !== 'checkout.paymongo.com'
            ) {
                throw new RuntimeException('Invalid PayMongo checkout response.');
            }

            $this->CarEaseModel->addPayment([
                'booking_id' => $id,
                'payment_type' => $type,
                'checkout_session_id' => $sessionId,
                'checkout_url' => $checkoutUrl,
                'amount_centavos' => $amount,
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s')
            ]);

            $this->respond([
                'status' => 'pending',
                'checkoutUrl' => $checkoutUrl,
                'payment_type' => $type,
                'amount_centavos' => $amount
            ]);
        } catch (Throwable $e) {
            error_log('CarEase payment checkout: ' . $e->getMessage());
            $this->respond(['error' => 'Unable to start payment. Check server logs.'], 502);
        }
    }

    public function paymentStatus($id)
    {
        $user = $this->currentUser();
        if (!$user) {
            $this->respond(['error' => 'Login required.'], 401);
            return;
        }
        $id = (int) $id;
        $booking = $this->CarEaseModel->booking($id);
        if (!$booking || (int) $booking['user_id'] !== (int) $user['id']) {
            $this->respond(['error' => 'Booking not found.'], 404);
            return;
        }
        try {
            $summary = $this->bookingPaymentSummary($booking);
            $summary['bookingId'] = $id;
            $summary['status'] = $summary['payment_status'] === 'fully_paid'
                ? 'paid'
                : ($summary['has_pending_checkout'] ? 'pending' : 'unpaid');
            $this->respond($summary);
        } catch (Throwable $e) {
            error_log('CarEase payment verification: ' . $e->getMessage());
            $this->respond(['error' => 'Unable to verify payment. Please try again.'], 502);
        }
    }
    
    // CLIENT VEHICLE RETURN REQUEST
    public function requestReturn($id = null)
    {
        $user = $this->currentUser();

        if (!$user || ($user['role'] ?? '') !== 'client') {
            $this->respond(['error' => 'Client login required.'], 401);
            return;
        }

        if (!$this->validCsrf()) {
            $this->respond(['error' => 'Invalid CSRF token.'], 403);
            return;
        }

        $id = (int) $id;
        $booking = $this->CarEaseModel->booking($id);

        if (!$booking || (int) $booking['user_id'] !== (int) $user['id']) {
            $this->respond(['error' => 'Booking not found.'], 404);
            return;
        }

        $existing = $this->CarEaseModel->returnRequest($id);

        if ($existing) {
            $this->respond([
                'message' => 'Return request already submitted.',
                'return_request' => $existing
            ]);
            return;
        }

        $status = strtolower((string) ($booking['status'] ?? ''));

        if (!in_array($status, ['ongoing', 'overdue'], true)) {
            $this->respond([
                'error' => 'Vehicle return is only available for ongoing or overdue rentals.'
            ], 422);
            return;
        }

        try {
            $this->CarEaseModel->addReturnRequest([
                'booking_id' => $id,
                'user_id' => (int) $user['id'],
                'status' => 'pending',
                'requested_at' => date('Y-m-d H:i:s')
            ]);

            $saved = $this->CarEaseModel->returnRequest($id);

            if (!$saved) {
                throw new RuntimeException('Return request was not saved.');
            }

            $this->respond([
                'message' => 'Request Submitted',
                'return_request' => $saved
            ], 201);

        } catch (Throwable $e) {
            // A unique booking_id constraint also prevents duplicates.
            $existing = $this->CarEaseModel->returnRequest($id);

            if ($existing) {
                $this->respond([
                    'message' => 'Return request already submitted.',
                    'return_request' => $existing
                ]);
                return;
            }

            error_log('CarEase return request: ' . $e->getMessage());

            $this->respond([
                'error' => 'Unable to submit return request.'
            ], 500);
        }
    }


    // ADMIN RETURN REQUESTS
    public function adminReturnRequests()
    {
        if (!$this->requireAdminOrStaff()) {
            return;
        }

        try {
            $requests = $this->CarEaseModel->returnRequests() ?: [];

            $this->respond([
                'return_requests' => array_values($requests),
                'requests' => array_values($requests)
            ]);
        } catch (Throwable $e) {
            error_log('CarEase admin return requests: ' . $e->getMessage());
            $this->respond(['error' => 'Unable to load return requests.'], 500);
        }
    }

}

