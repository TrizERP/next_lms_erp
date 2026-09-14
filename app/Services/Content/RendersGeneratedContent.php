<?php

namespace App\Services\Content;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * The HTML -> PDF pipeline shared by every content generator.
 *
 * These methods were private on contentController and served only the Gemini
 * branch. ContentGenerationService needs byte-identical output, so they live
 * here instead of being duplicated - the Gemini branch keeps behaving exactly
 * as it did.
 */
trait RendersGeneratedContent
{
    /**
     * Structural tags a generated document may use.
     *
     * Wider than a plain prose allowlist because generated content is laid out,
     * not just written: cover panels, callout cards, figures and tiles all need
     * containers. Presentation is carried entirely by `class` plus the
     * stylesheet below, so no inline `style` attribute is ever accepted.
     */
    protected array $generatedContentTags = [
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'p', 'br', 'hr', 'strong', 'b', 'em', 'i', 'sub', 'sup',
        'ul', 'ol', 'li', 'blockquote',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
        'div', 'span', 'section', 'figure', 'figcaption', 'img',
    ];

    /**
     * Attributes kept per tag. Everything else - every `on*` handler, every
     * inline `style`, every `srcset`/`formaction` - is dropped.
     */
    protected array $generatedContentAttributes = [
        '*' => ['class'],
        'img' => ['class', 'src', 'alt'],
        'td' => ['class', 'colspan', 'rowspan'],
        'th' => ['class', 'colspan', 'rowspan'],
    ];

    protected function renderGeneratedContentPdf($content, $chapterName, $contentType)
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isFontSubsettingEnabled', true);
        // Required so <img src="https://...spaces..."> resolves during render.
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $html = $this->generatedContentHtml($content, $chapterName, $contentType);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Only images already published to our own storage may be referenced. A
     * generated document must never be able to pull a remote URL of its own
     * choosing - that would let it phone out from the PDF renderer.
     */
    protected function allowedImageHosts(): array
    {
        $hosts = [];
        $endpoints = [
            config('filesystems.disks.digitalocean.endpoint'),
            env('DO_SPACES_CDN'),
        ];

        foreach ($endpoints as $endpoint) {
            $host = parse_url((string) $endpoint, PHP_URL_HOST);
            if (!$host) {
                continue;
            }
            $hosts[] = strtolower($host);
            $bucket = (string) config('filesystems.disks.digitalocean.bucket');
            if ($bucket !== '') {
                $hosts[] = strtolower($bucket . '.' . $host);
            }
        }

        return array_values(array_unique(array_filter($hosts)));
    }

    protected function isAllowedImageSrc(string $src): bool
    {
        $parts = parse_url(trim($src));
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return false;
        }

