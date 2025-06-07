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
        private HasOneOrManyThrough $hasOneOrManyThrough,
        private ModelService $modelService,
        private TableService $tableService,
    ) {
    }

    /**
     * @throws BindingResolutionException
     */
    public static function create(HasOneOrManyThrough $hasOneOrManyThrough): self
    {
        return app()->makeWith(self::class, ['relation' => $hasOneOrManyThrough]);
    }

    public function makeExportData(array $foreignIds): array
    {
        $model = $this->hasOneOrManyThrough->getParent();
        $parentTable = $model->getTable();
        $parentKeyName = $model->getKeyName();
        $parentForeignKey = $this->hasOneOrManyThrough->getFirstKeyName();
        $parentIds = $this->getUsedIdsByModel($model, $foreignIds, $parentForeignKey);

        $related = $this->hasOneOrManyThrough->getRelated();
        $relatedTable = $related->getTable();
        $relatedKeyName = $related->getKeyName();
        $relatedForeignKey = $this->hasOneOrManyThrough->getForeignKeyName();
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
            $reflectionProperty = new ReflectionProperty($this->hasOneOrManyThrough::class, 'farParent');

            return $reflectionProperty->getValue($this->hasOneOrManyThrough);
        } catch (ReflectionException) {
            throw new ReflectionException(sprintf(
                '%s проблема с получением farParent свойства',
                $this->hasOneOrManyThrough::class
            ));
        }
    }

    /**
     * @throws ReflectionException
     */
    public function getModifyInfo(): array
    {
        $model = $this->getEntity();
        $farParentTable = $model->getTable();
        $farParentKeyName = $model->getKeyName();
        $farParentUniqueKeyName = $this->modelService->identifyUniqueIdColumn($model);

        if ($farParentUniqueKeyName === null) {
            // todo: можно решить через конфигуратор что с этим делать: скип, дефолтный keyName, ...?
        }

        $parent = $this->hasOneOrManyThrough->getParent();
        $parentTable = $parent->getTable();
        $parentKeyName = $parent->getKeyName();
        $parentUniqueKeyName = $this->modelService->identifyUniqueIdColumn($parent);

        if ($parentUniqueKeyName === null) {
            // todo: можно решить через конфигуратор что с этим делать: скип, дефолтный keyName, ...?
        }

        $related = $this->hasOneOrManyThrough->getRelated();
        $relatedTable = $related->getTable();
        $relatedKeyName = $related->getKeyName();
        $relatedUniqueKeyName = $this->modelService->identifyUniqueIdColumn($related);

        if ($relatedUniqueKeyName === null) {
            // todo: можно решить через конфигуратор что с этим делать: скип, дефолтный keyName, ...?
        }

        $parentForeignKeyName = $this->hasOneOrManyThrough->getFirstKeyName();
        $relatedForeignKeyName = $this->hasOneOrManyThrough->getForeignKeyName();

        return [
            $farParentTable => [
                $farParentKeyName => [
                    'table' => $farParentTable,
                    'oldKeyName' => $farParentKeyName,
                    'keyName' => $farParentUniqueKeyName,
                    'isPrimaryKey' => true,
                    'autoIncrement' => $this->tableService->isAutoincrementColumn(
                        $farParentTable,
                        $farParentKeyName
                    ),
                    'nullable' => $this->tableService->isNullableColumn(
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
                    'autoIncrement' => $this->tableService->isAutoincrementColumn(
                        $parentTable,
                        $parentKeyName
                    ),
                    'nullable' => $this->tableService->isNullableColumn(
                        $parentTable,
                        $parentKeyName
                    ),
                ],
                $parentForeignKeyName => [
                    'table' => $farParentTable,
                    'oldKeyName' => $farParentKeyName,
                    'keyName' => $farParentUniqueKeyName,
                    'nullable' => $this->tableService->isNullableColumn(
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
                    'autoIncrement' => $this->tableService->isAutoincrementColumn(
                        $relatedTable,
                        $relatedKeyName
                    ),
                    'nullable' => $this->tableService->isNullableColumn(
                        $relatedTable,
                        $relatedKeyName
                    ),
                ],
                $relatedForeignKeyName => [
                    'table' => $parentTable,
                    'oldKeyName' => $parentKeyName,
                    'keyName' => $parentUniqueKeyName,
                    'nullable' => $this->tableService->isNullableColumn(
                        $relatedTable,
                        $relatedForeignKeyName
                    ),
                ],
            ],
        ];
    }
}
