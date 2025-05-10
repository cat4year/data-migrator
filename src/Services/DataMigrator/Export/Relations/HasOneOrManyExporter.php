<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Export\Relations;

use Cat4year\DataMigrator\Services\DataMigrator\Tools\ModelService;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\TableService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;

final readonly class HasOneOrManyExporter implements RelationExporter
{
    public function __construct(
        private HasOneOrMany $relation,
        private ModelService $modelService,
        private TableService $tableRepository,
    ) {
    }

    /**
     * @throws BindingResolutionException
     */
    public static function create(HasOneOrMany $relation): self
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

        return $this->relation->getRelated()::query()
            ->select()
            ->whereNotNull($foreignKey)
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

    public function getModifyInfo(): array
    {
        $parent = $this->relation->getParent();
        $parentTable = $parent->getTable();
        $parentKeyName = $parent->getKeyName();
        $uniqueKeyName = $this->modelService->identifyUniqueIdColumn($parent);

        if ($uniqueKeyName === null) {
            // todo: можно решить через конфигуратор что с этим делать: скип, дефолтный keyName, ...?
        }

        $related = $this->relation->getRelated();
        $relatedTable = $related->getTable();
        $uniqueRelatedKeyName = $this->modelService->identifyUniqueIdColumn($related);

        if ($uniqueRelatedKeyName === null) {
            // todo: можно решить через конфигуратор что с этим делать: скип, дефолтный keyName, ...?
        }

        $localKeyName = $this->relation->getLocalKeyName();
        $foreignKeyName = $this->relation->getForeignKeyName();

        return [
            $parentTable => [
                $parentKeyName => [
                    'table' => $parentTable,
                    'oldKeyName' => $parentKeyName,
                    'keyName' => $uniqueKeyName,
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
            ],
            $relatedTable => [
                $foreignKeyName => [
                    'table' => $parentTable,
                    'oldKeyName' => $localKeyName,
                    'keyName' => $uniqueRelatedKeyName,
                    'nullable' => $this->tableRepository->isNullableColumn(
                        $relatedTable,
                        $foreignKeyName
                    ),
                ],
            ],
        ];
    }
}
