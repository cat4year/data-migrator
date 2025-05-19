<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Import;

use Cat4year\DataMigrator\Entity\ExportModifyColumn;
use Cat4year\DataMigrator\Entity\ExportModifySimpleColumn;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\CollectionMerger;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\SyncIdState;
use DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Log;
use stdClass;
use Throwable;

final readonly class Importer
{
    /**
     * @param SupportCollection<string, SupportCollection> $existItemsByTable
     */
    public function __construct(
        private ImportDataPreparer $preparer,
        private CollectionMerger $collectionMerger,
        private SupportCollection $existItemsByTable,
        private SupportCollection $fixColumnsLater,
    )
    {
    }

    public function import(ImportData $importData): void
    {
        $data = $importData->get();
        $this->importData($data);
    }

    /**
     * todo: Нужно будет разбить на чанки по N элементов (из конфигурации)
     */
    public function importData(array $data): void
    {
        $this->collectExistData($data);

        [$withRelationFields, $withoutRelationFields] = $this->splitDataByRelationFields($data);

        $this->syncWithoutAutoincrementRelationFields($withoutRelationFields);
        $this->syncWithAutoincrementRelationFields($withRelationFields);
        $this->fixLaterNullableRelationFields($withRelationFields);
    }

    private function collectExistData(array $data): void
    {
        foreach ($data as $tableName => $tableData) {
            $items = $this->getExistsRequiredItemsFromDatabase($tableName, $tableData['syncId'], $tableData['items']);
            $this->existItemsByTable->put($tableName, collect($items));
        }
    }

    /**
     * @param array<non-empty-string, ExportModifyColumn> $data
     * @return array<array<non-empty-string, ExportModifyColumn>, array<non-empty-string, ExportModifyColumn>>
     */
    private function splitDataByRelationFields(array $data): array
    {
        return collect($data)->partition(function ($tableData) {
            return isset($tableData['modifiedAttributes']) && $this->hasRelationFields($tableData['modifiedAttributes']);
        })->toArray();
    }

    /**
     * @param array<non-empty-string, ExportModifyColumn> $modifiedAttributes
     * @return bool
     */
    private function hasRelationFields(array $modifiedAttributes): bool
    {
        if (count($modifiedAttributes) > 1) {
            return true;
        }

        return count($modifiedAttributes) === 1 && !current($modifiedAttributes)->isPrimaryKey();
    }

    /**
     * todo: проверить этот кейс
     */
    private function syncWithoutAutoincrementRelationFields(array $withoutAutoincrementData): void
    {
        foreach ($withoutAutoincrementData as $tableName => $tableData) {
            $syncId = $tableData['syncId'];
            //todo: нужно ли? Проверить
//            $itemsForSync = $this->preparer->beforeSyncWithDatabase(
//                $tableData['items'],
//                $syncId
//            );
            $this->syncWithDatabase($tableName, $syncId, $tableData);
        }
    }

    /**
     * Мы считаем, что все простые таблицы уже импортированы
     * Что в $data у нас отсортированные данные от простых к более зависимым
     */
    private function syncWithAutoincrementRelationFields(array $withRelationFields): void
    {
        foreach ($withRelationFields as $tableName => $tableData) {
            $syncId = $tableData['syncId'];

            $preparedFieldsData = $this->preparer->itemsWithAutoincrementRelationFields(
                $tableData,
                $this->existItemsByTable,
                $syncId
            );
            $preparedItems = $preparedFieldsData['items'];
            $this->fixColumnsLater->put($tableName, $preparedFieldsData['needFixLater']);

            $itemsForSync = $this->preparer->beforeSyncWithDatabase(
                $preparedItems,
                $tableData['modifiedAttributes'],
            );
            $this->syncWithDatabase($tableName, $syncId, $itemsForSync);
        }
    }

    private function syncWithDatabase(
        string $tableName,
        array $syncId,
        array $itemsData,
    ): void
    {
        //пока реализовываем вариант где syncId всегда присутствует и он уникальный - updateOrInsert
        //todo: реализовать проверку на уникальность при экспорте в отдельное поле добавлять
        //todo: если будем поддерживать отсутствие syncId - то upsert
        try {
            foreach ($itemsData as $item) {
                $query = DB::table($tableName);
                $queryBySyncId = $this->withConditionsBySyncIdForUpdateItem($query, $syncId, $item);
                $queryBySyncId->updateOrInsert($item);
            }
        } catch (Throwable $e) {
            Log::error('Ошибка при обновлении', [
                $tableName,
                $itemsData,
                $item,
                $syncId,
                $e->getMessage(),
            ]);
        }

        $items = $this->getExistsRequiredItemsFromDatabase($tableName, $syncId, $itemsData);
        $latestExistItemsByTable = collect($items);
        $this->collectionMerger->putWithMerge($this->existItemsByTable, $tableName, $latestExistItemsByTable);
    }

    private function withConditionsBySyncIdForUpdateItem(Builder $query, array $keys, array $values): Builder
    {
        foreach ($keys as $key) {
            $query->whereIn($key, array_column($values, $key));
        }

        return $query;
    }

    private function getExistsRequiredItemsFromDatabase(string $tableName, array $syncId, array $values): array
    {
        $query = DB::table($tableName);

        $queryBySyncId = $this->withConditionsBySyncIdForGetExistItems($query, $syncId, $values);

        $keyBySyncId = SyncIdState::makeHashSyncId($syncId);

        return $queryBySyncId->get()->map(static fn(stdClass $item) => (array)$item)->keyBy($keyBySyncId)->toArray();
    }

    /**
     * @todo: не совсем корректное условия, могут выбраться не с конкретным набором 3х колонок, а каждая из колонок случайно попала в значения разных наборов.
     */
    private function withConditionsBySyncIdForGetExistItems(Builder $query, array $keys, array $values): Builder
    {
        foreach ($keys as $key) {
            $query->whereIn($key, array_column($values, $key));
        }

        return $query;
    }

    private function fixLaterNullableRelationFields(array $data): void
    {
        foreach ($data as $tableName => $tableData) {
            if (!$this->fixColumnsLater->has($tableName)) {
                continue;
            }

            //удаляем все колонки кроме тех что в syncId и fixColumnsLater
            //меняем значения fixColumnsLater на значения конечной системы
            //обновляем fixColumnsLater значения, по syncId условию
            $primaryColumn = $this->getPrimaryKeyColumn($tableData['modifiedAttributes']);//todo: это не надо
            $primaryColumnKeyName = $primaryColumn?->getKeyName();
            $attributesForFixKeyName = $this->fixColumnsLater->get($tableName);
            //todo: это нужно ли? скорее всего будет меняться на syncIdState::makeHash
            if ($primaryColumnKeyName !== null && !in_array($primaryColumnKeyName, $attributesForFixKeyName, true)) {
                $attributesForFixKeyName[] = $primaryColumnKeyName;
            }

            $modifiedItems = $this->preparer->modifyItemsAttributes(
                $tableData['items'],
                $attributesForFixKeyName,
                $tableData['modifiedAttributes'],
                $this->existItemsByTable
            );

            $modifiedItemsOnlyFixLaterFields = $this->preparer->modifyAndSaveOnlyFixLaterFields(
                $modifiedItems,
                $attributesForFixKeyName,
                $primaryColumnKeyName
            );

            $syncId = $tableData['syncId'];
            foreach ($modifiedItemsOnlyFixLaterFields as $itemLaterAndSyncFields) {
                $query = DB::table($tableName);
                $queryBySyncId = $this->withConditionsBySyncIdForUpdateItem($query, $syncId, $itemLaterAndSyncFields);
                $queryBySyncId->update($itemLaterAndSyncFields);
            }
        }
    }

    /**
     * @param array<non-empty-string, ExportModifyColumn> $modifiedAttributes
     * @todo: Скорее всего нужно добавлять в экспсорте хэш syncId + добавить это в ключи для items
     * @todo: В морф таблице так-то primaryKey не будет в modifiedAttributes
     */
    private function getPrimaryKeyColumn(array $modifiedAttributes): ?ExportModifySimpleColumn
    {
        return collect($modifiedAttributes)
            ->first(static fn(ExportModifyColumn $column) => $column instanceof ExportModifySimpleColumn && $column->isPrimaryKey());
    }
}
