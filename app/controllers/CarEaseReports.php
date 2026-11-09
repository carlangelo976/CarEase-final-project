<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class CarEaseReports extends Controller
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

    private function data()
    {
        $bookings = $this->CarEaseModel->bookings() ?: [];
        $cars = $this->CarEaseModel->cars() ?: [];
        $maintenance = $this->CarEaseModel->maintenanceRecords() ?: [];
        $statuses = ['pending' => 0, 'approved' => 0, 'confirmed' => 0, 'ongoing' => 0, 'overdue' => 0, 'completed' => 0, 'rejected' => 0, 'cancelled' => 0];
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $key = date('Y-m', strtotime('first day of this month -' . $i . ' months'));
            $months[$key] = ['label' => date('M Y', strtotime($key . '-01')), 'bookings' => 0, 'value' => 0.0, 'maintenance' => 0.0];
        }
        $totalValue = 0.0;
        foreach ($bookings as $b) {
            $status = (string) ($b['status'] ?? '');
            if (isset($statuses[$status]))
                $statuses[$status]++;
            $month = substr((string) ($b['created_at'] ?? ''), 0, 7);
            if (isset($months[$month]))
                $months[$month]['bookings']++;
            if (in_array($status, ['approved', 'confirmed', 'ongoing', 'overdue', 'completed'], true)) {
                $amount = (float) ($b['total_amount'] ?? 0);
                $totalValue += $amount;
                if (isset($months[$month]))
                    $months[$month]['value'] += $amount;
            }
        }
        $available = 0;
        $underMaintenance = 0;
        foreach ($cars as $car) {
            if (($car['status'] ?? '') === 'available')
                $available++;
            if (($car['status'] ?? '') === 'maintenance')
                $underMaintenance++;
        }
        $maintenanceTotal = 0.0;
        foreach ($maintenance as $m) {
            if (($m['status'] ?? '') !== 'completed')
                continue;
            $cost = (float) ($m['cost'] ?? 0);
            $maintenanceTotal += $cost;
            $month = substr((string) ($m['scheduled_date'] ?? ''), 0, 7);
            if (isset($months[$month]))
                $months[$month]['maintenance'] += $cost;
        }
        return compact('bookings', 'cars', 'maintenance', 'statuses', 'months', 'totalValue', 'available', 'underMaintenance', 'maintenanceTotal');
    }

    private function token()
    {
        if (empty($_SESSION['carease_csrf']))
            $_SESSION['carease_csrf'] = bin2hex(random_bytes(24));
        return $_SESSION['carease_csrf'];
    }

    public function index()
    {
        $this->guard();
        $data = $this->data();
        $data['isAdmin'] = true;
        $data['careaseUser'] = $_SESSION['carease_name'] ?? null;
        $data['csrf'] = $this->token();
        $this->call->view('carease/reports', $data);
    }

    public function export()
    {
        $this->guard();
        $rows = $this->CarEaseModel->bookings() ?: [];
        $start = (string) ($_GET['start'] ?? '');
        $end = (string) ($_GET['end'] ?? '');
        foreach ([$start, $end] as $date) {
            if ($date !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4)))) {
                http_response_code(422);
                exit('Invalid date filter.');
            }
        }
        if ($start !== '' && $end !== '' && $start > $end) {
            http_response_code(422);
            exit('Invalid date range.');
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="carease-bookings-' . date('Ymd') . '.csv"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Booking ID', 'Customer', 'Email', 'Car ID', 'Pickup', 'Return', 'Booking Value PHP', 'Status', 'Created At']);
        foreach ($rows as $b) {
            $created = substr((string) ($b['created_at'] ?? ''), 0, 10);
            if ($start !== '' && $created < $start)
                continue;
            if ($end !== '' && $created > $end)
                continue;
            $values = [$b['id'] ?? '', $b['customer_name'] ?? '', $b['email'] ?? '', $b['car_id'] ?? '', $b['pickup_date'] ?? '', $b['return_date'] ?? '', $b['total_amount'] ?? '', $b['status'] ?? '', $b['created_at'] ?? ''];
            // Prevent spreadsheet formula injection in exported text fields.
            $values = array_map(static function ($v) {
                $s = (string) $v;
                return preg_match('/^[\s]*[=+\-@]/u', $s) ? "'" . $s : $s; }, $values);
            fputcsv($out, $values);
        }
        fclose($out);
        exit;
    }
}
