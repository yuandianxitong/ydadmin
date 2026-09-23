<?php

declare(strict_types=1);

namespace app\service\wechat;

use app\repository\wechat\WechatAutoReplyRepository;
use core\base\Service;
use core\exception\BusinessException;
use DI\Attribute\Inject;

/** 微信公众号自动回复管理与匹配。容器单例，不保存请求态。 */
class AutoReplyService extends Service
{
    #[Inject]
    protected WechatAutoReplyRepository $wechatAutoReplyRepository;

    /**
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getList(array $params, int $page, int $limit): array
    {
        return $this->wechatAutoReplyRepository->getAdminList($params, $page, $limit);
    }

    /** @return array<string, mixed> */
    public function getDetail(int $id): array
    {
        return $this->findOrFail($id);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): void
    {
        $this->runInTransaction(function () use ($data): void {
            $data['status'] ??= WechatAutoReplyRepository::STATUS_ENABLED;
            $data['sort_order'] ??= 0;
            $data = $this->normalize($data);
            $this->assertUniqueEnabledType($data);
            $data['reply_type'] = WechatAutoReplyRepository::REPLY_TEXT;
            $this->wechatAutoReplyRepository->create($data);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $this->runInTransaction(function () use ($id, $data): void {
            $current = $this->findOrFail($id);
            $candidate = array_merge($current, $data);
            $candidate = $this->normalize($candidate);
            $this->assertUniqueEnabledType($candidate, $id);

            $data = $this->normalize(array_merge($data, ['type' => $candidate['type']]));
            $data['reply_type'] = WechatAutoReplyRepository::REPLY_TEXT;
            $this->wechatAutoReplyRepository->update($id, $data);
        });
    }

    public function delete(int $id): void
    {
        $this->findOrFail($id);
        if (!$this->wechatAutoReplyRepository->delete($id)) {
            throw new BusinessException(lang('wechat.auto_reply_not_found'));
        }
    }

    public function matchKeyword(string $keyword): ?string
    {
        $exact = $this->wechatAutoReplyRepository->findExactKeyword($keyword);
        if ($exact !== null) {
            return (string) $exact['content'];
        }

        // 精确匹配落在 MySQL 的 utf8mb4_0900_ai_ci 上，本来就不分大小写；
        // 模糊匹配在 PHP 里做，不统一小写的话同一个关键词「精确能中、模糊不中」，运营会当成坏了。
        $haystack = mb_strtolower($keyword);
        foreach ($this->wechatAutoReplyRepository->fuzzyRules() as $rule) {
            $needle = mb_strtolower((string) ($rule['keyword'] ?? ''));
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return (string) $rule['content'];
            }
        }

        $default = $this->wechatAutoReplyRepository->findEnabledByType(WechatAutoReplyRepository::TYPE_DEFAULT);

        return $default === null ? null : (string) $default['content'];
    }

    public function subscribeReply(): ?string
    {
        $reply = $this->wechatAutoReplyRepository->findEnabledByType(WechatAutoReplyRepository::TYPE_SUBSCRIBE);

        return $reply === null ? null : (string) $reply['content'];
    }

    /** @return array<string, mixed> */
    private function findOrFail(int $id): array
    {
        return $this->wechatAutoReplyRepository->findById($id)
            ?? throw new BusinessException(lang('wechat.auto_reply_not_found'));
    }

    /** @param array<string, mixed> $data */
    private function assertUniqueEnabledType(array $data, ?int $exceptId = null): void
    {
        $type = (string) $data['type'];
        if ((int) $data['status'] !== WechatAutoReplyRepository::STATUS_ENABLED
            || !in_array($type, [WechatAutoReplyRepository::TYPE_SUBSCRIBE, WechatAutoReplyRepository::TYPE_DEFAULT], true)
            || !$this->wechatAutoReplyRepository->existsEnabledByType($type, $exceptId)) {
            return;
        }

        $key = $type === WechatAutoReplyRepository::TYPE_SUBSCRIBE
            ? 'wechat.reply_exists_subscribe'
            : 'wechat.reply_exists_default';
        throw new BusinessException(lang($key));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        // 这几列都是 NOT NULL，而校验规则写的是 nullable：显式传 null 会一路走到 INSERT 变成 500。
        // 传了 null 当作「没传」丢掉，由列默认值或下面的兜底决定。
        foreach (['keyword', 'match_type', 'sort_order', 'status', 'content'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === null) {
                unset($data[$field]);
            }
        }

        if (in_array((string) ($data['type'] ?? ''), [WechatAutoReplyRepository::TYPE_SUBSCRIBE, WechatAutoReplyRepository::TYPE_DEFAULT], true)) {
            $data['keyword'] = '';
            $data['match_type'] = WechatAutoReplyRepository::MATCH_EXACT;
        }

        return $data;
    }
}
