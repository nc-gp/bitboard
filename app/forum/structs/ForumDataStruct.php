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
     * @var array The array of splitted URL. (/)
     */
    public array $actionParameters;

    /**
     * ForumDataStruct constructor.
     *
     * @param array $forumData An associative array containing forum data.
     */
    public function __construct(array $forumData)
	{
        // Initialize the properties of the object with the provided forum data.
		foreach($forumData as $key => $value)
			$this->{$key} = $value;
	}
}

?>