<?php

declare(strict_types=1);

namespace core\diy;

/**
 * 归一化插件 hydrator 的渲染输出为自描述的 {render, items}，供端渲染器消费。
 * 防御性：行为不端的 hydrator 不能把垃圾发到端。只规整 render/items，其它 props（如 componentStyle，
 * 以及各 render 专属的顶层键 tabs/fetch_url/action_url/end_time/more_link/show_price 等）原样保留——
 * 本类不对顶层键做白名单，$props 除 render/items 外全量透传给端渲染器。
 *
 * render 语义（DIY 渲染器协议 v1，renderer 声明见 PluginManifestValidator 6.5 节）：
 * - 声明值 ∈ CORE_RENDER_KINDS：核心通用形态，hydrator 可在 5 种之间运行时自由切换
 *   （如 shop.goods-grid 按 layout 在 card-list/grid-3/scroll-x/list 间切换），越界值回退声明值；
 * - 声明值为插件自定义 token（如 tab-goods/coupon-row）：与插件专属渲染器一一绑定，
 *   不信任 hydrator 输出，强制钉死为声明值。端上注册表按 widget type 查插件渲染器，
 *   未命中一律降级 list 通用渲染（三端统一回退契约）。
 *
 * item 字段：基座白名单（TEXT/RAW_ITEM_KEYS）+ manifest diy_widgets[].item_fields 声明的追加字段
 * （text → (string) 强转；raw → 原样保留类型），由 DiyWidgetCatalog::widgetMetaForNormalize() 提供。
 *
 * M2.4c 骨架化说明：骨架化后 hydratorFor() 恒返回 null，DiyPageService 的
 * `if ($hydrator === null)` 分支恒成立，本类的 normalize() 在骨架化阶段实际上不会被触达，
 * 但仍需完整实现（M4 插件上线后才会被真正触发）。
 */
class NormalizedWidget
{
    /** DIY 渲染器协议版本：manifest renderer.protocol 大于此值时 inspect/pack/install 硬拒绝。 */
    public const SUPPORTED_PROTOCOL = 1;

    /** 核心通用 render：框架内置渲染器支持的 5 种形态，hydrator 可在其间自由切换。 */
    public const CORE_RENDER_KINDS = ['card-list', 'list', 'single', 'grid-3', 'scroll-x'];

    /** 文本类 item 基座键：统一 (string) 转换，防止 hydrator 误传数组/对象污染端渲染。 */
    private const TEXT_ITEM_KEYS = ['title', 'image', 'desc', 'link', 'meta', 'icon', 'label', 'badge_key', 'stat_key'];

    /**
     * 非文本 item 基座键：原样保留原始类型，不做 (string) 转换。
     * value（coupon-row 券 id，int|string）、received（coupon-row 是否已领取，bool，端用
     * `it.received === true` 严格比较，转字符串会破坏该判断）、progress（seckill-row 售出进度 0-100，
     * 端用 `(it.progress ?? 0) + '%'` 拼接，number 语义更贴合前端 PwItem 类型声明）。
     */
    private const RAW_ITEM_KEYS = ['value', 'received', 'progress'];

    /**
     * @param array<string,mixed> $props hydrator 返回的 props（含 render/items + 原始 props）
     * @param array{render?:string,text_keys?:array<int,string>,raw_keys?:array<int,string>} $widgetMeta
     *   信任源元数据（来自 manifest 声明，经 DiyWidgetCatalog::widgetMetaForNormalize() 解析）：
     *   render = manifest 声明的 render；text_keys/raw_keys = item_fields 追加白名单。
     *   缺省（如脱框架单测）等价于声明 render=list、无追加字段。
     * @return array<string,mixed>
     */
    public static function normalize(array $props, array $widgetMeta = []): array
    {
        $declared = (string) ($widgetMeta['render'] ?? 'list');
        if (in_array($declared, self::CORE_RENDER_KINDS, true)) {
            $render = $props['render'] ?? null;
            $props['render'] = in_array($render, self::CORE_RENDER_KINDS, true) ? $render : $declared;
        } else {
            // 插件自定义 render：钉死为声明值，hydrator 输出不参与
            $props['render'] = $declared;
        }

        $textKeys = array_merge(self::TEXT_ITEM_KEYS, array_map('strval', (array) ($widgetMeta['text_keys'] ?? [])));
        $rawKeys = array_merge(self::RAW_ITEM_KEYS, array_map('strval', (array) ($widgetMeta['raw_keys'] ?? [])));

        $items = $props['items'] ?? null;
        $clean = [];
        if (is_array($items)) {
            foreach ($items as $it) {
                if (!is_array($it)) {
                    continue;
                }
                $row = [];
                foreach ($textKeys as $k) {
                    if (isset($it[$k])) {
                        $row[$k] = (string) $it[$k];
                    }
                }
                foreach ($rawKeys as $k) {
                    if (array_key_exists($k, $it)) {
                        $row[$k] = $it[$k];
                    }
                }
                $clean[] = $row;
            }
        }
        $props['items'] = $clean;

        return $props;
    }
}
