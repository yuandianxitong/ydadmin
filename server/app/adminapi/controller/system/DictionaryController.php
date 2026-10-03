<?php

declare(strict_types=1);

namespace app\adminapi\controller\system;

use app\adminapi\controller\AuthenticatedController;
use app\service\system\DictionaryService;
use core\permission\Permission;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Response;
use Webman\Http\Request;

/**
 * 数据字典（契约 §2.6）。
 *
 * 端点（具名路由在 {id} 通配路由之前注册）：
 *   GET    /adminapi/system/dictionary                 index         system.dictionary.list
 *   GET    /adminapi/system/dictionary/options          options       PermissionSkip
 *   GET    /adminapi/system/dictionary/batch-options    batchOptions  PermissionSkip
 *   POST   /adminapi/system/dictionary/batch-delete     batchDelete   system.dictionary.delete
 *   POST   /adminapi/system/dictionary/item             storeItem     system.dictionary.create
 *   PUT    /adminapi/system/dictionary/item/{id}        updateItem    system.dictionary.update
 *   DELETE /adminapi/system/dictionary/item/{id}        deleteItem    system.dictionary.delete
 *   GET    /adminapi/system/dictionary/{id}/items       items         system.dictionary.list
 *   GET    /adminapi/system/dictionary/{id}             show          system.dictionary.list
 *   POST   /adminapi/system/dictionary                  store         system.dictionary.create
 *   PUT    /adminapi/system/dictionary/{id}             update        system.dictionary.update
 *   DELETE /adminapi/system/dictionary/{id}             delete        system.dictionary.delete
 *
 * update 场景用 sometimes|required：字段不传时跳过（局部更新），传了空字符串必须校验失败。
 * 字典项 update 场景不含 dictionary_id：validate() 的返回值只含有规则的字段，请求里带了也会被丢弃。
 */
#[RouteGroup('/adminapi/system/dictionary')]
class DictionaryController extends AuthenticatedController
{
    #[Inject]
    protected DictionaryService $dictionaryService;

    #[Get('')]
    #[Permission('system.dictionary.list')]
    public function index(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request);

