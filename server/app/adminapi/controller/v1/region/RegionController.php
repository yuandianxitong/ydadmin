<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\controller\v1\region;

use core\base\Controller;
use core\attribute\Permission;
use app\service\region\RegionService;
use app\adminapi\validate\v1\region\RegionValidate;
use think\Response;

class RegionController extends Controller
{
    protected RegionService $regionService;

    /**
     * 地区列表
     */
    #[Permission('region.list')]
    public function list(): Response
    {
        $params = $this->getRequestData([
            'page_no'   => 1,
            'page_size' => 20,
            'parent_id' => '',
            'level'     => '',
            'keyword'   => '',
        ]);
        $result = $this->regionService->getList($params);
        return $this->paginate($result);
    }

    /**
     * 地区树（级联选择器）
     */
    public function tree(): Response
    {
        $result = $this->regionService->getTree();
        return $this->success(lang('messages.get_success'), $result);
    }

    /**
     * 地区详情
     */
    #[Permission('region.detail')]
    public function detail(): Response
    {
        $id = (int) $this->request->param('id');
        $result = $this->regionService->detail($id);
        if (!$result) {
            return $this->error(lang('business.record_not_found'));
        }
        return $this->success(lang('messages.get_success'), $result);
    }

    /**
     * 创建地区
     */
    #[Permission('region.create')]
    public function create(): Response
    {
        $data = $this->request->only(['parent_id', 'name', 'code', 'level', 'sort', 'status']);
        $this->validate($data, RegionValidate::class, [], false, 'create');
        $result = $this->regionService->create($data);
        return $this->success(lang('messages.create_success'), $result);
    }

    /**
     * 更新地区
     */
    #[Permission('region.update')]
    public function update(): Response
    {
        $id = (int) $this->request->param('id');
        $data = $this->request->only(['parent_id', 'name', 'code', 'level', 'sort', 'status']);
        $this->validate($data, RegionValidate::class, [], false, 'update');
        $this->regionService->update($id, $data);
        return $this->success(lang('messages.update_success'));
    }

    /**
     * 删除地区
     */
    #[Permission('region.delete')]
    public function delete(): Response
    {
        $id = (int) $this->request->param('id');
        $this->regionService->delete($id);
        return $this->success(lang('messages.delete_success'));
    }
}
