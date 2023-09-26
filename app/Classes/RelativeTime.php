<?php

namespace App\Classes;

use DateTime;

/**
 * The RelativeTime class provides methods for converting a datetime to a human-readable relative time.
 */
class RelativeTime
{
    /**
     * Convert a datetime to a human-readable relative time.
     *
     * @param mixed $datetime The datetime to convert.
     * @param bool  $full     Set to true to include all time units (years, months, weeks, days, hours, minutes, seconds).
     *
     * @return string The relative time in a human-readable format.
     */
    public static function Convert($datetime, $full = false): string
    {
        $now = new DateTime();
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);
    
        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;
    
        $string = array(
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        );
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }
    
        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }

    /**
     * Format a timestamp into a human-readable string.
     *
     * @param string $timestamp The timestamp in the format 'YYYY-MM-DD HH:MM:SS'.
     * @param bool $full Whether to use full month and day names (default is true).
     * @return string The formatted timestamp in the format 'Published Month day, year, at time'.
     */
    public static function Format($timestamp, $full = true): string
    {
        $datetime = new DateTime($timestamp);

        $dateFormat = $full ? 'F d, Y' : 'M d, Y';
        
        $formattedDate = $datetime->format($dateFormat);
        $formattedTime = $datetime->format('g:i A');
        
        return $formattedDate . ', at ' . $formattedTime;
    }

    /**
     * Check if a user is considered active based on a timestamp.
     *
     * @param string $timestamp The timestamp to compare with the current time.
     *
     * @return bool Returns true if the user is considered active (less than 15 minutes have passed since their last activity), otherwise false.
     */
    public static function IsUserActive(string $timestamp): bool
    {
        $userTime = new DateTime($timestamp);
        $currentTime = new DateTime();
        $timeDifference = $currentTime->getTimestamp() - $userTime->getTimestamp();

        return $timeDifference < 900;
    }
}

?>