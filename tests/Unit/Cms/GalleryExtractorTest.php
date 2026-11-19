<?php

namespace Tests\Unit\Cms;

use App\Cms\GalleryExtractor;
use CatLab\CentralStorage\Client\Models\Asset;
use Tests\TestCase;

class GalleryExtractorTest extends TestCase
{
    /**
     * Assets known by URL: https://cdn.test/assets/{id}.
     */
    private function extractor(): GalleryExtractor
    {
        return new GalleryExtractor(function (string $src) {
            if (!preg_match('#^https://cdn\.test/assets/(\d+)$#', $src, $match)) {
                return null;
            }
            $asset = new Asset();
            $asset->id = (int) $match[1];
            return $asset;
        });
    }

    private function img(int $id, string $alt = ''): string
    {
        return '<img src="https://cdn.test/assets/' . $id . '" alt="' . $alt . '">';
    }

    private function text(string $html, array $extra = []): array
    {
        return [ 'id' => 'abc123', 'type' => 'rich_text', 'data' => array_merge([ 'html' => $html ], $extra) ];
    }

    public function testAWordPressGalleryBetweenTextIsSplitOut()
    {
        $html = '<p>Voor.</p><h2>Foto\'s</h2>'
            . '<figure class="wp-block-gallery"><ul class="blocks-gallery-grid">'
            . '<li class="blocks-gallery-item"><figure>' . $this->img(1, 'Zaal') . '<figcaption>De zaal</figcaption></figure></li>'
            . '<li class="blocks-gallery-item"><figure>' . $this->img(2, 'Winnaars') . '</figure></li>'
            . '</ul></figure><p>Na.</p>';

        $blocks = $this->extractor()->extract([ $this->text($html, [ 'colour' => '2' ]) ]);

        $this->assertSame([ 'rich_text', 'gallery', 'rich_text' ], array_column($blocks, 'type'));
        $this->assertSame('abc123', $blocks[0]['id'], 'the first part keeps the id');
        $this->assertNotSame('abc123', $blocks[1]['id']);
        $this->assertSame('<p>Voor.</p>', $blocks[0]['data']['html']);
        $this->assertSame('<p>Na.</p>', $blocks[2]['data']['html']);

        $gallery = $blocks[1]['data'];
        $this->assertSame('Foto\'s', $gallery['title'], 'the heading before it');
        $this->assertSame([ 1, 2 ], array_column($gallery['items'], 'image_id'));
        $this->assertSame([ 'De zaal', 'Winnaars' ], array_column($gallery['items'], 'caption'), 'figcaption, else alt');
        $this->assertSame('2', $gallery['colour']);
        $this->assertSame('2', $blocks[2]['data']['colour']);
    }

    public function testARunOfThreeLooseImagesIsAGalleryButTwoAreNot()
    {
        $three = '<p>Tekst.</p><p>' . $this->img(1) . '</p><figure>' . $this->img(2) . '</figure>' . $this->img(3);
        $blocks = $this->extractor()->extract([ $this->text($three) ]);
        $this->assertSame([ 'rich_text', 'gallery' ], array_column($blocks, 'type'));
        $this->assertSame([ 1, 2, 3 ], array_column($blocks[1]['data']['items'], 'image_id'));
        $this->assertArrayNotHasKey('title', $blocks[1]['data']);

        $two = '<p>Tekst.</p><p>' . $this->img(1) . '</p><p>' . $this->img(2) . '</p>';
        $this->assertSame([ $this->text($two) ], $this->extractor()->extract([ $this->text($two) ]));
    }

    public function testImagesInsideATextParagraphStayText()
    {
        $html = '<p>Kijk ' . $this->img(1) . ' en ' . $this->img(2) . ' en ' . $this->img(3) . '</p>';
        $blocks = [ $this->text($html) ];
        $this->assertSame($blocks, $this->extractor()->extract($blocks));
        $this->assertFalse($this->extractor()->wouldChange($blocks));
    }

    public function testAnImageThatIsNotAnAssetKeepsTheRunInTheText()
    {
        $html = '<p>' . $this->img(1) . '</p><p>' . $this->img(2) . '</p><p><img src="https://elders.test/x.jpg"></p>';
        $blocks = [ $this->text($html) ];
        $this->assertSame($blocks, $this->extractor()->extract($blocks));
    }

    public function testOnlyPhotosBecomesOneGalleryAndOtherBlocksAreLeftAlone()
    {
        $hero = [ 'id' => '000001', 'type' => 'hero', 'data' => [ 'title' => 'x' ] ];
        $html = '<figure class="wp-block-gallery">' . $this->img(1) . $this->img(2) . '</figure>';

        $blocks = $this->extractor()->extract([ $hero, $this->text($html) ]);

        $this->assertSame([ 'hero', 'gallery' ], array_column($blocks, 'type'));
        $this->assertSame($hero, $blocks[0]);
        $this->assertSame('abc123', $blocks[1]['id']);
        $this->assertTrue($this->extractor()->wouldChange([ $hero, $this->text($html) ]));
    }
}
