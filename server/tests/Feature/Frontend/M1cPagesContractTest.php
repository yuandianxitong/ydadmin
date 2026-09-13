<?php

declare(strict_types=1);

namespace tests\Feature\Frontend;

use support\Db;
use tests\Support\ApiTestCase;

/**
 * M1c 文件管理页的前端契约：请求参数照抄 admin/src/views/system/file/index.vue 的实际发送方式
 * （keyword/group/mime_type 即便是空字符串也照发，limit 默认 40），并断言页面读取的每个字段都在。
 *
 * 上传接口走 multipart，而 ApiTestCase 只构造 JSON 请求体，所以「传上去的字节变成一行 files 记录」
 * 由 scripts/admin-contract-check.php 用真实 multipart 请求覆盖；这里只核对两个上传端点的权限约定。
 */
final class M1cPagesContractTest extends ApiTestCase
{
    /** @var list<string> 本用例在 public/storage 下真实写出的文件，tearDown 时清掉 */
    private array $diskPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->diskPaths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        $this->diskPaths = [];
        parent::tearDown();
    }

    /**
     * 造一条 files 行。$onDisk 为 true 时按 path 在 public/storage 下真实写出文件，
     * 用来断言删除接口确实动了磁盘。
     *
     * @param array<string, mixed> $attributes
     */
    private function makeFile(array $attributes = [], bool $onDisk = false): int
    {
        $now = date('Y-m-d H:i:s');
        $tag = bin2hex(random_bytes(4));
        $path = (string) ($attributes['path'] ?? 'uploads/images/' . date('Ymd') . "/fe{$tag}.png");
        $row = array_merge([
            'name'       => "fe_{$tag}.png",
            'path'       => $path,
            'url'        => '/storage/' . $path,
            'mime_type'  => 'image/png',
            'extension'  => 'png',
            'size'       => 70,
            'group'      => '默认',
            'upload_by'  => 0,
            'storage'    => 'local',
            'created_at' => $now,
            'updated_at' => $now,
        ], $attributes, ['path' => $path]);

        $id = (int) Db::table('files')->insertGetId($row);
        $this->track('files', $id);

        if ($onDisk) {
            $absolute = public_path('storage') . '/' . $path;
            if (!is_dir(dirname($absolute))) {
                mkdir(dirname($absolute), 0755, true);
            }
            file_put_contents($absolute, 'contract');
            $this->diskPaths[] = $absolute;
        }

        return $id;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return list<int>
     */
    private function ids(array $rows): array
    {
        return array_values(array_map(static fn (array $row): int => (int) $row['id'], $rows));
    }

    public function test_file_list_and_groups(): void
    {
        $admin = $this->actingAsAdmin(['system.file.list']);
        $tag = bin2hex(random_bytes(4));
        $group = "前端分组_{$tag}";

        $imageId = $this->makeFile(['name' => "feimg_{$tag}.png", 'group' => $group]);
        $docId = $this->makeFile([
            'name'      => "fedoc_{$tag}.pdf",
            'path'      => 'uploads/files/' . date('Ymd') . "/fe{$tag}.pdf",
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'group'     => $group,
        ]);
        $otherId = $this->makeFile(['name' => "feother_{$tag}.png"]);

        // index.vue 首次加载：三个筛选项都是空字符串，照样发出
        $page = $this->get('/adminapi/system/file', ['keyword' => '', 'group' => '', 'mime_type' => '', 'page' => 1, 'limit' => 40], $admin->token)->assertOk()->data();
        $this->assertSame(['list', 'pagination'], array_keys($page));
        $this->assertSame(40, (int) $page['pagination']['per_page'], '页面默认每页 40');
        $this->assertGreaterThanOrEqual(3, (int) $page['pagination']['total'], '空筛选项不过滤');

        // keyword 按文件名搜索
        $found = $this->get('/adminapi/system/file', ['keyword' => "feimg_{$tag}", 'group' => '', 'mime_type' => '', 'page' => 1, 'limit' => 40], $admin->token)->assertOk()->data();
        $this->assertSame([$imageId], $this->ids($found['list']));

        // 卡片读取的字段必须齐，且类型对得上 formatSize / formatTime / isImage
        $row = $found['list'][0];
        foreach (['id', 'name', 'path', 'url', 'mime_type', 'extension', 'size', 'group', 'storage', 'created_at'] as $field) {
            $this->assertArrayHasKey($field, $row, "文件列表缺 {$field}");
        }
        $this->assertIsInt($row['size'], 'formatSize 按数字比较，字符串会走字典序');
        $this->assertIsString($row['extension']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $row['created_at']);
        $this->assertSame('/storage/' . $row['path'], $row['url'], '本地驱动是相对 URL');

        // 侧栏选分组
        $byGroup = $this->get('/adminapi/system/file', ['keyword' => '', 'group' => $group, 'mime_type' => '', 'page' => 1, 'limit' => 40], $admin->token)->assertOk()->data();
        $this->assertSame([$imageId, $docId], $this->sorted($this->ids($byGroup['list'])));
        $this->assertNotContains($otherId, $this->ids($byGroup['list']));

        // 侧栏选类型：image 桶命中图片、不命中 pdf；audio 桶谁也不命中
        $bucket = fn (string $mime): array => $this->ids($this->get('/adminapi/system/file', ['keyword' => '', 'group' => $group, 'mime_type' => $mime, 'page' => 1, 'limit' => 40], $admin->token)->assertOk()->data()['list']);
        $this->assertSame([$imageId], $bucket('image'));
        $this->assertNotContains($imageId, $bucket('audio'));
        $this->assertNotContains($imageId, $bucket('document'));
        $this->assertContains($docId, $bucket('document'), 'application/pdf 属于 document 桶');

        // 分组侧栏：[{group, count}]，按 count 倒序；#[PermissionSkip]，没有 file 权限也能读
        $nobody = $this->actingAsAdmin();
        $groups = $this->get('/adminapi/system/file/groups', [], $nobody->token)->assertOk()->data();
        $this->assertTrue(array_is_list($groups));
        $this->assertSame(['group', 'count'], array_keys($groups[0]), '页面读 g.group 与 g.count');
        $counts = array_map('intval', array_column($groups, 'count'));
        $desc = $counts;
        rsort($desc);
        $this->assertSame($desc, $counts, 'count desc');
        $this->assertSame(2, (int) array_column($groups, 'count', 'group')[$group], '新分组聚合出 2 条');
    }

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    private function sorted(array $ids): array
    {
        sort($ids);

        return $ids;
    }

    public function test_rename_move_group_and_delete(): void
    {
        $admin = $this->actingAsAdmin(['system.file.list', 'system.file.update', 'system.file.delete']);
        $tag = bin2hex(random_bytes(4));
        $id = $this->makeFile(['name' => "ferename_{$tag}.png"], true);
        $before = (array) Db::table('files')->where('id', $id)->first();

        // 重命名：只改显示名，path/url 与磁盘文件都不动
        $newName = "契约重命名_{$tag}.png";
        $this->put("/adminapi/system/file/{$id}/rename", ['name' => $newName], $admin->token)->assertOk();
        $after = (array) Db::table('files')->where('id', $id)->first();
        $this->assertSame($newName, $after['name']);
        $this->assertSame($before['path'], $after['path']);
        $this->assertSame($before['url'], $after['url']);
        $this->assertFileExists(public_path('storage') . '/' . $before['path'], '重命名不碰磁盘');

        // 前端不校验非空，后端必须挡住
        $this->put("/adminapi/system/file/{$id}/rename", ['name' => ''], $admin->token)->assertCode(422);

        // 移动分组（页面暂无入口，契约要求存在）
        $second = $this->makeFile(['name' => "femove_{$tag}.png"]);
        $group = "前端移动_{$tag}";
        $this->post('/adminapi/system/file/move-group', ['ids' => [$id, $second], 'group' => $group], $admin->token)->assertOk();
        $this->assertSame([$group, $group], array_map('strval', Db::table('files')->whereIn('id', [$id, $second])->orderBy('id')->pluck('group')->all()));

        // 删除：库里没了，磁盘上的文件也没了
        $absolute = public_path('storage') . '/' . $before['path'];
        $inGroup = fn (): array => $this->ids($this->get('/adminapi/system/file', ['keyword' => '', 'group' => $group, 'mime_type' => '', 'page' => 1, 'limit' => 40], $admin->token)->assertOk()->data()['list']);
        $this->assertSame([$id, $second], $this->sorted($inGroup()), '删除前两条都在新分组里');
        $this->delete("/adminapi/system/file/{$id}", [], $admin->token)->assertOk();
        $this->assertFileDoesNotExist($absolute, '删除要连物理文件一起删');
        $this->assertSame([$second], $inGroup(), '被删的行不该再出现在列表里');

        // 物理文件本来就不在时（$second 没有落盘），删除照样成功：只记 warning，不阻断
        $this->delete("/adminapi/system/file/{$second}", [], $admin->token)->assertOk();
        $this->assertSame([], $inGroup());
    }

    public function test_batch_delete(): void
    {
        $admin = $this->actingAsAdmin(['system.file.list', 'system.file.delete']);
        $tag = bin2hex(random_bytes(4));
        $group = "前端批删_{$tag}";
        $ids = [];
        $paths = [];
        foreach (['a', 'b', 'c'] as $seq) {
            $id = $this->makeFile(['name' => "febatch_{$seq}_{$tag}.png", 'group' => $group], true);
            $ids[] = $id;
            $paths[] = public_path('storage') . '/' . Db::table('files')->where('id', $id)->value('path');
        }
        foreach ($paths as $path) {
            $this->assertFileExists($path);
        }

        $response = $this->post('/adminapi/system/file/batch-delete', ['ids' => $ids], $admin->token)->assertOk();
        $this->assertStringContainsString('3', $response->message(), 'message 里带成功计数');
        $this->assertSame([], $this->ids($this->get('/adminapi/system/file', ['group' => $group, 'page' => 1, 'limit' => 40], $admin->token)->assertOk()->data()['list']));
        foreach ($paths as $path) {
            $this->assertFileDoesNotExist($path);
        }

        $this->post('/adminapi/system/file/batch-delete', ['ids' => []], $admin->token)->assertCode(422);
        $this->post('/adminapi/system/file/batch-delete', ['ids' => ['x']], $admin->token)->assertCode(422);
    }

    public function test_permission_contract(): void
    {
        $nobody = $this->actingAsAdmin();

        // 列表要 system.file.list
        $this->get('/adminapi/system/file', ['page' => 1, 'limit' => 40], $nobody->token)->assertCode(403);
        // 分组是 #[PermissionSkip]
        $this->get('/adminapi/system/file/groups', [], $nobody->token)->assertOk();

        // 两个上传端点：#[PermissionSkip]，任何登录管理员都能调；不带文件时是业务错误，不是 403
        foreach (['/adminapi/upload/image', '/adminapi/upload/file'] as $uri) {
            $code = $this->post($uri, [], $nobody->token)->code();
            $this->assertNotSame(403, $code, "{$uri} 必须对任何登录管理员开放");
            $this->assertContains($code, [400, 422], "{$uri} 不带文件时应是业务错误，实际 {$code}");
            $this->assertSame(401, $this->post($uri, [])->code(), "{$uri} 未登录必须 401");
        }
    }
}
