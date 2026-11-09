<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Sends return reminders and overdue notices, and keeps overdue bookings in
 * sync. Safe to call on every admin/client page load because each notice is
 * recorded on the booking row so it is sent only once.
 */
class CarEaseNotifications
{
    private $model;
    private $mailer = null;
    private $mailerError = '';
    private $dryRun = false;

    public function __construct($model, $dryRun = false)
    {
        $this->model = $model;
        $this->dryRun = (bool) $dryRun;
    }

    public function run()
    {
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $summary = ['checked' => 0, 'reminders' => 0, 'overdue' => 0, 'errors' => []];

        foreach ($this->model->bookings() ?: [] as $booking) {
            $status = (string) ($booking['status'] ?? '');
            if (!in_array($status, ['approved', 'confirmed', 'ongoing', 'overdue'], true)) {
                continue;
            }
            $summary['checked']++;
            $returnDate = (string) ($booking['return_date'] ?? '');
            if ($returnDate === '') {
                continue;
            }

            $car = $this->model->car($booking['car_id']);

            if (
                in_array($status, ['approved', 'confirmed', 'ongoing'], true)
                && $returnDate === $tomorrow
                && empty($booking['return_reminder_sent_at'])
            ) {
                if ($this->send('returnReminder', $booking, $car)) {
                    if (!$this->dryRun) {
                        $this->model->updateBooking($booking['id'], ['return_reminder_sent_at' => date('Y-m-d H:i:s')]);
                    }
                    $summary['reminders']++;
                }
            }

            if ($returnDate < $today) {
                if ($status !== 'overdue') {
                    if (!$this->dryRun) {
                        $this->model->updateBooking($booking['id'], ['status' => 'overdue']);
                    }
                    $status = 'overdue';
                }
                if (empty($booking['overdue_notice_sent_at'])) {
                    if ($this->send('overdueNotice', $booking, $car)) {
                        if (!$this->dryRun) {
                            $this->model->updateBooking($booking['id'], ['overdue_notice_sent_at' => date('Y-m-d H:i:s')]);
                        }
                        $summary['overdue']++;
                    }
                }
            }
        }

        if ($this->mailerError !== '') {
            $summary['errors'][] = $this->mailerError;
        }

        return $summary;
    }

    private function send($method, $booking, $car)
    {
        if ($this->dryRun) {
            return true;
        }
        $mailer = $this->mailer();
        if (!$mailer) {
            return false;
        }
        try {
            return (bool) $mailer->$method($booking, $car);
        } catch (Throwable $e) {
            $this->mailerError = $e->getMessage();
            error_log('CarEase notification (' . $method . '): ' . $e->getMessage());
            return false;
        }
    }

    private function mailer()
    {
        if ($this->mailer !== null) {
            return $this->mailer;
        }
        try {
            require_once __DIR__ . '/../config/carease_email_notification.php';
            $this->mailer = new CarEaseEmailNotification();
        } catch (Throwable $e) {
            $this->mailerError = $e->getMessage();
            error_log('CarEase notification setup: ' . $e->getMessage());
            $this->mailer = false;
        }
        return $this->mailer ?: null;
    }
}
