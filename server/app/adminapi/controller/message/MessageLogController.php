<?php

declare(strict_types=1);

namespace app\adminapi\controller\message;

use app\service\message\MessageLogService;
use core\base\Controller;
use core\permission\Permission;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/**
 * 消息日志（M6b spec §4.2）。
 *
 * 端点：
 *   GET /adminapi/message/log   index   system.message.log.list
 *
 * 筛选：channel（sms / wechat_official / wechat_mini / site）、status（0 待发 1 成功 2 失败）、
 * receiver（对遮蔽后的值 LIKE）、template_code（精确匹配）；id 倒序。
 */
class MessageLogController extends Controller
{
    #[Inject]
    protected MessageLogService $messageLogService;

    #[Permission('system.message.log.list')]
    public function index(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request);
        $params = $this->validate((array) $request->get(), $this->indexRules(), $this->messages());

        return $this->paginate($this->messageLogService->getList($params, $page, $limit));
    }

    /** @return array<string, string> */
    private function indexRules(): array
    {
        return [
            'channel'       => 'nullable|string|in:sms,wechat_official,wechat_mini,site',
            'status'        => 'nullable|in:0,1,2',
            'receiver'      => 'nullable|string|max:64',
            'template_code' => 'nullable|string|max:50',
        ];
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'status.in' => 'validation.status_invalid',
        ];
    }
}
