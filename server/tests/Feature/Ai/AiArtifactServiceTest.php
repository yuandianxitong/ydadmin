<?php
// tests/Feature/Ai/AiArtifactServiceTest.php
declare(strict_types=1);

namespace tests\Feature\Ai;

use app\repository\ai\AiArtifactRepository;
use app\service\system\AiArtifactService;
use core\ai\checks\CheckContext;
use core\ai\checks\CheckInterface;
use core\ai\checks\CheckResult;
use core\ai\checks\CheckRunner;
use core\exception\BusinessException;
use tests\TestCase;

/** 内存假 Repository：不连库，模拟 transition/find/create/supersede */
class FakeArtifactRepo extends AiArtifactRepository
{
    public array $rows = [];
    private int $seq = 0;

    public function __construct() {}

    public function find($id): ?array
    {
        return $this->rows[$id] ?? null;
    }

    public function create(array $data): array
    {
        $id = ++$this->seq;
        $this->rows[$id] = array_merge(['id' => $id, 'check_summary' => null], $data);
        return $this->rows[$id];
    }

    public function update($id, array $data): bool
    {
        if (!isset($this->rows[$id])) { return false; }
        $this->rows[$id] = array_merge($this->rows[$id], $data);
        return true;
    }

    public function transition(int $id, array $from, string $to, array $extra = []): int
    {
        if (!isset($this->rows[$id]) || !in_array($this->rows[$id]['state'], $from, true)) {
            return 0;
        }
        $this->rows[$id] = array_merge($this->rows[$id], ['state' => $to], $extra);
        return 1;
    }

    public function supersedeOthers(string $specId, int $keepId): int
    {
        $n = 0;
        foreach ($this->rows as $id => $r) {
            if ($id !== $keepId && $r['spec_id'] === $specId && in_array($r['state'], ['compiled', 'checking', 'checked_passed', 'checked_failed'], true)) {
                $this->rows[$id]['state'] = 'superseded';
                $n++;
            }
        }
        return $n;
    }
}

class AiArtifactServiceTest extends TestCase
{
    private function service(FakeArtifactRepo $repo, bool $checksPass): AiArtifactService
    {
        return new class($repo, $checksPass) extends AiArtifactService {
            public array $materialized = [];
            public function __construct(private FakeArtifactRepo $r, private bool $pass) { parent::__construct(); }
            protected function initialize(): void { $this->aiArtifactRepository = $this->r; }
            protected function buildContext(array $art): CheckContext { return new CheckContext('/tmp', [], [], '', '', []); }
            protected function makeRunner(): CheckRunner
            {
                $pass = $this->pass;
                $check = new class($pass) implements CheckInterface {
                    public function __construct(private bool $pass) {}
                    public function name(): string { return 'fake'; }
                    public function check(CheckContext $ctx): array
                    {
                        return $this->pass ? [] : [new CheckResult('fake', 'error', 'bad')];
                    }
                };
                return new CheckRunner([$check]);
            }
            protected function materialize(array $art, ?string $override): array
            {
                $this->materialized[] = $art['id'];
                return ['app/model/x/X.php'];
            }
        };
    }

    public function testRecordCreatesCompiledAndSupersedesOld(): void
    {
        $repo = new FakeArtifactRepo();
        $repo->create(['spec_id' => 'spec_a', 'stage_id' => 'compile_old', 'module' => 'x', 'title' => 'X', 'state' => 'checked_passed']);
        $svc = $this->service($repo, true);
        $id = $svc->record('spec_a', 'compile_new', 'x', 'X');
        $this->assertSame('compiled', $repo->rows[$id]['state']);
        $this->assertSame('superseded', $repo->rows[1]['state']);
    }

    public function testRunChecksPassGoesToPassed(): void
    {
        $repo = new FakeArtifactRepo();
        $svc = $this->service($repo, true);
        $id = $svc->record('spec_a', 'compile_x', 'x', 'X');
        $out = $svc->runChecks($id);
        $this->assertSame('checked_passed', $out['state']);
        $this->assertTrue($out['check_summary']['passed']);
    }

    public function testRunChecksFailGoesToFailed(): void
    {
        $repo = new FakeArtifactRepo();
        $svc = $this->service($repo, false);
        $id = $svc->record('spec_a', 'compile_x', 'x', 'X');
        $out = $svc->runChecks($id);
        $this->assertSame('checked_failed', $out['state']);
        $this->assertFalse($out['check_summary']['passed']);
    }

    public function testApplyRejectedWhenNotPassed(): void
    {
        $repo = new FakeArtifactRepo();
        $svc = $this->service($repo, false);
        $id = $svc->record('spec_a', 'compile_x', 'x', 'X');
        $svc->runChecks($id); // checked_failed
        $this->expectException(BusinessException::class);
        $svc->applyArtifact($id);
    }

    public function testApplySucceedsWhenPassed(): void
    {
        $repo = new FakeArtifactRepo();
        $svc = $this->service($repo, true);
        $id = $svc->record('spec_a', 'compile_x', 'x', 'X');
        $svc->runChecks($id); // checked_passed
        $res = $svc->applyArtifact($id);
        $this->assertTrue($res['applied']);
        $this->assertSame('applied', $repo->rows[$id]['state']);
        $this->assertContains('app/model/x/X.php', $repo->rows[$id]['applied_files']);
    }
}
