<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\App\Models\SlugFirst;

final class ModelFirstTest extends BaseTestCase
{
    use DatabaseMigrations;

    public function test_create(): void
    {
        $testWithSlug = SlugFirst::factory()->create();

        $this->assertDatabaseHas('slug_firsts', ['id' => $testWithSlug->id]);
    }
}
