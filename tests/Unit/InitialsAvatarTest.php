<?php

namespace Tests\Unit;

use App\Support\InitialsAvatar;
use PHPUnit\Framework\TestCase;

class InitialsAvatarTest extends TestCase
{
    public function test_initials_from_one_or_two_words_and_fallback(): void
    {
        $this->assertSame('BA', InitialsAvatar::initials('budi arief'));
        $this->assertSame('S', InitialsAvatar::initials('Siti'));
        $this->assertSame('NA', InitialsAvatar::initials('   '));
        $this->assertSame('NA', InitialsAvatar::initials('<>'));
    }

    public function test_data_uri_is_local_svg_and_cannot_inject_markup(): void
    {
        $uri = InitialsAvatar::dataUri('<script>alert(1)</script> x');

        $this->assertStringStartsWith('data:image/svg+xml;charset=utf-8,', $uri);
        $this->assertStringNotContainsString('http', explode(',', $uri, 2)[0] . 'x'); // tanpa host luar di skema
        $svg = rawurldecode(explode(',', $uri, 2)[1]);
        $this->assertStringNotContainsString('<script', $svg);
        $this->assertNotFalse(simplexml_load_string($svg), 'SVG harus XML valid');
    }
}
