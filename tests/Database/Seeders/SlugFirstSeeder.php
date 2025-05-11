<?php

declare(strict_types=1);

namespace Cat4year\DataMigratorTests\Database\Seeders;

use Illuminate\Database\Seeder;
use Cat4year\DataMigratorTests\Database\Factory\SlugFirstFactory;

final class SlugFirstSeeder extends Seeder
{
    public function run(): void
    {
        SlugFirstFactory::new()->createMany(5);
    }
}
