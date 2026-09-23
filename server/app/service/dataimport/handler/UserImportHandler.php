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

        $email = trim((string) ($row['email'] ?? ''));
        $gender = (int) ($row['gender'] ?? 0);
        if (!in_array($gender, [0, 1, 2], true)) {
            $gender = 0;
        }
        $status = (string) ($row['status'] ?? '1') === '0' ? 0 : 1;

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
