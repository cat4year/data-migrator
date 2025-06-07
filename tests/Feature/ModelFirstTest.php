<?php

declare(strict_types=1);

namespace Cat4year\DataMigratorTests\Feature;

use Cat4year\DataMigratorTests\App\Models\SlugFirst;
use Illuminate\Foundation\Testing\DatabaseMigrations;

final class ModelFirstTest extends BaseTestCase
{
    use DatabaseMigrations;

    public function test_create(): void
    {
        $slugFirst = SlugFirst::factory()->create();

        $this->assertDatabaseHas('slug_firsts', ['id' => $slugFirst->id]);
    }
}
