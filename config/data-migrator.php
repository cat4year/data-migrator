<?php

declare(strict_types=1);

return [
    /**
     * Если колонка связи - изменчивый инкрементарный id
     * Если в модели не указана конкретная колонка с помощью свойства migrationColumnKey
     * true - Пытаемся найти по индексам уникальную колонку типо slug. Если не нашли - выбрасываем исключение
     * false - Выбрасываем исключение, т.к. не можем мигрировать поля с инкрементарным id
     */
    'try_find_unique_relation_column' => env('MIGRATION_TRY_FIND_UNIQUE_RELATION_COLUMN', true),
    'migrations_path' => env('MIGRATIONS_PATH'),
];
