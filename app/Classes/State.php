<?php

namespace App\Classes;

/**
 * Renders reusable empty and error states from theme templates.
 */
class State
{
    public static function Empty(string $title, string $message, string $icon = 'info-circle', string $class = ''): string
    {
        return self::Render('is-empty', 'status', $title, $message, $icon, $class);
    }

    public static function Error(string $title, string $message, string $icon = 'alert-triangle', string $class = ''): string
    {
        return self::Render('is-error', 'alert', $title, $message, $icon, $class);
    }

    private static function Render(string $type, string $role, string $title, string $message, string $icon, string $class): string
    {
        $template = new Template('state', 'state');
        $template->AddEscapedEntry('{class}', trim($type . ' ' . $class));
        $template->AddEscapedEntry('{role}', $role);
        $template->AddEscapedEntry('{icon}', $icon);
        $template->AddEscapedEntry('{title}', $title);
        $template->AddEscapedEntry('{message}', $message);
        $template->Replace();

        return $template->template;
    }
}
