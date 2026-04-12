<?php
declare(strict_types=1);
namespace app\adminapi\controller\v1\user;

use app\adminapi\validate\v1\user\UserManageValidate;
use app\service\user\UserManageService;
use core\attribute\Permission;
use core\base\Controller;
use think\Response;

class UserManageController extends Controller
{
    protected UserManageService $userManageService;

    #[Permission('user.list')]
    public function list(): Response
    {
        $params = $this->getRequestData();
        $result = $this->userManageService->getUserList($params);
        return $this->success('ok', $result);
    }

    #[Permission('user.detail')]
    public function detail(): Response
    {
        $id = (int) $this->request->param('id');
        $result = $this->userManageService->getUserDetail($id);
        if (!$result) {
            return $this->error('用户不存在');
        }
        return $this->success('ok', $result);
    }

    #[Permission('user.adjust-balance')]
    public function adjustBalance(): Response
    {
        $data = $this->request->post();
        $this->validate($data, UserManageValidate::class, [], false, 'adjustBalance');
        $operatorId = $this->getUserId();
        $this->userManageService->adjustBalance(
            (int) $data['user_id'], (float) $data['amount'],
            $data['remark'] ?? '',
            \app\model\user\BalanceLog::TYPE_ADMIN_ADJUST,
            'admin_adjust', $operatorId
        );
        return $this->success('调整成功');
    }

    #[Permission('user.adjust-points')]
    public function adjustPoints(): Response
    {
        $data = $this->request->post();
        $this->validate($data, UserManageValidate::class, [], false, 'adjustPoints');
        $operatorId = $this->getUserId();
        $this->userManageService->adjustPoints(
            (int) $data['user_id'], (int) $data['points'],
            $data['remark'] ?? '',
            \app\model\user\PointsLog::TYPE_ADMIN_ADJUST,
            'admin_adjust', $operatorId
        );
        return $this->success('调整成功');
    }

    #[Permission('user.status')]
    public function updateStatus(): Response
    {
        $id = (int) $this->request->param('id');
        $data = $this->request->post();
        $this->validate($data, UserManageValidate::class, [], false, 'status');
        $this->userManageService->updateStatus($id, (int) $data['status']);
        return $this->success('操作成功');
    }

    #[Permission('user.balance-logs')]
    public function balanceLogs(): Response
    {
        $params = $this->getRequestData();
        $result = $this->userManageService->getBalanceLogs($params);
        return $this->success('ok', $result);
    }

    #[Permission('user.points-logs')]
    public function pointsLogs(): Response
    {
        $params = $this->getRequestData();
        $result = $this->userManageService->getPointsLogs($params);
        return $this->success('ok', $result);
    }
}
