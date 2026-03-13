<?php
declare(strict_types=1);

namespace app\adminapi\controller\v1\system;

use core\base\Controller;
use app\service\system\CronJobService;
use app\adminapi\validate\v1\system\CronJobValidate;
use core\attribute\Permission;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '定时任务', description: '定时任务的管理、手动执行、日志')]
class CronJobController extends Controller
{
    protected CronJobService $service;

    #[Permission('system.cron-job.list')]
    #[OA\Get(
        path: '/system/cron-job',
        summary: '定时任务列表',
        security: [['bearerAuth' => []]],
        tags: ['定时任务'],
        parameters: [
            new OA\Parameter(name: 'keyword', in: 'query', description: '关键词搜索', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', description: '状态(0禁用 1启用)', schema: new OA\Schema(type: 'integer', enum: [0, 1])),
            new OA\Parameter(name: 'page', in: 'query', description: '页码', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', description: '每页数量', schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedResponse'))
        ]
    )]
    public function index()
    {
        $params = $this->getRequestData([
            'keyword' => '',
            'status'  => '',
            'page'    => 1,
            'limit'   => 20,
        ]);
        $result = $this->service->getCronJobList($params);
        return $this->paginate($result);
    }

    #[Permission('system.cron-job.list')]
    #[OA\Get(
        path: '/system/cron-job/{id}',
        summary: '定时任务详情',
        security: [['bearerAuth' => []]],
        tags: ['定时任务'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '任务ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 400, description: '任务不存在', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]
    public function show()
    {
        $id = (int) $this->request->param('id');
        $result = $this->service->getCronJobDetail($id);
        if (!$result) {
            return $this->error(lang('business.task_not_found'));
        }
        return $this->success(lang('messages.get_success'), $result);
    }

    #[Permission('system.cron-job.create')]
    #[OA\Post(
        path: '/system/cron-job',
        summary: '创建定时任务',
        security: [['bearerAuth' => []]],
        tags: ['定时任务'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'command', 'cron_expression'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', description: '任务名称'),
                    new OA\Property(property: 'command', type: 'string', description: '执行命令'),
                    new OA\Property(property: 'cron_expression', type: 'string', description: 'Cron表达式'),
                    new OA\Property(property: 'description', type: 'string', description: '描述'),
                    new OA\Property(property: 'status', type: 'integer', description: '状态(0禁用 1启用)', enum: [0, 1]),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '创建成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 400, description: '验证失败', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]
    public function store()
    {
        $data = $this->request->post();
        $this->validate($data, CronJobValidate::class, [], 'create');
        $data['created_by'] = $this->getUserId();
        $result = $this->service->createCronJob($data);
        return $this->success(lang('messages.create_success'), $result);
    }

    #[Permission('system.cron-job.update')]
    #[OA\Put(
        path: '/system/cron-job/{id}',
        summary: '更新定时任务',
        security: [['bearerAuth' => []]],
        tags: ['定时任务'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '任务ID', schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'name', type: 'string', description: '任务名称'),
                new OA\Property(property: 'command', type: 'string', description: '执行命令'),
                new OA\Property(property: 'cron_expression', type: 'string', description: 'Cron表达式'),
                new OA\Property(property: 'description', type: 'string', description: '描述'),
                new OA\Property(property: 'status', type: 'integer', description: '状态(0禁用 1启用)', enum: [0, 1]),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: '更新成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function update()
    {
        $id = (int) $this->request->param('id');
        $data = $this->request->post();
        $this->validate($data, CronJobValidate::class, [], 'update');
        $this->service->updateCronJob($id, $data);
        return $this->success(lang('messages.update_success'));
    }

    #[Permission('system.cron-job.delete')]
    #[OA\Delete(
        path: '/system/cron-job/{id}',
        summary: '删除定时任务',
        security: [['bearerAuth' => []]],
        tags: ['定时任务'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '任务ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '删除成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function delete()
    {
        $id = (int) $this->request->param('id');
        $this->service->deleteCronJob($id);
        return $this->success(lang('messages.delete_success'));
    }

    #[Permission('system.cron-job.update')]
    #[OA\Put(
        path: '/system/cron-job/{id}/status',
        summary: '更新定时任务状态',
        security: [['bearerAuth' => []]],
        tags: ['定时任务'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '任务ID', schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'status', type: 'integer', description: '状态(0禁用 1启用)', enum: [0, 1]),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: '更新成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function status()
    {
        $id = (int) $this->request->param('id');
        $status = (int) $this->request->post('status');
        $this->service->updateStatus($id, $status);
        return $this->success(lang('messages.update_success'));
    }

    #[Permission('system.cron-job.run')]
    #[OA\Post(
        path: '/system/cron-job/{id}/run',
        summary: '手动执行定时任务',
        security: [['bearerAuth' => []]],
        tags: ['定时任务'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '任务ID', schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: '执行完成', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function run()
    {
        $id = (int) $this->request->param('id');
        $result = $this->service->runCronJob($id);
        return $this->success(lang('messages.execute_complete'), $result);
    }

    #[Permission('system.cron-job.list')]
    #[OA\Get(
        path: '/system/cron-job/{id}/logs',
        summary: '定时任务执行日志',
        security: [['bearerAuth' => []]],
        tags: ['定时任务'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '任务ID', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', description: '页码', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', description: '每页数量', schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedResponse'))
        ]
    )]
    public function logs()
    {
        $id = (int) $this->request->param('id');
        $params = $this->getRequestData([
            'page'  => 1,
            'limit' => 20,
        ]);
        $result = $this->service->getCronJobLogs($id, $params);
        return $this->paginate($result);
    }

    #[Permission('system.cron-job.clear')]
    #[OA\Post(
        path: '/system/cron-job/{id}/clear-logs',
        summary: '清理定时任务日志',
        security: [['bearerAuth' => []]],
        tags: ['定时任务'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: '任务ID', schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'keep_days', type: 'integer', description: '保留天数', example: 30),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: '清理成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function clearLogs()
    {
        $id = (int) $this->request->param('id');
        $keepDays = (int) ($this->request->post('keep_days') ?: 30);
        $count = $this->service->clearLogs($id, $keepDays);
        return $this->success(sprintf(lang('messages.log_clean_count'), $count));
    }
}
