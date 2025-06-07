<?php

declare(strict_types=1);

namespace Cat4year\DataMigratorTests\Feature\Export\Relations;

use Cat4year\DataMigrator\Entity\ExportModifyForeignColumn;
use Cat4year\DataMigrator\Entity\ExportModifyMorphColumn;
use Cat4year\DataMigrator\Entity\ExportModifySimpleColumn;
use Cat4year\DataMigrator\Entity\SyncId;
use Cat4year\DataMigrator\Services\DataMigrator\Export\ExportModifier;
use Cat4year\DataMigratorTests\App\Models\SlugFirst;
use Cat4year\DataMigratorTests\Database\Factory\SlugFirstFactory;
use Cat4year\DataMigratorTests\Database\Factory\SlugFourFactory;
use Cat4year\DataMigratorTests\Database\Factory\SlugSecondFactory;
use Cat4year\DataMigratorTests\Database\Factory\SlugThreeFactory;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use Cat4year\DataMigratorTests\Feature\BaseTestCase;

final class RelationsExporterModifyInfoTest extends BaseTestCase
{
    use DatabaseMigrations; // clean autoincrement. slowly

    /**
     * @param array<class-string<Model>, list<int|string>> $excepted
     *
     * @throws BindingResolutionException
     */
    #[DataProvider('provide_collect_belongs_to_relations')]
    #[DataProvider('provide_collect_morph_one_relations')]
    #[DataProvider('provide_collect_morph_to_many_relations')]
    public function test_modify_info(callable $getData): void
    {
        [$exportModifier, $excepted] = $getData();

        $this->assertInstanceOf(ExportModifier::class, $exportModifier);
        $this->assertEquals($excepted, $exportModifier->makeModifyInfo());
    }

    public static function provide_collect_belongs_to_relations(): array
    {
        return [
            'makeModifyInfo belongsTo' => [static function (): array {
                $relationName = 'slugThree';
                $slugThree = SlugThreeFactory::new()->makeOne(['id' => 1, 'slug_second_id' => null]);
                $slugFirst = SlugFirstFactory::new()->makeOne(['slug_three_id' => $slugThree->id])->setRelation($relationName, $slugThree);
                $slugFirst2 = SlugFirstFactory::new()->makeOne(['slug_three_id' => null]);

                $entities = [
                    $slugFirst->getTable() => [
                        'table' => $slugFirst->getTable(),
                        'items' => [$slugFirst->id => $slugFirst->getAttributes(), $slugFirst2->id => $slugFirst2->getAttributes()]
                    ],
                ];

                return [
                    app()->makeWith(ExportModifier::class, [
                        'entitiesCollections' => new Collection($entities),
                        'entityClasses' => new Collection([
                            $slugFirst::class => [
                                $relationName => $slugFirst->$relationName(),
                            ]
                        ]),
                    ]),
                    [
                        sprintf('%s|%s', $slugFirst::class, $relationName) => [
                            'slug_firsts' => [
                                'slug_three_id' => new ExportModifyForeignColumn(
                                    tableName: 'slug_firsts',
                                    keyName: 'slug_three_id',
                                    foreignTableName: 'slug_threes',
                                    foreignUniqueKeyName: new SyncId(['slug']),
                                    foreignOldKeyName: 'id',
                                    nullable: true,
                                    autoincrement: false,
                                    isPrimaryKey: false,
                                ),
                                'id' => new ExportModifySimpleColumn(
                                    tableName: 'slug_firsts',
                                    keyName: 'id',
                                    uniqueKeyName: new SyncId(['slug']),
                                    nullable: false,
                                    autoincrement: true,
                                    isPrimaryKey: true,
                                ),
                            ],
                            'slug_threes' => [
                                'id' => new ExportModifySimpleColumn(
                                    tableName: 'slug_threes',
                                    keyName: 'id',
                                    uniqueKeyName: new SyncId(['slug']),
                                    nullable: false,
                                    autoincrement: true,
                                    isPrimaryKey: true,
                                ),
                            ],
                        ],
                    ],
                ];
            }],
        ];
    }

