<?php
declare(strict_types=1);

namespace app\api\middleware;

use core\http\Middleware;
use Closure;
use think\Request;
use think\Response;

class SmsRateLimitMiddleware extends Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $mobile = (string) $request->param('mobile', '');
        if (empty($mobile)) {
            return $next($request);
        }

        // 同一手机号每分钟 1 次
        $minuteKey = 'sms_rate:minute:' . $mobile;
        if (!$this->checkRateLimit($minuteKey, 1, 60)) {
            return $this->errorResponse(lang('messages.sms_send_too_frequent'), 429);
        }

        // 同一手机号每天 10 次
        $dayKey = 'sms_rate:day:' . $mobile;
        if (!$this->checkRateLimit($dayKey, 10, 86400)) {
            return $this->errorResponse(lang('messages.sms_daily_limit'), 429);
        }

        return $next($request);
    }
}
