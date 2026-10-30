<?php

namespace App\Classes;

/**
 * Small, dependency-free Markdown renderer for forum posts.
 *
 * Policy: input is Markdown, raw HTML is always escaped, and the result is
 * passed through HtmlSanitizer before it is stored or previewed.
 */
class Markdown
{
    private const MAX_QUOTE_DEPTH = 6;

    /**
     * Render Markdown source to HTML.
     */
    public static function ToHtml(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\t"], ["\n", "\n", "    "], $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text) ?? '';
        $text = trim($text);

        if ($text === '')
            return '';

        $codeBlocks = [];
        $text = self::ExtractFencedCode($text, $codeBlocks);

        return self::ParseBlocks($text, $codeBlocks, 0);
    }

    /**
     * Replace fenced code blocks with placeholders so inline parsing cannot
     * touch their contents.
     *
     * @param array<int, string> $blocks Rendered code blocks, filled by reference.
     */
    private static function ExtractFencedCode(string $text, array &$blocks): string
    {
        $lines = explode("\n", $text);
        $output = [];
        $inBlock = false;
        $language = '';
        $buffer = [];

        foreach ($lines as $line)
        {
            if (!$inBlock)
            {
                if (preg_match('/^ {0,3}(`{3,}|~{3,})\s*([A-Za-z0-9_-]*)\s*$/', $line, $match) === 1)
                {
                    $inBlock = true;
                    $language = strtolower($match[2]);
                    $buffer = [];
                    continue;
                }

                $output[] = $line;
                continue;
            }

            if (preg_match('/^ {0,3}(`{3,}|~{3,})\s*$/', $line) === 1)
            {
                $blocks[] = self::CodeBlock($buffer, $language);
                $output[] = "\x1A" . (count($blocks) - 1) . "\x1A";
                $inBlock = false;
                $language = '';
                $buffer = [];
                continue;
            }

            $buffer[] = $line;
        }

        if ($inBlock)
        {
            $blocks[] = self::CodeBlock($buffer, $language);
            $output[] = "\x1A" . (count($blocks) - 1) . "\x1A";
        }

        return implode("\n", $output);
    }

