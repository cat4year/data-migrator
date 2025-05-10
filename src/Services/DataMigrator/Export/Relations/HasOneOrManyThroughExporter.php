<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Export\Relations;

use Cat4year\DataMigrator\Services\DataMigrator\Tools\ModelService;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\TableService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrManyThrough;
use ReflectionException;
use ReflectionProperty;

final readonly class HasOneOrManyThroughExporter implements RelationExporter
{
    public function __construct(
        private HasOneOrManyThrough $relation,
        private ModelService $modelService,
        private TableService $tableRepository,
    ) {
    }

    /**
     * @throws BindingResolutionException
     */
    public static function create(HasOneOrManyThrough $relation): self
    {
        return app()->makeWith(self::class, ['relation' => $relation]);
    }

    public function makeExportData(array $foreignIds): array
    {
        $parent = $this->relation->getParent();
        $parentTable = $parent->getTable();
        $parentKeyName = $parent->getKeyName();
        $parentForeignKey = $this->relation->getFirstKeyName();
        $parentIds = $this->getUsedIdsByModel($parent, $foreignIds, $parentForeignKey);

        $related = $this->relation->getRelated();
        $relatedTable = $related->getTable();
        $relatedKeyName = $related->getKeyName();
        $relatedForeignKey = $this->relation->getForeignKeyName();
        $relatedIds = $this->getUsedIdsByModel($related, $parentIds, $relatedForeignKey);

        return [
            $parentTable => [
                'table' => $parentTable,
                'keyName' => $parentKeyName,
                'ids' => $parentIds,
            ],
            $relatedTable => [
                'table' => $relatedTable,
                'keyName' => $relatedKeyName,
                'ids' => $relatedIds,
            ],
        ];
    }

    private function getUsedIdsByModel(Model $model, array $foreignIds, string $foreignKey): array
    {
        $idKey = $model->getKeyName();

        return $model::query()
            ->select()
            ->whereNotNull($foreignKey)
            ->whereIn($foreignKey, $foreignIds)
            ->get()
            ->pluck($idKey)
            ->toArray();
    }

    /**
     * @throws ReflectionException
     */
    private function getEntity(): Model
    {
        try {
            $reflectionFarParentProperty = new ReflectionProperty($this->relation::class, 'farParent');

            /** @var Model $farParent */
            return $reflectionFarParentProperty->getValue($this->relation);
        } catch (ReflectionException) {
            throw new ReflectionException(sprintf(
                '%s проблема с получением farParent свойства',
                $this->relation::class
            ));
        }
    }

    /**
     * @throws ReflectionException
     */
    public function getModifyInfo(): array
    {
        $farParent = $this->getEntity();
        $farParentTable = $farParent->getTable();
        $farParentKeyName = $farParent->getKeyName();
        $farParentUniqueKeyName = $this->modelService->identifyUniqueIdColumn($farParent);

        if ($farParentUniqueKeyName === null) {
            // todo: можно решить через конфигуратор что с этим делать: скип, дефолтный keyName, ...?
        }

        $parent = $this->relation->getParent();
        $parentTable = $parent->getTable();
        $parentKeyName = $parent->getKeyName();
        $parentUniqueKeyName = $this->modelService->identifyUniqueIdColumn($parent);

        if ($parentUniqueKeyName === null) {
            // todo: можно решить через конфигуратор что с этим делать: скип, дефолтный keyName, ...?
        }

        $related = $this->relation->getRelated();
        $relatedTable = $related->getTable();
        $relatedKeyName = $related->getKeyName();
        $relatedUniqueKeyName = $this->modelService->identifyUniqueIdColumn($related);

        if ($relatedUniqueKeyName === null) {
            // todo: можно решить через конфигуратор что с этим делать: скип, дефолтный keyName, ...?
        }

        $parentForeignKeyName = $this->relation->getFirstKeyName();
        $relatedForeignKeyName = $this->relation->getForeignKeyName();

        return [
            $farParentTable => [
                $farParentKeyName => [
                    'table' => $farParentTable,
                    'oldKeyName' => $farParentKeyName,
                    'keyName' => $farParentUniqueKeyName,
                    'isPrimaryKey' => true,
                    'autoIncrement' => $this->tableRepository->isAutoincrementColumn(
                        $farParentTable,
                        $farParentKeyName
                    ),
                    'nullable' => $this->tableRepository->isNullableColumn(
                        $farParentTable,
                        $farParentKeyName
                    ),
                ],
            ],
            $parentTable => [
                $parentKeyName => [
                    'table' => $parentTable,
                    'oldKeyName' => $parentKeyName,
                    'keyName' => $parentUniqueKeyName,
                    'isPrimaryKey' => true,
                    'autoIncrement' => $this->tableRepository->isAutoincrementColumn(
                        $parentTable,
                        $parentKeyName
                    ),
                    'nullable' => $this->tableRepository->isNullableColumn(
                        $parentTable,
                        $parentKeyName
                    ),
                ],
                $parentForeignKeyName => [
                    'table' => $farParentTable,
                    'oldKeyName' => $farParentKeyName,
                    'keyName' => $farParentUniqueKeyName,
                    'nullable' => $this->tableRepository->isNullableColumn(
                        $parentTable,
                        $parentForeignKeyName
                    ),
                ],
            ],
            $relatedTable => [
                $relatedKeyName => [
                    'table' => $relatedTable,
                    'oldKeyName' => $relatedKeyName,
                    'keyName' => $relatedUniqueKeyName,
                    'isPrimaryKey' => true,
                    'autoIncrement' => $this->tableRepository->isAutoincrementColumn(
                        $relatedTable,
                        $relatedKeyName
                    ),
                    'nullable' => $this->tableRepository->isNullableColumn(
                        $relatedTable,
                        $relatedKeyName
                    ),
                ],
                $relatedForeignKeyName => [
                    'table' => $parentTable,
                    'oldKeyName' => $parentKeyName,
                    'keyName' => $parentUniqueKeyName,
                    'nullable' => $this->tableRepository->isNullableColumn(
                        $relatedTable,
                        $relatedForeignKeyName
                    ),
                ],
            ],
        ];
    }
}
