<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Export\Relations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\ModelService;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\TableService;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final readonly class BelongsToManyExporter implements RelationExporter
{
    public function __construct(
        private BelongsToMany $belongsToMany,
        private ModelService $modelService,
        private TableService $tableService,
    ) {
    }

    /**
     * @throws BindingResolutionException
     */
    public static function create(BelongsToMany $belongsToMany): self
    {
        return app()->makeWith(self::class, ['relation' => $belongsToMany]);
    }

    public function makeExportData(array $foreignIds): array
    {
        $relatedIds = $this->getRelatedUsedIds($foreignIds);
        $pivotIdKeyName = $this->getPivotIdColumnKeyName();
        $pivotIds = $this->getPivotUsedIds($foreignIds, $pivotIdKeyName);

        $relatedTable = $this->belongsToMany->getRelated()->getTable();
        $pivotTable = $this->belongsToMany->getTable();

        return [
            $relatedTable => [
                'table' => $relatedTable,
                'keyName' => $this->belongsToMany->getRelated()->getKeyName(),
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
        $parentPivotKeyName = $this->belongsToMany->getForeignPivotKeyName();
        $relatedPivotKeyName = $this->belongsToMany->getRelatedPivotKeyName();

        return DB::table($this->belongsToMany->getTable())
            ->where($parentPivotKeyName, $ids)
            ->get()
            ->pluck($relatedPivotKeyName)
            ->toArray();
    }

    private function getPivotIdColumnKeyName(bool $checkFalseAutoIncrement = false): string
    {
        $columns = Schema::getColumns($this->belongsToMany->getTable());
        foreach ($columns as $column) {
            if ($column['nullable'] === false && (! $checkFalseAutoIncrement || $column['auto_increment'] === false)) {
                return $column['name'];
            }
        }

        return current($columns)['name'];
    }

    private function getPivotUsedIds(array $ids, string $pivotIdKeyName): array
    {
        $parentPivotKeyName = $this->belongsToMany->getForeignPivotKeyName();

        return DB::table($this->belongsToMany->getTable())
            ->whereIn($parentPivotKeyName, $ids)
            ->get()
            ->pluck($pivotIdKeyName)
            ->toArray();
    }

    public function getModifyInfo(): array
    {
        $model = $this->belongsToMany->getParent();
        $uniqueKeyName = $this->modelService->identifyUniqueIdColumn($model);
        $parentTable = $model->getTable();
        $parentKeyName = $model->getKeyName();

        if ($uniqueKeyName === null) {
            // todo: можно решить через конфигуратор что с этим делать: скип, дефолтный keyName, ...?
        }

        $related = $this->belongsToMany->getRelated();
        $relatedTable = $related->getTable();
        $relatedKeyName = $related->getKeyName();
        $uniqueRelatedKeyName = $this->modelService->identifyUniqueIdColumn($related);

        if ($uniqueRelatedKeyName === null) {
            // todo: можно решить через конфигуратор что с этим делать: скип, дефолтный keyName, ...?
        }

        $pivotTable = $this->belongsToMany->getTable();
        $parentPivotKeyName = $this->belongsToMany->getForeignPivotKeyName();
        $relatedPivotKeyName = $this->belongsToMany->getRelatedPivotKeyName();

        $parentModifyInfo = [
            'table' => $parentTable,
            'oldKeyName' => $parentKeyName,
            'keyName' => $uniqueKeyName,
            'autoIncrement' => $this->tableService->isAutoincrementColumn($parentTable, $parentKeyName),
            'nullable' => $this->tableService->isNullableColumn($parentTable, $parentKeyName),
        ];

        $relatedModifyInfo = [
            'table' => $relatedTable,
            'oldKeyName' => $relatedKeyName,
            'keyName' => $uniqueRelatedKeyName,
            'autoIncrement' => $this->tableService->isAutoincrementColumn($relatedTable, $relatedKeyName),
            'nullable' => $this->tableService->isNullableColumn($relatedTable, $relatedKeyName),
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
                'autoIncrement' => $this->tableService->isAutoincrementColumn($pivotTable, $pivotIdKeyName),
                'nullable' => $this->tableService->isNullableColumn($pivotTable, $pivotIdKeyName),
            ];
        }

        $pivotIdModifyPrimaryAttributes = ['isPrimaryKey' => true];
        $result[$pivotTable][$pivotIdKeyName] += $pivotIdModifyPrimaryAttributes;

        return $result;
    }
}
