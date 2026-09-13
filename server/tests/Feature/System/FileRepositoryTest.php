<?php

declare(strict_types=1);

namespace tests\Feature\System;

use app\repository\system\FileRepository;
use support\Db;
use tests\TestCase;

final class FileRepositoryTest extends TestCase
{
    /** @var list<int> 本用例插入的 files 行 */
    private array $fileIds = [];

    protected function tearDown(): void
    {
        if ($this->fileIds !== []) {
            // 硬删：files 是软删表，Db 门面直接发 DELETE 才能清干净
            Db::table('files')->whereIn('id', $this->fileIds)->delete();
            $this->fileIds = [];
        }
        parent::tearDown();
    }

    /** @param array<string, mixed> $attributes */
    private function file(array $attributes = []): int
    {
        $now = date('Y-m-d H:i:s');
        $suffix = bin2hex(random_bytes(4));
        $id = (int) Db::table('files')->insertGetId(array_merge([
            'name'       => "f_{$suffix}.png",
            'path'       => "uploads/images/20260913/{$suffix}.png",
            'url'        => "/storage/uploads/images/20260913/{$suffix}.png",
            'mime_type'  => 'image/png',
            'extension'  => 'png',
            'size'       => 1024,
            'group'      => '默认',
            'upload_by'  => 0,
            'storage'    => 'local',
            'created_at' => $now,
            'updated_at' => $now,
        ], $attributes));
        $this->fileIds[] = $id;

        return $id;
    }

    public function test_keyword_filter_escapes_like_wildcards(): void
    {
        $underscore = $this->file(['name' => 'kw_a_b.png']);
        $this->file(['name' => 'kw_axb.png']);
        $repo = new FileRepository();

        $result = $repo->getFileList(['keyword' => 'kw_a_b'], 1, 20);

        // 未转义时 '_' 会当成「任意单字符」，把 kw_axb.png 也捞进来
        $this->assertSame([$underscore], array_map(static fn (array $row): int => (int) $row['id'], $result['list']));
    }

    public function test_group_filter_uses_the_reserved_word_without_sql_error(): void
    {
        $inGroup = $this->file(['group' => '素材库']);
        $this->file(['group' => '默认']);
        $repo = new FileRepository();

        $result = $repo->getFileList(['group' => '素材库'], 1, 20);

        $this->assertSame([$inGroup], array_map(static fn (array $row): int => (int) $row['id'], $result['list']));
    }

    public function test_mime_buckets_follow_tp8_classification(): void
    {
        $image = $this->file(['mime_type' => 'image/jpeg', 'extension' => 'jpg']);
        $video = $this->file(['mime_type' => 'video/mp4', 'extension' => 'mp4']);
        $audio = $this->file(['mime_type' => 'audio/mpeg', 'extension' => 'mp3']);
        $doc = $this->file(['mime_type' => 'application/pdf', 'extension' => 'pdf']);
        $zip = $this->file(['mime_type' => 'application/zip', 'extension' => 'zip']);
        $repo = new FileRepository();

        $ids = function (string $bucket) use ($repo): array {
            $rows = $repo->getFileList(['mime_type' => $bucket], 1, 100)['list'];

            return array_values(array_intersect(
                array_map(static fn (array $row): int => (int) $row['id'], $rows),
                $this->fileIds
            ));
        };

        $this->assertSame([$image], $ids('image'));
        $this->assertSame([$video], $ids('video'));
        $this->assertSame([$audio], $ids('audio'));
        $this->assertSame([$doc], $ids('document'));
        $this->assertSame([$zip], $ids('archive'));
        // other = 不是 image/video/audio，所以 pdf 与 zip 都在里面；getFileList 固定按 id desc
        // （newest first，见 test_list_is_paginated_newest_first）排序，zip 后插入、id 更大，排前面
        $this->assertSame([$zip, $doc], $ids('other'));
    }

    public function test_groups_aggregate_counts_and_exclude_soft_deleted(): void
    {
        $suffix = bin2hex(random_bytes(3));
        $big = "分组A{$suffix}";
        $small = "分组B{$suffix}";
        $this->file(['group' => $big]);
        $this->file(['group' => $big]);
        $this->file(['group' => $small]);
        $this->file(['group' => $small, 'deleted_at' => date('Y-m-d H:i:s')]);
        $repo = new FileRepository();

        $rows = array_values(array_filter(
            $repo->getGroupCounts(),
            static fn (array $row): bool => str_ends_with((string) $row['group'], $suffix)
        ));

        $this->assertSame(
            [['group' => $big, 'count' => 2], ['group' => $small, 'count' => 1]],
            $rows,
            '按 count desc 排序，且软删行不计入'
        );
    }

    public function test_base_update_writes_the_reserved_word_group_column(): void
    {
        // 移动分组、重命名、删除都按契约逐条走基类 Repository::update()/delete()，仓储不再包一层
        // 批量方法；这里验证保留字列 group 经基类 update() 能正常写入，且只动给定的那一行
        $moved = $this->file(['group' => '默认']);
        $kept = $this->file(['group' => '默认']);
        $repo = new FileRepository();

        $this->assertTrue($repo->update($moved, ['group' => '素材库']));
        $this->assertSame('素材库', Db::table('files')->where('id', $moved)->value('group'));
        $this->assertSame('默认', Db::table('files')->where('id', $kept)->value('group'));
    }

    public function test_list_is_paginated_newest_first(): void
    {
        $suffix = bin2hex(random_bytes(3));
        $first = $this->file(['group' => "页{$suffix}"]);
        $second = $this->file(['group' => "页{$suffix}"]);
        $repo = new FileRepository();

        $result = $repo->getFileList(['group' => "页{$suffix}"], 1, 1);

        $this->assertSame([$second], array_map(static fn (array $row): int => (int) $row['id'], $result['list']));
        $this->assertSame(
            ['current_page' => 1, 'per_page' => 1, 'total' => 2, 'last_page' => 2],
            $result['pagination']
        );
        $this->assertSame(
            [$first],
            array_map(static fn (array $row): int => (int) $row['id'], $repo->getFileList(['group' => "页{$suffix}"], 2, 1)['list'])
        );
    }
}
