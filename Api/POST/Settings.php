<?php

namespace Api\POST;

use Api\Classes\ApiResponse;
use Api\Classes\ApiCodes;

use App\Classes\Database;

class Settings
{
    private int $code = ApiCodes::OK;
    private string $message = 'Settings updated!';

    public function __construct(Database $db, array $data)
    {
        if(empty($data) || empty($data['forumname']) || empty($data['forumdesc']) || empty($data['forumonline']))
        {
            $this->code = ApiCodes::BAD_REQUEST;
            $this->message = 'Data is missing!';
            return;
        }

        $forumName = $data['forumname'];
        $forumDesc = $data['forumdesc'];
        $forumOnlineMsg = $data['forumonline'];
        $forumOnline = isset($data['online']) ? 1 : 0;
        $forumForceLogin = isset($data['forum_force_login']) ? 1 : 0;

        $db->Query('UPDATE bit_settings SET forum_name = ?, forum_description = ?, forum_online_msg = ?, forum_online = ?, forum_force_login = ? WHERE id = 0', "$forumName", "$forumDesc", "$forumOnlineMsg", $forumOnline, $forumForceLogin);
    }

    public function Response(): void
    {
        ApiResponse::Generate($this->code, ['message' => $this->message]);
    }
}

?>