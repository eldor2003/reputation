<?php

namespace Tests\Unit\Enums;

use App\Enums\PersonAliasType;
use App\Enums\PersonLanguage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PersonEnumsTest extends TestCase
{
    #[Test]
    public function it_exposes_person_language_labels(): void
    {
        $this->assertSame('Russian', PersonLanguage::Russian->label());
        $this->assertSame('English', PersonLanguage::English->label());
        $this->assertSame('Custom', PersonLanguage::Custom->label());
    }

    #[Test]
    public function it_exposes_person_alias_type_labels(): void
    {
        $this->assertSame('Full Name', PersonAliasType::FullName->label());
        $this->assertSame('Transliteration', PersonAliasType::Transliteration->label());
        $this->assertSame('Typo Variant', PersonAliasType::TypoVariant->label());
    }
}
