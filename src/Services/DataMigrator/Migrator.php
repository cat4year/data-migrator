<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator;

use Cat4year\DataMigrator\Services\DataMigrator\Export\ExportConfigurator;
use Cat4year\DataMigrator\Services\DataMigrator\Export\Exporter;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\Attachment\AttachmentSaver;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

final readonly class Migrator
{
    private MigratorCreator $migratorCreator;

    public function __construct()
    {
        $this->migratorCreator = app('migration-data.creator');
    }

    /**
     * @throws FileNotFoundException
     * @throws BindingResolutionException
     */
    public function createByConfiguration(
        ExportConfigurator $exportConfigurator,
        ?string $modelClass = null,
    ): string {
        $exporter = Exporter::create(resolve($modelClass), $exportConfigurator);
        $exportData = $exporter->exportData();

        $fullPath = $exportConfigurator->makeSourceFullPath();
        $preparedExportData = $exportConfigurator->getSourceFormat()->prepareForMigration($exportData); // for xml will need add

        $migrationDirectory = dirname($fullPath);

        $migrationPath = $this->migratorCreator->createData(
            $exportConfigurator->getFileName(),
            $migrationDirectory,
            $preparedExportData
        );

        $nameWithDate = basename($migrationPath, '.php');
        $this->createAttachments($exportConfigurator->getAttachmentSaver(), $exportData, $migrationDirectory, $nameWithDate);

        return $migrationPath;
    }

    private function createAttachments(AttachmentSaver $attachmentSaver, array $exportData, string $directory, string $name): void
    {
        //todo: пока папка attachments статическая и рядом с создаваемым файлом миграции. Можно добавить настройку
        $attachmentSaver->collectForMigration($exportData, $directory . '/attachments', $name);
    }
}