        $host = strtolower($parts['host']);
        foreach ($this->allowedImageHosts() as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Strip any stray markdown fence, then reduce the document to the tag and
     * attribute allowlists above.
     *
     * This is also what gets stored in content_master.description, so the
     * stored body is sanitised server-side before it ever reaches a browser.
     * strip_tags alone is not enough: it removes disallowed *tags* but keeps
     * every attribute on the tags it preserves, so a <td onclick="..."> would
     * survive it untouched. The DOM pass is what removes those.
     */
    protected function formatGeneratedPdfBody($content)
    {
        $content = trim((string) $content);
        $content = preg_replace('/^```(?:html)?\s*|\s*```$/i', '', $content);

        if ($content === '') {
            return '';
        }

        // Plain text in, plain text out - unchanged from the original behaviour.
        if ($content === strip_tags($content)) {
            return nl2br(e($content));
        }

        // Drop script/style blocks WITH their contents. strip_tags would remove
        // only the tags and leave the code behind as visible text.
        $content = preg_replace('#<(script|style)\b[^>]*>.*?</\1\s*>#is', '', $content);
        $content = strip_tags($content, '<' . implode('><', $this->generatedContentTags) . '>');

        return $this->scrubAttributes($content);
    }

    /**
     * Remove every attribute not explicitly allowed, and drop images pointing
     * outside our own storage.
     */
    protected function scrubAttributes(string $html): string
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        // Wrapped so DOMDocument does not invent <html>/<body> around the
        // fragment, and marked UTF-8 so curly quotes and arrows survive.
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="lms-generated-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('lms-generated-root');
        if (!$root) {
            return '';
        }

        $xpath = new DOMXPath($doc);
        foreach (iterator_to_array($xpath->query('.//*', $root)) as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($node->nodeName);
            $allowed = array_merge(
                $this->generatedContentAttributes['*'],
                $this->generatedContentAttributes[$tag] ?? []
            );

            foreach (iterator_to_array($node->attributes ?? []) as $attr) {
                if (!in_array(strtolower($attr->nodeName), $allowed, true)) {
                    $node->removeAttribute($attr->nodeName);
                }
            }

            if ($tag === 'img' && !$this->isAllowedImageSrc($node->getAttribute('src'))) {
                $node->parentNode?->removeChild($node);
            }
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    protected function generatedContentHtml($content, $chapterName, $contentType)
    {
        $body = $this->formatGeneratedPdfBody($content);
        $title = e($chapterName . ' ' . $contentType);
        $css = $this->generatedContentCss();

        return <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>{$css}</style>
</head>
<body>
  <div class="doc-title-bar"><h1>{$title}</h1></div>
  <div class="content">{$body}</div>
</body>
</html>
HTML;
    }

    /**
     * Print stylesheet for generated documents.
     *
     * Deliberately conservative: Dompdf supports neither flexbox nor grid, so
     * multi-column layout uses tables, and colour is carried by borders and
     * background fills that print reliably.
     */
    protected function generatedContentCss(): string
    {
        return <<<'CSS'
@page { margin: 26px 30px; }
body { font-family: DejaVu Sans, sans-serif; color: #1e293b; font-size: 12.5px; line-height: 1.6; margin: 0; }
.doc-title-bar { background: #4f46e5; color: #ffffff; padding: 14px 18px; margin-bottom: 18px; border-radius: 8px; }
.doc-title-bar h1 { color: #ffffff; font-size: 19px; margin: 0; }
h1 { color: #1e3a8a; font-size: 22px; margin: 0 0 16px; }
h2 { color: #3730a3; font-size: 17px; margin: 20px 0 8px; padding-bottom: 5px; border-bottom: 2px solid #c7d2fe; }
h3 { color: #1e293b; font-size: 14px; margin: 14px 0 6px; }
h4 { color: #475569; font-size: 12.5px; margin: 10px 0 4px; }
p { margin: 0 0 9px; }
ul, ol { margin: 0 0 10px 20px; padding: 0; }
li { margin: 0 0 5px; }
hr { border: 0; border-top: 1px solid #e2e8f0; margin: 16px 0; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; }
th, td { border: 1px solid #cbd5e1; padding: 6px 8px; vertical-align: top; }
th { background: #eef2ff; font-weight: bold; color: #3730a3; }
blockquote { margin: 10px 0; padding: 8px 14px; border-left: 4px solid #c7d2fe; background: #f8fafc; color: #475569; }

/* Cover panel */
.cover { background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 10px; padding: 16px 18px; margin-bottom: 16px; }
.cover h2 { border: 0; margin: 0 0 6px; color: #3730a3; font-size: 19px; padding: 0; }
.eyebrow { color: #6366f1; font-size: 10px; letter-spacing: 1.5px; margin: 0 0 5px; font-weight: bold; }
.lede { color: #475569; margin: 0; }

/* Figures */
figure { margin: 12px 0; text-align: center; }
figure img { max-width: 100%; }
figcaption { color: #64748b; font-size: 10.5px; margin-top: 5px; font-style: italic; }
.fig-sm img { width: 170px; }
.fig-md img { width: 300px; }
.fig-lg img { width: 460px; }

/* Callout cards */
.callout { border-left: 4px solid #4f46e5; background: #f8fafc; padding: 9px 13px; margin: 11px 0; border-radius: 0 6px 6px 0; page-break-inside: avoid; }
.callout-label { display: block; font-size: 9.5px; letter-spacing: 1.2px; font-weight: bold; margin-bottom: 4px; color: #4f46e5; }
.callout p:last-child { margin-bottom: 0; }
.callout-key { border-left-color: #4f46e5; background: #eef2ff; }
.callout-key .callout-label { color: #4338ca; }
.callout-warn { border-left-color: #d97706; background: #fffbeb; }
.callout-warn .callout-label { color: #b45309; }
.callout-example { border-left-color: #059669; background: #ecfdf5; }
.callout-example .callout-label { color: #047857; }
.callout-try { border-left-color: #0284c7; background: #f0f9ff; }
.callout-try .callout-label { color: #0369a1; }

/* Stat tiles - table-based because Dompdf has no flexbox */
.tiles { width: 100%; border-collapse: separate; border-spacing: 5px; margin: 11px 0; }
.tiles td { border: 1px solid #c7d2fe; background: #eef2ff; border-radius: 8px; text-align: center; padding: 9px 5px; }
.tile-num { display: block; font-size: 16px; font-weight: bold; color: #3730a3; }
.tile-label { display: block; font-size: 9.5px; color: #475569; margin-top: 2px; }

/* Slide blocks */
.slide { border: 1px solid #e2e8f0; border-radius: 8px; padding: 11px 13px; margin: 11px 0; page-break-inside: avoid; }
.slide-head { background: #4f46e5; padding: 6px 10px; border-radius: 5px; margin-bottom: 9px; }
.slide-head h3 { color: #ffffff; margin: 0; font-size: 13px; }
.slide-num { color: #c7d2fe; font-size: 9.5px; letter-spacing: 1px; display: block; }
.meta { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 7px 10px; margin: 8px 0; font-size: 11px; }
.meta strong { color: #3730a3; }
.pill { background: #eef2ff; color: #3730a3; border: 1px solid #c7d2fe; border-radius: 10px; padding: 1px 6px; font-size: 10px; }
CSS;
    }
}
