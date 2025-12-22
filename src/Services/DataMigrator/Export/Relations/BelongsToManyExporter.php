<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Export\Relations;

use Cat4year\DataMigrator\Entity\ExportModifyForeignColumn;
use Cat4year\DataMigrator\Entity\ExportModifySimpleColumn;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\SyncIdState;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\TableService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final readonly class BelongsToManyExporter implements RelationExporter
{
    public function __construct(
        private BelongsToMany $belongsToMany,
        private TableService $tableService,
        private SyncIdState $syncIdState,
    ) {
    }

    /**
     * @throws BindingResolutionException
     */
    public static function create(BelongsToMany $belongsToMany): self
    {
        return app()->makeWith(self::class, ['belongsToMany' => $belongsToMany]);
    }

    public function makeExportData(array $foreignIds): array
    {
        $relatedIds = $this->getRelatedUsedIds($foreignIds);
        $pivotIdKeyName = $this->getPivotIdColumnKeyName();
        $pivotIds = $this->getPivotUsedIds($foreignIds, $pivotIdKeyName);

        $relatedTable = $this->belongsToMany->getRelated()->getTable();
        $pivotTable = $this->belongsToMany->getTable();

        return [
            $relatedTable => [
                'table' => $relatedTable,
                'keyName' => $this->belongsToMany->getRelated()->getKeyName(),
                'ids' => $relatedIds,
            ],
            $pivotTable => [
                'table' => $pivotTable,
                'keyName' => $pivotIdKeyName,
                'ids' => $pivotIds,
            ],
        ];
    }

    private function getRelatedUsedIds(array $ids): array
    {
        $parentPivotKeyName = $this->belongsToMany->getForeignPivotKeyName();
        $relatedPivotKeyName = $this->belongsToMany->getRelatedPivotKeyName();

        return DB::table($this->belongsToMany->getTable())
            ->where($parentPivotKeyName, $ids)
            ->get()
            ->pluck($relatedPivotKeyName)
            ->toArray();
    }

    private function getPivotIdColumnKeyName(bool $checkFalseAutoIncrement = false): string
    {
        $columns = Schema::getColumns($this->belongsToMany->getTable());
        foreach ($columns as $column) {
            if ($column['nullable'] === false && (! $checkFalseAutoIncrement || $column['auto_increment'] === false)) {
                return $column['name'];
            }
        }

        return current($columns)['name'];
    }

    private function getPivotUsedIds(array $ids, string $pivotIdKeyName): array
    {
        $parentPivotKeyName = $this->belongsToMany->getForeignPivotKeyName();

        return DB::table($this->belongsToMany->getTable())
            ->whereIn($parentPivotKeyName, $ids)
            ->get()
            ->pluck($pivotIdKeyName)
            ->toArray();
    }

    public function getModifyInfo(): array
    {
        $model = $this->belongsToMany->getParent();
        $parentTable = $model->getTable();
        $parentKeyName = $model->getKeyName();
        $syncId = $this->syncIdState->tableSyncId($model->getTable());

        $related = $this->belongsToMany->getRelated();
        $relatedTable = $related->getTable();
        $relatedKeyName = $related->getKeyName();
        $uniqueRelatedKeyName = $this->syncIdState->tableSyncId($related->getTable());

        $pivotTable = $this->belongsToMany->getTable();
        $parentPivotKeyName = $this->belongsToMany->getForeignPivotKeyName();
        $relatedPivotKeyName = $this->belongsToMany->getRelatedPivotKeyName();

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

        $foreignParentTableColumn = new ExportModifyForeignColumn(
            tableName: $pivotTable,
            keyName: $parentPivotKeyName,
            foreignTableName: $parentTable,
            foreignUniqueKeyName: $syncId,
            foreignOldKeyName: $parentKeyName,
            nullable: $this->tableService->isNullableColumn($pivotTable, $parentPivotKeyName),
        );

        $foreignRelatedTableColumn = new ExportModifyForeignColumn(
            tableName: $pivotTable,
            keyName: $relatedPivotKeyName,
            foreignTableName: $relatedTable,
            foreignUniqueKeyName: $uniqueRelatedKeyName,
            foreignOldKeyName: $relatedKeyName,
            nullable: $this->tableService->isNullableColumn($pivotTable, $relatedPivotKeyName),
        );

        $result = [
            $parentTable => [
                $parentTableColumn->getKeyName() => $parentTableColumn,
            ],
            $relatedTable => [
                $relatedTableColumn->getKeyName() => $relatedTableColumn,
            ],
            $pivotTable => [
                $foreignParentTableColumn->getKeyName() => $foreignParentTableColumn,
                $foreignRelatedTableColumn->getKeyName() => $foreignRelatedTableColumn,
            ],
        ];

        $pivotIdKeyName = $this->getPivotIdColumnKeyName(true);
        if ($pivotIdKeyName !== $parentPivotKeyName && $pivotIdKeyName !== $relatedPivotKeyName) {
            $uniquePivotKeyName = $this->syncIdState->tableSyncId($pivotTable);

            $pivotTableColumn = new ExportModifySimpleColumn(
                tableName: $pivotTable,
                keyName: $pivotIdKeyName,
                uniqueKeyName: $uniquePivotKeyName,
                nullable: $this->tableService->isNullableColumn($pivotTable, $pivotIdKeyName),
                autoincrement: $this->tableService->isAutoincrementColumn($pivotTable, $pivotIdKeyName),
            );

            $result[$pivotTable][$pivotIdKeyName] = $pivotTableColumn;
        }

        //todo: Рудимент?
        //$pivotIdModifyPrimaryAttributes = ['isPrimaryKey' => true];
        //$result[$pivotTable][$pivotIdKeyName] += $pivotIdModifyPrimaryAttributes;

        return $result;
    }
}
