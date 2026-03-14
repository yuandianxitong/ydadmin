<?php

use think\migration\Migrator;

class CreateMessageTemplatesTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('message_templates', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '消息模板表',
        ]);

        $table
            ->addColumn('name', 'string', ['limit' => 100, 'comment' => '模板名称'])
            ->addColumn('code', 'string', ['limit' => 50, 'comment' => '模板编码'])
            ->addColumn('sms_enabled', 'integer', ['limit' => 4, 'default' => 0, 'comment' => '短信启用:0否,1是'])
            ->addColumn('sms_template_id', 'string', ['limit' => 100, 'null' => true, 'comment' => '短信模板ID'])
            ->addColumn('sms_content', 'string', ['limit' => 500, 'null' => true, 'comment' => '短信内容'])
            ->addColumn('wechat_official_enabled', 'integer', ['limit' => 4, 'default' => 0, 'comment' => '公众号模板消息启用:0否,1是'])
            ->addColumn('wechat_official_template_id', 'string', ['limit' => 100, 'null' => true, 'comment' => '公众号模板ID'])
            ->addColumn('wechat_official_url', 'string', ['limit' => 500, 'null' => true, 'comment' => '公众号模板跳转URL'])
            ->addColumn('wechat_mini_enabled', 'integer', ['limit' => 4, 'default' => 0, 'comment' => '小程序订阅消息启用:0否,1是'])
            ->addColumn('wechat_mini_template_id', 'string', ['limit' => 100, 'null' => true, 'comment' => '小程序模板ID'])
            ->addColumn('wechat_mini_page', 'string', ['limit' => 200, 'null' => true, 'comment' => '小程序跳转页面'])
            ->addColumn('variables', 'json', ['null' => true, 'comment' => '模板变量'])
            ->addColumn('remark', 'string', ['limit' => 500, 'null' => true, 'comment' => '备注'])
            ->addColumn('status', 'boolean', ['default' => 1, 'comment' => '状态:1启用,0禁用'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间'])
            ->addIndex(['code'], ['unique' => true])
            ->addIndex(['status'])
            ->create();
    }

    public function down(): void
    {
        $this->table('message_templates')->drop()->save();
    }
}
