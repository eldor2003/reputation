<?php

namespace App\DTO;

readonly class MentionFingerprintDTO
{
    public function __construct(
        public string $simhash,
        public string $contentFingerprint,
        public string $dedupHash,
    ) {}
}
