<?php

/**
 * Sends the day-before return reminders and the overdue notices.
 *
 * Usage: php lava carease:notify
 */
class CarEaseNotify
{
    public static $command = 'carease:notify';

    public static $description = 'Send CarEase return reminders and overdue notices';

    public static $arguments = [
        '[--dry-run]' => 'Preview reminders and overdue notices without sending emails',
    ];

    public function handle($input = null, array $flags = [])
    {
        $index = PUBLIC_DIR . 'index.php';

        if (!file_exists($index)) {
            echo danger("index.php not found at: {$index}");
            exit(1);
        }

        $args = [
            escapeshellarg(PHP_BINARY),
            escapeshellarg($index),
            escapeshellarg('carease/notifications/run'),
        ];
        if (!empty($flags['dry-run'])) {
            $args[] = '--dry-run';
        }
        $command = implode(' ', $args);

        passthru($command, $exitCode);
        exit((int) $exitCode);
    }
}
