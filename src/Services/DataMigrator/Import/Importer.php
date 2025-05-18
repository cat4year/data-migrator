<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Import;

use Cat4year\DataMigrator\Entity\ExportModifyColumn;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\CollectionMerger;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\DataSource\MigrationDataSourceFormat;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\TableService;
use Cat4year\DataMigratorTests\App\Models\SlugFirst;
use DB;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PHPUnit\TextUI\XmlConfiguration\MigrationException;
use RuntimeException;
use stdClass;
use Throwable;

final readonly class Importer
{
    /**
     * @param SupportCollection<string, SupportCollection> $existItemsByTable
     */
    public function __construct(
        private MigrationDataSourceFormat $sourceFormat,
        private TableService $tableService,
        private ImportDataPreparer $preparer,
        private SupportCollection $existItemsByTable,
        private CollectionMerger $collectionMerger,
    ) {
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


        $uniqueIdItemsValues = $this->getUniqueIdsByTable($data);
        dd($uniqueIdItemsValues);
//        if (empty($uniqueIdItemsValues)) {
//            throw new RuntimeException('Нет уникальных id во всех таблицах');
//        }
        $this->collectExistDataByTable($data, $uniqueIdItemsValues);
        $this->syncWithoutAutoincrementRelationFields($data, $uniqueIdItemsValues);
        $this->syncWithAutoincrementRelationFields($data, $uniqueIdItemsValues);
    }

    private function getUniqueIdsByTable(array $data): array
    {
        $result = [];

        foreach ($data as $tableName => $tableData) {
            if (!isset($tableData['modifiedAttributes'])) {
                continue;
            }

            //todo: нужно будет поменять под историю с комплексным ключом для синхронизации

            //todo: по-идее просто убираем unqueIdAttribute и меняем на getSourceKeyName?
            $uniqueIdAttribute = $this->identifyUniqueAttribute($tableData['modifiedAttributes']);
            $result[$tableName]['items'] = array_column($tableData['items'], $uniqueIdAttribute);
            $currentModifyInfo = $tableData['modifiedAttributes'][$uniqueIdAttribute];
            assert($currentModifyInfo instanceof ExportModifyColumn);

            if ($currentModifyInfo->getSourceTableName() === $tableName) {
                $result[$tableName]['table'] = $currentModifyInfo->getTableName();
                $result[$tableName]['keyName'] = $currentModifyInfo->getSourceUniqueKeyName();
                $result[$tableName]['oldKeyName'] = $currentModifyInfo->getSourceKeyName();
                $result[$tableName]['loadExist'] = true;
            } else {
                $result[$tableName]['table'] = $tableName;
                $result[$tableName]['keyName'] = $uniqueIdAttribute;
                $result[$tableName]['loadExist'] = false;
            }
        }

        return $result;
    }

    /**
     * @param array<non-empty-string, ExportModifyColumn> $modifiedAttributes
     */
    private function identifyUniqueAttribute(array $modifiedAttributes): string
    {
        foreach ($modifiedAttributes as $attributeKey => $modifyInfo) {
            if ($modifyInfo->isPrimarykey()) {
                return $attributeKey;
            }
        }

        throw new RuntimeException('Не смогли определить уникальный id для таблицы ');
    }

    private function collectExistDataByTable(array $data, array $uniqueIdItemsValues): void
    {
        foreach ($data as $tableName => $tableData) {
            if (!isset($tableData['modifiedAttributes'])) {
                continue;
            }

            $currentData = $uniqueIdItemsValues[$tableName];
            $values = $currentData['items'];
            $keyName = $currentData['keyName'];
            $tableForPrimaryKey = $currentData['table'];

            if ($currentData['loadExist'] === false) {
                continue;
            }

            $latestExistItemsByTable = collect($this->getExistItemsFromTable($tableForPrimaryKey, $keyName, $values));
            $this->collectionMerger->putWithMerge($this->existItemsByTable, $tableName, $latestExistItemsByTable);
        }
    }

    /**
     * todo: проверить этот кейс
     */
    private function syncWithoutAutoincrementRelationFields(array $data, array $uniqueIdItemsValues): void
    {
        foreach ($data as $tableName => $tableData) {
            if (isset($tableData['modifiedAttributes']) && $this->hasRelationFields($tableData['modifiedAttributes'])) {
                continue;
            }

            $uniqueKeyName = $uniqueIdItemsValues[$tableName]['keyName'];
            //todo: нужно ли?
            $itemsForSync = $this->preparer->beforeSyncWithDatabase(
                $tableData['items'],
                $uniqueIdItemsValues[$tableName]
            );
            $this->syncWithDatabase($tableName, $uniqueKeyName, $itemsForSync);
        }
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
     * Мы считаем, что все простые таблицы уже импортированы
     * Что в $data у нас отсортированные данные от простых к более зависимым
     */
    private function syncWithAutoincrementRelationFields(array $data, array $uniqueIdItemsValues): void
    {
        $needFixLater = [];

        foreach ($data as $tableName => $tableData) {
            if (!isset($tableData['modifiedAttributes']) || $this->hasRelationFields($tableData['modifiedAttributes'])) {
                continue;
            }

            $uniqueKeyName = $uniqueIdItemsValues[$tableName]['keyName'];
            $preparedFieldsData = $this->preparer->itemsWithAutoincrementRelationFields($tableData, $this->existItemsByTable, $uniqueKeyName);
            $preparedItems = $preparedFieldsData['items'];
            $needFixLater[$tableName] = $preparedFieldsData['needFixLater'];

            $itemsForSync = $this->preparer->beforeSyncWithDatabase($preparedItems, $uniqueIdItemsValues[$tableName]);
            $this->syncWithDatabase($tableName, $uniqueKeyName, $itemsForSync);
        }

        $this->fixLaterNullableRelationFields($data, $needFixLater);
    }

    private function syncWithDatabase(
        string $tableName,
        string $uniqueKeyName,
        array $itemsData,
    ): void {
        $isUniqueColumn = $this->tableService->isUniqueColumn($tableName, $uniqueKeyName);
        if (! $isUniqueColumn) {
            try {
                foreach ($itemsData as $item) {
                    DB::table($tableName)->where($uniqueKeyName, $item[$uniqueKeyName])->updateOrInsert($item);
                }
            } catch (Throwable $e) {
                Log::error('Ошибка при обновлении', [
                    $tableName,
                    $itemsData,
                    $item,
                    $uniqueKeyName,
                    $e->getMessage(),
                ]);
            }
        } else {
            // могут быть проблемы с MariaDB и MySQL
            DB::table($tableName)->upsert($itemsData, [$uniqueKeyName]);
        }

        $uniqueKeys = array_column($itemsData, $uniqueKeyName);
        $latestExistItemsByTable = collect($this->getExistItemsFromTable($tableName, $uniqueKeyName, $uniqueKeys));
        $this->collectionMerger->putWithMerge($this->existItemsByTable, $tableName, $latestExistItemsByTable);
    }

    private function getExistItemsFromTable(string $tableName, string $keyName, array $values): array
    {
        return DB::table($tableName)->whereIn($keyName, $values)->get()
            ->keyBy($keyName)
            ->map(static fn (stdClass $item) => (array) $item)->toArray();
    }

    private function fixLaterNullableRelationFields(array $data, array $fieldsForFix): void
    {
        foreach ($data as $tableName => $tableData) {
            if (! array_key_exists($tableName, $fieldsForFix)) {
                continue;
            }

            $uniqueIdAttribute = $this->identifyUniqueAttribute($tableData['modifiedAttributes']);
            $attributesForFixKeyName = $fieldsForFix[$tableName];
            if (! in_array($uniqueIdAttribute, $attributesForFixKeyName, true)) {
                $attributesForFixKeyName[] = $uniqueIdAttribute;
            }

            $items = $this->preparer->modifyItemsAttributes(
                $tableData['items'],
                $attributesForFixKeyName,
                $tableData['modifiedAttributes'],
                $this->existItemsByTable
            );

            $itemsFieldsOnlyForUpdate = $this->preparer->modifyAndSaveOnlyRelationFields(
                $items,
                $attributesForFixKeyName,
                $uniqueIdAttribute
            );

            foreach ($itemsFieldsOnlyForUpdate as $itemForUpdate) {
                DB::table($tableName)
                    ->where($uniqueIdAttribute, $itemForUpdate[$uniqueIdAttribute])
                    ->update($itemForUpdate);
            }
        }
    }
}
