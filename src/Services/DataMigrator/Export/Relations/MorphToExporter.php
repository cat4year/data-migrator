<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Export\Relations;

use Cat4year\DataMigrator\Entity\ExportModifyMorphColumn;
use Cat4year\DataMigrator\Entity\ExportModifySimpleColumn;
use Cat4year\DataMigrator\Entity\SyncId;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\ModelService;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\TableService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final readonly class MorphToExporter implements RelationExporter
{
    public function __construct(
        private MorphTo $relation,
        private ModelService $modelService,
        private TableService $tableRepository,
        private ?array $tableData = null,
    ) {
    }

    /**
     * @throws BindingResolutionException
     */
    public static function create(MorphTo $relation, ?array $tableData): self
    {
        return app()->makeWith(self::class, compact('relation', 'tableData'));
    }

    public function makeExportData(array $foreignIds): array
    {
        $items = $this->getParentItems($foreignIds);
        $ids = $this->getUsedIds($items);
        $table = $this->relation->getModel()->getTable();

        $foreignTables = $this->getForeignTablesExportData($items);

        return [
            $table => [
                'table' => $table,
                'keyName' => $this->getKeyName(),
                'ids' => $ids,
            ],
            ...$foreignTables,
        ];
    }

    private function getUsedIds(Collection $items): array
    {
        $foreignKey = $this->relation->getForeignKeyName();

        return $items->pluck($foreignKey)->toArray();
    }

    private function getEntity(): Model
    {
        return $this->relation->getRelated();
    }

    private function getKeyName(): string
    {
        return $this->getEntity()->getKeyName();
    }

    public function getModifyInfo(): array
    {
        $parent = $this->relation->getParent();
        $parentTable = $parent->getTable();
        $parentKeyName = $parent->getKeyName();
        $uniqueParentKeyName = $this->modelService->identifyUniqueIdColumn($parent);

        $related = $this->relation->getRelated();
        $uniqueRelatedKeyName = $this->modelService->identifyUniqueIdColumn($related);

        $uniqueKeyName = $uniqueParentKeyName;
        $foreignKeyName = $this->relation->getForeignKeyName();

        if ($uniqueKeyName === null || $uniqueRelatedKeyName === null) {
            // todo: можно решить через конфигуратор что с этим делать: скип, дефолтный keyName, ...?
        }

        $foreignClasses = $this->getUsedForeignClasses($this->tableData['items'] ?? []);
        $foreignClassesModifyInfo = $this->getForeignClassesModifyInfo($foreignClasses);

        $foreignClassesColumnsData = array_map(current(...), $foreignClassesModifyInfo);

        $oldKeyNames = array_column($foreignClassesColumnsData, 'oldKeyName', 'table');
        $keyNames = array_column($foreignClassesColumnsData, 'keyName', 'table');
        $syncKeyNames = array_map(static fn(string $keyName) => new SyncId([$keyName]), $keyNames);

        $parentTableForeignColumn = new ExportModifyMorphColumn(
            morphType: $this->relation->getMorphType(),
            tableName: $parentTable,
            keyName: $foreignKeyName,
            sourceKeyNames: $syncKeyNames,
            sourceOldKeyNames: $oldKeyNames,
            nullable: $this->tableRepository->isNullableColumn($parentTable, $foreignKeyName),
            autoincrement: $this->tableRepository->isAutoincrementColumn($parentTable, $foreignKeyName),
        );

        $parentSyncKey = new SyncId([$uniqueParentKeyName]);
        $parentTableColumn = new ExportModifySimpleColumn(
            tableName: $parentTable,
            keyName: $parentKeyName,
            uniqueKeyName: $parentSyncKey,
            nullable: $this->tableRepository->isNullableColumn($parentTable, $parentKeyName),
            autoincrement: $this->tableRepository->isAutoincrementColumn($parentTable, $parentKeyName),
        );

        $foreignClassesModifyInfoTablesColumns = [];
        if (!empty($foreignClassesModifyInfo)) {
            foreach ($foreignClassesModifyInfo as $tableName => $tableData) {
                foreach ($tableData as $columnData) {
                    //todo: достаточно ли только simple? может там foreign или морф будет? может ли?
                    $column = new ExportModifySimpleColumn(
                        tableName: $columnData['table'], //todo: почему это в entity массив, а не объект?
                        keyName: $columnData['oldKeyName'],
                        uniqueKeyName: new SyncId([$columnData['keyName']]),
                        nullable: $columnData['nullable'],
                        autoincrement: $columnData['autoIncrement'],
                        isPrimaryKey: $columnData['isPrimaryKey'],
                    );

                    $foreignClassesModifyInfoTablesColumns[$tableName][$column->getKeyName()] = $column;
                }
            }
        }

        return [
            $parentTable => [
                $parentTableForeignColumn->getKeyName() => $parentTableForeignColumn,
                $parentTableColumn->getKeyName() => $parentTableColumn,
            ],
            ...$foreignClassesModifyInfoTablesColumns,//todo: нужно ли?
        ];
    }

    private function getUsedForeignClasses(array $items): array
    {
        $morphType = $this->relation->getMorphType();

        return array_column($items, $morphType);
    }

    private function getParentItems(array $ids): Collection
    {
        $idKey = $this->relation->getParent()->getKeyName();
        $foreignKey = $this->relation->getForeignKeyName();

        return $this->relation->getParent()::query()
            ->select()
            ->whereNotNull($foreignKey)
            ->whereNotNull($this->relation->getMorphType())
            ->whereIn($idKey, $ids)
            ->get();
    }

    private function getForeignClassesModifyInfo(array $foreignClasses): array
    {
        $result = [];

        foreach ($foreignClasses as $foreignClass) {
            $model = app($foreignClass);
            assert($model instanceof Model);
            $tableName = $model->getTable();
            $modelKeyName = $model->getKeyName();
            $uniqueModelKeyName = $this->modelService->identifyUniqueIdColumn($model);
            $result[$tableName] = [
                $modelKeyName => [
                    'table' => $tableName,
                    'oldKeyName' => $modelKeyName,
                    'keyName' => $uniqueModelKeyName,
                    'isPrimaryKey' => true,
                    'autoIncrement' => $this->tableRepository->isAutoincrementColumn($tableName, $modelKeyName),
                    'nullable' => $this->tableRepository->isNullableColumn($tableName, $modelKeyName),
                ],
            ];
        }

        return $result;
    }

    private function getForeignTablesExportData(Collection $items): array
    {
        $result = [];
        $morphType = $this->relation->getMorphType();
        $foreignKey = $this->relation->getForeignKeyName();
        foreach ($items as $item) {
            $foreignModelClass = $item->getAttribute($morphType);
            $foreignModelId = $item->getAttribute($foreignKey);
            $foreignModel = app($foreignModelClass);
            assert($foreignModel instanceof Model);
            $table = $foreignModel->getTable();

            if (! isset($result[$table])) {
                $result[$table]['table'] = $table;
                $result[$table]['keyName'] = $foreignModel->getKeyName();
            }

            if (! isset($result[$table]['ids']) || ! in_array($foreignModelId, $result[$table]['ids'], true)) {
                $result[$table]['ids'][] = $foreignModelId;
            }
        }

        return $result;
    }
}
