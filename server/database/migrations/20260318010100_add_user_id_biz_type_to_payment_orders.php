<?php

use think\migration\Migrator;

class AddUserIdBizTypeToPaymentOrders extends Migrator
{
    public function up(): void
    {
        $table = $this->table('payment_orders');
        $table
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => true, 'comment' => '用户ID', 'after' => 'id'])
            ->addColumn('biz_type', 'string', ['limit' => 30, 'null' => true, 'comment' => '业务类型', 'after' => 'user_id'])
            ->addIndex(['user_id'])
            ->addIndex(['biz_type'])
            ->update();
    }

    public function down(): void
    {
        $table = $this->table('payment_orders');
        $table->removeColumn('user_id')->removeColumn('biz_type')->update();
    }
}
