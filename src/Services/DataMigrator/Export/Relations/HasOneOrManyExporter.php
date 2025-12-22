<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Export\Relations;

use Cat4year\DataMigrator\Entity\ExportModifySimpleColumn;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\SyncIdState;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\TableService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;

final readonly class HasOneOrManyExporter implements RelationExporter
{
    public function __construct(
        private HasOneOrMany $hasOneOrMany,
        private TableService $tableService,
        private SyncIdState $syncIdState,
    ) {
    }

    /**
     * @throws BindingResolutionException
     */
    public static function create(HasOneOrMany $hasOneOrMany): self
    {
        return app()->makeWith(self::class, ['hasOneOrMany' => $hasOneOrMany]);
    }

    public function makeExportData(array $foreignIds): array
    {
        $ids = $this->getUsedIds($foreignIds);

        $tableName = $this->hasOneOrMany->getParent()->getTable();
        $keyName = $this->hasOneOrMany->getParent()->getKeyName();
        $relatedTable = $this->hasOneOrMany->getRelated()->getTable();
        $relatedKeyName = $this->hasOneOrMany->getRelated()->getKeyName();

        return [
            $tableName => [
                'table' => $tableName,
                'keyName' => $keyName,
                'ids' => $foreignIds,
            ],
            $relatedTable => [
                'table' => $relatedTable,
                'keyName' => $relatedKeyName,
                'ids' => $ids,
            ],
        ];
    }

    private function getUsedIds(array $foreignIds): array
    {
        $idKey = $this->hasOneOrMany->getRelated()->getKeyName();
        $foreignKey = $this->hasOneOrMany->getForeignKeyName();

        return $this->hasOneOrMany->getRelated()::query()
            ->select()
            ->whereNotNull($foreignKey)
            ->whereIn($foreignKey, $foreignIds)
            ->get()
            ->pluck($idKey)
            ->toArray();
    }

    public function getModifyInfo(): array
    {
        $model = $this->hasOneOrMany->getParent();
        $parentTable = $model->getTable();
        $parentKeyName = $model->getKeyName();
        $syncId = $this->syncIdState->tableSyncId($model->getTable());

        $related = $this->hasOneOrMany->getRelated();
        $relatedTable = $related->getTable();
        $relatedKeyName = $related->getKeyName();
        $uniqueRelatedKeyName = $this->syncIdState->tableSyncId($related->getTable());

        $parentTableColumn =  new ExportModifySimpleColumn(
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
                $parentTableColumn->getKeyName() => $parentTableColumn,
            ],
            $relatedTable => [
                $relatedTableColumn->getKeyName() => $relatedTableColumn,
            ]
        ];
    }
}
