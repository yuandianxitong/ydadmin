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

        foreach ($this->wechatAutoReplyRepository->fuzzyRules() as $rule) {
            $needle = (string) ($rule['keyword'] ?? '');
            if ($needle !== '' && str_contains($keyword, $needle)) {
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
        if (in_array((string) ($data['type'] ?? ''), [WechatAutoReplyRepository::TYPE_SUBSCRIBE, WechatAutoReplyRepository::TYPE_DEFAULT], true)) {
            $data['keyword'] = '';
            $data['match_type'] = WechatAutoReplyRepository::MATCH_EXACT;
        }

        return $data;
    }
}
