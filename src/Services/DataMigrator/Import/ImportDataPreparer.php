<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Import;

use Illuminate\Support\Collection as SupportCollection;

final readonly class ImportDataPreparer
{
    /**
     * @param array{items: array, modifiedAttributes: array} $tableData
     * @param SupportCollection<int, SupportCollection> $existItemsByTable
     * @return array{items: array, needFixLater: list<non-empty-string>}
     */
    public function itemsWithAutoincrementRelationFields(
        array $tableData,
        SupportCollection $existItemsByTable,
        string $uniqueKeyName
    ): array {
        $resultItems = [];

        $needFixLater = $this->identifyFixLaterRelationFields(
            $tableData['modifiedAttributes'],
            $uniqueKeyName
        );

        foreach ($tableData['items'] as $item) {
            $resultItems[] = $this->itemWithAutoincrementRelationFields(
                $item,
                $tableData['modifiedAttributes'],
                $existItemsByTable,
                $uniqueKeyName,
                $needFixLater
            );
        }

        return ['items' => $resultItems, 'needFixLater' => $needFixLater];
    }

    /**
     * Обрабатывает отдельный элемент согласно правилам модификации
     *
     * @param array $item Элемент для обработки
     * @param array $modifiedAttributes Правила модификации атрибутов
     * @param SupportCollection<int, SupportCollection> $existItemsByTable Существующие элементы
     * @param string $uniqueKeyName Имя уникального ключа
     * @return array Обработанный элемент
     */
    private function itemWithAutoincrementRelationFields(
        array $item,
        array $modifiedAttributes,
        SupportCollection $existItemsByTable,
        string $uniqueKeyName,
        array $needFixLater,
    ): array {
        foreach ($modifiedAttributes as $attributeKeyName => $modifyAttributeData) {
            if (! isset($modifyAttributeData['table'])) {
                $morphType = $modifyAttributeData['morphType'];
                $morphClass = $item[$morphType];
                $modifyTable = app($morphClass)->getTable();
            } else {
                $modifyTable = $modifyAttributeData['table'];
            }

            $existRelationTableItems = $existItemsByTable->get($modifyTable);

            if (empty($existRelationTableItems)) {
                continue;
            }

            $item = $this->autoincrementRelationField(
                $item,
                $existRelationTableItems,
                $attributeKeyName,
                $modifyAttributeData,
                $needFixLater,
                $uniqueKeyName
            );
        }

        return $item;
    }

    /**
     * Обрабатывает отдельный атрибут элемента
     *
     * @param array $item Элемент для обработки
     * @param SupportCollection|null $existItems Существующие элементы
     * @param string $attributeKeyName Имя атрибута
     * @param array $modifyAttributeData Правила модификации
     * @return array Обработанный элемент или null если обработка пропущена
     */
    private function autoincrementRelationField(
        array $item,
        ?SupportCollection $existItems,
        string $attributeKeyName,
        array $modifyAttributeData,
        array $needFixLater,
        string $uniqueKeyName,
    ): array {
        $maybeModifiedItem = $this->invertModifyAttribute(
            $item,
            $existItems,
            $attributeKeyName,
            $modifyAttributeData
        );

        $isModifiedItem = ! empty(array_diff_assoc($maybeModifiedItem, $item)) || ! empty(array_diff_assoc($item, $maybeModifiedItem));

        if ($isModifiedItem) {
            return $maybeModifiedItem;
        }

        if (
            $modifyAttributeData['nullable'] === true
            && $attributeKeyName !== $uniqueKeyName
            && in_array($attributeKeyName, $needFixLater, true)
        ) {
            $item[$attributeKeyName] = null;

            return $item;
        }

        if (isset($modifyAttributeData['autoIncrement']) &&
            $modifyAttributeData['autoIncrement'] === true) {
            unset($item[$attributeKeyName]);
        }

        return $item;
    }

    public function invertModifyAttribute(
        array $item,
        ?SupportCollection $existItems,
        string $attributeKeyName,
        array $modifyAttributeData
    ): array {
        if ($existItems === null || $existItems->isEmpty()) {
            return $item;
        }

        $keyValue = $item[$attributeKeyName];

        if (isset($modifyAttributeData['oldKeyNames'])) {
            $morphType = $modifyAttributeData['morphType'];
            $morphClass = $item[$morphType];
            $modifyTable = app($morphClass)->getTable(); // todo: оптимизировать получение таблицы по классу
            $oldKeyName = $modifyAttributeData['oldKeyNames'][$modifyTable] ?? null;
        } else {
            $oldKeyName = $modifyAttributeData['oldKeyName'];
        }

        if (! isset($oldKeyName)) {
            return $item;
        }

        $existItem = $existItems->get($keyValue);
        if ($existItem === null || ! array_key_exists($oldKeyName, $existItem)) {
            return $item;
        }

        $item[$attributeKeyName] = $existItem[$oldKeyName];

        return $item;
    }

    public function beforeSyncWithDatabase(array $items, array $tableMetaData): array
    {
        $oldUniqueKeyName = $this->getOldUniqueKeyName($tableMetaData);

        if ($oldUniqueKeyName !== null) {
            return $this->removeOldPrimaryKeyBeforeSave($oldUniqueKeyName, $items);
        }

        return $items;
    }

    private function removeOldPrimaryKeyBeforeSave(string $oldPrimaryUniqueKeyName, array $items): array
    {
        if (! empty($oldPrimaryUniqueKeyName)) {
            foreach ($items as &$item) {
                unset($item[$oldPrimaryUniqueKeyName]);
            }
            unset($item);
        }

        return $items;
    }

    private function getOldUniqueKeyName(array $tableMetaData): ?string
    {
        $uniqueKeyName = $tableMetaData['keyName'];
        $oldUniqueKeyName = $tableMetaData['oldKeyName'] ?? null;

        if ($oldUniqueKeyName !== null && $oldUniqueKeyName === $uniqueKeyName) {
            $oldUniqueKeyName = null;
        }

        return $oldUniqueKeyName;
    }

    private function identifyFixLaterRelationFields(
        array $modifiedAttributes,
        string $uniqueKeyName
    ): array {
        $fixLaterFields = [];

        foreach ($modifiedAttributes as $attributeKeyName => $modifyAttributeData) {
            if ($modifyAttributeData['nullable'] === true && $attributeKeyName !== $uniqueKeyName) {
                $fixLaterFields[] = $attributeKeyName;
            }
        }

        return $fixLaterFields;
    }

    public function modifyItemsAttributes(
        array $items,
        array $attributesForFixKeyName,
        array $attributesMetaData,
        SupportCollection $existItems
    ): array {
        $result = [];
        foreach ($items as $item) {
            foreach ($attributesForFixKeyName as $attributeForFixKeyName) {
                $attributeModifyData = $attributesMetaData[$attributeForFixKeyName];
                $existRelationTableItems = $existItems->get($attributeModifyData['table']);

                $item = $this->invertModifyAttribute(
                    $item,
                    $existRelationTableItems,
                    $attributeForFixKeyName,
                    $attributeModifyData
                );
            }

            $result[] = $item;
        }

        return $result;
    }

    public function modifyAndSaveOnlyRelationFields(array $items, array $neededAttributes, string $uniqueIdAttribute): array
    {
        $result = [];
        foreach ($items as $item) {
            $newItem = [];

            foreach ($neededAttributes as $attribute) {
                if (! array_key_exists($attribute, $item)) {
                    continue;
                }

                $newItem[$attribute] = $item[$attribute];
            }

            $uniqueIdAttributeValue = $item[$uniqueIdAttribute];
            $result[$uniqueIdAttributeValue] = $newItem;
        }

        return $result;
    }
}
