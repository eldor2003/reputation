<?php

namespace App\Enums;

enum SerpEngine: string
{
    case Google = 'google';
    case Yandex = 'yandex';
    case Bing = 'bing';
    case Baidu = 'baidu';

    public function serpApiEngine(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::Yandex => 'Yandex',
            self::Bing => 'Bing',
            self::Baidu => 'Baidu',
        };
    }
}
