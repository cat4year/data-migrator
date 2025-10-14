<?php

declare(strict_types=1);

namespace Cat4year\DataMigratorTests\Database\Factory;

use Cat4year\DataMigratorTests\App\Models\CompositeKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CompositeKey>
 */
final class CompositeKeyFactory extends Factory
{
    protected $model = CompositeKey::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key1' => Str::slug(fake()->sentence(fake()->numberBetween(1, 3))),
            'key2' => Str::slug(fake()->sentence(fake()->numberBetween(1, 3))),
            'key3' => fake()->boolean(75)
                ? Str::slug(fake()->sentence(fake()->numberBetween(1, 3)))
                : null,
        ];
    }
}
