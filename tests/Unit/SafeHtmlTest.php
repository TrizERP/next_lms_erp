<?php

namespace Tests\Unit;

use App\Support\SafeHtml;
use PHPUnit\Framework\TestCase;

class SafeHtmlTest extends TestCase
{
    public function test_formatting_survives(): void
    {
        $out = SafeHtml::clean('<p class="x"><b>Hi</b> <a href="https://a.example/p" target="_blank">link</a><br>ok</p>');
        $this->assertStringContainsString('<b>Hi</b>', $out);
        $this->assertStringContainsString('href="https://a.example/p"', $out);
        $this->assertStringContainsString('rel="noopener noreferrer"', $out);
    }

    /** @dataProvider attacks */
    public function test_attacks_are_removed(string $in): void
    {
        $out = strtolower(SafeHtml::clean($in));
        foreach (['<script', 'onerror', 'onclick', 'onload', 'javascript:', '<iframe', '<svg', 'alert(1)<', 'expression('] as $bad) {
            $this->assertStringNotContainsString($bad, $out, $in);
        }
    }

    public static function attacks(): array
    {
        return array_map(fn ($s) => [$s], [
            '<script>alert(1)</script>',
            '<img src=x onerror=alert(1)>',
            '<a href="javascript:alert(1)">x</a>',
            '<a href="  JaVa&#x73;cript:alert(1)">x</a>',
            '<div onclick="alert(1)">x</div>',
            '<iframe src="https://evil.example"></iframe>',
            '<svg onload=alert(1)></svg>',
            '<p style="width:expression(alert(1))">x</p>',
            '<body onload=alert(1)>x',
            '<b>ok</b><!--<script>alert(1)</script>-->',
        ]);
    }

    public function test_relative_images_and_links_are_kept(): void
    {
        $out = SafeHtml::clean('<img src="storage/logo.png"><a href="/login">x</a><a href="#top">y</a>');
        $this->assertStringContainsString('src="storage/logo.png"', $out);
        $this->assertStringContainsString('href="/login"', $out);
        $this->assertStringContainsString('href="#top"', $out);
    }

    public function test_plain_text_is_untouched(): void
    {
        $this->assertSame('Welcome to school', SafeHtml::clean('Welcome to school'));
    }
}
