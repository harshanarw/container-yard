<?php

namespace Tests\Unit\Support;

use App\Support\Iso6346;
use PHPUnit\Framework\TestCase;

/**
 * ISO 6346 container-number shape + check-digit helper.
 * CSQU3054383 is the canonical valid example; the digit 3 is the correct check.
 */
class Iso6346Test extends TestCase
{
    public function test_matches_format(): void
    {
        $this->assertTrue(Iso6346::matchesFormat('CSQU3054383'));
        $this->assertTrue(Iso6346::matchesFormat('csqu3054383'));   // normalised to upper
        $this->assertFalse(Iso6346::matchesFormat('CSQ3054383'));   // 3 letters
        $this->assertFalse(Iso6346::matchesFormat('CSQU305438'));   // 6 digits
        $this->assertFalse(Iso6346::matchesFormat('CSQU30543835')); // 8 digits
        $this->assertFalse(Iso6346::matchesFormat(''));
        $this->assertFalse(Iso6346::matchesFormat(null));
    }

    public function test_check_digit(): void
    {
        $this->assertTrue(Iso6346::checkDigitValid('CSQU3054383'));
        $this->assertTrue(Iso6346::checkDigitValid('MSCU1234566'));
        // Correct shape, wrong check digit.
        $this->assertFalse(Iso6346::checkDigitValid('CSQU3054384'));
        // Malformed can never have a valid check digit.
        $this->assertFalse(Iso6346::checkDigitValid('ABC123'));
    }

    public function test_is_valid_combines_both(): void
    {
        $this->assertTrue(Iso6346::isValid('CSQU3054383'));
        $this->assertFalse(Iso6346::isValid('CSQU3054384')); // bad check digit
        $this->assertFalse(Iso6346::isValid('CSQU305438'));  // bad shape
    }
}
