<?php

namespace App\Services\StudyDeck;

/**
 * Adds a bookmarks tree (the viewer's "Outline" panel) to a PDF that Dompdf has made.
 *
 * Dompdf can name places in a document (an element with an `id` becomes a named destination) and link to them, but it
 * has no bookmarks. This adds them the way a PDF allows a finished file to be changed: an INCREMENTAL UPDATE, which leaves
 * every byte of the original where it is and appends the new objects, a new cross-reference section and a new trailer. If
 * anything about the file is not what this expects it returns the original bytes unchanged, so a document is never
 * made worse by trying to improve it.
 *
 * What it rewrites: the outline root (Dompdf always writes an empty one, which the catalog already points at) and the
 * catalog (to ask the viewer to open with the bookmarks panel showing). What it adds: one object per bookmark, pointing at
 * a destination Dompdf already wrote.
 */
final class PdfOutline
{
    /**
     * @param array<int,array{title:string,dest:int,children?:array<int,array{title:string,dest:int}>}> $tree
     *        dest: the object number of a destination Dompdf wrote (Cpdf::$destinations)
     */
    public static function add(string $pdf, array $tree): string
    {
        if ($tree === []) {
            return $pdf;
        }
        try {
            return self::build($pdf, $tree) ?? $pdf;
        } catch (\Throwable) {
            return $pdf;
        }
    }

    /** @param array<int,array<string,mixed>> $tree */
    private static function build(string $pdf, array $tree): ?string
    {
        if (!preg_match('/startxref\s+(\d+)\s+%%EOF\s*$/', $pdf, $start)) {
            return null;
        }
        if (!preg_match('/trailer\s*<<(.*?)>>\s*startxref/s', $pdf, $trailer)) {
            return null;
        }
        if (!preg_match('~/Size\s+(\d+)~', $trailer[1], $size) || !preg_match('~/Root\s+(\d+)\s+0\s+R~', $trailer[1], $root)) {
            return null;
        }
        $rootId = (int) $root[1];
        $next = (int) $size[1];

        // The catalog, which must be a plain dictionary pointing at the (empty) outline root.
        if (!preg_match('~(?:^|\n)' . $rootId . ' 0 obj\s*(<<.*?>>)\s*endobj~s', $pdf, $cat) || substr_count($cat[1], '<<') !== 1) {
            return null;
        }
        if (!preg_match('~/Outlines\s+(\d+)\s+0\s+R~', $cat[1], $out)) {
            return null;
        }
        $outlinesId = (int) $out[1];
        $catalog = preg_replace('~>>\s*$~', ' /PageMode /UseOutlines >>', $cat[1]);
        if (str_contains($cat[1], '/PageMode')) {
            $catalog = $cat[1];
        }

        // Number the bookmarks in reading order, parents before children.
        $items = [];
        $add = function (array $node, int $parent) use (&$items, &$add, &$next): int {
            $id = $next++;
            $items[$id] = ['title' => (string) $node['title'], 'dest' => (int) $node['dest'], 'parent' => $parent, 'children' => []];
            foreach ($node['children'] ?? [] as $child) {
                $items[$id]['children'][] = $add($child, $id);
            }

            return $id;
        };
        $top = [];
        foreach ($tree as $node) {
            $top[] = $add($node, $outlinesId);
        }
        $descendants = function (int $id) use (&$items, &$descendants): int {
            $n = 0;
            foreach ($items[$id]['children'] as $c) {
                $n += 1 + $descendants($c);
            }

            return $n;
        };

        $objects = [];
        $siblings = static function (array $ids, int $at): array {
            return [$ids[$at - 1] ?? null, $ids[$at + 1] ?? null];
        };
        $write = function (int $id, array $siblingIds, int $position) use (&$items, &$objects, $siblings, $descendants): void {
            $it = $items[$id];
            [$prev, $nextId] = $siblings($siblingIds, $position);
            $o = '<< /Title ' . self::text($it['title']) . ' /Parent ' . $it['parent'] . ' 0 R';
            if ($prev !== null) {
                $o .= ' /Prev ' . $prev . ' 0 R';
            }
            if ($nextId !== null) {
                $o .= ' /Next ' . $nextId . ' 0 R';
            }
            if ($it['children'] !== []) {
                $o .= ' /First ' . $it['children'][0] . ' 0 R /Last ' . $it['children'][count($it['children']) - 1] . ' 0 R /Count ' . $descendants($id);
            }
            $objects[$id] = $o . ' /Dest ' . $it['dest'] . " 0 R >>";
        };
        foreach ($top as $position => $id) {
            $write($id, $top, $position);
            foreach ($items[$id]['children'] as $childPosition => $childId) {
                $write($childId, $items[$id]['children'], $childPosition);
            }
        }
        $total = count($items);
        $objects[$outlinesId] = '<< /Type /Outlines /First ' . $top[0] . ' 0 R /Last ' . $top[count($top) - 1] . ' 0 R /Count ' . $total . ' >>';
        $objects[$rootId] = $catalog;
        ksort($objects);

        // Append: the objects, then one cross-reference section naming exactly them, then the trailer.
        $body = '';
        $offsets = [];
        $base = strlen($pdf) + 1;
        foreach ($objects as $id => $dict) {
            $offsets[$id] = $base + strlen($body);
            $body .= $id . " 0 obj\n" . $dict . "\nendobj\n";
        }
        $xrefAt = $base + strlen($body);
        $xref = "xref\n";
        $ids = array_keys($objects);
        for ($i = 0; $i < count($ids);) {
            $j = $i;
            while (isset($ids[$j + 1]) && $ids[$j + 1] === $ids[$j] + 1) {
                $j++;
            }
            $xref .= $ids[$i] . ' ' . ($j - $i + 1) . "\n";
            for ($k = $i; $k <= $j; $k++) {
                $xref .= sprintf("%010d 00000 n \n", $offsets[$ids[$k]]);
            }
            $i = $j + 1;
        }
        $newTrailer = preg_replace('~/Size\s+\d+~', '/Size ' . $next, $trailer[1]);

        return $pdf . "\n" . $body . $xref . "trailer\n<<" . rtrim($newTrailer) . "\n/Prev " . (int) $start[1] . "\n>>\nstartxref\n" . $xrefAt . "\n%%EOF\n";
    }

    /** A PDF text string: UTF-16 with a byte order mark, as hex, so any letter is safe. */
    private static function text(string $s): string
    {
        $s = trim((string) preg_replace('/\s+/u', ' ', $s));

        return '<' . strtoupper(bin2hex("\xFE\xFF" . mb_convert_encoding($s, 'UTF-16BE', 'UTF-8'))) . '>';
    }
}
