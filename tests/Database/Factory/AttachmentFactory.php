<?php

declare(strict_types=1);

namespace Cat4year\DataMigratorTests\Database\Factory;

use Cat4year\DataMigratorTests\App\Models\Attachment;
use Exception;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attachment>
 */
final class AttachmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Attachment::class;

    /**
     * Define the model's default state.
     *
     * @return array
     *
     * @throws Exception
     */
    public function definition()
    {
        return [
            'name' => Str::random(),
            'original_name' => Str::random(),
            'mime' => 'unknown',
            'extension' => 'unknown',
            'size' => random_int(1, 100),
            'sort' => random_int(1, 100),
            'path' => Str::random(),
            'description' => Str::random(),
            'alt' => Str::random(),
            'hash' => Str::random(),
            'disk' => 'public',
            'group' => null,
        ];
    }
}
