
<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class CarEaseModel extends Model
{
    public function userByEmail($email)
    {
        return $this->db->table('carease_users')->where('email', $email)->get();
    }

    public function addUser($data)
    {
        return $this->db->table('carease_users')->insert($data);
    }

    public function staffAccounts()
    {
        return $this->db->table('carease_users')
            ->where('role', 'staff')
            ->get_all();
    }

    public function addStaff($data)
    {
        $data['role'] = 'staff';

        return $this->db->table('carease_users')
            ->insert($data);
    }

    public function user($id)
    {
        return $this->db->table('carease_users')
            ->where('id', (int) $id)
            ->get();
    }

    public function updateUser($id, $data)
    {
        return $this->db->table('carease_users')
            ->where('id', (int) $id)
            ->update($data);
    }

    public function usersWithValidId()
    {
        return $this->db->table('carease_users')
            ->where('id_status', '!=', 'none')
            ->get_all();
    }

    public function cars()
    {
        return $this->db->table('carease_cars')->get_all();
    }

    public function car($id)
    {
        return $this->db->table('carease_cars')
            ->where('id', (int) $id)
            ->get();
    }

    public function bookings()
    {
        return $this->db->table('carease_bookings')->get_all();
    }

    public function booking($id)
    {
        return $this->db->table('carease_bookings')
            ->where('id', (int) $id)
            ->get();
    }

    public function addCar($d)
    {
        return $this->db->table('carease_cars')->insert($d);
    }

    public function updateCar($id, $d)
    {
        return $this->db->table('carease_cars')
            ->where('id', (int) $id)
            ->update($d);
    }

    public function deleteCar($id)
    {
        return $this->db->table('carease_cars')
            ->where('id', (int) $id)
            ->delete();
    }

    // Use the same database connection for booking and contract writes.
    public function beginBookingTransaction()
    {
        return $this->db->transaction();
    }

    public function commitBookingTransaction()
    {
        return $this->db->commit();
    }

    public function rollbackBookingTransaction()
    {
        return $this->db->roll_back();
    }

    public function addBooking($d)
    {
        return $this->db->table('carease_bookings')->insert($d);
    }

    public function updateBooking($id, $d)
    {
        return $this->db->table('carease_bookings')
            ->where('id', (int) $id)
            ->update($d);
    }

    public function available($carId, $start, $end)
    {
        foreach ($this->bookings() ?: [] as $b) {
            if (
                (int) $b['car_id'] === (int) $carId &&
                in_array(
                    $b['status'],
                    ['pending', 'approved', 'confirmed', 'ongoing', 'overdue'],
                    true
                ) &&
                $b['pickup_date'] < $end &&
                $b['return_date'] > $start
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Reserved date ranges for a car.
     */
    public function blockedRanges($carId)
    {
        $ranges = [];

        foreach ($this->bookings() ?: [] as $b) {
            if ((int) $b['car_id'] !== (int) $carId) {
                continue;
            }

            if (!in_array(
                $b['status'],
                ['pending', 'approved', 'confirmed', 'ongoing', 'overdue'],
                true
            )) {
                continue;
            }

            $ranges[] = [
                'pickup_date' => $b['pickup_date'],
                'return_date' => $b['return_date'],
                'status' => $b['status']
            ];
        }

        return $ranges;
    }

    // CONTRACTS

    public function contract($bookingId)
    {
        return $this->db->table('carease_contracts')
            ->where('booking_id', (int) $bookingId)
            ->get();
    }

    public function contractById($id)
    {
        return $this->db->table('carease_contracts')
            ->where('id', (int) $id)
            ->get();
    }

    public function addContract($data)
    {
        return $this->db->table('carease_contracts')->insert($data);
    }

    public function updateContract($id, $data)
    {
        return $this->db->table('carease_contracts')
            ->where('id', (int) $id)
            ->update($data);
    }

    public function contracts()
    {
        return $this->db->table('carease_contracts')->get_all();
    }

    // OVERDUE BOOKINGS

    public function markOverdueBookings()
    {
        $today = date('Y-m-d');

        foreach ($this->bookings() ?: [] as $booking) {
            if (
                $booking['status'] === 'ongoing' &&
                $booking['return_date'] < $today
            ) {
                $this->updateBooking($booking['id'], [
                    'status' => 'overdue'
                ]);
            }
        }
    }

    // INSPECTIONS

    public function inspections($bookingId)
    {
        return $this->db->table('carease_inspections')
            ->where('booking_id', (int) $bookingId)
            ->get_all();
    }

    public function addInspection($data)
    {
        return $this->db->table('carease_inspections')
            ->insert($data);
    }

    // MAINTENANCE

    public function maintenanceRecords()
    {
        return $this->db->table('carease_maintenance')->get_all();
    }

    public function maintenanceRecord($id)
    {
        return $this->db->table('carease_maintenance')
            ->where('id', (int) $id)
            ->get();
    }

    public function maintenanceForCar($carId)
    {
        return $this->db->table('carease_maintenance')
            ->where('car_id', (int) $carId)
            ->get_all();
    }

    public function addMaintenance($data)
    {
        return $this->db->table('carease_maintenance')->insert($data);
    }

    public function updateMaintenance($id, $data)
    {
        return $this->db->table('carease_maintenance')
            ->where('id', (int) $id)
            ->update($data);
    }

    // PAYMENTS

    public function paymentsForBooking($bookingId)
    {
        return $this->db->table('carease_payments')
            ->where('booking_id', (int) $bookingId)
            ->get_all();
    }

    public function payments()
    {
        return $this->db->table('carease_payments')->get_all();
    }

    public function payment($id)
    {
        return $this->db->table('carease_payments')
            ->where('id', (int) $id)
            ->get();
    }

    public function addPayment($data)
    {
        return $this->db->table('carease_payments')->insert($data);
    }

    public function updatePayment($id, $data)
    {
        return $this->db->table('carease_payments')
            ->where('id', (int) $id)
            ->update($data);
    }

    public function paymentSummary($bookingId)
    {
        $booking = $this->booking($bookingId);

        if (!$booking) {
            return null;
        }

        $total = (int) round(
            (float) $booking['total_amount'] * 100
        );

        $downpayment = (int) round($total * 0.30);
        $paid = 0;

        $payments = $this->paymentsForBooking($bookingId) ?: [];

        foreach ($payments as $payment) {
            if ($payment['status'] === 'paid') {
                $paid += (int) $payment['amount_centavos'];
            }
        }

        $paid = min($paid, $total);
        $remaining = max(0, $total - $paid);

        if ($remaining === 0) {
            $status = 'fully_paid';
        } elseif ($paid >= $downpayment) {
            $status = 'downpayment_paid';
        } elseif ($paid > 0) {
            $status = 'partially_paid';
        } else {
            $status = 'unpaid';
        }

        return [
            'total_centavos' => $total,
            'downpayment_centavos' => $downpayment,
            'paid_centavos' => $paid,
            'remaining_centavos' => $remaining,
            'payment_status' => $status
        ];
    }

    // RETURN VEHICLE REQUESTS

    public function returnRequests()
    {
        return $this->db->table('carease_return_requests')
            ->get_all();
    }

    public function returnRequest($bookingId)
    {
        return $this->db->table('carease_return_requests')
            ->where('booking_id', (int) $bookingId)
            ->get();
    }

    public function addReturnRequest($data)
    {
        return $this->db->table('carease_return_requests')
            ->insert($data);
    }

    public function updateReturnRequest($id, $data)
    {
        return $this->db->table('carease_return_requests')
            ->where('id', (int) $id)
            ->update($data);
    }

    // EMAIL OTP VERIFICATION

public function emailOtp($userId)
{
    return $this->db->table('carease_email_otps')
        ->where('user_id', (int) $userId)
        ->get();
}

public function addEmailOtp($data)
{
    return $this->db->table('carease_email_otps')
        ->insert($data);
}

public function updateEmailOtp($userId, $data)
{
    return $this->db->table('carease_email_otps')
        ->where('user_id', (int) $userId)
        ->update($data);
}

public function deleteEmailOtp($userId)
{
    return $this->db->table('carease_email_otps')
        ->where('user_id', (int) $userId)
        ->delete();
}
}
