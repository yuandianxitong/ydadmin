<?php

declare(strict_types=1);

namespace core\payment;

/**
 * 交易类型（M5b spec §4）。微信：native / h5 / app / jsapi；支付宝：page / wap / app。
 * 订单表记录的是真实类型——1.x 把支付宝也存成 native，这里不再沿用。
 */
final class TradeType
{
    public const NATIVE = 'native';

    public const H5 = 'h5';

    public const APP = 'app';

    public const JSAPI = 'jsapi';

    public const PAGE = 'page';

    public const WAP = 'wap';
}
