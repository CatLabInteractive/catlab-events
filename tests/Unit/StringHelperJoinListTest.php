<?php

namespace Tests\Unit;

use App\Tools\StringHelper;
use PHPUnit\Framework\TestCase;

class StringHelperJoinListTest extends TestCase
{
    public function testJoinsListsTheDutchWay()
    {
        $this->assertSame('', StringHelper::joinList([]));
        $this->assertSame('Gent', StringHelper::joinList([ 'Gent' ]));
        $this->assertSame('Balegem en Gent', StringHelper::joinList([ 'Balegem', 'Gent' ]));
        $this->assertSame('Balegem, Berchem en Gent', StringHelper::joinList([ 'Balegem', 'Berchem', 'Gent' ]));
    }

    public function testSkipsEmptyItems()
    {
        $this->assertSame('Balegem en Gent', StringHelper::joinList([ 'Balegem', '', ' ', 'Gent' ]));
    }
}
