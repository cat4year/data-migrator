<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Export;

use Cat4year\DataMigrator\Entity\ExportModifySimpleColumn;

final readonly class ExportRelationModifier
{
    public function makeModifyColumn(string $tableName, string $keyName): ExportModifySimpleColumn
    {
        return new ExportModifySimpleColumn($tableName, $keyName);
    }
}
