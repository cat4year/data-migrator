<?php

declare(strict_types=1);

namespace Cat4year\DataMigratorTests\Feature\Export\Relations;

use Cat4year\DataMigrator\Entity\ExportModifyForeignColumn;
use Cat4year\DataMigrator\Entity\ExportModifyMorphColumn;
use Cat4year\DataMigrator\Entity\ExportModifySimpleColumn;
use Cat4year\DataMigrator\Entity\SyncId;
use Cat4year\DataMigrator\Services\DataMigrator\Export\ExportConfigurator;
use Cat4year\DataMigrator\Services\DataMigrator\Export\Exporter;
use Cat4year\DataMigrator\Services\DataMigrator\Export\Relations\RelationsExporter;
use Cat4year\DataMigrator\Services\DataMigrator\Tools\TableService;
use Cat4year\DataMigratorTests\App\Models\SlugFirst;
use Cat4year\DataMigratorTests\Resource\Export\ExporterTestSeeder;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use PHPUnit\Framework\Attributes\DataProvider;
use Cat4year\DataMigratorTests\Feature\BaseTestCase;
use RuntimeException;

final class ExporterExportDataTest extends BaseTestCase
{
    use DatabaseMigrations;

    /**
     * @param array<class-string<Model>, list<int|string>> $excepted
     *
     * @throws BindingResolutionException
     */
    #[DataProvider('provide_export_belongs_to_data')]
    #[DataProvider('provide_export_morph_one_data')]
    public function test_exportData(
        string $entityClass,
        array $ids,
        int $maxDepth,
        string $seederClass,
        array $excepted,
        ?string $relationTypeClass = null,
    ): void
    {
        $this->seed($seederClass);
        $exportConfigurator = app(ExportConfigurator::class);
        $exportConfigurator->setIds($ids)->setMaxRelationDepth($maxDepth);
        if ($relationTypeClass !== null) {
            $exportConfigurator->setSupportedRelations([$relationTypeClass]);
        }

        $relationsExporter = app()->makeWith(RelationsExporter::class, compact('configurator'));
        $exporter = app()->makeWith(Exporter::class,
            [
                'relationManager' => $relationsExporter,
                'entity' => app(TableService::class)->identifyModelByTable($entityClass)
            ]);

        $this->assertEquals($excepted, $this->prepareForAssert($exporter->exportData()));
    }

    private function prepareForAssert(array $exportData): array
    {
        $result = [];

        foreach ($exportData as $tableName => $tableData) {
            $result[$tableName] = $this->removeKeysFromItems($tableData, ['updated_at']);
        }

        return $result;
    }

    private function removeKeysFromItems(array $tableData, array $keysForRemove): array
    {
        if (!isset($tableData['items']) || !is_array($tableData['items'])) {
            return $tableData;
        }

        foreach (array_keys($tableData['items']) as $index) {
            foreach ($keysForRemove as $keyForRemove) {
                unset($tableData['items'][$index][$keyForRemove]);
            }
        }

        return $tableData;
    }

