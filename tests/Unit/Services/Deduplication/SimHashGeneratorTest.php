<?php

namespace Tests\Unit\Services\Deduplication;

use App\Services\Deduplication\SimHashGenerator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SimHashGeneratorTest extends TestCase
{
    #[Test]
    public function it_generates_stable_signatures_for_identical_content(): void
    {
        $generator = new SimHashGenerator;

        $first = $generator->generate('The company announced a major product update today.');
        $second = $generator->generate('The company announced a major product update today.');

        $this->assertSame($first, $second);
    }

    #[Test]
    public function it_detects_small_content_changes_with_low_hamming_distance(): void
    {
        $generator = new SimHashGenerator;

        $left = $generator->generate('The company announced a major product update today.');
        $right = $generator->generate('The company announced a major product update today!');

        $this->assertLessThanOrEqual(8, $generator->hammingDistance($left, $right));
    }
}
