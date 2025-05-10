<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Export\Relations;

use Cat4year\DataMigrator\Services\DataMigrator\Export\ExportConfigurator;
use Cat4year\DataMigrator\Services\DataMigrator\Export\ExporterState;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\CollectionMerger;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\TableService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Throwable;

final readonly class RelationsExporter
{
    public function __construct(
        private ExportConfigurator $configurator,
        private ExporterState $state,
        private RelationFactory $factory,
        private CollectionMerger $collectionMerger,
        private TableService $tableRepository,
        private SupportCollection $handledEntityIdsByTable,
    ) {
    }

    /**
     * @throws BindingResolutionException
     */
    public static function create(ExportConfigurator $configurator): self
    {
        return app()->makeWith(self::class, compact('configurator'));
    }

    public function collectRelations(string $entityTable, array $ids = [], int $lvl = 1): ExporterState
    {
        if (empty($ids) || $lvl > $this->configurator->getMaxRelationDepth()) {
            return $this->state;
        }

        $this->collectionMerger->putWithMerge(
            $this->state->entityIds,
            $entityTable,
            $ids,
            true
        );
        $this->collectionMerger->putWithMerge(
            $this->handledEntityIdsByTable,
            $entityTable,
            $ids,
            true
        );

        $entityModel = $this->tableRepository->identifyModelByTable($entityTable);

        if ($entityModel === null) {
            return $this->state;
        }

        $entityRelations = $this->collectRelationByEntity($entityModel::class, $ids);

        $nextLvl = $lvl + 1;
        foreach ($entityRelations as $relationEntityData) {
            $relationEntityTable = $relationEntityData['table'];
            $relationEntityIds = $relationEntityData['ids'];

            if (
                $this->handledEntityIdsByTable->has($relationEntityTable)
                && empty(array_diff($relationEntityIds, $this->handledEntityIdsByTable->get($relationEntityTable)))
            ) {
                continue;
            }

            $this->collectRelations($relationEntityTable, $relationEntityIds, $nextLvl);
        }

        return $this->state;
    }

    /**
     * @throws ReflectionException
     * @throws BindingResolutionException
     */
    private function collectRelationByEntity(string $entityClass, array $entityIds = []): array
    {
        $relations = $this->getRelations($entityClass);
        $relationEntities = [];
        foreach ($relations as $relationName => $relationType) {
            $entityModel = app($entityClass);
            assert($entityModel instanceof Model);
            $relation = $entityModel->$relationName();
            assert($relation instanceof Relation);

            $relationExporter = $this->factory->createByRelation($relation);

            if ($relationExporter === null) {
                continue;
            }

            try {
                $relationEntitiesConcreteRelation = $relationExporter->makeExportData($entityIds);
            } catch (ReflectionException) {
                continue;
            }

            // check: не помню как планировал использовать. надо вернуть в основной поток? Нужен ли хэш в виде ключа?
            if ($this->state->relationsInfo->has($entityClass)) {
                $stateRelationsByEntity = $this->state->relationsInfo->get($entityClass);
            }
            $stateRelationsByEntity[$relationName] = $relation;
            $this->state->relationsInfo->put($entityClass, $stateRelationsByEntity);

            $relationEntities = $this->relationEntitiesMerge($relationEntities, $relationEntitiesConcreteRelation);
        }

        $relationEntities = array_map(static function ($item) {
            $item['ids'] = array_unique($item['ids']);

            return $item;
        }, $relationEntities);

        return $relationEntities;
    }

    /**
     * @phpstan-template TModel of Model
     *
     * @param class-string<TModel> $class
     *
     * @throws ReflectionException
     */
    private function getRelations(string $class): array
    {
        $reflect = new ReflectionClass($class);
        $relations = [];

        foreach ($reflect->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            /** @var ReflectionMethod $method */
            if (
                $method->hasReturnType()
                && in_array((string) $method->getReturnType(), $this->configurator->getSupportedRelations(), true)
            ) {
                $relations[$method->getName()] = (string) $method->getReturnType();
            }
        }

        return $relations;
    }

    /**
     * todo: сделать элегантнее
     *
     * @param list<array{keyName: string, table: string, ids: list<non-negative-int>}> $relationEntities
     * @param list<array{keyName: string, table: string, ids: list<non-negative-int>}> $currentRelationEntities
     * @return list<array{keyName: string, table: string, ids: list<non-negative-int>}>
     */
    private function relationEntitiesMerge(array $relationEntities, array $currentRelationEntities): array
    {
        foreach ($currentRelationEntities as $tableName => $relationEntityData) {
            if (! isset($relationEntities[$tableName])) {
                $relationEntities[$tableName] = $relationEntityData;

                continue;
            }

            // todo: is correct merge ids? maybe uniq?
            $relationEntities[$tableName]['ids'] = [...$relationEntities[$tableName]['ids'], ...$relationEntityData['ids']];
        }

        return $relationEntities;
    }

    public function modify(SupportCollection $entitiesCollections, SupportCollection $relationsInfo): array
    {
        $entitiesModifyInfo = [];
        $entityClasses = $relationsInfo;

        /** может быть избыточно если какаие-то связи отвалились */
        foreach ($entityClasses as $entityTable => $relationsByEntity) {
            // получить данные слагов для всех связанных моделей из $entitiesCollections
            foreach ($relationsByEntity as $relationName => $relation) {
                assert($relation instanceof Relation);

                $relationModifier = $this->factory->createByRelation($relation, $entitiesCollections);

                if ($relationModifier === null) {
                    continue;
                }

                $entitiesModifyInfo[$entityTable.'|'.$relationName] = $relationModifier->getModifyInfo();
            }
        }

        $entitiesModifyInfoResult = [];
        foreach ($entitiesModifyInfo as $modifyItemsForRelationName) {
            foreach ($modifyItemsForRelationName as $table => $modifyInfo) {
                foreach ($modifyInfo as $attributeKeyName => $modifyInfoForKey) {
                    if (isset($modifyInfoForKey['table'])) {
                        $entitiesModifyInfoResult[$table][$attributeKeyName] = $modifyInfoForKey;

                        continue;
                    }

                    // морф связь. объединяем данные ключей для разных типов
                    if (! isset($entitiesModifyInfoResult[$table][$attributeKeyName])) {
                        $entitiesModifyInfoResult[$table][$attributeKeyName] = $modifyInfoForKey;
                    } else {
                        $entitiesModifyInfoResult[$table][$attributeKeyName]['oldKeyNames'] += $modifyInfoForKey['oldKeyNames'];
                        $entitiesModifyInfoResult[$table][$attributeKeyName]['keyNames'] += $modifyInfoForKey['keyNames'];
                    }
                }
            }
        }
        $entitiesModifyInfo = $entitiesModifyInfoResult; // todo

        // подмена id
        $entitiesCollections = $entitiesCollections->toArray();
        $modifiedResult = [];
        foreach ($entitiesCollections as $tableName => $entityInfo) {
            foreach ($entityInfo['items'] as $itemKey => $attributes) {
                $modifiedResult[$tableName]['items'][$itemKey] = $attributes;
                foreach ($attributes as $attributeKeyName => $attributeValue) {
                    if (
                        ! array_key_exists($attributeKeyName, $entitiesModifyInfo[$tableName])
                        || $attributeValue === null
                    ) {
                        continue;
                    }

                    // заменяем значение каждого атрибута на уникальный строковый ключ
                    $modifyInfoByKey = $entitiesModifyInfo[$tableName][$attributeKeyName];

                    if (isset($modifyInfoByKey['morphType'])) {
                        $morphType = $modifyInfoByKey['morphType'];
                        $morphClass = $attributes[$morphType];
                        $modifyInfoTable = app($morphClass)->getTable();
                        $modifyKeyName = $modifyInfoByKey['keyNames'][$modifyInfoTable];
                    } else {
                        $modifyInfoTable = $modifyInfoByKey['table'];
                        $modifyKeyName = $modifyInfoByKey['keyName'];
                    }

                    try {
                        $tableForFindNewAttributeValue = $entitiesCollections[$modifyInfoTable];
                        $newValue = $tableForFindNewAttributeValue['items'][$attributeValue][$modifyKeyName];
                    } catch (Throwable) {
                        Log::error('Ошибка при подмене id', [
                            $entitiesCollections,
                            $modifyInfoTable,
                            $tableForFindNewAttributeValue,
                            $attributeValue,
                            $modifyKeyName,
                        ]);
                    }

                    $modifiedResult[$tableName]['items'][$itemKey][$attributeKeyName] = $newValue; // todo: variable not found
                }
            }
            $modifiedResult[$tableName]['modifiedAttributes'] = $entitiesModifyInfo[$tableName];
        }

        return $modifiedResult;
    }
}
