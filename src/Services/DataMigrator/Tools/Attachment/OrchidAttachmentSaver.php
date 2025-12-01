<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Tools\Attachment;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Orchid\Attachment\Models\Attachment;

final readonly class OrchidAttachmentSaver implements AttachmentSaver
{

    public function __construct(private Filesystem $filesystem)
    {
    }

    public function collectForMigration(array $exportData, string $directory, string $name)
    {
        $this->filesystem->ensureDirectoryExists($directory);

        $this->copyFilesAndTransformMigrationData($exportData['attachments']['items'], $directory, $name);
    }

    public function upAttachments(array $data, string $attachmentsPath)
    {
        $this->copyFilesToDiskPathFromMigrationData($data['attachments']['items'], $attachmentsPath);
    }

    private function copyFilesAndTransformMigrationData(array $attachmentsData, string $directory, string $name)
    {
        $attachmentModel = app(Attachment::class);
        foreach ($attachmentsData as $attachment) {
            $filledAttachment = $attachmentModel->forceFill($attachment);
            $storagePathFile = Storage::disk($attachmentModel->disk)->path($filledAttachment->physicalPath());
            $migrationAttachmentFilePath = sprintf('%s/%s/%s', $directory, $name, $filledAttachment->physicalPath());

            if (!File::exists($migrationAttachmentFilePath)) {
                File::ensureDirectoryExists(dirname($migrationAttachmentFilePath), 0755);
                File::copy($storagePathFile, $migrationAttachmentFilePath);
            }
        }
    }

    private function copyFilesToDiskPathFromMigrationData(array $attachments, string $directory): void
    {
        $attachmentModel = app(Attachment::class);
        foreach ($attachments as $attachment) {
            $filledAttachment = $attachmentModel->forceFill($attachment);
            $storagePathFile = Storage::disk($attachmentModel->disk)->path($filledAttachment->physicalPath());
            $migrationAttachmentFilePath = sprintf('%s/%s', $directory, $filledAttachment->physicalPath());

            if (!File::exists($storagePathFile)) {
                File::ensureDirectoryExists(dirname($storagePathFile), 0755);
                File::copy($migrationAttachmentFilePath, $storagePathFile);
            }
        }
    }
}
