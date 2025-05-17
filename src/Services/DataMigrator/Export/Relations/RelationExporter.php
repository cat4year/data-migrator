<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Export\Relations;

use Cat4year\DataMigrator\Services\DataMigrator\Export\ExportModifyState;

interface RelationExporter
{
    public function makeExportData(array $foreignIds): array;

    public function getModifyInfo(): array;
}
