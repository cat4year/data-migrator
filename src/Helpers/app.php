<?php

declare(strict_types = 1);

namespace Cat4year\DataMigrator\Helpers;

if (!function_exists('var_pretty_export')) {
    /** @noinspection DebugFunctionUsageInspection */
    function var_pretty_export(array $expression, bool $return = false): ?string
    {
        $export = var_export($expression, true);
        $export = preg_replace('/^( *)(.*)/m', '$1$1$2', $export);
        $array = preg_split("/\r\n|\n|\r/", $export);
        $array = preg_replace(["/\s*array\s\($/", "/\)(,)?$/", "/\s=>\s$/"], [null, ']$1', ' => ['], $array);
        $export = implode(PHP_EOL, array_filter(['['] + $array));
        if ($return) {
            return $export;
        }

        echo $export;

        return null;
    }
}
