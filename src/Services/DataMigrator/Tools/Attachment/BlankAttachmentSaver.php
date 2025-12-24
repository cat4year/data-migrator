<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Tools\Attachment;

final readonly class BlankAttachmentSaver implements AttachmentSaver
{

    public function __construct()
    {
    }

    public function collectForMigration(array $exportData, string $directory, string $name): void
    {
    }

    public function upAttachments(array $data, string $attachmentsPath): void
    {
    }
}
