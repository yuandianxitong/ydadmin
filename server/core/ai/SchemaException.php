<?php
declare(strict_types=1);

namespace core\ai;

/**
 * 数据表结构读取失败异常（用户可修正的输入问题：表不存在/无字段）
 * 与其他 AiClientException（引擎连接、响应异常）区分，供命令层映射不同退出码
 */
class SchemaException extends AiClientException
{
}
