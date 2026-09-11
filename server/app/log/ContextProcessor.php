<?php

declare(strict_types=1);

namespace app\log;

use core\context\RequestContext;
use Monolog\Processor\ProcessorInterface;

/** 把 trace_id / acting_user 注入每条日志的 extra；空值省略（不写 0 或空串）。Monolog 2 签名。 */
final class ContextProcessor implements ProcessorInterface
{
    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    public function __invoke(array $record): array
    {
        if (RequestContext::traceId() !== '') {
            $record['extra']['trace_id'] = RequestContext::traceId();
        }
        if (RequestContext::actingUser() > 0) {
            $record['extra']['acting_user'] = RequestContext::actingUser();
        }

        return $record;
    }
}
