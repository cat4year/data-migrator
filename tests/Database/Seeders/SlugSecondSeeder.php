<?php

declare(strict_types=1);

namespace Tests\Database\Seeders;

use Illuminate\Database\Seeder;
use Tests\Database\Factory\SlugSecondFactory;

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
