<?php

declare(strict_types=1);

namespace Cat4year\DataMigratorTests\Feature;

use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase;
use Override;

abstract class BaseTestCase extends TestCase
{
    use WithWorkbench;

    #[Override]
    protected function getEnvironmentSetUp($app): void
    {
        $app->useStoragePath(realpath(__DIR__.'/../../storage'));
        $app['config']->set('filesystems.disks.public.root', app()->storagePath('/app/public'));

        $app->useEnvironmentPath(__DIR__.'/../../workbench/.env.testing');
        $app->bootstrapWith([LoadEnvironmentVariables::class]);
    }
}
