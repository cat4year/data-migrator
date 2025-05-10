<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Tools\DataSource\Php;

use Cat4year\DataMigrator\Services\DataMigrator\Tools\DataSource\MigrationDataSourceFormat;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

final readonly class PhpMigrationDataSourceFormat implements MigrationDataSourceFormat
{
    public function __construct(
        private Filesystem $files,
        private ArrayToPhp $arrayToPhp,
    ) {
    }

    /**
     * @throws FileNotFoundException
     */
    public function save(array $data, string $path): void
    {
        $result = $this->arrayToPhp->prepareStubBeforeSave($data);

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $result);
    }

    /**
     * @throws FileNotFoundException
     */
    public function load(string $resource): array
    {
        $data = require $resource;

        if (! isset($data)) {
            throw new RuntimeException('Variable $data not found on export file');
        }

        return $data;
    }
}
