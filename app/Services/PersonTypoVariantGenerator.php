<?php

namespace App\Services;

class PersonTypoVariantGenerator
{
    /**
     * @var array<string, list<string>>
     */
    private const HOMOGLYPHS = [
        'a' => ['а', '@'],
        'а' => ['a', '@'],
        'e' => ['е', '3'],
        'е' => ['e', '3'],
        'o' => ['о', '0'],
        'о' => ['o', '0'],
        'p' => ['р'],
        'р' => ['p'],
        'c' => ['с'],
        'с' => ['c'],
        'i' => ['l', '1', 'і'],
        'l' => ['i', '1'],
        'y' => ['у'],
        'у' => ['y'],
        'x' => ['х'],
        'х' => ['x'],
        'k' => ['к'],
        'к' => ['k'],
        'm' => ['м'],
        'м' => ['m'],
        't' => ['т'],
        'т' => ['t'],
    ];

    public function __construct(
        private readonly PersonNameNormalizer $normalizer,
    ) {}

    /**
     * @return list<string>
     */
    public function generate(string $alias): array
    {
        if (! (bool) config('person.typo_variants.enabled', true)) {
            return [];
        }

        $maxVariants = (int) config('person.typo_variants.max_per_alias', 10);
        $variants = [];
        $seen = [$this->normalizer->normalize($alias)];

        foreach ($this->adjacentSwaps($alias) as $variant) {
            if ($this->collectVariant($variant, $seen, $variants, $maxVariants)) {
                break;
            }
        }

        foreach ($this->homoglyphReplacements($alias) as $variant) {
            if ($this->collectVariant($variant, $seen, $variants, $maxVariants)) {
                break;
            }
        }

        foreach ($this->singleCharacterRemovals($alias) as $variant) {
            if ($this->collectVariant($variant, $seen, $variants, $maxVariants)) {
                break;
            }
        }

        return $variants;
    }

    /**
     * @param  list<string>  $seen
     * @param  list<string>  $variants
     */
    private function collectVariant(string $variant, array &$seen, array &$variants, int $maxVariants): bool
    {
        $normalized = $this->normalizer->normalize($variant);

        if ($normalized === '' || in_array($normalized, $seen, true)) {
            return count($variants) >= $maxVariants;
        }

        $seen[] = $normalized;
        $variants[] = $variant;

        return count($variants) >= $maxVariants;
    }

    /**
     * @return list<string>
     */
    private function adjacentSwaps(string $alias): array
    {
        $variants = [];
        $characters = preg_split('//u', $alias, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        for ($index = 0; $index < count($characters) - 1; $index++) {
            $swapped = $characters;
            [$swapped[$index], $swapped[$index + 1]] = [$swapped[$index + 1], $swapped[$index]];
            $variants[] = implode('', $swapped);
        }

        return $variants;
    }

    /**
     * @return list<string>
     */
    private function homoglyphReplacements(string $alias): array
    {
        $variants = [];
        $characters = preg_split('//u', $alias, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($characters as $index => $character) {
            $lower = mb_strtolower($character);
            $replacements = self::HOMOGLYPHS[$lower] ?? [];

            foreach ($replacements as $replacement) {
                $copy = $characters;
                $copy[$index] = mb_strtoupper($character) === $character
                    ? mb_strtoupper($replacement)
                    : $replacement;
                $variants[] = implode('', $copy);
            }
        }

        return $variants;
    }

    /**
     * @return list<string>
     */
    private function singleCharacterRemovals(string $alias): array
    {
        if (mb_strlen($alias) <= 3) {
            return [];
        }

        $variants = [];
        $characters = preg_split('//u', $alias, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($characters as $index => $character) {
            if ($character === ' ') {
                continue;
            }

            $copy = $characters;
            array_splice($copy, $index, 1);
            $variants[] = implode('', $copy);
        }

        return $variants;
    }
}
