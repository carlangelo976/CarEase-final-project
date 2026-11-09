<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
class CarEase extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->model('CarEaseModel');
        if (session_status() !== PHP_SESSION_ACTIVE)
            session_start();
    }
    private function logged()
    {
        if (empty($_SESSION['carease_user_id'])) {
            redirect('carease/login');
            exit;
        }
    }
    private function admin()
    {
        $this->logged();
        if (!$this->isAdmin()) {
            http_response_code(403);
            exit('CarEase admin only.');
        }
    }
    private function isAdmin()
    {
        return ($_SESSION['carease_role'] ?? '') === 'admin';
    }
    private function currentUser()
    {
        $id = (int) ($_SESSION['carease_user_id'] ?? 0);
        return $id > 0 ? $this->CarEaseModel->user($id) : null;
    }
    private function hasIdOnFile($user, $allowPending = true)
    {
        $status = (string) ($user['id_status'] ?? 'none');
        return $allowPending
            ? in_array($status, ['pending', 'verified'], true)
            : $status === 'verified';
    }
    private function idUploadDir()
    {
        return ROOT_DIR . 'storage' . DIRECTORY_SEPARATOR . 'carease' . DIRECTORY_SEPARATOR . 'ids';
    }
    private function runNotifications($dryRun = false)
    {
        try {
            require_once __DIR__ . '/../libraries/CarEaseNotifications.php';
            $runner = new CarEaseNotifications($this->CarEaseModel, $dryRun);
            return $runner->run();
        } catch (Throwable $e) {
            error_log('CarEase notifications: ' . $e->getMessage());
            return null;
        }
    }
    private function contractsByBooking()
    {
        $grouped = [];
        foreach ($this->CarEaseModel->contracts() ?: [] as $c) {
            $grouped[(int) $c['booking_id']] = $c;
        }
        return $grouped;
    }
    private function contractTerms($booking, $car)
    {
        $vehicle = trim((string) ($car['brand'] ?? '') . ' ' . (string) ($car['name'] ?? ''));
        if ($vehicle === '') {
            $vehicle = 'the rented vehicle';
        }

        return "1. VEHICLE. The renter agrees to rent " . $vehicle . " from CarEase Rentals for the period "
            . (string) ($booking['pickup_date'] ?? '') . " to " . (string) ($booking['return_date'] ?? '') . ".\n"
            . "2. VALID ID. The renter must present a valid government-issued ID. The ID must be verified by "
            . "CarEase staff before the vehicle is released.\n"
            . "3. PAYMENT. The total rental fee is PHP " . (string) ($booking['total_amount'] ?? '0.00') . ". "
            . "Payment must be settled before the release of the vehicle unless another arrangement is approved in writing.\n"
            . "4. USE OF VEHICLE. The vehicle must be used only for lawful purposes. Subletting, racing, and "
            . "transporting illegal goods are strictly prohibited.\n"
            . "5. RETURN. The vehicle must be returned on or before the return date and time at the agreed "
            . "location, in the same condition as released, with the same fuel level.\n"
            . "6. LATE RETURN. Late returns may incur additional charges. CarEase may send reminder and overdue "
            . "notices to the renter's registered email address.\n"
            . "7. DAMAGE AND VIOLATIONS. The renter is responsible for traffic violations, fines, tolls, and "
            . "damage caused during the rental period, subject to the inspection report.\n"
            . "8. CANCELLATION. CarEase may cancel this agreement for invalid documents, non-payment, or violation "
            . "of these terms.\n"
            . "9. PRIVACY. The renter's ID and contact details are used only for verification, rental records, and "
            . "notifications related to this booking.\n"
            . "10. ACCEPTANCE. By signing, the renter confirms that they have read, understood, and accepted these terms.";
    }
    private function createContract($booking, $car)
    {
        $existing = $this->CarEaseModel->contract($booking['id']);
        if ($existing) {
            return $existing;
        }

        $vehicle = trim((string) ($car['brand'] ?? '') . ' ' . (string) ($car['name'] ?? ''));
        $now = date('Y-m-d H:i:s');
        $data = [
            'booking_id' => (int) $booking['id'],
            'contract_no' => 'CE-' . date('Ymd') . '-' . str_pad((string) $booking['id'], 4, '0', STR_PAD_LEFT),
            'customer_name' => (string) $booking['customer_name'],
            'customer_email' => (string) $booking['email'],
            'customer_phone' => (string) $booking['phone'],
            'car_description' => $vehicle === '' ? 'Vehicle' : $vehicle,
            'pickup_date' => (string) $booking['pickup_date'],
            'return_date' => (string) $booking['return_date'],
            'total_amount' => (string) $booking['total_amount'],
            'terms' => $this->contractTerms($booking, $car),
            'status' => 'draft',
            'created_by' => (int) ($_SESSION['carease_user_id'] ?? 0) ?: null,
            'created_at' => $now,
        ];

        $id = (int) $this->CarEaseModel->addContract($data);
        return $this->CarEaseModel->contractById($id) ?: array_merge($data, ['id' => $id]);
    }
    private function emailContract($booking, $contract, $car, &$error = null)
    {
        try {
            require_once __DIR__ . '/../config/carease_email_notification.php';
            $mailer = new CarEaseEmailNotification();
            $mailer->contractAgreement($booking, $contract, $car);
            if ((string) ($contract['status'] ?? '') !== 'signed') {
                $this->CarEaseModel->updateContract($contract['id'], [
                    'status' => 'sent',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
            return true;
        } catch (Throwable $e) {
            $error = $e->getMessage();
            error_log('CarEase contract email: ' . $e->getMessage());
            return false;
        }
    }
    private function view($name, $data = [])
    {
        $data['isAdmin'] = $this->isAdmin();
        $data['careaseUser'] = $_SESSION['carease_name'] ?? null;
        $data['csrf'] = $this->token();
        $data['stats'] = $this->fleetStats();
        $this->call->view('carease/' . $name, $data);
    }
    private function fleetStats()
    {
        $cars = $this->CarEaseModel->cars() ?: [];
        $bookings = $this->CarEaseModel->bookings() ?: [];

        $customers = [];
        foreach ($bookings as $b) {
            if (!empty($b['email'])) {
                $customers[strtolower($b['email'])] = true;
            }
        }

        $brands = [];
        foreach ($cars as $c) {
            $brands[strtolower($c['brand'] ?? '')] = true;
        }

        return [
            'customers' => count($customers),
            'cars' => count($cars),
            'locations' => count(array_filter(array_keys($brands))),
        ];
    }
    private function token()
    {
        if (empty($_SESSION['carease_csrf']))
            $_SESSION['carease_csrf'] = bin2hex(random_bytes(24));
        return $_SESSION['carease_csrf'];
    }
    private function verifyToken()
    {
        if (!hash_equals($this->token(), (string) ($_POST['csrf'] ?? ''))) {
            http_response_code(403);
            exit('Invalid form token. Reload the page.');
        }
    }
    public function login()
    {
        if (!empty($_SESSION['carease_user_id'])) {
            redirect('carease');
            return;
        }
        $error = '';
        if ($this->io->method() === 'post') {
            $this->verifyToken();
            $email = strtolower(trim((string) $this->io->post('email')));
            $password = (string) $this->io->post('password');
            $user = $this->CarEaseModel->userByEmail($email);
            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['carease_user_id'] = (int) $user['id'];
                $_SESSION['carease_name'] = $user['full_name'];
                $_SESSION['carease_role'] = $user['role'];
                redirect($user['role'] === 'admin' ? 'carease/admin' : 'carease/account');
                return;
            }
            $error = 'Invalid email or password.';
        }
        $this->view('login', ['error' => $error]);
    }
    public function register()
    {
        if (!empty($_SESSION['carease_user_id'])) {
            redirect('carease');
            return;
        }
        $error = '';
        if ($this->io->method() === 'post') {
            $this->verifyToken();
            $name = trim((string) $this->io->post('full_name'));
            $email = strtolower(trim((string) $this->io->post('email')));
            $password = (string) $this->io->post('password');
            $confirm = (string) $this->io->post('confirm_password');
            if (mb_strlen($name) < 2 || mb_strlen($name) > 120 || preg_match('/[0-9]/u', $name))
                $error = 'Enter a valid full name without numbers.';
            elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190)
                $error = 'Enter a valid email.';
            elseif (strlen($password) < 8)
                $error = 'Password must be at least 8 characters.';
            elseif ($password !== $confirm)
                $error = 'Passwords do not match.';
            elseif ($this->CarEaseModel->userByEmail($email))
                $error = 'Email already registered.';
            else {
                $this->CarEaseModel->addUser(['full_name' => $name, 'email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'role' => 'client', 'created_at' => date('Y-m-d H:i:s')]);
                redirect('carease/login');
                return;
            }
        }
        $this->view('register', ['error' => $error]);
    }
    public function logout()
    {
        if ($this->io->method() !== 'post') {
            http_response_code(405);
            exit('POST required');
        }
        $this->verifyToken();
        unset($_SESSION['carease_user_id'], $_SESSION['carease_name'], $_SESSION['carease_role']);
        session_regenerate_id(true);
        redirect('carease/login');
    }
    public function index()
    {
        $this->view('cars', ['cars' => $this->CarEaseModel->cars() ?: []]);
    }
    public function book($id)
    {
        $this->logged();
        $car = $this->CarEaseModel->car($id);
        if (!$car || $car['status'] !== 'available') {
            http_response_code(404);
            exit('Car not available');
        }
        $user = $this->currentUser();
        $hasId = $this->hasIdOnFile($user);
        $blockedRanges = $this->CarEaseModel->blockedRanges($id);
        $error = '';
        if ($this->io->method() === 'post') {
            $this->verifyToken();
            $start = (string) $this->io->post('pickup_date');
            $end = (string) $this->io->post('return_date');
            $name = trim((string) $this->io->post('customer_name'));
            $email = trim((string) $this->io->post('email'));
            $phone = trim((string) $this->io->post('phone'));
            $a = DateTimeImmutable::createFromFormat('!Y-m-d', $start);
            $b = DateTimeImmutable::createFromFormat('!Y-m-d', $end);
            if (!$hasId)
                $error = 'Upload a valid ID in My Account before booking a car.';
            elseif (!$a || !$b || $a->format('Y-m-d') !== $start || $b->format('Y-m-d') !== $end || $a < new DateTimeImmutable('today') || $b <= $a)
                $error = 'Please choose valid future dates (return after pickup).';
            elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[0-9+() -]{7,20}$/', $phone))
                $error = 'Enter valid name, email and phone.';
            elseif (!$this->CarEaseModel->available($id, $start, $end))
                $error = 'Car is already booked for these dates. Please pick the next available dates.';
            else {
                $days = (int) $a->diff($b)->days;
                $newBookingId = (int) $this->CarEaseModel->addBooking(['user_id' => (int) $_SESSION['carease_user_id'], 'car_id' => (int) $id, 'customer_name' => $name, 'email' => $email, 'phone' => $phone, 'pickup_date' => $start, 'return_date' => $end, 'total_amount' => number_format($days * (float) $car['price_per_day'], 2, '.', ''), 'status' => 'pending', 'created_at' => date('Y-m-d H:i:s')]);

                // New reservations are pending but must still block the calendar
                // immediately so the next renter cannot pick the same dates.
                $booking = $this->CarEaseModel->booking($newBookingId);
                if ($booking) {
                    $_SESSION['carease_calendar_notice'] = $this->calendarSync($booking, 'sync');
                }

                redirect('carease/my-bookings');
                return;
            }
        }
        $this->view('book', [
            'car' => $car,
            'error' => $error,
            'customer' => $user,
            'hasId' => $hasId,
            'blockedRanges' => $blockedRanges,
        ]);
    }
    /**
     * Lets a client submit or replace their valid ID.
     */
    public function myId()
    {
        $this->logged();
        $user = $this->currentUser();
        if (!$user) {
            session_regenerate_id(true);
            unset($_SESSION['carease_user_id'], $_SESSION['carease_name'], $_SESSION['carease_role']);
            redirect('carease/login');
            return;
        }
        $error = '';
        $notice = '';

        if ($this->io->method() === 'post') {
            $this->verifyToken();
            $type = trim((string) $this->io->post('id_type'));
            $number = trim((string) $this->io->post('id_number'));
            $allowedTypes = ['Philippine Passport', "Driver's License", 'UMID', 'PhilHealth ID', 'Postal ID', 'PRC ID', 'National ID'];
            $uploadError = '';

            if (!in_array($type, $allowedTypes, true))
                $error = 'Choose a valid ID type.';
            elseif (!preg_match('/^[A-Za-z0-9#\- ]{3,60}$/', $number))
                $error = 'Enter a valid ID number (3-60 letters, numbers, dashes or spaces).';
            else {
                $uploaded = $this->handleIdImageUpload((int) $user['id'], $uploadError);
                if ($uploaded === false) {
                    $error = $uploadError;
                } elseif ($uploaded === null && trim((string) ($user['id_image'] ?? '')) === '') {
                    $error = 'Upload a clear photo of your valid ID.';
                } else {
                    $data = [
                        'id_type' => $type,
                        'id_number' => $number,
                        'id_status' => 'pending',
                        'id_notes' => null,
                        'id_verified_at' => null,
                    ];
                    if ($uploaded !== null) {
                        $data['id_image'] = $uploaded;
                    }
                    $this->CarEaseModel->updateUser((int) $user['id'], $data);
                    $notice = 'Your ID was submitted and is waiting for admin verification.';
                    $user = $this->currentUser();
                }
            }
        }

        $this->view('id', [
            'user' => $user,
            'error' => $error,
            'notice' => $notice,
        ]);
    }

    /**
     * Streams an ID photo. Admins can view any, clients only their own.
     */
    public function idImage($id)
    {
        $this->logged();
        $id = (int) $id;
        $isOwner = $id === (int) ($_SESSION['carease_user_id'] ?? 0);
        if (!$isOwner && !$this->isAdmin()) {
            http_response_code(403);
            exit('Not allowed.');
        }
        $user = $this->CarEaseModel->user($id);
        if (!$user) {
            http_response_code(404);
            exit('ID photo not found.');
        }
        $file = basename((string) ($user['id_image'] ?? ''));
        if ($file === '') {
            http_response_code(404);
            exit('ID photo not found.');
        }
        $path = $this->idUploadDir() . DIRECTORY_SEPARATOR . $file;
        if (!is_file($path)) {
            http_response_code(404);
            exit('ID photo not found.');
        }
        $mime = function_exists('mime_content_type') ? (string) @mime_content_type($path) : 'application/octet-stream';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            $mime = 'application/octet-stream';
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    /**
     * Stores an uploaded ID photo outside the public folder.
     */
    private function handleIdImageUpload($userId, &$error)
    {
        if (empty($_FILES['id_image']) || !is_array($_FILES['id_image']))
            return null;

        $file = $_FILES['id_image'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE)
            return null;

        if (($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
            $error = 'The ID upload failed. Please try again.';
            return false;
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            $error = 'The uploaded ID file is not valid.';
            return false;
        }
        if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
            $error = 'The ID photo must be 5 MB or smaller.';
            return false;
        }

        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $mime = (string) (function_exists('mime_content_type') ? @mime_content_type($file['tmp_name']) : '');
        if (!isset($allowed[$mime])) {
            $info = @getimagesize($file['tmp_name']);
            $fallback = $info ? (string) ($info['mime'] ?? '') : '';
            if (!isset($allowed[$fallback])) {
                $error = 'Only JPG, PNG, WEBP or GIF images are allowed.';
                return false;
            }
            $mime = $fallback;
        }

        $dir = $this->idUploadDir();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            $error = 'The ID upload folder is not writable.';
            return false;
        }

        $name = 'id-' . (int) $userId . '-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
        $target = $dir . DIRECTORY_SEPARATOR . $name;
        if (!@move_uploaded_file($file['tmp_name'], $target)) {
            $error = 'The ID photo could not be saved.';
            return false;
        }

        $existing = $this->CarEaseModel->user($userId);
        $old = basename((string) ($existing['id_image'] ?? ''));
        if ($old !== '' && $old !== $name) {
            $oldFile = $dir . DIRECTORY_SEPARATOR . $old;
            if (is_file($oldFile))
                @unlink($oldFile);
        }

        return $name;
    }

    /**
     * Admin approval/rejection of a client's submitted ID.
     */
    public function verifyId($id)
    {
        $this->admin();
        if ($this->io->method() !== 'post') {
            http_response_code(405);
            return;
        }
        $this->verifyToken();

        $user = $this->CarEaseModel->user($id);
        if (!$user) {
            http_response_code(404);
            exit('User not found.');
        }

        $action = (string) $this->io->post('action');
        $notes = trim((string) ($_POST['id_notes'] ?? ''));

        if ($action === 'approve') {
            if (empty($user['id_image']) || empty($user['id_type']) || empty($user['id_number'])) {
                http_response_code(422);
                exit('The client must submit an ID type, number and photo before it can be verified.');
            }
            $this->CarEaseModel->updateUser((int) $id, [
                'id_status' => 'verified',
                'id_notes' => null,
                'id_verified_at' => date('Y-m-d H:i:s'),
            ]);
            $_SESSION['carease_admin_notice'] = 'ID verified for ' . $user['full_name'] . '.';
        } elseif ($action === 'reject') {
            if ($notes === '' || mb_strlen($notes) > 255) {
                http_response_code(422);
                exit('A rejection reason is required (max 255 characters).');
            }
            $this->CarEaseModel->updateUser((int) $id, [
                'id_status' => 'rejected',
                'id_notes' => $notes,
                'id_verified_at' => null,
            ]);
            $_SESSION['carease_admin_notice'] = 'ID rejected for ' . $user['full_name'] . '.';
        } else {
            http_response_code(422);
            exit('Invalid ID verification action.');
        }

        redirect('carease/admin#ids');
    }

    /**
     * Returns the reserved date ranges for a car so the booking form can block
     * them before the request reaches the server.
     */
    public function availability($id)
    {
        $car = $this->CarEaseModel->car($id);
        if (!$car) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => false, 'error' => 'Car not found']);
            return;
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode([
            'status' => true,
            'car_id' => (int) $car['id'],
            'blocked' => $this->CarEaseModel->blockedRanges($id),
        ], JSON_UNESCAPED_SLASHES);
    }

    /**
     * Reviews and signs the rental agreement.
     */
    public function contract($id)
    {
        $this->logged();
        $booking = $this->CarEaseModel->booking($id);
        if (!$booking) {
            http_response_code(404);
            exit('Booking not found.');
        }
        $isAdmin = $this->isAdmin();
        $isOwner = (int) $booking['user_id'] === (int) ($_SESSION['carease_user_id'] ?? 0);
        if (!$isAdmin && !$isOwner) {
            http_response_code(403);
            exit('Not allowed.');
        }

        $car = $this->CarEaseModel->car($booking['car_id']);
        $contract = $this->CarEaseModel->contract($id);
        $client = $this->CarEaseModel->user($booking['user_id']);
        $error = '';
        $notice = '';

        if ($this->io->method() === 'post') {
            $this->verifyToken();
            $action = (string) $this->io->post('action');

            if ($action === 'generate') {
                if (!$isAdmin) {
                    http_response_code(403);
                    exit('Admin only.');
                }
                $contract = $this->createContract($booking, $car);
                $mailError = '';
                if ($this->emailContract($booking, $contract, $car, $mailError)) {
                    $notice = 'Rental agreement sent to ' . $booking['email'] . '.';
                    $contract = $this->CarEaseModel->contract($id);
                } else {
                    $error = 'Agreement saved, but the email could not be sent: ' . $mailError;
                }
            } elseif ($action === 'sign') {
                if ($isAdmin) {
                    http_response_code(403);
                    exit('The client must sign the agreement.');
                }
                $signature = trim((string) $this->io->post('signature'));
                if (!$contract) {
                    $error = 'The rental agreement is not ready yet.';
                } elseif ($contract['status'] === 'signed') {
                    $error = 'This agreement has already been signed.';
                } elseif (!in_array($contract['status'], ['draft', 'sent'], true)) {
                    $error = 'This agreement is no longer available for signing.';
                } elseif (!in_array($booking['status'], ['approved', 'confirmed'], true)) {
                    $error = 'This agreement can only be signed while the booking is approved.';
                } elseif (!$client) {
                    $error = 'The client account for this booking is no longer available.';
                } elseif (!$this->hasIdOnFile($client, false)) {
                    $error = 'Your valid ID must be verified before signing the agreement.';
                } elseif ($signature === '' || strcasecmp($signature, (string) $client['full_name']) !== 0) {
                    $error = 'Type your full name exactly as registered to sign.';
                } else {
                    $this->CarEaseModel->updateContract((int) $contract['id'], [
                        'status' => 'signed',
                        'customer_signature' => $signature,
                        'signed_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    $contract = $this->CarEaseModel->contract($id);
                    try {
                        require_once __DIR__ . '/../config/carease_email_notification.php';
                        $mailer = new CarEaseEmailNotification();
                        $mailer->contractSigned($booking, $contract, $car);
                    } catch (Throwable $e) {
                        error_log('CarEase contract signed email: ' . $e->getMessage());
                    }
                    $notice = 'Agreement signed. A copy was sent to ' . $booking['email'] . '.';
                }
            } else {
                $error = 'Unknown agreement action.';
            }
        }

        $this->view('contract', [
            'booking' => $booking,
            'car' => $car,
            'contract' => $contract,
            'client' => $client,
            'error' => $error,
            'notice' => $notice,
        ]);
    }

    /**
     * Sends pending return reminders and overdue notices. Callable from the CLI
     * cron command (php lava carease:notify) and from the admin dashboard.
     */
    public function notifications()
    {
        $dryRun = IS_CLI
            ? in_array('--dry-run', $_SERVER['argv'] ?? [], true)
            : !empty($_POST['dry_run']);

        if (!IS_CLI) {
            $this->admin();
            if ($this->io->method() !== 'post') {
                http_response_code(405);
                return;
            }
            $this->verifyToken();
        }
        $summary = $this->runNotifications($dryRun);

        if (IS_CLI) {
            if ($summary === null) {
                fwrite(STDERR, 'Notification run failed. Check the PHP error log.' . PHP_EOL);
                exit(1);
            }
            if ($dryRun) {
                echo 'DRY RUN - no emails were sent and no booking records were changed.' . PHP_EOL;
            }
            echo 'Checked ' . (int) $summary['checked'] . ' booking(s).' . PHP_EOL;
            echo 'Return reminders ' . ($dryRun ? 'that would be sent' : 'sent') . ': ' . (int) $summary['reminders'] . PHP_EOL;
            echo 'Overdue notices ' . ($dryRun ? 'that would be sent' : 'sent') . ': ' . (int) $summary['overdue'] . PHP_EOL;
            foreach ($summary['errors'] as $message) {
                fwrite(STDERR, 'Error: ' . $message . PHP_EOL);
            }
            exit(empty($summary['errors']) ? 0 : 1);
        }

        if ($summary === null) {
            $_SESSION['carease_admin_notice'] = 'Notification run failed. Check the PHP error log.';
        } else {
            $_SESSION['carease_admin_notice'] = ($dryRun ? 'Dry run: ' : '')
                . 'Notifications checked ' . (int) $summary['checked']
                . ' booking(s): ' . (int) $summary['reminders'] . ' reminder(s), '
                . (int) $summary['overdue'] . ' overdue notice(s).'
                . ($summary['errors'] ? ' Errors: ' . implode(' | ', $summary['errors']) : '');
        }
        redirect('carease/admin');
    }

    public function mine()
    {
        $this->logged();
        $all = $this->CarEaseModel->bookings() ?: [];
        $mine = array_values(array_filter($all, function ($b) {
            return (int) $b['user_id'] === (int) $_SESSION['carease_user_id'];
        }));
        $notice = $_SESSION['carease_calendar_notice'] ?? '';
        unset($_SESSION['carease_calendar_notice']);

        $this->view('bookings', [
            'bookings' => $mine,
            'cars' => $this->CarEaseModel->cars() ?: [],
            'payments' => $this->paymentsByBooking(),
            'contracts' => $this->contractsByBooking(),
            'notice' => $notice,
        ]);
    }

    /**
     * Client dashboard shown inside the collapsible app shell.
     */
    public function account()
    {
        $this->logged();
        $this->runNotifications();
        $all = $this->CarEaseModel->bookings() ?: [];
        $mine = array_values(array_filter($all, function ($b) {
            return (int) $b['user_id'] === (int) $_SESSION['carease_user_id'];
        }));
        $cars = $this->CarEaseModel->cars() ?: [];
        $available = array_values(array_filter($cars, function ($c) {
            return ($c['status'] ?? '') === 'available';
        }));

        $this->view('account', [
            'bookings' => $mine,
            'cars' => $cars,
            'availableCars' => $available,
            'payments' => $this->paymentsByBooking(),
            'contracts' => $this->contractsByBooking(),
            'profile' => $this->currentUser(),
        ]);
    }

    private function paymentsByBooking()
    {
        $grouped = [];
        foreach ($this->CarEaseModel->payments() ?: [] as $p) {
            $grouped[(int) $p['booking_id']][] = $p;
        }
        return $grouped;
    }

    public function dashboard()
    {
        $this->admin();

        $this->CarEaseModel->markOverdueBookings();
        $summary = $this->runNotifications();

        $notice = $_SESSION['carease_admin_notice'] ?? '';
        unset($_SESSION['carease_admin_notice']);
        if ($summary && !empty($summary['errors']) && $notice === '') {
            $notice = 'Notification email error: ' . implode(' | ', $summary['errors']);
        }

        $this->view('admin', [
            'cars' => $this->CarEaseModel->cars() ?: [],
            'bookings' => $this->CarEaseModel->bookings() ?: [],
            'payments' => $this->paymentsByBooking(),
            'contracts' => $this->contractsByBooking(),
            'idUsers' => $this->CarEaseModel->usersWithValidId() ?: [],
            'gcalEmbedUrl' => $this->gcalEmbedUrl(),
            'notice' => $notice,
        ]);
    }


    private function gcalConfig()
    {
        require_once __DIR__ . '/../libraries/CarEaseGoogleCalendar.php';
        return CarEaseGoogleCalendar::config();
    }

    /**
     * Embeddable Google Calendar (month view) for the admin dashboard.
     */
    private function gcalEmbedUrl()
    {
        require_once __DIR__ . '/../libraries/CarEaseGoogleCalendar.php';
        return CarEaseGoogleCalendar::embedUrl();
    }

    private function calendarSync($booking, $action = 'sync')
    {
        try {
            require_once __DIR__ . '/../libraries/CarEaseCalendarSync.php';
            $calendar = new CarEaseCalendarSync();
            $car = $this->CarEaseModel->car($booking['car_id']);
            $result = $action === 'delete'
                ? $calendar->delete((int) $booking['id'])
                : $calendar->sync($booking, $car);
            if ($action === 'delete')
                return $result === 'deleted' ? 'Calendar event removed.' : 'No calendar event to remove.';
            return 'Calendar event ' . $result . '.';
        } catch (Throwable $e) {
            error_log('CarEase Calendar auto-sync: ' . $e->getMessage());
            return 'Calendar sync failed: ' . $e->getMessage();
        }
    }
    public function saveCar()
    {
        $this->admin();
        if ($this->io->method() !== 'post') {
            http_response_code(405);
            return;
        }
        $this->verifyToken();
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) $this->io->post('name'));
        $brand = trim((string) $this->io->post('brand'));
        $price = $this->io->post('price_per_day');
        $seats = (int) $this->io->post('seats');
        $status = (string) $this->io->post('status');
        if ($name === '' || $brand === '' || !is_numeric($price) || (float) $price <= 0 || $seats < 1 || $seats > 30 || !in_array($status, ['available', 'maintenance'], true)) {
            http_response_code(422);
            exit('Invalid car details.');
        }

        if ($status === 'available') {
            foreach ($this->CarEaseModel->maintenanceForCar($id) ?: [] as $m) {
                if (in_array($m['status'], ['scheduled', 'in_progress'], true)) {
                    http_response_code(422);
                    exit('Cannot mark this car available while maintenance is ongoing or scheduled.');
                }
            }
        }

        $d = ['name' => $name, 'brand' => $brand, 'price_per_day' => number_format((float) $price, 2, '.', ''), 'seats' => $seats, 'status' => $status];

        // Save the car first so the uploaded photo can be named after its id.
        if ($id > 0)
            $this->CarEaseModel->updateCar($id, $d);
        else
            $id = (int) $this->CarEaseModel->addCar($d);

        $uploadError = '';
        $uploaded = $this->handleCarImageUpload($id, $uploadError);
        if ($uploaded !== null) {
            if ($uploaded === false) {
                $_SESSION['carease_admin_notice'] = $uploadError ?: 'The photo could not be uploaded.';
                redirect('carease/admin');
                return;
            }
            $this->CarEaseModel->updateCar($id, ['image_path' => $uploaded]);
        }
        redirect('carease/admin');
    }

    /**
     * Stores an uploaded car photo under public/uploads/carease.
     *
     * Returns the stored file name on success, false on failure and null when
     * no file was submitted (or the "remove photo" box was ticked).
     */
    private function handleCarImageUpload($carId, &$error)
    {
        if (!empty($_POST['remove_image'])) {
            $this->deleteCarPhoto((int) $carId);
            return '';
        }

        if (empty($_FILES['car_image']) || !is_array($_FILES['car_image']))
            return null;

        $file = $_FILES['car_image'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE)
            return null;

        if (($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
            $error = 'The photo upload failed. Please try again.';
            return false;
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            $error = 'The uploaded file is not valid.';
            return false;
        }
        if ((int) ($file['size'] ?? 0) > 4 * 1024 * 1024) {
            $error = 'The photo must be 4 MB or smaller.';
            return false;
        }

        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $mime = (string) (function_exists('mime_content_type') ? @mime_content_type($file['tmp_name']) : '');

        if (!isset($allowed[$mime])) {
            $info = @getimagesize($file['tmp_name']);
            $fallback = $info ? (string) ($info['mime'] ?? '') : '';
            if (!isset($allowed[$fallback])) {
                $error = 'Only JPG, PNG, WEBP or GIF images are allowed.';
                return false;
            }
            $mime = $fallback;
        }

        $dir = $this->carUploadDir();
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            $error = 'The upload folder is not writable.';
            return false;
        }

        $name = 'car-' . (int) $carId . '-' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
        $target = $dir . DIRECTORY_SEPARATOR . $name;

        if (!@move_uploaded_file($file['tmp_name'], $target)) {
            $error = 'The photo could not be saved.';
            return false;
        }

        // Drop the previous photo so uploads do not pile up.
        if ($carId > 0) {
            $existing = $this->CarEaseModel->car($carId);
            $old = trim((string) ($existing['image_path'] ?? ''));
            if ($old !== '' && $old !== $name) {
                $oldFile = $this->carUploadDir() . DIRECTORY_SEPARATOR . basename($old);
                if (is_file($oldFile))
                    @unlink($oldFile);
            }
        }

        return $name;
    }

    /**
     * Absolute path of the folder that holds the uploaded car photos.
     */
    private function carUploadDir()
    {
        return ROOT_DIR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'carease';
    }

    /**
     * Removes the stored photo of a car from disk.
     */
    private function deleteCarPhoto($carId)
    {
        if ($carId <= 0)
            return;
        $existing = $this->CarEaseModel->car($carId);
        $old = trim((string) ($existing['image_path'] ?? ''));
        if ($old === '')
            return;
        $file = $this->carUploadDir() . DIRECTORY_SEPARATOR . basename($old);
        if (is_file($file))
            @unlink($file);
    }
    public function removeCar($id)
    {
        $this->admin();
        if ($this->io->method() !== 'post') {
            http_response_code(405);
            return;
        }
        $this->verifyToken();

        foreach ($this->CarEaseModel->bookings() ?: [] as $b) {
            if (
                (int) $b['car_id'] === (int) $id &&
                in_array($b['status'], ['pending', 'approved', 'confirmed', 'ongoing', 'overdue'], true)
            ) {
                http_response_code(422);
                exit('Cannot delete this car: it has active or upcoming bookings.');
            }
        }

        $this->deleteCarPhoto((int) $id);
        $this->CarEaseModel->deleteCar($id);
        redirect('carease/admin');
    }

    public function status($id)
    {
        $this->admin();

        if ($this->io->method() !== 'post') {
            http_response_code(405);
            return;
        }

        $this->verifyToken();

        $booking = $this->CarEaseModel->booking($id);
        $new = (string) $this->io->post('status');

        $allowed = ['pending', 'approved', 'ongoing', 'overdue', 'completed', 'rejected', 'cancelled'];

        if (!$booking || !in_array($new, $allowed, true)) {
            http_response_code(422);
            exit('Invalid booking status change.');
        }

        if ($new === 'overdue') {
            if (date('Y-m-d') <= $booking['return_date']) {
                http_response_code(422);
                exit('Booking is not overdue yet.');
            }
        }

        if ($new === 'ongoing') {
            $client = $this->CarEaseModel->user($booking['user_id']);
            if (!$client || (string) ($client['id_status'] ?? '') !== 'verified') {
                http_response_code(422);
                exit('The client must submit and have a valid ID verified before the vehicle can be released.');
            }
            $contract = $this->CarEaseModel->contract($id);
            if (!$contract || $contract['status'] !== 'signed') {
                http_response_code(422);
                exit('The client must sign the rental agreement before the vehicle can be released.');
            }
        }

        if ($new === 'ongoing' || $new === 'completed') {
            $inspectionRecords = $this->CarEaseModel->inspections($id) ?: [];

            $hasPre = false;
            $hasPost = false;

            foreach ($inspectionRecords as $record) {
                if ($record['inspection_type'] === 'pre') {
                    $hasPre = true;
                }

                if ($record['inspection_type'] === 'post') {
                    $hasPost = true;
                }
            }

            if ($new === 'ongoing' && !$hasPre) {
                http_response_code(422);
                exit('Pre-inspection is required before vehicle release.');
            }

            if ($new === 'completed' && !$hasPost) {
                http_response_code(422);
                exit('Post-inspection is required before completing the rental.');
            }
        }


        $this->CarEaseModel->updateBooking($id, ['status' => $new]);

        $updatedBooking = $this->CarEaseModel->booking($id);
        $car = $this->CarEaseModel->car($updatedBooking['car_id']);
        $contractNotice = '';

        try {
            require_once __DIR__ . '/../config/carease_email_notification.php';

            $emailNotification = new CarEaseEmailNotification();

            $emailNotification->bookingStatus(
                $updatedBooking,
                $car,
                $new
            );
        } catch (Throwable $e) {
            error_log('CarEase Email API: ' . $e->getMessage());
        }

        if ($new === 'approved') {
            $contract = $this->createContract($updatedBooking, $car);
            $contractError = '';
            if ($this->emailContract($updatedBooking, $contract, $car, $contractError)) {
                $contractNotice = ' Rental agreement sent to the client.';
            } else {
                $contractNotice = ' Rental agreement saved but email failed: ' . $contractError;
            }
        } elseif (in_array($new, ['rejected', 'cancelled'], true)) {
            $contract = $this->CarEaseModel->contract($id);
            if ($contract) {
                $this->CarEaseModel->updateContract((int) $contract['id'], [
                    'status' => 'cancelled',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        $booking = $this->CarEaseModel->booking($id);
        $action = in_array($new, ['rejected', 'cancelled'], true) ? 'delete' : 'sync';

        $syncNotice = $this->calendarSync($booking, $action);
        $message = 'Booking #' . $id . ' set to ' . $new . '. ' . $syncNotice . $contractNotice;

        $_SESSION['carease_calendar_notice'] = $message;
        $_SESSION['carease_admin_notice'] = $message;

        redirect('carease/admin');
    }

    public function inspection($id)
    {
        $this->admin();

        $booking = $this->CarEaseModel->booking($id);
        if (!$booking) {
            http_response_code(404);
            exit('Booking not found.');
        }

        $car = $this->CarEaseModel->car($booking['car_id']);
        if (!$car) {
            http_response_code(404);
            exit('Vehicle not found.');
        }

        $records = $this->CarEaseModel->inspections($id) ?: [];
        $inspections = [];
        foreach ($records as $record) {
            $inspections[$record['inspection_type']] = $record;
        }

        $error = '';

        if ($this->io->method() === 'post') {
            $this->verifyToken();

            $type = (string) ($_POST['inspection_type'] ?? '');
            $fuel = (string) ($_POST['fuel_level'] ?? '');
            $mileage = filter_var(
                $_POST['mileage'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 0, 'max_range' => 99999999]]
            );
            $condition = (string) ($_POST['vehicle_condition'] ?? '');
            $notes = trim((string) ($_POST['damage_notes'] ?? ''));

            $validStage =
                ($type === 'pre' &&
                    in_array($booking['status'], ['approved', 'confirmed'], true)) ||
                ($type === 'post' &&
                    in_array($booking['status'], ['ongoing', 'overdue'], true));

            if (!$validStage || isset($inspections[$type])) {
                $error = 'Inspection is not allowed at this stage or already exists.';
            } elseif (
                !in_array($fuel, ['empty', 'quarter', 'half', 'three_quarter', 'full'], true)
                || $mileage === false
                || !in_array($condition, ['good', 'minor', 'major'], true)
                || strlen($notes) > 2000
            ) {
                $error = 'Please enter valid inspection details.';
            } elseif (
                $type === 'post' && isset($inspections['pre'])
                && $mileage < (int) $inspections['pre']['mileage']
            ) {
                $error = 'Return mileage cannot be lower than pickup mileage.';
            } else {
                $this->CarEaseModel->addInspection([
                    'booking_id' => (int) $id,
                    'inspection_type' => $type,
                    'fuel_level' => $fuel,
                    'mileage' => $mileage,
                    'vehicle_condition' => $condition,
                    'damage_notes' => $notes,
                    'inspected_at' => date('Y-m-d H:i:s')
                ]);

                redirect('carease/admin/inspection/' . (int) $id);
                return;
            }
        }

        $this->view('inspection', [
            'booking' => $booking,
            'car' => $car,
            'inspections' => $inspections,
            'inspectionError' => $error
        ]);
    }


}
