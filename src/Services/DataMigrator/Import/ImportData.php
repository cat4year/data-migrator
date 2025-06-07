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
        private readonly MigrationDataSourceFormat $migrationDataSourceFormat,
    ) {
    }

    public static function createFromFile(string $path): self
    {
        $importData = app(self::class);

        $importData->data = $importData->migrationDataSourceFormat->load($path);

        return $importData;
    }

    public static function createFromArray(array $data): self
    {
        $importData = app(self::class);

        $importData->data = $data;

        return $importData;
    }

    public function get(): array
    {
        return $this->prepareForImport($this->data);
    }

    private function prepareForImport(array $data): array
    {
        foreach ($data as &$tableData) {
            if (! isset($tableData['modifiedAttributes'])) {
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
