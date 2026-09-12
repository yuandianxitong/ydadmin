<?php

declare(strict_types=1);

namespace tests\Feature\System;

use support\Cache;
use support\Db;
use tests\Support\ApiTestCase;
use tests\Support\TestAdmin;

final class DictionaryApiTest extends ApiTestCase
{
    private const BASE = '/adminapi/system/dictionary';

    private const ALL = ['system.dictionary.list', 'system.dictionary.create', 'system.dictionary.update', 'system.dictionary.delete'];

    private function uniqueCode(): string
    {
        return 'dict_' . bin2hex(random_bytes(4));
    }

    /** @param array<string, mixed> $overrides */
    private function createDictionary(TestAdmin $actor, array $overrides = []): int
    {
        $id = (int) $this->post(self::BASE, array_merge(['name' => '接口字典', 'code' => $this->uniqueCode()], $overrides), $actor->token)->assertOk()->data()['id'];
        $this->track('dictionaries', $id);

        return $id;
    }

    /** @param array<string, mixed> $overrides */
    private function createItem(TestAdmin $actor, int $dictionaryId, array $overrides = []): int
    {
        $id = (int) $this->post(self::BASE . '/item', array_merge(['dictionary_id' => $dictionaryId, 'label' => '选项', 'value' => bin2hex(random_bytes(3))], $overrides), $actor->token)->assertOk()->data()['id'];
        $this->track('dictionary_items', $id);

        return $id;
    }

    private function codeOf(int $dictionaryId): string
    {
        return (string) Db::table('dictionaries')->where('id', $dictionaryId)->value('code');
    }

    /** @return list<string> */
    private function optionValues(TestAdmin $actor, string $code): array
    {
        return array_column($this->get(self::BASE . '/options', ['code' => $code], $actor->token)->assertOk()->data(), 'value');
    }

    /** @return list<string> */
    private function optionLabels(TestAdmin $actor, string $code): array
    {
        return array_column($this->get(self::BASE . '/options', ['code' => $code], $actor->token)->assertOk()->data(), 'label');
    }

    public function test_menu_and_sample_dictionary_seeds(): void
    {
        $menus = Db::table('menus')->whereBetween('id', [60, 63])->orderBy('id')->get(['id', 'parent_id', 'type', 'permission'])->all();
        $this->assertSame(
            [[60, 2, 2, 'system.dictionary.list'], [61, 60, 3, 'system.dictionary.create'], [62, 60, 3, 'system.dictionary.update'], [63, 60, 3, 'system.dictionary.delete']],
            array_map(static fn (object $menu): array => [(int) $menu->id, (int) $menu->parent_id, (int) $menu->type, (string) $menu->permission], $menus)
        );
        $this->assertSame('/system/dictionary/index', Db::table('menus')->where('id', 60)->value('component'));

        $admin = $this->actingAsAdmin();
        $this->assertSame(['1', '2', '0'], $this->optionValues($admin, 'gender'));
        $this->assertSame(['1', '0'], $this->optionValues($admin, 'common_status'));
    }

    public function test_index_rows_have_items_count_and_filters(): void
    {
        $admin = $this->actingAsAdmin(self::ALL);
        $code = $this->uniqueCode();
        $id = $this->createDictionary($admin, ['name' => '计数字典', 'code' => $code]);
        $this->createItem($admin, $id, ['value' => 'a']);
        $this->createItem($admin, $id, ['value' => 'b', 'status' => 0]);

        $data = $this->get(self::BASE, ['keyword' => $code], $admin->token)->assertOk()->data();
        $this->assertSame(1, $data['pagination']['total']);
        $this->assertSame($id, $data['list'][0]['id']);
        $this->assertSame(2, (int) $data['list'][0]['items_count'], 'items_count 不分状态');

        $this->assertSame(0, $this->get(self::BASE, ['keyword' => $code, 'status' => 0], $admin->token)->assertOk()->data()['pagination']['total']);
    }

    public function test_show_and_items_include_disabled_items_in_sort_order(): void
    {
        $admin = $this->actingAsAdmin(self::ALL);
        $id = $this->createDictionary($admin);
        $enabled = $this->createItem($admin, $id, ['value' => 'x', 'sort' => 2]);
        $disabled = $this->createItem($admin, $id, ['value' => 'y', 'sort' => 1, 'status' => 0]);

        $data = $this->get(self::BASE . "/{$id}", [], $admin->token)->assertOk()->data();
        $this->assertSame($id, $data['id']);
        $this->assertSame([$disabled, $enabled], array_column($data['items'], 'id'));
        $this->assertSame([$disabled, $enabled], array_column($this->get(self::BASE . "/{$id}/items", [], $admin->token)->assertOk()->data(), 'id'));

        $this->assertSame(lang('business.dict_not_found'), $this->get(self::BASE . '/999999', [], $admin->token)->assertCode(400)->message());
        $this->assertSame(lang('business.dict_not_found'), $this->get(self::BASE . '/999999/items', [], $admin->token)->assertCode(400)->message());
    }

