<?php

declare(strict_types=1);

namespace app\adminapi\controller\message;

use app\adminapi\controller\AuthenticatedController;
use app\service\message\MessageTemplateService;
use core\permission\Permission;
use DI\Attribute\Inject;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Response;
use Webman\Http\Request;

/**
 * 消息模板（M6b spec §4.1）。
 *
 * 端点：
 *   GET    /adminapi/message/template         index   system.message.template.list
 *   GET    /adminapi/message/template/{id}    show    system.message.template.list
 *   POST   /adminapi/message/template         store   system.message.template.create
 *   PUT    /adminapi/message/template/{id}    update  system.message.template.update
 *   DELETE /adminapi/message/template/{id}    delete  system.message.template.delete
 *
 * validate() 的返回值就是字段白名单：规则里只有表单字段。编辑弹窗回传整行时，wechat_*_data、site_*、variables
 * 会被丢掉。code 只出现在 storeRules 里，编辑时传了也会被丢弃。
 * update 用 sometimes|required：不传就跳过（部分更新），传了空值必须校验失败。
 */
#[RouteGroup('/adminapi/message')]
class MessageTemplateController extends AuthenticatedController
{
    #[Inject]
    protected MessageTemplateService $messageTemplateService;

    #[Get('/template')]
    #[Permission('system.message.template.list')]
    public function index(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request);
        $params = $this->validate((array) $request->get(), $this->indexRules(), $this->messages());

        return $this->paginate($this->messageTemplateService->getList($params, $page, $limit));
    }

    #[Get('/template/{id:\d+}')]
    #[Permission('system.message.template.list')]
    public function show(Request $request, string $id): Response
    {
        return $this->success($this->messageTemplateService->getDetail((int) $id), lang('messages.get_success'));
    }

    #[Post('/template')]
    #[Permission('system.message.template.create')]
    public function store(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->storeRules(), $this->messages());
        $this->messageTemplateService->create($data);

        return $this->success([], lang('messages.create_success'));
    }

    #[Put('/template/{id:\d+}')]
    #[Permission('system.message.template.update')]
    public function update(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->updateRules(), $this->messages());
        $this->messageTemplateService->update((int) $id, $data);

        return $this->success([], lang('messages.update_success'));
    }

    #[Delete('/template/{id:\d+}')]
    #[Permission('system.message.template.delete')]
    public function delete(Request $request, string $id): Response
    {
        $this->messageTemplateService->delete((int) $id);

        return $this->success([], lang('messages.delete_success'));
    }

    /** @return array<string, string> */
    private function indexRules(): array
    {
        return [
            'keyword' => 'nullable|string|max:100',
            'status'  => 'nullable|in:0,1',
        ];
    }

    /** @return array<string, string> */
    private function storeRules(): array
    {
        return [
            'name'                        => 'required|string|max:100',
            'code'                        => 'required|string|max:50|regex:/^[a-z][a-z0-9_]*$/',
            'remark'                      => 'nullable|string|max:500',
            'status'                      => 'sometimes|required|integer|in:0,1',
            'sms_enabled'                 => 'sometimes|required|integer|in:0,1',
            'sms_template_id'             => 'nullable|string|max:100',
            'sms_content'                 => 'nullable|string|max:500',
            'wechat_official_enabled'     => 'sometimes|required|integer|in:0,1',
            'wechat_official_template_id' => 'nullable|string|max:100',
            'wechat_official_url'         => 'nullable|string|max:500',
            'wechat_mini_enabled'         => 'sometimes|required|integer|in:0,1',
            'wechat_mini_template_id'     => 'nullable|string|max:100',
            'wechat_mini_page'            => 'nullable|string|max:200',
            'email_enabled'               => 'sometimes|required|integer|in:0,1',
            'email_subject'               => 'nullable|string|max:200',
            'email_content'               => 'nullable|string|max:2000',
        ];
    }

    /** @return array<string, string> code 不在其中：编辑时不可改 */
    private function updateRules(): array
    {
        return [
            'name'                        => 'sometimes|required|string|max:100',
            'remark'                      => 'nullable|string|max:500',
            'status'                      => 'sometimes|required|integer|in:0,1',
            'sms_enabled'                 => 'sometimes|required|integer|in:0,1',
            'sms_template_id'             => 'nullable|string|max:100',
            'sms_content'                 => 'nullable|string|max:500',
            'wechat_official_enabled'     => 'sometimes|required|integer|in:0,1',
            'wechat_official_template_id' => 'nullable|string|max:100',
            'wechat_official_url'         => 'nullable|string|max:500',
            'wechat_mini_enabled'         => 'sometimes|required|integer|in:0,1',
            'wechat_mini_template_id'     => 'nullable|string|max:100',
            'wechat_mini_page'            => 'nullable|string|max:200',
            'email_enabled'               => 'sometimes|required|integer|in:0,1',
            'email_subject'               => 'nullable|string|max:200',
            'email_content'               => 'nullable|string|max:2000',
        ];
    }

    /**
     * message 值即 lang key（与 ValidatorFactory::resolveMessage 的约定一致）；未列出的规则用 validation.php 的通用文案。
     *
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'code.regex'     => 'message.template_code_format',
            'status.integer' => 'validation.status_integer',
            'status.in'      => 'validation.status_invalid',
        ];
    }
}
