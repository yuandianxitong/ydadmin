<?php

declare(strict_types=1);

namespace app\repository\wechat;

use app\model\wechat\WechatAutoReply;
use core\base\Model;
use core\base\Repository;

/**
 * 微信公众号自动回复仓储（M6c spec §4.1）。不设 $dataScoped：全局运营数据，表无 created_by（$creatorColumn=null）。
 */
class WechatAutoReplyRepository extends Repository
{
    /** 转发常量：Service 禁止引用 app\model\* 的常量（check:context 规则三）。 */
    public const TYPE_KEYWORD = WechatAutoReply::TYPE_KEYWORD;

    public const TYPE_SUBSCRIBE = WechatAutoReply::TYPE_SUBSCRIBE;

    public const TYPE_DEFAULT = WechatAutoReply::TYPE_DEFAULT;

    public const MATCH_EXACT = WechatAutoReply::MATCH_EXACT;

    public const MATCH_FUZZY = WechatAutoReply::MATCH_FUZZY;

    public const REPLY_TEXT = WechatAutoReply::REPLY_TEXT;

    public const STATUS_ENABLED = WechatAutoReply::STATUS_ENABLED;

    public const STATUS_DISABLED = WechatAutoReply::STATUS_DISABLED;

    protected ?string $creatorColumn = null;

    protected function getModel(): Model
    {
        return new WechatAutoReply();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $data['reply_type'] = self::REPLY_TEXT;

        return parent::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(int|string $id, array $data): bool
    {
        $data['reply_type'] = self::REPLY_TEXT;

        return parent::update($id, $data);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        return $this->query()->where($this->qualify('id'), $id)->first()?->toArray();
    }

    /**
     * 管理端列表：type 精确过滤（空串不过滤）；sort_order asc, id desc。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getAdminList(array $params, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = min(self::MAX_PAGE_SIZE, max(1, $limit));
        $query = $this->query();

        $type = trim((string) ($params['type'] ?? ''));
        if ($type !== '') {
            $query->where($this->qualify('type'), $type);
        }

        $total = (clone $query)->count();
        $list = $query->orderBy($this->qualify('sort_order'), 'asc')
            ->orderBy($this->qualify('id'), 'desc')
            ->forPage($page, $limit)
            ->get()
            ->toArray();

        return $this->buildPagination($list, $page, $limit, $total);
    }

    public function existsEnabledByType(string $type, ?int $exceptId = null): bool
    {
        $query = $this->query()->where($this->qualify('type'), $type)->where($this->qualify('status'), self::STATUS_ENABLED);
        if ($exceptId !== null) {
            $query->where($this->qualify('id'), '<>', $exceptId);
        }

        return $query->exists();
    }

    /**
     * 启用且未软删的精确关键词规则（sort_order 最小者优先）。
     *
     * @return array<string, mixed>|null
     */
    public function findExactKeyword(string $keyword): ?array
    {
        $row = $this->query()
            ->where($this->qualify('type'), self::TYPE_KEYWORD)
            ->where($this->qualify('match_type'), self::MATCH_EXACT)
            ->where($this->qualify('keyword'), $keyword)
            ->where($this->qualify('status'), self::STATUS_ENABLED)
            ->orderBy($this->qualify('sort_order'), 'asc')
            ->orderBy($this->qualify('id'), 'asc')
            ->first();

        /** @var \core\base\Model|null $row */
        return $row?->toArray();
    }

    /**
     * 启用的模糊关键词规则（跳过空 keyword），sort_order asc, id asc。
     *
     * @return array<int, array<string, mixed>>
     */
    public function fuzzyRules(): array
    {
        return $this->query()
            ->where($this->qualify('type'), self::TYPE_KEYWORD)
            ->where($this->qualify('match_type'), self::MATCH_FUZZY)
            ->where($this->qualify('status'), self::STATUS_ENABLED)
            ->where($this->qualify('keyword'), '<>', '')
            ->orderBy($this->qualify('sort_order'), 'asc')
            ->orderBy($this->qualify('id'), 'asc')
            ->get()
            ->toArray();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findEnabledByType(string $type): ?array
    {
        return $this->query()
            ->where($this->qualify('type'), $type)
            ->where($this->qualify('status'), self::STATUS_ENABLED)
            ->first()?->toArray();
    }
}
