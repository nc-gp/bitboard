<?php

namespace App\Forum\Widgets;

use App\Classes\Template;
use App\Classes\UrlManager;
use App\Forum\Controllers\AttachmentController;

/**
 * Renders attachment lists and the upload/manage controls.
 */
class AttachmentWidget
{
    public static function Render(array $attachments): string
    {
        if (count($attachments) === 0)
            return '';

        $items = '';

        foreach ($attachments as $attachment)
        {
            $item = new Template('attachment', 'item');
            $item->AddEscapedEntry('{url}', UrlManager::GetPath() . 'attachment/' . (int) $attachment['id']);
            $item->AddEscapedEntry('{name}', $attachment['original_name']);
            $item->AddEscapedEntry('{size}', AttachmentController::FormatSize((int) $attachment['size_bytes']));
            $item->AddEscapedEntry('{icon}', AttachmentController::Icon($attachment));
            $item->Replace();
            $items .= $item->template;
        }

        $list = new Template('attachment', 'list');
        $list->AddEntry('{items}', $items);
        $list->Replace();

        return $list->template;
    }

    public static function Manage(array $attachments): string
    {
        if (count($attachments) === 0)
            return '';

        $items = '';

        foreach ($attachments as $attachment)
        {
            $item = new Template('attachment', 'manage_item');
            $item->AddEscapedEntry('{id}', $attachment['id']);
            $item->AddEscapedEntry('{name}', $attachment['original_name']);
            $item->AddEscapedEntry('{size}', AttachmentController::FormatSize((int) $attachment['size_bytes']));
            $item->Replace();
            $items .= $item->template;
        }

        $list = new Template('attachment', 'manage_list');
        $list->AddEntry('{items}', $items);
        $list->Replace();

        return $list->template;
    }

    public static function UploadField(string $id, array $config): string
    {
        if (empty($config['enabled']))
            return '';

        $acceptExtensions = [];

        foreach ($config['formats'] as $extension)
        {
            $acceptExtensions[] = '.' . $extension;

            if ($extension === 'jpg')
                $acceptExtensions[] = '.jpeg';
        }

        $accept = implode(',', $acceptExtensions);

        $template = new Template('attachment', 'upload_field');
        $template->AddEscapedEntry('{id}', $id);
        $template->AddEscapedEntry('{accept}', $accept);
        $template->AddEscapedEntry('{extensions}', AttachmentController::FormatLabel($config['formats']));
        $template->AddEscapedEntry('{max_files}', $config['max_files']);
        $template->AddEscapedEntry('{max_size}', AttachmentController::MaxSizeLabel($config['max_size']));
        $template->Replace();

        return $template->template;
    }
}
