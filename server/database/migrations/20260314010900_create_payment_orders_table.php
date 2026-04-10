<?php

use think\migration\Migrator;

class CreatePaymentOrdersTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('payment_orders', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_0900_ai_ci',
            'comment' => '支付订单表',
        ]);

        $table
            ->addColumn('order_no', 'string', ['limit' => 64, 'comment' => '订单号'])
            ->addColumn('trade_no', 'string', ['limit' => 128, 'null' => true, 'comment' => '交易号'])
            ->addColumn('channel', 'string', ['limit' => 20, 'null' => true, 'comment' => '支付渠道'])
            ->addColumn('trade_type', 'string', ['limit' => 20, 'null' => true, 'comment' => '交易类型'])
            ->addColumn('subject', 'string', ['limit' => 255, 'null' => true, 'comment' => '订单标题'])
            ->addColumn('body', 'string', ['limit' => 500, 'null' => true, 'comment' => '订单描述'])
            ->addColumn('total_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '总金额'])
            ->addColumn('refund_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0, 'comment' => '退款金额'])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'pending', 'comment' => '状态'])
            ->addColumn('notify_data', 'text', ['null' => true, 'comment' => '通知数据'])
            ->addColumn('extra', 'text', ['null' => true, 'comment' => '扩展数据'])
            ->addColumn('paid_at', 'datetime', ['null' => true, 'comment' => '支付时间'])
            ->addColumn('refunded_at', 'datetime', ['null' => true, 'comment' => '退款时间'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间'])
            ->addIndex(['order_no'], ['unique' => true])
            ->addIndex(['trade_no'])
            ->addIndex(['channel'])
            ->addIndex(['status'])
            ->addIndex(['created_at'])
            ->create();
    }

    public function down(): void
    {
        $this->table('payment_orders')->drop()->save();
    }
}
