<?php

namespace Api\POST;

use Api\Classes\ApiResponse;
use Api\Classes\ApiCodes;

use App\Classes\Database;
use App\Forum\Controllers\AuditController;
use App\Forum\Controllers\RegistrationController;

class Registration
{
    private int $code = ApiCodes::OK;
    private string $message = 'Registration settings updated!';
    private array $errors = [];

    public function __construct(Database $db, array $data)
    {
        $terms = $this->ReadString($data, 'registration_terms', 10, 2000, 'Registration terms');
        $minimumDelay = $this->ReadInteger($data, 'registration_min_delay', 0, 60, 'Minimum form delay');
        $ipLimit = $this->ReadInteger($data, 'registration_ip_limit', 1, 100, 'Registration attempt limit');
        $emailMode = $this->ReadMode($data);
        $rawDomains = $this->ReadString($data, 'registration_email_domains', 0, 1000, 'Email domains');
        $domains = RegistrationController::NormalizeDomains($rawDomains ?? '');

        if ($emailMode !== null && $emailMode !== 'off' && $domains === '')
            $this->errors['registration_email_domains'] = 'Add at least one valid domain for this policy.';

        if (!empty($this->errors))
        {
            $this->code = ApiCodes::BAD_REQUEST;
            $this->message = 'Invalid registration settings.';
            return;
        }

        $db->Query(
            'UPDATE bit_settings
                SET registration_terms = ?, registration_min_delay = ?, registration_ip_limit = ?,
                    registration_email_mode = ?, registration_email_domains = ?
                WHERE settings_key = ?',
            $terms,
            $minimumDelay,
            $ipLimit,
            $emailMode,
            $domains,
            'core'
        );

        AuditController::Log(
            $db,
            'settings.registration',
            sprintf(
                'Updated registration settings: %s policy, %d attempts/hour, %ds minimum delay.',
                RegistrationController::ModeLabel((string) $emailMode),
                (int) $ipLimit,
                (int) $minimumDelay
            ),
            'settings',
            0
        );
    }

    private function ReadString(array $data, string $key, int $minimum, int $maximum, string $label): ?string
    {
        if (!array_key_exists($key, $data) || !is_string($data[$key]))
        {
            $this->errors[$key] = $label . ' must be a string.';
            return null;
        }

        $value = trim($data[$key]);
        $length = mb_strlen($value);

        if ($minimum > 0 && $length < $minimum)
        {
            $this->errors[$key] = $label . ' must be at least ' . $minimum . ' characters long.';
            return null;
        }

        if ($length > $maximum)
        {
            $this->errors[$key] = $label . ' cannot be longer than ' . $maximum . ' characters.';
            return null;
        }

        return $value;
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

    private function ReadMode(array $data): ?string
    {
        $mode = $data['registration_email_mode'] ?? null;

        if (!is_string($mode) || !array_key_exists($mode, RegistrationController::EMAIL_MODES))
        {
            $this->errors['registration_email_mode'] = 'Choose a valid email-domain policy.';
            return null;
        }

        return $mode;
    }

    public function Response(): void
    {
        $response = ['message' => $this->message];

        if (!empty($this->errors))
            $response['errors'] = $this->errors;

        ApiResponse::Generate($this->code, $response);
    }
}
