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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
        $relatedIdKeyName = $this->morphToMany->getRelated()->getKeyName();

        $relatedTable = $this->morphToMany->getRelated()->getTable();
        $pivotTable = $this->morphToMany->getTable();

        $pivotColumns = $this->getPivotColumns($foreignIds);
        $syncId = $this->syncIdState->tableSyncId($this->morphToMany->getTable());
        $syncColumns = $syncId->columns();
        $pivotIds = $pivotColumns
            ->map(static function ($item) use ($syncColumns) {
                $values = array_map(static function ($column) use ($item) {
                    return data_get($item, $column);
                }, $syncColumns);

                return implode('|', array_filter($values));
            })
            ->all();

        $relatedIds = [];
        if ($pivotIds !== []) {
            $relatedIds = $pivotColumns->pluck($this->morphToMany->getRelatedPivotKeyName());
        }

        return [
            $relatedTable => [
                'table' => $relatedTable,
                'keyName' => $relatedIdKeyName,
                'ids' => $relatedIds,
            ],
            $pivotTable => [
                'table' => $pivotTable,
                'keyName' => $syncId,
                'ids' => $pivotIds,
            ],
        ];
    }

    private function getPivotColumns(array $ids): Collection
    {
        $parentPivotKeyName = $this->morphToMany->getForeignPivotKeyName();
        return DB::table($this->morphToMany->getTable())
            ->whereIn($parentPivotKeyName, $ids)
            ->where($this->morphToMany->getMorphType(), $this->morphToMany->getParent()->getMorphClass())
            ->get();
    }

    private function getEntity(): Model
    {
        return $this->morphToMany->getRelated();
    }

    private function getKeyName(): string
    {
        return $this->getEntity()->getKeyName();
    }

    //todo: проверить syncId с автоинкрементами
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
