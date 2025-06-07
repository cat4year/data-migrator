<?php

declare(strict_types=1);

namespace Cat4year\DataMigratorTests\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

use function Orchestra\Testbench\package_path;

final class AdaptSlugModelsForImport extends Seeder
{
    public function run(): void
    {
        $path = package_path('tests/Feature/Fixtures/new_export.php');

        if (! file_exists($path)) {
            return;
        }

        $data = require $path;

        foreach ($data as $tableName => $tableData) {
            $modelClass = $this->getModelClass($tableName);
            if (! class_exists($modelClass)) {
                continue;
            }

            foreach ($tableData['items'] as $id => $item) {
                $modelClass::where('id', $id)->update(['slug' => $item['slug']]);
            }
        }
    }

    /**
     * @param non-empty-string $tableName
     * @return class-string
     */
    private function getModelClass(string $tableName): string
    {
        $modelName = str_replace(' ', '', ucwords(str_replace('_', ' ', Str::singular($tableName))));

        return 'Cat4year\DataMigratorTests\App\Models\\'.$modelName;
    }
}
