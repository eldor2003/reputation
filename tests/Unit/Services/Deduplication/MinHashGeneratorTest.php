<?php

namespace Tests\Unit\Services\Deduplication;

use App\Services\Deduplication\MinHashGenerator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MinHashGeneratorTest extends TestCase
{
    #[Test]
    public function it_generates_signatures_with_configured_permutation_count(): void
    {
        config(['deduplication.minhash.permutations' => 128]);

        $generator = new MinHashGenerator;
        $signature = $generator->generateSignature('Foundation minhash signature test content.');

        $this->assertCount(128, $signature);
    }

    #[Test]
    public function it_generates_stable_signatures_for_identical_content(): void
    {
        config(['deduplication.minhash.permutations' => 32]);

        $generator = new MinHashGenerator;
        $content = 'Foundation minhash signature test content.';

        $this->assertSame(
            $generator->generateSignature($content),
            $generator->generateSignature($content),
        );
    }
}
