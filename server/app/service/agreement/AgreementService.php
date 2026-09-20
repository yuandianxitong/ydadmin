<?php

declare(strict_types=1);

namespace app\service\agreement;

use app\repository\agreement\AgreementRepository;
use core\base\Service;
use core\exception\NotFoundException;
use core\exception\ValidationException;
use DI\Attribute\Inject;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * 协议。
 *
 * 只调 Repository：查询条件在 AgreementRepository。
 * 写操作一律包 runInTransaction()。
 *
 * code：新增时先查重（无软删，query() 即全表），重复 → 422 errors.code；并发插入撞唯一索引时转成同一个 422。
 * 编辑时忽略 code。
 */
class AgreementService extends Service
{
    /** @var list<string> */
    private const CREATE_FIELDS = ['title', 'code', 'content', 'status'];

    /** @var list<string> */
    private const UPDATE_FIELDS = ['title', 'content', 'status'];

    #[Inject]
    protected AgreementRepository $agreementRepository;

    /**
     * 管理端列表：keyword、status。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getAgreementList(array $params, int $page, int $limit): array
    {
        return $this->agreementRepository->getAgreementList($params, $page, $limit);
    }

    /** @return array<string, mixed> */
    public function getAgreementDetail(int $id): array
    {
        return $this->findAgreementOrFail($id);
    }

    /**
     * C 端按编码读取：只认 status=ENABLED。空码 / 未知 / 禁用 → 404。
     *
     * @return array<string, mixed>
     */
    public function getPublishedByCode(string $code): array
    {
        return $this->agreementRepository->findPublishedByCode($code)
            ?? throw new NotFoundException(lang('agreement.not_found'));
    }

    /**
     * @param array<string, mixed> $data 控制器 validate() 的返回值（字段白名单）
     * @return array<string, mixed>
     */
    public function createAgreement(array $data): array
    {
        $code = (string) $data['code'];
        if ($this->agreementRepository->existsByCode($code)) {
            throw self::codeTaken();
        }

        $row = array_intersect_key($data, array_flip(self::CREATE_FIELDS));

        try {
            return $this->runInTransaction(fn (): array => $this->agreementRepository->create($row));
        } catch (UniqueConstraintViolationException) {
            throw self::codeTaken();
        }
    }

    /**
     * 局部更新：只写 title/content/status，值为 null 的丢弃。传了 code 也丢掉。
     *
     * @param array<string, mixed> $data 控制器 validate() 的返回值（字段白名单）
     */
    public function updateAgreement(int $id, array $data): void
    {
        $this->findAgreementOrFail($id);
        $update = array_filter(
            array_intersect_key($data, array_flip(self::UPDATE_FIELDS)),
            static fn ($value) => $value !== null
        );
        if ($update === []) {
            return;
        }

        $this->runInTransaction(function () use ($id, $update): void {
            $this->agreementRepository->update($id, $update);
        });
    }

    public function deleteAgreement(int $id): void
    {
        $this->findAgreementOrFail($id);

        $this->runInTransaction(function () use ($id): void {
            $this->agreementRepository->delete($id);
        });
    }

    /**
     * 批量删除：同一事务内逐条删除，任一 id 不存在则整体回滚（与 M1 的角色、字典批量删除同语义）。
     * 重复 id 先去重。生成器残留，无路由。
     *
     * @param list<int> $ids
     */
    public function batchDelete(array $ids): void
    {
        $this->runInTransaction(function () use ($ids): void {
            foreach (array_values(array_unique($ids)) as $id) {
                $this->deleteAgreement($id);
            }
        });
    }

    /** @return array<string, mixed> */
    private function findAgreementOrFail(int $id): array
    {
        return $this->agreementRepository->find($id)
            ?? throw new NotFoundException(lang('agreement.not_found'));
    }

    private static function codeTaken(): ValidationException
    {
        return new ValidationException(['code' => lang('agreement.code_exists')]);
    }
}
