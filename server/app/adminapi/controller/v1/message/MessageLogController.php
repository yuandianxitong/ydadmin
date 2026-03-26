<?php
declare(strict_types=1);

namespace app\adminapi\controller\v1\message;

use core\base\Controller;
use app\service\message\MessageService;
use core\attribute\Permission;
use think\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '消息日志', description: '消息发送记录查询')]
class MessageLogController extends Controller
{
    protected MessageService $service;

    /**
     * 发送记录列表
     */
    #[Permission('system.message.log.list')]
    #[OA\Get(
        path: '/message/log',
        summary: '获取消息发送记录列表',
        security: [['bearerAuth' => []]],
        tags: ['消息日志'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', description: '页码', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', description: '每页数量', schema: new OA\Schema(type: 'integer', default: 20)),
            new OA\Parameter(name: 'channel', in: 'query', description: '发送渠道', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'template_code', in: 'query', description: '模板编码', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', description: '发送状态', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'receiver', in: 'query', description: '接收者', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedResponse'))
        ]
    )]
    public function index(): Response
    {
        $params = $this->getRequestData([
            'channel'       => '',
            'template_code' => '',
            'status'        => '',
            'receiver'      => '',
            'page'          => 1,
            'limit'         => 20,
        ]);
        $result = $this->service->getLogList($params);
        return $this->paginate($result);
    }
}
