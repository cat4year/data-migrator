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
        private HasOneOrMany $hasOneOrMany,
        private ModelService $modelService,
        private TableService $tableService,
    ) {
    }

    /**
     * @throws BindingResolutionException
     */
    public static function create(HasOneOrMany $hasOneOrMany): self
    {
        return app()->makeWith(self::class, ['hasOneOrMany' => $hasOneOrMany]);
    }

    public function makeExportData(array $foreignIds): array
    {
        $ids = $this->getUsedIds($foreignIds);

        $table = $this->hasOneOrMany->getModel()->getTable();

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
        $idKey = $this->hasOneOrMany->getRelated()->getKeyName();
        $foreignKey = $this->hasOneOrMany->getForeignKeyName();

        return $this->hasOneOrMany->getRelated()::query()
            ->select()
            ->whereNotNull($foreignKey)
            ->whereIn($foreignKey, $foreignIds)
            ->get()
            ->pluck($idKey)
            ->toArray();
    }

    private function getEntity(): Model
    {
        return $this->hasOneOrMany->getRelated();
    }

    private function getKeyName(): string
    {
        return $this->getEntity()->getKeyName();
    }

    public function getModifyInfo(): array
    {
        $model = $this->hasOneOrMany->getParent();
        $parentTable = $model->getTable();
        $parentKeyName = $model->getKeyName();
        $uniqueKeyName = $this->modelService->identifyUniqueIdColumn($model);

        if ($uniqueKeyName === null) {
            // todo: можно решить через конфигуратор что с этим делать: скип, дефолтный keyName, ...?
        }

        $related = $this->hasOneOrMany->getRelated();
        $relatedTable = $related->getTable();
        $uniqueRelatedKeyName = $this->modelService->identifyUniqueIdColumn($related);

        if ($uniqueRelatedKeyName === null) {
            // todo: можно решить через конфигуратор что с этим делать: скип, дефолтный keyName, ...?
        }

        $localKeyName = $this->hasOneOrMany->getLocalKeyName();
        $foreignKeyName = $this->hasOneOrMany->getForeignKeyName();

        return [
            $parentTable => [
                $parentKeyName => [
                    'table' => $parentTable,
                    'oldKeyName' => $parentKeyName,
                    'keyName' => $uniqueKeyName,
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
            ],
            $relatedTable => [
                $foreignKeyName => [
                    'table' => $parentTable,
                    'oldKeyName' => $localKeyName,
                    'keyName' => $uniqueRelatedKeyName,
                    'nullable' => $this->tableService->isNullableColumn(
                        $relatedTable,
                        $foreignKeyName
                    ),
                ],
            ],
        ];
    }
}
