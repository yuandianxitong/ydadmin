<?php

declare(strict_types=1);

/**
 * 常驻内存纪律检查·规则七（M2b spec §6/§14）：$this->validate( 的第二个参数必须**恰好**是
 * $this->{当前动作名}Rules()。
 *
 * 为什么用 token_get_all() 而不是正则/awk 逐行扫描：评审在隔离目录逐条实测过，awk 逐行版
 * 两头都漏——箭头两侧带空格（$this -> validate(...)）、方法链跨行（$this\n->validate(...)）、
 * 行尾注释里恰好出现正确方法名（// TODO: $this->storeRules()）都会被 awk 版放行；反过来，
 * 格式正确但跨多行的 validate() 调用、注释里提到 $this->validate( 又会被 awk 版误报——
 * 正则按「行」为单位，天然处理不了「这段文本是不是注释/字符串」「参数是不是跨行」。
 * token 流是词法分析的结果：注释、字符串字面量本身就是单独的 token（T_COMMENT /
 * T_CONSTANT_ENCAPSED_STRING 等），不会被误当成代码里的 $this->validate( 或 Rules()；
 * 比对时跳过 T_WHITESPACE/T_COMMENT/T_DOC_COMMENT，换行和空格自然不影响判定。
 *
 * 用法：php scripts/check-validate-rules.php <root> <dir1> [<dir2> ...]
 *   <root>：用于把命中文件的绝对路径裁成相对路径（点名违规时好读）。
 *   <dir*>：要扫描的绝对目录路径（递归查 *.php），由调用方——check-context-discipline.sh——
 *   先按存在性过滤好，这里不重复判断，也不接受不存在的目录（is_dir() 会自然跳过）。
 */

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * 跳过空白/注释，返回沿 $step 方向下一个「有意义」token 的下标（不存在则为 null）。
 *
 * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens token_get_all() 的原始返回值
 */
function nextSignificantIndex(array $tokens, int $index, int $step): ?int
{
    $total = count($tokens);
    for ($index += $step; $index >= 0 && $index < $total; $index += $step) {
        $token = $tokens[$index];
        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        return $index;
    }
    return null;
}

/**
 * 把 [$start, $end] 闭区间内的「有意义」token 原样拼回字面文本（跳过空白与注释），用于比对。
 *
 * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens token_get_all() 的原始返回值
 */
function renderSegment(array $tokens, ?int $start, ?int $end): string
{
    if ($start === null || $end === null || $start > $end) {
        return '';
    }
    $text = '';
    for ($k = $start; $k <= $end; $k++) {
        $token = $tokens[$k];
        if (is_array($token)) {
            if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $text .= $token[1];
        } else {
            $text .= $token;
        }
    }
    return $text;
}

/**
 * 扫描单个文件，返回违规描述列表（每条已经是"相对路径:行号: 期望.../实际..."的成品文案）。
 *
 * @return list<string>
 */
