<?php

declare(strict_types=1);

namespace app\service\realtime;

use core\auth\TokenVersion;
use core\base\Service;
use core\realtime\RealtimePublisher;
use DI\Attribute\Inject;

/**
 * 强制下线（spec §7）：踢掉一个管理员的全部会话。
 *
 * 顺序固定：先 TokenVersion::bump（该管理员所有 token 立即失效）→ 再推送 force_logout → 最后清在线状态。
 * 先吊销再推送：推送是至多一次，丢了也不能留下空档——被踢者下一次 HTTP 请求照样 401，WS 进程的
 * 60 秒吊销复查也会断开它的连接。发布器失败只记 warning（RealtimePublisher 内部吞掉），不影响吊销结果。
 *
 * 权限与「能不能踢」的判定在 OnlineAdminService，本类只执行。容器单例，无实例态。
 */
class ForceLogoutService extends Service
{
    #[Inject]
    protected RealtimePublisher $publisher;

    #[Inject]
    protected PresenceService $presence;

    /**
     * @param string $reason kicked（被管理员强制下线）| revoked（会话被吊销）
     * @return int 清掉的在线连接数（不在线为 0）
     */
    public function kick(int $adminId, string $reason = 'kicked'): int
    {
        TokenVersion::bump($adminId);
        $this->publisher->publish([$adminId], 'force_logout', [
            'reason'  => $reason,
            'message' => lang('business.realtime_force_logout'),
        ]);

        return $this->presence->forget($adminId);
    }
}
