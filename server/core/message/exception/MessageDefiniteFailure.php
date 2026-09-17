<?php

declare(strict_types=1);

namespace core\message\exception;

/** 确定失败：不重试（见 MessageFailure）。 */
final class MessageDefiniteFailure extends MessageFailure
{
}
