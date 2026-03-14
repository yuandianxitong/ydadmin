<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\exception;

/**
 * 系统错误码分类
 *
 * 1xxx - 认证错误
 * 2xxx - 参数验证错误
 * 3xxx - 业务逻辑错误
 * 4xxx - 支付相关错误
 * 5xxx - 系统错误
 */
class ErrorCode
{
    // ---- 1xxx 认证错误 ----
    public const AUTH_TOKEN_EXPIRED     = 1001;
    public const AUTH_TOKEN_INVALID     = 1002;
    public const AUTH_TOKEN_MISSING     = 1003;
    public const AUTH_LOGIN_FAILED      = 1004;
    public const AUTH_ACCOUNT_DISABLED  = 1005;
    public const AUTH_ACCOUNT_LOCKED    = 1006;
    public const AUTH_PERMISSION_DENIED = 1007;
    public const AUTH_CAPTCHA_INVALID   = 1008;
    public const AUTH_SMS_CODE_INVALID  = 1009;
    public const AUTH_SMS_SEND_LIMIT    = 1010;

    // ---- 2xxx 参数验证错误 ----
    public const VALIDATE_FAILED       = 2001;
    public const VALIDATE_PARAM_MISSING = 2002;
    public const VALIDATE_FORMAT_ERROR = 2003;
    public const VALIDATE_UNIQUE_CONFLICT = 2004;

    // ---- 3xxx 业务逻辑错误 ----
    public const BIZ_RECORD_NOT_FOUND  = 3001;
    public const BIZ_RECORD_EXISTS     = 3002;
    public const BIZ_STATUS_INVALID    = 3003;
    public const BIZ_OPERATION_FAILED  = 3004;
    public const BIZ_UPLOAD_FAILED     = 3005;
    public const BIZ_TEMPLATE_NOT_FOUND = 3006;
    public const BIZ_FEEDBACK_CLOSED   = 3007;

    // ---- 4xxx 支付相关错误 ----
    public const PAY_CHANNEL_INVALID   = 4001;
    public const PAY_AMOUNT_INVALID    = 4002;
    public const PAY_ORDER_NOT_FOUND   = 4003;
    public const PAY_ORDER_PAID        = 4004;
    public const PAY_REFUND_EXCEED     = 4005;
    public const PAY_REFUND_FAILED     = 4006;
    public const PAY_NOTIFY_VERIFY_FAILED = 4007;

    // ---- 5xxx 系统错误 ----
    public const SYS_ERROR             = 5000;
    public const SYS_DB_ERROR          = 5001;
    public const SYS_CACHE_ERROR       = 5002;
    public const SYS_THIRD_PARTY_ERROR = 5003;
    public const SYS_RATE_LIMIT        = 5004;
}
