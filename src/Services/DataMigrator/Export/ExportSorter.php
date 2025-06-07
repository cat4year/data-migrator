<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Export;

use Cat4year\DataMigrator\Entity\ExportModifyColumn;
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
            $graph[$tableName] = [
                'name' => $tableName,
                'dependencies' => [],
                'hasNullableKey' => false,
                'referenced_by' => [],
            ];

            if (isset($tableInfo['modifiedAttributes'])) {
                foreach ($tableInfo['modifiedAttributes'] as $column) {
                    if ($column instanceof ExportModifyMorphColumn) {
                        $graph[$tableName]['dependencies'] = array_keys($column->getSourceKeyNames()); // или oldKeyNames

                        // todo: это морф ключ. Может нужно добваить tables и добавить их зависимости.
                        continue;
                    }

                    $columnTableName = $column->getTableName();
                    if ($columnTableName !== $tableName) {
                        $graph[$tableName]['dependencies'][] = $columnTableName;
                        if (! isset($graph[$columnTableName])) {
                            $graph[$columnTableName] = [
                                'name' => $columnTableName,
                                'dependencies' => [],
                                'hasNullableKey' => false,
                                'referenced_by' => [],
                            ];
                        }

                        $graph[$columnTableName]['referenced_by'][] = $tableName;

                        if ($column->isNullable()) {
                            $graph[$tableName]['hasNullableKey'] = true;
                        }
                    }
                }
            }
        }

        return $graph;
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
        // Сначала добавляем таблицы без зависимостей, и с необязательными ключами
        $noDependencies = array_filter($graph, static fn ($deps): bool => empty($deps['dependencies']) && $deps['hasNullableKey']);

        // Затем таблицы без зависимостей, но с обязательными ключами
        $nullableWithoutDependencies = array_filter($graph, static fn ($deps): bool => empty($deps['dependencies']) && ! $deps['hasNullableKey']);

        // Затем таблицы с зависимостями, но с необязательными ключами
        $nullableWithDependencies = array_filter($graph, static fn ($deps) => $deps['hasNullableKey']);

        // Затем таблицы из цикла
        $cycleTables = array_filter($graph, static fn ($deps): bool => in_array($deps['name'], $cycleTables, true));

        // Затем pivot таблицы с зависимостями, но от которых никто не зависит
        $pivotTables = array_filter($graph, static fn ($deps): bool => empty($deps['referenced_by']) && ! $deps['hasNullableKey']);

        // Меняем направление сортировки на возрастание (от слабых к сильным)
        uasort($cycleTables, static function (array $a, array $b): int {
            // Сначала сравниваем количество зависимостей
            $dependencyDiff = count($a['dependencies']) <=> count($b['dependencies']);
            if ($dependencyDiff !== 0) {
                return $dependencyDiff;
            }

            // Если количество зависимостей одинаковое, проверяем зависимости
            $aDeps = array_flip($a['dependencies']);
            $bDeps = array_flip($b['dependencies']);

            // Проверяем, есть ли у a зависимости, которые идут раньше в b
            $aHasEarlierDeps = false;
            foreach (array_keys($aDeps) as $dep) {
                if (isset($bDeps[$dep])) {
                    $aHasEarlierDeps = true;
                    break;
                }
            }

            // Проверяем, есть ли у b зависимости, которые идут раньше в a
            $bHasEarlierDeps = false;
            foreach (array_keys($bDeps) as $dep) {
                if (isset($aDeps[$dep])) {
                    $bHasEarlierDeps = true;
                    break;
                }
            }

            // Если у a есть зависимости, которые идут раньше в b, то a идёт после b
            if ($aHasEarlierDeps && ! $bHasEarlierDeps) {
                return 1;
            }

            // Если у b есть зависимости, которые идут раньше в a, то b идёт после a
            if ($bHasEarlierDeps && ! $aHasEarlierDeps) {
                return -1;
            }

            // Если таблицы имеют одинаковые зависимости или нет пересечений,
            // то сравниваем по nullable полю
            return $b['hasNullableKey'] <=> $a['hasNullableKey'];
        });

        // Объединяем все группы
        $sortedTables = array_merge(
            $noDependencies,
            $nullableWithoutDependencies,
            $nullableWithDependencies,
            $cycleTables,
            $pivotTables
        );

        $result = [];
        foreach (array_keys($sortedTables) as $tableName) {
            $result[$tableName] = $originalTables[$tableName];
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
