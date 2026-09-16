<?php

declare(strict_types=1);

namespace app\service\user;

use app\repository\user\BalanceLogRepository;
use app\repository\user\UserRepository;
use core\base\Service;
use core\exception\NotFoundException;
use core\exception\ValidationException;
use DI\Attribute\Inject;

/**
 * 余额变动的唯一写入口（spec §5.1）。M5b 的支付回调入账同样走 change()，不另开写路径。
 *
 * 为什么不用 Repository::inc()/dec()（spec §5.2）：
 *   1. 流水要写 before_balance / after_balance。inc/dec 是一条 UPDATE，拿不到前后值；先 SELECT
 *      再 inc，两句之间没有锁，并发下两个请求读到同一个 before，两条流水的前后值都是错的
 *      （丢失更新在账面上就表现为「流水接不上」）。
 *   2. inc/dec 挡不住扣成负数：balance 是 decimal(10,2)，不是 unsigned，balance - 100 会写成负值。
 *      即便补 WHERE balance >= 100，也只能知道「没扣成」，仍拿不到当时的余额去写流水。
 * 做法：runInTransaction 内先 findForUpdate() 行锁读出当前值（SELECT ... FOR UPDATE，行锁封在
 * Repository，Service 不碰 Builder，也不直接调用数据库门面），校验结果不为负，再更新余额并写流水。
 * 代价是同一用户的变动排队——这正是要的：M5b 的回调可能并发重入同一个用户。
 *
 * 金额一律换算成「分」做整数加减：decimal(10,2) 取回来是字符串，用浮点数直接相加会出现
 * 0.1 + 0.2 = 0.30000000000000004 这类误差，累积几次就和流水对不上。
 *
 * 容器单例，无实例态；事务内不注册 afterCommit（资金事务的回调抛异常会在数据已提交之后抛出）。
 */
class BalanceService extends Service
{
    #[Inject]
    protected UserRepository $userRepository;

    #[Inject]
    protected BalanceLogRepository $balanceLogRepository;

    /**
     * @param float       $amount     正数入账、负数扣减；结果为负抛 422（errors.amount）。为 0 时照常写一条零额流水，
     *                                是否允许 0 由调用方把关（管理端控制器用 not_in:0 拒绝）
     * @param int         $type       BalanceLog::TYPE_*（1 充值 2 消费 3 退款 4 后台调整）
     * @param string      $source     来源标识：管理端调整 admin_adjust，M5b 充值 recharge
     * @param int|null    $operatorId 操作管理员 id；用户自己触发的变动为 null
     * @return array{before: float, after: float}
     */
    public function change(int $userId, float $amount, int $type, string $source, string $remark = '', ?int $operatorId = null): array
    {
        return $this->runInTransaction(function () use ($userId, $amount, $type, $source, $remark, $operatorId): array {
            // 行锁读：本事务提交前，同一用户的其它变动在这里排队，读到的值就是写入时的值
            $user = $this->userRepository->findForUpdate($userId);
            if ($user === null) {
                throw new NotFoundException();
            }

            $beforeCents = $this->toCents($user['balance'] ?? 0);
            $deltaCents = $this->toCents($amount);
            $afterCents = $beforeCents + $deltaCents;
            if ($afterCents < 0) {
                throw new ValidationException(['amount' => lang('validation.balance_not_enough')]);
            }

            $before = $this->toAmount($beforeCents);
            $after = $this->toAmount($afterCents);
            $this->userRepository->update($userId, ['balance' => $after]);
            $this->balanceLogRepository->create([
                'user_id'        => $userId,
                'amount'         => $this->toAmount($deltaCents),
                'before_balance' => $before,
                'after_balance'  => $after,
                'type'           => $type,
                'source'         => $source,
                'remark'         => $remark,
                'operator_id'    => $operatorId,
                'created_at'     => date('Y-m-d H:i:s'),
            ]);

            return ['before' => (float) $before, 'after' => (float) $after];
        });
    }

    /** 元 → 分。入参最多两位小数，round 一次即可绕开浮点误差。 */
    private function toCents(float|int|string $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    /** 分 → 元，两位小数的字符串，直接写进 decimal(10,2) 列。 */
    private function toAmount(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
