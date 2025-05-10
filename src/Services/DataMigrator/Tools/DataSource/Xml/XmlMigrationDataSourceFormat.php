<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Tools\DataSource\Xml;

use Cat4year\DataMigrator\Services\DataMigrator\Tools\DataSource\MigrationDataSourceFormat;
use DOMException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

final readonly class XmlMigrationDataSourceFormat implements MigrationDataSourceFormat
{
    private array $keysMapMultipleToConcrete;

    public function __construct(private Filesystem $files)
    {
        $this->keysMapMultipleToConcrete = ['items' => 'item', 'relations' => 'relation'];
    }

    /**
     * @throws DOMException
     */
    public function save(array $data, string $path): void
    {
        $data = $this->prepareBeforeArrayToXml($data);

        $xml = new ArrayToXml($data, 'data', xmlEncoding: 'utf-8', options: ['convertBoolToString' => true]);

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $xml->prettify()->toXml());
    }

    public function load(string $resource): array
    {
        $xml = Storage::disk('public')->get($resource);

        $array = XmlToArray::convert($xml)['data'];

        return $this->prepareAfterXmlToArray($array);
    }

    private function prepareBeforeArrayToXml(array $data): array
    {
        foreach ($data as $key => &$value) {
            if (array_key_exists($key, $this->keysMapMultipleToConcrete)) {
                $concreteKey = $this->keysMapMultipleToConcrete[$key];
                $value = [$concreteKey => $value];
            }
            if (is_array($value)) {
                $value = $this->prepareBeforeArrayToXml($value);
            }
        }
        unset($value);

        return $data;
    }

    private function prepareAfterXmlToArray(array $data): array
    {
        return $this->fixConcreteKeysStructure($data);
    }

    private function fixConcreteKeysStructure(array $data): array
    {
        foreach ($data as $key => &$value) {
            if (! is_array($value)) {
                continue;
            }

            if (array_key_exists($key, $this->keysMapMultipleToConcrete)) {
                $concreteKey = $this->keysMapMultipleToConcrete[$key];
                $value = $this->fixWhenOneElementIsNotOnArray($value[$concreteKey]);
            }

            $value = $this->fixConcreteKeysStructure($value);
        }
        unset($value);

        return $data;
    }

    private function fixWhenOneElementIsNotOnArray(array $value): array
    {
        if (count($value) === 0) {
            return [];
        }

        if (is_int(array_key_first($value))) {
            return $value;
        }

        return [$value];
    }
}
