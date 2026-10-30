<?php

namespace App\Forum\Controllers;

use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use Throwable;

use App\Classes\Database;
use App\Classes\Permissions;
use App\Classes\SessionManager;
use App\Classes\UrlManager;

/**
 * Theme discovery, installation, activation, preview, and deletion.
 */
class ThemeController
{
    private const THEMES_DIR = __DIR__ . '/../../../themes';
    private const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;
    private const MAX_EXTRACTED_BYTES = 30 * 1024 * 1024;
    private const MAX_FILES = 2000;

    private const ALLOWED_EXTENSIONS = [
        'html', 'htm', 'css', 'js', 'json', 'webp', 'png', 'jpg', 'jpeg',
        'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'map', 'txt', 'md',
    ];

    public static function ThemesDirectory(): string
    {
        return self::THEMES_DIR;
    }

    public static function IsValidSlug(string $slug): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $slug) === 1;
    }

    public static function Exists(string $slug): bool
    {
        return self::IsValidSlug($slug) && is_dir(self::THEMES_DIR . '/' . $slug);
    }

    /**
     * The currently configured theme, or the default when it is missing.
     */
    public static function ActiveTheme(Database $db): string
    {
        $theme = 'default';

        try
        {
            $row = $db->Query("SELECT forum_theme FROM bit_settings WHERE settings_key = 'core'")->FetchArray();
            $theme = (string) ($row['forum_theme'] ?? 'default');
        }
        catch (Throwable $e)
        {
            error_log('BitBoard active theme lookup failed: ' . $e->getMessage());
        }

        return self::Exists($theme) ? $theme : 'default';
    }

    /**
     * Resolve the theme for the current request, including an admin preview.
     */
    public static function ResolveTheme(Database $db, string $active): string
    {
        $active = self::Exists($active) ? $active : 'default';
        $preview = self::PreviewTheme();

        if ($preview !== ''
            && SessionManager::IsLogged()
            && SessionManager::HasPermission(Permissions::ADMIN_PANEL_ACCESS))
            return $preview;

        return $active;
    }

    public static function PreviewTheme(): string
    {
        $preview = $_SESSION['bb_preview_theme'] ?? '';

        return is_string($preview) && self::Exists($preview) ? $preview : '';
    }

    public static function SetPreview(string $slug): bool
    {
        if (!self::Exists($slug))
            return false;

        $_SESSION['bb_preview_theme'] = $slug;
        return true;
    }

    public static function ClearPreview(): void
    {
        unset($_SESSION['bb_preview_theme']);
    }

    /**
     * @return array<int, array{
     *     slug: string, name: string, desc: string, version: string, author: string,
     *     contact: string, active: bool, preview: bool, valid: bool, error: string,
     *     screenshot: string, url: string
     * }>
     */
    public static function ListThemes(Database $db): array
    {
        $active = self::ActiveTheme($db);
        $preview = self::PreviewTheme();
        $themes = [];
        $directories = glob(self::THEMES_DIR . '/*', GLOB_ONLYDIR) ?: [];

        foreach ($directories as $directory)
        {
            $slug = basename($directory);

            if (!self::IsValidSlug($slug))
                continue;

            $metadata = self::Metadata($slug);
            $validation = self::IsValidTheme($slug);

            $themes[] = [
                'slug' => $slug,
                'name' => $metadata['name'],
                'desc' => $metadata['desc'],
                'version' => $metadata['version'],
                'author' => $metadata['author'],
                'contact' => $metadata['contact'],
                'active' => $slug === $active,
                'preview' => $slug === $preview,
                'valid' => $validation['valid'],
                'error' => $validation['error'],
                'screenshot' => self::ScreenshotUrl($slug),
                'url' => self::ThemeUrl($slug),
            ];
        }

        usort($themes, static function (array $a, array $b): int
        {
            if ($a['slug'] === 'default')
                return -1;

            if ($b['slug'] === 'default')
                return 1;

            return strcasecmp($a['name'], $b['name']);
        });

        return $themes;
    }

    /**
     * @return array{name: string, desc: string, version: string, author: string, contact: string}
     */
    public static function Metadata(string $slug): array
    {
        $metadata = [
            'name' => ucfirst(str_replace(['-', '_'], ' ', $slug)),
            'desc' => '',
            'version' => '',
            'author' => '',
            'contact' => '',
        ];

        $file = self::THEMES_DIR . '/' . $slug . '/theme.json';

        if (!is_file($file))
            return $metadata;

        $raw = @file_get_contents($file);

        if ($raw === false)
            return $metadata;

        $data = json_decode($raw, true);

        if (!is_array($data))
            return $metadata;

        foreach ($metadata as $key => $default)
        {
            if (isset($data[$key]) && is_string($data[$key]))
                $metadata[$key] = trim($data[$key]);
        }

        return $metadata;
    }

    /**
     * @return array{valid: bool, error: string}
     */
    public static function IsValidTheme(string $slug): array
    {
        if (!self::Exists($slug))
            return ['valid' => false, 'error' => 'Theme directory not found.'];

        $required = [
            'templates/index/index.html',
            'templates/head/head.html',
            'templates/footer/end.html',
            'styles/global.css',
        ];

        foreach ($required as $file)
        {
            if (!is_file(self::THEMES_DIR . '/' . $slug . '/' . $file))
                return ['valid' => false, 'error' => 'Missing ' . $file . '.'];
        }

        return ['valid' => true, 'error' => ''];
    }

    public static function ThemeUrl(string $slug): string
    {
        return UrlManager::GetPath() . 'themes/' . rawurlencode($slug);
    }

    public static function ScreenshotUrl(string $slug): string
    {
        foreach (['screenshot.webp', 'screenshot.png', 'screenshot.jpg', 'screenshot.jpeg'] as $file)
        {
            if (is_file(self::THEMES_DIR . '/' . $slug . '/' . $file))
                return self::ThemeUrl($slug) . '/' . $file;
        }

        return '';
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public static function Activate(Database $db, string $slug): array
    {
        $validation = self::IsValidTheme($slug);

        if (!$validation['valid'])
            return ['ok' => false, 'message' => 'Unable to activate theme: ' . $validation['error']];

        try
        {
            $db->Query("UPDATE bit_settings SET forum_theme = ? WHERE settings_key = 'core'", $slug);
            self::ClearPreview();
        }
        catch (Throwable $e)
        {
            error_log('BitBoard theme activation failed: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Unable to activate the theme. Please try again.'];
        }

        return ['ok' => true, 'message' => 'Theme activated.'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public static function Delete(Database $db, string $slug): array
    {
        if ($slug === 'default')
            return ['ok' => false, 'message' => 'The default theme cannot be deleted.'];

        if ($slug === self::ActiveTheme($db))
            return ['ok' => false, 'message' => 'The active theme cannot be deleted. Activate another theme first.'];

        if (!self::Exists($slug))
            return ['ok' => false, 'message' => 'Theme not found.'];

        $root = realpath(self::THEMES_DIR);
        $target = realpath(self::THEMES_DIR . '/' . $slug);

        if ($root === false || $target === false || dirname($target) !== $root)
            return ['ok' => false, 'message' => 'Invalid theme path.'];

        if (self::PreviewTheme() === $slug)
            self::ClearPreview();

        try
        {
            self::RemoveDirectory($target);
        }
        catch (Throwable $e)
        {
            error_log('BitBoard theme delete failed: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Unable to delete the theme.'];
        }

        return ['ok' => true, 'message' => 'Theme deleted.'];
    }

    /**
     * Handle a browser upload and install the archive.
     *
     * @param array $file One entry from $_FILES.
     *
     * @return array{ok: bool, message: string}
     */
    public static function Install(array $file): array
    {
        if (!class_exists(PharData::class))
            return ['ok' => false, 'message' => 'ZIP support is not available on this server.'];

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE)
            return ['ok' => false, 'message' => 'Choose a theme ZIP file to install.'];

        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE)
            return ['ok' => false, 'message' => 'The theme ZIP is too large for this server.'];

        if ($error !== UPLOAD_ERR_OK)
            return ['ok' => false, 'message' => 'The upload failed. Please try again.'];

        if ((int) ($file['size'] ?? 0) > self::MAX_UPLOAD_BYTES)
            return ['ok' => false, 'message' => 'The theme ZIP must be 10 MB or smaller.'];

        if (!is_uploaded_file((string) ($file['tmp_name'] ?? '')))
            return ['ok' => false, 'message' => 'Invalid upload.'];

        $name = (string) ($file['name'] ?? '');
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'zip')
            return ['ok' => false, 'message' => 'Only .zip theme archives are supported.'];

        $temporary = tempnam(sys_get_temp_dir(), 'bb-theme-');

        if ($temporary === false)
            return ['ok' => false, 'message' => 'Unable to prepare the upload.'];

        $archivePath = $temporary . '.zip';

        if (!move_uploaded_file((string) $file['tmp_name'], $archivePath))
        {
            @unlink($temporary);
            return ['ok' => false, 'message' => 'Unable to store the upload.'];
        }

        @unlink($temporary);

        $result = self::InstallFromArchive($archivePath);
        @unlink($archivePath);

        return $result;
    }

    /**
     * Install a theme from a local ZIP path. Exposed for CLI verification.
     *
     * @return array{ok: bool, message: string}
     */
    public static function InstallFromArchive(string $archivePath): array
    {
        if (!self::isWritableDirectory(self::THEMES_DIR))
            return ['ok' => false, 'message' => 'The themes directory is not writable.'];

        try
        {
            $archive = new PharData($archivePath);
        }
        catch (Throwable $e)
        {
            return ['ok' => false, 'message' => 'The archive could not be opened.'];
        }

        $prefix = 'phar://' . str_replace('\\', '/', (string) realpath($archivePath)) . '/';
        $files = [];
        $total = 0;

        try
        {
            $iterator = new RecursiveIteratorIterator($archive, RecursiveIteratorIterator::LEAVES_ONLY);

            foreach ($iterator as $file)
            {
                $path = str_replace('\\', '/', $file->getPathname());

                if (!str_starts_with($path, $prefix))
                    continue;

                $name = substr($path, strlen($prefix));

                if ($name === '' || str_ends_with($name, '/'))
                    continue;

                if (!self::IsSafeRelativePath($name))
                    return ['ok' => false, 'message' => 'The archive contains an unsafe path.'];

                $size = (int) $file->getSize();
                $total += $size;

                if ($total > self::MAX_EXTRACTED_BYTES || count($files) >= self::MAX_FILES)
                    return ['ok' => false, 'message' => 'The theme archive is too large.'];

                $files[$name] = $file->getPathname();
            }
        }
        catch (Throwable $e)
        {
            return ['ok' => false, 'message' => 'The archive could not be read.'];
        }

        $manifest = null;
        $top = '';

        if (isset($files['theme.json']))
            $manifest = 'theme.json';
        else
        {
            $candidates = [];

            foreach (array_keys($files) as $name)
            {
                if (preg_match('#^([^/]+)/theme\.json$#', $name, $match) === 1)
                    $candidates[] = $match[1];
            }

            if (count($candidates) === 1)
            {
                $top = $candidates[0];
                $manifest = $top . '/theme.json';
            }
        }

        if ($manifest === null)
            return ['ok' => false, 'message' => 'The ZIP must contain theme.json at its root or inside one top-level folder.'];

        $slug = $top !== '' ? strtolower($top) : '';

        if ($slug === '')
        {
            $metadata = json_decode((string) @file_get_contents($prefix . $manifest), true);
            $slug = self::SlugFromName(is_array($metadata) && isset($metadata['name']) && is_string($metadata['name'])
                ? $metadata['name']
                : pathinfo($archivePath, PATHINFO_FILENAME));
        }

        if (!self::IsValidSlug($slug))
            return ['ok' => false, 'message' => 'The theme folder name must use lowercase letters, numbers, hyphens, or underscores.'];

        if ($slug === 'default' || self::Exists($slug))
            return ['ok' => false, 'message' => 'A theme with that folder name already exists.'];

        $destination = self::THEMES_DIR . '/' . $slug;

        if (!mkdir($destination, 0777, true))
            return ['ok' => false, 'message' => 'Unable to create the theme directory.'];

        foreach ($files as $name => $source)
        {
            if ($top !== '' && !str_starts_with($name, $top . '/'))
                continue;

            $relative = $top !== '' ? substr($name, strlen($top) + 1) : $name;

            if ($relative === '' || !self::IsSafeRelativePath($relative))
            {
                self::RemoveDirectory($destination);
                return ['ok' => false, 'message' => 'The archive contains an unsafe path.'];
            }

            $target = $destination . '/' . $relative;

            if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0777, true))
            {
                self::RemoveDirectory($destination);
                return ['ok' => false, 'message' => 'Unable to create the theme files.'];
            }

            $contents = @file_get_contents($source);

            if ($contents === false || file_put_contents($target, $contents) === false)
            {
                self::RemoveDirectory($destination);
                return ['ok' => false, 'message' => 'Unable to write the theme files.'];
            }
        }

        $validation = self::IsValidTheme($slug);

        if (!$validation['valid'])
        {
            self::RemoveDirectory($destination);
            return ['ok' => false, 'message' => 'Installed theme is incomplete: ' . $validation['error']];
        }

        $metadata = self::Metadata($slug);

        return ['ok' => true, 'message' => 'Installed "' . $metadata['name'] . '".'];
    }

    /**
     * @return string[]
     */
    public static function AllowedExtensions(): array
    {
        return self::ALLOWED_EXTENSIONS;
    }

    private static function IsSafeRelativePath(string $path): bool
    {
        if ($path === '' || strlen($path) > 255 || str_contains($path, "\0") || str_contains($path, '\\') || str_starts_with($path, '/'))
            return false;

        foreach (explode('/', $path) as $segment)
        {
            if ($segment === '' || $segment === '.' || $segment === '..' || str_starts_with($segment, '.'))
                return false;

            if (preg_match('/^[A-Za-z0-9._ -]+$/', $segment) !== 1)
                return false;

            if (preg_match('/\.(?:php\d*|phtml|phar|cgi|pl|py|sh|asp|aspx|jsp|ini|htaccess)$/i', $segment) === 1)
                return false;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, self::ALLOWED_EXTENSIONS, true);
    }

    /**
     * Create a new theme by copying the default theme.
     *
     * @return array{ok: bool, message: string, slug?: string}
     */
    public static function Create(string $name, string $slug, string $author = ''): array
    {
        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 64)
            return ['ok' => false, 'message' => 'Theme name must be between 1 and 64 characters.'];

        $slug = self::SlugFromName($slug !== '' ? $slug : $name);

        if (!self::IsValidSlug($slug))
            return ['ok' => false, 'message' => 'Theme folder must use letters, numbers, hyphens, or underscores.'];

        if ($slug === 'default')
            return ['ok' => false, 'message' => 'The default theme cannot be replaced.'];

        if (self::Exists($slug))
            return ['ok' => false, 'message' => 'A theme with that folder name already exists.'];

        if (!self::isWritableDirectory(self::THEMES_DIR))
            return ['ok' => false, 'message' => 'The themes directory is not writable.'];

        $source = realpath(self::THEMES_DIR . '/default');
        $validation = self::IsValidTheme('default');

        if ($source === false || !$validation['valid'])
            return ['ok' => false, 'message' => 'The default theme is missing or incomplete.'];

        $destination = self::THEMES_DIR . '/' . $slug;

        try
        {
            self::CopyTheme($source, $destination);

            $metadata = [
                'name' => $name,
                'desc' => 'A copy of the default theme.',
                'version' => '1.0',
                'author' => trim($author),
                'contact' => '',
            ];

            $encoded = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if ($encoded === false || file_put_contents($destination . '/theme.json', $encoded . "\n") === false)
                throw new \RuntimeException('Unable to write theme.json.');
        }
        catch (Throwable $e)
        {
            self::RemoveDirectory($destination);
            error_log('BitBoard theme create failed: ' . $e->getMessage());

            return ['ok' => false, 'message' => 'Unable to create the theme.'];
        }

        $validation = self::IsValidTheme($slug);

        if (!$validation['valid'])
        {
            self::RemoveDirectory($destination);
            return ['ok' => false, 'message' => 'Created theme is incomplete: ' . $validation['error']];
        }

        return ['ok' => true, 'message' => 'Theme "' . $name . '" created from the default theme.', 'slug' => $slug];
    }

    private static function CopyTheme(string $source, string $destination): void
    {
        if (!mkdir($destination, 0777, true))
            throw new \RuntimeException('Unable to create the theme directory.');

        $sourceLength = strlen($source) + 1;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item)
        {
            $relative = str_replace('\\', '/', substr($item->getPathname(), $sourceLength));

            // The legacy metadata file is not used; theme.json is authoritative.
            if ($relative === 'theme.php')
                continue;

            $target = $destination . '/' . $relative;

            if ($item->isDir())
            {
                if (!is_dir($target) && !mkdir($target, 0777, true))
                    throw new \RuntimeException('Unable to create theme directory: ' . $relative);
            }
            elseif ($item->isFile())
            {
                if (!copy($item->getPathname(), $target))
                    throw new \RuntimeException('Unable to copy theme file: ' . $relative);
            }
        }
    }

    private static function SlugFromName(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9_-]+/i', '-', $name) ?? '', '-'));
        $slug = preg_replace('/-+/', '-', $slug) ?? '';
        $slug = substr($slug, 0, 64);
        $slug = rtrim($slug, '-');

        return $slug !== '' ? $slug : 'theme';
    }

    private static function isWritableDirectory(string $directory): bool
    {
        return is_dir($directory) && is_writable($directory);
    }

    private static function RemoveDirectory(string $directory): void
    {
        if (!is_dir($directory))
            return;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item)
        {
            if ($item->isLink() || $item->isFile())
                @unlink($item->getPathname());
            else
                @rmdir($item->getPathname());
        }

        @rmdir($directory);
    }
}
