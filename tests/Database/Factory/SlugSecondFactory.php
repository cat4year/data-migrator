<?php

declare(strict_types=1);

namespace Cat4year\DataMigratorTests\Database\Factory;

use Cat4year\DataMigratorTests\App\Models\SlugFirst;
use Cat4year\DataMigratorTests\App\Models\SlugSecond;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SlugSecond>
 */
final class SlugSecondFactory extends Factory
{
    protected $model = SlugSecond::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => Str::slug(fake()->sentence(fake()->numberBetween(1, 3)), '-'),
            'name' => fake()->sentence(),
            'slug_first_id' => SlugFirst::factory(),
        ];
    }
}
