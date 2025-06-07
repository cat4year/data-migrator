<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Helpers;

use Brick\VarExporter\ExportException;
use Brick\VarExporter\VarExporter;

if (! function_exists('var_pretty_export')) {
    /**
     * @throws ExportException
     */
    function var_pretty_export(array $expression, bool $return = false): ?string
    {
        $result = VarExporter::export($expression, VarExporter::NO_SET_STATE | VarExporter::NO_CLOSURES);

        if ($return) {
            return $result;
        }

        echo $result;

        return null;
    }
}
