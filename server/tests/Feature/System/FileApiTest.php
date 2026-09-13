<?php

declare(strict_types=1);

namespace tests\Feature\System;

use support\Db;
use tests\Support\ApiTestCase;
use tests\Support\TestAdmin;

/**
 * 文件管理（契约 §2.9.1）。列表 / 分组 / 移动分组 / 重命名 / 删除 / 批量删除。
 * 删除要真的把 public/storage 下的文件删掉，所以涉及物理文件的用例经 upload 接口造数据。
 */
final class FileApiTest extends ApiTestCase
{
    private const BASE = '/adminapi/system/file';

    private const ALL = ['system.file.list', 'system.file.update', 'system.file.delete'];

    /**
     * 直接写库造一条 files 行(不落盘,用于列表/分组/改名这类不碰磁盘的用例)。
     *
     * @param array<string, mixed> $attributes
     */
    private function insertFile(array $attributes = []): int
    {
        $now = date('Y-m-d H:i:s');
        $id = (int) Db::table('files')->insertGetId(array_merge([
            'name'      => '素材' . bin2hex(random_bytes(3)) . '.png',
            'path'      => 'uploads/images/20260101/' . bin2hex(random_bytes(16)) . '.png',
            'url'       => '/storage/uploads/images/20260101/x.png',
            'mime_type' => 'image/png',
            'extension' => 'png',
            'size'      => 1024,
            'group'     => 'images',
            'upload_by' => 0,
            'storage'   => 'local',
            'created_at' => $now,
            'updated_at' => $now,
        ], $attributes));
        $this->track('files', $id);

        return $id;
    }

    /** 经上传接口造一条真实落盘的文件,返回 [files.id, 相对路径]。 @return array{0: int, 1: string} */
    private function uploadReal(TestAdmin $admin): array
    {
        $data = $this->postFile('/adminapi/upload/image', 'file', 'real.png', "\x89PNG\r\n\x1a\ndata", 'image/png', $admin->token)->assertOk()->data();
        $path = (string) $data['path'];
        $this->trackStorageFile($path);
        $id = (int) Db::table('files')->where('path', $path)->value('id');
        $this->track('files', $id);

        return [$id, $path];
    }

    /** @return list<int> */
    private function listIds(TestAdmin $admin, array $query = []): array
    {
        return array_map('intval', array_column($this->get(self::BASE, $query + ['limit' => 100], $admin->token)->assertOk()->data()['list'], 'id'));
    }

    public function test_index_filters_by_keyword_group_and_mime_bucket(): void
    {
        $admin = $this->actingAsAdmin(self::ALL);
        $tag = bin2hex(random_bytes(4));
        $image = $this->insertFile(['name' => "{$tag}_图片.png", 'mime_type' => 'image/png', 'extension' => 'png', 'group' => 'images']);
        $doc = $this->insertFile(['name' => "{$tag}_文档.pdf", 'mime_type' => 'application/pdf', 'extension' => 'pdf', 'group' => 'files']);
        $video = $this->insertFile(['name' => "{$tag}_视频.mp4", 'mime_type' => 'video/mp4', 'extension' => 'mp4', 'group' => 'files']);

        $all = $this->listIds($admin, ['keyword' => $tag]);
        $this->assertEqualsCanonicalizing([$image, $doc, $video], $all);

        $this->assertSame([$image], $this->listIds($admin, ['keyword' => $tag, 'mime_type' => 'image']));
        $this->assertSame([$video], $this->listIds($admin, ['keyword' => $tag, 'mime_type' => 'video']));
        $this->assertSame([$doc], $this->listIds($admin, ['keyword' => $tag, 'mime_type' => 'document']));
        // other = 不是 image/video/audio（FileRepositoryTest::test_mime_buckets_follow_tp8_classification 锁定的口径），video 不在里面
        $this->assertSame([$doc], $this->listIds($admin, ['keyword' => $tag, 'mime_type' => 'other']));
        $this->assertEqualsCanonicalizing([$doc, $video], $this->listIds($admin, ['keyword' => $tag, 'group' => 'files']));

        // keyword 里的 % 按字面匹配(core\support\Like)
        $percent = $this->insertFile(['name' => "{$tag}_100%.png"]);
        $this->assertSame([$percent], $this->listIds($admin, ['keyword' => "{$tag}_100%"]));

        // 分页信封
        $pagination = $this->get(self::BASE, ['keyword' => $tag, 'limit' => 2], $admin->token)->assertOk()->data()['pagination'];
        $this->assertSame(['current_page', 'per_page', 'total', 'last_page'], array_keys($pagination));
        $this->assertSame(4, $pagination['total']);
        $this->assertSame(2, $pagination['per_page']);
    }

