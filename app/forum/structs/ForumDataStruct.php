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
     * @var int The unique identifier for the forum (nothing important).
     */
    public int $id;

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
			$this->{$key} = $value;
	}
}

?>