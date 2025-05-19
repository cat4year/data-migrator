<?php

namespace Cat4year\DataMigrator\Services\DataMigrator\Tools;

use Cat4year\DataMigrator\Entity\SyncId;
use RuntimeException;

final class SyncIdState
{
    public function __construct(private readonly TableService $tableService) {}

    /**
     * @var array<string, list<string|list<string>>>
     */
    private array $potentialSyncIds = [];
    /**
     * @var array<string, SyncId>
     */
    private array $syncIds = [];

    public static function makeHashSyncId(array|string $keys): string
    {
        if (is_string($keys)) {
            return $keys;
        }

        if (count($keys) === 1) {
            return current($keys);
        }

        sort($keys);
        return md5(json_encode($keys, JSON_THROW_ON_ERROR));
    }

    /**
     * @todo Добавить проверок корректности + логирование или ошибки для разработчика по моделям
     * В идеале если не unique отдавать ошибку
     * Иначе желательно хотя бы на nullable проверять
     * Гарантия есть только когда колонка таблицы как unique в БД.
     * @todo: сделать чекер для разработчика. Чтобы он указал уникальные колонки для проблемных таблиц и не боялся за дубли
     */
    public function tableSyncId(string $table): SyncId
    {
        if (!isset($this->syncIds[$table])) {
            $this->syncIds[$table] = $this->makeSyncId($table);
        }

        return $this->syncIds[$table];
    }

    private function makeSyncId(string $tableName): SyncId
    {
        $potentialSyncIds = $this->potentialSyncIds($tableName);
        if($tableName === 'slug_secondables'){
            echo 'hello';
            // dd($potentialSyncIds);
        }

        $potentialSyncId = $this->firstUniquePotentialSyncIds($potentialSyncIds, $tableName);
        if (!is_array($potentialSyncId)) {
            $potentialSyncId = [$potentialSyncId];
        }

        return new SyncId($potentialSyncId);
    }

    private function potentialSyncIds(string $table): array
    {
        return $this->potentialSyncIds[$table] ?? $this->makePotentialSyncIds($table);
    }

    private function makePotentialSyncIds(string $tableName): array
    {
        while (true) {
            $potentialValue = $this->makePotentialSyncId($tableName);

            if ($potentialValue === null) {
                break;
            }

            $this->potentialSyncIds[$tableName][] = $potentialValue;

            if (is_array($potentialValue)) {
                break;
            }
        }

        if (empty($this->potentialSyncIds[$tableName])) {
            throw new RuntimeException(
                'Не смогли определить уникальный идентификатор для таблицы ' . $tableName
            );
        }

        return $this->potentialSyncIds[$tableName];
    }

    private function makePotentialSyncId(string $tableName): array|string|null
    {
        $syncIdByTables = config('data-migrator.table_sync_id', []);
        if (
            isset($syncIdByTables[$tableName])
            && !$this->hasPotentialSyncId($tableName, self::makeHashSyncId($syncIdByTables[$tableName]))
        ) {
            return $syncIdByTables[$tableName];//нет гарантии, что не будет дублей с этим ключом/ключами
        }

        try {
            $model = $this->tableService->identifyModelByTable($tableName);

            if ($model === null) {
                throw new RuntimeException('Модель не идентифицирована по таблице '. $tableName);
            }

            if (!$model->getIncrementing() && !$this->hasPotentialSyncId($tableName, $model->getKeyName())) {
                //нет гарантии, что разработчик не переопределил $incrementing в модели ошибочно. Нужна доп. проверка в БД
                //нет гарантии, что не будет дублей с этим якобы уникальным идентификатором
                return $model->getKeyName();
            }

            if (method_exists($model, 'uniqueIds') ) {
                $tableColumns = $this->tableService->schemaState()->columns($model->getTable());
                //$maybeUniqueColumn = null;
                foreach ($model->uniqueIds() as $uniqueId) {
                    if (!isset($tableColumns[$uniqueId])) {
                        continue;
                    }

                    if (
                        $tableColumns[$uniqueId]['unique'] === true
                        && !$this->hasPotentialSyncId($tableName, $uniqueId)
                    ) {
                        return $uniqueId;
                    }

                    //$maybeUniqueColumn = $uniqueId;
                }

                //throw new RuntimeException('Не найдено подходящего уникального ключа в uniqueIds');
                //return $maybeUniqueColumn;//нет гарантии, что не будет дублей с этим якобы уникальным идентификатором
            }

            $foundPotentialColumn = $this->tableService->tryFindUniqueColumnsByIndex($tableName);
            if (!$this->hasPotentialSyncId($tableName, self::makeHashSyncId($foundPotentialColumn))) {
                return $foundPotentialColumn;
            }
        } catch (RuntimeException) {
        }

        return null;
    }

    private function hasPotentialSyncId(string $tableName, string $value): bool
    {
        return !empty($value) && in_array($value, $this->potentialSyncIds[$tableName] ?? [], true);
    }

    /**
     * @param  list<string|list<string>> $potentialSyncIds
     * @return string|list<string>
     */
    private function firstUniquePotentialSyncIds(array $potentialSyncIds, string $tableName): string|array
    {
        foreach ($potentialSyncIds as $potentialSyncId) {
            if (is_array($potentialSyncId)) {
                $isGoodPotentialSyncId = $this->isGoodPotentialCompoundSyncId($potentialSyncId, $tableName);
            } else {
                $isGoodPotentialSyncId = $this->isGoodPotentialStringSyncId($potentialSyncId, $tableName);
            }

            if ($isGoodPotentialSyncId === true) {
                return $potentialSyncId;
            }
        }

        throw new RuntimeException(sprintf('Ни один ключ синхронизации таблицы %s не подходит', $tableName));
    }

    private function isGoodPotentialCompoundSyncId(array $compoundPotentialSyncId, string $tableName): bool
    {
        return array_all(
            $compoundPotentialSyncId,
            fn($column) => $this->isGoodPotentialCompoundPartSyncId($column, $tableName) !== false
        );

    }

    private function isGoodPotentialCompoundPartSyncId(string $potentialSyncId, string $tableName): bool
    {
        $columns = $this->tableService->schemaState()->columns($tableName);

        if (isset($columns[$potentialSyncId])) {
            //желательно еще на nullable, но это не точно
            //можно снова с индексом сверять, но можем сильно отсеить и не получить ключа в итоге, т.к. в конфиге плохо зададут
            return $columns[$potentialSyncId]['auto_increment'] === false;
        }

        return false;
    }

    private function isGoodPotentialStringSyncId(string $potentialSyncId, string $tableName): bool
    {
        $columns = $this->tableService->schemaState()->columns($tableName);

        if (isset($columns[$potentialSyncId])) {
            return $columns[$potentialSyncId]['auto_increment'] === false;
        }

        return false;
    }
}
