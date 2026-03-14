<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\controller\v1\agreement;

use core\base\Controller;
use core\attribute\Permission;
use app\service\agreement\AgreementService;
use app\adminapi\validate\v1\agreement\AgreementValidate;
use think\Response;

class AgreementController extends Controller
{
    protected AgreementService $agreementService;

    /**
     * 协议列表
     */
    #[Permission('agreement.list')]
    public function list(): Response
    {
        $params = $this->getRequestData([
            'page_no'   => 1,
            'page_size' => 20,
            'status'    => '',
            'keyword'   => '',
        ]);
        $result = $this->agreementService->getList($params);
        return $this->paginate($result);
    }

    /**
     * 协议详情
     */
    #[Permission('agreement.detail')]
    public function detail(): Response
    {
        $id = (int) $this->request->param('id');
        $result = $this->agreementService->detail($id);
        if (!$result) {
            return $this->error(lang('business.record_not_found'));
        }
        return $this->success(lang('messages.get_success'), $result);
    }

    /**
     * 创建协议
     */
    #[Permission('agreement.create')]
    public function create(): Response
    {
        $data = $this->request->only(['title', 'code', 'content', 'status']);
        $this->validate($data, AgreementValidate::class, [], false, 'create');
        $result = $this->agreementService->create($data);
        return $this->success(lang('messages.create_success'), $result);
    }

    /**
     * 更新协议
     */
    #[Permission('agreement.update')]
    public function update(): Response
    {
        $id = (int) $this->request->param('id');
        $data = $this->request->only(['title', 'content', 'status']);
        $this->validate($data, AgreementValidate::class, [], false, 'update');
        $this->agreementService->update($id, $data);
        return $this->success(lang('messages.update_success'));
    }

    /**
     * 删除协议
     */
    #[Permission('agreement.delete')]
    public function delete(): Response
    {
        $id = (int) $this->request->param('id');
        $this->agreementService->delete($id);
        return $this->success(lang('messages.delete_success'));
    }
}
