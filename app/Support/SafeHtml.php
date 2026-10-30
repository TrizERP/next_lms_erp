<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allow-list cleaner for rich text that staff type in and other people then see (login-page title,
 * curriculum text, syllabus messages, ...). Blade views printed these with `{!! !!}`, so a script
 * or event handler saved by one user ran in everyone else's browser.
 *
 * Formatting tags survive; scripts, event handlers, `javascript:` links, forms and frames do not.
 */
class SafeHtml
{
    private const TAGS = [
        'a', 'abbr', 'b', 'blockquote', 'br', 'caption', 'code', 'col', 'colgroup', 'div', 'em', 'font', 'h1', 'h2', 'h3',
        'h4', 'h5', 'h6', 'hr', 'i', 'img', 'li', 'ol', 'p', 'pre', 's', 'small', 'span', 'strike', 'strong', 'sub',
        'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'u', 'ul', 'center', 'label',
    ];

    private const ATTRS = ['alt', 'class', 'colspan', 'height', 'href', 'rowspan', 'src', 'style', 'title', 'width', 'align', 'color', 'size', 'face', 'target'];

    public static function clean($html): string
    {
        $html = (string) $html;
        if ($html === '' || strpbrk($html, '<&') === false) {
            return $html;
        }

        $doc = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $body = $doc->getElementsByTagName('body')->item(0);
        if (!$body) {
            return e(strip_tags($html));
        }
        self::walk($body);

        $out = '';
        foreach (iterator_to_array($body->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (!in_array($tag, self::TAGS, true)) {
                    // Drop the element; keep harmless text of wrappers, but never of script/style.
                    if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'svg', 'math', 'link', 'meta', 'base', 'template', 'noscript'], true)) {
                        $node->removeChild($child);
                    } else {
                        self::walk($child);
                        while ($child->firstChild) {
                            $node->insertBefore($child->firstChild, $child);
                        }
                        $node->removeChild($child);
                    }
                    continue;
                }
                self::cleanAttributes($child, $tag);
                self::walk($child);
            } elseif ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);
            }
        }
    }

    private static function cleanAttributes(DOMElement $el, string $tag): void
    {
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->name);
            $value = $attr->value;
            $plain = strtolower(preg_replace('/[\x00-\x20\x7f]+/', '', html_entity_decode($value, ENT_QUOTES | ENT_HTML5)));

            $ok = in_array($name, self::ATTRS, true);
            if ($ok && ($name === 'href' || $name === 'src')) {
                // No scheme at all (relative path / anchor) is fine; otherwise only these schemes.
                $hasScheme = (bool) preg_match('#^[a-z][a-z0-9+.\-]*:#', $plain);
                $ok = !$hasScheme
                    || (bool) preg_match('#^(https?:|mailto:|tel:|data:image/(png|jpe?g|gif|webp);base64,)#', $plain);
                if ($name === 'href' && str_starts_with($plain, 'data:')) {
                    $ok = false;
                }
            }
            if ($ok && $name === 'style') {
                $ok = !preg_match('/expression|javascript:|url\s*\(|behavior|-moz-binding|@import/', $plain);
            }
            if (!$ok) {
                $el->removeAttribute($attr->name);
            }
        }

        if ($tag === 'a' && strtolower($el->getAttribute('target')) === '_blank') {
            $el->setAttribute('rel', 'noopener noreferrer');
        }
    }
}
