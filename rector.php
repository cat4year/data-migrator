<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withBootstrapFiles([
        __DIR__ . '/vendor/orchestra/testbench-core/laravel/bootstrap/app.php',
    ])
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/config',
    ])
    ->withSkip([
        __DIR__ . '/tests/Fixtures/**',
        __DIR__ . '/tests/**/Fixtures/**',
        __DIR__ . '/tests/Resource/**',
        __DIR__ . '/tests/**/Resource/**',
    ])
    ->withSets([
        LaravelSetList::LARAVEL_120,
        LaravelSetList::LARAVEL_ARRAYACCESS_TO_METHOD_CALL,
        LaravelSetList::LARAVEL_ARRAY_STR_FUNCTION_TO_STATIC_CALL,
        LaravelSetList::LARAVEL_COLLECTION,
        LaravelSetList::LARAVEL_CONTAINER_STRING_TO_FULLY_QUALIFIED_NAME,
        LaravelSetList::LARAVEL_ELOQUENT_MAGIC_METHOD_TO_QUERY_BUILDER,
        LaravelSetList::LARAVEL_IF_HELPERS,
        LaravelSetList::LARAVEL_FACADE_ALIASES_TO_FULL_NAMES,
        LaravelSetList::LARAVEL_LEGACY_FACTORIES_TO_CLASSES,
        LaravelSetList::LARAVEL_CODE_QUALITY,
        // LaravelSetList::LARAVEL_STATIC_TO_INJECTION, //incorrect work with lambda
    ])
    ->withPhpSets(php84: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        instanceOf: true,
        earlyReturn: true,
        carbon: true,
        rectorPreset: true,
        phpunitCodeQuality: true,
    )
    ->withImportNames(importShortClasses: false)
    ->withPHPStanConfigs([__DIR__ . '/phpstan.neon'])
    ->withCache(__DIR__ . '/storage/rector', FileCacheStorage::class)
    ->withParallel(360)
    ->withoutParallel();
