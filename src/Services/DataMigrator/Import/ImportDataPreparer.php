<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Import;

use Cat4year\DataMigrator\Entity\ExportModifyColumn;
use Cat4year\DataMigrator\Entity\ExportModifyMorphColumn;
use Cat4year\DataMigrator\Entity\ExportModifySimpleColumn;
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
                $existItemsByTable,
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
     * @param SupportCollection<int, SupportCollection> $existItemsByTable Существующие элементы
     * @param string $uniqueKeyName Имя уникального ключа
     * @return array Обработанный элемент
     */
    private function itemWithAutoincrementRelationFields(
        array $item,
        array $modifiedAttributes,
        SupportCollection $existItemsByTable,
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

            $existRelationTableItems = $existItemsByTable->get($modifyTable);

            if (empty($existRelationTableItems)) {
                continue;
            }

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
     * @param SupportCollection|null $existItems Существующие элементы
     * @param string $attributeKeyName Имя атрибута
     * @param ExportModifyColumn $modifyAttribute Правила модификации
     * @return array Обработанный элемент или null если обработка пропущена
     */
    private function autoincrementRelationField(
        array $item,
        ?SupportCollection $existItems,
        string $attributeKeyName,
        ExportModifyColumn $modifyAttribute,
        array $needFixLater,
        array $syncId,
    ): array {
        $maybeModifiedItem = $this->invertModifyAttribute(
            $item,
            $existItems,
            $attributeKeyName,
            $modifyAttribute
        );

        $isModifiedItem = ! empty(array_diff_assoc($maybeModifiedItem, $item)) || ! empty(array_diff_assoc($item, $maybeModifiedItem));

        if ($isModifiedItem) {
            return $maybeModifiedItem;
        }

        if (
            !in_array($attributeKeyName, $syncId, true)
            && $modifyAttribute->isNullable()
            && in_array($attributeKeyName, $needFixLater, true)
        ) {
            $item[$attributeKeyName] = null;

            return $item;
        }

        if ($modifyAttribute->isAutoincrement() === true) {
            unset($item[$attributeKeyName]);
        }

        return $item;
    }

    public function invertModifyAttribute(
        array $item,
        ?SupportCollection $existItems,
        string $attributeKeyName,
        ExportModifyColumn $modifyAttributeData
    ): array {
        if ($existItems === null || $existItems->isEmpty()) {
            return $item;
        }

        $keyValue = $item[$attributeKeyName];

        if ($modifyAttributeData instanceof ExportModifyMorphColumn) {
            $morphType = $modifyAttributeData->getMorphType();
            $morphClass = $item[$morphType];
            $modifyTable = app($morphClass)->getTable(); // todo: оптимизировать получение таблицы по классу
            $oldKeyName = $modifyAttributeData->getSourceOldKeyNames()[$modifyTable] ?? null;
        } else {
            $oldKeyName = $modifyAttributeData->getSourceKeyName();
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

    public function beforeSyncWithDatabase(array $items, array $modifiedAttributes): array
    {
        $primaryKey = $this->getPrimaryKeyForUnsetBeforeSync($modifiedAttributes);

        if ($primaryKey !== null) {
            return $this->removeOldPrimaryKeyBeforeSave($primaryKey, $items);
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

    /**
     * @todo: В морф таблице так-то primaryKey не будет в modifiedAttributes
     * @param array<non-empty-string, ExportModifyColumn> $modifiedAttributes
     * @return string|null
     */
    private function getPrimaryKeyForUnsetBeforeSync(array $modifiedAttributes): ?string
    {
        $primaryKeyColumn = $this->getPrimaryKeyColumn($modifiedAttributes);

        if ($primaryKeyColumn === null) {
            //todo: добавить поиск primaryKey, которого не было в modifiedAttributes?
            return null;
        }

        $sourceUniqueKeyName = $primaryKeyColumn->getSourceUniqueKeyName();
        $sourceKeyName = $primaryKeyColumn->getSourceKeyName();

        if ($sourceUniqueKeyName !== null && $sourceUniqueKeyName === $sourceKeyName) {
            $sourceKeyName = null;
        }

        return $sourceKeyName;
    }

    /**
     * @todo: В морф таблице так-то primaryKey не будет в modifiedAttributes
     * @param array<non-empty-string, ExportModifyColumn> $modifiedAttributes
     */
    private function getPrimaryKeyColumn(array $modifiedAttributes): ?ExportModifySimpleColumn
    {
        return collect($modifiedAttributes)
            ->first(static fn(ExportModifyColumn $column) => $column instanceof ExportModifySimpleColumn && $column->isPrimaryKey());
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
            if (!in_array($attributeKeyName, $syncId, true) && $modifyAttributeData->isNullable()) {
                $fixLaterFields[] = $attributeKeyName;
            }
        }

        return $fixLaterFields;
    }

    /**
     * @param array $items
     * @param list<string> $attributesForFixKeyName
     * @param array<non-empty-string, ExportModifyColumn> $attributesMetaData
     * @param SupportCollection $existItems
     * @return array
     */
    public function modifyItemsAttributes(
        array $items,
        array $attributesForFixKeyName,
        array $attributesMetaData,
        SupportCollection $existItems
    ): array {
        $result = [];
        foreach ($items as $item) {
            foreach ($attributesForFixKeyName as $attributeForFixKeyName) {
                $attributeModify = $attributesMetaData[$attributeForFixKeyName];
                $existRelationTableItems = $existItems->get($attributeModify->getSourceTableName());

                $item = $this->invertModifyAttribute(
                    $item,
                    $existRelationTableItems,
                    $attributeForFixKeyName,
                    $attributeModify
                );
            }

            $result[] = $item;
        }

        return $result;
    }

    public function modifyAndSaveOnlyFixLaterFields(array $items, array $neededAttributes, string $primaryColumnKeyName): array
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

            $uniqueIdAttributeValue = $item[$primaryColumnKeyName];
            $result[$uniqueIdAttributeValue] = $newItem;
        }

        return $result;
    }
}
