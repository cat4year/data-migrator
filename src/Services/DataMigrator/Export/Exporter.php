<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Export;

use Cat4year\DataMigrator\Entity\SyncId;
use Cat4year\DataMigrator\Services\DataMigrator\Export\Relations\RelationsExporter;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\SyncIdState;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\TableService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
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
        if (! $exportConfigurator instanceof ExportConfigurator) {
            $exportConfigurator = ExportConfigurator::create();
        }

        throw_if(is_string($entity) && ! class_exists($entity), new InvalidArgumentException(sprintf("Entity class '%s' does not exist", $entity)));

        $params = [
            'entity' => is_string($entity) ? app($entity) : $entity,
            'configurator' => $exportConfigurator,
            'relationManager' => RelationsExporter::create($exportConfigurator),
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
        if ($this->configurator->getIds() === []) {
            $idKey = $this->entity->getKeyName();
            $ids = $this->entity::query()
                ->select($idKey)
                ->pluck($idKey)
                ->toArray();
        } else {
            $ids = $this->configurator->getIds();
        }

        throw_if(empty($ids), new RuntimeException('Empty ids for export entity'));

        $exportData = $this->makeEntityData($ids);
        throw_if($exportData === [], new RuntimeException('Export items not found'));

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
        $table = $this->entity->getTable();
        $syncId = $this->syncIdState->tableSyncId($table);
        $mainEntityResult = $this->makeItems($table, $ids, $syncId);

        if ($mainEntityResult === []) {
            return [];
        }

        if (! $this->configurator->withRelations()) {
            $resultMainData = [
                'table' => $table,
                'items' => $mainEntityResult,
                'syncId' => $syncId,
            ];

            return [$table => $resultMainData];
        }

        $exporterState = $this->relationManager->collectRelations($table, $ids); // todo: перекрывает result

        /** @var string $entityTable */
        foreach ($exporterState->entityIds as $entityTable => $entityIds) {
            $entitySyncId = $this->syncIdState->tableSyncId($entityTable);
            $entityItems = $this->makeItems($entityTable, $entityIds, $entitySyncId);

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

        // $resultWithUniqueColumns = $this->syncIdAttacher->attachSyncIds($result);

        return $this->sorter->sort($result);
    }

    /**
     * @param list<non-negative-int|non-empty-string> $ids
     * @return list<array<string, mixed>>
     */
    private function makeItems(
        string $table,
        array $ids,
        SyncId $syncId,
        bool $emptyIsAll = false
    ): array {
        if ($ids === [] && ! $emptyIsAll) {
            return [];
        }

        $query = DB::table($table);
        foreach ($ids as $idString) {
            $idsForColumns = explode('|', $idString);

            $syncColumns = $syncId->columns();
            $query->orWhere(function ($q) use ($syncColumns, $idsForColumns) {
                foreach ($syncColumns as $index => $field) {
                    if (isset($idsForColumns[$index])) {
                        $q->where($field, $idsForColumns[$index]);
                    }
                }
            });
        }

        $items = $query->get()
            ->keyBy(static fn (stdClass $item): string => $syncId->keyStringByValues((array) $item));

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
