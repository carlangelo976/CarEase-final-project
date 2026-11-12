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
    public function annualPdf()
    {
        $this->guard();
        $yearRaw = (string) ($_GET['year'] ?? date('Y'));
        if (!preg_match('/^\d{4}$/', $yearRaw) || (int)$yearRaw < 2020 || (int)$yearRaw > 2100) {
            http_response_code(422);
            exit('Invalid report year.');
        }
        $year = (int)$yearRaw;
        $rows = $this->CarEaseModel->bookings() ?: [];
        $cars = $this->CarEaseModel->cars() ?: [];
        $maintenance = $this->CarEaseModel->maintenanceRecords() ?: [];
        $statuses = ['pending'=>0,'approved'=>0,'confirmed'=>0,'ongoing'=>0,'overdue'=>0,'completed'=>0,'rejected'=>0,'cancelled'=>0];
        $monthly = [];
        for ($m=1;$m<=12;$m++) $monthly[$m]=['bookings'=>0,'value'=>0.0,'maintenance'=>0.0];
        $annualBookings=[];
        $bookingValue=0.0;
        foreach($rows as $b) {
            $created=(string)($b['created_at'] ?? '');
            if(substr($created,0,4)!==(string)$year) continue;
            $annualBookings[]=$b;
            $status=(string)($b['status']??'');
            if(isset($statuses[$status]))$statuses[$status]++;
            $month=(int)substr($created,5,2);
            if($month<1||$month>12)continue;
            $monthly[$month]['bookings']++;
            if(in_array($status,['approved','confirmed','ongoing','overdue','completed'],true)) {
                $amount=(float)($b['total_amount']??0);
                $bookingValue+=$amount;
                $monthly[$month]['value']+=$amount;
            }
        }
        $maintenanceTotal=0.0;
        foreach($maintenance as $m) {
            if(($m['status']??'')!=='completed')continue;
            $date=(string)($m['scheduled_date']??'');
            if(substr($date,0,4)!==(string)$year)continue;
            $month=(int)substr($date,5,2);
            if($month<1||$month>12)continue;
            $cost=(float)($m['cost']??0);
            $maintenanceTotal+=$cost;
            $monthly[$month]['maintenance']+=$cost;
        }
        $available=0;$underMaintenance=0;
        foreach($cars as $car) {
            if(($car['status']??'')==='available')$available++;
            if(($car['status']??'')==='maintenance')$underMaintenance++;
        }
        require_once __DIR__ . '/../libraries/CarEaseAnnualPdf.php';
        $pdf=new CarEaseAnnualPdf();
        $logo=__DIR__.'/../assets/carease-report-logo.jpg';
        $money=static function($n){return 'PHP '.number_format((float)$n,2);};
        $pdf->page('YEAR '.$year.'  |  EXECUTIVE SUMMARY');
        $pdf->image($logo,42,96,205,137);
        $pdf->text(270,122,'ANNUAL SYSTEM REPORT',18,'#0B2747',true);
        $pdf->text(270,155,'Reporting year: '.$year,12,'#52667D');
        $pdf->text(270,180,'Generated: '.date('M d, Y h:i A'),10,'#52667D');
        $pdf->text(42,257,'PERFORMANCE OVERVIEW',15,'#0B2747',true);
        $cards=[
            ['TOTAL BOOKINGS',count($annualBookings)],
            ['FLEET (CURRENT)',count($cars)],
            ['ANNUAL BOOKING VALUE',$money($bookingValue)],
            ['ANNUAL MAINTENANCE COST',$money($maintenanceTotal)]
        ];
        foreach($cards as $i=>$card) {
            $x=42+($i%2)*260;$y=294+(int)floor($i/2)*105;
            $pdf->rect($x,$y,250,92,'#F0F5FA');
            $pdf->text($x+15,$y+16,$card[0],9,'#63778E',true);
            $pdf->text($x+15,$y+41,(string)$card[1],19,'#102A46',true);
        }
        $pdf->text(42,525,'BOOKING STATUS',15,'#0B2747',true);
        $i=0;
        foreach($statuses as $name=>$count) {
            $x=42+($i%2)*260;$y=558+(int)floor($i/2)*37;
            $pdf->rect($x,$y,250,30,'#F6F8FB');
            $pdf->text($x+10,$y+8,ucfirst($name),10,'#43566C');
            $pdf->text($x+205,$y+8,(string)$count,11,'#0C2844',true);
            $i++;
        }
        $pdf->text(42,734,'Fleet now: '.$available.' available / '.$underMaintenance.' in maintenance',10,'#52667D');
        $pdf->text(42,752,'Booking value is NOT the same as payment revenue.',9,'#9C7132',true);

        $pdf->page('YEAR '.$year.'  |  MONTHLY PERFORMANCE');
        $pdf->text(42,112,'MONTHLY BOOKING AND MAINTENANCE ACTIVITY',15,'#0B2747',true);
        $pdf->rect(42,155,511,34,'#0B2747');
        foreach([[52,'MONTH'],[154,'BOOKINGS'],[267,'BOOKING VALUE'],[418,'MAINTENANCE']] as $h)
            $pdf->text($h[0],165,$h[1],9,'#FFFFFF',true);
        $names=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        for($m=1;$m<=12;$m++) {
            $y=189+($m-1)*40;
            $pdf->rect(42,$y,511,40,$m%2?'#F3F7FB':'#FFFFFF');
            $pdf->text(52,$y+12,$names[$m-1].' '.$year,10);
            $pdf->text(164,$y+12,(string)$monthly[$m]['bookings'],10,'#0B2747',true);
            $pdf->text(267,$y+12,$money($monthly[$m]['value']),10);
            $pdf->text(418,$y+12,$money($monthly[$m]['maintenance']),10);
            $pdf->line(42,$y+40,553,$y+40);
        }
        $pdf->text(42,711,'BOOKING VALUE: '.$money($bookingValue),12,'#0B2747',true);
        $pdf->text(42,738,'Maintenance uses completed records grouped by scheduled date.',9,'#63778E');

        $pdf->page('YEAR '.$year.'  |  BOOKING LEDGER');
        $pdf->text(42,108,'BOOKINGS CREATED DURING '.$year,15,'#0B2747',true);
        $pdf->text(42,133,'Total records: '.count($annualBookings).'  |  Sorted by booking ID',10,'#63778E');
        $drawHeader=static function($pdf) {
            $pdf->rect(42,166,511,30,'#0B2747');
            foreach([[49,'ID'],[95,'CUSTOMER'],[245,'PICKUP'],[335,'RETURN'],[425,'VALUE'],[515,'STATUS']] as $h)
                $pdf->text($h[0],175,$h[1],8,'#FFFFFF',true);
        };
        $drawHeader($pdf);
        usort($annualBookings,static function($a,$b){return (int)($a['id']??0)<=>(int)($b['id']??0);});
        $row=0;
        foreach($annualBookings as $b) {
            if($row && $row%15===0) {
                $pdf->page('YEAR '.$year.'  |  BOOKING LEDGER (CONTINUED)');
                $drawHeader($pdf);
            }
            $y=196+($row%15)*37;
            $pdf->rect(42,$y,511,37,$row%2?'#FFFFFF':'#F1F5F9');
            $customer=(string)($b['customer_name']??'');
            if(strlen($customer)>22)$customer=substr($customer,0,19).'...';
            $pdf->text(49,$y+11,'#'.($b['id']??''),8);
            $pdf->text(95,$y+11,$customer,8);
            $pdf->text(245,$y+11,substr((string)($b['pickup_date']??''),0,10),8);
            $pdf->text(335,$y+11,substr((string)($b['return_date']??''),0,10),8);
            $pdf->text(425,$y+11,number_format((float)($b['total_amount']??0),0),8);
            $pdf->text(515,$y+11,substr((string)($b['status']??''),0,9),7);
            $row++;
        }
        if(!$row)$pdf->text(50,220,'No bookings found for this reporting year.',11,'#63778E');
        $pdf->text(42,768,'Source: live CarEase database. Payment receipts are not included.',9,'#63778E');
        $pdf->output('carease-annual-report-'.$year.'.pdf');
        exit;
    }

}
