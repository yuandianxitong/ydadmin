<?php

declare(strict_types=1);

namespace app\adminapi\controller\realtime;

use app\service\realtime\WsTicketService;
use core\base\Controller;
use core\http\ClientIp;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/**
 * WebSocket 握手票据（spec §6）。
 *
 *   POST /adminapi/ws/ticket   store   PermissionSkip（登录即可）
 *
 * 不记操作日志（config/admin_log.php 的 skip）：前端每次重连都会取票据，不改变任何业务数据。
 */
class WsTicketController extends Controller
{
    #[Inject]
    protected WsTicketService $wsTicketService;

    #[PermissionSkip]
    public function store(Request $request): Response
    {
        return $this->success($this->wsTicketService->grant(
            (int) $request->userId,
            (int) ($request->tokenVer ?? 0),
            (string) ($request->tokenJti ?? ''),
            ClientIp::resolve($request),
            (string) $request->header('user-agent', ''),
        ), lang('messages.get_success'));
    }
}
