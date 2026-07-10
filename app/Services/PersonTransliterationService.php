<?php

namespace App\Services;

class PersonTransliterationService
{
    /**
     * @var array<string, string>
     */
    private const CYRILLIC_TO_LATIN = [
        'а' => 'a',
        'б' => 'b',
        'в' => 'v',
        'г' => 'g',
        'д' => 'd',
        'е' => 'e',
        'ё' => 'e',
        'ж' => 'zh',
        'з' => 'z',
        'и' => 'i',
        'й' => 'y',
        'к' => 'k',
        'л' => 'l',
        'м' => 'm',
        'н' => 'n',
        'о' => 'o',
        'п' => 'p',
        'р' => 'r',
        'с' => 's',
        'т' => 't',
        'у' => 'u',
        'ф' => 'f',
        'х' => 'kh',
        'ц' => 'ts',
        'ч' => 'ch',
        'ш' => 'sh',
        'щ' => 'shch',
        'ъ' => '',
        'ы' => 'y',
        'ь' => '',
        'э' => 'e',
        'ю' => 'yu',
        'я' => 'ya',
    ];

    public function __construct(
        private readonly PersonNameNormalizer $normalizer,
    ) {}

    public function toLatin(string $text): string
    {
        $lower = mb_strtolower($text);
        $result = '';

        $length = mb_strlen($lower);

        for ($index = 0; $index < $length; $index++) {
            $character = mb_substr($lower, $index, 1);
            $result .= self::CYRILLIC_TO_LATIN[$character] ?? $character;
        }

        return trim(preg_replace('/\s+/u', ' ', $result) ?? $result);
    }

    public function toCyrillic(string $text): string
    {
        $lower = mb_strtolower($text);

        $multiCharReplacements = [
            'shch' => 'щ',
            'zh' => 'ж',
            'kh' => 'х',
            'ts' => 'ц',
            'ch' => 'ч',
            'sh' => 'ш',
            'yu' => 'ю',
            'ya' => 'я',
            'yo' => 'ё',
            'ye' => 'е',
        ];

        foreach ($multiCharReplacements as $latin => $cyrillic) {
            $lower = str_replace($latin, $cyrillic, $lower);
        }

        $singleCharReplacements = [
            'a' => 'а',
            'b' => 'б',
            'v' => 'в',
            'g' => 'г',
            'd' => 'д',
            'e' => 'е',
            'z' => 'з',
            'i' => 'и',
            'y' => 'й',
            'k' => 'к',
            'l' => 'л',
            'm' => 'м',
            'n' => 'н',
            'o' => 'о',
            'p' => 'п',
            'r' => 'р',
            's' => 'с',
            't' => 'т',
            'u' => 'у',
            'f' => 'ф',
            'h' => 'х',
            'c' => 'к',
            'w' => 'в',
            'x' => 'кс',
            'j' => 'дж',
        ];

        $result = '';

        $length = mb_strlen($lower);

        for ($index = 0; $index < $length; $index++) {
            $character = mb_substr($lower, $index, 1);
            $result .= $singleCharReplacements[$character] ?? $character;
        }

        return trim(preg_replace('/\s+/u', ' ', $result) ?? $result);
    }

    /**
     * @return list<string>
     */
    public function generateVariants(string $text, PersonNameNormalizer $normalizer): array
    {
        $variants = [];

        if ($normalizer->containsCyrillic($text)) {
            $latin = $this->toLatin($text);

            if ($latin !== '' && $normalizer->normalize($latin) !== $normalizer->normalize($text)) {
                $variants[] = $latin;
            }
        }

        if ($normalizer->containsLatin($text) && ! $normalizer->containsCyrillic($text)) {
            $cyrillic = $this->toCyrillic($text);

            if ($cyrillic !== '' && $normalizer->normalize($cyrillic) !== $normalizer->normalize($text)) {
                $variants[] = $cyrillic;
            }
        }

        return array_values(array_unique($variants));
    }
}
