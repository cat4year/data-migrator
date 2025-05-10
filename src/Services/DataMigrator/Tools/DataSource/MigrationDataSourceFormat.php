<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Tools\DataSource;

interface MigrationDataSourceFormat
{
    /**
     * @param array<string, mixed> $data
     */
    public function save(array $data, string $path): void;

    public function load(string $resource): array;
}
