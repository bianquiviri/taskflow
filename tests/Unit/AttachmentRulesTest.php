<?php

declare(strict_types=1);

use App\Support\AttachmentRules;

it('accepts the document, image and archive types a task may carry', function () {
    expect(AttachmentRules::mimetypes())->toContain(
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'text/plain',
        'text/csv',
        'application/zip',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    );
});

it('keeps executable, script and markup types out of the allow list', function () {
    expect(AttachmentRules::mimetypes())->not->toContain(
        'application/x-msdownload',
        'application/x-executable',
        'application/x-sh',
        'text/x-php',
        'text/html',
        'image/svg+xml',
    );
});

it('derives the accepted file extensions from the allowed mime types', function () {
    expect(AttachmentRules::extensions())->toContain('.pdf', '.png', '.jpg', '.csv', '.txt', '.zip', '.docx');
});

it('describes the size limit in kilobytes and for the upload form', function () {
    expect(AttachmentRules::maxKilobytes())->toBe(5120)
        ->and(AttachmentRules::maxSizeLabel())->toBe('5 MB');
});

it('keeps the attachment disk private and out of any serving route', function () {
    $disk = config('filesystems.disks.attachments');

    expect($disk)->not->toBeNull()
        ->and($disk['driver'])->toBe('local')
        ->and($disk['root'])->toBe(storage_path('app/attachments'))
        ->and($disk['root'])->not->toStartWith(public_path())
        ->and($disk['visibility'])->toBe('private')
        ->and($disk['serve'])->toBeFalse()
        ->and(AttachmentRules::DISK)->toBe('attachments');
});
