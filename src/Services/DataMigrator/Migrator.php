<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator;

use Cat4year\DataMigrator\Services\Configurations\DataMigratorConfiguration;
use Cat4year\DataMigrator\Services\DataMigrator\Export\Exporter;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use RuntimeException;

final readonly class Migrator
{
    private MigratorCreator $creator;

    public function __construct()
    {
        $this->creator = app('migration-data.creator');
    }

    /**
     * @throws FileNotFoundException
     */
    public function makeEntityMigration(string $name, string $path): string
    {
        return $this->creator->createData($name, $path, $this->makeEntityMigrationData());
    }

    public function makeEntityMigrationData(): string
    {
        return 'hello world';
    }

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

        /** @var DataMigratorConfiguration $configMaker */
        $configMaker = app($configClass);
        $configurator = $configMaker->make();

        $configurator->setDirectoryPath($path)
            ->setIds($ids ?? [])
            ->setFileName(date('Y_m_d_His').'_'.$name);

        $exporter = Exporter::create(app($modelClass), $configurator);
        $exporter->export();

        return $configurator->makeSource();
    }
}
