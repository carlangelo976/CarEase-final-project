<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class CarEaseAvailability extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->model('CarEaseModel');
    }

    public function reservedDates($id)
    {
        // Allow Vue frontend to access this API.
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        $allowedOrigins = [
            'http://127.0.0.1:5174',
            'http://localhost:5174',
            'http://127.0.0.1:5173',
            'http://localhost:5173'
        ];

        if (in_array($origin, $allowedOrigins, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
            header('Vary: Origin');
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        $carId = (int) $id;

        if ($carId <= 0 || !$this->CarEaseModel->car($carId)) {
            http_response_code(404);

            echo json_encode([
                'error' => 'Car not found.'
            ]);

            return;
        }

        try {
            $reservations = [];

            foreach ($this->CarEaseModel->bookings() ?: [] as $booking) {

                if ((int) $booking['car_id'] !== $carId) {
                    continue;
                }

                if (!in_array(
                    strtolower((string) $booking['status']),
                    [
                        'pending',
                        'approved',
                        'confirmed',
                        'ongoing',
                        'overdue'
                    ],
                    true
                )) {
                    continue;
                }

                $reservations[] = [
                    'pickup_date' => $booking['pickup_date'],
                    'return_date' => $booking['return_date']
                ];
            }

            echo json_encode([
                'reservations' => $reservations
            ]);

        } catch (Throwable $e) {

            error_log(
                'CarEase availability: ' . $e->getMessage()
            );

            http_response_code(500);

            echo json_encode([
                'error' => 'Unable to load reserved dates.'
            ]);
        }
    }
}
