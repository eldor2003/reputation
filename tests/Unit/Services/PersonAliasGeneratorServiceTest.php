<?php

namespace Tests\Unit\Services;

use App\DTO\CreatePersonData;
use App\Enums\PersonAliasType;
use App\Enums\PersonLanguage;
use App\Services\PersonAliasGeneratorService;
use App\Services\PersonNameNormalizer;
use App\Services\PersonTransliterationService;
use App\Services\PersonTypoVariantGenerator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PersonAliasGeneratorServiceTest extends TestCase
{
    #[Test]
    public function it_generates_full_name_alias_transliterations_and_typos(): void
    {
        config()->set('person.transliteration.enabled', true);
        config()->set('person.typo_variants.enabled', true);

        $service = new PersonAliasGeneratorService(
            new PersonNameNormalizer,
            new PersonTransliterationService(new PersonNameNormalizer),
            new PersonTypoVariantGenerator(new PersonNameNormalizer),
        );

        $aliases = $service->generateForCreate(new CreatePersonData(
            projectId: 1,
            fullName: 'Владимир Путин',
            primaryLanguage: PersonLanguage::Russian,
            customAliases: ['Путин'],
        ));

        $types = collect($aliases)->map(fn ($alias) => $alias->type)->unique()->values()->all();

        $this->assertContains(PersonAliasType::FullName, $types);
        $this->assertContains(PersonAliasType::Alias, $types);
        $this->assertContains(PersonAliasType::Transliteration, $types);
        $this->assertContains(PersonAliasType::TypoVariant, $types);
    }
}
