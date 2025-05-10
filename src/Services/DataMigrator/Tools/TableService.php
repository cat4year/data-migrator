<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Tools;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Schema;

final readonly class TableService
{
    public function identifyModelByTable(string $table): ?Model
    {
        foreach (get_declared_classes() as $class) {
            if (is_subclass_of($class, Model::class)) {
                $model = new $class;
                if ($model->getTable() === $table) {
                    return $model;
                }
            }
        }

        return null;
    }

    /**
     * todo: странный метод. перепроверить когда буду делать тесты
     */
    public function tryFindUniqueIdColumnByAutoIncrementKey(string $autoIncKey, string $table): string
    {
        $stableKey = $autoIncKey;

        $indexes = Schema::getIndexes($table);

        foreach ($indexes as $index) {
            if (
                $index['unique'] === true
                && count($index['columns']) === 1
                && ! in_array($autoIncKey, $index['columns'], true)
            ) {
                $stableKey = $index['columns'][0];
            }
        }

        if ($autoIncKey === $stableKey) {
            throw new RuntimeException;
        }

        return $stableKey;
    }

    public function identifyPrimaryKeyNameByTable(string $table): ?string
    {
        $indexes = Schema::getIndexes($table);

        foreach ($indexes as $index) {
            if (
                $index['unique'] === true
                && $index['primary'] === true
                && count($index['columns']) === 1
            ) {
                return $index['columns'][0];
            }
        }

        return null;
    }

    public function isNullableColumn(string $tableName, string $columnName): bool
    {
        $columns = Schema::getColumns($tableName); // todo: add in memory cache
        $columnNullableByName = array_column($columns, 'nullable', 'name');

        return $columnNullableByName[$columnName] === true;
    }

    public function isAutoincrementColumn(string $tableName, string $columnName): bool
    {
        $columns = Schema::getColumns($tableName); // todo: add in memory cache
        $columnNullableByName = array_column($columns, 'auto_increment', 'name');

        return $columnNullableByName[$columnName] === true;
    }

    public function isUniqueColumn(string $tableName, string $columnName): bool
    {
        return Schema::hasIndex($tableName, [$columnName], 'unique');
    }
}
