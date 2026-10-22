<?php

namespace Tests\Unit\StudyDeck;

use App\Services\StudyDeck\PdfOutline;
use Dompdf\Dompdf;
use PHPUnit\Framework\TestCase;
use Smalot\PdfParser\Parser;

/**
 * Bookmarks are added to a finished PDF as an incremental update. They must leave the document readable and its pages where
 * they were, and a file this does not understand must come back exactly as it went in. A real Dompdf PDF is used (no database).
 */
class PdfOutlineTest extends TestCase
{
    /** @return array{0:string,1:array<string,int>} the PDF and its named destinations (label => object number) */
    private function pdf(): array
    {
        $dir = sys_get_temp_dir();
        $dompdf = new Dompdf(['fontDir' => $dir, 'fontCache' => $dir, 'tempDir' => $dir]);
        $dompdf->loadHtml('<html><body>'
            . '<div id="one">First</div><div style="page-break-before:always" id="two">Second</div>'
            . '<div style="page-break-before:always" id="three">Third</div></body></html>');
        $dompdf->render();
        $destinations = (array) $dompdf->getCanvas()->get_cpdf()->destinations;

        return [$dompdf->output(), $destinations];
    }

    public function test_the_viewer_gets_a_bookmarks_tree_pointing_at_the_named_places(): void
    {
        [$pdf, $d] = $this->pdf();
        foreach (['one', 'two', 'three'] as $label) {
            $this->assertArrayHasKey($label, $d, "Dompdf should name $label");
        }

        $out = PdfOutline::add($pdf, [
            ['title' => 'Part one', 'dest' => $d['one'], 'children' => [['title' => 'Section two', 'dest' => $d['two']]]],
            ['title' => 'Part three', 'dest' => $d['three']],
        ]);

        $this->assertStringStartsWith($pdf, $out, 'an incremental update keeps every original byte');
        $added = substr($out, strlen($pdf));
        $this->assertStringContainsString('/PageMode /UseOutlines', $added);
        $this->assertStringContainsString('/Type /Outlines', $added);
        $this->assertSame(3, preg_match_all('~/Title <FEFF~', $added), 'one bookmark object each');
        $this->assertStringContainsString('/Count 3', $added);
        $this->assertStringContainsString('/Dest ' . $d['two'] . ' 0 R', $added);
        $this->assertMatchesRegularExpression('~/Prev \d+\s*>>\s*startxref\s+\d+\s+%%EOF\s*$~', $out);
    }

    public function test_the_document_still_opens_with_the_same_pages(): void
    {
        [$pdf, $d] = $this->pdf();
        $out = PdfOutline::add($pdf, [['title' => 'Start', 'dest' => $d['one']], ['title' => 'End', 'dest' => $d['three']]]);

        $before = (new Parser())->parseContent($pdf)->getPages();
        $after = (new Parser())->parseContent($out)->getPages();

        $this->assertCount(3, $before);
        $this->assertCount(3, $after);
        $this->assertSame(trim($before[1]->getText()), trim($after[1]->getText()));
    }

    public function test_every_offset_in_the_new_cross_reference_section_points_at_its_object(): void
    {
        [$pdf, $d] = $this->pdf();
        $out = PdfOutline::add($pdf, [['title' => 'Start', 'dest' => $d['one'], 'children' => [['title' => 'Middle', 'dest' => $d['two']]]]]);

        $this->assertSame(2, preg_match_all('~startxref\s+(\d+)\s+%%EOF~', $out, $m), 'the original ending, then the update\'s');
        $xref = (int) $m[1][1];
        $this->assertStringStartsWith('xref', substr($out, $xref, 4), 'the last startxref points at the new section');

        $section = substr($out, $xref);
        preg_match_all('~(\d+) (\d+)\n((?:\d{10} \d{5} n \n)+)~', $section, $subs, PREG_SET_ORDER);
        $this->assertNotEmpty($subs);
        foreach ($subs as $sub) {
            foreach (explode("\n", trim($sub[3])) as $k => $line) {
                $at = (int) substr($line, 0, 10);
                $this->assertSame(((int) $sub[1] + $k) . ' 0 obj', substr($out, $at, strlen(((int) $sub[1] + $k) . ' 0 obj')), 'object ' . ((int) $sub[1] + $k));
            }
        }
    }

    public function test_a_title_in_any_script_is_kept_whole(): void
    {
        [$pdf, $d] = $this->pdf();
        $out = PdfOutline::add($pdf, [['title' => "Cell  \u{2014} \u{0A95}\u{0AB6}\u{0ABE}", 'dest' => $d['one']]]);

        $this->assertStringContainsString(strtoupper(bin2hex("\xFE\xFF" . mb_convert_encoding("Cell \u{2014} \u{0A95}\u{0AB6}\u{0ABE}", 'UTF-16BE', 'UTF-8'))), $out);
    }

    public function test_a_file_that_is_not_what_it_expects_comes_back_unchanged(): void
    {
        [$pdf, $d] = $this->pdf();
        $tree = [['title' => 'Start', 'dest' => $d['one']]];

        $this->assertSame('not a pdf', PdfOutline::add('not a pdf', $tree));
        $this->assertSame($pdf, PdfOutline::add($pdf, []), 'nothing to add');
        $noOutlines = (string) preg_replace('~/Outlines\s+\d+\s+0\s+R~', '', $pdf);
        $this->assertSame($noOutlines, PdfOutline::add($noOutlines, $tree), 'no outline root to fill in');
    }
}
