<?php
declare(strict_types=1);

namespace app\adminapi\controller\v1\message;

use core\base\Controller;
use app\service\message\MessageService;
use core\attribute\Permission;
use think\Response;

class MessageLogController extends Controller
{
    protected MessageService $service;

    /**
     * 发送记录列表
     */
    #[Permission('message.log.list')]
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
