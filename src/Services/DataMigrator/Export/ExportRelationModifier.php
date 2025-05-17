<?php

namespace Cat4year\DataMigrator\Services\DataMigrator\Export;

use Cat4year\DataMigrator\Entity\ExportModifySimpleColumn;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\TableService;

final readonly class ExportRelationModifier
{
    public function __construct(
        private TableService $tableService,
    ) {
    }

    public function makeModifyColumn(string $tableName, string $keyName, string $oldKeyName): ExportModifySimpleColumn
    {
        return new ExportModifySimpleColumn($tableName, $keyName );
    }
}
