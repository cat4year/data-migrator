<?php

namespace Cat4year\DataMigrator\Entity;

final class ExportModifyInfo
{
    private static array $state = [];

    public static function add(ExportModifyColumn $column): void
    {
        $hash = $column->getKeyName() . '|' . $column->getTableName();
        if (isset(self::$state[$hash])) {
            return;
        }

        self::$state[$hash] = $column;
    }
}
