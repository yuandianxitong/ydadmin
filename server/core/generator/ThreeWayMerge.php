<?php

declare(strict_types=1);

namespace core\generator;

/** 按行合并不重叠的改动；无法证明安全时返回 null，不输出冲突标记到可执行文件。 */
final class ThreeWayMerge
{
    public function merge(string $base, string $current, string $incoming): ?string
    {
        if ($current === $incoming || $base === $incoming) {
            return $current;
        }
        if ($base === $current) {
            return $incoming;
        }
        $lines = $this->lines($base);
        $ours = $this->edits($lines, $this->lines($current));
        $theirs = $this->edits($lines, $this->lines($incoming));
        if ($ours === null || $theirs === null) {
            return null;
        }
        $combined = $ours;
        foreach ($theirs as $edit) {
            $duplicate = false;
            foreach ($ours as $other) {
                if ($edit === $other) {
                    $duplicate = true;
                    break;
                }
                if ($this->overlaps($edit, $other)) {
                    return null;
                }
            }
            if (!$duplicate) {
                $combined[] = $edit;
            }
        }
        usort($combined, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);
        $result = [];
        $cursor = 0;
        foreach ($combined as $edit) {
            array_push($result, ...array_slice($lines, $cursor, $edit['start'] - $cursor), ...$edit['lines']);
            $cursor = $edit['end'];
        }
        array_push($result, ...array_slice($lines, $cursor));
        return implode('', $result);
    }

    /** @return list<string> */
    private function lines(string $content): array
    {
        return preg_split('/(?<=\n)/', $content, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * @param list<string> $base
     * @param list<string> $changed
     * @return list<array{start: int, end: int, lines: list<string>}>|null
     */
    private function edits(array $base, array $changed): ?array
    {
        $prefix = 0;
        while (isset($base[$prefix], $changed[$prefix]) && $base[$prefix] === $changed[$prefix]) {
            $prefix++;
        }
        $endBase = count($base);
        $endChanged = count($changed);
        while ($endBase > $prefix && $endChanged > $prefix && $base[$endBase - 1] === $changed[$endChanged - 1]) {
            $endBase--;
            $endChanged--;
        }
        $a = array_slice($base, $prefix, $endBase - $prefix);
        $b = array_slice($changed, $prefix, $endChanged - $prefix);
        $n = count($a);
        $m = count($b);
        // 有界 LCS，超出预算转为人工冲突，避免后台请求占满内存。
        if (($n + 1) * ($m + 1) > 250000) {
            return null;
        }
        $dp = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $dp[$i][$j] = $a[$i] === $b[$j] ? $dp[$i + 1][$j + 1] + 1 : max($dp[$i + 1][$j], $dp[$i][$j + 1]);
            }
        }
        $edits = [];
        $i = $j = 0;
        $edit = null;
        while ($i < $n || $j < $m) {
            if ($i < $n && $j < $m && $a[$i] === $b[$j]) {
                if ($edit !== null) {
                    $edits[] = $edit;
                    $edit = null;
                }
                $i++;
                $j++;
            } else {
                $edit ??= ['start' => $prefix + $i, 'end' => $prefix + $i, 'lines' => []];
                if ($j < $m && ($i === $n || $dp[$i][$j + 1] > $dp[$i + 1][$j])) {
                    $edit['lines'][] = $b[$j++];
                } else {
                    $edit['end'] = $prefix + ++$i;
                }
            }
        }
        if ($edit !== null) {
            $edits[] = $edit;
        }
        return $edits;
    }

    /** @param array{start: int, end: int, lines: list<string>} $a
     * @param array{start: int, end: int, lines: list<string>} $b */
    private function overlaps(array $a, array $b): bool
    {
        if ($a['start'] === $a['end']) {
            return $a['start'] >= $b['start'] && $a['start'] <= $b['end'];
        }
        if ($b['start'] === $b['end']) {
            return $b['start'] >= $a['start'] && $b['start'] <= $a['end'];
        }
        return $a['start'] < $b['end'] && $b['start'] < $a['end'];
    }
}
