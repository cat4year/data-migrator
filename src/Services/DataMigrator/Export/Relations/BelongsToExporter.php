<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Export\Relations;

use Cat4year\DataMigrator\Entity\ExportModifyForeignColumn;
use Cat4year\DataMigrator\Entity\ExportModifySimpleColumn;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\ModelService;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\SyncIdState;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\TableService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final readonly class BelongsToExporter implements RelationExporter
{
    public function __construct(
        private BelongsTo $belongsTo,
        private TableService $tableService,
        private SyncIdState $syncIdState,
    ) {}

    /**
     * @throws BindingResolutionException
     */
    public static function create(BelongsTo $belongsTo): self
    {
        return app()->makeWith(self::class, compact('belongsTo'));
    }

    public function makeExportData(array $foreignIds): array
    {
        $ids = $this->getUsedIds($foreignIds);

        $table = $this->belongsTo->getModel()->getTable();

        return [
            $table => [
                'table' => $table,
                'keyName' => $this->getKeyName(),
                'ids' => $ids,
            ],
        ];
    }

    private function getUsedIds(array $ids): array
    {
        $idKey = $this->belongsTo->getParent()->getKeyName();

        $foreignKey = $this->belongsTo->getForeignKeyName();

        return $this->belongsTo->getParent()::query()
            ->select()
            ->whereNotNull($foreignKey)
            ->whereIn($idKey, $ids)
            ->get()
            ->pluck($foreignKey)
            ->toArray();
    }

    private function getEntity(): Model
    {
        return $this->belongsTo->getRelated();
    }

    private function getKeyName(): string
    {
        return $this->getEntity()->getKeyName();
    }

    public function getModifyInfo(): array
    {
        $model = $this->belongsTo->getParent();
        $parentTable = $model->getTable();
        $parentKeyName = $model->getKeyName();
        $syncId = $this->syncIdState->tableSyncId($model->getTable());

        $related = $this->belongsTo->getRelated();
        $relatedTable = $related->getTable();
        $relatedKeyName = $related->getKeyName();
        $uniqueRelatedKeyName =  $this->syncIdState->tableSyncId($related->getTable());
        $oldForeignKeyName = $this->belongsTo->getOwnerKeyName();
        $foreignKeyName = $this->belongsTo->getForeignKeyName();

        $exportModifyForeignColumn = new ExportModifyForeignColumn(
            tableName: $parentTable,
            keyName: $foreignKeyName,
            foreignTableName: $relatedTable,
            foreignUniqueKeyName: $uniqueRelatedKeyName,
            foreignOldKeyName: $oldForeignKeyName,
            nullable: $this->tableService->isNullableColumn($parentTable, $foreignKeyName),
        );

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

        return [
            $parentTable => [
                $exportModifyForeignColumn->getKeyName() => $exportModifyForeignColumn,
                $parentTableColumn->getKeyName() => $parentTableColumn,
            ],
            $relatedTable => [
                $relatedTableColumn->getKeyName() => $relatedTableColumn,
            ],
        ];
    }
}