    /**
     * @param string[] $lines
     */
    private static function CodeBlock(array $lines, string $language): string
    {
        $code = htmlspecialchars(implode("\n", $lines), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $language = preg_replace('/[^a-z0-9_-]/', '', $language) ?? '';
        $class = $language !== '' ? ' class="language-' . $language . '"' : '';

        return '<pre><code' . $class . '>' . $code . '</code></pre>';
    }

    /**
     * @param array<int, string> $codeBlocks
     */
    private static function ParseBlocks(string $text, array $codeBlocks, int $depth): string
    {
        $lines = explode("\n", $text);
        $count = count($lines);
        $html = '';
        $i = 0;

        while ($i < $count)
        {
            $line = $lines[$i];
            $trimmed = trim($line);

            if ($trimmed === '')
            {
                $i++;
                continue;
            }

            if (preg_match('/^\x1A(\d+)\x1A$/', $trimmed, $match) === 1)
            {
                $html .= $codeBlocks[(int) $match[1]] ?? '';
                $i++;
                continue;
            }

            if (self::IsHorizontalRule($trimmed))
            {
                $html .= '<hr>';
                $i++;
                continue;
            }

            if (preg_match('/^ {0,3}(#{1,6})\s+(.*?)\s*#*\s*$/u', $line, $match) === 1)
            {
                $level = strlen($match[1]);
                $html .= '<h' . $level . '>' . self::Inline($match[2]) . '</h' . $level . '>';
                $i++;
                continue;
            }

            if (preg_match('/^ {0,3}>/', $line) === 1)
            {
                $quote = [];

                while ($i < $count && preg_match('/^ {0,3}>/', $lines[$i]) === 1)
                {
                    $quote[] = preg_replace('/^ {0,3}>\s?/', '', $lines[$i]) ?? '';
                    $i++;
                }

                $html .= self::RenderQuote($quote, $codeBlocks, $depth);
                continue;
            }

            if (self::IsListItem($line))
            {
                $ordered = preg_match('/^ {0,3}\d+[.)]\s+/', $line) === 1;
                $items = [];

                while ($i < $count)
                {
                    if (self::IsListItem($lines[$i]))
                    {
                        $items[] = preg_replace('/^ {0,3}(?:[-*+]|\d+[.)])\s+/', '', $lines[$i]) ?? '';
                        $i++;
                        continue;
                    }

                    if (trim($lines[$i]) === '')
                        break;

                    if (preg_match('/^ {2,}\S/', $lines[$i]) === 1 && count($items) > 0)
                    {
                        $items[count($items) - 1] .= "\n" . trim($lines[$i]);
                        $i++;
                        continue;
                    }

                    break;
                }

                $html .= self::RenderList($items, $ordered);
                continue;
            }

            $paragraph = [];

            while ($i < $count)
            {
                $current = $lines[$i];

                if (trim($current) === '')
                    break;

                if (count($paragraph) > 0 && self::StartsBlock($current))
                    break;

                $paragraph[] = $current;
                $i++;
            }

            $html .= '<p>' . self::InlineLines($paragraph) . '</p>';
        }

        return $html;
    }

    /**
     * @param string[] $lines
     * @param array<int, string> $codeBlocks
     */
    private static function RenderQuote(array $lines, array $codeBlocks, int $depth): string
    {
        $author = '';

        if (count($lines) > 0 && preg_match('/^(.{1,64}) wrote:$/u', trim($lines[0])) === 1)
        {
            $author = trim(array_shift($lines));
            $author = htmlspecialchars($author, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        if ($depth >= self::MAX_QUOTE_DEPTH)
        {
            $body = self::InlineLines($lines);
            return '<blockquote><p>' . ($author !== '' ? '<strong>' . $author . '</strong><br>' : '') . $body . '</p></blockquote>';
        }

        $innerText = self::ExtractFencedCode(implode("\n", $lines), $codeBlocks);
        $inner = self::ParseBlocks($innerText, $codeBlocks, $depth + 1);

        if ($author !== '')
        {
            if ($inner === '')
                return '<blockquote><p><strong>' . $author . '</strong></p></blockquote>';

            if (str_starts_with($inner, '<p>'))
                $inner = '<p><strong>' . $author . '</strong><br>' . substr($inner, 3);
            else
                $inner = '<p><strong>' . $author . '</strong></p>' . $inner;

            return '<blockquote>' . $inner . '</blockquote>';
        }

        return '<blockquote>' . $inner . '</blockquote>';
    }

    /**
     * @param string[] $items
     */
    private static function RenderList(array $items, bool $ordered): string
    {
        $tag = $ordered ? 'ol' : 'ul';
        $html = '<' . $tag . '>';

        foreach ($items as $item)
        {
            $lines = explode("\n", $item);
            $html .= '<li>' . self::InlineLines($lines) . '</li>';
        }

        return $html . '</' . $tag . '>';
    }

    private static function IsListItem(string $line): bool
    {
        return preg_match('/^ {0,3}(?:[-*+]|\d+[.)])\s+\S/u', $line) === 1;
    }

    private static function IsHorizontalRule(string $line): bool
    {
        return preg_match('/^ {0,3}(?:(?:\*\s*){3,}|(?:-\s*){3,}|(?:_\s*){3,})$/', $line) === 1;
    }

    private static function StartsBlock(string $line): bool
    {
        return self::IsListItem($line)
            || self::IsHorizontalRule(trim($line))
            || preg_match('/^ {0,3}#{1,6}\s+/', $line) === 1
            || preg_match('/^ {0,3}>/', $line) === 1
            || preg_match('/^\x1A\d+\x1A$/', trim($line)) === 1;
    }

    /**
     * @param string[] $lines
     */
    private static function InlineLines(array $lines): string
    {
        $parts = [];

        foreach ($lines as $line)
            $parts[] = self::Inline($line);

        return implode('<br>', $parts);
    }

    /**
     * Convert inline Markdown to HTML. Input is escaped first, so raw HTML is
     * always displayed as text.
     */
    private static function Inline(string $text): string
    {
        if ($text === '')
            return '';

        $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $codeSpans = [];
        $protected = [];

        $text = preg_replace_callback('/`([^`\n]+)`/', static function (array $match) use (&$codeSpans): string
        {
            $codeSpans[] = '<code>' . $match[1] . '</code>';
            return "\x1B" . (count($codeSpans) - 1) . "\x1B";
        }, $text) ?? $text;

        $text = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)\)/', static function (array $match) use (&$protected): string
        {
            $url = self::SafeUrl($match[2], true);

            if ($url === null)
                return $match[0];

            $alt = $match[1];

            $protected[] = '<img src="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '" alt="' . htmlspecialchars($alt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';

            return "\x1C" . (count($protected) - 1) . "\x1C";
        }, $text) ?? $text;

        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', static function (array $match) use (&$protected): string
        {
            $url = self::SafeUrl($match[2], false);

            if ($url === null)
                return $match[0];

            $protected[] = '<a href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
                . $match[1] . '</a>';

            return "\x1C" . (count($protected) - 1) . "\x1C";
        }, $text) ?? $text;

        $text = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/__(.+?)__/u', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/~~(.+?)~~/u', '<del>$1</del>', $text) ?? $text;
        $text = preg_replace('/(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?![*\w])/u', '<em>$1</em>', $text) ?? $text;
        $text = preg_replace('/(?<![_\w])_(?!\s)(.+?)(?<!\s)_(?![_\w])/u', '<em>$1</em>', $text) ?? $text;

        foreach ($protected as $index => $tag)
            $text = str_replace("\x1C" . $index . "\x1C", $tag, $text);

        foreach ($codeSpans as $index => $span)
            $text = str_replace("\x1B" . $index . "\x1B", $span, $text);

        return $text;
    }

    /**
     * Allow relative URLs and http, https, or mailto links.
     */
    private static function SafeUrl(string $url, bool $image): ?string
    {
        $url = trim($url);

        if ($url === '')
            return null;

        $normalized = preg_replace('/[\x00-\x20]+/', '', $url);

        if ($normalized === null || $normalized === '')
            return null;

        if (str_starts_with($normalized, '#') || str_starts_with($normalized, '/'))
            return $url;

        $scheme = parse_url($normalized, PHP_URL_SCHEME);

        if ($scheme === null)
            return $url;

        $scheme = strtolower($scheme);

        if ($image)
            return in_array($scheme, ['http', 'https'], true) ? $url : null;

        return in_array($scheme, ['http', 'https', 'mailto'], true) ? $url : null;
    }
}
