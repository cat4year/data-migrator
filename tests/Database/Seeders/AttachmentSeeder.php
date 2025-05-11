<?php

declare(strict_types=1);

namespace Cat4year\DataMigratorTests\Database\Seeders;

use Illuminate\Database\Seeder;
use Orchid\Platform\Database\Factories\AttachmentFactory;
use Cat4year\DataMigratorTests\Database\Factory\SlugFirstFactory;

final class AttachmentSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [
                'id' => 8,
                'name' => '5d11b2895cecbda580a9f667bd26a6389143c982',
                'original_name' => 'avatar.jpg',
                'mime' => 'image/jpeg',
                'extension' => 'jpg',
                'size' => 13195,
                'sort' => 0,
                'path' => 'avatars/',
                'description' => '',
                'alt' => '',
                'hash' => '925d617146e7d345e1cbca4e4bcc4f7bbc305a3b',
                'disk' => 'testing',
                'user_id' => 1,
                'group' => 'avatar',
            ],
            [
                'id' => 9,
                'name' => '36c0aa11ae62359f9e164464e9110c172aa117af',
                'original_name' => 'second-avatar.jpg',
                'mime' => 'image/jpeg',
                'extension' => 'jpg',
                'size' => 11340,
                'sort' => 0,
                'path' => 'avatars/3e7cd4111c70e712f1109a90fe425051947ef6b6.png',
                'description' => '',
                'alt' => '',
                'hash' => '3d52f0b413eeea53d74eb3772b82d4cafa6feb63',
                'disk' => 'testing',
                'user_id' => 1,
                'group' => 'avatar',
            ],
            [
                'id' => 10,
                'name' => '3e7cd4111c70e712f1109a90fe425051947ef6b6',
                'original_name' => 'third-user.png',
                'mime' => 'image/png',
                'extension' => 'png',
                'size' => 39362,
                'sort' => 0,
                'path' => 'avatars/',
                'description' => '',
                'alt' => '',
                'hash' => 'dcaabece4b669932e9749836622940c81c346616',
                'disk' => 'testing',
                'user_id' => 1,
                'group' => 'avatar',
            ]
        ];

        AttachmentFactory::new()->createMany($data);
    }
}
