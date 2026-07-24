<?php
declare(strict_types=1);

namespace core\ai\checks;

class CheckRunner
{
    /** @param CheckInterface[] $checks */
    public function __construct(private array $checks)
    {
    }

    /**
     * @return array{passed:bool,error_count:int,warning_count:int,skipped:array<int,string>,results:array<int,array>}
     */
    public function run(CheckContext $ctx): array
    {
        $results = [];
        foreach ($this->checks as $check) {
            try {
                foreach ($check->check($ctx) as $r) {
                    $results[] = $r;
                }
            } catch (\Throwable $e) {
                $results[] = new CheckResult($check->name(), 'error', $check->name() . ' 检查异常：' . $e->getMessage());
            }
        }

        $errorCount = 0;
        $warningCount = 0;
        $skipped = [];
        $out = [];
        foreach ($results as $r) {
            if ($r->severity === 'error') {
                $errorCount++;
            } elseif ($r->severity === 'warning') {
                $warningCount++;
            } elseif ($r->severity === 'skipped') {
                $skipped[] = $r->check;
            }
            $out[] = $r->toArray();
        }

        return [
            'passed'        => $errorCount === 0,
            'error_count'   => $errorCount,
            'warning_count' => $warningCount,
            'skipped'       => array_values(array_unique($skipped)),
            'results'       => $out,
        ];
    }
}
