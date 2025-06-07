<?php

declare(strict_types=1);

namespace Cat4year\DataMigratorTests\Database\Seeders;

use Cat4year\DataMigratorTests\Database\Factory\SlugFourFactory;
use Illuminate\Database\Seeder;

final class SlugFourSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SlugFourFactory::new()->createMany(5);
    }
}
