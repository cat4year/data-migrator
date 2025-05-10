<?php

declare(strict_types=1);

namespace Tests\Database\Factory;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Tests\App\Models\SlugSecond;
use Tests\App\Models\SlugThree;

/**
 * @extends Factory<SlugThree>
 */
final class SlugThreeFactory extends Factory
{
    protected $model = SlugThree::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => Str::slug($this->faker->sentence($this->faker->numberBetween(1, 3)), '-'),
            'name' => $this->faker->sentence(),
            'slug_second_id' => $this->faker->boolean() ? SlugSecond::factory() : null,
        ];
    }
}
