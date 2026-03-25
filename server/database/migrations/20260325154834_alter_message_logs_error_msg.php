<?php

use think\migration\Migrator;

class AlterMessageLogsErrorMsg extends Migrator
{
    public function up()
    {
        $this->table('message_logs')
            ->changeColumn('error_msg', 'text', ['null' => true, 'comment' => '错误信息'])
            ->update();
    }

    public function down()
    {
        $this->table('message_logs')
            ->changeColumn('error_msg', 'string', ['limit' => 500, 'default' => '', 'comment' => '错误信息'])
            ->update();
    }
}
