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
        private BelongsTo $relation,
        private TableService $tableRepository,
        private SyncIdState $syncIdState,
    ) {}

    /**
     * @throws BindingResolutionException
     */
    public static function create(BelongsTo $relation): self
    {
        return app()->makeWith(self::class, compact('relation'));
    }

    public function makeExportData(array $foreignIds): array
    {
        $ids = $this->getUsedIds($foreignIds);

        $table = $this->relation->getModel()->getTable();

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
        $idKey = $this->relation->getParent()->getKeyName();

        $foreignKey = $this->relation->getForeignKeyName();

        return $this->relation->getParent()::query()
            ->select()
            ->whereNotNull($foreignKey)
            ->whereIn($idKey, $ids)
            ->get()
            ->pluck($foreignKey)
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
        $parentTable = $parent->getTable();
        $parentKeyName = $parent->getKeyName();
        $uniqueParentKeyName = $this->syncIdState->tableSyncId($parent->getTable());

        $related = $this->relation->getRelated();
        $relatedTable = $related->getTable();
        $relatedKeyName = $related->getKeyName();
        $uniqueRelatedKeyName =  $this->syncIdState->tableSyncId($related->getTable());
        $oldForeignKeyName = $this->relation->getOwnerKeyName();
        $foreignKeyName = $this->relation->getForeignKeyName();

        $parentTableForeignColumn = new ExportModifyForeignColumn(
            tableName: $parentTable,
            keyName: $foreignKeyName,
            foreignTableName: $relatedTable,
            foreignUniqueKeyName: $uniqueRelatedKeyName,
            foreignOldKeyName: $oldForeignKeyName,
            nullable: $this->tableRepository->isNullableColumn($parentTable, $foreignKeyName),
        );

        $parentTableColumn = new ExportModifySimpleColumn(
            tableName: $parentTable,
            keyName: $parentKeyName,
            uniqueKeyName: $uniqueParentKeyName,
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

        return [
            $parentTable => [
                $parentTableForeignColumn->getKeyName() => $parentTableForeignColumn,
                $parentTableColumn->getKeyName() => $parentTableColumn,
            ],
            $relatedTable => [
                $relatedTableColumn->getKeyName() => $relatedTableColumn,
            ],
        ];
    }
}
