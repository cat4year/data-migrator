<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Entity;

final class ExportModifyInfo
{
    private static array $state = [];

    public static function add(ExportModifyColumn $exportModifyColumn): void
    {
        $hash = $exportModifyColumn->getKeyName() . '|' . $exportModifyColumn->getTableName();
        if (isset(self::$state[$hash])) {
            return;
        }

        self::$state[$hash] = $exportModifyColumn;
    }
}
