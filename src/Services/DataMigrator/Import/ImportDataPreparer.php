<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Import;

use Cat4year\DataMigrator\Entity\ExportModifyColumn;
use Cat4year\DataMigrator\Entity\ExportModifyMorphColumn;
use Cat4year\DataMigrator\Entity\ExportModifySimpleColumn;
use Cat4year\DataMigrator\Entity\SyncId;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\TableService;
use Illuminate\Support\Collection as SupportCollection;

final readonly class ImportDataPreparer
{
    public function __construct(private TableService $tableService)
    {
    }

    /**
     * @param array{items: array, modifiedAttributes: array} $tableData
     * @param SupportCollection<int, SupportCollection> $supportCollection
     * @return array{items: array, needFixLater: list<non-empty-string>}
     */
    public function itemsWithAutoincrementRelationFields(
        array $tableData,
        SupportCollection $supportCollection,
        array $syncId
    ): array {
        $resultItems = [];

        $needFixLater = $this->identifyFixLaterRelationFields(
            $tableData['modifiedAttributes'],
            $syncId
        );

        foreach ($tableData['items'] as $item) {
            $resultItems[] = $this->itemWithAutoincrementRelationFields(
                $item,
                $tableData['modifiedAttributes'],
                $supportCollection,
                $syncId,
                $needFixLater
            );
        }

        return ['items' => $resultItems, 'needFixLater' => $needFixLater];
    }

    /**
     * Обрабатывает отдельный элемент согласно правилам модификации
     *
     * @param array $item Элемент для обработки
     * @param array<non-empty-string, ExportModifyColumn> $modifiedAttributes Правила модификации атрибутов
     * @param SupportCollection<int, SupportCollection> $supportCollection Существующие элементы
     * @return array Обработанный элемент
     */
    private function itemWithAutoincrementRelationFields(
        array $item,
        array $modifiedAttributes,
        SupportCollection $supportCollection,
        array $syncId,
        array $needFixLater,
    ): array {
        foreach ($modifiedAttributes as $attributeKeyName => $modifyAttribute) {
            if ($modifyAttribute instanceof ExportModifyMorphColumn) {
                $morphType = $modifyAttribute->getMorphType();
                $morphClass = $item[$morphType];
                $modifyTable = app($morphClass)->getTable();
            } else {
                $modifyTable = $modifyAttribute->getSourceTableName();
            }

            $existRelationTableItems = $supportCollection->get($modifyTable);

            $item = $this->autoincrementRelationField(
                $item,
                $existRelationTableItems,
                $attributeKeyName,
                $modifyAttribute,
                $needFixLater,
                $syncId
            );
        }

        return $item;
    }

    /**
     * Обрабатывает отдельный атрибут элемента
     *
     * @param array $item Элемент для обработки
     * @param SupportCollection|null $supportCollection Существующие элементы
     * @param string $attributeKeyName Имя атрибута
     * @param ExportModifyColumn $exportModifyColumn Правила модификации
     * @return array Обработанный элемент или null если обработка пропущена
     */
    private function autoincrementRelationField(
        array $item,
        ?SupportCollection $supportCollection,
        string $attributeKeyName,
        ExportModifyColumn $exportModifyColumn,
        array $needFixLater,
        array $syncId,
    ): array {
        if ($supportCollection?->isNotEmpty()) {
            $maybeModifiedItem = $this->invertModifyAttribute(
                $item,
                $supportCollection,
                $attributeKeyName,
                $exportModifyColumn
            );

            $isModifiedItem = array_diff_assoc($maybeModifiedItem, $item) !== [] || array_diff_assoc($item, $maybeModifiedItem) !== [];

            if ($isModifiedItem) {
                return $maybeModifiedItem;
            }
        }

        if (
            ! in_array($attributeKeyName, $syncId, true)
            && $exportModifyColumn->isNullable()
            && in_array($attributeKeyName, $needFixLater, true)
        ) {
            $item[$attributeKeyName] = null;

            return $item;
        }

        if ($exportModifyColumn->isAutoincrement()) {
            unset($item[$attributeKeyName]);
        }

        return $item;
    }

    public function invertModifyAttribute(
        array $item,
        ?SupportCollection $supportCollection,
        string $attributeKeyName,
        ExportModifyColumn $exportModifyColumn
    ): array {
        if (! $supportCollection instanceof SupportCollection || $supportCollection->isEmpty()) {
            return $item;
        }

        $keyValue = $item[$attributeKeyName];

        if ($exportModifyColumn instanceof ExportModifyMorphColumn) {
            $morphType = $exportModifyColumn->getMorphType();
            $morphClass = $item[$morphType];
            $modifyTable = app($morphClass)->getTable(); // todo: оптимизировать получение таблицы по классу
            $oldKeyName = $exportModifyColumn->getSourceOldKeyNames()[$modifyTable] ?? null;
        } else {
            $oldKeyName = $exportModifyColumn->getSourceKeyName();
        }

        if (! isset($oldKeyName)) {
            return $item;
        }

        $existItem = $supportCollection->get($keyValue);
        if ($existItem === null || ! array_key_exists($oldKeyName, $existItem)) {
            return $item;
        }

        $item[$attributeKeyName] = $existItem[$oldKeyName];

        return $item;
    }

    public function beforeSyncWithDatabase(string $tableName, array $items, array $modifiedAttributes): array
    {
        $primaryKey = $this->getPrimaryKeyForUnsetBeforeSync($tableName, $modifiedAttributes);

        if ($primaryKey !== null) {
            return $this->removeOldPrimaryKeyBeforeSave($primaryKey, $items);
        }

        return $items;
    }

    private function removeOldPrimaryKeyBeforeSave(string $oldPrimaryUniqueKeyName, array $items): array
    {
        if ($oldPrimaryUniqueKeyName !== '' && $oldPrimaryUniqueKeyName !== '0') {
            foreach ($items as &$item) {
                unset($item[$oldPrimaryUniqueKeyName]);
            }

            unset($item);
        }

        return $items;
    }

    /**
     * @todo: В морф таблице так-то primaryKey не будет в modifiedAttributes
     *
     * @param array<non-empty-string, ExportModifyColumn> $modifiedAttributes
     */
    private function getPrimaryKeyForUnsetBeforeSync(string $tableName, array $modifiedAttributes): ?string
    {
        $primaryKeyColumn = $this->getPrimaryKeyColumn($modifiedAttributes);

        if (! $primaryKeyColumn instanceof ExportModifySimpleColumn) {
            return $this->tableService->identifyPrimaryKeyNameByTable($tableName);
        }

        $syncId = $primaryKeyColumn->getSourceUniqueKeyName();
        $sourceKeyName = $primaryKeyColumn->getSourceKeyName();

        if ($syncId === $sourceKeyName) {
            return null;
        }

        return $sourceKeyName;
    }

    /**
     * @todo: В морф таблице так-то primaryKey не будет в modifiedAttributes
     *
     * @param array<non-empty-string, ExportModifyColumn> $modifiedAttributes
     */
    private function getPrimaryKeyColumn(array $modifiedAttributes): ?ExportModifySimpleColumn
    {
        return collect($modifiedAttributes)
            ->first(static fn (ExportModifyColumn $exportModifyColumn): bool => $exportModifyColumn instanceof ExportModifySimpleColumn && $exportModifyColumn->isPrimaryKey());
    }

    /**
     * @param array<non-empty-string, ExportModifyColumn> $modifiedAttributes
     */
    private function identifyFixLaterRelationFields(
        array $modifiedAttributes,
        array $syncId
    ): array {
        $fixLaterFields = [];

        foreach ($modifiedAttributes as $attributeKeyName => $modifyAttributeData) {
            if (! in_array($attributeKeyName, $syncId, true) && $modifyAttributeData->isNullable()) {
                $fixLaterFields[] = $attributeKeyName;
            }
        }

        return $fixLaterFields;
    }

    /**
     * @param list<string> $attributesForFixKeyName
     * @param array<non-empty-string, ExportModifyColumn> $attributesMetaData
     */
    public function modifyItemsAttributes(
        array $items,
        array $attributesForFixKeyName,
        array $attributesMetaData,
        SupportCollection $supportCollection
    ): array {
        $result = [];
        foreach ($items as $item) {
            foreach ($attributesForFixKeyName as $attributeForFixKeyName) {
                if (! isset($attributesMetaData[$attributeForFixKeyName])) {
                    continue;
                }

                $attributeModify = $attributesMetaData[$attributeForFixKeyName];
                if ($attributeModify instanceof ExportModifyMorphColumn) {
                    foreach (array_keys($attributeModify->getSourceTableNames()) as $sourceTableName) {
                        $existRelationTableItems = $supportCollection->get($sourceTableName);
                        $item = $this->invertModifyAttribute(
                            $item,
                            $existRelationTableItems,
                            $attributeForFixKeyName,
                            $attributeModify
                        );
                    }
                } else {
                    $existRelationTableItems = $supportCollection->get($attributeModify->getSourceTableName()); // todo: а морф?

                    $item = $this->invertModifyAttribute(
                        $item,
                        $existRelationTableItems,
                        $attributeForFixKeyName,
                        $attributeModify
                    );
                }
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * @todo: Скорее всего надо будет заменить $primaryColumnKeyName на syncIdState::makeHash
     */
    public function modifyAndSaveOnlyFixLaterFields(array $items, array $neededAttributes, SyncId $syncId): array
    {
        $result = [];

        foreach ($items as $item) {
            $newItem = [];

            foreach ($neededAttributes as $neededAttribute) {
                if (! array_key_exists($neededAttribute, $item)) {
                    continue;
                }

                $newItem[$neededAttribute] = $item[$neededAttribute];
            }

            // $uniqueIdAttributeValue = $item[$primaryColumnKeyName];
            $result[$syncId->keyStringByValues($item)] = $newItem;
        }

        return $result;
    }
}
