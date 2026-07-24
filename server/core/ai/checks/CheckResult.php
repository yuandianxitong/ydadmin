<?php
declare(strict_types=1);

namespace core\ai\checks;

class CheckResult
{
    public function __construct(
        public string $check,
        public string $severity,
        public string $message,
        public ?string $ref = null
    ) {
    }

    public function toArray(): array
    {
        return [
            'check'    => $this->check,
            'severity' => $this->severity,
            'message'  => $this->message,
            'ref'      => $this->ref,
        ];
    }
}
