<?php

declare(strict_types=1);

namespace tests\Feature\Agreement;

use support\Db;
use tests\Support\ApiTestCase;

final class AgreementApiTest extends ApiTestCase
{
    public function test_create_rejects_duplicate_code_and_update_ignores_code(): void
    {
        $admin = $this->actingAsAdmin('super');
        $this->post('/adminapi/agreement', ['title' => 'A', 'code' => 'user_agreement', 'content' => 'x', 'status' => 1], $admin->token)->assertCode(422);
        $created = $this->post('/adminapi/agreement', ['title' => 'T', 'code' => 't'.bin2hex(random_bytes(4)), 'content' => 'c', 'status' => 1], $admin->token)->assertOk()->data();
        $this->track('agreements', (int) $created['id']);
        $this->put('/adminapi/agreement/'.$created['id'], ['title' => 'T2', 'code' => 'hacked_code', 'content' => 'c2', 'status' => 1], $admin->token)->assertOk();
        $row = Db::table('agreements')->where('id', $created['id'])->first();
        $this->assertSame($created['code'], $row->code);
        $this->assertSame('T2', $row->title);
    }

    public function test_c_end_get_by_code_hides_disabled(): void
    {
        $admin = $this->actingAsAdmin('super');
        $code = 't'.bin2hex(random_bytes(4));
        $row = $this->post('/adminapi/agreement', ['title' => 'X', 'code' => $code, 'content' => '<p>z</p>', 'status' => 0], $admin->token)->assertOk()->data();
        $this->track('agreements', (int) $row['id']);
        $this->get('/api/agreement/'.$code)->assertCode(404);
        $this->put('/adminapi/agreement/'.$row['id'], ['title' => 'X', 'content' => '<p>z</p>', 'status' => 1], $admin->token)->assertOk();
        $data = $this->get('/api/agreement/'.$code)->assertOk()->data();
        $this->assertSame($code, $data['code']);
        $this->get('/api/agreement/user_agreement')->assertOk();
    }
}
