<?php
declare(strict_types=1);

namespace app\service\wechat;

use app\repository\wechat\WechatAutoReplyRepository;
use core\base\Service;
use core\exception\BusinessException;

class AutoReplyService extends Service
{
    protected WechatAutoReplyRepository $autoReplyRepo;

    /**
     * 获取自动回复列表
     */
    public function getList(array $params): array
    {
        $where = [];

        if (!empty($params['type'])) {
            $where[] = ['type', '=', $params['type']];
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $where[] = ['status', '=', (int)$params['status']];
        }
        if (!empty($params['keyword'])) {
            $where[] = ['keyword', 'like', '%' . $params['keyword'] . '%'];
        }

        $page = (int)($params['page'] ?? 1);
        $limit = (int)($params['limit'] ?? 20);

        return $this->autoReplyRepo->getPageList($where, $page, $limit);
    }

    /**
     * 获取详情
     */
    public function getDetail(int $id): ?array
    {
        return $this->autoReplyRepo->find($id);
    }

    /**
     * 创建自动回复
     */
    public function create(array $data): array
    {
        // subscribe 和 default 类型只能有一条启用的
        if (in_array($data['type'], ['subscribe', 'default']) && !empty($data['status'])) {
            if ($this->autoReplyRepo->existsEnabledByType($data['type'])) {
                throw new BusinessException($data['type'] === 'subscribe' ? lang('business.reply_exists_follow') : lang('business.reply_exists_default'));
            }
        }

        return $this->autoReplyRepo->create($data);
    }

    /**
     * 更新自动回复
     */
    public function update(int $id, array $data): bool
    {
        $reply = $this->autoReplyRepo->findModel($id);
        if (!$reply) {
            throw new BusinessException(lang('business.auto_reply_not_found'));
        }

        return $reply->save($data);
    }

    /**
     * 删除自动回复
     */
    public function delete(int $id): bool
    {
        $reply = $this->autoReplyRepo->findModel($id);
        if (!$reply) {
            throw new BusinessException(lang('business.auto_reply_not_found'));
        }
        return $reply->delete();
    }

    /**
     * 根据关键词匹配回复（供消息回调使用）
     */
    public function matchKeyword(string $content): ?string
    {
        // 精确匹配
        $reply = $this->autoReplyRepo->findExactKeyword($content);
        if ($reply) {
            return $reply->content;
        }

        // 模糊匹配
        $fuzzyRules = $this->autoReplyRepo->getFuzzyKeywordRules();
        foreach ($fuzzyRules as $item) {
            if (str_contains($content, $item['keyword'])) {
                return $item['content'];
            }
        }

        return null;
    }

    /**
     * 获取关注回复
     */
    public function getSubscribeReply(): ?string
    {
        $reply = $this->autoReplyRepo->findEnabledByType('subscribe');
        return $reply ? $reply->content : null;
    }

    /**
     * 获取默认回复
     */
    public function getDefaultReply(): ?string
    {
        $reply = $this->autoReplyRepo->findEnabledByType('default');
        return $reply ? $reply->content : null;
    }
}
