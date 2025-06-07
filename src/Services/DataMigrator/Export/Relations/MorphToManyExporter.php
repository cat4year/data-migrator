<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Export\Relations;

use Cat4year\DataMigrator\Entity\ExportModifyForeignColumn;
use Cat4year\DataMigrator\Entity\ExportModifyMorphColumn;
use Cat4year\DataMigrator\Entity\ExportModifySimpleColumn;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\SyncIdState;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\TableService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final readonly class MorphToManyExporter implements RelationExporter
{
    public function __construct(
        private MorphToMany $morphToMany,
        private TableService $tableService,
        private SyncIdState $syncIdState,
    ) {
    }

    /**
     * @throws BindingResolutionException
     */
    public static function create(MorphToMany $morphToMany): self
    {
        return app()->makeWith(self::class, ['morphToMany' => $morphToMany]);
    }

    public function makeExportData(array $foreignIds): array
    {
        $pivotIdKeyName = $this->getPivotIdColumnKeyName();
        $pivotIds = $this->getPivotUsedIds($foreignIds, $pivotIdKeyName);
        $relatedIdKeyName = $this->morphToMany->getRelated()->getKeyName();

        $relatedTable = $this->morphToMany->getRelated()->getTable();
        $pivotTable = $this->morphToMany->getTable();
        $relatedPivotKeyName = $this->morphToMany->getRelatedPivotKeyName();

        $relatedIds = [];
        if ($pivotIds !== []) {
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
        return DB::table($this->morphToMany->getTable())
            ->select($relatedForeignKeyName)
            ->whereIn($pivotIdKeyName, $ids)
            ->where($this->morphToMany->getMorphType(), $this->morphToMany->getParent()->getMorphClass())
            ->get()
            ->pluck($relatedForeignKeyName)
            ->toArray();
    }

    private function getPivotIdColumnKeyName(bool $checkFalseAutoIncrement = false): string
    {
        $columns = Schema::getColumns($this->morphToMany->getTable());
        foreach ($columns as $column) {
            if ($column['nullable'] === false && (! $checkFalseAutoIncrement || $column['auto_increment'] === false)) {
                return $column['name'];
            }
        }

        return current($columns)['name'];
    }

    private function getPivotUsedIds(array $ids, string $pivotIdKeyName): array
    {
        $parentPivotKeyName = $this->morphToMany->getForeignPivotKeyName();

        return DB::table($this->morphToMany->getTable())
            ->whereIn($parentPivotKeyName, $ids)
            ->where($this->morphToMany->getMorphType(), $this->morphToMany->getParent()->getMorphClass())
            ->get()
            ->pluck($pivotIdKeyName)
            ->toArray();
    }

    private function getEntity(): Model
    {
        return $this->morphToMany->getRelated();
    }

    private function getKeyName(): string
    {
        return $this->getEntity()->getKeyName();
    }

    public function getModifyInfo(): array
    {
        $model = $this->morphToMany->getParent();
        $syncId = $this->syncIdState->tableSyncId($model->getTable());
        $parentTable = $model->getTable();
        $parentKeyName = $model->getKeyName();

        $related = $this->morphToMany->getRelated();
        $relatedTable = $related->getTable();
        $relatedKeyName = $related->getKeyName();
        $uniqueRelatedKeyName = $this->syncIdState->tableSyncId($related->getTable());

        $pivotTable = $this->morphToMany->getTable();
        $parentPivotKeyName = $this->morphToMany->getForeignPivotKeyName();
        $relatedPivotKeyName = $this->morphToMany->getRelatedPivotKeyName();

        $parentTableColumn = new ExportModifySimpleColumn(
            tableName: $parentTable,
            keyName: $parentKeyName,
            uniqueKeyName: $syncId,
            nullable: $this->tableService->isNullableColumn($parentTable, $parentKeyName),
            autoincrement: $this->tableService->isAutoincrementColumn($parentTable, $parentKeyName),
        );

        $relatedTableColumn = new ExportModifySimpleColumn(
            tableName: $relatedTable,
            keyName: $relatedKeyName,
            uniqueKeyName: $uniqueRelatedKeyName,
            nullable: $this->tableService->isNullableColumn($relatedTable, $relatedKeyName),
            autoincrement: $this->tableService->isAutoincrementColumn($relatedTable, $relatedKeyName),
        );

        $exportModifyMorphColumn = new ExportModifyMorphColumn(
            morphType: $this->morphToMany->getMorphType(),
            tableName: $relatedTable,
            keyName: $this->morphToMany->getRelatedPivotKeyName(),// todo: вот тут то пупупу. Что делать? Ключ должен быть составным. Возможно должно быть nullable? Юзается ли?
            sourceKeyNames: [$parentTable => $syncId],
            sourceOldKeyNames: [$parentTable => $parentKeyName],
            nullable: $this->tableService->isNullableColumn($pivotTable, $parentPivotKeyName),
            autoincrement: $this->tableService->isAutoincrementColumn($pivotTable, $parentPivotKeyName),
        );

        $exportModifyForeignColumn = new ExportModifyForeignColumn(
            tableName: $relatedTable,
            keyName: $relatedKeyName,
            foreignTableName: $relatedTable,
            foreignUniqueKeyName: $uniqueRelatedKeyName,
            foreignOldKeyName: $relatedKeyName,
            nullable: $this->tableService->isNullableColumn($pivotTable, $relatedKeyName),
        );

        return [
            $parentTable => [
                $parentKeyName => $parentTableColumn,
            ],
            $relatedTable => [
                $relatedKeyName => $relatedTableColumn,
            ],
            $pivotTable => [
                $parentPivotKeyName => $exportModifyMorphColumn,
                $relatedPivotKeyName => $exportModifyForeignColumn,
            ],
        ];
    }
}
