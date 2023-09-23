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
     * @return string The formatted timestamp in the format 'Published Month day, year, at time'.
     */
    public static function Format($timestamp): string
    {
        $datetime = new DateTime($timestamp);
        
        $formattedDate = $datetime->format('F d, Y');
        $formattedTime = $datetime->format('g:i A');
        
        return 'Published ' . $formattedDate . ', at ' . $formattedTime;
    }
}

?>