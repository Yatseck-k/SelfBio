<?php

declare(strict_types=1);

namespace Whodoku\Application\Appearance;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Makes an uploaded SVG safe to serve from our own origin: no scripts, no foreign content, no event handlers and
 * no references to anything but the file itself. The root must be `svg` with a `viewBox`.
 */
class SvgSanitizer
{
    private const array FORBIDDEN_ELEMENTS = ['script', 'foreignobject', 'iframe', 'object', 'embed', 'style', 'audio', 'video', 'canvas', 'a', 'animate', 'set', 'animatetransform', 'animatemotion'];

    /** @throws InvalidImage */
    public function clean(string $source): string
    {
        if (preg_match('/<!(DOCTYPE|ENTITY)/i', $source) === 1) {
            throw new InvalidImage('В SVG не допускаются DOCTYPE и ENTITY.');
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($source, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->documentElement;
        if (!$loaded || !$root instanceof DOMElement || strtolower($root->localName) !== 'svg') {
            throw new InvalidImage('Файл не похож на SVG.');
        }
        if (!$root->hasAttribute('viewBox')) {
            throw new InvalidImage('У SVG должен быть атрибут viewBox.');
        }

        $this->stripAttributes($root);
        $this->sweep($root);

        return (string) $document->saveXML($root);
    }

    private function sweep(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                if (in_array(strtolower($child->localName), self::FORBIDDEN_ELEMENTS, true)) {
                    $node->removeChild($child);

                    continue;
                }
                $this->stripAttributes($child);
                $this->sweep($child);
            } elseif (!in_array($child->nodeType, [XML_TEXT_NODE, XML_CDATA_SECTION_NODE], true)) {
                $node->removeChild($child);
            }
        }
    }

    private function stripAttributes(DOMElement $element): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->nodeName);
            $value = trim($attribute->nodeValue ?? '');
            $reference = in_array($name, ['href', 'xlink:href'], true);
            if (str_starts_with($name, 'on')
                || ($reference && !str_starts_with($value, '#'))
                || preg_match('/(javascript:|data:|url\(\s*[\'"]?(?!#))/i', $value) === 1) {
                $element->removeAttributeNode($attribute);
            }
        }
    }
}
