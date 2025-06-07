<?php

declare(strict_types=1);

namespace Cat4year\DataMigratorTests\Database\Seeders;

use Cat4year\DataMigratorTests\App\Models\SlugFirst;
use Cat4year\DataMigratorTests\Database\Factory\SlugFirstFactory;
use Cat4year\DataMigratorTests\Database\Factory\SlugFourFactory;
use Cat4year\DataMigratorTests\Database\Factory\SlugSecondFactory;
use Cat4year\DataMigratorTests\Database\Factory\SlugThreeFactory;
use Illuminate\Database\Seeder;

final class SlugModelsSeeder extends Seeder
{
    public function run(): void
    {
        $first = SlugFirstFactory::new()->createMany(3);
        $second = SlugSecondFactory::new()->recycle($first)->createMany(3);
        SlugThreeFactory::new()->recycle([$second, $first])->createMany(3);
        $first->each(static fn (SlugFirst $slugFirst) => $slugFirst->slugSeconds()->sync([$second->random()->id]));
        $first->first()->slugSecondables()->sync([1, 2]);
        $first->last()->slugSecondables()->sync([3]);

        SlugFourFactory::new()->recycle([$second, $first])->createMany(3);
    }
}
