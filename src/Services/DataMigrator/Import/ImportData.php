<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Import;

use Cat4year\DataMigrator\Entity\ExportModifyForeignColumn;
use Cat4year\DataMigrator\Entity\ExportModifyMorphColumn;
use Cat4year\DataMigrator\Entity\ExportModifySimpleColumn;
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
        return $this->prepareForImport($this->data);
    }

    private function prepareForImport(array $data): array
    {
        foreach ($data as &$tableData) {
            if (!isset($tableData['modifiedAttributes'])) {
                continue;
            }

            foreach ($tableData['modifiedAttributes'] as &$modifyInfo) {
                $modifyInfo = match (true) {
                    isset($modifyInfo['foreignTableName']) => ExportModifyForeignColumn::fromArray($modifyInfo),
                    isset($modifyInfo['morphType']) => ExportModifyMorphColumn::fromArray($modifyInfo),
                    default => ExportModifySimpleColumn::fromArray($modifyInfo),
                };
            }
        }

        return $data;
    }
}
