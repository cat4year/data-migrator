<?php

declare(strict_types=1);

namespace Cat4year\DataMigratorTests\Database\Seeders;

use Cat4year\DataMigratorTests\Database\Factory\SlugSecondFactory;
use Illuminate\Database\Seeder;

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
