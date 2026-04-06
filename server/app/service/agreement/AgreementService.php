<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\service\agreement;

use app\repository\agreement\AgreementRepository;
use core\base\Service;
use core\exception\BusinessException;

class AgreementService extends Service
{
    protected AgreementRepository $agreementRepository;

    /**
     * 获取协议列表（管理端）
     */
    public function getList(array $params): array
    {
        [$page, $limit] = $this->extractPagination($params);
        return $this->agreementRepository->getSearchList($params, $page, $limit);
    }

    /**
     * 协议详情
     */
    public function detail(int $id): ?array
    {
        return $this->agreementRepository->find($id);
    }

    /**
     * 根据标识码获取协议（C端）
     */
    public function findByCode(string $code): ?array
    {
        return $this->agreementRepository->findByCode($code);
    }

    /**
     * 创建协议
     */
    public function create(array $data): array
    {
        // 检查 code 唯一性
        $existing = $this->agreementRepository->findByCode($data['code']);
        if ($existing) {
            throw new BusinessException('协议标识码已存在');
        }

        return $this->agreementRepository->create($data);
    }

    /**
     * 更新协议
     */
    public function update(int $id, array $data): bool
    {
        $this->findOrFail($this->agreementRepository, $id);
        return $this->agreementRepository->update($id, $data);
    }

    /**
     * 删除协议
     */
    public function delete(int $id): bool
    {
        return $this->agreementRepository->delete($id);
    }
}