    public function test_index_rejects_an_unknown_mime_bucket(): void
    {
        $admin = $this->actingAsAdmin(self::ALL);
        $this->get(self::BASE, ['mime_type' => 'executable'], $admin->token)->assertCode(422);
    }

    public function test_groups_returns_group_and_count_ordered_by_count_desc(): void
    {
        $admin = $this->actingAsAdmin(self::ALL);
        $group = 'g' . bin2hex(random_bytes(4));
        $other = 'o' . bin2hex(random_bytes(4));
        $this->insertFile(['group' => $group]);
        $this->insertFile(['group' => $group]);
        $this->insertFile(['group' => $other]);

        $rows = $this->get(self::BASE . '/groups', [], $admin->token)->assertOk()->data();
        $counts = [];
        foreach ($rows as $row) {
            $this->assertSame(['group', 'count'], array_keys($row), '每行形如 {group, count}');
            $counts[(string) $row['group']] = (int) $row['count'];
        }
        $this->assertSame(2, $counts[$group] ?? 0);
        $this->assertSame(1, $counts[$other] ?? 0);

        // count desc:本用例造的两个分组之间,多的排在少的前面
        $ordered = array_values(array_filter(array_column($rows, 'group'), static fn (string $g): bool => in_array($g, [$group, $other], true)));
        $this->assertSame([$group, $other], $ordered);

        // 只需登录即可(PermissionSkip)
        $this->get(self::BASE . '/groups', [], $this->actingAsAdmin()->token)->assertOk();
    }

    public function test_move_group_updates_every_id(): void
    {
        $admin = $this->actingAsAdmin(self::ALL);
        $a = $this->insertFile(['group' => 'images']);
        $b = $this->insertFile(['group' => 'images']);
        $target = '设计稿' . bin2hex(random_bytes(3));

        $this->post(self::BASE . '/move-group', ['ids' => [$a, $b], 'group' => $target], $admin->token)->assertOk();

        $this->assertSame($target, Db::table('files')->where('id', $a)->value('group'));
        $this->assertSame($target, Db::table('files')->where('id', $b)->value('group'));
    }

    public function test_move_group_validation(): void
    {
        $admin = $this->actingAsAdmin(self::ALL);
        $id = $this->insertFile(['group' => 'images']);

        $this->assertSame(
            lang('validation.file_ids_require'),
            $this->post(self::BASE . '/move-group', ['ids' => [], 'group' => 'x'], $admin->token)->assertCode(422)->data()['errors']['ids']
        );
        $this->assertSame(
            lang('validation.file_group_require'),
            $this->post(self::BASE . '/move-group', ['ids' => [$id], 'group' => ''], $admin->token)->assertCode(422)->data()['errors']['group']
        );
        $this->assertSame('images', Db::table('files')->where('id', $id)->value('group'), '校验失败不落库');
    }

    public function test_rename(): void
    {
        $admin = $this->actingAsAdmin(self::ALL);
        $id = $this->insertFile(['name' => '旧名.png']);

        $this->put(self::BASE . "/{$id}/rename", ['name' => '新名.png'], $admin->token)->assertOk();
        $this->assertSame('新名.png', Db::table('files')->where('id', $id)->value('name'));

        $this->assertSame(
            lang('validation.file_name_require'),
            $this->put(self::BASE . "/{$id}/rename", ['name' => ''], $admin->token)->assertCode(422)->data()['errors']['name']
        );
        $this->assertSame('新名.png', Db::table('files')->where('id', $id)->value('name'));

        $this->assertSame(
            lang('business.file_not_found'),
            $this->put(self::BASE . '/999999/rename', ['name' => 'x.png'], $admin->token)->assertCode(400)->message()
        );
    }

    public function test_delete_removes_the_physical_file_and_soft_deletes_the_row(): void
    {
        $admin = $this->actingAsAdmin(self::ALL);
        [$id, $path] = $this->uploadReal($admin);
        $absolute = public_path('storage/' . $path);
        $this->assertFileExists($absolute);

        $this->delete(self::BASE . "/{$id}", [], $admin->token)->assertOk();

        $this->assertFileDoesNotExist($absolute, '物理文件应当被删除');
        $this->assertNotNull(Db::table('files')->where('id', $id)->value('deleted_at'), '软删');
        $this->assertNotContains($id, $this->listIds($admin), '软删后不再出现在列表里');
        $this->assertSame(lang('business.file_not_found'), $this->delete(self::BASE . "/{$id}", [], $admin->token)->assertCode(400)->message());
    }

    public function test_delete_still_succeeds_when_the_physical_file_is_missing(): void
    {
        $admin = $this->actingAsAdmin(self::ALL);
        // 库里有行,盘上没有文件(TP8 老数据、手工清盘等场景)
        $id = $this->insertFile();

        $this->delete(self::BASE . "/{$id}", [], $admin->token)->assertOk();

        $this->assertNotNull(Db::table('files')->where('id', $id)->value('deleted_at'));
    }

