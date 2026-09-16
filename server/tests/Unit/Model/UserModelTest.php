<?php

declare(strict_types=1);

namespace tests\Unit\Model;

use app\model\user\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use tests\TestCase;

final class UserModelTest extends TestCase
{
    public function test_password_is_hidden_from_array_and_json(): void
    {
        $user = new User();
        $user->forceFill([
            'id'       => 1,
            'nickname' => '小明',
            'mobile'   => '13800000000',
            'password' => 'hashed-secret',
            'status'   => 1,
        ]);

        $array = $user->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertSame('小明', $array['nickname']);
        $this->assertStringNotContainsString('hashed-secret', $user->toJson());
    }

    public function test_casts_numeric_columns_and_balance_as_two_decimal_string(): void
    {
        $user = new User();
        $user->forceFill([
            'gender'      => '1',
            'login_count' => '3',
            'status'      => '1',
            'points'      => '100',
            'balance'     => '88.5',
        ]);

        $this->assertSame(1, $user->gender);
        $this->assertSame(3, $user->login_count);
        $this->assertSame(1, $user->status);
        $this->assertSame(100, $user->points);
        $this->assertSame('88.50', $user->balance);
    }

    public function test_uses_soft_deletes(): void
    {
        $this->assertContains(SoftDeletes::class, class_uses(User::class));
    }
}
