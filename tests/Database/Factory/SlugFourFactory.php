<?php

declare(strict_types=1);

namespace Cat4year\DataMigratorTests\Database\Factory;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Cat4year\DataMigratorTests\App\Models\SlugFirst;
use Cat4year\DataMigratorTests\App\Models\SlugFour;
use Cat4year\DataMigratorTests\App\Models\SlugSecond;

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
