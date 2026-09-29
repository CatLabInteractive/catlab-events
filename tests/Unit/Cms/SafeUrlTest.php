<?php

namespace Tests\Unit\Cms;

use App\Rules\SafeUrl;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SafeUrlTest extends TestCase
{
    private function passes($value): bool
    {
        return Validator::make([ 'url' => $value ], [ 'url' => [ new SafeUrl() ] ])->passes();
    }

    public function testAcceptsSafeUrls()
    {
        foreach ([
            '/x',
            '/calendar',
            '/',
            '#top',
            'https://example.test/path?q=1',
            'http://example.test',
            'mailto:hallo@example.test',
            'tel:+3291234567',
        ] as $url) {
            $this->assertTrue($this->passes($url), $url);
        }
    }

    public function testRejectsUnsafeUrls()
    {
        foreach ([
            'javascript:alert(1)',
            ' javascript:alert(1)',
            'JaVaScRiPt:alert(1)',
            'data:text/html;base64,PHNjcmlwdD4=',
            'ftp://example.test',
            '//evil.test',
            '/\\evil.test',
            'vbscript:msgbox(1)',
            'https://',
            'relative/path',
            "https://example.test/\njavascript:x",
        ] as $url) {
            $this->assertFalse($this->passes($url), $url);
        }
    }

    public function testRejectsNonStrings()
    {
        $this->assertFalse($this->passes([ '/x' ]));
    }
}
