<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Shared access to the CarEase Google Calendar settings.
 *
 * The OAuth credentials file lives outside public/ so it is never exposed.
 */
class CarEaseGoogleCalendar
{
    /**
     * Loads app/config/carease_oauth_local.php when it exists.
     *
     * @return array|null
     */
    public static function config()
    {
        $path = __DIR__ . '/../config/carease_oauth_local.php';
        if (!is_file($path))
            return null;

        $cfg = require $path;
        return empty($cfg['calendar_id']) ? null : $cfg;
    }

    /**
     * Embeddable Google Calendar (month view) used inside the admin dashboard.
     *
     * @return string|null
     */
    public static function embedUrl()
    {
        $cfg = self::config();
        if (!$cfg)
            return null;

        return 'https://calendar.google.com/calendar/embed?src='
            . rawurlencode($cfg['calendar_id'])
            . '&mode=MONTH&showTitle=0&showPrint=0&showCalendars=0&wkst=1&bgcolor=%23ffffff&timezone=Asia%2FManila';
    }
}
