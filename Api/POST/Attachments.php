<?php

namespace Api\POST;

use Api\Classes\ApiResponse;
use Api\Classes\ApiCodes;

use App\Classes\Database;
use App\Forum\Controllers\AttachmentController;
use App\Forum\Controllers\AuditController;

class Attachments
{
    private int $code = ApiCodes::OK;
    private string $message = 'Attachment settings updated!';
    private array $errors = [];

    public function __construct(Database $db, array $data)
    {
        $enabled = $this->ReadBoolean($data, 'attachment_uploads', 'Attachment uploads flag');
        $maxSize = $this->ReadInteger($data, 'attachment_max_size', 1, AttachmentController::MAX_SIZE_LIMIT_KB, 'Maximum file size');
        $maxFiles = $this->ReadInteger($data, 'attachment_max_files', 1, AttachmentController::MAX_FILES_LIMIT, 'Maximum files per post');
        $formats = $this->ReadFormats($data);

        if (!empty($this->errors))
        {
            $this->code = ApiCodes::BAD_REQUEST;
            $this->message = 'Invalid attachment settings.';
            return;
        }

        $db->Query(
            'UPDATE bit_settings
                SET attachment_uploads = ?, attachment_max_size = ?, attachment_max_files = ?, attachment_formats = ?
                WHERE settings_key = ?',
            $enabled,
            $maxSize,
            $maxFiles,
            $formats,
            'core'
        );

        AuditController::Log(
            $db,
            'settings.attachments',
            sprintf(
                'Updated attachment settings: uploads %s, %d KB limit, %d files maximum, formats: %s.',
                $enabled ? 'enabled' : 'disabled',
                (int) $maxSize,
                (int) $maxFiles,
                $formats
            ),
            'settings',
            0
        );
    }

    private function ReadInteger(array $data, string $key, int $minimum, int $maximum, string $label): ?int
    {
        if (!array_key_exists($key, $data) || !is_numeric($data[$key]))
        {
            $this->errors[$key] = $label . ' must be a number.';
            return null;
        }

        $value = (int) $data[$key];

        if ($value < $minimum || $value > $maximum)
        {
            $this->errors[$key] = $label . ' must be between ' . $minimum . ' and ' . $maximum . '.';
            return null;
        }

        return $value;
    }

    private function ReadBoolean(array $data, string $key, string $label): ?int
    {
        if (!array_key_exists($key, $data))
            return 0;

        $value = $data[$key];

        if (is_bool($value))
            return $value ? 1 : 0;

        if (is_int($value))
            return $value === 1 ? 1 : 0;

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

    private function ReadFormats(array $data): ?string
    {
        $raw = $data['attachment_formats'] ?? null;

        if (is_string($raw))
            $raw = preg_split('/[\s,]+/', $raw) ?: [];
        elseif (!is_array($raw))
            $raw = [];

        $selected = [];

        foreach ($raw as $extension)
        {
            if (!is_string($extension))
                continue;

            $extension = strtolower(trim($extension));

            if ($extension === 'jpeg')
                $extension = 'jpg';

            if (isset(AttachmentController::SUPPORTED_FORMATS[$extension]))
                $selected[$extension] = true;
        }

        if (count($selected) === 0)
        {
            $this->errors['attachment_formats'] = 'Choose at least one attachment format.';
            return null;
        }

        return implode(',', array_keys($selected));
    }

    public function Response(): void
    {
        $response = ['message' => $this->message];

        if (!empty($this->errors))
            $response['errors'] = $this->errors;

        ApiResponse::Generate($this->code, $response);
    }
}
