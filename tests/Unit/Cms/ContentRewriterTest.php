<?php

namespace Tests\Unit\Cms;

use App\Cms\Import\ContentRewriter;
use App\Cms\Import\MediaUrl;
use PHPUnit\Framework\TestCase;

class ContentRewriterTest extends TestCase
{
    private function rewriter(): ContentRewriter
    {
        return new ContentRewriter(new MediaUrl('https://www.quizfabriek.be'));
    }

    public function testUploadUrlsAreRecognisedInEveryForm()
    {
        $urls = $this->rewriter()->uploadUrls(
            '<img src="https://www.quizfabriek.be/wp-content/uploads/a.jpg">'
            . '<img src="http://quizfabriek.be/wp-content/uploads/b.jpg?x=1">'
            . '<img src="//www.quizfabriek.be/wp-content/uploads/c.jpg">'
            . '<a href="/wp-content/uploads/d.pdf">d</a>'
            . '<img src="https://blog.quizfabriek.be/wp-content/uploads/h.jpg">'
            . '<img src="https://notquizfabriek.be/wp-content/uploads/i.jpg">'
            . '<img src="https://evil.example/wp-content/uploads/e.jpg">'
            . '<img src="https://www.quizfabriek.be/wp-content/themes/f.jpg">'
            . '<img src="https://www.quizfabriek.be/wp-content/uploads/../../etc/passwd">'
            . '<a href="https://www.quizfabriek.be/over-ons/">g</a>'
        );

        $this->assertSame([
            'https://www.quizfabriek.be/wp-content/uploads/a.jpg',
            'https://www.quizfabriek.be/wp-content/uploads/b.jpg',
            'https://www.quizfabriek.be/wp-content/uploads/c.jpg',
            'https://www.quizfabriek.be/wp-content/uploads/d.pdf',
            'https://www.quizfabriek.be/wp-content/uploads/h.jpg',
        ], $urls);
    }

    public function testRewriteReplacesUploadsAndDropsSrcset()
    {
        $html = $this->rewriter()->rewrite(
            '<p><img src="/wp-content/uploads/a-300x200.jpg" srcset="x 300w" sizes="100vw" alt="A">'
            . '<img src="/wp-content/uploads/missing.jpg"></p>'
            . '<p><a href="https://www.quizfabriek.be/over-ons/">Over ons</a></p>',
            function (string $url) {
                return $url === 'https://www.quizfabriek.be/wp-content/uploads/a-300x200.jpg'
                    ? 'https://storage.test/assets/abc'
                    : null;
            }
        );

        $this->assertSame(
            '<p><img src="https://storage.test/assets/abc" alt="A"><img src="/wp-content/uploads/missing.jpg"></p>'
            . '<p><a href="https://www.quizfabriek.be/over-ons/">Over ons</a></p>',
            $html
        );
    }

    public function testLazyLoadedSourcesAreRestored()
    {
        $html = $this->rewriter()->rewrite(
            '<iframe data-src="https://www.youtube.com/embed/x" src="data:image/svg+xml;base64,AA=="></iframe>'
            . '<img data-lazy-src="/wp-content/uploads/a.jpg" src="data:image/gif;base64,AA==">',
            function (string $url) {
                return $url === 'https://www.quizfabriek.be/wp-content/uploads/a.jpg' ? 'https://storage.test/assets/a' : null;
            }
        );

        $this->assertStringContainsString('src="https://www.youtube.com/embed/x"', $html);
        $this->assertStringContainsString('src="https://storage.test/assets/a"', $html);
        $this->assertStringNotContainsString('src="data:', $html);
    }

    public function testElementorLayoutIsUnwrappedForPages()
    {
        $source = '<div class="elementor elementor-1"><section class="elementor-section">'
            . '<div class="elementor-container"><div class="elementor-widget-container"><h2>Titel</h2></div>'
            . '<div class="elementor-widget-container"><p>Tekst <span class="elementor-x">hier</span></p></div>'
            . '</div></section></div><div class="wp-block-group"><p>Blijft</p></div>';

        $this->assertSame(
            '<h2>Titel</h2><p>Tekst hier</p><div class="wp-block-group"><p>Blijft</p></div>',
            $this->rewriter()->rewrite($source, function () {
                return null;
            }, true)
        );

        // Posts keep their structure.
        $this->assertStringContainsString('elementor-container', $this->rewriter()->rewrite($source, function () {
            return null;
        }));
    }

    public function testOriginalOfAResize()
    {
        $url = new MediaUrl('https://www.quizfabriek.be');
        $this->assertSame('https://x/wp-content/uploads/foto.jpg', $url->original('https://x/wp-content/uploads/foto-1024x768.jpg'));
        $this->assertNull($url->original('https://x/wp-content/uploads/foto.jpg'));
        $this->assertSame('wp-content/uploads/foto.jpg', $url->path('https://www.quizfabriek.be/wp-content/uploads/foto.jpg'));
    }
}
