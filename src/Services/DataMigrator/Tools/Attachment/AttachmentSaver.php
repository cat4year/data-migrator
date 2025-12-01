<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Tools\Attachment;

interface AttachmentSaver
{
    public function collectForMigration(array $exportData, string $directory, string $name);

    public function upAttachments(array $data, string $attachmentsPath);
}
