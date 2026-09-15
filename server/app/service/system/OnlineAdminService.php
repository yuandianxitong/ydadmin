<?php

declare(strict_types=1);

namespace app\service\system;

use app\repository\system\AdminRepository;
use app\repository\system\RoleRepository;
use app\service\realtime\ForceLogoutService;
use app\service\realtime\PresenceService;
use core\base\Service;
use core\context\RequestContext;
use core\exception\NotFoundException;
use core\exception\ValidationException;
use DI\Attribute\Inject;

/**
 * 在线管理员（spec §6）：「在线」= 存在 WS 连接（在线状态存 Redis，见 PresenceService）。
 *
 * - 列表只含当前管理员数据范围内可见的管理员（AdminRepository::visibleIds），保持在线顺序（最后心跳倒序）。
 * - 强制下线判定（计划设计决定 6）：范围外 404（不泄露存在性）；踢自己、踢持有 is_system 角色的管理员 422。
 *   adminHoldsSystemRole() 是防提权判定，不看角色状态、fail closed；不论操作者是不是超管都不能踢超管。
 */
class OnlineAdminService extends Service
{
    #[Inject]
    protected PresenceService $presence;

    #[Inject]
    protected ForceLogoutService $forceLogout;

    #[Inject]
    protected AdminRepository $adminRepository;

    #[Inject]
    protected RoleRepository $roleRepository;

    /**
     * @return array{list: list<array{admin_id: int, username: string, nickname: string, connections: int, ip: string, ua: string, connected_at: string, last_seen: string}>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getList(int $page, int $limit): array
    {
        $online = $this->presence->onlineAdminIds();
        $visible = $this->adminRepository->visibleIds($online);
        $ids = array_values(array_filter($online, static fn (int $id): bool => in_array($id, $visible, true)));

        $total = count($ids);
        $pageIds = array_slice($ids, ($page - 1) * $limit, $limit);
        $profiles = $this->adminRepository->briefByIds($pageIds);

        $list = [];
        foreach ($pageIds as $id) {
            $state = $this->presence->describe($id);
            if ($state === null || !isset($profiles[$id])) {
                continue;
            }
            $list[] = [
                'admin_id'     => $id,
                'username'     => $profiles[$id]['username'],
                'nickname'     => $profiles[$id]['nickname'],
                'connections'  => $state['connections'],
                'ip'           => $state['ip'],
                'ua'           => $state['ua'],
                'connected_at' => $state['connected_at'],
                'last_seen'    => $state['last_seen'],
            ];
        }

        return [
            'list'       => $list,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $limit,
                'total'        => $total,
                'last_page'    => max(1, (int) ceil($total / $limit)),
            ],
        ];
    }

    /** @return int 被清掉的在线连接数 */
    public function logout(int $adminId): int
    {
        if ($this->adminRepository->visibleIds([$adminId]) === []) {
            throw new NotFoundException();
        }
        if ($adminId === RequestContext::actingUser()) {
            throw new ValidationException(['admin_id' => lang('validation.online_kick_self')]);
        }
        if ($this->roleRepository->adminHoldsSystemRole($adminId)) {
            throw new ValidationException(['admin_id' => lang('validation.online_kick_super')]);
        }

        return $this->forceLogout->kick($adminId);
    }
}
