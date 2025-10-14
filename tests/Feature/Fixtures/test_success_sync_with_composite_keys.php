<?php

return [
    'composite_keys' => [
        'table' => 'composite_keys',
        'items' => [
            'test1|test2|test3' => [
                'id' => 1,
                'key1' => 'test1',
                'key2' => 'test2',
                'key3' => 'test3',
                'created_at' => '2025-06-08 10:08:15',
                'updated_at' => '2025-06-08 10:08:15'
            ],
            'test3|test2|' => [
                'id' => 2,
                'key1' => 'test3',
                'key2' => 'test2',
                'key3' => null,
                'created_at' => '2025-06-08 10:08:15',
                'updated_at' => '2025-06-08 10:08:15'
            ],
            'test|test|test' => [
                'id' => 3,
                'key1' => 'test',
                'key2' => 'test',
                'key3' => 'test',
                'created_at' => '2025-06-08 10:08:15',
                'updated_at' => '2025-06-08 10:08:15'
            ]
        ],
        'syncId' => [
            'key1',
            'key2',
            'key3'
        ]
    ]
];
