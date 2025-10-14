<?php

declare(strict_types=1);

namespace Cat4year\DataMigratorTests\Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Cat4year\DataMigratorTests\Database\Factory\CompositeKeyFactory;
use Illuminate\Database\Seeder;

final class CompositeKeySeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $data1 = [
            'key1' => 'test1',
            'key2' => 'test2',
            'key3' => 'test3',
        ];

        $data2 = [
            'key1' => 'test3',
            'key2' => 'test2',
            'key3' => null,
        ];

        $data3 = [
            'key1' => 'test',
            'key2' => 'test',
            'key3' => 'test',
        ];

        CompositeKeyFactory::new($data1)->createOne();
        CompositeKeyFactory::new($data2)->createOne();
        CompositeKeyFactory::new($data3)->createOne();
    }
}
