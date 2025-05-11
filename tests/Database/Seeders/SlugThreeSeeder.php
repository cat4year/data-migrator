<?php

declare(strict_types=1);

namespace Cat4year\DataMigratorTests\Database\Seeders;

use Illuminate\Database\Seeder;
use Cat4year\DataMigratorTests\Database\Factory\SlugThreeFactory;

final class SlugThreeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SlugThreeFactory::new()->createMany(5);
    }
}
