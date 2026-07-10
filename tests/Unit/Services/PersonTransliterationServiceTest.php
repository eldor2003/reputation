<?php

namespace Tests\Unit\Services;

use App\Services\PersonNameNormalizer;
use App\Services\PersonTransliterationService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PersonTransliterationServiceTest extends TestCase
{
    #[Test]
    public function it_transliterates_russian_names_to_latin(): void
    {
        $service = new PersonTransliterationService(new PersonNameNormalizer);

        $this->assertSame('vladimir putin', $service->toLatin('Владимир Путин'));
    }

    #[Test]
    public function it_transliterates_latin_names_to_cyrillic(): void
    {
        $service = new PersonTransliterationService(new PersonNameNormalizer);

        $this->assertSame('путин', $service->toCyrillic('Putin'));
    }

    #[Test]
    public function it_generates_transliteration_variants(): void
    {
        $normalizer = new PersonNameNormalizer;
        $service = new PersonTransliterationService($normalizer);

        $variants = $service->generateVariants('Путин', $normalizer);

        $this->assertContains('putin', $variants);
    }
}
