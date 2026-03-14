<?php
declare(strict_types=1);

namespace tests\Unit;

use PHPUnit\Framework\TestCase;
use core\exception\BusinessException;
use core\exception\ApiException;
use core\exception\AuthException;
use core\exception\ValidationException;
use core\exception\ErrorCode;

class ExceptionTest extends TestCase
{
    // ==================== BusinessException ====================

    public function testBusinessExceptionMessage(): void
    {
        $e = new BusinessException('Test error');
        $this->assertEquals('Test error', $e->getMessage());
    }

    public function testBusinessExceptionDefaultCode(): void
    {
        $e = new BusinessException('Error');
        $this->assertEquals(400, $e->getCode());
    }

    public function testBusinessExceptionCustomCode(): void
    {
        $e = new BusinessException('Error', 422);
        $this->assertEquals(422, $e->getCode());
    }

    public function testBusinessExceptionData(): void
    {
        $data = ['field' => 'name', 'reason' => 'required'];
        $e = new BusinessException('Error', 400, $data);
        $this->assertEquals($data, $e->getData());
    }

    public function testBusinessExceptionSetData(): void
    {
        $e = new BusinessException('Error');
        $result = $e->setData(['key' => 'value']);

        // setData returns $this for chaining
        $this->assertInstanceOf(BusinessException::class, $result);
        $this->assertEquals(['key' => 'value'], $e->getData());
    }

    public function testBusinessExceptionEmptyData(): void
    {
        $e = new BusinessException('Error');
        $this->assertEquals([], $e->getData());
    }

    // ==================== ApiException ====================

    public function testApiExceptionMessage(): void
    {
        $e = new ApiException('API Error', 500, 500);
        $this->assertEquals('API Error', $e->getMessage());
    }

    public function testApiExceptionHttpCode(): void
    {
        $e = new ApiException('Not Found', 404, 404);
        $this->assertEquals(404, $e->getHttpCode());
    }

    public function testApiExceptionDifferentCodeAndHttpCode(): void
    {
        $e = new ApiException('Error', 3001, 400);
        $this->assertEquals(3001, $e->getCode());
        $this->assertEquals(400, $e->getHttpCode());
    }

    public function testApiExceptionData(): void
    {
        $data = ['detail' => 'something went wrong'];
        $e = new ApiException('Error', 500, 500, $data);
        $this->assertEquals($data, $e->getData());
    }

    // ==================== AuthException ====================

    public function testAuthExceptionDefaultCode(): void
    {
        $e = new AuthException('Unauthorized');
        $this->assertEquals(ErrorCode::AUTH_TOKEN_INVALID, $e->getCode());
    }

    public function testAuthExceptionCustomCode(): void
    {
        $e = new AuthException('Token expired', ErrorCode::AUTH_TOKEN_EXPIRED);
        $this->assertEquals(ErrorCode::AUTH_TOKEN_EXPIRED, $e->getCode());
    }

    public function testAuthExceptionMessage(): void
    {
        $e = new AuthException('Custom auth error');
        $this->assertEquals('Custom auth error', $e->getMessage());
    }

    // ==================== ValidationException ====================

    public function testValidationExceptionWithArrayErrors(): void
    {
        $errors = ['name is required', 'email is invalid'];
        $e = new ValidationException($errors);
        $this->assertEquals($errors, $e->getErrors());
    }

    public function testValidationExceptionWithStringError(): void
    {
        $e = new ValidationException('name is required');
        $this->assertEquals(['name is required'], $e->getErrors());
    }

    public function testValidationExceptionGetFirstError(): void
    {
        $errors = ['first error', 'second error'];
        $e = new ValidationException($errors);
        $this->assertEquals('first error', $e->getFirstError());
    }

    public function testValidationExceptionDefaultCode(): void
    {
        $e = new ValidationException('error');
        $this->assertEquals(ErrorCode::VALIDATE_FAILED, $e->getCode());
    }

    // ==================== ErrorCode constants ====================

    public function testErrorCodeConstants(): void
    {
        $this->assertEquals(1001, ErrorCode::AUTH_TOKEN_EXPIRED);
        $this->assertEquals(1002, ErrorCode::AUTH_TOKEN_INVALID);
        $this->assertEquals(1003, ErrorCode::AUTH_TOKEN_MISSING);
        $this->assertEquals(2001, ErrorCode::VALIDATE_FAILED);
        $this->assertEquals(3001, ErrorCode::BIZ_RECORD_NOT_FOUND);
        $this->assertEquals(5000, ErrorCode::SYS_ERROR);
    }

    public function testErrorCodeRanges(): void
    {
        // Auth codes are in 1xxx range
        $this->assertGreaterThanOrEqual(1000, ErrorCode::AUTH_TOKEN_EXPIRED);
        $this->assertLessThan(2000, ErrorCode::AUTH_SMS_SEND_LIMIT);

        // Validation codes are in 2xxx range
        $this->assertGreaterThanOrEqual(2000, ErrorCode::VALIDATE_FAILED);
        $this->assertLessThan(3000, ErrorCode::VALIDATE_UNIQUE_CONFLICT);

        // Business codes are in 3xxx range
        $this->assertGreaterThanOrEqual(3000, ErrorCode::BIZ_RECORD_NOT_FOUND);
        $this->assertLessThan(4000, ErrorCode::BIZ_FEEDBACK_CLOSED);

        // Payment codes are in 4xxx range
        $this->assertGreaterThanOrEqual(4000, ErrorCode::PAY_CHANNEL_INVALID);
        $this->assertLessThan(5000, ErrorCode::PAY_NOTIFY_VERIFY_FAILED);

        // System codes are in 5xxx range
        $this->assertGreaterThanOrEqual(5000, ErrorCode::SYS_ERROR);
    }
}
