<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Export\Relations;

use Cat4year\DataMigrator\Entity\ExportModifyForeignColumn;
use Cat4year\DataMigrator\Entity\ExportModifyMorphColumn;
use Cat4year\DataMigrator\Entity\ExportModifySimpleColumn;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\ModelService;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\TableService;
use DB;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Schema;

final readonly class MorphToManyExporter implements RelationExporter
{
    public function __construct(
        private MorphToMany $relation,
        private ModelService $modelService,
        private TableService $tableRepository,
    ) {
    }

    /**
     * @throws BindingResolutionException
     */
    public static function create(MorphToMany $relation): self
    {
        return app()->makeWith(self::class, compact('relation'));
    }

    public function makeExportData(array $foreignIds): array
    {
        $pivotIdKeyName = $this->getPivotIdColumnKeyName();
        $pivotIds = $this->getPivotUsedIds($foreignIds, $pivotIdKeyName);
        $relatedIdKeyName = $this->relation->getRelated()->getKeyName();

        $relatedTable = $this->relation->getRelated()->getTable();
        $pivotTable = $this->relation->getTable();
        $relatedPivotKeyName = $this->relation->getRelatedPivotKeyName();

        $relatedIds = [];
        if (! empty($pivotIds)) {
            $relatedIds = $this->getRelatedUsedIdsByPivot($pivotIds, $pivotIdKeyName, $relatedPivotKeyName);
        }

        return [
            $relatedTable => [
                'table' => $relatedTable,
                'keyName' => $relatedIdKeyName,
                'ids' => $relatedIds,
            ],
            $pivotTable => [
                'table' => $pivotTable,
                'keyName' => $pivotIdKeyName,
                'ids' => $pivotIds,
            ],
        ];
    }

    private function getRelatedUsedIdsByPivot(array $ids, string $pivotIdKeyName, string $relatedForeignKeyName): array
    {
        return DB::table($this->relation->getTable())
            ->select($relatedForeignKeyName)
            ->whereIn($pivotIdKeyName, $ids)
            ->where($this->relation->getMorphType(), $this->relation->getParent()->getMorphClass())
            ->get()
            ->pluck($relatedForeignKeyName)
            ->toArray();
    }

    private function getPivotIdColumnKeyName(bool $checkFalseAutoIncrement = false): string
    {
        $columns = Schema::getColumns($this->relation->getTable());
        foreach ($columns as $column) {
            if ($column['nullable'] === false && (! $checkFalseAutoIncrement || $column['auto_increment'] === false)) {
                return $column['name'];
            }
        }

        return current($columns)['name'];
    }

    private function getPivotUsedIds(array $ids, string $pivotIdKeyName): array
    {
        $parentPivotKeyName = $this->relation->getForeignPivotKeyName();

        return DB::table($this->relation->getTable())
            ->whereIn($parentPivotKeyName, $ids)
            ->where($this->relation->getMorphType(), $this->relation->getParent()->getMorphClass())
            ->get()
            ->pluck($pivotIdKeyName)
            ->toArray();
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
        $uniqueKeyName = $this->modelService->identifyUniqueIdColumn($parent);
        $parentTable = $parent->getTable();
        $parentKeyName = $parent->getKeyName();

        if ($uniqueKeyName === null) {
            // todo: можно решить через конфигуратор что с этим делать: скип, дефолтный keyName, ...?
        }

        $related = $this->relation->getRelated();
        $relatedTable = $related->getTable();
        $relatedKeyName = $related->getKeyName();
        $uniqueRelatedKeyName = $this->modelService->identifyUniqueIdColumn($related);

        if ($uniqueRelatedKeyName === null) {
            // todo: можно решить через конфигуратор что с этим делать: скип, дефолтный keyName, ...?
        }

        $pivotTable = $this->relation->getTable();
        $parentPivotKeyName = $this->relation->getForeignPivotKeyName();
        $relatedPivotKeyName = $this->relation->getRelatedPivotKeyName();

        $parentTableColumn = new ExportModifySimpleColumn(
            tableName: $parentTable,
            keyName: $parentKeyName,
            uniqueKeyName: $uniqueKeyName,
            nullable: $this->tableRepository->isNullableColumn($parentTable, $parentKeyName),
            autoincrement: $this->tableRepository->isAutoincrementColumn($parentTable, $parentKeyName),
        );

        $relatedTableColumn = new ExportModifySimpleColumn(
            tableName: $relatedTable,
            keyName: $relatedKeyName,
            uniqueKeyName: $uniqueRelatedKeyName,
            nullable: $this->tableRepository->isNullableColumn($relatedTable, $relatedKeyName),
            autoincrement: $this->tableRepository->isAutoincrementColumn($relatedTable, $relatedKeyName),
        );

        $pivotParentMorphColumn = new ExportModifyMorphColumn(
            morphType: $this->relation->getMorphType(),
            tableName: $relatedTable,
            keyName: $this->relation->getRelatedPivotKeyName(),//todo: вот тут то пупупу. Что делать? Ключ должен быть составным. Возможно должно быть nullable? Юзается ли?
            sourceKeyNames: [$parentTable => $uniqueKeyName],
            sourceOldKeyNames: [$parentTable => $parentKeyName],
            nullable: $this->tableRepository->isNullableColumn($pivotTable, $parentPivotKeyName),
            autoincrement: $this->tableRepository->isAutoincrementColumn($pivotTable, $parentPivotKeyName),
        );

        $pivotRelatedForeignColumn = new ExportModifyForeignColumn(
            tableName: $relatedTable,
            keyName: $relatedKeyName,
            foreignTableName: $relatedTable,
            foreignUniqueKeyName: $uniqueRelatedKeyName,
            foreignOldKeyName: $relatedKeyName,
            nullable: $this->tableRepository->isNullableColumn($pivotTable, $relatedKeyName),
        );

        return [
            $parentTable => [
                $parentKeyName => $parentTableColumn,
            ],
            $relatedTable => [
                $relatedKeyName => $relatedTableColumn,
            ],
            $pivotTable => [
                $parentPivotKeyName => $pivotParentMorphColumn,
                $relatedPivotKeyName => $pivotRelatedForeignColumn,
            ],
        ];
    }
}
