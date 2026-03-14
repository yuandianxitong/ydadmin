<?php
declare(strict_types=1);

namespace tests\Unit;

use PHPUnit\Framework\TestCase;
use core\utils\Validator;

class ValidatorTest extends TestCase
{
    // ==================== Mobile ====================

    public function testValidMobile(): void
    {
        $this->assertTrue(Validator::mobile('13800138000'));
        $this->assertTrue(Validator::mobile('15912345678'));
        $this->assertTrue(Validator::mobile('18600001111'));
    }

    public function testInvalidMobile(): void
    {
        $this->assertFalse(Validator::mobile('12345678901'));  // starts with 12
        $this->assertFalse(Validator::mobile('1380013800'));   // too short
        $this->assertFalse(Validator::mobile('138001380001')); // too long
        $this->assertFalse(Validator::mobile('abcdefghijk'));  // not digits
    }

    // ==================== Email ====================

    public function testValidEmail(): void
    {
        $this->assertTrue(Validator::email('user@example.com'));
        $this->assertTrue(Validator::email('test.name@domain.org'));
    }

    public function testInvalidEmail(): void
    {
        $this->assertFalse(Validator::email('not-an-email'));
        $this->assertFalse(Validator::email('@domain.com'));
        $this->assertFalse(Validator::email('user@'));
    }

    // ==================== URL ====================

    public function testValidUrl(): void
    {
        $this->assertTrue(Validator::url('https://www.example.com'));
        $this->assertTrue(Validator::url('http://example.com/path?query=1'));
        $this->assertTrue(Validator::url('ftp://files.example.com'));
    }

    public function testInvalidUrl(): void
    {
        $this->assertFalse(Validator::url('not a url'));
        $this->assertFalse(Validator::url('www.example.com')); // no scheme
    }

    // ==================== IP ====================

    public function testValidIp(): void
    {
        $this->assertTrue(Validator::ip('192.168.1.1'));
        $this->assertTrue(Validator::ip('10.0.0.1'));
        $this->assertTrue(Validator::ip('::1')); // IPv6 loopback
    }

    public function testInvalidIp(): void
    {
        $this->assertFalse(Validator::ip('999.999.999.999'));
        $this->assertFalse(Validator::ip('not.an.ip'));
    }

    // ==================== Chinese ====================

    public function testValidChinese(): void
    {
        $this->assertTrue(Validator::chinese('你好'));
        $this->assertTrue(Validator::chinese('中文测试'));
    }

    public function testInvalidChinese(): void
    {
        $this->assertFalse(Validator::chinese('Hello'));
        $this->assertFalse(Validator::chinese('你好World'));
        $this->assertFalse(Validator::chinese('123'));
    }

    // ==================== JSON ====================

    public function testValidJson(): void
    {
        $this->assertTrue(Validator::json('{"key":"value"}'));
        $this->assertTrue(Validator::json('[1,2,3]'));
        $this->assertTrue(Validator::json('"string"'));
        $this->assertTrue(Validator::json('null'));
    }

    public function testInvalidJson(): void
    {
        $this->assertFalse(Validator::json('{invalid}'));
        $this->assertFalse(Validator::json("{'key': 'value'}"));
    }

    // ==================== Date ====================

    public function testValidDate(): void
    {
        $this->assertTrue(Validator::date('2025-03-14'));
        $this->assertTrue(Validator::date('2025-12-31'));
    }

    public function testInvalidDate(): void
    {
        $this->assertFalse(Validator::date('2025-13-01'));
        $this->assertFalse(Validator::date('not-a-date'));
        $this->assertFalse(Validator::date('2025-02-30'));
    }

    public function testDateWithCustomFormat(): void
    {
        $this->assertTrue(Validator::date('14/03/2025', 'd/m/Y'));
        $this->assertTrue(Validator::date('2025/03/14', 'Y/m/d'));
    }

    // ==================== Password Strength ====================

    public function testPasswordStrengthLevel1(): void
    {
        $this->assertTrue(Validator::passwordStrength('abcdef', 1));       // 6+ chars with lowercase
        $this->assertFalse(Validator::passwordStrength('abc', 1));          // too short
    }

    public function testPasswordStrengthLevel2(): void
    {
        $this->assertTrue(Validator::passwordStrength('AbcDefgh', 2));     // 8+ with upper+lower
        $this->assertFalse(Validator::passwordStrength('abcdefgh', 2));    // no uppercase
    }

    public function testPasswordStrengthLevel3(): void
    {
        $this->assertTrue(Validator::passwordStrength('AbcDef12', 3));     // 8+ with upper+lower+digit
        $this->assertFalse(Validator::passwordStrength('AbcDefgh', 3));    // no digit
    }

    public function testPasswordStrengthLevel4(): void
    {
        $this->assertTrue(Validator::passwordStrength('AbcDef1!', 4));     // 8+ with upper+lower+digit+special
        $this->assertFalse(Validator::passwordStrength('AbcDef12', 4));    // no special char
    }

    public function testPasswordStrengthInvalidLevel(): void
    {
        $this->assertFalse(Validator::passwordStrength('password', 99));
    }

    // ==================== ID Card ====================

    public function testIdCardLength(): void
    {
        $this->assertFalse(Validator::idCard('12345'));           // too short
        $this->assertFalse(Validator::idCard('1234567890123456')); // 16 digits
    }

    public function testIdCardNonDigitPrefix(): void
    {
        $this->assertFalse(Validator::idCard('abcdefghijklmnopqr'));
    }

    // ==================== Bank Card ====================

    public function testBankCardTooShort(): void
    {
        $this->assertFalse(Validator::bankCard('123456789012345')); // 15 digits
    }

    public function testBankCardNonDigit(): void
    {
        $this->assertFalse(Validator::bankCard('abcdefghijklmnop'));
    }

    public function testBankCardTooLong(): void
    {
        $this->assertFalse(Validator::bankCard('12345678901234567890')); // 20 digits
    }
}
