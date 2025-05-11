<?php

declare(strict_types=1);

namespace Cat4year\DataMigratorTests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Cat4year\DataMigratorTests\App\Models\SlugFirst;

final class ModelFirstTest extends BaseTestCase
{
    use DatabaseMigrations;

    public function test_create(): void
    {
        $testWithSlug = SlugFirst::factory()->create();

        $this->assertDatabaseHas('slug_firsts', ['id' => $testWithSlug->id]);
    }
}