    public static function provide_collect_morph_one_relations(): array
    {
        return [
            'makeModifyInfo morphOne' => [static function (): array {
                $morphOneRelationName = 'slugFours';

                $slugFirst = SlugFirstFactory::new()->makeOne(['slug_three_id' => null]);
                $slugFirst2 = SlugFirstFactory::new()->makeOne(['slug_three_id' => null]);

                $slugFour = SlugFourFactory::new()->makeOne([
                    'slug_fourable_type' => SlugFirst::class,
                    'slug_fourable_id' => $slugFirst->id,
                ]);
                $slugFour2 = SlugFourFactory::new()->makeOne([
                    'slug_fourable_type' => SlugFirst::class,
                    'slug_fourable_id' => $slugFirst2->id,
                ]);

                $slugFirst->setRelation($morphOneRelationName, $slugFour);
                $slugFirst2->setRelation($morphOneRelationName, $slugFour2);

                $entities = [
                    $slugFirst->getTable() => [
                        'table' => $slugFirst->getTable(),
                        'items' => [$slugFirst->id => $slugFirst->getAttributes(), $slugFirst2->id => $slugFirst2->getAttributes()]
                    ],
                ];

                return [
                    app()->makeWith(ExportModifier::class, [
                        'entitiesCollections' => new Collection($entities),
                        'entityClasses' => new Collection([
                            $slugFirst::class => [
                                $morphOneRelationName => $slugFirst->$morphOneRelationName(),
                            ]
                        ]),
                    ]),
                    [
                        sprintf('%s|%s', $slugFirst::class, $morphOneRelationName) => [
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
                        ],
                    ],
                ];
            }],
            'makeModifyInfo morphTo' => [static function (): array {
                $relationName = 'slugFourable';

                $slugFirst = SlugFirstFactory::new()->makeOne(['slug_three_id' => null]);
                $slugFirst2 = SlugFirstFactory::new()->makeOne(['slug_three_id' => null]);

                $slugFour = SlugFourFactory::new()->makeOne([
                    'slug_fourable_type' => SlugFirst::class,
                    'slug_fourable_id' => $slugFirst->id,
                ])->setRelation($relationName, $slugFirst);
                $slugFour2 = SlugFourFactory::new()->makeOne([
                    'slug_fourable_type' => SlugFirst::class,
                    'slug_fourable_id' => $slugFirst2->id,
                ])->setRelation($relationName, $slugFirst2);

                $entities = [
                    $slugFirst->getTable() => [
                        'table' => $slugFirst->getTable(),
                        'items' => [$slugFirst->id => $slugFirst->getAttributes(), $slugFirst2->id => $slugFirst2->getAttributes()]
                    ],
                    $slugFour->getTable() => [
                        'table' => $slugFour->getTable(),
                        'items' => [$slugFour->id => $slugFour->getAttributes(), $slugFour2->id => $slugFour2->getAttributes()]
                    ],
                ];

                return [
                    app()->makeWith(ExportModifier::class, [
                        'entitiesCollections' => new Collection($entities),
                        'entityClasses' => new Collection([
                            $slugFour::class => [
                                $relationName => $slugFour->$relationName(),
                            ]
                        ]),
                    ]),
                    [
                        sprintf('%s|%s', $slugFour::class, $relationName) => [
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
                        ],
                    ],
                ];
            }],
        ];
    }

    public static function provide_collect_morph_to_many_relations(): array
    {
        return [
            'makeModifyInfo morphToMany' => [
                static function (): array {
                    $morphToManyRelation = 'slugSecondables';

                    $slugFirst = SlugFirstFactory::new()->makeOne(['slug_three_id' => null]);
                    $slugFirst2 = SlugFirstFactory::new()->makeOne(['slug_three_id' => null]);

                    $slugSecond = SlugSecondFactory::new()->makeOne([
                        'slug_first_id' => null,
                    ]);
                    $slugSecond2 = SlugSecondFactory::new()->makeOne([
                        'slug_first_id' => null,
                    ]);

                    $slugFirst->setRelation($morphToManyRelation, $slugSecond);
                    $slugFirst2->setRelation($morphToManyRelation, $slugSecond2);

                    $entities = [
                        $slugFirst->getTable() => [
                            'table' => $slugFirst->getTable(),
                            'items' => [
                                $slugFirst->id => $slugFirst->getAttributes(),
                                $slugFirst2->id => $slugFirst2->getAttributes()
                            ]
                        ],
                    ];

                    return [
                        app()->makeWith(ExportModifier::class, [
                            'entitiesCollections' => new Collection($entities),
                            'entityClasses' => new Collection([
                                $slugFirst::class => [
                                    $morphToManyRelation => $slugFirst->$morphToManyRelation(),
                                ]
                            ]),
                        ]),
                        [
                            sprintf('%s|%s', $slugFirst::class, $morphToManyRelation) => [
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
                                'slug_seconds' => [
                                    'id' => new ExportModifySimpleColumn(
                                        tableName: 'slug_seconds',
                                        keyName: 'id',
                                        uniqueKeyName: new SyncId(['slug']),
                                        nullable: false,
                                        autoincrement: true,
                                        isPrimaryKey: true,
                                    ),
                                ],
                                'slug_secondables' => [
                                    'slug_secondable_id' => new ExportModifyMorphColumn(
                                        morphType: 'slug_secondable_type',
                                        tableName: 'slug_seconds',
                                        keyName: 'slug_second_id',
                                        sourceKeyNames: ['slug_firsts' => new SyncId(['slug'])],
                                        sourceOldKeyNames: ['slug_firsts' => 'id'],
                                        nullable: false,
                                        autoincrement: false,
                                        isPrimaryKey: false,
                                    ),
                                    'slug_second_id' => new ExportModifyForeignColumn(
                                        tableName: 'slug_seconds',
                                        keyName: 'id',
                                        foreignTableName: 'slug_seconds',
                                        foreignUniqueKeyName: new SyncId(['slug']),
                                        foreignOldKeyName: 'id',
                                        nullable: false,
                                        autoincrement: false,
                                        isPrimaryKey: false,
                                    ),
                                ]
                            ],
                        ],
                    ];
                }
            ],
        ];
    }
}
