<?php

namespace App\Forum\Structs;

/**
 * Class ForumDataStruct
 *
 * This class represents the structure of forum data.
 */
class ForumDataStruct
{
    /**
     * @var bool Whether the forum is online or not.
     */
    public bool $forum_online;

    /**
     * @var string The name of the forum.
     */
    public string $forum_name;

    /**
     * @var string The description of the forum.
     */
    public string $forum_description;

    /**
     * @var string The message displayed when the forum is online.
     */
    public string $forum_online_msg;

    /**
     * @var string The theme of the forum.
     */
    public string $forum_theme;

    /**
     * @var bool Force visitors to login before view the forum.
     */
    public bool $forum_force_login;

    /**
     * @var bool Whether to show the BitBoard credit in the footer.
     */
    public bool $forum_credits = true;

    /**
     * @var bool Whether avatar uploads are enabled.
     */
    public bool $avatar_uploads = true;

    /**
     * @var int Maximum avatar file size in kilobytes.
     */
    public int $avatar_max_size = 2048;

    /**
     * @var string Comma-separated avatar file extensions.
     */
    public string $avatar_formats = 'jpg,png,webp,gif';

    /**
     * @var int Maximum avatar width in pixels; 0 means unlimited.
     */
    public int $avatar_max_width = 0;

    /**
     * @var int Maximum avatar height in pixels; 0 means unlimited.
     */
    public int $avatar_max_height = 0;

    /**
     * @var string Configurable admin panel route segment.
     */
    public string $admin_path = 'admin';

    /**
     * @var string Terms text shown on the registration form.
     */
    public string $registration_terms = 'By registering, you agree to follow the forum rules and keep your account secure.';

    /**
     * @var int Minimum seconds between opening and submitting the registration form.
     */
    public int $registration_min_delay = 3;

    /**
     * @var int Maximum registration attempts from one IP per hour.
     */
    public int $registration_ip_limit = 5;

    /**
     * @var bool Whether post attachments are enabled.
     */
    public bool $attachment_uploads = true;

    /**
     * @var int Maximum attachment size in kilobytes.
     */
    public int $attachment_max_size = 5120;

    /**
     * @var int Maximum attachments per post.
     */
    public int $attachment_max_files = 5;

    /**
     * @var string Comma-separated attachment extensions.
     */
    public string $attachment_formats = 'jpg,png,gif,webp,pdf,txt,zip';

    /**
     * @var string Email-domain policy: off, allow, or block.
     */
    public string $registration_email_mode = 'off';

    /**
     * @var string Newline-separated email domains used by the policy.
     */
    public string $registration_email_domains = "gmail.com\noutlook.com\nhotmail.com\nyahoo.com\nicloud.com\nprotonmail.com";

    /**
     * @var array The array of splitted URL. (/)
     * Note: Can be empty.
     */
    public array $actionParameters = [];

    /**
     * ForumDataStruct constructor.
     *
     * @param array $forumData An associative array containing forum data.
     */
    public function __construct(array $forumData)
	{
		foreach($forumData as $key => $value)
		{
			if (property_exists($this, $key))
				$this->{$key} = $value;
		}
	}
}

?>