<?php

declare(strict_types=1);

namespace Tests\Database\Seeders;

use Illuminate\Database\Seeder;
use Tests\Database\Factory\SlugFourFactory;

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
