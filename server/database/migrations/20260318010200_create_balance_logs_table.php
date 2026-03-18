<?php

use think\migration\Migrator;

class CreateBalanceLogsTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('balance_logs', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '余额变动记录',
        ]);

        $table
            ->addColumn('user_id', 'integer', ['signed' => false, 'comment' => '用户ID'])
            ->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2, 'comment' => '变动金额'])
            ->addColumn('before_balance', 'decimal', ['precision' => 10, 'scale' => 2, 'comment' => '变动前余额'])
            ->addColumn('after_balance', 'decimal', ['precision' => 10, 'scale' => 2, 'comment' => '变动后余额'])
            ->addColumn('type', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1, 'comment' => '类型:1充值,2消费,3退款,4后台调整'])
            ->addColumn('source', 'string', ['limit' => 50, 'default' => '', 'comment' => '来源标识'])
            ->addColumn('remark', 'string', ['limit' => 255, 'default' => '', 'comment' => '备注'])
            ->addColumn('operator_id', 'integer', ['signed' => false, 'null' => true, 'comment' => '操作管理员ID'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addIndex(['user_id'])
            ->addIndex(['type'])
            ->create();
    }

    public function down(): void
    {
        $this->table('balance_logs')->drop()->save();
    }
}