    public function test_store_and_update_validation_and_code_uniqueness(): void
    {
        $admin = $this->actingAsAdmin(self::ALL);

        $this->assertSame(lang('business.dict_code_exists'), $this->post(self::BASE, ['name' => '重复', 'code' => 'gender'], $admin->token)->assertCode(400)->message());
        $response = $this->post(self::BASE, ['name' => '非法编码', 'code' => 'bad:code'], $admin->token)->assertCode(422);
        $this->assertSame(lang('validation.dict_code_alpha_dash'), $response->data()['errors']['code']);
        $this->post(self::BASE, ['code' => $this->uniqueCode()], $admin->token)->assertCode(422);

        $id = $this->createDictionary($admin);
        $this->assertSame(lang('business.dict_code_exists'), $this->put(self::BASE . "/{$id}", ['code' => 'gender'], $admin->token)->assertCode(400)->message());
        $this->put(self::BASE . "/{$id}", ['name' => '改名', 'code' => $this->codeOf($id)], $admin->token)->assertOk();
        $this->assertSame('改名', Db::table('dictionaries')->where('id', $id)->value('name'));

        foreach (['name', 'code', 'status', 'sort'] as $field) {
            $response = $this->put(self::BASE . "/{$id}", [$field => ''], $admin->token);
            $response->assertCode(422);
            $this->assertArrayHasKey($field, $response->data()['errors']);
        }
        $this->assertSame(1, (int) Db::table('dictionaries')->where('id', $id)->value('status'));
    }

    /** 唯一索引对软删行同样生效：复用已删除字典的编码要返回业务错误，不能 500。 */
    public function test_reusing_the_code_of_a_deleted_dictionary_is_a_business_error(): void
    {
        $admin = $this->actingAsAdmin(self::ALL);
        $code = $this->uniqueCode();
        $id = $this->createDictionary($admin, ['code' => $code]);
        $this->delete(self::BASE . "/{$id}", [], $admin->token)->assertOk();

        $response = $this->post(self::BASE, ['name' => '复用编码', 'code' => $code], $admin->token);
        $this->assertSame(200, $response->status());
        $this->assertSame(lang('business.dict_code_exists'), $response->assertCode(400)->message());
    }

    public function test_item_value_is_unique_within_its_dictionary(): void
    {
        $admin = $this->actingAsAdmin(self::ALL);
        $a = $this->createDictionary($admin);
        $b = $this->createDictionary($admin);
        $first = $this->createItem($admin, $a, ['value' => 'v1']);
        $this->createItem($admin, $b, ['value' => 'v1']);
        $second = $this->createItem($admin, $a, ['value' => 'v2']);

        $this->assertSame(lang('business.dict_item_value_exists'), $this->post(self::BASE . '/item', ['dictionary_id' => $a, 'label' => '重复', 'value' => 'v1'], $admin->token)->assertCode(400)->message());
        $this->assertSame(lang('business.dict_item_value_exists'), $this->put(self::BASE . "/item/{$second}", ['value' => 'v1'], $admin->token)->assertCode(400)->message());
        $this->assertSame(lang('business.dict_not_found'), $this->post(self::BASE . '/item', ['dictionary_id' => 999999, 'label' => '孤儿', 'value' => 'x'], $admin->token)->assertCode(400)->message());
        $this->assertSame(lang('business.dict_item_not_found'), $this->put(self::BASE . '/item/999999', ['label' => 'x'], $admin->token)->assertCode(400)->message());
        $this->post(self::BASE . '/item', ['dictionary_id' => $a, 'label' => '缺值'], $admin->token)->assertCode(422);

        // update 不能把字典项改挂到别的字典：dictionary_id 不在 update 规则里，被丢弃
        $this->put(self::BASE . "/item/{$second}", ['dictionary_id' => $b, 'label' => '改标签'], $admin->token)->assertOk();
        $row = Db::table('dictionary_items')->where('id', $second)->first();
        $this->assertSame($a, (int) $row->dictionary_id);
        $this->assertSame('改标签', $row->label);

        // 软删的项仍占着唯一索引：业务错误，不是 500
        $this->delete(self::BASE . "/item/{$first}", [], $admin->token)->assertOk();
        $this->assertNotNull(Db::table('dictionary_items')->where('id', $first)->value('deleted_at'));
        $response = $this->post(self::BASE . '/item', ['dictionary_id' => $a, 'label' => '复用', 'value' => 'v1'], $admin->token);
        $this->assertSame(200, $response->status());
        $this->assertSame(lang('business.dict_item_value_exists'), $response->assertCode(400)->message());
    }

