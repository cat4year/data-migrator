<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Tools\DataSource\Xml;

use Cat4year\DataMigrator\Services\DataMigrator\Tools\DataSource\MigrationDataSourceFormat;
use DOMException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use JsonException;

final readonly class XmlMigrationDataSourceFormat implements MigrationDataSourceFormat
{
    private array $keysMapMultipleToConcrete;

    public function __construct(private Filesystem $filesystem)
    {
        $this->keysMapMultipleToConcrete = ['items' => 'item', 'relations' => 'relation'];
    }

    /**
     * @throws DOMException
     * @throws JsonException
     */
    public function save(array $data, string $path): void
    {
        $this->filesystem->ensureDirectoryExists(dirname($path));
        $this->filesystem->put($path, $this->prepare($data));
    }

    public function prepareForMigration(array $exportData): string
    {
        $preparedData = $this->prepare($exportData);

        return <<<XML
{$preparedData}
XML;
    }

    /**
     * @throws DOMException
     * @throws JsonException
     */
    private function prepare(array $exportData): string
    {
        $exportDataWithoutObjects = json_decode(
            json_encode($exportData, JSON_THROW_ON_ERROR),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $exportDataCorrectStructure = $this->prepareBeforeArrayToXml($exportDataWithoutObjects);
        $arrayToXml = new ArrayToXml($exportDataCorrectStructure, 'data', xmlEncoding: 'utf-8', options: ['convertBoolToString' => true]);

        return $arrayToXml->prettify()->toXml();
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
        if ($value === []) {
            return [];
        }

        if (is_int(array_key_first($value))) {
            return $value;
        }

        return [$value];
    }
}
