<?php

declare(strict_types=1);

namespace Tests\Feature\Export\Relations;

use Cat4year\DataMigrator\Services\DataMigrator\Export\ExportConfigurator;
use Cat4year\DataMigrator\Services\DataMigrator\Export\Relations\RelationsExporter;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\BaseTestCase;
use Tests\Resource\Export\Relations\RelationsExporterTestSeeder;

final class RelationsExporterTest extends BaseTestCase
{
    // use RefreshDatabase; // don't clean autoincrement. faster
    use DatabaseMigrations; // clean autoincrement. slowly

    /**
     * @param array<class-string<Model>, list<int|string>> $excepted
     *
     * @throws BindingResolutionException
     */
    #[DataProvider('provide_collect_has_one_relations')]
    #[DataProvider('provide_collect_belongs_to_relations')]
    #[DataProvider('provide_collect_has_through_relations')]
    #[DataProvider('provide_collect_all_relations')]
    #[DataProvider('provide_collect_morph_one_relations')]
    public function test_collect_relations(
        string $entityClass,
        array $ids,
        int $maxDepth,
        string $seederClass,
        array $excepted,
        ?string $relationTypeClass = null,
    ): void {
        $this->seed($seederClass);
        $configurator = app(ExportConfigurator::class); // use mock instead?
        $configurator->setMaxRelationDepth($maxDepth);
        if ($relationTypeClass !== null) {
            $configurator->setSupportedRelations([$relationTypeClass]);
        }
        $relationManager = app()->makeWith(RelationsExporter::class, compact('configurator'));

        $result = $relationManager->collectRelations($entityClass, $ids)->entityIds->toArray();
        foreach ($result as $tableName => $tableEntityIds) {
            sort($tableEntityIds);
            $result[$tableName] = array_values($tableEntityIds);
        }

        $this->assertSame($result, $excepted);
    }

    public static function provide_collect_has_one_relations(): array
    {
        return [
            'slugs SlugFirst hasOne lvl1' => [
                'slug_firsts',
                [1, 2, 3],
                1,
                RelationsExporterTestSeeder::class,
                [
                    'slug_firsts' => [1, 2, 3],
                ],
                HasOne::class,
            ],
            'slugs SlugFirst hasOne lvl2' => [
                'slug_firsts',
                [1, 2, 3],
                2,
                RelationsExporterTestSeeder::class,
                [
                    'slug_firsts' => [1, 2, 3],
                    'slug_seconds' => [1, 2, 3],
                ],
                HasOne::class,
            ],
            'slugs SlugFirst hasOne lvl3' => [
                'slug_firsts',
                [1, 2, 3],
                3,
                RelationsExporterTestSeeder::class,
                [
                    'slug_firsts' => [1, 2, 3],
                    'slug_seconds' => [1, 2, 3],
                    'slug_threes' => [1, 2, 3],
                ],
                HasOne::class,
            ],
            'slugs SlugSecond hasOne lvl3' => [
                'slug_seconds',
                [1, 2, 3],
                3,
                RelationsExporterTestSeeder::class,
                [
                    'slug_seconds' => [1, 2, 3],
                    'slug_threes' => [1, 2, 3],
                    'slug_firsts' => [1, 3],
                ],
                HasOne::class,
            ],
            'slugs SlugThree hasOne lvl3' => [
                'slug_threes',
                [1, 2, 3],
                3,
                RelationsExporterTestSeeder::class,
                [
                    'slug_threes' => [1, 2, 3],
                    'slug_firsts' => [1, 3],
                    'slug_seconds' => [1, 3],
                ],
                HasOne::class,
            ],
        ];
    }

    public static function provide_collect_belongs_to_relations(): array
    {
        return [
            'slugs SlugFirst belongsTo lvl1' => [
                'slug_firsts',
                [1, 2, 3],
                1,
                RelationsExporterTestSeeder::class,
                [
                    'slug_firsts' => [1, 2, 3],
                ],
                BelongsTo::class,
            ],
            'slugs SlugFirst belongsTo lvl2' => [
                'slug_firsts',
                [1, 2, 3],
                2,
                RelationsExporterTestSeeder::class,
                [
                    'slug_firsts' => [1, 2, 3],
                    'slug_threes' => [1, 2],
                ],
                BelongsTo::class,
            ],
            'slugs SlugSecond belongsTo lvl2' => [
                'slug_seconds',
                [1, 2, 3],
                1,
                RelationsExporterTestSeeder::class,
                [
                    'slug_seconds' => [1, 2, 3],
                ],
                BelongsTo::class,
            ],
            'slugs SlugThree belongsTo lvl2' => [
                'slug_threes',
                [1, 2, 3],
                2,
                RelationsExporterTestSeeder::class,
                [
                    'slug_threes' => [1, 2, 3],
                    'slug_seconds' => [1, 2],
                ],
                BelongsTo::class,
            ],
        ];
    }

