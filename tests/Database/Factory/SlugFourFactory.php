<?php

declare(strict_types=1);

namespace Tests\Database\Factory;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Tests\App\Models\SlugFirst;
use Tests\App\Models\SlugFour;
use Tests\App\Models\SlugSecond;

/**
 * @extends Factory<SlugFour>
 */
final class SlugFourFactory extends Factory
{
    protected $model = SlugFour::class;

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
            'slug_fourable_type' => $this->faker->randomElement([
                SlugFirst::class,
                SlugSecond::class,
            ]),
            'slug_fourable_id' => static fn (array $attributes) => $attributes['slug_fourable_type']::factory(),
        ];
    }
}
