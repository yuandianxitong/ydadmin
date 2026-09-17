<?php

declare(strict_types=1);

namespace tests\Support\Message;

use support\Db;
use tests\Support\TestUser;

/**
 * 消息体系用例的夹具（M6b Task 5 起复用）。只能用在 tests\Support\ApiTestCase 的子类里（依赖 track / actingAsUser）。
 * 模板 code 随机、登记后由父类 tearDown 硬删；会员登记在本 trait，cleanupMessageFixtures() 清掉他们名下的消息行。
 */
trait MessageFixtures
{
    /** @var list<int> */
    private array $messageUserIds = [];

    /**
     * @param array<string, mixed> $overrides 覆盖 message_templates 列；三个 JSON 列可直接给数组
     * @return array{id: int, code: string}
     */
    private function insertMessageTemplate(array $overrides = []): array
    {
        $now = date('Y-m-d H:i:s');
        $code = (string) ($overrides['code'] ?? 't_' . bin2hex(random_bytes(6)));
        $row = array_merge([
            'name'                        => '测试模板',
            'status'                      => 1,
            'sms_enabled'                 => 0,
            'sms_template_id'             => '',
            'sms_content'                 => '',
            'wechat_official_enabled'     => 0,
            'wechat_official_template_id' => '',
            'wechat_official_url'         => '',
            'wechat_official_data'        => null,
            'wechat_mini_enabled'         => 0,
            'wechat_mini_template_id'     => '',
            'wechat_mini_page'            => '',
            'wechat_mini_data'            => null,
            'site_enabled'                => 0,
            'site_title'                  => '',
            'site_content'                => '',
            'variables'                   => [['key' => 'nickname', 'name' => '昵称', 'example' => '张三']],
            'created_at'                  => $now,
            'updated_at'                  => $now,
        ], $overrides, ['code' => $code]);
        foreach (['wechat_official_data', 'wechat_mini_data', 'variables'] as $column) {
            if (is_array($row[$column])) {
                $row[$column] = json_encode($row[$column], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            }
        }
        $id = (int) Db::table('message_templates')->insertGetId($row);
        $this->track('message_templates', $id);

        return ['id' => $id, 'code' => $code];
    }

    /** @param array<string, mixed> $attributes 覆盖 users 列，如 ['oa_openid' => 'o...'] */
    private function messageUser(array $attributes = []): TestUser
    {
        $user = $this->actingAsUser($attributes);
        $this->messageUserIds[] = $user->id;

        return $user;
    }

    /**
     * 直接插一条待发日志（绕过 sendToUser，用来单独驱动 deliver()）。
     *
     * @param array{id: int, code: string} $template
     * @param array<string, mixed>         $vars
     * @param array<string, mixed>         $overrides
     */
    private function insertPendingLog(array $template, string $channel, int $userId, array $vars, array $overrides = []): int
    {
        $now = date('Y-m-d H:i:s');

        return (int) Db::table('message_logs')->insertGetId(array_merge([
            'template_id'   => $template['id'],
            'template_code' => $template['code'],
            'channel'       => $channel,
            'user_id'       => $userId,
            'receiver'      => '…',
            'variables'     => json_encode($vars, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'content'       => null,
            'status'        => 0,
            'error_msg'     => '',
            'attempts'      => 0,
            'created_at'    => $now,
            'updated_at'    => $now,
        ], $overrides));
    }

    /** @return array<string, mixed> */
    private function messageLog(int $id): array
    {
        return (array) Db::table('message_logs')->where('id', $id)->first();
    }

    /** @return list<array<string, mixed>> id 升序 */
    private function messageLogsFor(int $userId): array
    {
        return array_map(static fn (object $row): array => (array) $row, Db::table('message_logs')->where('user_id', $userId)->orderBy('id')->get()->all());
    }

    /** @return list<array<string, mixed>> id 升序 */
    private function notificationsFor(int $userId): array
    {
        return array_map(static fn (object $row): array => (array) $row, Db::table('user_notifications')->where('user_id', $userId)->orderBy('id')->get()->all());
    }

    private function cleanupMessageFixtures(): void
    {
        if ($this->messageUserIds !== []) {
            Db::table('message_logs')->whereIn('user_id', $this->messageUserIds)->delete();
            Db::table('user_notification_reads')->whereIn('user_id', $this->messageUserIds)->delete();
            Db::table('user_notifications')->whereIn('user_id', $this->messageUserIds)->delete();
        }
        // sync 驱动下暂时失败即「最后一次」，M3 会写 failed_jobs
        Db::table('failed_jobs')->where('queue', 'message-send')->delete();
        $this->messageUserIds = [];
    }
}
