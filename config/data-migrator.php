<?php

declare(strict_types=1);

return [
    'migrations_path' => env('MIGRATIONS_PATH'),
    'model_config_map' => [],
    /** Если в таблице нет уникальной не инкрементарной колонки - добавьте ее в сопоставление
     * Ключ - название таблицы, Значение - название колонки
     * Будет использоваться указанная колонка приоритетно, вместо автоматического поиска подходящей колонки
     */
    'table_unique_column_map' => [
        // 'users' => ['name', 'email']
        'slug_secondables' => [
            'slug_secondable_type',
            'slug_second_id',
            'slug_secondable_id',
        ],
        'slug_firsts' => 'slug',
    ],
    /**
     * Вместо использования этой настройки, лучше добавить unique к вашим таблицам
     * Будет использоваться для приоритетной синхронизации по указанному syncId
     * Если в таблице нет уникальной не инкрементарной колонки - добавьте ее или сразу несколько колонок в сопоставление
     * Ключ - название таблицы, Значение - название колонки/колонок
     * Будет использоваться указанная колонка приоритетно, вместо автоматического поиска подходящей колонки
     */
    'table_sync_id' => [
        // 'users' => ['name', 'email']
        'slug_secondables' => [
            'slug_secondable_type',
            'slug_second_id',
            'slug_secondable_id',
        ],
        'slug_firsts' => 'slug',
        'composite_keys' => [
            'key1',
            'key2',
            'key3',
        ],
    ],
    /**
     * Если колонка связи - изменчивый инкрементарный id
     * Если в модели не указана конкретная колонка с помощью свойства migrationColumnKey
     * true - Пытаемся найти по индексам уникальную колонку типо slug. Если не нашли - выбрасываем исключение
     * false - Выбрасываем исключение, т.к. не можем мигрировать поля с инкрементарным id
     */
    'try_find_unique_relation_column' => env('MIGRATION_TRY_FIND_UNIQUE_RELATION_COLUMN', true),
    'try_use_index_for_sync_on_import' => true,
];
