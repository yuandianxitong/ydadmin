<?php

declare(strict_types=1);

namespace app\service\dataimport\handler;

use app\repository\user\UserRepository;
use core\exception\BusinessException;
use DI\Attribute\Inject;

/**
 * 会员 CSV 行写入。只经 UserRepository::create；重复手机号（含软删）本行失败。
 */
class UserImportHandler
{
    #[Inject]
    protected UserRepository $userRepository;

    /** @param array<string, mixed> $row */
    public function handle(array $row): void
    {
        $mobile = trim((string) ($row['mobile'] ?? ''));
        if (preg_match('/^1[3-9]\d{9}$/', $mobile) !== 1) {
            throw new BusinessException(lang('dataimport.invalid_mobile'));
        }
        if ($this->userRepository->mobileTakenIncludingTrashed($mobile)) {
            throw new BusinessException(lang('dataimport.mobile_taken'));
        }

        $password = trim((string) ($row['password'] ?? ''));
        $hashed = null;
        if ($password !== '') {
            $length = strlen($password);
            if ($length < 6 || $length > 20) {
                throw new BusinessException(lang('dataimport.invalid_password'));
            }
            $hashed = password_hash($password, PASSWORD_DEFAULT);
        }

        $nickname = trim((string) ($row['nickname'] ?? ''));
        if ($nickname === '') {
            $nickname = '用户' . substr($mobile, -4);
        }

        // 列宽超了会在 INSERT 处抛底层异常（那条消息带着绑定值，只能记进日志），这里先按业务错误挡掉
        if (mb_strlen($nickname) > 50) {
            throw new BusinessException(lang('dataimport.nickname_too_long'));
        }

        $email = trim((string) ($row['email'] ?? ''));
        if ($email !== '' && (mb_strlen($email) > 100 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            throw new BusinessException(lang('dataimport.invalid_email'));
        }

        // 性别、状态写错时不能静默当成 0 / 启用：状态尤其要命，填错一个字就把账号开了
        $genderRaw = trim((string) ($row['gender'] ?? ''));
        $gender = $genderRaw === '' ? 0 : (int) $genderRaw;
        if (!in_array($genderRaw, ['', '0', '1', '2'], true)) {
            throw new BusinessException(lang('dataimport.invalid_gender'));
        }

        $statusRaw = trim((string) ($row['status'] ?? ''));
        if (!in_array($statusRaw, ['', '0', '1'], true)) {
            throw new BusinessException(lang('dataimport.invalid_status'));
        }
        $status = $statusRaw === '0' ? 0 : 1;

        $this->userRepository->create([
            'mobile'   => $mobile,
            'password' => $hashed,
            'nickname' => $nickname,
            'email'    => $email === '' ? null : $email,
            'gender'   => $gender,
            'status'   => $status,
        ]);
    }
}
