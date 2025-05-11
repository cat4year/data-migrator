<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Import;

use Cat4year\DataMigrator\Services\DataMigrator\Tools\DataSource\MigrationDataSourceFormat;

final class ImportData
{
    private array $data;

    public function __construct(
        private readonly MigrationDataSourceFormat $sourceFormat,
    ) {
    }

    public static function createFromFile(string $path): self
    {
        $self = app(self::class);

        $self->data = $self->sourceFormat->load($path);

        return $self;

    }

    public static function createFromArray(array $data): self
    {
        $self = app(self::class);

        $self->data = $data;

        return $self;
    }

    public function get(): array
    {
        return $this->data;
    }
}
