<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Export;

use Cat4year\DataMigrator\Entity\ExportModifyColumn;
use Cat4year\DataMigrator\Entity\ExportModifyMorphColumn;
use Cat4year\DataMigrator\Entity\SyncId;
use Cat4year\DataMigrator\Exceptions\Export\SourceItemNotFoundException;
use Cat4year\DataMigrator\Services\DataMigrator\Export\Relations\RelationExporter;
use Cat4year\DataMigrator\Services\DataMigrator\Export\Relations\RelationFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection as SupportCollection;
use RuntimeException;

final readonly class ExportModifier
{
    public function __construct(
        private RelationFactory $relationFactory,
        private SupportCollection $entitiesCollections,
        private SupportCollection $entityClasses
    ) {
    }

    public function modify(): array
    {
        $entitiesModifyInfo = $this->makeAndHandleModifyInfo();

        if ($entitiesModifyInfo === []) {
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

                $relationModifier = $this->relationFactory->createByRelation($relation, $this->entitiesCollections);

                if (! $relationModifier instanceof RelationExporter) {
                    continue;
                }

                $result[$entityTable.'|'.$relationName] = $relationModifier->getModifyInfo();
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
        foreach ($entitiesModifyInfo as $entityModifyInfo) {
            foreach ($entityModifyInfo as $table => $modifyInfo) {
                foreach ($modifyInfo as $attributeKeyName => $modifyInfoForKey) {
                    assert($modifyInfoForKey instanceof ExportModifyColumn);

                    if (! $modifyInfoForKey instanceof ExportModifyMorphColumn) {
                        $result[$table][$attributeKeyName] = $modifyInfoForKey;

                        continue;
                    }

                    // морф связь. объединяем данные ключей для разных типов
                    assert($modifyInfoForKey instanceof ExportModifyMorphColumn);
                    if (! isset($result[$table][$attributeKeyName])) {
                        $result[$table][$attributeKeyName] = $modifyInfoForKey;
                    } else {
                        // todo: разбить разные морф таблицы на разные экземпляры? и не завязыватья на ключе?
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
        foreach ($entities as $tableName => $entityInfo) {
            foreach ($entityInfo['items'] as $itemKey => $item) {
                if (is_array($item)) {
                    $attributes = $item;
                } else {
                    assert($item instanceof Model);
                    $attributes = $item->getAttributes();
                }

                $result[$tableName]['items'][$itemKey] = $attributes;
                // todo: правильный ли подход? Может стоит идти по конкретным полям, которые нужно модифицировать
                foreach ($attributes as $attributeKeyName => $attributeValue) {
                    if (! isset($entitiesModifyInfo[$tableName][$attributeKeyName])) {
                        continue;
                    }

                    if ($attributeValue === null) {
                        continue;
                    }

                    // заменяем значение каждого атрибута на уникальный строковый ключ
                    $modifyInfoByKey = $entitiesModifyInfo[$tableName][$attributeKeyName];
                    assert($modifyInfoByKey instanceof ExportModifyColumn);

                    if ($modifyInfoByKey instanceof ExportModifyMorphColumn) {
                        $morphType = $modifyInfoByKey->getMorphType();
                        $morphClass = $attributes[$morphType];
                        $modifyInfoTable = app($morphClass)->getTable();
                        $syncId = $modifyInfoByKey->getSourceUniqueKeyNameByTable($modifyInfoTable);
                        $sourceKeyName = $modifyInfoByKey->getSourceKeyNameByTable($modifyInfoTable);
                    } else {
                        $modifyInfoTable = $modifyInfoByKey->getSourceTableName();
                        $sourceKeyName = $modifyInfoByKey->getSourceKeyName();
                        $syncId = $modifyInfoByKey->getSourceUniqueKeyName();
                    }

                    assert($sourceKeyName !== null);
                    assert($syncId instanceof SyncId);

                    /**
                     * todo: надо решить как тут действовать.
                     * todo: Либо отменять экспорт, либо пропускать с неизменным автоинкрементным полем
                     */
                    throw_unless(isset($entities[$modifyInfoTable]['items']), new RuntimeException('Отсутствуют данные таблицы источника для подмены колонки'));

                    try {
                        $newSyncValue = $this->getSyncStringFromSource(
                            $entities[$modifyInfoTable]['items'],
                            $sourceKeyName,
                            $attributeValue,
                            $syncId
                        );

                        $result[$tableName]['items'][$itemKey][$attributeKeyName] = $newSyncValue;
                    } catch (SourceItemNotFoundException) {
                        continue;
                    }
                }
            }

            $result[$tableName]['modifiedAttributes'] = $entitiesModifyInfo[$tableName];
            $result[$tableName]['syncId'] = $entityInfo['syncId'];
        }

        return $result;
    }

    /**
     * @throws SourceItemNotFoundException
     */
    private function getSyncStringFromSource(array $items, string $key, string|int $value, SyncId $syncId): string
    {
        $sourceItem = collect($items)->first(static fn ($item): bool => $item[$key] === $value);

        throw_if($sourceItem === null, new SourceItemNotFoundException('Source item not found for attribute value: '.$value));

        return $syncId->keyStringByValues($sourceItem);
    }
}
