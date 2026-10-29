<?php

namespace Api\POST;

use Api\Classes\ApiResponse;
use Api\Classes\ApiCodes;

use App\Classes\Database;
use App\Forum\Controllers\AuditController;
use App\Forum\Controllers\SettingsController;

class Avatars
{
    private int $code = ApiCodes::OK;
    private string $message = 'Avatar settings updated!';
    private array $errors = [];

    public function __construct(Database $db, array $data)
    {
        $uploads = $this->ReadBoolean($data, 'avatar_uploads', 'Avatar uploads flag');
        $maxSize = $this->ReadInt($data, 'avatar_max_size', 1, 10240, 'Avatar maximum size', 2048);
        $formats = $this->ReadAvatarFormats($data);
        $maxWidth = $this->ReadInt($data, 'avatar_max_width', 0, 10000, 'Avatar maximum width', 0);
        $maxHeight = $this->ReadInt($data, 'avatar_max_height', 0, 10000, 'Avatar maximum height', 0);

        if (!empty($this->errors))
        {
            $this->code = ApiCodes::BAD_REQUEST;
            $this->message = 'Invalid avatar settings.';
            return;
        }

        $db->Query(
            'UPDATE bit_settings
            SET avatar_uploads = ?, avatar_max_size = ?, avatar_formats = ?, avatar_max_width = ?, avatar_max_height = ?
            WHERE id = 0',
            $uploads,
            $maxSize,
            $formats,
            $maxWidth,
            $maxHeight
        );

        AuditController::Log(
            $db,
            'avatars.update',
            sprintf(
                'Updated avatar settings (%s, max %d KB).',
                SettingsController::AvatarFormatLabel((string) $formats),
                $maxSize
            ),
            'settings',
            0
        );
    }

    private function ReadBoolean(array $data, string $key, string $label): ?int
    {
        if (!array_key_exists($key, $data))
            return 0;

        $value = $data[$key];

        if (is_bool($value))
            return $value ? 1 : 0;

        if (is_int($value))
        {
            if ($value === 1)
                return 1;

            if ($value === 0)
                return 0;
        }

        if (is_string($value))
        {
            $value = strtolower(trim($value));

            if (in_array($value, ['1', 'true', 'on', 'yes'], true))
                return 1;

            if (in_array($value, ['0', 'false', 'off', 'no', ''], true))
                return 0;
        }

        $this->errors[$key] = $label . ' must be a boolean value.';
        return null;
    }

    private function ReadInt(
        array $data,
        string $key,
        int $minimum,
        int $maximum,
        string $label,
        int $default
    ): int
    {
        if (!array_key_exists($key, $data) || $data[$key] === '')
            return $default;

        $value = $data[$key];

        if (is_int($value))
            $integer = $value;
        elseif (is_string($value) && preg_match('/^-?\d+$/', trim($value)))
            $integer = (int) trim($value);
        else
        {
            $this->errors[$key] = $label . ' must be a whole number.';
            return $default;
        }

        if ($integer < $minimum || $integer > $maximum)
        {
            $this->errors[$key] = $label . ' must be between ' . $minimum . ' and ' . $maximum . '.';
            return $default;
        }

        return $integer;
    }

    private function ReadAvatarFormats(array $data): ?string
    {
        if (!array_key_exists('avatar_formats', $data))
            return 'jpg,png,webp,gif';

        if (!is_string($data['avatar_formats']))
        {
            $this->errors['avatar_formats'] = 'Allowed avatar formats must be a string.';
            return null;
        }

        $parts = array_filter(array_map('trim', explode(',', strtolower($data['avatar_formats']))));
        $allowed = [];

        foreach ($parts as $part)
        {
            if ($part === 'jpeg')
                $part = 'jpg';

            if (!isset(SettingsController::AVATAR_FORMAT_MIME[$part]))
            {
                $this->errors['avatar_formats'] = 'Allowed avatar formats must use jpg, png, webp, or gif.';
                return null;
            }

            $allowed[] = $part;
        }

        $allowed = array_values(array_unique($allowed));

        if (count($allowed) === 0)
        {
            $this->errors['avatar_formats'] = 'At least one avatar format must be allowed.';
            return null;
        }

        return implode(',', $allowed);
    }

    public function Response(): void
    {
        $response = ['message' => $this->message];

        if (!empty($this->errors))
            $response['errors'] = $this->errors;

        ApiResponse::Generate($this->code, $response);
    }
}
