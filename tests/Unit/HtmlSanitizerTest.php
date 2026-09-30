<?php

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    /** @dataProvider xssVectors */
    public function test_it_neutralises_xss_vectors(string $input, string $mustNotContain): void
    {
        $this->assertStringNotContainsStringIgnoringCase($mustNotContain, HtmlSanitizer::clean($input));
    }

    public static function xssVectors(): array
    {
        return [
            'script tag' => ['<p>a</p><script>alert(1)</script>', 'script'],
            'onerror' => ['<img src="x" onerror="alert(1)">', 'onerror'],
            'onclick' => ['<p onclick="x()">t</p>', 'onclick'],
            'javascript href' => ['<a href="javascript:alert(1)">x</a>', 'javascript'],
            'obfuscated javascript href' => ['<a href="  jaVa&#x09;script:alert(1)">x</a>', 'script:'],
            'data html href' => ['<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>', 'data:'],
            'svg onload' => ['<svg onload="alert(1)"><circle/></svg>', 'onload'],
            'iframe javascript' => ['<iframe src="javascript:alert(1)"></iframe>', 'iframe'],
            'iframe evil host' => ['<iframe src="https://evil.example/x"></iframe>', 'iframe'],
            'iframe google non-maps' => ['<iframe src="https://www.google.com/search?q=x"></iframe>', 'iframe'],
            'form' => ['<form action="https://evil.example"><input name="pw"></form>', 'form'],
            'style expression' => ['<p style="width:expression(alert(1))">x</p>', 'expression'],
            'style url' => ['<p style="background:url(javascript:alert(1))">x</p>', 'url('],
            'style overlay' => ['<div style="position:fixed;top:0;left:0;width:100%">x</div>', 'position'],
            'meta refresh' => ['<meta http-equiv="refresh" content="0;url=https://evil.example">', 'meta'],
            'object' => ['<object data="x.swf"></object>', 'object'],
            'comment payload' => ['<!--<script>alert(1)</script>--><p>ok</p>', 'script'],
            'img data svg' => ['<img src="data:image/svg+xml;base64,PHN2Zz4=">', 'svg'],
        ];
    }

    public function test_it_keeps_normal_content(): void
    {
        $html = '<h2 class="t">Judul</h2><p>Halo <strong>dunia</strong> <a href="https://contoh.com/x" target="_blank">link</a></p>'
            . '<ul><li>satu</li></ul><table><tr><td colspan="2">sel</td></tr></table>'
            . '<img src="/img/a.png" alt="a"><p style="text-align:center;color:#333">tengah</p>'
            . '<iframe src="https://www.youtube.com/embed/abc" width="560" height="315" allowfullscreen></iframe>';

        $out = HtmlSanitizer::clean($html);

        foreach (['<h2 class="t">', '<strong>dunia</strong>', 'href="https://contoh.com/x"', '<li>satu</li>', 'colspan="2"', 'src="/img/a.png"', 'text-align:center', 'youtube.com/embed/abc'] as $needle) {
            $this->assertStringContainsString($needle, $out, "hilang: {$needle}");
        }

        $this->assertStringContainsString('rel="noopener noreferrer"', $out);
    }

    public function test_it_preserves_utf8_and_handles_empty_input(): void
    {
        $this->assertSame('', HtmlSanitizer::clean(null));
        $this->assertSame('', HtmlSanitizer::clean('   '));
        $this->assertStringContainsString('Promo Lebaran — diskon 50% ✓', HtmlSanitizer::clean('<p>Promo Lebaran — diskon 50% ✓</p>'));
    }
}
