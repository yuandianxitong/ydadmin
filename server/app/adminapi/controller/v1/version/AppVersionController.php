<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\controller\v1\version;

use core\base\Controller;
use core\attribute\Permission;
use app\service\version\AppVersionService;
use app\adminapi\validate\v1\version\AppVersionValidate;
use think\Response;

class AppVersionController extends Controller
{
    protected AppVersionService $appVersionService;

    /**
     * 版本列表
     */
    #[Permission('version.list')]
    public function list(): Response
    {
        $params = $this->getRequestData([
            'page_no'   => 1,
            'page_size' => 20,
            'platform'  => '',
            'status'    => '',
        ]);
        $result = $this->appVersionService->getList($params);
        return $this->paginate($result);
    }

    /**
     * 版本详情
     */
    #[Permission('version.detail')]
    public function detail(): Response
    {
        $id = (int) $this->request->param('id');
        $result = $this->appVersionService->detail($id);
        if (!$result) {
            return $this->error(lang('business.record_not_found'));
        }
        return $this->success(lang('messages.get_success'), $result);
    }

    /**
     * 创建版本
     */
    #[Permission('version.create')]
    public function create(): Response
    {
        $data = $this->request->only([
            'platform', 'version', 'version_code',
            'download_url', 'description', 'force_update', 'status',
        ]);
        $this->validate($data, AppVersionValidate::class, [], false, 'create');
        $result = $this->appVersionService->create($data);
        return $this->success(lang('messages.create_success'), $result);
    }

    /**
     * 更新版本
     */
    #[Permission('version.update')]
    public function update(): Response
    {
        $id = (int) $this->request->param('id');
        $data = $this->request->only([
            'platform', 'version', 'version_code',
            'download_url', 'description', 'force_update', 'status',
        ]);
        $this->validate($data, AppVersionValidate::class, [], false, 'update');
        $this->appVersionService->update($id, $data);
        return $this->success(lang('messages.update_success'));
    }

    /**
     * 删除版本
     */
    #[Permission('version.delete')]
    public function delete(): Response
    {
        $id = (int) $this->request->param('id');
        $this->appVersionService->delete($id);
        return $this->success(lang('messages.delete_success'));
    }
}