    public static function provide_collect_all_relations(): array
    {
        return [
            'slugs SlugFirst all lvl1' => [
                'slug_firsts',
                [1, 2, 3],
                1,
                RelationsExporterTestSeeder::class,
                [
                    'slug_firsts' => [1, 2, 3],
                ],
            ],
            'slugs SlugFirst all lvl2' => [
                'slug_firsts',
                [1, 2, 3],
                2,
                RelationsExporterTestSeeder::class,
                [
                    'slug_firsts' => [1, 2, 3],
                    'slug_seconds' => [1, 2, 3],
                    'slug_threes' => [1, 2, 3],
                    'slug_secondables' => [1, 2],
                    'slug_fours' => [1, 2],
                ],
            ],

            // infinity
            'slugs SlugFirst all infinity' => [
                'slug_firsts',
                [1, 2, 3],
                PHP_INT_MAX,
                RelationsExporterTestSeeder::class,
                [
                    'slug_firsts' => [1, 2, 3],
                    'slug_seconds' => [1, 2, 3],
                    'slug_threes' => [1, 2, 3],
                    'slug_secondables' => [1, 2, 3],
                    'slug_fours' => [1, 2, 3],
                ],
            ],

            'slugs SlugSecond all infinity' => [
                'slug_seconds',
                [1, 2, 3],
                PHP_INT_MAX,
                RelationsExporterTestSeeder::class,
                [
                    'slug_seconds' => [1, 2, 3],
                    'slug_firsts' => [1, 2, 3],
                    'slug_threes' => [1, 2, 3],
                    'slug_secondables' => [1, 2, 3],
                    'slug_fours' => [1, 2, 3],
                ],
            ],

            'slugs SlugThree all infinity' => [
                'slug_threes',
                [1, 2, 3],
                PHP_INT_MAX,
                RelationsExporterTestSeeder::class,
                [
                    'slug_threes' => [1, 2, 3],
                    'slug_firsts' => [1, 2, 3],
                    'slug_seconds' => [1, 2, 3],
                    'slug_fours' => [1, 2, 3],
                    'slug_secondables' => [1, 2, 3],
                ],
            ],

            // different ids
            'slugs SlugFirst all infinity ids 1' => [
                'slug_firsts',
                [1],
                PHP_INT_MAX,
                RelationsExporterTestSeeder::class,
                [
                    'slug_firsts' => [1, 2, 3],
                    'slug_seconds' => [1, 2, 3],
                    'slug_threes' => [1, 2, 3],
                    'slug_secondables' => [1, 2, 3],
                    'slug_fours' => [1, 2, 3],
                ],
            ],
            'slugs SlugFirst all infinity ids 2' => [
                'slug_firsts',
                [2],
                PHP_INT_MAX,
                RelationsExporterTestSeeder::class,
                [
                    'slug_firsts' => [1, 2, 3],
                    'slug_seconds' => [1, 2, 3],
                    'slug_threes' => [1, 2, 3],
                    'slug_secondables' => [1, 2, 3],
                    'slug_fours' => [1, 2, 3],
                ],
            ],

            'slugs SlugSecond all infinity ids 1' => [
                'slug_seconds',
                [1],
                PHP_INT_MAX,
                RelationsExporterTestSeeder::class,
                [
                    'slug_seconds' => [1, 2, 3],
                    'slug_firsts' => [1, 2, 3],
                    'slug_threes' => [1, 2, 3],
                    'slug_secondables' => [1, 2, 3],
                    'slug_fours' => [1, 2, 3],
                ],
            ],
            'slugs SlugSecond all infinity ids 2' => [
                'slug_seconds',
                [2],
                PHP_INT_MAX,
                RelationsExporterTestSeeder::class,
                [
                    'slug_seconds' => [1, 2, 3],
                    'slug_firsts' => [1, 2, 3],
                    'slug_threes' => [1, 2, 3],
                    'slug_secondables' => [1, 2, 3],
                    'slug_fours' => [1, 2, 3],
                ],
            ],

            'slugs SlugThree all infinity ids 1' => [
                'slug_threes',
                [1],
                PHP_INT_MAX,
                RelationsExporterTestSeeder::class,
                [
                    'slug_threes' => [1, 2, 3],
                    'slug_firsts' => [1, 2, 3],
                    'slug_seconds' => [1, 2, 3],
                    'slug_fours' => [1, 2, 3],
                    'slug_secondables' => [1, 2, 3],
                ],
            ],
            'slugs SlugThree all infinity ids 2' => [
                'slug_threes',
                [2],
                PHP_INT_MAX,
                RelationsExporterTestSeeder::class,
                [
                    'slug_threes' => [1, 2, 3],
                    'slug_firsts' => [1, 2, 3],
                    'slug_seconds' => [1, 2, 3],
                    'slug_fours' => [1, 2, 3],
                    'slug_secondables' => [1, 2, 3],
                ],
            ],
        ];
    }

