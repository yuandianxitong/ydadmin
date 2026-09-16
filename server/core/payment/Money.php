<?php

declare(strict_types=1);

namespace core\payment;

/**
 * 元 ↔ 分（M5b spec §2.3）。支付两端的 API 都用分，库里也存分；元字符串只出现在对外契约与余额流水。
 *
 * 全程字符串解析与整数运算，不经 float：1.x 用 float 比对回调金额出过误差。
 * 格式故意比 Laravel 的 decimal:0,2 严格（后者放行 '1.'、'.5'、'+5'），校验层要自己加正则再交进来。
 */
final class Money
{
    /**
     * @throws \InvalidArgumentException 负数、超过两位小数、非纯数字字符串、溢出
     */
    public static function toCents(string|int $yuan): int
    {
        if (is_int($yuan)) {
            if ($yuan < 0 || $yuan > intdiv(PHP_INT_MAX, 100)) {
                throw new \InvalidArgumentException("金额超出范围：{$yuan}");
            }

            return $yuan * 100;
        }

        if (preg_match('/^(\d{1,15})(?:\.(\d{1,2}))?$/D', $yuan, $matches) !== 1) {
            throw new \InvalidArgumentException('金额格式无效：' . json_encode($yuan, JSON_UNESCAPED_UNICODE));
        }

        $fraction = str_pad($matches[2] ?? '', 2, '0');

        return (int) $matches[1] * 100 + (int) $fraction;
    }

    /**
     * @throws \InvalidArgumentException 负数
     */
    public static function toYuan(int $cents): string
    {
        if ($cents < 0) {
            throw new \InvalidArgumentException("金额不能为负：{$cents}");
        }

        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
