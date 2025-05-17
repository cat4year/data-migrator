<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Export;

use Cat4year\DataMigrator\Services\DataMigrator\Export\Relations\RelationsExporter;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\TableService;
use DB;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use stdClass;

final readonly class Exporter
{
    public function __construct(
        private Model $entity,
        private ExportConfigurator $configurator,
        private RelationsExporter $relationManager,
        private ExportSorter $sorter,
        private TableService $tableRepository,
    ) {
    }

    /**
     * @template TModel of Model
     *
     * @param Model|class-string<TModel>
     *
     * @throws BindingResolutionException
     */
    public static function create(Model|string $entity, ?ExportConfigurator $configurator = null): self
    {
        if ($configurator === null) {
            $configurator = ExportConfigurator::create();
        }

        if (is_string($entity) && ! class_exists($entity)) {
            throw new InvalidArgumentException("Entity class '$entity' does not exist");
        }

        $params = [
            'entity' => is_string($entity) ? app($entity) : $entity,
            'configurator' => $configurator,
            'relationManager' => RelationsExporter::create($configurator),
        ];

        return app()->makeWith(self::class, $params);
    }

    /**
     * @throws JsonException
     */
    public function export(): string
    {
        $exportData = $this->exportData();

        return $this->save($exportData);
    }

    /**
     * @throws JsonException
     */
    public function exportData(): array
    {
        if (empty($this->configurator->getIds())) {
            $idKey = $this->entity->getKeyName();
            $ids = $this->entity::query()
                ->select($idKey)
                ->pluck($idKey)
                ->toArray();
        } else {
            $ids = $this->configurator->getIds();
        }

        if (empty($ids)) {
            throw new RuntimeException('Empty ids for export entity');
        }

        $exportData = $this->makeEntityData($ids);
        if (empty($exportData)) {
            throw new RuntimeException('Export items not found');
        }

        return $exportData;
    }

    public function save(array $exportData): string
    {
        $migrationPath = $this->configurator->makeSourceFullPath();
        $this->configurator->getSourceFormat()->save($exportData, $migrationPath);

        return $migrationPath;
    }

    /**
     * check: может вынести куда-нибудь? Или в интерфейсе не указывать для пользователя конечного
     *
     * @param list<non-negative-int|non-empty-string> $ids
     */
    public function makeEntityData(array $ids): array
    {
        $mainEntityResult = $this->makeItems($this->entity->getTable(), $ids, $this->entity->getKeyName());

        if (empty($mainEntityResult)) {
            return [];
        }

        if (! $this->configurator->withRelations()) {
            $resultMainData = [
                'table' => $this->entity->getTable(),
                'items' => $mainEntityResult,
            ];

            return [$this->entity->getTable() => $resultMainData];
        }

        $state = $this->relationManager->collectRelations($this->entity->getTable(), $ids); // todo: перекрывает result

        /** @var string $entityTable */
        foreach ($state->entityIds as $entityTable => $entityIds) {
            // есть проблема дублирования получения записей основной модели. Критично ли?
            $keyName = $this->tableRepository->identifyPrimaryKeyNameByTable($entityTable);

            if ($keyName === null) {
                continue;
            }

            $entityItems = $this->makeItems($entityTable, $entityIds, $keyName);

            if (empty($entityItems)) {
                continue;
            }

            $resultDataForTable = [
                'table' => $entityTable,
                'items' => $entityItems,
            ];

            $state->result->put($entityTable, $resultDataForTable);
        }

        $exportModifier = app()->makeWith(ExportModifier::class, [
            'entitiesCollections' => $state->result,
            'entityClasses' => $state->relationsInfo,
        ]);

        $result = $exportModifier->modify();
        return $this->sorter->sort($result);
    }

    /**
     * @param list<non-negative-int|non-empty-string> $ids
     * @return list<array<string, mixed>>
     */
    private function makeItems(string $table, array $ids, string $idKey = 'id', bool $emptyIsAll = false): array
    {
        if (empty($ids) && ! $emptyIsAll) {
            return [];
        }

        $collection = DB::table($table)
            ->when(! empty($ids), static fn ($q) => $q->whereIn($idKey, $ids))
            ->get()
            ->keyBy($idKey);

        return $this->dataToArray($collection);
    }

    public function dataToArray(SupportCollection $collection, bool $safeKeyName = true): array
    {
        return $collection->map(static function (Model|stdClass $model) use ($safeKeyName) {
            if ($model instanceof stdClass) {
                return (array) $model;
            }

            if ($model->totallyGuarded()) {
                return null;
            }

            $attributes = $model->attributesToArray();

            if (! empty($model->getGuarded()) && $model->getGuarded() !== ['*']) {
                foreach ($model->getGuarded() as $guarded) {
                    unset($attributes[$guarded]);
                }

                return $attributes;
            }

            foreach ($attributes as $attributeKey => $value) {
                if (! $model->isFillable($attributeKey)) {
                    unset($attributes[$attributeKey]);
                }

                if (in_array($attributeKey, $model->getHidden(), true)) {
                    unset($attributes[$attributeKey]);
                }
            }

            if ($safeKeyName === true) {
                $attributes[$model->getKeyName()] = $model->getKey();
            }

            return $attributes;
        })->toArray();
    }
}
