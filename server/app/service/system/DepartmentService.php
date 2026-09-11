<?php

declare(strict_types=1);

namespace app\service\system;

use app\repository\system\AdminRepository;
use app\repository\system\DepartmentRepository;
use core\base\Service;
use core\context\RequestContext;
use core\datascope\DataScopeResolver;
use core\exception\BusinessException;
use DI\Attribute\Inject;

/**
 * 部门（契约 §2.5）；增改删经 afterCommit 清全部数据范围缓存。
 */
class DepartmentService extends Service
{
    #[Inject]
    protected DepartmentRepository $departmentRepository;

    #[Inject]
    protected AdminRepository $adminRepository;

    #[Inject]
    protected DataScopeResolver $dataScopeResolver;

    /**
     * 部门树，支持 keyword（name/code 模糊）/status 过滤。
     *
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     */
    public function getDepartmentTree(array $params = []): array
    {
        $rows = $this->departmentRepository->getTree($params);

        $nodes = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $nodes[$id] = [
                'id'         => $id,
                'parent_id'  => (int) $row['parent_id'],
                'name'       => $row['name'],
                'code'       => $row['code'] ?? '',
                'leader'     => $row['leader'] ?? '',
                'phone'      => $row['phone'] ?? '',
                'email'      => $row['email'] ?? '',
                'status'     => (int) $row['status'],
                'sort'       => (int) $row['sort'],
                'remark'     => $row['remark'] ?? '',
                'created_at' => $row['created_at'] ?? null,
                'updated_at' => $row['updated_at'] ?? null,
                'children'   => [],
            ];
        }

