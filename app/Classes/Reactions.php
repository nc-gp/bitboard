<?php

namespace App\Classes;

/**
 * Available post reaction types.
 */
class Reactions
{
    public const ALL = [
        'heart' => ['icon' => 'ti-heart', 'label' => 'Heart', 'action' => 'React with heart'],
        'sad' => ['icon' => 'ti-mood-sad', 'label' => 'Sad', 'action' => 'React with sad'],
        'funny' => ['icon' => 'ti-mood-tongue', 'label' => 'Funny', 'action' => 'React with funny'],
        'what' => ['icon' => 'ti-mood-confuzed', 'label' => 'What?', 'action' => 'React with what?'],
        'angry' => ['icon' => 'ti-mood-angry', 'label' => 'Angry', 'action' => 'React with angry'],
    ];

    /**
     * Check whether a reaction key is supported.
     */
    public static function IsValid(string $reaction): bool
    {
        return isset(self::ALL[$reaction]);
    }

    /**
     * Normalize an unknown or legacy reaction to a supported type.
     */
    public static function Normalize(?string $reaction): string
    {
        return is_string($reaction) && self::IsValid($reaction) ? $reaction : 'heart';
    }
}

?>
