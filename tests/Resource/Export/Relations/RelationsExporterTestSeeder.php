<?php

declare(strict_types=1);

namespace Cat4year\DataMigratorTests\Resource\Export\Relations;

use Cat4year\DataMigratorTests\App\Models\SlugFirst;
use Cat4year\DataMigratorTests\App\Models\SlugFour;
use Cat4year\DataMigratorTests\App\Models\SlugSecond;
use Cat4year\DataMigratorTests\App\Models\SlugThree;
use Cat4year\DataMigratorTests\Database\Factory\SlugFirstFactory;
use Cat4year\DataMigratorTests\Database\Factory\SlugFourFactory;
use Cat4year\DataMigratorTests\Database\Factory\SlugSecondFactory;
use Cat4year\DataMigratorTests\Database\Factory\SlugThreeFactory;
use Illuminate\Database\Seeder;

final class RelationsExporterTestSeeder extends Seeder
{
    public function run(): void
    {
        SlugFirstFactory::new(['slug_three_id' => null])->createMany(3);

        SlugSecondFactory::new()->create(['slug_first_id' => 3]);
        SlugSecondFactory::new()->create(['slug_first_id' => 2]);
        SlugSecondFactory::new()->create(['slug_first_id' => 1]);

        SlugThreeFactory::new()->create(['slug_second_id' => 2]);
        SlugThreeFactory::new()->create(['slug_second_id' => 2]);
        SlugThreeFactory::new()->create(['slug_second_id' => 1]);

        SlugFirst::query()->find(1)->update(['slug_three_id' => 2]);
        SlugFirst::query()->find(3)->update(['slug_three_id' => 1]);

        SlugFirst::query()->find(2)->slugSecondables()->sync([1, 3]);
        SlugThree::query()->find(1)->slugSeconds()->sync([3]);

        $slugFirstFirst = SlugFirst::query()->find(1);
        $slugFirstSecond = SlugFirst::query()->find(3);
        $slugSecondFirst = SlugSecond::query()->find(1);
        SlugFour::factory()->for($slugFirstFirst, 'slugFourable')->create();
        SlugFourFactory::new()->for($slugFirstSecond, 'slugFourable')->create();
        SlugFourFactory::new()->for($slugSecondFirst, 'slugFourable')->create();
    }
}
