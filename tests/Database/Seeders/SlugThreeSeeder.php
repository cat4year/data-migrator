<?php

declare(strict_types=1);

namespace Tests\Database\Seeders;

use Illuminate\Database\Seeder;
use Tests\Database\Factory\SlugThreeFactory;

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
