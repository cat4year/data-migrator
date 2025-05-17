<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator;

use Cat4year\DataMigrator\Services\Configurations\DataMigratorConfiguration;
use Cat4year\DataMigrator\Services\DataMigrator\Export\Exporter;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use RuntimeException;
use function Cat4year\DataMigrator\Helpers\var_pretty_export;

final readonly class Migrator
{
    private MigratorCreator $creator;

    public function __construct()
    {
        $this->creator = app('migration-data.creator');
    }

    /**
     * @throws FileNotFoundException
     * @throws BindingResolutionException
     */
    public function createByConfiguration(
        string $configClass,
        string $name,
        string $path,
        ?string $modelClass = null,
        ?array $ids = null
    ): string {
        if (! class_exists($configClass) || ! (app($configClass) instanceof DataMigratorConfiguration)) {
            throw new RuntimeException('Migration class not found or not instance of Model');
        }

        $configMaker = app($configClass);
        assert($configMaker instanceof DataMigratorConfiguration);
        $configurator = $configMaker->make();

        $configurator->setDirectoryPath($path)
            ->setIds($ids ?? [])
            ->setFileName($name);

        $exporter = Exporter::create(app($modelClass), $configurator);
        $exportData = $exporter->exportData();

        $fullPath = $configurator->makeSourceFullPath();
        $preparedExportData = $configurator->getSourceFormat()->prepareForMigration($exportData); //for xml will need add
        $this->creator->createData(
            $configurator->getFileName(),
            dirname($fullPath),
            $preparedExportData
        );

        return $fullPath;
    }
}
