<?php
// server/core/ai/ydspec/YdSpecValidator.php
declare(strict_types=1);

namespace core\ai\ydspec;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

class YdSpecValidator
{
    public const VERSION = 'ydspec/v1';

    private function schemaPath(): string
    {
        return __DIR__ . '/../spec/ydspec.schema.json';
    }

    /** @return string[] 结构错误列表，空数组表示通过 */
    public function validateStructure(array $spec): array
    {
        $validator = new Validator();
        // 转成 stdClass，opis 要求对象而非关联数组
        $data = json_decode(json_encode($spec, JSON_UNESCAPED_UNICODE));
        $schema = json_decode((string) file_get_contents($this->schemaPath()));
        $result = $validator->validate($data, $schema);
        if ($result->isValid()) {
            return [];
        }
        $formatted = (new ErrorFormatter())->formatFlat($result->error());
        return array_values($formatted);
    }

    private const KNOWN_ENTITIES = ['User', 'Dept', 'Region', 'Admin'];
    private const RESERVED_FIELDS = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /** @return array<int,array{ref:string,rule:string,severity:string,message:string}> */
    public function validateSemantics(array $spec): array
    {
        $issues = [];
        $entityNames = array_column($spec['entities'] ?? [], 'name');

        foreach ($spec['entities'] ?? [] as $entity) {
            $eName = (string) ($entity['name'] ?? '?');
            $kind = (string) ($entity['kind'] ?? '');
            $fieldNames = array_column($entity['fields'] ?? [], 'name');

            if ($kind === 'log' && (string) ($entity['soft_delete'] ?? '') !== 'none') {
                $issues[] = $this->issue($eName, 'log-append-only', 'error', 'log 类实体必须 soft_delete=none（只追加）');
            }

            foreach ($entity['fields'] ?? [] as $field) {
                $name = (string) ($field['name'] ?? '');
                $type = (string) ($field['type'] ?? '');
                $ref = $eName . '.' . $name;

                if (in_array($name, self::RESERVED_FIELDS, true)) {
                    $issues[] = $this->issue($ref, 'reserved-field', 'error', "保留字段 {$name} 由编译器自动生成，请勿声明");
                }
                if ($this->looksLikeMoney($name) && $type !== 'decimal') {
                    $issues[] = $this->issue($ref, 'money-decimal', 'error', '金额字段必须使用 decimal 类型');
                }
                if (preg_match('/(_no|_sn|_code)$/', $name) && empty($field['unique'])) {
                    $issues[] = $this->issue($ref, 'business-no-unique', 'warn', '业务编号建议加唯一索引 unique:true');
                }
                if ($name === 'status' && $kind === 'log') {
                    $issues[] = $this->issue($ref, 'log-no-status', 'warn', 'log 类实体通常不应有状态生命周期');
                }
                if (isset($field['relation']['to'])) {
                    $to = (string) $field['relation']['to'];
                    $known = in_array($to, self::KNOWN_ENTITIES, true) || in_array($to, $entityNames, true);
                    if (!$known) {
                        $issues[] = $this->issue($ref, 'relation-target', 'error', "关系目标实体 {$to} 不存在");
                    }
                }
                if (preg_match('/(_dept|_region|_dict)$/', $name)) {
                    $issues[] = $this->issue($ref, 'reuse-capability', 'warn', '疑似可复用框架已有能力（字典/部门/区域），确认是否需要新建列');
                }
            }

            foreach ($entity['indexes'] ?? [] as $index) {
                foreach ($index['fields'] ?? [] as $col) {
                    if (!in_array($col, $fieldNames, true)) {
                        $issues[] = $this->issue($eName, 'index-unknown-field', 'error', "索引引用了不存在的字段 {$col}");
                    }
                }
            }
        }

        return $issues;
    }

    private function looksLikeMoney(string $name): bool
    {
        return (bool) preg_match('/(^amount$|_amount$|^price$|_price$|^money$|_money$|^balance$|_balance$|^fee$|_fee$)/', $name);
    }

    /** @return array{ref:string,rule:string,severity:string,message:string} */
    private function issue(string $ref, string $rule, string $severity, string $message): array
    {
        return ['ref' => $ref, 'rule' => $rule, 'severity' => $severity, 'message' => $message];
    }
}
