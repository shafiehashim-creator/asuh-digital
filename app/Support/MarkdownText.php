<?php

namespace App\Support;

class MarkdownText
{
    /**
     * Escape user-submitted text before it goes into a Markdown mail, so a
     * public form cannot inject links, images or table breaks into e-mails
     * sent to staff. Blade's {{ }} only escapes HTML, not Markdown.
     */
    public static function escape(?string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\n"], ' ', (string) $text);

        return preg_replace('/([\\\\`*_{}\[\]()<>#+\-.!|~])/', '\\\\$1', $text);
    }
}