    public function test_options_are_cached_and_every_write_invalidates_them(): void
    {
        $admin = $this->actingAsAdmin(self::ALL);
        $viewer = $this->actingAsAdmin();
        $code = $this->uniqueCode();
        $id = $this->createDictionary($admin, ['code' => $code]);
        $item = $this->createItem($admin, $id, ['label' => '甲', 'value' => '1', 'sort' => 1]);
        $this->createItem($admin, $id, ['label' => '乙', 'value' => '2', 'sort' => 2, 'status' => 0]);

        $this->assertSame(['甲'], $this->optionLabels($viewer, $code), '只返回启用项；无权限点也能取（PermissionSkip）');
        $this->assertIsArray(Cache::get("dict.{$code}"), '结果写入 dict.{code}');

        // 绕过接口直接改库：命中缓存，仍是旧值
        Db::table('dictionary_items')->where('id', $item)->update(['label' => '直改']);
        $this->assertSame(['甲'], $this->optionLabels($viewer, $code));

        $this->put(self::BASE . "/item/{$item}", ['label' => '丙'], $admin->token)->assertOk();
        $this->assertSame(['丙'], $this->optionLabels($viewer, $code), '改字典项后缓存被清');

        $this->createItem($admin, $id, ['label' => '丁', 'value' => '3', 'sort' => 3]);
        $this->assertSame(['丙', '丁'], $this->optionLabels($viewer, $code), '新增字典项后缓存被清');

        $this->delete(self::BASE . "/item/{$item}", [], $admin->token)->assertOk();
        $this->assertSame(['丁'], $this->optionLabels($viewer, $code), '删除字典项后缓存被清');

        $this->put(self::BASE . "/{$id}", ['status' => 0], $admin->token)->assertOk();
        $this->assertSame([], $this->optionLabels($viewer, $code), '禁用字典后返回空');

        $this->put(self::BASE . "/{$id}", ['status' => 1], $admin->token)->assertOk();
        $this->assertSame(['丁'], $this->optionLabels($viewer, $code), '重新启用后恢复');
    }

    public function test_changing_the_code_clears_both_old_and_new_codes(): void
    {
        $admin = $this->actingAsAdmin(self::ALL);
        $old = $this->uniqueCode();
        $new = $this->uniqueCode();
        $id = $this->createDictionary($admin, ['code' => $old]);
        $this->createItem($admin, $id, ['value' => 'v']);

        $this->assertSame(['v'], $this->optionValues($admin, $old));
        $this->assertSame([], $this->optionValues($admin, $new), '新编码此时被缓存为「不存在」');

        $this->put(self::BASE . "/{$id}", ['code' => $new], $admin->token)->assertOk();

        $this->assertSame([], $this->optionValues($admin, $old));
        $this->assertSame(['v'], $this->optionValues($admin, $new));
    }

    public function test_new_dictionary_clears_a_cached_miss(): void
    {
        $admin = $this->actingAsAdmin(self::ALL);
        $code = $this->uniqueCode();
        $this->assertSame([], $this->optionValues($admin, $code));

        $id = $this->createDictionary($admin, ['code' => $code]);
        $this->createItem($admin, $id, ['value' => 'n']);

        $this->assertSame(['n'], $this->optionValues($admin, $code));
    }

    public function test_batch_options_is_keyed_by_code(): void
    {
        $admin = $this->actingAsAdmin();

        $data = $this->get(self::BASE . '/batch-options', ['codes' => 'gender, common_status,missing_code,bad:code,gender'], $admin->token)->assertOk()->data();
        $this->assertSame(['gender', 'common_status', 'missing_code', 'bad:code'], array_keys($data));
        $this->assertSame(['1', '2', '0'], array_column($data['gender'], 'value'));
        $this->assertSame([], $data['missing_code']);
        $this->assertSame([], $data['bad:code'], '非法编码不查库、不写缓存，也不 500');

        $this->assertSame(['gender'], array_keys($this->get(self::BASE . '/batch-options', ['codes' => ['gender']], $admin->token)->assertOk()->data()));

        $this->get(self::BASE . '/batch-options', [], $admin->token)->assertCode(422);
        $this->get(self::BASE . '/options', [], $admin->token)->assertCode(422);
        $this->assertSame([], $this->get(self::BASE . '/options', ['code' => 'bad:code'], $admin->token)->assertOk()->data());
    }

