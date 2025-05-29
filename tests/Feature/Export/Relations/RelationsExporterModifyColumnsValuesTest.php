<?php

declare(strict_types=1);

namespace Cat4year\DataMigratorTests\Feature\Export\Relations;

use Cat4year\DataMigrator\Entity\ExportModifyForeignColumn;
use Cat4year\DataMigrator\Entity\ExportModifyMorphColumn;
use Cat4year\DataMigrator\Entity\ExportModifySimpleColumn;
use Cat4year\DataMigrator\Entity\SyncId;
use Cat4year\DataMigrator\Services\DataMigrator\Export\ExportModifier;
use Cat4year\DataMigratorTests\App\Models\SlugFirst;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use PHPUnit\Framework\Attributes\DataProvider;
use Cat4year\DataMigratorTests\Feature\BaseTestCase;
use RuntimeException;

final class RelationsExporterModifyColumnsValuesTest extends BaseTestCase
{
    use DatabaseMigrations;

    #[DataProvider('provide_collect_belongs_to_relations')]
    #[DataProvider('provide_collect_morph_one_relations')]
    public function test_modify_columns_values(array $entities, array $modifyInfo, array $excepted): void
    {
        $exportModifier = app()->makeWith(ExportModifier::class, ['entitiesCollections' => collect($entities)]);
        assert($exportModifier instanceof ExportModifier);

        $result = $exportModifier->modifyColumnsValues($modifyInfo);

        $this->assertEquals($excepted, $this->extractItems($result));
    }

    private function extractItems(array $result): array
    {
        return array_map(static fn($tableData) => $tableData['items'], $result);
    }

    public static function provide_collect_belongs_to_relations(): array
    {
        $slugFirstModifyInfo = [
            'slug_firsts' => [
                'slug_three_id' =>
                    new ExportModifyForeignColumn(...[
                        'tableName' => 'slug_firsts',
                        'keyName' => 'slug_three_id',
                        'foreignTableName' => 'slug_threes',
                        'foreignUniqueKeyName' => new SyncId(['slug']),
                        'foreignOldKeyName' => 'id',
                        'nullable' => true,
                        'autoincrement' => false,
                        'isPrimaryKey' => false,
                    ]),
                'id' =>
                    new ExportModifySimpleColumn(...[
                        'tableName' => 'slug_firsts',
                        'keyName' => 'id',
                        'uniqueKeyName' => new SyncId(['slug']),
                        'nullable' => false,
                        'autoincrement' => true,
                        'isPrimaryKey' => true,
                    ]),
            ],
            'slug_threes' => [
                'id' =>
                    new ExportModifySimpleColumn(...[
                        'tableName' => 'slug_threes',
                        'keyName' => 'id',
                        'uniqueKeyName' => new SyncId(['slug']),
                        'nullable' => false,
                        'autoincrement' => true,
                        'isPrimaryKey' => true,
                    ]),
            ],
        ];

        return [
            'handleModifyInfo SlugFirst belongsTo lvl2' => [
                [
                    'slug_firsts' => [
                        'table' => 'slug_firsts',
                        'items' => [
                            1 => [
                                'id' => 1,
                                'slug' => 'sf1',
                                'slug_three_id' => 1
                            ],
                            2 => [
                                'id' => 2,
                                'slug' => 'sf2',
                                'slug_three_id' => null
                            ],
                        ],
                        'syncId' => new SyncId(['slug'])
                    ],
                    'slug_threes' => [
                        'table' => 'slug_threes',
                        'items' => [
                            1 => [
                                'id' => 1,
                                'slug' => 'st1',
                                'slug_second_id' => null
                            ],
                        ],
                        'syncId' => new SyncId(['slug'])
                    ]
                ],
                $slugFirstModifyInfo,
                [
                    'slug_firsts' => [
                        1 => [
                            'id' => 'sf1',
                            'slug' => 'sf1',
                            'slug_three_id' => 'st1',
                        ],
                        2 => [
                            'id' => 'sf2',
                            'slug' => 'sf2',
                            'slug_three_id' => null,
                        ]
                    ],
                    'slug_threes' => [
                        1 => [
                            'id' => 'st1',
                            'slug' => 'st1',
                            'slug_second_id' => null
                        ],
                    ]
                ],
            ],
        ];
    }

