<?php

namespace App\Services\lms\H5P;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Reduces authored or model-written HTML to the small set a learning screen
 * needs: paragraphs, emphasis, lists, short headings, sub/superscripts, simple
 * tables and links.
 *
 * WHY THIS EXISTS WHEN THE PLAYER ALREADY SANITISES. The player runs DOMPurify
 * on everything it renders, and that stays the defence that matters -- an
 * imported .h5p can carry anything. This is a second, narrower job: a model
 * asked for "HTML" returns <div style="...">, <font>, inline colours, headings
 * of every size, and the occasional <script>; a teacher pasting from a word
 * processor does the same. Normalising it on the way IN means a generated
 * scenario looks like the rest of the product instead of like whichever page it
 * was copied from, and means what is STORED is already clean.
 *
 * Unknown elements are unwrapped (their text is kept); <script>, <style>,
 * <iframe> and their relatives are removed with their contents. No attribute
 * survives except an http(s)/mailto `href` on a link, which gets
 * rel="noopener noreferrer" and target="_blank".
 *
 * PURE: DOM in, string out.
 */
final class HtmlLite
{
    private const ALLOWED = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'h3', 'h4',
        'sub', 'sup', 'blockquote', 'a', 'span',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
    ];

    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template', 'svg', 'math', 'form', 'button', 'input', 'textarea', 'select', 'link', 'meta'];

    /** Headings above h4 become h3/h4: a scenario screen is not a document. */
    private const HEADING_MAP = ['h1' => 'h3', 'h2' => 'h3', 'h3' => 'h3', 'h4' => 'h4', 'h5' => 'h4', 'h6' => 'h4'];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('root');
        if ($root === null) {
            return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
        }

        self::walk($root);
        self::wrapLooseText($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    /**
     * Text sitting between blocks at the top level becomes a paragraph.
     *
     * A question taken from the bank is often "some words, then a table": the
     * words are a bare text node next to a <table>, which would render with no
     * spacing and read as part of the table. Consecutive loose text and inline
     * elements are gathered into one <p>; blocks are left where they are.
     */
    private static function wrapLooseText(DOMElement $root): void
    {
        $blocks = ['p', 'ul', 'ol', 'h3', 'h4', 'blockquote', 'table'];
        $run = [];

        $flush = function (?DOMNode $before) use (&$run, $root): void {
            $hasContent = false;
            foreach ($run as $node) {
                if (trim($node->textContent) !== '' || $node instanceof DOMElement) {
                    $hasContent = true;
                }
            }
            if ($hasContent) {
                $p = $root->ownerDocument->createElement('p');
                $root->insertBefore($p, $before);
                foreach ($run as $node) {
                    $p->appendChild($node);
                }
                // The spaces that separated the words from the neighbouring
                // block are not part of the paragraph.
                if ($p->firstChild instanceof DOMText) {
                    $p->firstChild->nodeValue = ltrim((string) $p->firstChild->nodeValue);
                }
                if ($p->lastChild instanceof DOMText) {
                    $p->lastChild->nodeValue = rtrim((string) $p->lastChild->nodeValue);
                }
            } else {
                foreach ($run as $node) {
                    $root->removeChild($node);
                }
            }
            $run = [];
        };

        foreach (iterator_to_array($root->childNodes) as $child) {
            if ($child instanceof DOMElement && in_array(strtolower($child->nodeName), $blocks, true)) {
                if ($run !== []) {
                    $flush($child);
                }

                continue;
            }
            $run[] = $child;
        }
        if ($run !== []) {
            $flush(null);
        }
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                if ($child->nodeType === XML_COMMENT_NODE) {
                    $node->removeChild($child);
                }

                continue;
            }

            $name = strtolower($child->nodeName);

            if (in_array($name, self::DROP_WITH_CONTENT, true)) {
                $node->removeChild($child);

                continue;
            }

            if (isset(self::HEADING_MAP[$name])) {
                $child = self::rename($child, self::HEADING_MAP[$name]);
                $name = strtolower($child->nodeName);
            } elseif ($name === 'div' || $name === 'section' || $name === 'article') {
                // A block wrapper becomes a paragraph, so spacing survives.
                $child = self::rename($child, 'p');
                $name = 'p';
            } elseif ($name === 'b' || $name === 'i') {
                // One spelling of emphasis, so the same text compares equal.
                $child = self::rename($child, $name === 'b' ? 'strong' : 'em');
                $name = strtolower($child->nodeName);
            }

            self::walk($child);

            if (! in_array($name, self::ALLOWED, true)) {
                self::unwrap($child);

                continue;
            }

            self::stripAttributes($child);
        }
    }

    private static function stripAttributes(DOMElement $element): void
    {
        $href = $element->nodeName === 'a' ? trim($element->getAttribute('href')) : '';

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $element->removeAttributeNode($attribute);
        }

        if ($element->nodeName === 'a') {
            if ($href !== '' && preg_match('/^(https?:\/\/|mailto:)/i', $href) === 1) {
                $element->setAttribute('href', $href);
                $element->setAttribute('target', '_blank');
                $element->setAttribute('rel', 'noopener noreferrer');
            } else {
                self::unwrap($element);
            }
        }
    }

    private static function rename(DOMElement $element, string $to): DOMElement
    {
        $replacement = $element->ownerDocument->createElement($to);
        while ($element->firstChild !== null) {
            $replacement->appendChild($element->firstChild);
        }
        $element->parentNode->replaceChild($replacement, $element);

        return $replacement;
    }

    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;
        if ($parent === null) {
            return;
        }
        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }
}
