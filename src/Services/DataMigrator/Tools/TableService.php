<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Tools;

use Cat4year\DataMigrator\Entity\SyncId;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Schema;

final readonly class TableService
{

    public function __construct(
        private SchemaState $schemaState,
    ) {}

    public function schemaState(): SchemaState
    {
        return $this->schemaState;
    }

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

    public function syncId(string $table): SyncId
    {
        return app(SyncIdState::class)->tableSyncId($table); //todo: циклическая зависимость-
    }

    /**
     * @deprecated Пока используетсся но будет убран
     * todo: странный метод. перепроверить когда буду делать тесты
     */
    public function tryFindUniqueIdColumnByAutoIncrementKey(string $autoIncKey, string $table): string
    {
        $stableKey = $autoIncKey;

        $indexes = $this->schemaState->indexes($table);

        foreach ($indexes as $index) {
            if (
                $index['unique'] === true
                && count($index['columns']) === 1
                && !in_array($autoIncKey, $index['columns'], true)
            ) {
                $stableKey = $index['columns'][0];
            }
        }

        if ($autoIncKey === $stableKey) {
            throw new RuntimeException;
        }

        return $stableKey;
    }

    public function tryFindUniqueColumnsByIndex(string $table): string|array|null
    {
        $indexes = $this->schemaState->indexes($table);
        $nonAutoIncrementColumns = $this->getNonAutoIncrementColumns($table);

        foreach ($indexes as $index) {
            if (
                $index['unique'] === true
                && empty(array_diff($index['columns'], $nonAutoIncrementColumns))
            ) {
                if (count($index['columns']) === 1) {
                    return current($index['columns']);
                }

                return $index['columns'];
            }
        }

        return null;
    }

    private function getNonAutoIncrementColumns(string $tableName): array
    {
        $columns = $this->schemaState->columns($tableName);
        return array_map(
            static fn($column) => $column['name'],
            array_filter($columns, static fn($column) => !$column['auto_increment'])
        );
    }

    public function identifyPrimaryKeyNameByTable(string $table): ?string
    {
        $indexes = $this->schemaState->indexes($table);

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
        $columns = $this->schemaState->columns($tableName);
        $columnNullableByName = array_column($columns, 'nullable', 'name');

        return $columnNullableByName[$columnName] === true;
    }

    public function isAutoincrementColumn(string $tableName, string $columnName): bool
    {
        $columns = $this->schemaState->columns($tableName);
        $columnNullableByName = array_column($columns, 'auto_increment', 'name');

        if (!isset($columnNullableByName[$columnName])) {
            throw new RuntimeException(sprintf('Не обнаружена колонка %s в таблице %s', $columnName, $tableName));
        }

        return $columnNullableByName[$columnName] === true;
    }

    public function isUniqueColumn(string $tableName, string $columnName): bool
    {
        return $this->schemaState->hasIndex($tableName, [$columnName], 'unique');
    }
}
