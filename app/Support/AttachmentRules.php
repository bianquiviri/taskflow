<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Number;
use Symfony\Component\Mime\MimeTypes;

/**
 * What a task attachment may look like: the disk it is kept on, the mime types
 * the upload accepts and the size it may reach. The rules live here so the
 * validation of an upload and the hints of the upload form can never drift
 * apart.
 */
final class AttachmentRules
{
    /**
     * The private disk attachments are stored on.
     */
    public const string DISK = 'attachments';

    /**
     * The size limit, in kilobytes, as the validation rule counts it.
     */
    public const int MAX_KILOBYTES = 5120;

    /**
     * The mime types an upload may have, detected from the content of the
     * file rather than from the name it was sent with. Executables, scripts
     * and anything a browser would render as markup (HTML, SVG) are left out
     * on purpose.
     *
     * @var list<string>
     */
    public const array MIMETYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
        'text/plain',
        'text/csv',
        'text/markdown',
        'application/json',
        'application/zip',
        'application/x-zip-compressed',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];

    /**
     * @return list<string>
     */
    public static function mimetypes(): array
    {
        return self::MIMETYPES;
    }

    public static function maxKilobytes(): int
    {
        return self::MAX_KILOBYTES;
    }

    /**
     * The extensions a file picker may offer, derived from the accepted mime
     * types so the two lists stay in step.
     *
     * @return list<string>
     */
    public static function extensions(): array
    {
        $extensions = [];

        foreach (self::MIMETYPES as $mimetype) {
            foreach (MimeTypes::getDefault()->getExtensions($mimetype) as $extension) {
                $extensions['.'.strtolower($extension)] = true;
            }
        }

        return array_keys($extensions);
    }

    /**
     * The size limit as a human label, for the upload form.
     */
    public static function maxSizeLabel(): string
    {
        return Number::fileSize(self::MAX_KILOBYTES * 1024);
    }
}
