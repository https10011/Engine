<?php

declare(strict_types=1);

namespace CasinoEngine\Tests\Unit;

use CasinoEngine\Core\Exception\ValidationException;
use CasinoEngine\Services\Auth\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneNumberTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function philippineForms(): array
    {
        return [
            'local 09 form'     => ['0917 123 4567', '+639171234567'],
            'local 9 form'      => ['917-123-4567', '+639171234567'],
            'country code form' => ['+63 917 123 4567', '+639171234567'],
            'international 00'  => ['0063 917 123 4567', '+639171234567'],
            'unspaced digits'   => ['639171234567', '+639171234567'],
            'other country'     => ['+1 (555) 123-4567', '+15551234567'],
        ];
    }

    #[DataProvider('philippineForms')]
    public function testNormalisesTheCommonFormsToTheSameValue(string $input, string $expected): void
    {
        self::assertSame($expected, PhoneNumber::normalize($input));
    }

    /** @return array<string, array{string}> */
    public static function invalidForms(): array
    {
        return [
            'empty'        => [''],
            'letters only' => ['not a phone'],
            'too short'    => ['12345'],
            'too long'     => ['1234567890123456'],
        ];
    }

    #[DataProvider('invalidForms')]
    public function testRejectsValuesThatCannotBeAPhoneNumber(string $input): void
    {
        $this->expectException(ValidationException::class);
        PhoneNumber::normalize($input);
    }
}
