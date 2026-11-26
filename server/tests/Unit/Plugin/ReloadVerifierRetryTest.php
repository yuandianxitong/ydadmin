<?php

declare(strict_types=1);

namespace tests\Unit\Plugin;

use core\plugin\contracts\PluginHostProfile;
use core\plugin\saga\ReloadVerifier;
use tests\TestCase;

final class ReloadVerifierRetryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!method_exists(ReloadVerifier::class, 'stopTimeoutSeconds')) {
            $this->markTestSkipped('插件内核尚未包含稳定旧指纹后的 SIGUSR1 补发');
        }
    }

    public function test_stable_old_fingerprint_is_reloaded_once_and_then_accepted(): void
    {
        $verifier = new class ($this->profile()) extends ReloadVerifier {
            public int $resent = 0;

            public string $fingerprint = 'old';

            protected function resendReload(): void
            {
                $this->resent++;
                $this->fingerprint = 'expected';
            }

            protected function stopTimeoutSeconds(): float
            {
                return 0.0;
            }

            protected function liveWorkers(): array
            {
                return [[
                    'pid' => 11,
                    'fingerprint' => $this->fingerprint,
                    'booted_at' => '2026-01-01 00:00:00',
                ]];
            }

            protected function resolveMasterChildPids(): ?array
            {
                return [11];
            }
        };

        $this->assertSame('expected', $verifier->waitUntilEffective('expected', 5));
        $this->assertSame(1, $verifier->resent);
    }

    public function test_matching_fingerprint_does_not_resend(): void
    {
        $verifier = new class ($this->profile()) extends ReloadVerifier {
            public int $resent = 0;

            protected function resendReload(): void
            {
                $this->resent++;
            }

            protected function stopTimeoutSeconds(): float
            {
                return 0.0;
            }

            protected function liveWorkers(): array
            {
                return [[
                    'pid' => 11,
                    'fingerprint' => 'expected',
                    'booted_at' => '2026-01-01 00:00:00',
                ]];
            }

            protected function resolveMasterChildPids(): ?array
            {
                return [11];
            }
        };

        $this->assertSame('expected', $verifier->waitUntilEffective('expected', 5));
        $this->assertSame(0, $verifier->resent);
    }

    public function test_changing_worker_set_does_not_resend(): void
    {
        $verifier = new class ($this->profile()) extends ReloadVerifier {
            public int $resent = 0;

            public int $polls = 0;

            protected function resendReload(): void
            {
                $this->resent++;
            }

            protected function stopTimeoutSeconds(): float
            {
                return 10.0;
            }

            protected function liveWorkers(): array
            {
                $this->polls++;
                $fingerprint = $this->polls < 4 ? 'old' : 'expected';

                return [[
                    'pid' => $this->polls,
                    'fingerprint' => $fingerprint,
                    'booted_at' => '2026-01-01 00:00:00',
                ]];
            }

            protected function resolveMasterChildPids(): ?array
            {
                return [1, 2, 3, 4];
            }
        };

        $this->assertSame('expected', $verifier->waitUntilEffective('expected', 5));
        $this->assertSame(0, $verifier->resent);
    }

    public function test_resend_stops_after_two_attempts(): void
    {
        $verifier = new class ($this->profile()) extends ReloadVerifier {
            public int $resent = 0;

            protected function resendReload(): void
            {
                $this->resent++;
            }

            protected function stopTimeoutSeconds(): float
            {
                return 0.0;
            }

            protected function liveWorkers(): array
            {
                return [[
                    'pid' => 11,
                    'fingerprint' => 'old',
                    'booted_at' => '2026-01-01 00:00:00',
                ]];
            }

            protected function resolveMasterChildPids(): ?array
            {
                return [11];
            }
        };

        try {
            $verifier->waitUntilEffective('expected', 1);
            $this->fail('旧指纹一直不变时应超时');
        } catch (\core\exception\BusinessException $e) {
            $this->assertStringContainsString('仍有存活 worker 报告与期望不一致的指纹', $e->getMessage());
        }
        $this->assertSame(2, $verifier->resent);
    }

    public function test_empty_worker_set_does_not_resend(): void
    {
        $verifier = new class ($this->profile()) extends ReloadVerifier {
            public int $resent = 0;

            protected function resendReload(): void
            {
                $this->resent++;
            }

            protected function stopTimeoutSeconds(): float
            {
                return 0.0;
            }

            protected function liveWorkers(): array
            {
                return [];
            }

            protected function resolveMasterChildPids(): ?array
            {
                return [];
            }
        };

        try {
            $verifier->waitUntilEffective('expected', 1);
            $this->fail('没有存活 worker 时应超时');
        } catch (\core\exception\BusinessException $e) {
            $this->assertStringContainsString('没有任何存活 worker 报到', $e->getMessage());
        }
        $this->assertSame(0, $verifier->resent);
    }

    private function profile(): PluginHostProfile
    {
        return new class () implements PluginHostProfile {
            public function strictMigrationHash(): bool
            {
                return false;
            }

            public function categories(): array
            {
                return [];
            }

            public function reloadTimeoutSeconds(): int
            {
                return 5;
            }

            public function reloadPollIntervalMs(): int
            {
                return 1;
            }

            public function publishableFrontends(): array
            {
                return [];
            }

            public function sourceEnds(): array
            {
                return [];
            }
        };
    }
}
