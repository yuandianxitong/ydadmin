<?php

declare(strict_types=1);

namespace core\message\exception;

/** 暂时失败：交给队列重试（见 MessageFailure）。 */
final class MessageTransientFailure extends MessageFailure
{
}
