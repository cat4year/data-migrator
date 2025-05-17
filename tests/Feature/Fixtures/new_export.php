<?php

declare(strict_types=1);

return [
    'slug_firsts' => [
        'items' => [
            1 => [
                'id' => 'rem-ut',
                'slug' => 'rem-ut',
                'bool_test' => false,
                'timestamp_test' => '1979-01-08 13:09:37',
                'string_test' => 'Здесь есть уникальный столбец slug и belongsTo колонка slug_three_id === 2',
                'int_test' => 13098,
                'created_at' => '2025-05-13 01:49:12',
                'slug_three_id' => 'et-repellendus-odit-possimus',
            ],
            2 => [
                'id' => 'consectetur-illum-voluptatibus',
                'slug' => 'consectetur-illum-voluptatibus',
                'bool_test' => true,
                'timestamp_test' => '2018-04-05 07:14:23',
                'string_test' => 'Здесь есть уникальный столбец slug и belongsTo колонка slug_three_id === null',
                'int_test' => 855576,
                'created_at' => '2025-05-13 01:49:12',
                'slug_three_id' => null,
            ],
            3 => [
                'id' => 'dignissimos',
                'slug' => 'dignissimos',
                'bool_test' => true,
                'timestamp_test' => '2004-05-12 00:28:59',
                'string_test' => 'Здесь есть уникальный столбец slug и belongsTo колонка slug_three_id === 1',
                'int_test' => 56598568,
                'created_at' => '2025-05-13 01:49:12',
                'slug_three_id' => 'magnam-dolorum',
            ],
        ],
        'modifiedAttributes' => [
            'slug_three_id' => [
                'tableName' => 'slug_firsts',
                'keyName' => 'slug_three_id',
                'foreignTableName' => 'slug_threes',
                'foreignUniqueKeyName' => 'slug',
                'foreignOldKeyName' => 'id',
                'nullable' => true,
                'autoincrement' => false,
                'isPrimaryKey' => false,
            ],
            'id' => [
                'tableName' => 'slug_firsts',
                'keyName' => 'id',
                'uniqueKeyName' => 'slug',
                'nullable' => false,
                'autoincrement' => true,
                'isPrimaryKey' => true,
            ],
        ],
    ],
    'slug_threes' => [
        'items' => [
            1 => [
                'id' => 'magnam-dolorum',
                'slug' => 'magnam-dolorum',
                'name' => 'Velit qui tenetur amet amet c...uatur.',
                'created_at' => '2025-05-13 02:37:33',
                'slug_second_id' => 'hic-sit-illum',
            ],
            2 => [
                'id' => 'et-repellendus-odit-possimus',
                'slug' => 'et-repellendus-odit-possimus',
                'name' => 'Nobis enim omnis et distincti...ntium.',
                'created_at' => '2025-05-13 02:37:33',
                'slug_second_id' => 'hic-sit-illum',
            ],
        ],
        'modifiedAttributes' => [
            'id' => [
                'tableName' => 'slug_threes',
                'keyName' => 'id',
                'uniqueKeyName' => 'slug',
                'nullable' => false,
                'autoincrement' => true,
                'isPrimaryKey' => true,
            ],
            'slug_second_id' => [
                'tableName' => 'slug_threes',
                'keyName' => 'slug_second_id',
                'foreignTableName' => 'slug_seconds',
                'foreignUniqueKeyName' => 'slug',
                'foreignOldKeyName' => 'id',
                'nullable' => true,
                'autoincrement' => false,
                'isPrimaryKey' => false,
            ],
        ],
    ],
    'slug_seconds' => [
        'items' => [
            1 => [
                'id' => 'autem-architecto-vel-quia-repudiandae',
                'slug' => 'autem-architecto-vel-quia-repudiandae',
                'created_at' => '2025-05-13 02:44:18',
                'name' => 'Quibusdam velit aut suscipit ...uidem.',
                'slug_first_id' => 'dignissimos',
            ],
            2 => [
                'id' => 'hic-sit-illum',
                'slug' => 'hic-sit-illum',
                'created_at' => '2025-05-13 02:44:18',
                'name' => 'Quia ipsa quas ut dolor nostr...eaque.',
                'slug_first_id' => 'consectetur-illum-voluptatibus'
            ],
            3 => [
                'id' => 'enim',
                'slug' => 'enim',
                'created_at' => '2025-05-13 02:44:18',
                'name' => 'Aut nisi aut perferendis iure eaque.',
                'slug_first_id' => 'rem-ut',
            ],
        ],
        'modifiedAttributes' => [
            'id' => [
                'tableName' => 'slug_seconds',
                'keyName' => 'id',
                'uniqueKeyName' => 'slug',
                'nullable' => false,
                'autoincrement' => true,
                'isPrimaryKey' => true,
            ],
            'slug_first_id' => [
                'tableName' => 'slug_seconds',
                'keyName' => 'slug_first_id',
                'foreignTableName' => 'slug_firsts',
                'foreignUniqueKeyName' => 'slug',
                'foreignOldKeyName' => 'id',
                'nullable' => false,
                'autoincrement' => false,
                'isPrimaryKey' => false,
            ],
        ],
    ],
];