        return $this->paginate($this->dictionaryService->getDictionaryList((array) $request->get(), $page, $limit));
    }

    #[Get('/{id:\d+}')]
    #[Permission('system.dictionary.list')]
    public function show(Request $request, string $id): Response
    {
        return $this->success($this->dictionaryService->getDictionaryDetail((int) $id), lang('messages.get_success'));
    }

    #[Post('')]
    #[Permission('system.dictionary.create')]
    public function store(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->storeRules(), $this->messages());

        return $this->success($this->dictionaryService->createDictionary($data), lang('messages.create_success'));
    }

    #[Put('/{id:\d+}')]
    #[Permission('system.dictionary.update')]
    public function update(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->updateRules(), $this->messages());
        $this->dictionaryService->updateDictionary((int) $id, $data);

        return $this->success([], lang('messages.update_success'));
    }

    #[Delete('/{id:\d+}')]
    #[Permission('system.dictionary.delete')]
    public function delete(Request $request, string $id): Response
    {
        $this->dictionaryService->deleteDictionary((int) $id);

        return $this->success([], lang('messages.delete_success'));
    }

    #[Post('/batch-delete')]
    #[Permission('system.dictionary.delete')]
    public function batchDelete(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->batchDeleteRules(), [
            'ids.required'  => 'validation.dict_ids_require',
            'ids.array'     => 'validation.dict_ids_require',
            'ids.min'       => 'validation.dict_ids_require',
            'ids.*.integer' => 'validation.dict_ids_integer',
        ]);
        $this->dictionaryService->batchDeleteDictionaries(array_map('intval', (array) $data['ids']));

        return $this->success([], lang('messages.batch_delete_success'));
    }

    #[Get('/options')]
    #[PermissionSkip]
    public function options(Request $request): Response
    {
        $data = $this->validate(['code' => $request->get('code')], $this->optionsRules(), [
            'code.required' => 'validation.dict_code_require',
            'code.string'   => 'validation.dict_code_require',
            'code.max'      => 'validation.dict_code_length',
        ]);

        return $this->success($this->dictionaryService->getOptionsByCode((string) $data['code']), lang('messages.get_success'));
    }

    #[Get('/batch-options')]
    #[PermissionSkip]
    public function batchOptions(Request $request): Response
    {
        // 逗号分隔与数组两种写法先归一成数组再校验，上限对两种写法一致生效；
        // 去掉空白项，'' 与 [] 一样按「必填」拒绝（与归一化之前的行为一致）
        $codes = $request->get('codes');
        if (is_string($codes)) {
            $codes = explode(',', $codes);
        }
        if (is_array($codes)) {
            $codes = array_values(array_filter(
                array_map(static fn (mixed $code): string => is_scalar($code) ? trim((string) $code) : '', $codes),
                static fn (string $code): bool => $code !== ''
            ));
        }
        $data = $this->validate(['codes' => $codes], $this->batchOptionsRules(), [
            'codes.required' => 'validation.dict_codes_require',
            'codes.array'    => 'validation.dict_codes_require',
            'codes.max'      => 'validation.dict_codes_max',
        ]);

        return $this->success($this->dictionaryService->getOptionsByCodes((array) $data['codes']), lang('messages.get_success'));
    }

    #[Get('/{id:\d+}/items')]
    #[Permission('system.dictionary.list')]
    public function items(Request $request, string $id): Response
    {
        return $this->success($this->dictionaryService->getItemList((int) $id), lang('messages.get_success'));
    }

    #[Post('/item')]
    #[Permission('system.dictionary.create')]
    public function storeItem(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->storeItemRules(), $this->messages());

        return $this->success($this->dictionaryService->createItem($data), lang('messages.create_success'));
    }

    #[Put('/item/{id:\d+}')]
    #[Permission('system.dictionary.update')]
    public function updateItem(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->updateItemRules(), $this->messages());
        $this->dictionaryService->updateItem((int) $id, $data);

        return $this->success([], lang('messages.update_success'));
    }

    #[Delete('/item/{id:\d+}')]
    #[Permission('system.dictionary.delete')]
    public function deleteItem(Request $request, string $id): Response
    {
        $this->dictionaryService->deleteItem((int) $id);

        return $this->success([], lang('messages.delete_success'));
    }

    /** @return array<string, string> */
    private function dictionaryRules(string $scene): array
    {
        $required = $scene === 'create' ? 'required' : 'sometimes|required';

        return [
            'name'        => "{$required}|string|max:100",
            'code'        => "{$required}|string|max:100|alpha_dash:ascii",
            'description' => 'nullable|string|max:500',
            'status'      => 'sometimes|required|integer|in:0,1',
            'sort'        => 'sometimes|required|integer|min:0',
        ];
    }

    /** @return array<string, string> */
    private function itemRules(string $scene): array
    {
        $required = $scene === 'create' ? 'required' : 'sometimes|required';
        $rules = [
            'label'       => "{$required}|string|max:100",
            'value'       => "{$required}|string|max:100",
            'tag_type'    => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
            'status'      => 'sometimes|required|integer|in:0,1',
            'sort'        => 'sometimes|required|integer|min:0',
        ];

        return $scene === 'create' ? ['dictionary_id' => 'required|integer|min:1'] + $rules : $rules;
    }

    /**
     * 薄包装，委派给既有的 dictionaryRules('create')。
     *
     * @return array<string, string>
     */
    private function storeRules(): array
    {
        return $this->dictionaryRules('create');
    }

    /**
     * 薄包装，委派给既有的 dictionaryRules('update')。
     *
     * @return array<string, string>
     */
    private function updateRules(): array
    {
        return $this->dictionaryRules('update');
    }

    /** @return array<string, string> */
    private function batchDeleteRules(): array
    {
        return [
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
        ];
    }

    /** @return array<string, string> */
    private function optionsRules(): array
    {
        return ['code' => 'required|string|max:100'];
    }

    /**
     * 常量拼接，容器实调即得真值（spec §6 特例二）。注意：本方法只出规则，
     * options() 里对 codes 的逗号拆分/去空白/丢空项等数据整形不属于这里，留在动作方法内。
     *
     * @return array<string, string>
     */
    private function batchOptionsRules(): array
    {
        return ['codes' => 'required|array|max:' . DictionaryService::MAX_BATCH_CODES];
    }

    /**
     * 薄包装，委派给既有的 itemRules('create')。
     *
     * @return array<string, string>
     */
    private function storeItemRules(): array
    {
        return $this->itemRules('create');
    }

    /**
     * 薄包装，委派给既有的 itemRules('update')。
     *
     * @return array<string, string>
     */
    private function updateItemRules(): array
    {
        return $this->itemRules('update');
    }

    /**
     * message 值即 lang key（与 ValidatorFactory::resolveMessage 的约定一致）。
     *
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'name.required'         => 'validation.dict_name_require',
            'name.string'           => 'validation.dict_name_require',
            'name.max'              => 'validation.dict_name_length',
            'code.required'         => 'validation.dict_code_require',
            'code.string'           => 'validation.dict_code_require',
            'code.max'              => 'validation.dict_code_length',
            'code.alpha_dash'       => 'validation.dict_code_alpha_dash',
            'description.max'       => 'validation.dict_description_max',
            'dictionary_id.required' => 'validation.dict_id_require',
            'dictionary_id.integer' => 'validation.dict_id_integer',
            'dictionary_id.min'     => 'validation.dict_id_integer',
            'label.required'        => 'validation.dict_label_require',
            'label.string'          => 'validation.dict_label_require',
            'label.max'             => 'validation.dict_label_length',
            'value.required'        => 'validation.dict_value_require',
            'value.string'          => 'validation.dict_value_require',
            'value.max'             => 'validation.dict_value_length',
            'tag_type.max'          => 'validation.dict_tag_type_max',
            'status.required'       => 'validation.status_invalid',
            'status.integer'        => 'validation.status_integer',
            'status.in'             => 'validation.status_invalid',
            'sort.required'         => 'validation.sort_integer',
            'sort.integer'          => 'validation.sort_integer',
            'sort.min'              => 'validation.sort_min',
        ];
    }
}
