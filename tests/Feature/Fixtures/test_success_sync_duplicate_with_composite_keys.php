<?php

return [
    'composite_keys' => [
        'table' => 'composite_keys',
        'items' => [
            'test1|test2|test3' => [
                'id' => 3,
                'key1' => 'test1',
                'key2' => 'test2',
                'key3' => 'test3',
                'created_at' => '2025-06-08 10:08:16',
                'updated_at' => '2025-06-08 10:08:16'
            ],
            'test3|test2|' => [
                'id' => 2,
                'key1' => 'test3',
                'key2' => 'test2',
                'key3' => null,
                'created_at' => '2025-06-08 10:08:16',
                'updated_at' => '2025-06-08 10:08:16'
            ]
        ],
        'syncId' => [
            'key1',
            'key2',
            'key3'
        ]
    ]
];