    public static function provide_collect_morph_one_relations(): array
    {
        $slogFoursModifyInfo = [
            'slug_firsts' => [
                'id' => new ExportModifySimpleColumn(
                    tableName: 'slug_firsts',
                    keyName: 'id',
                    uniqueKeyName: new SyncId(['slug']),
                    nullable: false,
                    autoincrement: true,
                    isPrimaryKey: true,
                ),
            ],
            'slug_fours' => [
                'id' => new ExportModifySimpleColumn(
                    tableName: 'slug_fours',
                    keyName: 'id',
                    uniqueKeyName: new SyncId(['slug']),
                    nullable: false,
                    autoincrement: true,
                    isPrimaryKey: true,
                ),
                'slug_fourable_id' => new ExportModifyMorphColumn(
                    morphType: 'slug_fourable_type',
                    tableName: 'slug_fours',
                    keyName: 'slug_fourable_id',
                    sourceKeyNames: ['slug_firsts' => new SyncId(['slug'])],
                    sourceOldKeyNames: ['slug_firsts' => 'id'],
                    nullable: false,
                    autoincrement: false,
                    isPrimaryKey: false,
                )
            ],
        ];

        return [
            'handleModifyInfo morphTo' => [
                [
                    'slug_firsts' => [
                        'table' => 'slug_firsts',
                        'items' => [
                            1 => [
                                'id' => 1,
                                'slug' => 'sf1',
                            ],
                            2 => [
                                'id' => 2,
                                'slug' => 'sf2',
                            ],
                        ],
                        'syncId' => new SyncId(['slug'])
                    ],
                    'slug_fours' => [
                        'table' => 'slug_fours',
                        'items' => [
                            1 => [
                                'id' => 1,
                                'slug' => 'sfo1',
                                'slug_fourable_type' => SlugFirst::class,
                                'slug_fourable_id' => 2,
                            ],
                            2 => [
                                'id' => 2,
                                'slug' => 'sfo2',
                                'slug_fourable_type' => SlugFirst::class,
                                'slug_fourable_id' => 1
                            ],
                        ],
                        'syncId' => new SyncId(['slug'])
                    ]
                ],
                $slogFoursModifyInfo,
                [
                    'slug_firsts' => [
                        1 => [
                            'id' => 'sf1',
                            'slug' => 'sf1',
                        ],
                        2 => [
                            'id' => 'sf2',
                            'slug' => 'sf2',
                        ]
                    ],
                    'slug_fours' => [
                        1 => [
                            'id' => 'sfo1',
                            'slug' => 'sfo1',
                            'slug_fourable_type' => SlugFirst::class,
                            'slug_fourable_id' => 'sf2',
                        ],
                        2 => [
                            'id' => 'sfo2',
                            'slug' => 'sfo2',
                            'slug_fourable_type' => SlugFirst::class,
                            'slug_fourable_id' => 'sf1',
                        ],
                    ]
                ],
            ],
        ];
    }

    #[DataProvider('provide_error_modify_columns_values')]
    public function test_error_modify_columns_values(array $entities, array $modifyInfo): void
    {
        $exportModifier = app()->makeWith(ExportModifier::class, ['entitiesCollections' => collect($entities)]);
        assert($exportModifier instanceof ExportModifier);

        $this->expectException(RuntimeException::class);
        $exportModifier->modifyColumnsValues($modifyInfo);
    }

    public static function provide_error_modify_columns_values(): array
    {
        $slugFirstModifyInfo = [
            'slug_firsts' => [
                'slug_three_id' =>
                    new ExportModifyForeignColumn(...[
                        'tableName' => 'slug_firsts',
                        'keyName' => 'slug_three_id',
                        'foreignTableName' => 'slug_threes',
                        'foreignUniqueKeyName' => new SyncId(['slug']),
                        'foreignOldKeyName' => 'id',
                        'nullable' => true,
                        'autoincrement' => false,
                        'isPrimaryKey' => false,
                    ]),
                'id' =>
                    new ExportModifySimpleColumn(...[
                        'tableName' => 'slug_firsts',
                        'keyName' => 'id',
                        'uniqueKeyName' => new SyncId(['slug']),
                        'nullable' => false,
                        'autoincrement' => true,
                        'isPrimaryKey' => true,
                    ]),
            ],
        ];
        return [
            'error handleModifyInfo SlugFirst belongsTo lvl1 (slug_three_id)' => [
                [
                    'slug_firsts' => [
                        'table' => 'slug_firsts',
                        'items' => [
                            1 => [
                                'id' => 1,
                                'slug' => 'sf1',
                                'slug_three_id' => 1
                            ],
                            2 => [
                                'id' => 2,
                                'slug' => 'sf2',
                                'slug_three_id' => null
                            ],
                        ],
                        'syncId' => new SyncId(['slug'])
                    ],
                ],
                $slugFirstModifyInfo,
            ],
        ];
    }
}