        return $this->assembleTree($nodes);
    }

    /**
     * 部门详情。不存在时抛 business.dept_not_found（code 400，契约如此）。
     *
     * @return array<string, mixed>
     */
    public function getDepartmentDetail(int $id): array
    {
        return $this->departmentRepository->find($id) ?? throw new BusinessException(lang('business.dept_not_found'));
    }

    /**
     * 部门选项树（表单选择用，仅启用部门，节点为 id/parent_id/name/code/children，
     * 供前端 el-tree-select 消费——同 DeptForm.vue/AdminForm.vue 实际用法，参考 §7.2）。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDepartmentOptions(): array
    {
        $rows = $this->departmentRepository->getAllEnabled();

        $nodes = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $nodes[$id] = [
                'id'        => $id,
                'parent_id' => (int) $row['parent_id'],
                'name'      => $row['name'],
                'code'      => $row['code'] ?? '',
                'children'  => [],
            ];
        }

        return $this->assembleTree($nodes);
    }

    /**
     * 创建部门。code 非空时唯一（含软删行）；parent_id>0 时校验父部门存在。
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createDepartment(array $data): array
    {
        $code = trim((string) ($data['code'] ?? ''));
        if ($code !== '' && $this->departmentRepository->existsCode($code)) {
            throw new BusinessException(lang('business.dept_code_exists'));
        }

        $parentId = (int) ($data['parent_id'] ?? 0);
        if ($parentId > 0 && $this->departmentRepository->find($parentId) === null) {
            throw new BusinessException(lang('business.parent_dept_not_found'));
        }

        $departmentData = [
            'parent_id'  => $parentId,
            'name'       => $data['name'],
            'code'       => $code !== '' ? $code : null,
            'leader'     => $data['leader'] ?? '',
            'phone'      => $data['phone'] ?? '',
            'email'      => $data['email'] ?? '',
            'status'     => $data['status'] ?? 1,
            'sort'       => $data['sort'] ?? 0,
            'remark'     => $data['remark'] ?? '',
            'created_by' => RequestContext::actingUser() ?: null,
        ];

        return $this->runInTransaction(function () use ($departmentData): array {
            $department = $this->departmentRepository->create($departmentData);
            $this->afterCommit(fn () => $this->dataScopeResolver->forgetAll());

            return $department;
        });
    }

    /**
     * 更新部门。环检测（逐字对齐参考 §4.3）：
     *   - 不能将自己设为上级 → business.dept_parent_not_self
     *   - 不能将自己的子部门设为上级 → business.dept_parent_not_child
     *
     * @param array<string, mixed> $data
     */
    public function updateDepartment(int $id, array $data): void
    {
        $department = $this->departmentRepository->find($id);
        if ($department === null) {
            throw new BusinessException(lang('business.dept_not_found'));
        }

        // 不能将自己设为上级
        if (!empty($data['parent_id']) && (int) $data['parent_id'] === $id) {
            throw new BusinessException(lang('business.dept_parent_not_self'));
        }

        // 不能将自己的子部门设为上级
        if (!empty($data['parent_id'])) {
            $childIds = $this->departmentRepository->getChildIds($id);
            if (in_array((int) $data['parent_id'], $childIds, true)) {
                throw new BusinessException(lang('business.dept_parent_not_child'));
            }
        }

        if (isset($data['code']) && trim((string) $data['code']) !== ''
            && $this->departmentRepository->existsCode(trim((string) $data['code']), $id)) {
            throw new BusinessException(lang('business.dept_code_exists'));
        }

        if (!empty($data['parent_id'])) {
            $parentId = (int) $data['parent_id'];
            if ($this->departmentRepository->find($parentId) === null) {
                throw new BusinessException(lang('business.parent_dept_not_found'));
            }
        }

        $updateData = array_filter([
            'parent_id'  => isset($data['parent_id']) ? (int) $data['parent_id'] : null,
            'name'       => $data['name'] ?? null,
            'code'       => $data['code'] ?? null,
            'leader'     => $data['leader'] ?? null,
            'phone'      => $data['phone'] ?? null,
            'email'      => $data['email'] ?? null,
            'status'     => $data['status'] ?? null,
            'sort'       => $data['sort'] ?? null,
            'remark'     => $data['remark'] ?? null,
            'updated_by' => RequestContext::actingUser() ?: null,
        ], static fn ($value) => $value !== null);

        $this->runInTransaction(function () use ($id, $updateData): void {
            $this->departmentRepository->update($id, $updateData);
            $this->afterCommit(fn () => $this->dataScopeResolver->forgetAll());
        });
    }

    /**
     * 删除部门（软删）。删除保护（逐字对齐参考 §4.3）：
     *   - 有子部门 → business.dept_has_children
     *   - 有管理员（AdminRepository::existsByDepartment）→ business.dept_has_admins
     */
    public function deleteDepartment(int $id): void
    {
        $department = $this->departmentRepository->find($id);
        if ($department === null) {
            throw new BusinessException(lang('business.dept_not_found'));
        }

        $childIds = $this->departmentRepository->getChildIds($id);
        if ($childIds !== []) {
            throw new BusinessException(lang('business.dept_has_children'));
        }

        if ($this->adminRepository->existsByDepartment($id)) {
            throw new BusinessException(lang('business.dept_has_admins'));
        }

        $this->runInTransaction(function () use ($id): void {
            $this->departmentRepository->delete($id);
            $this->afterCommit(fn () => $this->dataScopeResolver->forgetAll());
        });
    }

    /** 更新部门状态（不级联子部门，单行状态翻转；不影响数据范围，不清缓存）。 */
    public function updateStatus(int $id, int $status): void
    {
        $department = $this->departmentRepository->find($id);
        if ($department === null) {
            throw new BusinessException(lang('business.dept_not_found'));
        }

        $this->departmentRepository->update($id, ['status' => $status]);
    }

    /**
     * 扁平节点表（以 id 为 key，值含 'parent_id'/'children'=[]）→ 树形。
     *
     * O(n) 单次遍历 + PHP 引用构建（同 MenuRepository::buildTree 手法）：父节点缺失
     * （非法/已被过滤掉的 parent_id）时降级为根节点，不抛异常，语义与
     * MenuRepository::buildTree 一致。
     *
     * @param array<int, array<string, mixed>> $nodes
     * @return array<int, array<string, mixed>>
     */
    private function assembleTree(array $nodes): array
    {
        $tree = [];
        foreach ($nodes as $id => &$node) {
            $pid = $node['parent_id'];
            if ($pid !== 0 && isset($nodes[$pid])) {
                $nodes[$pid]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }
        unset($node);

        return $tree;
    }
}