function scanValidateCalls(string $source, string $relativePath): array
{
    $tokens = token_get_all($source);
    $total = count($tokens);
    $violations = [];
    $currentAction = null;

    for ($i = 0; $i < $total; $i++) {
        $token = $tokens[$i];

        // --- 追踪「当前动作名」：T_FUNCTION 后紧跟 T_STRING 才是具名方法声明（哪怕方法名跟
        //     function 关键字不在同一行）；紧跟 "(" 的是闭包（function (...) {...}），不更新；
        //     T_FN 箭头函数根本不是 T_FUNCTION token，同样不会走到这里，天生不更新。
        if (is_array($token) && $token[0] === T_FUNCTION) {
            $nameIdx = nextSignificantIndex($tokens, $i, 1);
            if ($nameIdx !== null && is_array($tokens[$nameIdx]) && $tokens[$nameIdx][0] === T_STRING) {
                $currentAction = $tokens[$nameIdx][1];
            }
            continue;
        }

        // --- 定位 $this->validate( 调用：T_VARIABLE($this) -> T_OBJECT_OPERATOR -> T_STRING(validate) -> '('，
        //     中间任意多空白/注释都不影响（nextSignificantIndex 已经跳过）。
        if (!is_array($token) || $token[0] !== T_VARIABLE || $token[1] !== '$this') {
            continue;
        }
        $opIdx = nextSignificantIndex($tokens, $i, 1);
        if ($opIdx === null || !is_array($tokens[$opIdx]) || $tokens[$opIdx][0] !== T_OBJECT_OPERATOR) {
            continue;
        }
        $methodIdx = nextSignificantIndex($tokens, $opIdx, 1);
        if (
            $methodIdx === null
            || !is_array($tokens[$methodIdx])
            || $tokens[$methodIdx][0] !== T_STRING
            || $tokens[$methodIdx][1] !== 'validate'
        ) {
            continue;
        }
        $parenIdx = nextSignificantIndex($tokens, $methodIdx, 1);
        if ($parenIdx === null || $tokens[$parenIdx] !== '(') {
            continue;
        }
        $callLine = $token[2];

        // --- 从 '(' 之后按括号/中括号/花括号深度走，切出深度为 1 的顶层逗号分隔参数区间。
        $depth = 1;
        $segStart = nextSignificantIndex($tokens, $parenIdx, 1);
        $params = [];
        $j = $parenIdx;
        while (true) {
            $j++;
            if ($j >= $total) {
                break; // 括号不闭合，语法本身有问题，交给 php -l，这里不重复报告
            }
            $current = $tokens[$j];
            $text = is_array($current) ? $current[1] : $current;
            if ($text === '(' || $text === '[' || $text === '{') {
                $depth++;
                continue;
            }
            if ($text === ')' || $text === ']' || $text === '}') {
                $depth--;
                if ($depth === 0) {
                    $params[] = [$segStart, $j - 1];
                    break;
                }
                continue;
            }
            if ($depth === 1 && $text === ',') {
                $params[] = [$segStart, $j - 1];
                $segStart = nextSignificantIndex($tokens, $j, 1);
            }
        }

        if (!isset($params[1])) {
            continue; // 不足两个参数，不是本规则要判定的形状
        }

        [$segStart, $segEnd] = $params[1];
        $actual = renderSegment($tokens, $segStart, $segEnd);
        $expected = $currentAction === null ? null : ('$this->' . $currentAction . 'Rules()');

        if ($expected === null || $actual !== $expected) {
            $violations[] = sprintf(
                '%s:%d: 期望 %s，实际是 %s',
                $relativePath,
                $callLine,
                $expected ?? '$this->{当前动作名}Rules()（未能识别当前动作名）',
                $actual === '' ? '(空)' : $actual
            );
        }
    }

    return $violations;
}

$args = array_slice($argv, 1);
if ($args === []) {
    echo "✅ 规则七通过：\$this->validate( 的规则参数均为 \$this->{动作名}Rules()\n";
    exit(0);
}

$root = array_shift($args);
$dirs = $args;
$violations = [];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        /** @var SplFileInfo $file */
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $absolute = $file->getPathname();
        $relative = str_starts_with($absolute, $root) ? ltrim(substr($absolute, strlen($root)), '/') : $absolute;
        $source = (string) file_get_contents($absolute);
        foreach (scanValidateCalls($source, $relative) as $violation) {
            $violations[] = $violation;
        }
    }
}

if ($violations !== []) {
    echo "❌ \$this->validate( 的第二个参数必须是 \$this->{动作名}Rules()（发现不匹配的调用，文档会静默漏掉该端点的参数）：\n";
    foreach ($violations as $violation) {
        echo "  {$violation}\n";
    }
    exit(1);
}

echo "✅ 规则七通过：\$this->validate( 的规则参数均为 \$this->{动作名}Rules()\n";
exit(0);
