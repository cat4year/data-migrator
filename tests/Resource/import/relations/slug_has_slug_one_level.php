<?php

declare(strict_types=1);

$data = [
    'items' => [
        0 => [
            'slug' => 'rerum',
            'bool_test' => false,
            'timestamp_test' => '1984-01-19 15:03:06',
            'string_test' => 'К тому ж дело было совсем невыгодно. — Так вот же: до тех пор, — сказал он, — или не понимаем друг друга, — позабыли, в чем провинился, либо был пьян. Лошади были удивительно как вычищены. Хомут на.',
            'int_test' => 11280,
            'slug_three_id' => 'nihil-et',
        ],
        1 => [
            'slug' => 'doloremque-omnis-harum-pariatur',
            'bool_test' => false,
            'timestamp_test' => '1972-12-16 06:56:38',
            'string_test' => 'Заманиловка? — Ну вот то-то же, нужно будет ехать в город. Так совершилось дело. Оба решили, что завтра же быть в городе об этом новом лице, которое очень скоро не преминуло показать себя на.',
            'int_test' => 953146,
            'slug_three_id' => 'ad-quia',
        ],
        2 => [
            'slug' => 'deserunt-voluptas-voluptatum',
            'bool_test' => true,
            'timestamp_test' => '1972-09-02 15:57:06',
            'string_test' => 'Порфирий и с этой стороны, несмотря на непостижимую уму бочковатость ребр «и комкость лап. — Да кто вы такой? — сказала помещица стоявшей около крыльца девчонке лет — одиннадцати, в платье из.',
            'int_test' => 7835,
            'slug_three_id' => 'eos-deleniti',
        ],
        3 => [
            'slug' => 'qui-rerum-ullam',
            'bool_test' => false,
            'timestamp_test' => '1994-04-06 02:12:30',
            'string_test' => 'Порфирию и рассматривая брюхо щенка, — и время — провел очень приятно: общество самое обходительное. — А прекрасный человек! — Да на что не услышит ни ответа, ни мнения, ни подтверждения, но на два.',
            'int_test' => 258596175,
            'slug_three_id' => 'et-voluptatem',
        ],
        4 => [
            'slug' => 'omnis-provident-aliquam',
            'bool_test' => true,
            'timestamp_test' => '1998-09-28 08:19:05',
            'string_test' => 'Я его прочу по дипломатической части. Фемистоклюс, — — сказал Чичиков. Манилов выронил тут же со слугою услышали хриплый бабий голос: — Кто такой? — сказала — Коробочка. Чичиков попросил списочка.',
            'int_test' => 51,
            'slug_three_id' => 'est-animi',
        ],
        5 => [
            'slug' => 'quia-quisquam',
            'bool_test' => true,
            'timestamp_test' => '1974-10-05 16:48:32',
            'string_test' => 'И нагадит так, как с тем, у которого их пятьсот, а с другой стороны, чтоб дать отдохнуть лошадям, а с тем, у которого их восемьсот, — словом, все то же, что и везде; только и разницы, что на один.',
            'int_test' => 630108089,
            'slug_three_id' => 'maxime-officiis-reprehenderit',
        ],
        6 => [
            'slug' => 'perferendis',
            'bool_test' => false,
            'timestamp_test' => '1989-03-08 03:43:52',
            'string_test' => 'Будет, будет готова. Расскажите только мне, как добраться до большой — претензии, право, я должен ей рассказать о ярмарке. — Эх ты, Софрон! Разве нельзя быть в одно и то довольно жидкой. Но здоровые.',
            'int_test' => 76,
            'slug_three_id' => 'corporis-quis-aut',
        ],
        7 => [
            'slug' => 'omnis-aut',
            'bool_test' => true,
            'timestamp_test' => '2020-07-26 12:59:27',
            'string_test' => 'Во все продолжение этой проделки Чичиков глядел очень внимательно глядел на нее похожая. Она проводила его в комнату. Порфирий подал свечи, и Чичиков заметил на крыльце и, как только вышел из.',
            'int_test' => 5818992,
            'slug_three_id' => 'aut-reprehenderit-aut',
        ],
    ],
    'additional' => [
        'entity' => 'Cat4year\DataMigratorTests\App\Models\SlugFirst',
        'idColumn' => 'slug',
        'relations' => [
            1 => [
                'items' => [
                    0 => [
                        'slug' => 'quia-eveniet-dolorem',
                        'name' => 'Rerum dolores quas temporibus officia qui.',
                        'slug_first_id' => null,
                    ],
                    1 => [
                        'slug' => 'necessitatibus',
                        'name' => 'Tempore natus libero placeat voluptatem quibusdam.',
                        'slug_first_id' => null,
                    ],
                    2 => [
                        'slug' => 'dignissimos-quo-dolor',
                        'name' => 'Dolores et nam aut.',
                        'slug_first_id' => null,
                    ],
                ],
                'additional' => [
                    'entity' => 'Cat4year\DataMigratorTests\App\Models\SlugSecond',
                    'idColumn' => 'slug',
                    'relations' => [],
                    'type' => 'Illuminate\\Database\\Eloquent\\Relations\\HasOne',
                    'name' => 'slugSecond',
                    'foreignKey' => 'slug_first_id',
                    'localKey' => 'slug',
                    'originalLocalKey' => 'id',
                ],
            ],
        ],
    ],
];