    /**
     * batch-options 的 codes 必须有上限：接口是 PermissionSkip，每个未知但合法的编码都要查一次库、
     * 再写一条 7200 秒的负缓存，不限量就能靠一次请求把 Redis（还存着 token 版本号与黑名单）撑起来。
     */
    public function test_batch_options_caps_the_number_of_codes(): void
    {
        $admin = $this->actingAsAdmin();
        $fifty = array_map(static fn (int $i): string => 'cap_' . $i, range(1, 50));
        $fiftyOne = [...$fifty, 'cap_51'];

        $this->assertCount(50, $this->get(self::BASE . '/batch-options', ['codes' => implode(',', $fifty)], $admin->token)->assertOk()->data(), '50 个仍然正常');
        $this->assertCount(50, $this->get(self::BASE . '/batch-options', ['codes' => $fifty], $admin->token)->assertOk()->data(), '数组形式同样放行');

        foreach ([implode(',', $fiftyOne), $fiftyOne] as $codes) {
            $response = $this->get(self::BASE . '/batch-options', ['codes' => $codes], $admin->token);
            $response->assertCode(422);
            $this->assertSame(lang('validation.dict_codes_max'), $response->data()['errors']['codes']);
        }
    }

    public function test_delete_cascades_to_items_and_batch_delete_is_atomic(): void
    {
        $admin = $this->actingAsAdmin(self::ALL);
        $id = $this->createDictionary($admin);
        $code = $this->codeOf($id);
        $item = $this->createItem($admin, $id, ['value' => 'x']);
        $this->assertSame(['x'], $this->optionValues($admin, $code));

        $this->delete(self::BASE . "/{$id}", [], $admin->token)->assertOk();
        $this->assertNotNull(Db::table('dictionaries')->where('id', $id)->value('deleted_at'));
        $this->assertNotNull(Db::table('dictionary_items')->where('id', $item)->value('deleted_at'), '字典项随字典级联删除');
        $this->assertSame([], $this->optionValues($admin, $code), '删除后缓存被清');
        $this->assertSame(lang('business.dict_not_found'), $this->delete(self::BASE . "/{$id}", [], $admin->token)->assertCode(400)->message());

        $a = $this->createDictionary($admin);
        $b = $this->createDictionary($admin);
        $this->createItem($admin, $b, ['value' => 'y']);

        $this->assertSame(lang('business.dict_not_found'), $this->post(self::BASE . '/batch-delete', ['ids' => [$a, 999999]], $admin->token)->assertCode(400)->message());
        $this->assertNull(Db::table('dictionaries')->where('id', $a)->value('deleted_at'), '混入不存在的 id 时整体回滚');

        $this->post(self::BASE . '/batch-delete', ['ids' => [$a, $b, $b]], $admin->token)->assertOk();
        $this->assertSame(2, Db::table('dictionaries')->whereIn('id', [$a, $b])->whereNotNull('deleted_at')->count());
        $this->assertSame(0, Db::table('dictionary_items')->where('dictionary_id', $b)->whereNull('deleted_at')->count());

        $this->post(self::BASE . '/batch-delete', ['ids' => []], $admin->token)->assertCode(422);
        $this->post(self::BASE . '/batch-delete', ['ids' => ['abc']], $admin->token)->assertCode(422);
    }

    public function test_permission_points(): void
    {
        $viewer = $this->actingAsAdmin(['system.dictionary.list']);
        $nobody = $this->actingAsAdmin();

        $this->get(self::BASE, [], $viewer->token)->assertOk();
        $this->post(self::BASE, ['name' => '越权', 'code' => $this->uniqueCode()], $viewer->token)->assertCode(403);
        $this->post(self::BASE . '/item', ['dictionary_id' => 1, 'label' => '越权', 'value' => 'z'], $viewer->token)->assertCode(403);
        $this->delete(self::BASE . '/item/1', [], $viewer->token)->assertCode(403);
        $this->get(self::BASE, [], $nobody->token)->assertCode(403);
        $this->get(self::BASE . '/options', ['code' => 'gender'], $nobody->token)->assertOk();
    }
}
