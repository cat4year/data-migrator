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
            'slug' => Str::slug($this->faker->sentence($this->faker->numberBetween(1, 3)), '-'),
            'bool_test' => $this->faker->boolean(),
            'timestamp_test' => $this->faker->dateTime(),
            'string_test' => $this->faker->realText(),
            'int_test' => $this->faker->randomNumber(),
            'slug_three_id' => $this->faker->boolean() ? SlugThree::factory() : null,
        ];
    }
}
