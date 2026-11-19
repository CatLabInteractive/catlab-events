<?php

namespace Tests\Unit\Cms;

use App\Cms\HtmlSanitizer;
use Tests\TestCase;

class HtmlSanitizerTest extends TestCase
{
    /**
     * @var HtmlSanitizer
     */
    private $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'centralStorage.front' => 'https://assets.example.test',
            'cms.allowed_image_hosts' => [ 'images.partner.test' ],
        ]);

        $this->app->forgetInstance(HtmlSanitizer::class);
        $this->sanitizer = $this->app->make(HtmlSanitizer::class);
    }

    private function clean(string $html): string
    {
        return $this->sanitizer->sanitize($html);
    }

    public function testIsAContainerSingleton()
    {
        $this->assertSame($this->sanitizer, $this->app->make(HtmlSanitizer::class));
    }

    public function testScriptsAndEventHandlersAreRemoved()
    {
        $out = $this->clean('<p onclick="alert(1)">Hi<script>alert(2)</script></p><img src="https://assets.example.test/a.jpg" onerror="alert(3)">');

        $this->assertStringNotContainsString('script', $out);
        $this->assertStringNotContainsString('alert', $out);
        $this->assertStringNotContainsString('onclick', $out);
        $this->assertStringNotContainsString('onerror', $out);
        $this->assertStringContainsString('<p>Hi</p>', $out);
    }

    public function testJavascriptLinksLoseTheirHref()
    {
        $out = $this->clean('<a href="javascript:alert(1)">x</a><a href="data:text/html;base64,PHNjcmlwdD4=">y</a>');

        $this->assertStringNotContainsString('javascript', $out);
        $this->assertStringNotContainsString('data:', $out);
    }

    public function testStyleAndIdAreDropped()
    {
        $out = $this->clean('<p style="background:url(x)" id="foo">Text</p><style>p{}</style>');

        $this->assertSame('<p>Text</p>', $out);
    }

    public function testAllowedLinksAreKept()
    {
        $out = $this->clean('<a href="/calendar">a</a> <a href="mailto:hallo@example.test">b</a> <a href="tel:+3291234567">c</a> <a href="https://example.test/x" title="t">d</a>');

        $this->assertStringContainsString('href="/calendar"', $out);
        $this->assertStringContainsString('href="mailto:hallo@example.test"', $out);
        $this->assertStringContainsString('href="tel:+3291234567"', $out);
        $this->assertStringContainsString('href="https://example.test/x"', $out);
        $this->assertStringContainsString('title="t"', $out);
    }

    public function testTargetBlankGetsNoopener()
    {
        $out = $this->clean('<a href="https://example.test" target="_blank">x</a><a href="/y" target="_parent">y</a>');

        $this->assertStringContainsString('target="_blank"', $out);
        $this->assertStringContainsString('rel="noopener noreferrer"', $out);
        $this->assertStringNotContainsString('_parent', $out);
    }

    public function testYoutubeIframesAreKeptOtherIframesDropped()
    {
        $out = $this->clean(
            '<iframe src="https://www.youtube-nocookie.com/embed/abc" width="560" height="315" allowfullscreen></iframe>' .
            '<iframe src="https://www.youtube.com/embed/def"></iframe>' .
            '<iframe src="https://evil.test/embed"></iframe>' .
            '<iframe src="https://assets.example.test/a.html"></iframe>'
        );

        $this->assertStringContainsString('src="https://www.youtube-nocookie.com/embed/abc"', $out);
        $this->assertStringContainsString('src="https://www.youtube.com/embed/def"', $out);
        $this->assertStringContainsString('width="560"', $out);
        $this->assertStringNotContainsString('evil.test', $out);
        $this->assertStringNotContainsString('a.html', $out);
        $this->assertSame(2, substr_count($out, '<iframe'));
    }

    public function testImagesOnlySurviveFromOurStorageOrApprovedHosts()
    {
        $out = $this->clean(
            '<img src="https://assets.example.test/assets/abc?width=800" alt="ours" loading="lazy">' .
            '<img src="https://images.partner.test/logo.png" alt="approved">' .
            '<img src="https://tracker.evil.test/pixel.gif" alt="foreign">' .
            '<img src="http://assets.example.test/insecure.jpg" alt="http">' .
            '<img src="https://www.youtube.com/thumb.jpg" alt="videohost">' .
            '<img src="/relative.jpg" alt="relative">'
        );

        $this->assertStringContainsString('alt="ours"', $out);
        $this->assertStringContainsString('loading="lazy"', $out);
        $this->assertStringContainsString('alt="approved"', $out);
        $this->assertStringNotContainsString('foreign', $out);
        $this->assertStringNotContainsString('alt="http"', $out);
        $this->assertStringNotContainsString('videohost', $out);
        $this->assertStringNotContainsString('relative', $out);
        $this->assertSame(2, substr_count($out, '<img'));
    }

    public function testOnlyAllowedClassPrefixesSurvive()
    {
        $out = $this->clean('<figure class="wp-block-gallery alignwide elementor-widget has-text-align-center"><p class="cms-lead evil">x</p><div class="elementor">y</div></figure>');

        $this->assertStringContainsString('class="wp-block-gallery alignwide has-text-align-center"', $out);
        $this->assertStringContainsString('<p class="cms-lead">x</p>', $out);
        $this->assertStringContainsString('<div>y</div>', $out);
        $this->assertStringNotContainsString('elementor', $out);
        $this->assertStringNotContainsString('evil', $out);
    }

    public function testH1IsDemotedToH2()
    {
        $this->assertSame('<h2>Title</h2><h3>Sub</h3>', $this->clean('<h1>Title</h1><h3>Sub</h3>'));
    }

    public function testTablesAndListsAreKept()
    {
        $html = '<table><thead><tr><th colspan="2">A</th></tr></thead><tbody><tr><td rowspan="1">1</td><td>2</td></tr></tbody></table><ul><li>a</li></ul><ol><li>b</li></ol><blockquote>q</blockquote>';

        $this->assertSame($html, $this->clean($html));
    }

    public function testUnicodeSurvives()
    {
        $this->assertSame('<p>Café – “quiz” €5</p>', $this->clean('<p>Café – “quiz” €5</p>'));
    }

    public function testEmptyInputGivesEmptyOutput()
    {
        $this->assertSame('', $this->clean(''));
        $this->assertSame('', $this->clean('<script>alert(1)</script>'));
    }

    public function testImageUrlCheck()
    {
        $this->assertTrue($this->sanitizer->isAllowedImageUrl('https://assets.example.test/assets/abc'));
        $this->assertTrue($this->sanitizer->isAllowedImageUrl('https://IMAGES.partner.test/x.png'));
        $this->assertFalse($this->sanitizer->isAllowedImageUrl('http://images.partner.test/x.png'));
        $this->assertFalse($this->sanitizer->isAllowedImageUrl('https://images.partner.test.evil.test/x.png'));
        $this->assertFalse($this->sanitizer->isAllowedImageUrl('javascript:alert(1)'));
    }
}
