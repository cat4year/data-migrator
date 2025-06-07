<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Tools;

use Illuminate\Support\Facades\Schema;

final class SchemaState
{
    private array $columns = [];

    private array $indexes = [];

    public function columns(string $table): array
    {
        if (! isset($this->columns[$table])) {
            $columns = Schema::getColumns($table);
            $result = array_column($columns, null, 'name');
            $this->columns[$table] = $result;
        }

        return $this->columns[$table];
    }

    public function indexes(string $table): array
    {
        if (! isset($this->indexes[$table])) {
            $this->indexes[$table] = Schema::getIndexes($table);
        }

        return $this->indexes[$table];
    }

    public function hasIndex(string $table, array|string $index, ?string $type = null): bool
    {
        $type = $type === null ? $type : mb_strtolower($type);

        foreach ($this->indexes($table) as $value) {
            $typeMatches = $type === null
                || ($type === 'primary' && $value['primary'])
                || ($type === 'unique' && $value['unique'])
                || $type === $value['type'];

            if (($value['name'] === $index || $value['columns'] === $index) && $typeMatches) {
                return true;
            }
        }

        return false;
    }
}
