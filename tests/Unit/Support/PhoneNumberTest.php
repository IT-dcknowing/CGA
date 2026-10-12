<?php

namespace Tests\Unit\Support;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    #[DataProvider('phoneInputs')]
    public function test_normalizes_input_to_e164(string $input, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function phoneInputs(): array
    {
        return [
            'local number with spaces' => ['07 00 00 00 01', '+2250700000001'],
            'local number with dots' => ['07.00.00.00.01', '+2250700000001'],
            'international number with plus' => ['+225 07 00 00 00 01', '+2250700000001'],
            'international number with 00 prefix' => ['00225 0700000001', '+2250700000001'],
            'country code without plus' => ['2250700000001', '+2250700000001'],
            'foreign number with plus' => ['+33 6 12 34 56 78', '+33612345678'],
            'too short number keeps digits only' => ['07 00', '0700'],
        ];
    }

    public function test_pattern_accepts_normalized_numbers_only(): void
    {
        $this->assertMatchesRegularExpression(PhoneNumber::PATTERN, '+2250700000001');
        $this->assertDoesNotMatchRegularExpression(PhoneNumber::PATTERN, '0700');
    }
}
