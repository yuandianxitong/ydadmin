<?php
declare(strict_types=1);

namespace app\service\message;

use app\repository\message\MessageLogRepository;
use app\repository\message\MessageTemplateRepository;
use app\repository\user\UserRepository;
use core\base\Service;
use core\exception\BusinessException;
use think\facade\Log;

class MessageService extends Service
{
    protected MessageTemplateRepository $templateRepository;
    protected MessageLogRepository $logRepository;
    protected UserRepository $userRepository;

    /**
     * 获取模板列表
     */
    public function getTemplateList(array $params): array
    {
        $page = (int) ($params['page'] ?? 1);
        $limit = (int) ($params['limit'] ?? 20);

        return $this->templateRepository->getSearchList($params, $page, $limit);
    }

    /**
     * 获取模板详情
     */
    public function getTemplateDetail(int $id): ?array
    {
        return $this->templateRepository->find($id);
    }

    /**
     * 创建模板
     */
    public function createTemplate(array $data): array
    {
        if ($this->templateRepository->existsCode($data['code'])) {
            throw new BusinessException(lang('business.template_code_exists'));
        }

        return $this->templateRepository->create($data);
    }

    /**
     * 更新模板
     */
    public function updateTemplate(int $id, array $data): bool
    {
        $template = $this->templateRepository->find($id);
        if (!$template) {
            throw new BusinessException(lang('business.template_not_found'));
        }

        if (isset($data['code']) && $data['code'] !== $template['code']) {
            if ($this->templateRepository->existsCode($data['code'], $id)) {
                throw new BusinessException(lang('business.template_code_exists'));
            }
        }

        return $this->templateRepository->update($id, $data);
    }

    /**
     * 删除模板
     */
    public function deleteTemplate(int $id): bool
    {
        $template = $this->templateRepository->find($id);
        if (!$template) {
            throw new BusinessException(lang('business.template_not_found'));
        }
        return $this->templateRepository->delete($id);
    }

    /**
     * 统一发送消息
     * @param string $code 模板标识
     * @param array  $receivers ['phone' => '手机号', 'openid' => '公众号openid', 'mini_openid' => '小程序openid']
     * @param array  $data 模板变量
     */
    public function send(string $code, array $receivers, array $data): array
    {
        $template = $this->templateRepository->findEnabledByCode($code);
        if (!$template) {
            throw new BusinessException(sprintf(lang('business.template_disabled'), $code));
        }

        $results = [];

        // 短信通道
        if ($template->sms_enabled && !empty($receivers['phone']) && !empty($template->sms_template_id)) {
            $results['sms'] = $this->sendChannel(
                'sms',
                $template,
                $receivers['phone'],
                $template->sms_template_id,
                $data
            );
        }

        // 公众号模板消息
        if ($template->wechat_official_enabled && !empty($receivers['openid']) && !empty($template->wechat_official_template_id)) {
            $results['wechat_official'] = $this->sendChannel(
                'wechat_official',
                $template,
                $receivers['openid'],
                $template->wechat_official_template_id,
                $data,
                ['url' => $template->wechat_official_url]
            );
        }

        // 小程序订阅消息
        if ($template->wechat_mini_enabled && !empty($receivers['mini_openid']) && !empty($template->wechat_mini_template_id)) {
            $results['wechat_mini'] = $this->sendChannel(
                'wechat_mini',
                $template,
                $receivers['mini_openid'],
                $template->wechat_mini_template_id,
                $data,
                ['page' => $template->wechat_mini_page]
            );
        }

        return $results;
    }

    /**
     * 尝试发送消息（失败不抛异常）
     *
     * 专为事件监听器设计：消息发送是副作用，失败不应影响主流程。
     * 模板不存在或未启用时静默跳过，发送失败只记日志。
     *
     * @param string $code 模板标识
     * @param array  $receivers ['phone' => '手机号', 'openid' => '公众号openid', 'mini_openid' => '小程序openid']
     * @param array  $data 模板变量
     * @return array 发送结果，失败时返回 ['error' => '错误信息']
     */
    public function trySend(string $code, array $receivers, array $data): array
    {
        try {
            return $this->send($code, $receivers, $data);
        } catch (\Throwable $e) {
            Log::warning("消息发送跳过 [{$code}]: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * 根据用户 ID 发送模板消息
     *
     * 封装"查找用户 → 构造 receivers（phone/openid/mini_openid）→ trySend"的固定流程，
     * 供 Listener 等调用方避免重复编写查找 + 构造 receivers 的样板代码。
     *
     * @param int    $userId       用户 ID，为 0 或用户不存在时静默返回 error
     * @param string $templateCode 模板编码
     * @param array  $variables    模板变量
     */
    public function sendToUser(int $userId, string $templateCode, array $variables = []): array
    {
        if ($userId <= 0) {
            return ['error' => 'invalid user id'];
        }

        $user = $this->userRepository->findModel($userId);
        if (!$user) {
            return ['error' => 'user not found'];
        }

        $receivers = array_filter([
            'phone'       => $user->mobile ?? '',
            'openid'      => $user->oa_openid ?? '',
            'mini_openid' => $user->mini_openid ?? '',
        ]);

        if (empty($receivers)) {
            return ['error' => 'no receivers'];
        }

        return $this->trySend($templateCode, $receivers, $variables);
    }

    /**
     * 单通道发送并记录日志（异步队列）
     */
    protected function sendChannel(
        string $channel,
        $template,
        string $receiver,
        string $templateId,
        array $data,
        array $extra = []
    ): array {
        $log = $this->logRepository->createLog([
            'template_id'   => $template->id,
            'template_code' => $template->code,
            'channel'       => $channel,
            'receiver'      => $receiver,
            'variables'     => $data,
            'status'        => 0,
        ]);

        \core\queue\QueueManager::push(\app\job\MessageSendJob::class, [
            'channel'     => $channel,
            'receiver'    => $receiver,
            'template_id' => $templateId,
            'variables'   => $data,
            'extra'       => $extra,
            'log_id'      => $log['id'],
        ]);

        return ['success' => true, 'queued' => true];
    }

    /**
     * 获取发送记录列表
     */
    public function getLogList(array $params): array
    {
        $page = (int) ($params['page'] ?? 1);
        $limit = (int) ($params['limit'] ?? 20);

        return $this->logRepository->getSearchList($params, $page, $limit);
    }
}
