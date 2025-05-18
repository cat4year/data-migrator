<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Tools;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final readonly class ModelService
{
    public function __construct(
        //todo: задуматься, о проблеме low cohesion
        private TableService $tableRepository,
    ) {
    }

    public function identifyUniqueIdColumn(Model $model): ?string
    {
        try {
            $modelKey = $model->getKeyName();
            if ($model->getKeyName() === 'id' && $model->getKeyType() === 'int' && ! $model->isFillable('id')) {
                $modelKey = $this->getFromAttributeOrTryFindIdColumn($model);
            }

            return $modelKey;
        } catch (RuntimeException) {
            return null;
        }
    }

    public function getUniqueColumnsForSync(Model $model): ?array
    {
        try {
            $tableColumnMap = config('data-migrator.table_sync_id');
            if (isset($tableColumnMap[$model->getTable()])) {
                if (is_string($tableColumnMap[$model->getTable()])) {
                    return [$tableColumnMap[$model->getTable()]];//не обязательно добавлять как массив
                }

                return $tableColumnMap[$model->getTable()];
            }

            $modelKey = $model->getKeyName();
            if ($model->getKeyName() === 'id' && $model->getKeyType() === 'int' && ! $model->isFillable('id')) {
                $modelKey = $this->getFromAttributeOrTryFindIdColumn($model);
            }

            return [$modelKey];
        } catch (RuntimeException) {
            return null;
        }
    }

    public function getFromAttributeOrTryFindIdColumn(Model $model): string
    {
        if (! $model->hasAttribute('migrationColumnKey')) { // todo: добавить треит и везде его где не используется
            if (config('data-migrator.try_find_unique_relation_column') === true) {
                return $this->tableRepository->tryFindUniqueIdColumnByAutoIncrementKey(
                    $model->getKeyName(),
                    $model->getTable()
                );
            }

            throw new RuntimeException;
        }

        return $model->getAttribute('migrationColumnKey');
    }

    public function implodeUniqueColumns(array $parentKeyNames): string
    {
        return implode('|', $parentKeyNames);
    }
}
