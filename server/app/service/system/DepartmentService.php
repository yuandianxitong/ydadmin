<?php
declare(strict_types=1);

namespace app\service\system;

use core\base\Service;
use core\helper\ArrayHelper;
use app\repository\system\DepartmentRepository;
use think\facade\Db;

class DepartmentService extends Service
{
    protected DepartmentRepository $repo;

    /**
     * 获取部门树形列表
     */
    public function getDepartmentTree(array $params): array
    {
        $list = $this->repo->getTree($params);
        return ArrayHelper::toTree($list);
    }

    /**
     * 获取部门详情
     */
    public function getDepartmentDetail(int $id): ?array
    {
        return $this->repo->find($id);
    }

    /**
     * 获取部门选项（下拉）
     */
    public function getDepartmentOptions(): array
    {
        $list = $this->repo->getAllEnabled();
        return ArrayHelper::toTree($list);
    }

    /**
     * 创建部门
     */
    public function createDepartment(array $data): array
    {
        if (!empty($data['code']) && $this->repo->existsCode($data['code'])) {
            $this->throwBusinessException(lang('business.dept_code_exists'));
        }

        if (!empty($data['parent_id'])) {
            $parent = $this->repo->find((int) $data['parent_id']);
            if (!$parent) {
                $this->throwBusinessException(lang('business.parent_dept_not_found'));
            }
        }

        return $this->repo->create($data);
    }

    /**
     * 更新部门
     */
    public function updateDepartment(int $id, array $data): bool
    {
        $dept = $this->repo->find($id);
        if (!$dept) {
            $this->throwBusinessException(lang('business.dept_not_found'));
        }

        if (!empty($data['code']) && $this->repo->existsCode($data['code'], $id)) {
            $this->throwBusinessException(lang('business.dept_code_exists'));
        }

        // 不能将自己设为上级
        if (!empty($data['parent_id']) && (int) $data['parent_id'] === $id) {
            $this->throwBusinessException(lang('business.dept_parent_not_self'));
        }

        // 不能将自己的子部门设为上级
        if (!empty($data['parent_id'])) {
            $childIds = $this->repo->getChildIds($id);
            if (in_array((int) $data['parent_id'], $childIds)) {
                $this->throwBusinessException(lang('business.dept_parent_not_child'));
            }
        }

        return $this->repo->update($id, $data);
    }

    /**
     * 删除部门
     */
    public function deleteDepartment(int $id): bool
    {
        $dept = $this->repo->find($id);
        if (!$dept) {
            $this->throwBusinessException(lang('business.dept_not_found'));
        }

        // 检查是否有子部门
        $childIds = $this->repo->getChildIds($id);
        if (!empty($childIds)) {
            $this->throwBusinessException(lang('business.dept_has_children'));
        }

        // 检查是否有关联管理员
        if ($this->repo->hasAdmins($id)) {
            $this->throwBusinessException(lang('business.dept_has_admins'));
        }

        return $this->repo->delete($id);
    }

    /**
     * 更新部门状态
     */
    public function updateStatus(int $id, int $status): bool
    {
        $dept = $this->repo->find($id);
        if (!$dept) {
            $this->throwBusinessException(lang('business.dept_not_found'));
        }

        return $this->repo->update($id, ['status' => $status]);
    }

}
