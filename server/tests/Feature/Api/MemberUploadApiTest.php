<?php

declare(strict_types=1);

namespace tests\Feature\Api;

use support\Db;
use tests\Support\ApiTestCase;

/** C 端图片上传：已登录会员，校验与落盘走管理端同一套 UploadService。 */
final class MemberUploadApiTest extends ApiTestCase
{
    private const URI = '/api/common/upload/image';

    public function test_logged_in_member_can_upload_an_image(): void
    {
        $user = $this->actingAsUser();

        $data = $this->postFile(self::URI, 'file', 'avatar.png', "\x89PNG\r\n\x1a\nimage", 'image/png', $user->token)
            ->assertOk()
            ->data();
        $this->trackStorageFile((string) $data['path']);
        $id = Db::table('files')->where('path', $data['path'])->value('id');
        if ($id !== null) {
            $this->track('files', (int) $id);
        }

        $this->assertSame(['url', 'path', 'filename', 'size', 'storage'], array_keys($data));
        $this->assertStringStartsWith('uploads/images/', (string) $data['path']);
        $this->assertSame(0, (int) Db::table('files')->where('path', $data['path'])->value('upload_by'));
    }

    public function test_anonymous_upload_is_rejected(): void
    {
        $this->postFile(self::URI, 'file', 'avatar.png', "\x89PNG\r\n\x1a\nimage", 'image/png')
            ->assertCode(401);
    }
}
