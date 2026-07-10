<?php

namespace Tests\Unit\Services;

use App\Services\PersonNameNormalizer;
use App\Services\PersonTypoVariantGenerator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PersonTypoVariantGeneratorTest extends TestCase
{
    #[Test]
    public function it_generates_typo_variants_for_names(): void
    {
        config()->set('person.typo_variants.enabled', true);
        config()->set('person.typo_variants.max_per_alias', 10);

        $generator = new PersonTypoVariantGenerator(new PersonNameNormalizer);
        $variants = $generator->generate('Smith');

        $this->assertNotEmpty($variants);
        $this->assertTrue(collect($variants)->contains(
            fn (string $variant): bool => $variant !== 'Smith',
        ));
    }

    #[Test]
    public function it_respects_disabled_configuration(): void
    {
        config()->set('person.typo_variants.enabled', false);

        $generator = new PersonTypoVariantGenerator(new PersonNameNormalizer);

        $this->assertSame([], $generator->generate('Smith'));
    }
}