    public static function provide_export_belongs_to_data(): array
    {
        return [
            'exportData SlugSecond (foreign nullable false) belongsTo  lvl3' => [
                'slug_seconds',
                [1, 2, 3],
                3,
                ExporterTestSeeder::class,
                [
                    'slug_seconds' => [
                        'items' => [
                            'autem-architecto-vel-quia-repudiandae' => [
                                'id' => 'autem-architecto-vel-quia-repudiandae',
                                'slug' => 'autem-architecto-vel-quia-repudiandae',
                                'created_at' => '2025-05-13 02:44:18',
                                'name' => 'Quibusdam velit aut suscipit ...uidem.',
                                'slug_first_id' => 'dignissimos',
                            ],
                            'hic-sit-illum' => [
                                'id' => 'hic-sit-illum',
                                'slug' => 'hic-sit-illum',
                                'created_at' => '2025-05-13 02:44:18',
                                'name' => 'Quia ipsa quas ut dolor nostr...eaque.',
                                'slug_first_id' => 'consectetur-illum-voluptatibus'
                            ],
                            'enim' => [
                                'id' => 'enim',
                                'slug' => 'enim',
                                'created_at' => '2025-05-13 02:44:18',
                                'name' => 'Aut nisi aut perferendis iure eaque.',
                                'slug_first_id' => 'rem-ut',
                            ],
                        ],
                        'modifiedAttributes' => [
                            'id' => new ExportModifySimpleColumn(...[
                                'tableName' => 'slug_seconds',
                                'keyName' => 'id',
                                'uniqueKeyName' => new SyncId(['slug']),
                                'nullable' => false,
                                'autoincrement' => true,
                                'isPrimaryKey' => true,
                            ]),
                            'slug_first_id' => new ExportModifyForeignColumn(...[
                                'tableName' => 'slug_seconds',
                                'keyName' => 'slug_first_id',
                                'foreignTableName' => 'slug_firsts',
                                'foreignUniqueKeyName' => new SyncId(['slug']),
                                'foreignOldKeyName' => 'id',
                                'nullable' => false,
                                'autoincrement' => false,
                                'isPrimaryKey' => false,
                            ]),
                        ],
                        'syncId' => new SyncId(['slug'])
                    ],
                    'slug_firsts' => [
                        'items' => [
                            'rem-ut' => [
                                'id' => 'rem-ut',
                                'slug' => 'rem-ut',
                                'bool_test' => false,
                                'timestamp_test' => '1979-01-08 13:09:37',
                                'string_test' => 'Здесь есть уникальный столбец slug и belongsTo колонка slug_three_id === 2',
                                'int_test' => 13098,
                                'created_at' => '2025-05-13 01:49:12',
                                'slug_three_id' => 'et-repellendus-odit-possimus',
                            ],
                            'consectetur-illum-voluptatibus' => [
                                'id' => 'consectetur-illum-voluptatibus',
                                'slug' => 'consectetur-illum-voluptatibus',
                                'bool_test' => true,
                                'timestamp_test' => '2018-04-05 07:14:23',
                                'string_test' => 'Здесь есть уникальный столбец slug и belongsTo колонка slug_three_id === null',
                                'int_test' => 855576,
                                'created_at' => '2025-05-13 01:49:12',
                                'slug_three_id' => null,
                            ],
                            'dignissimos' => [
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
                            'slug_three_id' => new ExportModifyForeignColumn(...[
                                'tableName' => 'slug_firsts',
                                'keyName' => 'slug_three_id',
                                'foreignTableName' => 'slug_threes',
                                'foreignUniqueKeyName' => new SyncId(['slug']),
                                'foreignOldKeyName' => 'id',
                                'nullable' => true,
                                'autoincrement' => false,
                                'isPrimaryKey' => false,
                            ]),
                            'id' => new ExportModifySimpleColumn(...[
                                'tableName' => 'slug_firsts',
                                'keyName' => 'id',
                                'uniqueKeyName' => new SyncId(['slug']),
                                'nullable' => false,
                                'autoincrement' => true,
                                'isPrimaryKey' => true,
                            ]),
                        ],
                        'syncId' => new SyncId(['slug'])
                    ],
                    'slug_threes' => [
                        'items' => [
                            'magnam-dolorum' => [
                                'id' => 'magnam-dolorum',
                                'slug' => 'magnam-dolorum',
                                'name' => 'Velit qui tenetur amet amet c...uatur.',
                                'created_at' => '2025-05-13 02:37:33',
                                'slug_second_id' => 'hic-sit-illum',
                            ],
                            'et-repellendus-odit-possimus' => [
                                'id' => 'et-repellendus-odit-possimus',
                                'slug' => 'et-repellendus-odit-possimus',
                                'name' => 'Nobis enim omnis et distincti...ntium.',
                                'created_at' => '2025-05-13 02:37:33',
                                'slug_second_id' => 'hic-sit-illum',
                            ],
                        ],
                        'modifiedAttributes' => [
                            'id' => new ExportModifySimpleColumn(...[
                                'tableName' => 'slug_threes',
                                'keyName' => 'id',
                                'uniqueKeyName' => new SyncId(['slug']),
                                'nullable' => false,
                                'autoincrement' => true,
                                'isPrimaryKey' => true,
                            ]),
                            'slug_second_id' => new ExportModifyForeignColumn(...[
                                'tableName' => 'slug_threes',
                                'keyName' => 'slug_second_id',
                                'foreignTableName' => 'slug_seconds',
                                'foreignUniqueKeyName' => new SyncId(['slug']),
                                'foreignOldKeyName' => 'id',
                                'nullable' => true,
                                'autoincrement' => false,
                                'isPrimaryKey' => false,
                            ]),
                        ],
                        'syncId' => new SyncId(['slug'])
                    ]
                ],
                BelongsTo::class,
            ],
        ];
    }

    public static function provide_export_morph_one_data(): array
    {
        return [
            'exportData morphOne lvl2' => [
                'slug_firsts',
                [1, 2, 3],
                2,
                ExporterTestSeeder::class,
                [
                    'slug_firsts' => [
                        'items' => [
                            'rem-ut' => [
                                'id' => 'rem-ut',
                                'slug' => 'rem-ut',
                                'bool_test' => false,
                                'timestamp_test' => '1979-01-08 13:09:37',
                                'string_test' => 'Здесь есть уникальный столбец slug и belongsTo колонка slug_three_id === 2',
                                'int_test' => 13098,
                                'created_at' => '2025-05-13 01:49:12',
                                'slug_three_id' => 2,
                            ],
                            'consectetur-illum-voluptatibus' => [
                                'id' => 'consectetur-illum-voluptatibus',
                                'slug' => 'consectetur-illum-voluptatibus',
                                'bool_test' => true,
                                'timestamp_test' => '2018-04-05 07:14:23',
                                'string_test' => 'Здесь есть уникальный столбец slug и belongsTo колонка slug_three_id === null',
                                'int_test' => 855576,
                                'created_at' => '2025-05-13 01:49:12',
                                'slug_three_id' => null,
                            ],
                            'dignissimos' => [
                                'id' => 'dignissimos',
                                'slug' => 'dignissimos',
                                'bool_test' => true,
                                'timestamp_test' => '2004-05-12 00:28:59',
                                'string_test' => 'Здесь есть уникальный столбец slug и belongsTo колонка slug_three_id === 1',
                                'int_test' => 56598568,
                                'created_at' => '2025-05-13 01:49:12',
                                'slug_three_id' => 1,
                            ],
                        ],
                        'modifiedAttributes' => [
                            'id' => new ExportModifySimpleColumn(...[
                                'tableName' => 'slug_firsts',
                                'keyName' => 'id',
                                'uniqueKeyName' => new SyncId(['slug']),
                                'nullable' => false,
                                'autoincrement' => true,
                                'isPrimaryKey' => true,
                            ]),
                        ],
                        'syncId' => new SyncId(['slug'])
                    ],
                    'slug_fours' => [
                        'items' => [
                            'sfo1' => [
                                'id' => 'sfo1',
                                'slug' => 'sfo1',
                                'name' => 'sfo1',
                                'created_at' => '2025-05-12 02:49:12',
                                'slug_fourable_type' => SlugFirst::class,
                                'slug_fourable_id' => 'rem-ut',
                            ],
                            'sfo2' => [
                                'id' => 'sfo2',
                                'slug' => 'sfo2',
                                'name' => 'sfo2',
                                'created_at' => '2025-12-12 01:49:12',
                                'slug_fourable_type' => SlugFirst::class,
                                'slug_fourable_id' => 'dignissimos',
                            ],
                        ],
                        'modifiedAttributes' => [
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
                        'syncId' => new SyncId(['slug'])
                    ]
                ],
                MorphOne::class,
            ],
        ];
    }

    #[DataProvider('provide_exception_export_belongs_to_data')]
    public function test_exception_exportData(
        string $entityClass,
        array $ids,
        int $maxDepth,
        string $seederClass,
        ?string $relationTypeClass = null,
    ): void
    {
        $this->seed($seederClass);
        $exportConfigurator = app(ExportConfigurator::class);
        $exportConfigurator->setIds($ids)->setMaxRelationDepth($maxDepth);
        if ($relationTypeClass !== null) {
            $exportConfigurator->setSupportedRelations([$relationTypeClass]);
        }

        $relationsExporter = app()->makeWith(RelationsExporter::class, compact('configurator'));
        $exporter = app()->makeWith(Exporter::class,
            [
                'relationManager' => $relationsExporter,
                'entity' => app(TableService::class)->identifyModelByTable($entityClass)
            ]);

        $this->expectException(RuntimeException::class);
        $exporter->exportData();
    }

    public static function provide_exception_export_belongs_to_data(): array
    {
        return [
            'exception exportData SlugFirst (foreign nullable true) belongsTo lvl1 (slug_three_id)' => [
                'slug_firsts',
                [1, 2, 3],
                1,
                ExporterTestSeeder::class,
                BelongsTo::class,
            ],
            'exception exportData SlugFirst (foreign nullable true) belongsTo lvl2 (slug_second_id)' => [
                'slug_firsts',
                [1, 2, 3],
                2,
                ExporterTestSeeder::class,
                BelongsTo::class,
            ],
            'exception exportData SlugSecond (foreign nullable false) belongsTo  lvl2 (slug_three_id)' => [
                'slug_seconds',
                [1, 2, 3],
                2,
                ExporterTestSeeder::class,
                BelongsTo::class,
            ],
            'exception exportData SlugThree belongsTo lvl3 (slug_first_id)' => [
                'slug_threes',
                [1, 2, 3],
                2,
                ExporterTestSeeder::class,
                BelongsTo::class,
            ],
        ];
    }
}
