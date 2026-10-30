<?php

namespace App\Forum\Controllers;

use Throwable;

use DOMDocument;
use DOMElement;
use DOMText;
use DOMXPath;

use App\Classes\Database;
use App\Classes\UrlManager;

/**
 * Detects, links, and notifies @username mentions.
 */
class MentionController
{
    /**
     * Mention handles start with a letter or number and may contain letters,
     * numbers, underscores, dots, and hyphens.
     */
    private const PATTERN = '/(?<![\p{L}\p{N}_.@-])@([\p{L}\p{N}][\p{L}\p{N}_.-]{1,31})/u';

    /** @var array<string, array> */
    private static array $accountCache = [];

    /**
     * Extract mention handles from plain text.
     *
     * @param bool $ignoreQuotes Skip quoted lines so quoting a post does not notify
     *                           people who were mentioned in the original.
     *
     * @return string[] Unique handles in their first-seen casing.
     */
    public static function Extract(string $text, bool $ignoreQuotes = false): array
    {
        $text = self::StripMarkdownNoise($text);

        if ($ignoreQuotes)
        {
            $lines = preg_split('/\n/', $text) ?: [];
            $text = implode("\n", array_filter(
                $lines,
                static fn (string $line): bool => !str_starts_with(ltrim($line), '>')
            ));
        }

        if (strpos($text, '@') === false)
            return [];

        if (!preg_match_all(self::PATTERN, $text, $matches))
            return [];

        $handles = [];

        foreach ($matches[1] as $raw)
        {
            $handle = rtrim($raw, '._-');

            if ($handle === '')
                continue;

            $key = mb_strtolower($handle);

            if (!isset($handles[$key]))
                $handles[$key] = $handle;
        }

        return array_values($handles);
    }

    /**
     * Remove code spans, code blocks, and URL targets before looking for
     * mentions, so Markdown syntax cannot create false notifications.
     */
    private static function StripMarkdownNoise(string $text): string
    {
        $text = preg_replace('/^ {0,3}(`{3,}|~{3,})[^\n]*\n.*?(?:^ {0,3}\1\s*$|\z)/ms', '', $text) ?? $text;
        $text = preg_replace('/`[^`\n]*`/', '', $text) ?? $text;
        $text = preg_replace('/!\[[^\]]*\]\([^)\s]*\)/', '', $text) ?? $text;
        $text = preg_replace('/\[([^\]]*)\]\([^)\s]*\)/', '$1', $text) ?? $text;

        return $text;
    }

    /**
     * Turn valid @username mentions in HTML into profile links.
     */
    public static function Link(Database $db, string $html): string
    {
        if ($html === '' || strpos($html, '@') === false)
            return $html;

        if (!class_exists(DOMDocument::class))
            return $html;

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="bb-mention-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded)
            return $html;

        $xpath = new DOMXPath($document);
        $root = $xpath->query('//*[@id="bb-mention-root"]')->item(0);

        if (!$root instanceof DOMElement)
            return $html;

        $textNodes = [];
        $nodes = $xpath->query('.//text()[not(ancestor::a) and not(ancestor::code) and not(ancestor::pre)]', $root);

        foreach ($nodes as $node)
            $textNodes[] = $node;

        foreach ($textNodes as $node)
        {
            if ($node instanceof DOMText)
                self::LinkTextNode($db, $document, $node);
        }

        $output = '';

        foreach ($root->childNodes as $child)
            $output .= $document->saveHTML($child);

        return $output;
    }

    /**
     * Notify every mentioned account in a new thread or reply.
     */
    public static function NotifyMentions(
        Database $db,
        int $actorID,
        string $actorName,
        string $text,
        int $threadID,
        int $postID,
        string $threadTitle,
        string $url
    ): void
    {
        $handles = self::Extract($text, true);

        if (count($handles) <= 0)
            return;

        try
        {
            $placeholders = implode(',', array_fill(0, count($handles), '?'));
            $accounts = $db->Query('SELECT id, username FROM bit_accounts
                WHERE username IN (' . $placeholders . ')', $handles)->FetchAll();

            if (count($accounts) <= 0)
                return;

            $message = $actorName . ' mentioned you in “' . $threadTitle . '”';
            $seen = [];

            foreach ($accounts as $account)
            {
                $userID = (int) $account['id'];

                if ($userID === $actorID || isset($seen[$userID]))
                    continue;

                $seen[$userID] = true;

                NotificationController::Create(
                    $db,
                    $userID,
                    $actorID,
                    'mention',
                    $threadID,
                    $postID,
                    $message,
                    $url
                );
            }
        }
        catch (Throwable $e)
        {
            // Notifications must never make posting fail.
            error_log('BitBoard mention notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Replace mentions inside one text node with profile links.
     */
    private static function LinkTextNode(Database $db, DOMDocument $document, DOMText $node): void
    {
        $text = $node->nodeValue ?? '';

        if ($text === '' || !preg_match_all(self::PATTERN, $text, $matches, PREG_OFFSET_CAPTURE))
            return;

        $parent = $node->parentNode;

        if ($parent === null)
            return;

        $fragments = [];
        $cursor = 0;

        foreach ($matches[1] as $index => $match)
        {
            $handle = rtrim($match[0], '._-');

            if ($handle === '')
                continue;

            $account = self::FindAccount($db, $handle);

            if (count($account) <= 0)
                continue;

            $start = $matches[0][$index][1];

            if ($start < $cursor)
                continue;

            $before = substr($text, $cursor, $start - $cursor);

            if ($before !== '')
                $fragments[] = $document->createTextNode($before);

            $anchor = $document->createElement('a', '@' . $account['username']);
            $anchor->setAttribute('href', UrlManager::GetPath() . 'profile/' . (int) $account['id']);
            $anchor->setAttribute('class', 'mention');
            $fragments[] = $anchor;

            $cursor = $start + 1 + strlen($handle);
        }

        if (count($fragments) <= 0)
            return;

        $tail = substr($text, $cursor);

        if ($tail !== '')
            $fragments[] = $document->createTextNode($tail);

        foreach ($fragments as $fragment)
            $parent->insertBefore($fragment, $node);

        $parent->removeChild($node);
    }

    /**
     * Find an account once per request.
     */
    private static function FindAccount(Database $db, string $username): array
    {
        $key = mb_strtolower($username);

        if (array_key_exists($key, self::$accountCache))
            return self::$accountCache[$key];

        $account = $db->Query('SELECT id, username FROM bit_accounts
            WHERE username = ? LIMIT 1', $username)->FetchArray();

        self::$accountCache[$key] = empty($account) ? [] : $account;

        return self::$accountCache[$key];
    }
}

?>
