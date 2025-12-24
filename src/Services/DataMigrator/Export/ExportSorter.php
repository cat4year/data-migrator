<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Export;

use Cat4year\DataMigrator\Entity\ExportModifyColumn;
use Cat4year\DataMigrator\Entity\ExportModifyForeignColumn;
use Cat4year\DataMigrator\Entity\ExportModifyMorphColumn;

final class ExportSorter
{
    public function sort(array $tables): array
    {
        // Сохраняем исходные данные
        $originalTables = $tables;

        // Создаём граф только для анализа зависимостей
        $graph = $this->buildDependencyGraph($tables);

        // Находим циклы и определяем порядок
        $cycleTables = $this->findCycles($graph);

        return $this->sortTables($cycleTables, $graph, $originalTables);
    }

    /**
     * @param array<string, array{items: array<int|string, array<string|mixed>>, modifiedAttributes: list<ExportModifyColumn>}> $tables
     */
    private function buildDependencyGraph(array $tables): array
    {
        $graph = [];
        foreach ($tables as $tableName => $tableInfo) {
            $this->makeEmptyGraphNodeByTableIfNotExist($graph, $tableName);

            if (isset($tableInfo['modifiedAttributes'])) {
                foreach ($tableInfo['modifiedAttributes'] as $column) {
                    if ($column instanceof ExportModifyMorphColumn) {
                        $graph[$tableName]['dependencies'] = array_keys($column->getSourceKeyNames()); // или oldKeyNames

                        // todo: это морф ключ. Может нужно добавить tables и добавить их зависимости.
                        continue;
                    }

                    if ($column instanceof ExportModifyForeignColumn) {
                        if ($column->getTableName() !== $column->getSourceTableName()) {
                            $this->makeEmptyGraphNodeByTableIfNotExist($graph, $column->getTableName());
                            $graph[$column->getTableName()]['dependencies'][] = $column->getSourceTableName();

                            if ($column->isNullable()) {
                                $graph[$column->getTableName()]['hasNullableKey'] = true;
                            }

                            $this->makeEmptyGraphNodeByTableIfNotExist($graph, $column->getSourceTableName());
                            $graph[$column->getSourceTableName()]['referenced_by'][] = $column->getTableName();

                        }

                        continue;
                    }

                    $columnTableName = $column->getTableName();
                    if ($columnTableName !== $tableName) {
                        $graph[$tableName]['dependencies'][] = $columnTableName;

                        if ($column->isNullable()) {
                            $graph[$tableName]['hasNullableKey'] = true;
                        }

                        $this->makeEmptyGraphNodeByTableIfNotExist($graph, $columnTableName);
                        $graph[$columnTableName]['referenced_by'][] = $tableName;
                    }
                }
            }
        }

        return $graph;
    }

    private function makeEmptyGraphNodeByTableIfNotExist(array &$graph, string $table): void
    {
        if (isset($graph[$table])) {
            return;
        }

        $graph[$table] = [
            'name' => $table,
            'dependencies' => [],
            'hasNullableKey' => false,
            'referenced_by' => [],
        ];
    }

    private function findCycles(array $graph): array
    {
        $cycleTables = [];
        $visited = [];

        foreach (array_keys($graph) as $tableName) {
            if (! isset($visited[$tableName])) {
                $this->findCycle($tableName, $graph, $visited, [], $cycleTables);
            }
        }

        return $cycleTables;
    }

    private function sortTables(array $cycleTables, array $graph, array $originalTables): array
    {
        // 1. Топологический порядок (Kahn)
        $inDegree = [];
        $edges = [];

        foreach ($graph as $name => $node) {
            $inDegree[$name] ??= 0;

            foreach ($node['dependencies'] as $dep) {
                if (! isset($graph[$dep])) {
                    continue;
                }

                $edges[$dep][] = $name;
                $inDegree[$name]++;
            }
        }

        $queue = [];
        foreach ($inDegree as $name => $deg) {
            if ($deg === 0) {
                $queue[] = $name;
            }
        }

        $ordered = [];
        while ($queue) {
            $current = array_shift($queue);
            $ordered[] = $current;

            foreach ($edges[$current] ?? [] as $next) {
                if (--$inDegree[$next] === 0) {
                    $queue[] = $next;
                }
            }
        }

        // 2. Если остались — это циклы
        foreach ($graph as $name => $_) {
            if (! in_array($name, $ordered, true)) {
                $ordered[] = $name;
            }
        }

        // 3. Собираем результат
        $result = [];
        foreach ($ordered as $tableName) {
            if (isset($originalTables[$tableName])) {
                $result[$tableName] = $originalTables[$tableName];
            }
        }

        return $result;
    }

    private function findCycle(
        string $tableName,
        array $graph,
        array &$visited,
        array $currentPath,
        array &$cycleTables
    ): bool {
        $visited[$tableName] = true;
        $currentPath[$tableName] = true;

        if (!isset($graph[$tableName])) {
            return false;
        }

        foreach ($graph[$tableName]['dependencies'] as $dep) {
            if (! isset($visited[$dep])) {
                if ($this->findCycle($dep, $graph, $visited, $currentPath, $cycleTables)) {
                    $cycleTables[] = $tableName;

                    return true;
                }
            } elseif (isset($currentPath[$dep])) {
                $cycleTables[] = $tableName;

                return true;
            }
        }

        unset($currentPath[$tableName]);

        return false;
    }
}
