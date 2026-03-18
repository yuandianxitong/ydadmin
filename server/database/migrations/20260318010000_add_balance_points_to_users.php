<?php

use think\migration\Migrator;

class AddBalancePointsToUsers extends Migrator
{
    public function up(): void
    {
        $table = $this->table('users');
        $table
            ->addColumn('balance', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0.00', 'comment' => '余额', 'after' => 'status'])
            ->addColumn('points', 'integer', ['default' => 0, 'comment' => '积分', 'after' => 'balance'])
            ->update();
    }

    public function down(): void
    {
        $table = $this->table('users');
        $table->removeColumn('balance')->removeColumn('points')->update();
    }
}
