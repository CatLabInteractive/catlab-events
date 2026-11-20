<?php

namespace Tests\Unit;

use App\Models\Organisation;
use PHPUnit\Framework\TestCase;

class OrganisationPhoneTest extends TestCase
{
    /**
     * @dataProvider phoneNumbers
     */
    public function testPhoneHref(?string $phone, ?string $expected)
    {
        $organisation = new Organisation();
        $organisation->phone = $phone;

        $this->assertSame($expected, $organisation->getPhoneHref());
    }

    public static function phoneNumbers(): array
    {
        return [
            'empty' => [ null, null ],
            'blank' => [ '   ', null ],
            'international' => [ '+32 9 123 45 67', 'tel:+3291234567' ],
            'trunk prefix' => [ '+32 (0)9 123 45 67', 'tel:+3291234567' ],
            'double zero' => [ '0032 9 123 45 67', 'tel:+3291234567' ],
            'national with separators' => [ '09/123.45.67', 'tel:091234567' ],
            'mobile' => [ '0470 12 34 56', 'tel:0470123456' ],
            'no digits' => [ 'bel ons', null ],
        ];
    }
}
