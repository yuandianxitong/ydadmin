<?php

declare(strict_types=1);

namespace app\service\payment;

/**
 * 商户单号：业务前缀 + YmdHis + 8 位 random_int（M5b spec §2.3）。充值订单前缀 R，退款单前缀 F（Task 11）。
 * 撞唯一键由调用方重试。不是 final：测试用匿名子类排队返回撞号，经容器替换（无状态，可作容器单例）。
 */
class OrderNoGenerator
{
    public function generate(string $prefix): string
    {
        return $prefix . date('YmdHis') . str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT);
    }
}
