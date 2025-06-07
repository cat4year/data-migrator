<?php

declare(strict_types=1);

namespace Cat4year\DataMigratorTests\Database\Factory;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Cat4year\DataMigratorTests\App\Models\SlugFirst;
use Cat4year\DataMigratorTests\App\Models\SlugThree;

/**
 * @extends Factory<SlugFirst>
 */
final class SlugFirstFactory extends Factory
{
    protected $model = SlugFirst::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => Str::slug(fake()->sentence(fake()->numberBetween(1, 3)), '-'),
            'bool_test' => fake()->boolean(),
            'timestamp_test' => fake()->dateTime(),
            'string_test' => fake()->realText(),
            'int_test' => fake()->randomNumber(),
            'slug_three_id' => fake()->boolean() ? SlugThree::factory() : null,
        ];
    }
}
