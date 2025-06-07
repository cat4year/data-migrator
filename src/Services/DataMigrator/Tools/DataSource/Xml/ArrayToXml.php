<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Tools\DataSource\Xml;

use Closure;
use DOMDocument;
use DOMElement;
use DOMException;
use RuntimeException;

use function key;

final class ArrayToXml
{
    private readonly DOMDocument $domDocument;

    private readonly DOMElement $domElement;

    private string $numericTagNamePrefix = 'numeric_';

    private array $options = ['convertNullToXsiNil' => false, 'convertBoolToString' => false];

    /**
     * @throws DOMException
     */
    public function __construct(
        array $array,
        string|array $rootElement = '',
        private readonly bool $replaceSpacesByUnderScoresInKeyNames = true,
        ?string $xmlEncoding = null,
        string $xmlVersion = '1.0',
        array $domProperties = [],
        ?bool $xmlStandalone = null,
        private bool $addXmlDeclaration = true,
        ?array $options = ['convertNullToXsiNil' => false, 'convertBoolToString' => false]
    ) {
        $this->domDocument = new DOMDocument($xmlVersion, $xmlEncoding ?? '');

        if ($xmlStandalone !== null) {
            $this->domDocument->xmlStandalone = $xmlStandalone;
        }

        if ($domProperties !== []) {
            $this->setDomProperties($domProperties);
        }

        $this->options = array_merge($this->options, $options);

        throw_if($array !== [] && $this->isArrayAllKeySequential($array), new DOMException('Invalid Character Error'));

        $this->domElement = $this->createRootElement($rootElement);

        $this->domDocument->appendChild($this->domElement);

        $this->convertElement($this->domElement, $array);
    }

    public function setNumericTagNamePrefix(string $prefix): void
    {
        $this->numericTagNamePrefix = $prefix;
    }

    /**
     * @throws DOMException
     */
    public static function convert(
        array $array,
        $rootElement = '',
        bool $replaceSpacesByUnderScoresInKeyNames = true,
        ?string $xmlEncoding = null,
        string $xmlVersion = '1.0',
        array $domProperties = [],
        ?bool $xmlStandalone = null,
        bool $addXmlDeclaration = true,
        array $options = ['convertNullToXsiNil' => false]
    ): string {
        $converter = new self(
            $array,
            $rootElement,
            $replaceSpacesByUnderScoresInKeyNames,
            $xmlEncoding,
            $xmlVersion,
            $domProperties,
            $xmlStandalone,
            $addXmlDeclaration,
            $options
        );

        return $converter->toXml();
    }

    public function toXml($options = 0): string
    {
        return $this->addXmlDeclaration
            ? $this->domDocument->saveXML(options: $options)
            : $this->domDocument->saveXML($this->domDocument->documentElement, $options);
    }

    public function toDom(): DOMDocument
    {
        return $this->domDocument;
    }

    private function ensureValidDomProperties(array $domProperties): void
    {
        foreach (array_keys($domProperties) as $key) {
            throw_unless(property_exists($this->domDocument, $key), new RuntimeException($key . ' is not a valid property of DOMDocument'));
        }
    }

    public function setDomProperties(array $domProperties): self
    {
        $this->ensureValidDomProperties($domProperties);

        foreach ($domProperties as $key => $value) {
            $this->domDocument->{$key} = $value;
        }

        return $this;
    }

    public function prettify(): self
    {
        $this->domDocument->preserveWhiteSpace = false;
        $this->domDocument->formatOutput = true;

        return $this;
    }

    public function dropXmlDeclaration(): self
    {
        $this->addXmlDeclaration = false;

        return $this;
    }

    public function addProcessingInstruction(string $target, string $data): self
    {
        $domNodeList = $this->domDocument->getElementsByTagName('*');

        $rootElement = $domNodeList->count() > 0 ? $domNodeList->item(0) : null;

        $processingInstruction = $this->domDocument->createProcessingInstruction($target, $data);

        $this->domDocument->insertBefore($processingInstruction, $rootElement);

        return $this;
    }

