<?php

declare(strict_types=1);

namespace Tests\Database\Seeders;

use Illuminate\Database\Seeder;
use Tests\Database\Factory\SlugFirstFactory;

final class SlugFirstSeeder extends Seeder
{
    public function run(): void
    {
        SlugFirstFactory::new()->createMany(5);
    }
}
