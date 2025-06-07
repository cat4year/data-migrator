<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Tools\DataSource\Xml;

use DOMAttr;
use DOMCdataSection;
use DOMDocument;
use DOMElement;
use DOMNamedNodeMap;
use DOMText;

final readonly class XmlToArray
{
    private DOMDocument $domDocument;

    public function __construct(string $xml)
    {
        $this->domDocument = new DOMDocument;
        $this->domDocument->preserveWhiteSpace = false;

        $this->domDocument->loadXML($xml);
    }

    public static function convert(string $xml): array
    {
        return new self($xml)->toArray();
    }

    private function convertAttributes(DOMNamedNodeMap $domNamedNodeMap): ?array
    {
        if ($domNamedNodeMap->length === 0) {
            return null;
        }

        $result = [];

        /** @var DOMAttr $item */
        foreach ($domNamedNodeMap as $item) {
            $result[$item->name] = $item->value;
        }

        return ['_attributes' => $result];
    }

    private function convertDomElement(DOMElement $domElement): string|array|null
    {
        $sameNames = [];
        $result = $this->convertAttributes($domElement->attributes);

        foreach ($domElement->childNodes as $node) {
            if (array_key_exists($node->nodeName, $sameNames)) {
                ++$sameNames[$node->nodeName];
            } else {
                $sameNames[$node->nodeName] = 0;
            }
        }

        foreach ($domElement->childNodes as $key => $node) {
            if ($result === null) {
                $result = [];
            }

            if ($node instanceof DOMCdataSection) {
                $result['_cdata'] = $node->data;

                continue;
            }

            if ($node instanceof DOMText) {
                if ($result === '' || $result === '0' || $result === []) {
                    $result = $node->textContent;
                } else {
                    $result['_value'] = $node->textContent;
                }

                continue;
            }

            if ($node instanceof DOMElement) {
                if (is_string($result)) {
                    continue;
                }

                if ($sameNames[$node->nodeName] !== 0) {
                    if (! array_key_exists($node->nodeName, $result)) {
                        $result[$node->nodeName] = [];
                    }

                    $result[$node->nodeName][$key] = $this->convertDomElement($node);
                } else {
                    $result[$node->nodeName] = $this->convertDomElement($node);
                }
            }
        }

        return $result;
    }

    public function toArray(): array
    {
        $result = [];

        if ($this->domDocument->hasChildNodes()) {
            $children = $this->domDocument->childNodes;

            foreach ($children as $child) {
                $result[$child->nodeName] = $this->convertDomElement($child);
            }
        }

        return $result;
    }
}
