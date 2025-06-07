<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Export;

use Cat4year\DataMigrator\Services\DataMigrator\Tools\SyncIdState;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\TableService;

final readonly class ExportSyncIdAttacher
{

    public function __construct(
        private SyncIdState $syncIdState,
    ) {}

    public function attachSyncIds(array $exportData): array
    {
        $result = [];

        foreach ($exportData as $tableName => $tableData) {
            $result[$tableName] = $tableData;
            $result[$tableName]['syncId'] = $this->syncIdState->tableSyncId($tableName);
        }

        return $result;
    }
}
