<?php

namespace Cat4year\DataMigrator\Services\DataMigrator\Export;

use Cat4year\DataMigrator\Entity\ExportModifyColumn;
use Cat4year\DataMigrator\Entity\ExportModifyMorphColumn;
use Cat4year\DataMigrator\Services\DataMigrator\Export\Relations\RelationFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final readonly class ExportModifier
{
    public function __construct(
        private RelationFactory $factory,
        private SupportCollection $entitiesCollections,
        private SupportCollection $entityClasses
    )
    {
    }

    public function modify(): array
    {
        $entitiesModifyInfo = $this->makeAndHandleModifyInfo();

        if (empty($entitiesModifyInfo)) {
            return $this->entitiesCollections->toArray();
        }

        return $this->modifyColumnsValues($entitiesModifyInfo);
    }


    public function makeAndHandleModifyInfo(): array
    {
        $modifyInfo = $this->makeModifyInfo();

        return $this->handleModifyInfo($modifyInfo);
    }

    public function makeModifyInfo(): array
    {
        $result = [];
        /** может быть избыточно если какаие-то связи отвалились */
        foreach ($this->entityClasses as $entityTable => $relationsByEntity) {
            // получить данные слагов для всех связанных моделей из $entitiesCollections
            foreach ($relationsByEntity as $relationName => $relation) {
                assert($relation instanceof Relation);

                $relationModifier = $this->factory->createByRelation($relation, $this->entitiesCollections);

                if ($relationModifier === null) {
                    continue;
                }

                $result[$entityTable . '|' . $relationName] = $relationModifier->getModifyInfo();
            }
        }

        return $result;
    }

    /**
     * @todo: можно это зашить в morph экспортеры сразу?
     */
    public function handleModifyInfo(array $entitiesModifyInfo): array
    {
        $result = [];
        foreach ($entitiesModifyInfo as $modifyItemsForRelationName) {
            foreach ($modifyItemsForRelationName as $table => $modifyInfo) {
                foreach ($modifyInfo as $attributeKeyName => $modifyInfoForKey) {
                    assert($modifyInfoForKey instanceof ExportModifyColumn);

                    if (!$modifyInfoForKey instanceof ExportModifyMorphColumn) {
                        $result[$table][$attributeKeyName] = $modifyInfoForKey;

                        continue;
                    }

                    // морф связь. объединяем данные ключей для разных типов
                    assert($modifyInfoForKey instanceof ExportModifyMorphColumn);
                    if (! isset($result[$table][$attributeKeyName])) {
                        $result[$table][$attributeKeyName] = $modifyInfoForKey;
                    } else {
                        //todo: разбить разные морф таблицы на разные экземпляры? и не завязыватья на ключе?
                        $result[$table][$attributeKeyName]->addKeyNames($modifyInfoForKey->getSourceKeyNames());
                        $result[$table][$attributeKeyName]->addOldKeyNames($modifyInfoForKey->getSourceOldKeyNames());
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @param array<string, ExportModifyColumn> $entitiesModifyInfo
     */
    public function modifyColumnsValues(array $entitiesModifyInfo): array
    {

        $result = [];
        $entities = $this->entitiesCollections->toArray();
        dd($entities);
        foreach ($entities as $tableName => $entityInfo) {
            foreach ($entityInfo['items'] as $itemKey => $item) {
                if (is_array($item)) {
                    $attributes = $item;
                } else {
                    assert($item instanceof Model);
                    $attributes = $item->getAttributes();
                }

                $result[$tableName]['items'][$itemKey] = $attributes;
                //todo: правильный ли подход? Может стоит идти по конкретным полям, которые нужно модифицировать
                foreach ($attributes as $attributeKeyName => $attributeValue) {
                    if (
                        !isset($entitiesModifyInfo[$tableName][$attributeKeyName])
                        || $attributeValue === null
                    ) {
                        continue;
                    }

                    // заменяем значение каждого атрибута на уникальный строковый ключ
                    $modifyInfoByKey = $entitiesModifyInfo[$tableName][$attributeKeyName];
                    assert($modifyInfoByKey instanceof ExportModifyColumn);

                    if ($modifyInfoByKey instanceof ExportModifyMorphColumn) {
                        $morphType = $modifyInfoByKey->getMorphType();
                        $morphClass = $attributes[$morphType];
                        $modifyInfoTable = app($morphClass)->getTable();
                        $modifyKeyNames = $modifyInfoByKey->getSourceKeyNames()[$modifyInfoTable];

                        //todo
                        if (is_string($modifyKeyNames)) {
                            $modifyKeyName = $modifyKeyNames;
                        } elseif (is_array($modifyKeyNames) && count($modifyKeyNames) === 1) {
                            $modifyKeyName = $modifyKeyNames[0];
                        } else {
                            dd($modifyKeyNames); //in_array может проверять с $attributeKeyName?
                        }

                    } else {
                        $modifyInfoTable = $modifyInfoByKey->getSourceTableName();
                        $modifyKeyName = $modifyInfoByKey->getSourceUniqueKeyName();
                    }

                    try {
                        if (!isset($entities[$modifyInfoTable])) {
                            /**
                             * todo: надо решить как тут действовать.
                             * todo: Либо отменять экспорт, либо пропускать с неизменным автоинкрементным полем
                             **/
                            throw new RuntimeException('Отсутствуют данные таблицы источника для подмены колонки');
                        }

                        $tableForFindNewAttributeValue = $entities[$modifyInfoTable];
                        $newValue = $tableForFindNewAttributeValue['items'][$attributeValue][$modifyKeyName];
                        $result[$tableName]['items'][$itemKey][$attributeKeyName] = $newValue; // todo: variable not found
                    } catch (Throwable) {
                        Log::error('Ошибка при подмене колонки', [
                            $entities,
                            $modifyInfoTable,
                            $attributeValue,
                            $modifyKeyName,
                        ]);
                    }
                }
            }
            $result[$tableName]['modifiedAttributes'] = $entitiesModifyInfo[$tableName];
        }

        return $result;
    }

    public function addModifyInfo()
    {

    }

}
