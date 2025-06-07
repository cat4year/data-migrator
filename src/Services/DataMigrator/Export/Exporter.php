<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Export;

use Illuminate\Support\Facades\DB;
use Cat4year\DataMigrator\Entity\SyncId;
use Cat4year\DataMigrator\Services\DataMigrator\Export\Relations\RelationsExporter;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\SyncIdState;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\TableService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection as SupportCollection;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use stdClass;

final readonly class Exporter
{
    public function __construct(
        private Model $model,
        private ExportConfigurator $exportConfigurator,
        private RelationsExporter $relationsExporter,
        private ExportSorter $exportSorter,
        private TableService $tableService,
        private SyncIdState $syncIdState,
    ) {
    }

    /**
     * @template TModel of Model
     *
     * @param Model|class-string<TModel>
     *
     * @throws BindingResolutionException
     */
    public static function create(Model|string $entity, ?ExportConfigurator $exportConfigurator = null): self
    {
        if (!$exportConfigurator instanceof ExportConfigurator) {
            $exportConfigurator = ExportConfigurator::create();
        }

        throw_if(is_string($entity) && ! class_exists($entity), new InvalidArgumentException(sprintf("Entity class '%s' does not exist", $entity)));

        $params = [
            'entity' => is_string($entity) ? app($entity) : $entity,
            'configurator' => $exportConfigurator,
            'relationManager' => RelationsExporter::create(),
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
        if ($this->exportConfigurator->getIds() === []) {
            $idKey = $this->model->getKeyName();
            $ids = $this->model::query()
                ->select($idKey)
                ->pluck($idKey)
                ->toArray();
        } else {
            $ids = $this->exportConfigurator->getIds();
        }

        throw_if(empty($ids), new RuntimeException('Empty ids for export entity'));

        $exportData = $this->makeEntityData($ids);
        throw_if($exportData === [], new RuntimeException('Export items not found'));

        return $exportData;
    }

    public function save(array $exportData): string
    {
        $migrationPath = $this->exportConfigurator->makeSourceFullPath();
        $this->exportConfigurator->getSourceFormat()->save($exportData, $migrationPath);

        return $migrationPath;
    }

    /**
     * check: может вынести куда-нибудь? Или в интерфейсе не указывать для пользователя конечного
     *
     * @param list<non-negative-int|non-empty-string> $ids
     */
    public function makeEntityData(array $ids): array
    {
        $table = $this->model->getTable();
        $syncId = $this->syncIdState->tableSyncId($table);
        $mainEntityResult = $this->makeItems($table, $ids, $syncId, $this->model->getKeyName());

        if ($mainEntityResult === []) {
            return [];
        }

        if (! $this->exportConfigurator->withRelations()) {
            $resultMainData = [
                'table' => $table,
                'items' => $mainEntityResult,
                'syncId' => $syncId,
            ];

            return [$table => $resultMainData];
        }

        $exporterState = $this->relationsExporter->collectRelations($table, $ids); // todo: перекрывает result

        /** @var string $entityTable */
        foreach ($exporterState->entityIds as $entityTable => $entityIds) {
            // есть проблема дублирования получения записей основной модели. Критично ли?
            $keyName = $this->tableService->identifyPrimaryKeyNameByTable($entityTable);

            if ($keyName === null) {
                continue;
            }

            $entitySyncId = $this->syncIdState->tableSyncId($entityTable);
            $entityItems = $this->makeItems($entityTable, $entityIds, $entitySyncId, $keyName);

            if ($entityItems === []) {
                continue;
            }

            $resultDataForTable = [
                'table' => $entityTable,
                'items' => $entityItems,
                'syncId' => $entitySyncId,
            ];

            $exporterState->result->put($entityTable, $resultDataForTable);
        }

        $exportModifier = app()->makeWith(ExportModifier::class, [
            'entitiesCollections' => $exporterState->result,
            'entityClasses' => $exporterState->relationsInfo,
        ]);

        $result = $exportModifier->modify();

        //$resultWithUniqueColumns = $this->syncIdAttacher->attachSyncIds($result);

        return $this->exportSorter->sort($result);
    }

    /**
     * @param list<non-negative-int|non-empty-string> $ids
     * @return list<array<string, mixed>>
     */
    private function makeItems(
        string $table,
        array $ids,
        SyncId $syncId,
        string $idKey = 'id',
        bool $emptyIsAll = false
    ): array
    {
        if ($ids === [] && ! $emptyIsAll) {
            return [];
        }

        $items = DB::table($table)
            ->unless($ids === [], static fn($q) => $q->whereIn($idKey, $ids))
            ->get()
            ->keyBy(static fn(stdClass $item): string => $syncId->keyStringByValues((array)$item));

        return $this->dataToArray($items);
    }

    public function dataToArray(SupportCollection $supportCollection, bool $safeKeyName = true): array
    {
        return $supportCollection->map(static function (Model|stdClass $model) use ($safeKeyName) {
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

            if ($safeKeyName) {
                $attributes[$model->getKeyName()] = $model->getKey();
            }

            return $attributes;
        })->toArray();
    }
}
