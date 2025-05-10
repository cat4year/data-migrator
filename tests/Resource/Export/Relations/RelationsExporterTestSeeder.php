<?php

declare(strict_types=1);

namespace Tests\Resource\Export\Relations;

use Illuminate\Database\Seeder;
use Tests\App\Models\SlugFirst;
use Tests\App\Models\SlugFour;
use Tests\App\Models\SlugSecond;
use Tests\App\Models\SlugThree;
use Tests\Database\Factory\SlugFirstFactory;
use Tests\Database\Factory\SlugFourFactory;
use Tests\Database\Factory\SlugSecondFactory;
use Tests\Database\Factory\SlugThreeFactory;

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
