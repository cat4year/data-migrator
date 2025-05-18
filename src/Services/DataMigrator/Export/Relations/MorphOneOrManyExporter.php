<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Export\Relations;

use Cat4year\DataMigrator\Entity\ExportModifyMorphColumn;
use Cat4year\DataMigrator\Entity\ExportModifySimpleColumn;
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
        private MorphOneOrMany $relation,
        private ModelService $modelService,
        private TableService $tableRepository,
    ) {
    }

    /**
     * @throws BindingResolutionException
     */
    public static function create(MorphOneOrMany $relation): self
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

    private function getUsedIds(array $foreignIds): array
    {
        $idKey = $this->relation->getRelated()->getKeyName();
        $foreignKey = $this->relation->getForeignKeyName();
        $morphType = $this->relation->getMorphType();
        $morphTypeValue = $this->relation->getParent()::class;

        return $this->relation->getRelated()::query()
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
        return $this->relation->getRelated();
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
        $parent = $this->relation->getParent();
        $parentTable = $parent->getTable();
        $parentKeyName = $parent->getKeyName();
        $uniqueParentKeyName = $this->modelService->identifyUniqueIdColumn($parent);

        if ($uniqueParentKeyName === null) {
            // todo: можно решить через конфигуратор что с этим делать: скип, дефолтный keyName, ...?
        }

        $related = $this->relation->getRelated();
        $relatedTable = $related->getTable();
        $uniqueRelatedKeyName = $this->modelService->identifyUniqueIdColumn($related);

        if ($uniqueRelatedKeyName === null) {
            // todo: можно решить через конфигуратор что с этим делать: скип, дефолтный keyName, ...?
        }

        $foreignKeyName = $this->relation->getForeignKeyName();

        $parentTableColumn = new ExportModifySimpleColumn(
            tableName: $parentTable,
            keyName: $parentKeyName,
            uniqueKeyName: $uniqueParentKeyName,
            nullable: $this->tableRepository->isNullableColumn($parentTable, $parentKeyName),
            autoincrement: $this->tableRepository->isAutoincrementColumn($parentTable, $parentKeyName),
        );

        $relatedTableForeignColumn = new ExportModifyMorphColumn(
            morphType: $this->relation->getMorphType(),
            tableName: $relatedTable,
            keyName: $foreignKeyName,
            sourceKeyNames: [$parentTable => $uniqueParentKeyName],
            sourceOldKeyNames: [$parentTable => $parentKeyName],
            nullable: $this->tableRepository->isNullableColumn($relatedTable, $foreignKeyName),
            autoincrement: $this->tableRepository->isAutoincrementColumn($relatedTable, $foreignKeyName),
        );

        $result = [
            $parentTable => [
                $parentTableColumn->getKeyName() => $parentTableColumn,
            ],
            $relatedTable => [
                $relatedTableForeignColumn->getKeyName() => $relatedTableForeignColumn,
            ],
        ];

        $relatedIdKeyName = $related->getKeyName();
        $relatedUniqueIdKeyName = $this->modelService->identifyUniqueIdColumn($related);
        if ($relatedIdKeyName !== $foreignKeyName) {
            $result[$relatedTable][$relatedIdKeyName] = new ExportModifySimpleColumn(
                tableName: $relatedTable,
                keyName: $relatedIdKeyName,
                uniqueKeyName: $relatedUniqueIdKeyName,
                nullable: $this->tableRepository->isNullableColumn($relatedTable, $relatedIdKeyName),
                autoincrement: $this->tableRepository->isAutoincrementColumn($relatedTable, $relatedIdKeyName),
            );
        }

        return $result;
    }
}
