<?php

namespace Tests\Unit\Services;

use App\Services\PersonNameNormalizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PersonNameNormalizerTest extends TestCase
{
    #[Test]
    public function it_normalizes_case_whitespace_and_punctuation(): void
    {
        $normalizer = new PersonNameNormalizer;

        $this->assertSame('john smith', $normalizer->normalize('  John   Smith! '));
        $this->assertSame('владимир путин', $normalizer->normalize('Владимир Путин'));
    }

    #[Test]
    public function it_detects_cyrillic_and_latin_scripts(): void
    {
        $normalizer = new PersonNameNormalizer;

        $this->assertTrue($normalizer->containsCyrillic('Путин'));
        $this->assertTrue($normalizer->containsLatin('Putin'));
        $this->assertFalse($normalizer->containsCyrillic('Putin'));
    }
}
