<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/src', __DIR__ . '/tests'])
    ->withSkip([
        __DIR__ . '/tests/Fixtures/**',
        __DIR__ . '/tests/**/Fixtures/**',
        __DIR__ . '/tests/Resource/**',
        __DIR__ . '/tests/**/Resource/**',
    ])
    ->withPhpSets(php84: true);
