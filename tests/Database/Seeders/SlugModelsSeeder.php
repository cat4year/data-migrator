<?php

declare(strict_types=1);

namespace Tests\Database\Seeders;

use Illuminate\Database\Seeder;
use Tests\App\Models\SlugFirst;
use Tests\Database\Factory\SlugFirstFactory;
use Tests\Database\Factory\SlugFourFactory;
use Tests\Database\Factory\SlugSecondFactory;
use Tests\Database\Factory\SlugThreeFactory;

final class SlugModelsSeeder extends Seeder
{
    public function run(): void
    {
        $first = SlugFirstFactory::new()->createMany(3);
        $second = SlugSecondFactory::new()->recycle($first)->createMany(3);
        SlugThreeFactory::new()->recycle([$second, $first])->createMany(3);
        $first->each(static fn (SlugFirst $item) => $item->slugSeconds()->sync([$second->random()->id]));
        $first->first()->slugSecondables()->sync([1, 2]);
        $first->last()->slugSecondables()->sync([3]);

        SlugFourFactory::new()->recycle([$second, $first])->createMany(3);
    }
}
