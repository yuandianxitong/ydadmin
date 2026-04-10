<?php

use think\migration\Migrator;

class CreatePointsLogsTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('points_logs', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_0900_ai_ci',
            'comment' => '积分变动记录',
        ]);

        $table
            ->addColumn('user_id', 'integer', ['signed' => false, 'comment' => '用户ID'])
            ->addColumn('points', 'integer', ['comment' => '变动积分'])
            ->addColumn('before_points', 'integer', ['comment' => '变动前积分'])
            ->addColumn('after_points', 'integer', ['comment' => '变动后积分'])
            ->addColumn('type', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'default' => 1, 'comment' => '类型:1后台调整,2注册赠送,3签到,4消费赠送,5消费扣减'])
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
        $this->table('points_logs')->drop()->save();
    }
}
