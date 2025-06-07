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
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

final readonly class RelationsExporter
{
    public function __construct(
        private ExportConfigurator $configurator,
        private ExporterState $exporterState,
        private RelationFactory $relationFactory,
        private CollectionMerger $collectionMerger,
        private TableService $tableService,
        private SupportCollection $supportCollection,
    ) {
    }

    /**
     * @throws BindingResolutionException
     */
    public static function create(ExportConfigurator $configurator): self
    {
        return app()->makeWith(self::class, ['configurator' => $configurator]);
    }

    public function collectRelations(string $entityTable, array $ids = [], int $lvl = 1): ExporterState
    {
        if ($ids === [] || $lvl > $this->configurator->getMaxRelationDepth()) {
            return $this->exporterState;
        }

        $this->collectionMerger->putWithMerge(
            $this->exporterState->entityIds,
            $entityTable,
            $ids,
            true
        );
        $this->collectionMerger->putWithMerge(
            $this->supportCollection,
            $entityTable,
            $ids,
            true
        );

        $entityModel = $this->tableService->identifyModelByTable($entityTable);

        if (! $entityModel instanceof Model) {
            return $this->exporterState;
        }

        $entityRelations = $this->collectRelationByEntity($entityModel::class, $ids);

        $nextLvl = $lvl + 1;
        foreach ($entityRelations as $entityRelation) {
            $relationEntityTable = $entityRelation['table'];
            $relationEntityIds = $entityRelation['ids'];

            if (
                $this->supportCollection->has($relationEntityTable)
                && array_diff($relationEntityIds, $this->supportCollection->get($relationEntityTable)) === []
            ) {
                continue;
            }

            $this->collectRelations($relationEntityTable, $relationEntityIds, $nextLvl);
        }

        return $this->exporterState;
    }

    /**
     * @throws ReflectionException
     * @throws BindingResolutionException
     */
    private function collectRelationByEntity(string $entityClass, array $entityIds = []): array
    {
        $relations = $this->getRelations($entityClass);
        $relationEntities = [];
        foreach (array_keys($relations) as $relationName) {
            $entityModel = app($entityClass);
            assert($entityModel instanceof Model);
            $relation = $entityModel->$relationName();
            assert($relation instanceof Relation);

            $relationExporter = $this->relationFactory->createByRelation($relation);

            if (! $relationExporter instanceof RelationExporter) {
                continue;
            }

            try {
                $relationEntitiesConcreteRelation = $relationExporter->makeExportData($entityIds);
            } catch (ReflectionException) {
                continue;
            }

            // check: не помню как планировал использовать. надо вернуть в основной поток? Нужен ли хэш в виде ключа?
            if ($this->exporterState->relationsInfo->has($entityClass)) {
                $stateRelationsByEntity = $this->exporterState->relationsInfo->get($entityClass);
            }

            $stateRelationsByEntity[$relationName] = $relation;
            $this->exporterState->relationsInfo->put($entityClass, $stateRelationsByEntity);

            $relationEntities = $this->relationEntitiesMerge($relationEntities, $relationEntitiesConcreteRelation);
        }

        return array_map(static function (array $item): array {
            $item['ids'] = array_unique($item['ids']);

            return $item;
        }, $relationEntities);
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
        $reflectionClass = new ReflectionClass($class);
        $relations = [];

        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
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
}
