<?php

namespace App\Forum\Controllers;

use finfo;

use App\Classes\AttachmentException;
use App\Classes\Database;

/**
 * Validation, private storage, database records, and delivery for post attachments.
 */
class AttachmentController
{
    public const DEFAULT_MAX_SIZE_KB = 5120;
    public const DEFAULT_MAX_FILES = 5;
    public const MAX_SIZE_LIMIT_KB = 20480;
    public const MAX_FILES_LIMIT = 20;

    public const SUPPORTED_FORMATS = [
        'jpg' => 'JPEG',
        'png' => 'PNG',
        'gif' => 'GIF',
        'webp' => 'WebP',
        'pdf' => 'PDF',
        'txt' => 'Text',
        'zip' => 'ZIP',
    ];

    public const DEFAULT_FORMATS = ['jpg', 'png', 'gif', 'webp', 'pdf', 'txt', 'zip'];

    public const TYPES = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
    ];

    private const IMAGE_TYPES = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public static function CreateTable(Database $db): void
    {
        $db->Query('CREATE TABLE IF NOT EXISTS bit_attachments (
            id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT(6) UNSIGNED NOT NULL,
            thread_id INT(6) UNSIGNED NOT NULL DEFAULT 0,
            post_id INT(6) UNSIGNED NOT NULL DEFAULT 0,
            original_name VARCHAR(255) NOT NULL,
            stored_path VARCHAR(255) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            extension VARCHAR(10) NOT NULL,
            size_bytes INT(10) UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY attachments_thread (thread_id),
            KEY attachments_post (post_id),
            KEY attachments_user (user_id),
            CONSTRAINT fk_attachments_user FOREIGN KEY (user_id) REFERENCES bit_accounts (id) ON DELETE CASCADE
        )');
    }

    /**
     * Resolve the attachment limits and formats from forum settings.
     *
     * @return array{enabled:bool,max_size:int,max_size_kb:int,max_files:int,formats:array<int,string>}
     */
    public static function Config(object $forumData): array
    {
        $maxSizeKb = max(1, min(self::MAX_SIZE_LIMIT_KB, (int) ($forumData->attachment_max_size ?? self::DEFAULT_MAX_SIZE_KB)));
        $maxFiles = max(1, min(self::MAX_FILES_LIMIT, (int) ($forumData->attachment_max_files ?? self::DEFAULT_MAX_FILES)));
        $formats = self::ParseFormats((string) ($forumData->attachment_formats ?? ''));

        return [
            'enabled' => (bool) ($forumData->attachment_uploads ?? true),
            'max_size' => $maxSizeKb * 1024,
            'max_size_kb' => $maxSizeKb,
            'max_files' => $maxFiles,
            'formats' => $formats,
        ];
    }

    /**
     * @return array<int, string> Canonical supported extensions.
     */
    public static function ParseFormats(string $formats): array
    {
        $parts = preg_split('/[\s,]+/', strtolower($formats)) ?: [];
        $valid = [];

        foreach ($parts as $part)
        {
            $extension = $part === 'jpeg' ? 'jpg' : $part;

            if (isset(self::SUPPORTED_FORMATS[$extension]))
                $valid[$extension] = true;
        }

        return count($valid) > 0 ? array_keys($valid) : self::DEFAULT_FORMATS;
    }

    /**
     * @param array<int, mixed>|string $formats
     */
    public static function NormalizeFormats(array|string $formats): string
    {
        if (is_array($formats))
            $formats = implode(',', array_map(static fn($format): string => is_string($format) ? $format : '', $formats));

        return implode(',', self::ParseFormats($formats));
    }

    /**
     * @param array<int, string> $formats
     */
    public static function FormatLabel(array $formats): string
    {
        return implode(', ', array_map(
            static fn(string $extension): string => self::SUPPORTED_FORMATS[$extension] ?? strtoupper($extension),
            $formats
        ));
    }

    public static function MaxSizeLabel(int $bytes): string
    {
        return self::FormatSize($bytes);
    }

    /**
     * Normalize the multi-file PHP upload array and validate every file.
     *
     * @param array{enabled:bool,max_size:int,max_size_kb:int,max_files:int,formats:array<int,string>} $config
     * @return array<int, array{tmp_name:string,name:string,mime:string,extension:string,size:int}>
     */
    public static function ValidateUploads(?array $input, array $config): array
    {
        $files = self::NormalizeFiles($input);

        if (count($files) === 0)
            return [];

        if (!$config['enabled'])
            throw new AttachmentException('Attachments are disabled.');

        if (count($files) > $config['max_files'])
            throw new AttachmentException('You can attach up to ' . $config['max_files'] . ' files at once.');

        $validated = [];

        foreach ($files as $file)
        {
            if ($file['error'] !== UPLOAD_ERR_OK)
                throw new AttachmentException(self::UploadErrorMessage($file['error']));

            if ($file['size'] <= 0 || $file['size'] > $config['max_size'])
                throw new AttachmentException('Each attachment must be ' . self::MaxSizeLabel($config['max_size']) . ' or smaller.');

            $name = self::CleanFileName((string) $file['name']);
            $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
            $canonical = $extension === 'jpeg' ? 'jpg' : $extension;

            if (!array_key_exists($extension, self::TYPES))
                throw new AttachmentException('That attachment type is not allowed.');

            if (!in_array($canonical, $config['formats'], true))
                throw new AttachmentException('That attachment type is not allowed.');

            $mime = self::DetectMime((string) $file['tmp_name']);

            if (!in_array($mime, self::TYPES[$extension], true))
                throw new AttachmentException('The file contents do not match the "' . $extension . '" extension.');

            if (in_array($extension, self::IMAGE_TYPES, true) && @getimagesize((string) $file['tmp_name']) === false)
                throw new AttachmentException('That image could not be read.');

            $validated[] = [
                'tmp_name' => (string) $file['tmp_name'],
                'name' => $name,
                'mime' => $mime,
                'extension' => $extension,
                'size' => (int) $file['size'],
            ];
        }

        return $validated;
    }

    /**
     * Move validated uploads into private storage and create their records.
     *
     * @param array<int, array{tmp_name:string,name:string,mime:string,extension:string,size:int}> $files
     */
    public static function StoreUploads(
        Database $db,
        array $files,
        int $userID,
        int $threadID,
        int $postID
    ): void
    {
        if (count($files) === 0)
            return;

        $root = self::StorageRoot();
        $stored = [];
        $insertedIDs = [];

        try
        {
            foreach ($files as $file)
            {
                $relativeDirectory = date('Y/m');
                $directory = $root . '/' . $relativeDirectory;

                if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory))
                    throw new AttachmentException('The attachment storage directory could not be created.');

                $storedName = bin2hex(random_bytes(16)) . '.' . $file['extension'];
                $relativePath = $relativeDirectory . '/' . $storedName;
                $fullPath = $root . '/' . $relativePath;

                if (!move_uploaded_file($file['tmp_name'], $fullPath))
                    throw new AttachmentException('The attachment could not be saved.');

                $stored[] = $fullPath;

                $db->Query('INSERT INTO bit_attachments
                    (user_id, thread_id, post_id, original_name, stored_path, mime_type, extension, size_bytes)
                    VALUES (?,?,?,?,?,?,?,?)',
                    $userID,
                    $threadID,
                    $postID,
                    $file['name'],
                    $relativePath,
                    $file['mime'],
                    $file['extension'],
                    $file['size']
                );

                $insertedIDs[] = (int) $db->LastInsertID();
            }
        }
        catch (\Throwable $e)
        {
            foreach ($stored as $path)
                @unlink($path);

            if (count($insertedIDs) > 0)
            {
                $placeholders = implode(',', array_fill(0, count($insertedIDs), '?'));
                $db->Query('DELETE FROM bit_attachments WHERE id IN (' . $placeholders . ')', $insertedIDs);
            }

            throw $e;
        }
    }

    public static function GetForThread(Database $db, int $threadID): array
    {
        return $db->Query('SELECT * FROM bit_attachments
            WHERE thread_id = ?
            ORDER BY id ASC', $threadID)->FetchAll();
    }

    public static function GetForContent(Database $db, int $threadID, int $postID): array
    {
        return $db->Query('SELECT * FROM bit_attachments
            WHERE thread_id = ? AND post_id = ?
            ORDER BY id ASC', $threadID, $postID)->FetchAll();
    }

    public static function GetByID(Database $db, int $attachmentID): array
    {
        return $db->Query('SELECT * FROM bit_attachments WHERE id = ? LIMIT 1', $attachmentID)->FetchArray();
    }

    /**
     * Delete selected attachments that belong to a specific content row.
     *
     * @param array<int, int> $attachmentIDs
     */
    public static function DeleteForContent(Database $db, array $attachmentIDs, int $threadID, int $postID): void
    {
        $attachmentIDs = array_values(array_unique(array_filter(
            array_map('intval', $attachmentIDs),
            static fn(int $id): bool => $id > 0
        )));

        if (count($attachmentIDs) === 0)
            return;

        $placeholders = implode(',', array_fill(0, count($attachmentIDs), '?'));
        $parameters = $attachmentIDs;
        $parameters[] = $threadID;
        $parameters[] = $postID;

        $rows = $db->Query('SELECT * FROM bit_attachments
            WHERE id IN (' . $placeholders . ') AND thread_id = ? AND post_id = ?',
            $parameters
        )->FetchAll();

        if (count($rows) === 0)
            return;

        $db->Query('DELETE FROM bit_attachments
            WHERE id IN (' . $placeholders . ') AND thread_id = ? AND post_id = ?',
            $parameters
        );

        self::DeleteFiles($rows);
    }

    public static function DeleteForPost(Database $db, int $postID): void
    {
        $rows = $db->Query('SELECT * FROM bit_attachments WHERE post_id = ?', $postID)->FetchAll();

        if (count($rows) === 0)
            return;

        $db->Query('DELETE FROM bit_attachments WHERE post_id = ?', $postID);
        self::DeleteFiles($rows);
    }

    public static function DeleteForThread(Database $db, int $threadID): void
    {
        $rows = $db->Query('SELECT * FROM bit_attachments WHERE thread_id = ?', $threadID)->FetchAll();

        if (count($rows) === 0)
            return;

        $db->Query('DELETE FROM bit_attachments WHERE thread_id = ?', $threadID);
        self::DeleteFiles($rows);
    }

    /**
     * Stream a stored attachment after the page has checked forum access.
     */
    public static function Send(array $attachment): bool
    {
        $path = self::StoragePath($attachment);

        if ($path === null)
            return false;

        $mime = in_array($attachment['mime_type'], array_merge(...array_values(self::TYPES)), true)
            ? $attachment['mime_type']
            : 'application/octet-stream';

        $name = self::CleanFileName((string) $attachment['original_name']);
        $fallback = preg_replace('/[^\x20-\x7E]/', '_', $name) ?: 'attachment';
        $disposition = in_array(strtolower((string) $attachment['extension']), self::IMAGE_TYPES, true)
            ? 'inline'
            : 'attachment';

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . $disposition . '; filename="' . addcslashes($fallback, '"\\') . '"; filename*=UTF-8\'\'' . rawurlencode($name));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=600');

        readfile($path);
        exit;
    }

    public static function FormatSize(int $bytes): string
    {
        if ($bytes >= 1048576)
            return number_format($bytes / 1048576, 1) . ' MB';

        if ($bytes >= 1024)
            return (string) max(1, (int) ceil($bytes / 1024)) . ' KB';

        return $bytes . ' B';
    }

    public static function Icon(array $attachment): string
    {
        $extension = strtolower((string) $attachment['extension']);

        return match ($extension)
        {
            'jpg', 'jpeg', 'png', 'gif', 'webp' => 'photo',
            'pdf' => 'file-type-pdf',
            'zip' => 'file-zip',
            'txt' => 'file-text',
            default => 'paperclip',
        };
    }

    private static function NormalizeFiles(?array $input): array
    {
        if (!is_array($input) || !isset($input['name']))
            return [];

        $names = is_array($input['name']) ? $input['name'] : [$input['name']];
        $types = is_array($input['type'] ?? null) ? $input['type'] : [$input['type'] ?? ''];
        $tmpNames = is_array($input['tmp_name'] ?? null) ? $input['tmp_name'] : [$input['tmp_name'] ?? ''];
        $errors = is_array($input['error'] ?? null) ? $input['error'] : [$input['error'] ?? UPLOAD_ERR_NO_FILE];
        $sizes = is_array($input['size'] ?? null) ? $input['size'] : [$input['size'] ?? 0];

        $files = [];

        foreach ($names as $index => $name)
        {
            $error = (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE);

            if ($error === UPLOAD_ERR_NO_FILE)
                continue;

            $files[] = [
                'name' => (string) $name,
                'type' => (string) ($types[$index] ?? ''),
                'tmp_name' => (string) ($tmpNames[$index] ?? ''),
                'error' => $error,
                'size' => (int) ($sizes[$index] ?? 0),
            ];
        }

        return $files;
    }

    private static function DetectMime(string $path): string
    {
        if (class_exists(finfo::class))
        {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($path);
        }
        else
        {
            $mime = function_exists('mime_content_type') ? mime_content_type($path) : '';
        }

        if (!is_string($mime))
            return '';

        return strtolower(trim(explode(';', $mime)[0]));
    }

    private static function CleanFileName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = trim($name);

        if ($name === '' || $name === '.' || $name === '..')
            $name = 'attachment';

        return mb_substr($name, 0, 255);
    }

    private static function UploadErrorMessage(int $error): string
    {
        return match ($error)
        {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That attachment is too large.',
            UPLOAD_ERR_PARTIAL => 'The upload did not finish. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'The server could not store that attachment.',
            default => 'The attachment could not be uploaded.',
        };
    }

    private static function StorageRoot(): string
    {
        return dirname(__DIR__, 2) . '/storage/attachments';
    }

    private static function StoragePath(array $attachment): ?string
    {
        $relative = str_replace('\\', '/', (string) ($attachment['stored_path'] ?? ''));

        if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '/'))
            return null;

        $root = realpath(self::StorageRoot());
        $path = realpath(self::StorageRoot() . '/' . $relative);

        if ($root === false || $path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR))
            return null;

        return $path;
    }

    private static function DeleteFiles(array $rows): void
    {
        foreach ($rows as $row)
        {
            $path = self::StoragePath($row);

            if ($path !== null)
                @unlink($path);
        }
    }
}
