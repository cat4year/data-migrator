<?php

declare(strict_types=1);

namespace Cat4year\DataMigratorTests\Database\Seeders;

use Illuminate\Database\Seeder;
use Cat4year\DataMigratorTests\Database\Factory\SlugSecondFactory;

final class SlugSecondSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SlugSecondFactory::new()->createMany(5);
    }
}
