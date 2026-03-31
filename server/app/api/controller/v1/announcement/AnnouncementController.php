<?php
declare(strict_types=1);

namespace app\api\controller\v1\announcement;

use core\base\Controller;
use app\service\announcement\AnnouncementService;
use think\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '公告', description: '公告列表与详情')]
class AnnouncementController extends Controller
{
    protected AnnouncementService $announcementService;

    #[OA\Get(
        path: '/announcement/list',
        summary: '获取已发布的公告列表',
        tags: ['公告'],
        parameters: [
            new OA\Parameter(name: 'page_no', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'page_size', in: 'query', schema: new OA\Schema(type: 'integer', default: 10)),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedResponse')),
        ]
    )]
    public function list(): Response
    {
        try {
            $params = $this->request->only(['page_no', 'page_size']);
            $result = $this->announcementService->getPublishedList($params);
            return $this->paginate($result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/announcement/detail/{id}',
        summary: '公告详情',
        tags: ['公告'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
    public function detail(int $id): Response
    {
        try {
            $result = $this->announcementService->detail($id);
            if (!$result || (int) ($result['status'] ?? 0) !== 1) {
                return $this->error(lang('business.record_not_found'));
            }
            return $this->success(lang('messages.get_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
