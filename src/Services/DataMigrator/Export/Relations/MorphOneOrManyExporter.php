<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Export\Relations;

use Cat4year\DataMigrator\Entity\ExportModifyMorphColumn;
use Cat4year\DataMigrator\Entity\ExportModifySimpleColumn;
use Cat4year\DataMigrator\Entity\SyncId;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\ModelService;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\TableService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use ReflectionException;

/**
 * @todo Очень похоже на HasOneOrMany за некоторыми отличиями в modifyInfo
 */
final readonly class MorphOneOrManyExporter implements RelationExporter
{
    public function __construct(
        private MorphOneOrMany $morphOneOrMany,
        private ModelService $modelService,
        private TableService $tableService,
    ) {
    }

    /**
     * @throws BindingResolutionException
     */
    public static function create(MorphOneOrMany $morphOneOrMany): self
    {
        return app()->makeWith(self::class, compact('morphOneOrMany'));
    }

    public function makeExportData(array $foreignIds): array
    {
        $ids = $this->getUsedIds($foreignIds);

        $table = $this->morphOneOrMany->getModel()->getTable();

        return [
            $table => [
                'table' => $table,
                'keyName' => $this->getKeyName(),
                'ids' => $ids,
            ],
        ];
    }

    private function getUsedIds(array $foreignIds): array
    {
        $idKey = $this->morphOneOrMany->getRelated()->getKeyName();
        $foreignKey = $this->morphOneOrMany->getForeignKeyName();
        $morphType = $this->morphOneOrMany->getMorphType();
        $morphTypeValue = $this->morphOneOrMany->getParent()::class;

        return $this->morphOneOrMany->getRelated()::query()
            ->select()
            ->whereNotNull($foreignKey)
            ->where($morphType, $morphTypeValue)
            ->whereIn($foreignKey, $foreignIds)
            ->get()
            ->pluck($idKey)
            ->toArray();
    }

    private function getEntity(): Model
    {
        return $this->morphOneOrMany->getRelated();
    }

    private function getKeyName(): string
    {
        return $this->getEntity()->getKeyName();
    }

    /**
     * @throws ReflectionException
     */
    public function getModifyInfo(): array
    {
        $model = $this->morphOneOrMany->getParent();
        $parentTable = $model->getTable();
        $parentKeyName = $model->getKeyName();
        $uniqueParentKeyName = $this->modelService->identifyUniqueIdColumn($model);

        if ($uniqueParentKeyName === null) {
            // todo: можно решить через конфигуратор что с этим делать: скип, дефолтный keyName, ...?
        }

        $related = $this->morphOneOrMany->getRelated();
        $relatedTable = $related->getTable();
        $uniqueRelatedKeyName = $this->modelService->identifyUniqueIdColumn($related);

        if ($uniqueRelatedKeyName === null) {
            // todo: можно решить через конфигуратор что с этим делать: скип, дефолтный keyName, ...?
        }

        $foreignKeyName = $this->morphOneOrMany->getForeignKeyName();

        $parenSyncKey = new SyncId([$uniqueParentKeyName]);

        $exportModifySimpleColumn = new ExportModifySimpleColumn(
            tableName: $parentTable,
            keyName: $parentKeyName,
            uniqueKeyName: $parenSyncKey,
            nullable: $this->tableService->isNullableColumn($parentTable, $parentKeyName),
            autoincrement: $this->tableService->isAutoincrementColumn($parentTable, $parentKeyName),
        );

        $exportModifyMorphColumn = new ExportModifyMorphColumn(
            morphType: $this->morphOneOrMany->getMorphType(),
            tableName: $relatedTable,
            keyName: $foreignKeyName,
            sourceKeyNames: [$parentTable => $parenSyncKey],
            sourceOldKeyNames: [$parentTable => $parentKeyName],
            nullable: $this->tableService->isNullableColumn($relatedTable, $foreignKeyName),
            autoincrement: $this->tableService->isAutoincrementColumn($relatedTable, $foreignKeyName),
        );

        $result = [
            $parentTable => [
                $exportModifySimpleColumn->getKeyName() => $exportModifySimpleColumn,
            ],
            $relatedTable => [
                $exportModifyMorphColumn->getKeyName() => $exportModifyMorphColumn,
            ],
        ];

        $relatedIdKeyName = $related->getKeyName();
        $relatedUniqueIdKeyName = $this->modelService->identifyUniqueIdColumn($related);
        $relatedSyncKey = new SyncId([$relatedUniqueIdKeyName]);
        if ($relatedIdKeyName !== $foreignKeyName) {
            $result[$relatedTable][$relatedIdKeyName] = new ExportModifySimpleColumn(
                tableName: $relatedTable,
                keyName: $relatedIdKeyName,
                uniqueKeyName: $relatedSyncKey,
                nullable: $this->tableService->isNullableColumn($relatedTable, $relatedIdKeyName),
                autoincrement: $this->tableService->isAutoincrementColumn($relatedTable, $relatedIdKeyName),
            );
        }

        return $result;
    }
}
