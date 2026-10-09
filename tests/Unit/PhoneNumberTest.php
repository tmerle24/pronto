<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    /**
     * @return array<string, array{string, ?string}>
     */
    public static function numbers(): array
    {
        return [
            'german mobile' => ['0171 1234567', '+491711234567'],
            'with slash' => ['0171/123 45 67', '+491711234567'],
            'international' => ['+43 664 1234567', '+436641234567'],
            'double zero' => ['0049 171 1234567', '+491711234567'],
            'too short' => ['0171', null],
            'letters' => ['0171 TIAMO', null],
        ];
    }

    #[DataProvider('numbers')]
    public function test_normalize(string $input, ?string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($input));
    }
}
