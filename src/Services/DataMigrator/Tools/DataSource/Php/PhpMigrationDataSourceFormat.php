<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Tools\DataSource\Php;

use Brick\VarExporter\ExportException;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\DataSource\MigrationDataSourceFormat;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use JsonException;
use RuntimeException;

use function Cat4year\DataMigrator\Helpers\var_pretty_export;

final readonly class PhpMigrationDataSourceFormat implements MigrationDataSourceFormat
{
    public function __construct(
        private Filesystem $filesystem,
        private ArrayToPhp $arrayToPhp,
    ) {
    }

    /**
     * @throws ExportException
     * @throws FileNotFoundException
     * @throws JsonException
     */
    public function save(array $data, string $path): void
    {
        $result = $this->prepareForSave($data);

        $this->filesystem->ensureDirectoryExists(dirname($path));
        $this->filesystem->put($path, $result);
    }

    /**
     * @throws FileNotFoundException
     */
    public function load(string $resource): array
    {
        $data = require $resource;

        throw_unless(isset($data), new RuntimeException('Variable $data not found on export file'));

        return $data;
    }

    /**
     * @throws ExportException
     * @throws FileNotFoundException
     * @throws JsonException
     */
    private function prepareForSave(array $exportData): string
    {
        return $this->arrayToPhp->prepareStubBeforeSave($this->prepare($exportData));
    }

    /**
     * @throws ExportException
     * @throws JsonException
     */
    public function prepareForMigration(array $exportData): string
    {
        return $this->prepare($exportData);
    }

    /**
     * @throws ExportException
     * @throws JsonException
     */
    private function prepare(array $exportData): string
    {
        $exportDataWithoutObjects = json_decode(
            json_encode($exportData, JSON_THROW_ON_ERROR),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        return var_pretty_export($exportDataWithoutObjects, true);
    }
}
