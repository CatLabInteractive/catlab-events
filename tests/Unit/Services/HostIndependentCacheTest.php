<?php

namespace Tests\Unit\Services;

use App\Services\HostIndependentCache;
use PHPUnit\Framework\TestCase;

class HostIndependentCacheTest extends TestCase
{
    private function replace($value)
    {
        $method = new \ReflectionMethod(HostIndependentCache::class, 'replace');
        return $method->invoke(null, $value, 'https://www.example.test', '@root');
    }

    public function testReplacesTheRootInNestedArrays()
    {
        $this->assertSame(
            [ 'url' => '@root/over-ons', 'children' => [ [ 'url' => '@root' ], [ 'url' => '@root?page=2' ] ] ],
            $this->replace([ 'url' => 'https://www.example.test/over-ons', 'children' => [ [ 'url' => 'https://www.example.test' ], [ 'url' => 'https://www.example.test?page=2' ] ] ])
        );
    }

    public function testKeepsOtherHostsAndOtherValues()
    {
        $this->assertSame('https://www.example.test.be/x', $this->replace('https://www.example.test.be/x'));
        $this->assertSame('https://elders.test/https://www.example.test', $this->replace('https://elders.test/https://www.example.test'));
        $this->assertSame(0.5, $this->replace(0.5));
        $this->assertNull($this->replace(null));
    }
}
