<?php

declare(strict_types=1);

namespace app\adminapi\controller\agreement;

use app\service\agreement\AgreementService;
use core\base\Controller;
use core\permission\Permission;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/**
 * 协议（由代码生成器生成）。
 *
 * 端点（具名/静态路径必须排在 {id} 通配路由之前注册，见 config/route/agreement.php）：
 *   GET    /adminapi/agreement/list              index   agreement.list
 *   GET    /adminapi/agreement/detail/{id}       show    agreement.list
 *   POST   /adminapi/agreement                   store   agreement.create
 *   PUT    /adminapi/agreement/{id}              update  agreement.update
 *   DELETE /adminapi/agreement/{id}              delete  agreement.delete
 *
 * 无独立 status 路由：启用/禁用走 update 的 status 字段。
 * update 场景用 sometimes|required：字段不传时跳过（局部更新），传了空值必须校验失败。
 * code 只出现在 storeRules 里；编辑时客户端传了也会被丢掉。
 * 唯一性不在这里做成校验规则，由 AgreementService 查重 + 唯一索引异常兜底（422 errors.code）。
 *
 * store()/update()/batchDelete() 各自的校验规则由同名的 xxxRules() 无参私有方法
 * 提供：M2b 的 RuleReflector 按动作名反射调用 "{action}Rules"（spec §5、§14），方法必须无参、
 * 纯函数——少了任何一个动作的这层包装，文档就会静默漏掉那个端点的参数，且不会有任何报错
 * （check:context 规则七拦这个，见 scripts/check-context-discipline.sh）。
 */
class AgreementController extends Controller
{
    #[Inject]
    protected AgreementService $agreementService;

    #[Permission('agreement.list')]
    public function index(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request, 20);

        return $this->paginate($this->agreementService->getAgreementList((array) $request->get(), $page, $limit));
    }

    #[Permission('agreement.list')]
    public function show(Request $request, string $id): Response
    {
        return $this->success($this->agreementService->getAgreementDetail((int) $id), lang('messages.get_success'));
    }

    #[Permission('agreement.create')]
    public function store(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->storeRules(), $this->messages());

        return $this->success($this->agreementService->createAgreement($data), lang('messages.create_success'));
    }

    #[Permission('agreement.update')]
    public function update(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->updateRules(), $this->messages());
        $this->agreementService->updateAgreement((int) $id, $data);

        return $this->success([], lang('messages.update_success'));
    }

    #[Permission('agreement.delete')]
    public function delete(Request $request, string $id): Response
    {
        $this->agreementService->deleteAgreement((int) $id);

        return $this->success([], lang('messages.delete_success'));
    }

    #[Permission('agreement.delete')]
    public function batchDelete(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->batchDeleteRules(), [
            'ids.required'  => 'agreement.agreement_ids_require',
            'ids.array'     => 'agreement.agreement_ids_require',
            'ids.min'       => 'agreement.agreement_ids_require',
            'ids.*.integer' => 'agreement.agreement_ids_integer',
        ]);
        $this->agreementService->batchDelete(array_map('intval', (array) $data['ids']));

        return $this->success([], lang('messages.batch_delete_success'));
    }

    /**
     * store 场景的字段校验规则。M2b 的 RuleReflector 按动作名反射调用 "storeRules"（spec §5、§14）。
     *
     * @return array<string, string>
     */
    private function storeRules(): array
    {
        return [
            'title'   => 'required|string|max:200',
            'code'    => 'required|string|max:50|regex:/^[a-z][a-z0-9_]{1,49}$/',
            'content' => 'nullable|string',
            'status'  => 'sometimes|required|integer|in:0,1',
        ];
    }

    /**
     * update 场景：白名单只有 title/content/status，不含 code。
     *
     * @return array<string, string>
     */
    private function updateRules(): array
    {
        return [
            'title'   => 'sometimes|required|string|max:200',
            'content' => 'nullable|string',
            'status'  => 'sometimes|required|integer|in:0,1',
        ];
    }

    /**
     * 批量删除固定校验：ids 必须是非空数组，元素必须是整数（CLAUDE.md + spec §6.1 第 4 条）。
     *
     * @return array<string, string>
     */
    private function batchDeleteRules(): array
    {
        return [
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
        ];
    }

    /**
     * message 的值即 lang key（与 ValidatorFactory::resolveMessage 的约定一致）。
     *
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'title.required'  => 'agreement.agreement_title_require',
            'title.max'       => 'agreement.agreement_title_length',
            'code.required'   => 'agreement.agreement_code_require',
            'code.max'        => 'agreement.agreement_code_length',
            'code.regex'      => 'agreement.code_format',
            'status.required' => 'agreement.agreement_status_require',
            'status.integer'  => 'agreement.agreement_status_integer',
            'status.in'       => 'agreement.agreement_status_invalid',
        ];
    }
}