    /**
     * @throws DOMException
     */
    private function convertElement(DOMElement $domElement, mixed $value): void
    {
        if ($value instanceof Closure) {
            $value = $value();
        }

        $sequential = $this->isArrayAllKeySequential($value);

        if (! is_array($value)) {
            $value = (string) $value;
            $value = htmlspecialchars($value);
            $value = $this->removeControlCharacters($value);

            $domElement->nodeValue = $value;

            return;
        }

        foreach ($value as $key => $data) {
            if (! $sequential) {
                if (($key === '_attributes') || ($key === '@attributes')) {
                    $this->addAttributes($domElement, $data);
                } elseif ((($key === '_value') || ($key === '@value')) && is_string($data)) {
                    $domElement->nodeValue = htmlspecialchars($data);
                } elseif ((($key === '_cdata') || ($key === '@cdata')) && is_string($data)) {
                    $domElement->appendChild($this->domDocument->createCDATASection($data));
                } elseif ((($key === '_mixed') || ($key === '@mixed')) && is_string($data)) {
                    $fragment = $this->domDocument->createDocumentFragment();
                    $fragment->appendXML($data);
                    $domElement->appendChild($fragment);
                } elseif ($key === '__numeric') {
                    $this->addNumericNode($domElement, $data);
                } elseif (str_starts_with($key, '__custom:')) {
                    $this->addNode($domElement, str_replace('\:', ':', preg_split('/(?<!\\\):/', $key)[1]), $data);
                } else {
                    $this->addNode($domElement, $key, $data);
                }
            } elseif (is_array($data)) {
                $this->addCollectionNode($domElement, $data);
            } else {
                $this->addSequentialNode($domElement, $data);
            }
        }
    }

    /**
     * @throws DOMException
     */
    private function addNumericNode(DOMElement $domElement, mixed $value): void
    {
        foreach ($value as $key => $item) {
            $this->convertElement($domElement, [$this->numericTagNamePrefix . $key => $item]);
        }
    }

    /**
     * @throws DOMException
     */
    private function addNode(DOMElement $domElement, string $key, mixed $value): void
    {
        if ($this->replaceSpacesByUnderScoresInKeyNames) {
            $key = str_replace(' ', '_', $key);
        }

        $child = $this->domDocument->createElement($key);

        $this->addNodeTypeAttribute($child, $value);

        $domElement->appendChild($child);

        $value = $this->convertNodeValue($value);

        $this->convertElement($child, $value);
    }

    private function convertNodeValue(mixed $value): mixed
    {
        if ($this->options['convertBoolToString'] && is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return $value;
    }

    private function addNodeTypeAttribute(DOMElement $domElement, mixed $value): void
    {
        if ($this->options['convertNullToXsiNil'] && $value === null) {
            if (! $this->domElement->hasAttribute('xmlns:xsi')) {
                $this->domElement->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
            }

            $domElement->setAttribute('xsi:nil', 'true');
        }
    }

    /**
     * @throws DOMException
     */
    private function addCollectionNode(DOMElement $domElement, mixed $value): void
    {
        if ($domElement->childNodes->length === 0 && $domElement->attributes->length === 0) {
            $this->convertElement($domElement, $value);

            return;
        }

        $child = $this->domDocument->createElement($domElement->tagName);
        $domElement->parentNode->appendChild($child);
        $this->convertElement($child, $value);
    }

    /**
     * @throws DOMException
     */
    private function addSequentialNode(DOMElement $domElement, mixed $value): void
    {
        if (($domElement->nodeValue === null || $domElement->nodeValue === '' || $domElement->nodeValue === '0') && ! is_numeric($domElement->nodeValue)) {
            $domElement->nodeValue = htmlspecialchars((string) $value);

            return;
        }

        $child = $this->domDocument->createElement($domElement->tagName);
        $child->nodeValue = htmlspecialchars((string) $value);

        $domElement->parentNode->appendChild($child);
    }

    private function isArrayAllKeySequential(array|string|int|null $value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        if (count($value) <= 0) {
            return true;
        }

        if (key($value) === '__numeric') {
            return false;
        }

        return array_unique(array_map('is_int', array_keys($value))) === [true];
    }

    private function addAttributes(DOMElement $domElement, array $data): void
    {
        foreach ($data as $attrKey => $attrVal) {
            $domElement->setAttribute($attrKey, $attrVal ?? '');
        }
    }

    /**
     * @throws DOMException
     */
    private function createRootElement(string|array $rootElement): DOMElement
    {
        if (is_string($rootElement)) {
            $rootElementName = $rootElement ?: 'root';

            return $this->domDocument->createElement($rootElementName);
        }

        $rootElementName = $rootElement['rootElementName'] ?? 'root';

        $element = $this->domDocument->createElement($rootElementName);

        foreach ($rootElement as $key => $value) {
            if ($key !== '_attributes' && $key !== '@attributes') {
                continue;
            }

            $this->addAttributes($element, $value);
        }

        return $element;
    }

    private function removeControlCharacters(string $value): string
    {
        return preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
    }
}
