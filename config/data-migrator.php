<?php

declare(strict_types=1);

return [
    'migrations_path' => env('MIGRATIONS_PATH', database_path('migrations')),
    'model_config_map' => [],
    /**
     * Запретить для модели какие-то связи по имени метода.
     * Model1::class => ['relationNameForExclude1', 'relationNameForExclude2'],
     * Model2::class => ['relationNameForExclude1', 'relationNameForExclude2'],
     * или строкой, а не массивом, если нужно исключить всего одну связь для модели
     * Model1::class => 'relationNameForExclude',
     * Model2::class => 'relationNameForExclude',
     */
    'exclude_relations_by_model_map' => [],
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
];
