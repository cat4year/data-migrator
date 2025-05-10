<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Export\Relations;

use Cat4year\DataMigrator\Services\DataMigrator\Tools\ModelService;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\TableService;
use DB;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Schema;

final readonly class BelongsToManyExporter implements RelationExporter
{
    public function __construct(
        private BelongsToMany $relation,
        private ModelService $modelService,
        private TableService $tableRepository,
    ) {
    }

    /**
     * @throws BindingResolutionException
     */
    public static function create(BelongsToMany $relation): self
    {
        return app()->makeWith(self::class, ['relation' => $relation]);
    }

    public function makeExportData(array $foreignIds): array
    {
        $relatedIds = $this->getRelatedUsedIds($foreignIds);
        $pivotIdKeyName = $this->getPivotIdColumnKeyName();
        $pivotIds = $this->getPivotUsedIds($foreignIds, $pivotIdKeyName);

        $relatedTable = $this->relation->getRelated()->getTable();
        $pivotTable = $this->relation->getTable();

        return [
            $relatedTable => [
                'table' => $relatedTable,
                'keyName' => $this->relation->getRelated()->getKeyName(),
                'ids' => $relatedIds,
            ],
            $pivotTable => [
                'table' => $pivotTable,
                'keyName' => $pivotIdKeyName,
                'ids' => $pivotIds,
            ],
        ];
    }

    private function getRelatedUsedIds(array $ids): array
    {
        $parentPivotKeyName = $this->relation->getForeignPivotKeyName();
        $relatedPivotKeyName = $this->relation->getRelatedPivotKeyName();

        return DB::table($this->relation->getTable())
            ->where($parentPivotKeyName, $ids)
            ->get()
            ->pluck($relatedPivotKeyName)
            ->toArray();
    }

    private function getPivotIdColumnKeyName(bool $checkFalseAutoIncrement = false): string
    {
        $columns = Schema::getColumns($this->relation->getTable());
        foreach ($columns as $column) {
            if ($column['nullable'] === false && (! $checkFalseAutoIncrement || $column['auto_increment'] === false)) {
                return $column['name'];
            }
        }

        return current($columns)['name'];
    }

    private function getPivotUsedIds(array $ids, string $pivotIdKeyName): array
    {
        $parentPivotKeyName = $this->relation->getForeignPivotKeyName();

        return DB::table($this->relation->getTable())
            ->whereIn($parentPivotKeyName, $ids)
            ->get()
            ->pluck($pivotIdKeyName)
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
        $uniqueKeyName = $this->modelService->identifyUniqueIdColumn($parent);
        $parentTable = $parent->getTable();
        $parentKeyName = $parent->getKeyName();

        if ($uniqueKeyName === null) {
            // todo: можно решить через конфигуратор что с этим делать: скип, дефолтный keyName, ...?
        }

        $related = $this->relation->getRelated();
        $relatedTable = $related->getTable();
        $relatedKeyName = $related->getKeyName();
        $uniqueRelatedKeyName = $this->modelService->identifyUniqueIdColumn($related);

        if ($uniqueRelatedKeyName === null) {
            // todo: можно решить через конфигуратор что с этим делать: скип, дефолтный keyName, ...?
        }

        $pivotTable = $this->relation->getTable();
        $parentPivotKeyName = $this->relation->getForeignPivotKeyName();
        $relatedPivotKeyName = $this->relation->getRelatedPivotKeyName();

        $parentModifyInfo = [
            'table' => $parentTable,
            'oldKeyName' => $parentKeyName,
            'keyName' => $uniqueKeyName,
            'autoIncrement' => $this->tableRepository->isAutoincrementColumn($parentTable, $parentKeyName),
            'nullable' => $this->tableRepository->isNullableColumn($parentTable, $parentKeyName),
        ];

        $relatedModifyInfo = [
            'table' => $relatedTable,
            'oldKeyName' => $relatedKeyName,
            'keyName' => $uniqueRelatedKeyName,
            'autoIncrement' => $this->tableRepository->isAutoincrementColumn($relatedTable, $relatedKeyName),
            'nullable' => $this->tableRepository->isNullableColumn($relatedTable, $relatedKeyName),
        ];

        $result = [
            $parentTable => [
                $parentKeyName => $parentModifyInfo + ['isPrimaryKey' => true],
            ],
            $relatedTable => [
                $relatedKeyName => $relatedModifyInfo + ['isPrimaryKey' => true],
            ],
            $pivotTable => [
                $parentPivotKeyName => $parentModifyInfo,
                $relatedPivotKeyName => $relatedModifyInfo,
            ],
        ];

        $pivotIdKeyName = $this->getPivotIdColumnKeyName(true);
        if ($pivotIdKeyName !== $parentPivotKeyName && $pivotIdKeyName !== $relatedPivotKeyName) {
            $result[$pivotTable][$pivotIdKeyName] = [
                'table' => $pivotTable,
                'oldKeyName' => $pivotIdKeyName,
                'keyName' => $pivotIdKeyName,
                'autoIncrement' => $this->tableRepository->isAutoincrementColumn($pivotTable, $pivotIdKeyName),
                'nullable' => $this->tableRepository->isNullableColumn($pivotTable, $pivotIdKeyName),
            ];
        }

        $pivotIdModifyPrimaryAttributes = ['isPrimaryKey' => true];
        $result[$pivotTable][$pivotIdKeyName] += $pivotIdModifyPrimaryAttributes;

        return $result;
    }
}
