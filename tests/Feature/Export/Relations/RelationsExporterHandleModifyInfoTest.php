<?php

declare(strict_types=1);

namespace Cat4year\DataMigratorTests\Feature\Export\Relations;

use Cat4year\DataMigrator\Entity\ExportModifyForeignColumn;
use Cat4year\DataMigrator\Entity\ExportModifySimpleColumn;
use Cat4year\DataMigrator\Entity\SyncId;
use Cat4year\DataMigrator\Services\DataMigrator\Export\ExportModifier;
use Cat4year\DataMigratorTests\Database\Factory\SlugFirstFactory;
use Cat4year\DataMigratorTests\Database\Factory\SlugThreeFactory;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use Cat4year\DataMigratorTests\Feature\BaseTestCase;

final class RelationsExporterHandleModifyInfoTest extends BaseTestCase
{
    use DatabaseMigrations;

    #[DataProvider('provide_collect_belongs_to_relations')]
    public function test_handle_modify_info(callable $getData): void
    {
        [$exportModifier, $modifyInfo, $excepted] = $getData();

        $this->assertInstanceOf(ExportModifier::class, $exportModifier);
        $this->assertEquals($exportModifier->handleModifyInfo($modifyInfo), $excepted);
    }

    public static function provide_collect_belongs_to_relations(): array
    {
        return [
            'handleModifyInfo SlugFirst belongsTo' => [static function () {
                $relationName = 'slugThree';
                $relationModel = SlugThreeFactory::new()->makeOne(['id' => 1, 'slug_second_id' => null]);
                $slugFirst = SlugFirstFactory::new()
                    ->makeOne(['slug_three_id' => $relationModel->id])
                    ->setRelation($relationName, $relationModel);
                $slugFirst2 = SlugFirstFactory::new()
                    ->makeOne(['slug_three_id' => null]);

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
                    [
                        'slug_firsts' =>
                            [
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
                        'slug_threes' =>
                            [
                                'id' =>
                                    new ExportModifySimpleColumn(...[
                                        'tableName' => 'slug_threes',
                                        'keyName' => 'id',
                                        'uniqueKeyName' => new SyncId(['slug']),
                                        'nullable' => false,
                                        'autoincrement' => true,
                                        'isPrimaryKey' => true,
                                    ]),
                            ]
                    ],
                ];
            }],
        ];
    }

    public static function provide_collect_morph_one_relations(): array
    {
        return [
            'handleModifyInfo SlugFirst morphOne' => [static function () {
                $relationName = 'slugThree';
                $relationModel = SlugThreeFactory::new()->makeOne(['id' => 1, 'slug_second_id' => null]);
                $slugFirst = SlugFirstFactory::new()
                                             ->makeOne(['slug_three_id' => $relationModel->id])
                                             ->setRelation($relationName, $relationModel);
                $slugFirst2 = SlugFirstFactory::new()
                                              ->makeOne(['slug_three_id' => null]);

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
                    [
                        'slug_firsts' =>
                            [
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
                        'slug_threes' =>
                            [
                                'id' =>
                                    new ExportModifySimpleColumn(...[
                                        'tableName' => 'slug_threes',
                                        'keyName' => 'id',
                                        'uniqueKeyName' => new SyncId(['slug']),
                                        'nullable' => false,
                                        'autoincrement' => true,
                                        'isPrimaryKey' => true,
                                    ]),
                            ]
                    ],
                ];
            }],
        ];
    }
}
