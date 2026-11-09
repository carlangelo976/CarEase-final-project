<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class CarEaseMaintenance extends Controller
{
    public function __construct()
    {
        parent::__construct();
        if (session_status() !== PHP_SESSION_ACTIVE)
            session_start();
        $this->call->model('CarEaseModel');
    }

    private function admin()
    {
        if (empty($_SESSION['carease_user_id']) || ($_SESSION['carease_role'] ?? '') !== 'admin') {
            http_response_code(403);
            exit('CarEase admin login required.');
        }
    }

    private function csrf()
    {
        if (empty($_SESSION['carease_csrf']))
            $_SESSION['carease_csrf'] = bin2hex(random_bytes(24));
        return $_SESSION['carease_csrf'];
    }

    private function verifyPost()
    {
        if (strtolower($_SERVER['REQUEST_METHOD'] ?? '') !== 'post') {
            http_response_code(405);
            exit('POST required.');
        }
        if (!hash_equals($this->csrf(), (string) ($_POST['csrf'] ?? ''))) {
            http_response_code(403);
            exit('Invalid form token.');
        }
    }

    private function fail($message)
    {
        $_SESSION['carease_maintenance_notice'] = $message;
        redirect('carease/admin/maintenance');
    }

    public function index()
    {
        $this->admin();
        $notice = $_SESSION['carease_maintenance_notice'] ?? '';
        unset($_SESSION['carease_maintenance_notice']);
        $this->call->view('carease/maintenance', [
            'cars' => $this->CarEaseModel->cars() ?: [],
            'records' => $this->CarEaseModel->maintenanceRecords() ?: [],
            'csrf' => $this->csrf(),
            'notice' => $notice,
            'isAdmin' => true,
            'careaseUser' => $_SESSION['carease_name'] ?? null,
        ]);
    }

    public function save()
    {
        $this->admin();
        $this->verifyPost();
        $carId = filter_var($_POST['car_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $type = trim((string) ($_POST['maintenance_type'] ?? ''));
        $date = (string) ($_POST['scheduled_date'] ?? '');
        $cost = (string) ($_POST['cost'] ?? '');
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (
            !$carId || !$this->CarEaseModel->car($carId) || $type === '' || strlen($type) > 100 ||
            !$d || $d->format('Y-m-d') !== $date || !is_numeric($cost) || (float) $cost < 0 ||
            (float) $cost > 9999999999.99 || strlen($notes) > 2000
        ) {
            $this->fail('Invalid maintenance details.');
            return;
        }
        $this->CarEaseModel->addMaintenance([
            'car_id' => $carId,
            'maintenance_type' => $type,
            'scheduled_date' => $date,
            'cost' => number_format((float) $cost, 2, '.', ''),
            'status' => 'scheduled',
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->fail('Maintenance record created.');
    }

    public function update($id)
    {
        $this->admin();
        $this->verifyPost();
        $record = $this->CarEaseModel->maintenanceRecord($id);
        $new = (string) ($_POST['status'] ?? '');
        $allowed = ['scheduled' => ['in_progress'], 'in_progress' => ['completed']];
        if (!$record || !in_array($new, $allowed[$record['status']] ?? [], true)) {
            $this->fail('Invalid maintenance status change.');
            return;
        }
        $carId = (int) $record['car_id'];
        if ($new === 'in_progress') {
            foreach ($this->CarEaseModel->bookings() ?: [] as $b) {
                if ((int) $b['car_id'] === $carId && in_array($b['status'], ['ongoing', 'overdue'], true)) {
                    $this->fail('Cannot start maintenance while the vehicle is out on rental.');
                    return;
                }
            }
            $this->CarEaseModel->updateCar($carId, ['status' => 'maintenance']);
        }
        $this->CarEaseModel->updateMaintenance($id, ['status' => $new]);
        if ($new === 'completed') {
            $stillActive = false;
            foreach ($this->CarEaseModel->maintenanceForCar($carId) ?: [] as $m) {
                if ($m['status'] === 'in_progress')
                    $stillActive = true;
            }
            if (!$stillActive)
                $this->CarEaseModel->updateCar($carId, ['status' => 'available']);
        }
        $this->fail('Maintenance status updated.');
    }
}
