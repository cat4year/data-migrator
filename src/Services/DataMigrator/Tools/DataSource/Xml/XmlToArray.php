<?php

declare(strict_types=1);

namespace Cat4year\DataMigrator\Services\DataMigrator\Tools\DataSource\Xml;

use DOMAttr;
use DOMCdataSection;
use DOMDocument;
use DOMElement;
use DOMNamedNodeMap;
use DOMText;

final class XmlToArray
{
    private DOMDocument $document;

    public function __construct(string $xml)
    {
        $this->document = new DOMDocument;
        $this->document->preserveWhiteSpace = false;

        $this->document->loadXML($xml);
    }

    public static function convert(string $xml): array
    {
        return (new self($xml))->toArray();
    }

    private function convertAttributes(DOMNamedNodeMap $nodeMap): ?array
    {
        if ($nodeMap->length === 0) {
            return null;
        }

        $result = [];

        /** @var DOMAttr $item */
        foreach ($nodeMap as $item) {
            $result[$item->name] = $item->value;
        }

        return ['_attributes' => $result];
    }

    private function convertDomElement(DOMElement $element)
    {
        $sameNames = [];
        $result = $this->convertAttributes($element->attributes);

        foreach ($element->childNodes as $node) {
            if (array_key_exists($node->nodeName, $sameNames)) {
                $sameNames[$node->nodeName]++;
            } else {
                $sameNames[$node->nodeName] = 0;
            }
        }

        foreach ($element->childNodes as $key => $node) {
            if ($result === null) {
                $result = [];
            }

            if ($node instanceof DOMCdataSection) {
                $result['_cdata'] = $node->data;

                continue;
            }

            if ($node instanceof DOMText) {
                if (empty($result)) {
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

                if ($sameNames[$node->nodeName]) {
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

        if ($this->document->hasChildNodes()) {
            $children = $this->document->childNodes;

            foreach ($children as $child) {
                $result[$child->nodeName] = $this->convertDomElement($child);
            }
        }

        return $result;
    }
}