    public function test_delete_uses_the_driver_recorded_on_the_row(): void
    {
        $admin = $this->actingAsAdmin(self::ALL);
        [$id, $path] = $this->uploadReal($admin);   // 上传时 storage_driver=local,行上记的就是 local
        $absolute = public_path('storage/' . $path);
        $this->assertFileExists($absolute);

        // 上传之后把当前驱动切成云驱动:删除仍然必须按行上记录的 local 去删。
        // 若实现改回按当前驱动解析(disk()),这里会去云端找、找不到,本地文件留成孤儿,
        // 下面的 assertFileDoesNotExist 立刻红——这就是这条用例的全部意义。
        $this->setConfig('storage_driver', 'aliyun');

        $this->delete(self::BASE . "/{$id}", [], $admin->token)->assertOk();

        $this->assertFileDoesNotExist($absolute, '按行上记录的 local 驱动删除,与当前 storage_driver 无关');
        $this->assertNotNull(Db::table('files')->where('id', $id)->value('deleted_at'));
    }

    public function test_an_unknown_driver_on_the_row_surfaces_as_a_business_error(): void
    {
        $admin = $this->actingAsAdmin(self::ALL);
        $id = $this->insertFile(['storage' => 'retired_driver']);

        // 驱动层的结构性失败必须冒出来:宁可报错,也不要「接口说删成功了、对象其实还在」
        $this->delete(self::BASE . "/{$id}", [], $admin->token)->assertCode(400);
        $this->assertNull(Db::table('files')->where('id', $id)->value('deleted_at'), 'DB 行不该被删掉');

        // 批量删除里同样的一行按「单条失败」跳过,不拖垮整批(契约 §2.9.1:非事务、失败跳过)
        $ok = $this->insertFile();
        $response = $this->post(self::BASE . '/batch-delete', ['ids' => [$id, $ok]], $admin->token)->assertOk();
        $this->assertSame(sprintf(lang('messages.file_delete_count'), 1), $response->message());
        $this->assertNotNull(Db::table('files')->where('id', $ok)->value('deleted_at'));
        $this->assertNull(Db::table('files')->where('id', $id)->value('deleted_at'));
    }

    public function test_batch_delete_counts_successes_and_skips_failures(): void
    {
        $admin = $this->actingAsAdmin(self::ALL);
        $a = $this->insertFile();
        $b = $this->insertFile();

        // 混入一个不存在的 id:不整体失败,只计成功数
        $response = $this->post(self::BASE . '/batch-delete', ['ids' => [$a, 999999, $b]], $admin->token)->assertOk();
        $this->assertSame(sprintf(lang('messages.file_delete_count'), 2), $response->message());

        $this->assertNotNull(Db::table('files')->where('id', $a)->value('deleted_at'));
        $this->assertNotNull(Db::table('files')->where('id', $b)->value('deleted_at'));
    }

    public function test_batch_delete_validation(): void
    {
        $admin = $this->actingAsAdmin(self::ALL);

        $this->assertSame(
            lang('validation.file_ids_require'),
            $this->post(self::BASE . '/batch-delete', ['ids' => []], $admin->token)->assertCode(422)->data()['errors']['ids']
        );
        $this->assertSame(
            lang('validation.file_ids_integer'),
            $this->post(self::BASE . '/batch-delete', ['ids' => ['abc']], $admin->token)->assertCode(422)->data()['errors']['ids.0']
        );
    }

    public function test_permission_points(): void
    {
        $nobody = $this->actingAsAdmin();
        $id = $this->insertFile();

        $this->get(self::BASE, [], $nobody->token)->assertCode(403);
        $this->post(self::BASE . '/move-group', ['ids' => [$id], 'group' => 'x'], $nobody->token)->assertCode(403);
        $this->put(self::BASE . "/{$id}/rename", ['name' => 'x.png'], $nobody->token)->assertCode(403);
        $this->delete(self::BASE . "/{$id}", [], $nobody->token)->assertCode(403);
        $this->post(self::BASE . '/batch-delete', ['ids' => [$id]], $nobody->token)->assertCode(403);
        // groups 只要登录
        $this->get(self::BASE . '/groups', [], $nobody->token)->assertOk();

        $this->get(self::BASE)->assertCode(401);

        // 只有 list 权限的管理员读得了、写不了
        $reader = $this->actingAsAdmin(['system.file.list']);
        $this->get(self::BASE, [], $reader->token)->assertOk();
        $this->delete(self::BASE . "/{$id}", [], $reader->token)->assertCode(403);
    }
}
