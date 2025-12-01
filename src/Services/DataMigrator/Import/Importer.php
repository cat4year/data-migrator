<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Import;

use Cat4year\DataMigrator\Entity\ExportModifyColumn;
use Cat4year\DataMigrator\Entity\ExportModifySimpleColumn;
use Cat4year\DataMigrator\Entity\SyncId;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\Attachment\AttachmentSaver;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\CollectionMerger;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;
use Throwable;

final readonly class Importer
{
    /**
     * @param SupportCollection<string, SupportCollection> $existItemsByTable
     */
    public function __construct(
        private ImportDataPreparer $importDataPreparer,
        private CollectionMerger $collectionMerger,
        private SupportCollection $existItemsByTable,
        private SupportCollection $fixColumnsLater,
    ) {
    }

    public function import(ImportData $importData, string $migrationName): void
    {
        $data = $importData->get();

        $this->handleAttachments($data, $migrationName);
        $this->importData($data);
    }

    /**
     * todo: Нужно будет разбить на чанки по N элементов (из конфигурации)
     */
    public function importData(array $data): void
    {
        // $this->collectExistData($data);

        [$withRelationFields, $withoutRelationFields] = $this->splitDataByRelationFields($data);

        $this->syncWithoutAutoincrementRelationFields($withoutRelationFields);
        $this->syncWithAutoincrementRelationFields($withRelationFields);
        $this->fixLaterNullableRelationFields($withRelationFields);
    }

    /**
     * @param array<non-empty-string, ExportModifyColumn> $data
     * @return array<array<non-empty-string, ExportModifyColumn>, array<non-empty-string, ExportModifyColumn>>
     */
    private function splitDataByRelationFields(array $data): array
    {
        return collect($data)->partition(fn ($tableData): bool => isset($tableData['modifiedAttributes']) && $this->hasRelationFields($tableData['modifiedAttributes']))->toArray();
    }

    /**
     * @param array<non-empty-string, ExportModifyColumn> $modifiedAttributes
     */
    private function hasRelationFields(array $modifiedAttributes): bool
    {
        if (count($modifiedAttributes) > 1) {
            return true;
        }

        return count($modifiedAttributes) === 1 && ! current($modifiedAttributes)->isPrimaryKey();
    }

    /**
     * todo: проверить этот кейс
     */
    private function syncWithoutAutoincrementRelationFields(array $withoutAutoincrementData): void
    {
        foreach ($withoutAutoincrementData as $tableName => $tableData) {
            $syncId = $tableData['syncId'];
            // todo: нужно ли? Проверить
            $itemsForSync = $this->importDataPreparer->beforeSyncWithDatabase(
                $tableName,
                $tableData['items'],
                $tableData['modifiedAttributes'] ?? [],
            );

            $this->syncWithDatabase($tableName, $syncId, $itemsForSync);
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

            $preparedFieldsData = $this->importDataPreparer->itemsWithAutoincrementRelationFields(
                $tableData,
                $this->existItemsByTable,
                $syncId
            );
            $preparedItems = $preparedFieldsData['items'];
            $this->fixColumnsLater->put($tableName, $preparedFieldsData['needFixLater']);
            //            if($tableName === 'attachmentable'){
            //                dd($tableData, $this->fixColumnsLater);
            //            }
            $itemsForSync = $this->importDataPreparer->beforeSyncWithDatabase(
                $tableName,
                $preparedItems,
                $tableData['modifiedAttributes'],
            );
            // dd($itemsForSync, $this->fixColumnsLater);
            $this->syncWithDatabase($tableName, $syncId, $itemsForSync);
        }

        // dd($this->fixColumnsLater);
    }

    private function syncWithDatabase(
        string $tableName,
        array $syncId,
        array $itemsData,
    ): void {
        // пока реализовываем вариант где syncId всегда присутствует и он уникальный - updateOrInsert
        // todo: реализовать проверку на уникальность при экспорте в отдельное поле добавлять
        // todo: если будем поддерживать отсутствие syncId - то upsert
        foreach ($itemsData as $itemData) {
            $query = DB::table($tableName);
            // dd($item, $syncId);
            $syncIdWithValues = [];
            $attributesItemOnlyForUpdate = $itemData;
            foreach ($syncId as $syncColumn) {
                try{
                    $syncIdWithValues[$syncColumn] = $itemData[$syncColumn];
                } catch (Throwable){
                    dd($itemData);
                }

                unset($attributesItemOnlyForUpdate[$syncColumn]);
            }

            // $queryBySyncId = $this->withConditionsBySyncIdForUpdateItem($query, $syncId, $item);

            //                            if($tableName === 'attachmentable'){
            //                dd($syncIdWithValues, $attributesItemOnlyForUpdate);
            //            }
            // dd($syncIdWithValues, $attributesItemOnlyForUpdate);
            $query->updateOrInsert($syncIdWithValues, $attributesItemOnlyForUpdate);
        }

        $items = $this->getExistsRequiredItemsFromDatabase($tableName, $syncId, $itemsData);
        $latestExistItemsByTable = collect($items);
        $this->collectionMerger->putWithMerge($this->existItemsByTable, $tableName, $latestExistItemsByTable);
    }

    private function getExistsRequiredItemsFromDatabase(string $tableName, array $syncId, array $values): array
    {
        $builder = DB::table($tableName);

        $queryBySyncId = $this->withConditionsBySyncIdForGetExistItems($builder, $syncId, $values);
        //        if($tableName === 'attachmentable'){
        //            dd($tableName, $syncId, $values,$queryBySyncId, $this->existItemsByTable);
        //        }
        //        $keyBySyncId = SyncId::makeHash($syncId);

        $syncId = new SyncId($syncId);

        return $queryBySyncId->get()->map(static fn (stdClass $item): array => (array) $item)
            ->keyBy(static fn (array $item): string => $syncId->keyStringByValues($item))
            ->toArray();
    }

    /**
     * @todo: не совсем корректное условия, могут выбраться не с конкретным набором 3х колонок, а каждая из колонок случайно попала в значения разных наборов.
     */
    private function withConditionsBySyncIdForGetExistItems(Builder $builder, array $keys, array $items): Builder
    {
        $builder->where(static function ($q) use ($keys, $items): void {
            foreach ($items as $item) {
                $q->orWhere(static function ($subQuery) use ($keys, $item): void {
                    foreach ($keys as $key) {
                        $subQuery->where($key, $item[$key]);
                    }
                });
            }
        });

        return $builder;
    }

    private function fixLaterNullableRelationFields(array $data): void
    {
        foreach ($data as $tableName => $tableData) {
            if (! $this->fixColumnsLater->has($tableName)) {
                continue;
            }

            // удаляем все колонки кроме тех что в syncId и fixColumnsLater
            // меняем значения fixColumnsLater на значения конечной системы
            // обновляем fixColumnsLater значения, по syncId условию
            $primaryColumn = $this->getPrimaryKeyColumn($tableData['modifiedAttributes']); // todo: это не надо
            $primaryColumnKeyName = $primaryColumn?->getKeyName();
            $attributesForFixKeyName = $this->fixColumnsLater->get($tableName);
            // todo: это нужно ли? скорее всего будет меняться на syncIdState::makeHash
            if ($primaryColumnKeyName !== null && ! in_array($primaryColumnKeyName, $attributesForFixKeyName, true)) {
                // dd($primaryColumnKeyName);
                //  $attributesForFixKeyName[] = $primaryColumnKeyName;
            }

            foreach ($tableData['syncId'] as $syncColumn) {
                if (! in_array($syncColumn, $attributesForFixKeyName, true)) {
                    $attributesForFixKeyName[] = $syncColumn;
                }
            }

            $modifiedItems = $this->importDataPreparer->modifyItemsAttributes(
                $tableData['items'],
                $attributesForFixKeyName,
                $tableData['modifiedAttributes'],
                $this->existItemsByTable
            );

            if ($primaryColumnKeyName === null) {
                //  dd($tableName, $modifiedItems, $attributesForFixKeyName, $primaryColumnKeyName);
            }

            $syncIdColumns = $tableData['syncId'];
            $syncId = new SyncId($syncIdColumns);
            $modifiedItemsOnlyFixLaterFields = $this->importDataPreparer->modifyAndSaveOnlyFixLaterFields(
                $modifiedItems,
                $attributesForFixKeyName,
                $syncId
            );

            foreach ($modifiedItemsOnlyFixLaterFields as $modifiedItemOnlyFixLaterField) {
                $query = DB::table($tableName);
                $syncIdWithValues = [];
                $attributesItemOnlyForUpdate = $modifiedItemOnlyFixLaterField;
                //   dd($syncIdColumns, $attributesItemOnlyForUpdate, $this->existItemsByTable->get($tableName),$modifiedItems, $attributesForFixKeyName);
                foreach ($syncIdColumns as $syncIdColumn) {
                    $syncIdWithValues[$syncIdColumn] = $modifiedItemOnlyFixLaterField[$syncIdColumn];
                    unset($attributesItemOnlyForUpdate[$syncIdColumn]);
                }

                $query->updateOrInsert($syncIdWithValues, $attributesItemOnlyForUpdate);
                // $queryBySyncId = $this->withConditionsBySyncIdForUpdateItem($query, $syncId, $itemLaterAndSyncFields);
                // $queryBySyncId->update($itemLaterAndSyncFields);
            }
        }
    }

    /**
     * @param array<non-empty-string, ExportModifyColumn> $modifiedAttributes
     *
     * @todo: Скорее всего нужно добавлять в экспсорте хэш syncId + добавить это в ключи для items
     *
     * @todo: В морф таблице так-то primaryKey не будет в modifiedAttributes
     */
    private function getPrimaryKeyColumn(array $modifiedAttributes): ?ExportModifySimpleColumn
    {
        return collect($modifiedAttributes)
            ->first(static fn (ExportModifyColumn $exportModifyColumn): bool => $exportModifyColumn instanceof ExportModifySimpleColumn && $exportModifyColumn->isPrimaryKey());
    }

    public function tryReplaceSourceColumn(
        string $sourceTableName,
        array &$items,
        array $syncKeys,
        string $sourcePrimaryKey,
        string $modifyColumn
    ): void {
        // dd($sourceTableName, $syncId, $items);
        throw_unless($this->existItemsByTable->has($sourceTableName), new RuntimeException);

        $existItems = $this->existItemsByTable->get($sourceTableName);

        new SyncId($syncKeys);
        // обновляем те элементы которые можем, остальные потом
        foreach ($items as &$item) {
            // $columnValues = explode('|', $item[$modifyColumn]);
            //  dd($columnValues);
            // $hashSyncKey = $syncId->keyStringByValues($columnValues);
            // dd($columnValues, $hashSyncKey);
            if (! $existItems->has($item[$modifyColumn])) {
                continue;
            }

            $item[$modifyColumn] = $existItems->get($item[$modifyColumn])[$sourcePrimaryKey];
        }

        unset($item);
    }

    private function handleAttachments(array $data, string $migrationName): void
    {
        app(AttachmentSaver::class)->upAttachments($data, $migrationName);
    }
}
