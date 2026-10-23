<?php

namespace Api\POST;

use Api\Classes\ApiResponse;
use Api\Classes\ApiCodes;

use App\Classes\Database;

class Settings
{
    private int $code = ApiCodes::OK;
    private string $message = 'Settings updated!';
    private array $errors = [];

    public function __construct(Database $db, array $data)
    {
        $forumName = $this->ReadString($data, 'forumname', 2, 64, 'Forum name');
        $forumDesc = $this->ReadString($data, 'forumdesc', 2, 256, 'Forum description');
        $forumOnlineMsg = $this->ReadString($data, 'forumonline', 0, 1024, 'Offline message');

        $forumOnline = $this->ReadBoolean($data, 'online', 'Online flag');
        $forumForceLogin = $this->ReadBoolean($data, 'forum_force_login', 'Force-login flag');

        if(!empty($this->errors))
        {
            $this->code = ApiCodes::BAD_REQUEST;
            $this->message = 'Invalid settings data.';
            return;
        }

        $db->Query(
            'UPDATE bit_settings SET forum_name = ?, forum_description = ?, forum_online_msg = ?, forum_online = ?, forum_force_login = ? WHERE id = 0',
            $forumName,
            $forumDesc,
            $forumOnlineMsg,
            $forumOnline,
            $forumForceLogin
        );
    }

    private function ReadString(array $data, string $key, int $minimum, int $maximum, string $label): ?string
    {
        if(!array_key_exists($key, $data) || !is_string($data[$key]))
        {
            $this->errors[$key] = $label . ' must be a string.';
            return null;
        }

        $value = trim($data[$key]);
        $length = mb_strlen($value);

        if($minimum > 0 && $length < $minimum)
        {
            $this->errors[$key] = $label . ' must be at least ' . $minimum . ' characters long.';
            return null;
        }

        if($length > $maximum)
        {
            $this->errors[$key] = $label . ' cannot be longer than ' . $maximum . ' characters.';
            return null;
        }

        return $value;
    }

    private function ReadBoolean(array $data, string $key, string $label): ?int
    {
        if(!array_key_exists($key, $data))
            return 0;

        $value = $data[$key];

        if(is_bool($value))
            return $value ? 1 : 0;

        if(is_int($value))
        {
            if($value === 1)
                return 1;

            if($value === 0)
                return 0;
        }

        if(is_string($value))
        {
            $value = strtolower(trim($value));

            if(in_array($value, array('1', 'true', 'on', 'yes'), true))
                return 1;

            if(in_array($value, array('0', 'false', 'off', 'no', ''), true))
                return 0;
        }

        $this->errors[$key] = $label . ' must be a boolean value.';
        return null;
    }

    public function Response(): void
    {
        $response = ['message' => $this->message];

        if(!empty($this->errors))
            $response['errors'] = $this->errors;

        ApiResponse::Generate($this->code, $response);
    }
}

?>
