<?php

declare(strict_types=1);

namespace core\apidoc;

/**
 * Laravel 规则串 → OpenAPI schema 片段（spec §7）。只覆盖仓库实际用到的 18 种校验器；
 * 未识别的规则一律原样计入 notes，绝不静默丢弃——这正是本里程碑要治的病。
 */
final class RuleTranslator
{
    /** @var array<string, string> 规则名 → OpenAPI schema 的 type 取值 */
    private const TYPE_TOKENS = [
        'string'  => 'string',
        'integer' => 'integer',
        'array'   => 'array',
        'boolean' => 'boolean',
    ];

    /** @var array<string, array{max: string, min: string}> 已定型的类型 → max/min 对应的 OpenAPI 关键字 */
    private const BOUND_KEYS = [
        'string'  => ['max' => 'maxLength', 'min' => 'minLength'],
        'integer' => ['max' => 'maximum', 'min' => 'minimum'],
        'array'   => ['max' => 'maxItems', 'min' => 'minItems'],
    ];

    /**
     * 只在字符串上有意义的格式类 token：没有显式 string/integer/array/boolean token 时兜底为 string。
     * 显式类型 token 始终优先——见 translate() 里 $explicitType ?? ($impliesString ? 'string' : null)。
     *
     * @var list<string>
     */
    private const FORMAT_IMPLIES_STRING = ['email', 'url', 'alpha_dash', 'regex', 'date_format'];

    /**
     * @return array{schema: array<string, mixed>, required: bool, notes: list<string>}
     */
    public function translate(string $ruleString): array
    {
        $rules = $this->parse($ruleString);

        // 第一遍：只扫「类型最终定成什么」与「sometimes 有没有出现过」，不动 schema。
        // 三者都不能用单趟从左到右的状态机决定：
        //   - 'nullable|max:50|string' 里 string 排在 max 之后；
        //   - 'sometimes|required|...' 里 sometimes 必须压过后面的 required，不管谁在前面；
        //   - 'nullable|email|max:100' 里没有任何字面类型 token，但 email 蕴含 string，
        //     否则 max:100 会因为"类型未定型"白白丢进 notes。
        $explicitType = null;
        $impliesString = false;
        $hasSometimes = false;
        foreach ($rules as $rule) {
            if (isset(self::TYPE_TOKENS[$rule['name']])) {
                $explicitType = self::TYPE_TOKENS[$rule['name']];
            }
            if (in_array($rule['name'], self::FORMAT_IMPLIES_STRING, true)) {
                $impliesString = true;
            }
            if ($rule['name'] === 'sometimes') {
                $hasSometimes = true;
            }
        }
        // 显式类型永远优先；格式类 token 只在没有任何显式类型 token 时才兜底为 string。
        $type = $explicitType ?? ($impliesString ? 'string' : null);

        $schema = $type !== null ? ['type' => $type] : [];
        $required = false;
        $notes = [];

        // 第二遍：按已定型的 $type / $hasSometimes 解释每一条规则。
        foreach ($rules as $rule) {
            $name = $rule['name'];
            $param = $rule['param'];
            $raw = $rule['raw'];

            switch ($name) {
                case 'required':
                    if (!$hasSometimes) {
                        $required = true;
                    }
                    break;
                case 'sometimes':
                    $required = false;
                    break;
                case 'present':
                    // present 只保证键必须出现，不说值可以是 null：'present|array' 收到 null 会被
                    // array 规则拒绝。只有规则串里真的写了 nullable 才加 nullable（见下一个 case）。
                    $required = true;
                    break;
                case 'nullable':
                    $schema['nullable'] = true;
                    break;
                case 'string':
                case 'integer':
                case 'array':
                case 'boolean':
                    break; // 已在第一遍决定 schema['type']，这里不重复处理
                case 'max':
                case 'min':
                    $key = self::BOUND_KEYS[$type ?? ''][$name] ?? null;
                    if ($key === null) {
                        // 没有 string/integer/array 中任何一个字面 token 定型，无法判定 max/min
                        // 落进哪个 OpenAPI 关键字——原样记一笔，不猜。
                        $notes[] = $raw;
                        break;
                    }
                    $schema[$key] = (int) $param;
                    break;
                case 'in':
                    $values = explode(',', (string) $param);
                    $schema['enum'] = $type === 'integer'
                        ? array_map(static fn (string $value): int => (int) $value, $values)
                        : $values;
                    break;
                case 'not_in':
                    $notes[] = $raw;
                    break;
                case 'email':
                    $schema['format'] = 'email';
                    break;
                case 'url':
                    $schema['format'] = 'uri';
                    break;
                case 'date_format':
                    $schema['format'] = preg_match('/[HhGgis]/', (string) $param) === 1 ? 'date-time' : 'date';
                    break;
                case 'alpha_dash':
                    $schema['pattern'] = '^[A-Za-z0-9_-]+$';
                    break;
                case 'regex':
                    preg_match('/^(.)(.*)\1[a-zA-Z]*$/s', (string) $param, $matches);
                    $schema['pattern'] = $matches[2] ?? (string) $param;
                    $notes[] = $raw; // u 等修饰符没有 OpenAPI 对应物，原始串原样留痕
                    break;
                case 'required_if':
                    $notes[] = $raw;
                    break;
                default:
                    $notes[] = $raw;
                    break;
            }
        }

        return ['schema' => $schema, 'required' => $required, 'notes' => $notes];
    }

    /** @return list<array{raw: string, name: string, param: ?string}> */
    private function parse(string $ruleString): array
    {
        $tokens = array_values(array_filter(explode('|', $ruleString), static fn (string $token): bool => $token !== ''));

        return array_map(static function (string $token): array {
            $parts = explode(':', $token, 2);

            return ['raw' => $token, 'name' => $parts[0], 'param' => $parts[1] ?? null];
        }, $tokens);
    }
}
