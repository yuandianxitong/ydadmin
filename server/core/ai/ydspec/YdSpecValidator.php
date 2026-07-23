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
}
