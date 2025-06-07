<?php

declare(strict_types=1);

namespace Cat4year\DataMigratorTests\Resource\Export;

use Cat4year\DataMigratorTests\App\Models\SlugFirst;
use Cat4year\DataMigratorTests\App\Models\SlugSecond;
use Cat4year\DataMigratorTests\App\Models\SlugThree;
use Cat4year\DataMigratorTests\Database\Factory\SlugFirstFactory;
use Cat4year\DataMigratorTests\Database\Factory\SlugFourFactory;
use Cat4year\DataMigratorTests\Database\Factory\SlugSecondFactory;
use Cat4year\DataMigratorTests\Database\Factory\SlugThreeFactory;
use Illuminate\Database\Seeder;

final class ExporterTestSeeder extends Seeder
{
    public function run(): void
    {
        // first
        SlugFirstFactory::new([
            'slug' => 'rem-ut',
            'bool_test' => false,
            'timestamp_test' => '1979-01-08 13:09:37',
            'string_test' => 'Здесь есть уникальный столбец slug и belongsTo колонка slug_three_id === 2',
            'int_test' => 13098,
            'created_at' => '2025-05-13 01:49:12',
            'updated_at' => '2025-05-13 01:49:12',
            'slug_three_id' => null,
        ])->createOne();

        SlugFirstFactory::new([
            'slug' => 'consectetur-illum-voluptatibus',
            'bool_test' => true,
            'timestamp_test' => '2018-04-05 07:14:23',
            'string_test' => 'Здесь есть уникальный столбец slug и belongsTo колонка slug_three_id === null',
            'int_test' => 855576,
            'created_at' => '2025-05-13 01:49:12',
            'updated_at' => '2025-05-13 01:49:12',
            'slug_three_id' => null,
        ])->createOne();

        SlugFirstFactory::new([
            'slug' => 'dignissimos',
            'bool_test' => true,
            'timestamp_test' => '2004-05-12 00:28:59',
            'string_test' => 'Здесь есть уникальный столбец slug и belongsTo колонка slug_three_id === 1',
            'int_test' => 56598568,
            'created_at' => '2025-05-13 01:49:12',
            'updated_at' => '2025-05-13 01:49:12',
            'slug_three_id' => null,
        ])->createOne();

        // second
        SlugSecondFactory::new()->create([
            'slug' => 'autem-architecto-vel-quia-repudiandae',
            'created_at' => '2025-05-13 02:44:18',
            'name' => 'Quibusdam velit aut suscipit ...uidem.',
            'slug_first_id' => 3,
        ]);
        SlugSecondFactory::new()->create([
            'slug' => 'hic-sit-illum',
            'created_at' => '2025-05-13 02:44:18',
            'name' => 'Quia ipsa quas ut dolor nostr...eaque.',
            'slug_first_id' => 2,
        ]);
        SlugSecondFactory::new()->create([
            'slug' => 'enim',
            'created_at' => '2025-05-13 02:44:18',
            'name' => 'Aut nisi aut perferendis iure eaque.',
            'slug_first_id' => 1,
        ]);

        // three
        SlugThreeFactory::new()->create([
            'slug' => 'magnam-dolorum',
            'name' => 'Velit qui tenetur amet amet c...uatur.',
            'created_at' => '2025-05-13 02:37:33',
            'slug_second_id' => 2,
        ]);
        SlugThreeFactory::new()->create([
            'slug' => 'et-repellendus-odit-possimus',
            'name' => 'Nobis enim omnis et distincti...ntium.',
            'created_at' => '2025-05-13 02:37:33',
            'slug_second_id' => 2,
        ]);
        SlugThreeFactory::new()->create([
            'slug' => 'slug-three-3',
            'name' => 'Slug three three',
            'created_at' => '2025-05-13 02:37:33',
            'slug_second_id' => 1,
        ]);

        SlugFirst::query()->find(1)->update(['slug_three_id' => 2]);
        SlugFirst::query()->find(3)->update(['slug_three_id' => 1]);

        SlugFirst::query()->find(2)->slugSecondables()->sync([1, 3]);
        SlugThree::query()->find(1)->slugSeconds()->sync([3]);

        $slugFirstFirst = SlugFirst::query()->find(1);
        $slugFirstSecond = SlugFirst::query()->find(3);
        $slugSecondFirst = SlugSecond::query()->find(1);
        SlugFourFactory::new([
            'slug' => 'sfo1',
            'name' => 'sfo1',
            'created_at' => '2025-05-12 02:49:12',
            'slug_fourable_type' => SlugFirst::class,
            'slug_fourable_id' => $slugFirstFirst?->id,
        ])
            ->for($slugFirstFirst, 'slugFourable')
            ->create();
        SlugFourFactory::new([
            'slug' => 'sfo2',
            'name' => 'sfo2',
            'created_at' => '2025-12-12 01:49:12',
            'slug_fourable_type' => SlugFirst::class,
            'slug_fourable_id' => $slugFirstSecond->id,
        ])
            ->for($slugFirstSecond, 'slugFourable')
            ->create();
        SlugFourFactory::new([
            'slug' => 'sfo3',
            'name' => 'sfo3',
            'created_at' => '2025-12-11 01:55:12',
            'slug_fourable_type' => SlugSecond::class,
            'slug_fourable_id' => $slugSecondFirst?->id,
        ])
            ->for($slugSecondFirst, 'slugFourable')
            ->create();
    }
}