    public static function provide_collect_has_through_relations(): array
    {
        return [
            'slugs SlugFirst hasThrough lvl1' => [
                'slug_firsts',
                [1, 2, 3],
                1,
                RelationsExporterTestSeeder::class,
                [
                    'slug_firsts' => [1, 2, 3],
                ],
                HasOneThrough::class,
            ],
            'slugs SlugFirst hasThrough lvl2' => [
                'slug_firsts',
                [1, 2, 3],
                2,
                RelationsExporterTestSeeder::class,
                [
                    'slug_firsts' => [1, 2, 3],
                    'slug_seconds' => [1, 2, 3],
                    'slug_threes' => [1, 2, 3],
                ],
                HasOneThrough::class,
            ],
            'slugs SlugFirst hasThrough lvl3' => [
                'slug_firsts',
                [1, 2, 3],
                3,
                RelationsExporterTestSeeder::class,
                [
                    'slug_firsts' => [1, 2, 3],
                    'slug_seconds' => [1, 2, 3],
                    'slug_threes' => [1, 2, 3],
                ],
                HasOneThrough::class,
            ],

            // infinity lvls with different ids
            'slugs SlugFirst hasThrough infinity ids 1' => [
                'slug_firsts',
                [1],
                PHP_INT_MAX,
                RelationsExporterTestSeeder::class,
                [
                    'slug_firsts' => [1],
                    'slug_seconds' => [3],
                ],
                HasOneThrough::class,
            ],
            'slugs SlugFirst hasThrough infinity ids 2' => [
                'slug_firsts',
                [2],
                PHP_INT_MAX,
                RelationsExporterTestSeeder::class,
                [
                    'slug_firsts' => [2],
                    'slug_seconds' => [2],
                    'slug_threes' => [1, 2],
                ],
                HasOneThrough::class,
            ],
            'slugs SlugFirst hasThrough infinity ids 3' => [
                'slug_firsts',
                [3],
                PHP_INT_MAX,
                RelationsExporterTestSeeder::class,
                [
                    'slug_firsts' => [3],
                    'slug_seconds' => [1],
                    'slug_threes' => [3],
                ],
                HasOneThrough::class,
            ],
            'slugs SlugFirst hasThrough infinity all' => [
                'slug_firsts',
                [1, 2, 3],
                PHP_INT_MAX,
                RelationsExporterTestSeeder::class,
                [
                    'slug_firsts' => [1, 2, 3],
                    'slug_seconds' => [1, 2, 3],
                    'slug_threes' => [1, 2, 3],
                ],
                HasOneThrough::class,
            ],

            // without hasThrough
            'slugs SlugSecond no hasThrough' => [
                'slug_seconds',
                [1, 2, 3],
                PHP_INT_MAX,
                RelationsExporterTestSeeder::class,
                [
                    'slug_seconds' => [1, 2, 3],
                ],
                HasOneThrough::class,
            ],
        ];
    }

    public static function provide_collect_morph_one_relations(): array
    {
        return [
            'slugs SlugFirst morphOne lvl1' => [
                'slug_firsts',
                [1, 2, 3],
                1,
                RelationsExporterTestSeeder::class,
                [
                    'slug_firsts' => [1, 2, 3],
                ],
                MorphOne::class,
            ],
            'slugs SlugFirst morphOne lvl2' => [
                'slug_firsts',
                [1, 2, 3],
                2,
                RelationsExporterTestSeeder::class,
                [
                    'slug_firsts' => [1, 2, 3],
                    'slug_fours' => [1, 2],
                ],
                MorphOne::class,
            ],
            'slugs SlugFirst morphOne lvl3' => [
                'slug_firsts',
                [1, 2, 3],
                3,
                RelationsExporterTestSeeder::class,
                [
                    'slug_firsts' => [1, 2, 3],
                    'slug_fours' => [1, 2],
                ],
                MorphOne::class,
            ],

            // infinity lvls with different ids
            'slugs SlugFirst morphOne infinity ids 1' => [
                'slug_firsts',
                [1],
                PHP_INT_MAX,
                RelationsExporterTestSeeder::class,
                [
                    'slug_firsts' => [1],
                    'slug_fours' => [1],
                ],
                MorphOne::class,
            ],
            'slugs SlugFirst morphOne infinity ids 2' => [
                'slug_firsts',
                [2],
                PHP_INT_MAX,
                RelationsExporterTestSeeder::class,
                [
                    'slug_firsts' => [2],
                ],
                MorphOne::class,
            ],
            'slugs SlugFirst morphOne infinity ids 3' => [
                'slug_firsts',
                [3],
                PHP_INT_MAX,
                RelationsExporterTestSeeder::class,
                [
                    'slug_firsts' => [3],
                    'slug_fours' => [2],
                ],
                MorphOne::class,
            ],
            'slugs SlugFirst morphOne infinity all' => [
                'slug_firsts',
                [1, 2, 3],
                PHP_INT_MAX,
                RelationsExporterTestSeeder::class,
                [
                    'slug_firsts' => [1, 2, 3],
                    'slug_fours' => [1, 2],
                ],
                MorphOne::class,
            ],

            // without morphOne
            'slugs SlugSecond no morphOne' => [
                'slug_seconds',
                [1, 2, 3],
                PHP_INT_MAX,
                RelationsExporterTestSeeder::class,
                [
                    'slug_seconds' => [1, 2, 3],
                ],
                MorphOne::class,
            ],
        ];
    }
}
