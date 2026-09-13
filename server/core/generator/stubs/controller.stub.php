<?php
// controller.stub.php —— 渲染产物 key: controller → app/adminapi/controller/{module}/{Model}Controller.php。
//
// 可用模板变量只有底稿 §3.1 那一套；$formColumns 已由 ModuleBlueprint 筛好，模板里不得再过滤。
//
// 生成的代码必须满足（CLAUDE.md + spec §6.1、§14）：
//   1. 每个动作挂 #[Permission('{module}.{model_snake}.{action}')]，否则默认 403；
//   2. 只调 Service；写库只能用 $this->validate() 的返回值；
//   3. 校验规则写在私有方法里，create / update 两个场景靠
//      $required = $scene === 'create' ? 'required' : 'sometimes|required' 切换；
//   4. 批量删除固定校验 ids: required|array|min:1 与 ids.*: integer；
//   5. 【spec §14】私有校验方法 return 的数组必须是**字面量**：键是字段名、值是字符串，
//      唯一允许的插值是 {$required}。M2b 的 OpenAPI 推导器要从这个方法里读接口参数，
//      整个规则数组一旦动态拼出来（foreach 塞、array_merge、变量当键），推导器就读不出参数。
//      模板侧的循环只能发生在「渲染的时候」，不能出现在渲染结果里。
//
// 生成物里要输出 PHP 开标签时，一律用短输出标签回显字符串，不要在模板里直接写标签本身。
$service = lcfirst($model) . 'Service';
$rulesMethod = lcfirst($model) . 'Rules';
$permission = $module . '.' . $modelSnake;
$langPrefix = $module . '.' . $modelSnake;

// 规则值与消息键在这里一次算好：规则片段自带 nullable / sometimes 前导的原样单引号输出；
// 其余片段属于「必填类」，用双引号拼 {$required}，让两个场景切换必填写法。
//
// 消息键的后缀一律取 TypeInference::ruleTokens()——lang.stub.php 迭代的是同一个数组，
// 键在这边引用、在那边生成，只有共用一个来源才不会分叉（对不上就只会在运行时回显 key）。
// token 是语言键后缀，消息数组的键要的是 Laravel 规则名，两者只有三处不同名。
$ruleOfToken = ['require' => 'required', 'length' => 'max', 'invalid' => 'in'];
$ruleValues = [];
$messageKeys = [];
foreach ($formColumns as $column) {
    $fragment = $inference->validationRule($column);
    $needsRequired = !str_starts_with($fragment, 'nullable') && !str_starts_with($fragment, 'sometimes');
    if ($needsRequired) {
        $ruleValues[$column->name] = $fragment === '' ? '"{$required}"' : '"{$required}|' . $fragment . '"';
    } else {
        $ruleValues[$column->name] = "'" . $fragment . "'";
    }

    foreach ($inference->ruleTokens($column) as $token) {
        $rule = $ruleOfToken[$token] ?? $token;
        $messageKeys["'" . $column->name . '.' . $rule . "'"] = $langPrefix . '_' . $column->name . '_' . $token;
    }
}
$rulePad = 0;
foreach (array_keys($ruleValues) as $name) {
    $rulePad = max($rulePad, strlen((string) $name) + 2);
}
$messagePad = 0;
foreach (array_keys($messageKeys) as $key) {
    $messagePad = max($messagePad, strlen((string) $key));
}

// 类注释里的端点表：路径列与方法列对齐，宽度由最长的一条算出来
$endpoints = [
    ['GET', '', 'index', 'list'],
    ['POST', '/batch-delete', 'batchDelete', 'delete'],
    ['GET', '/{id}', 'show', 'list'],
    ['POST', '', 'store', 'create'],
    ['PUT', '/{id}', 'update', 'update'],
    ['DELETE', '/{id}', 'delete', 'delete'],
];
if ($hasStatus) {
    $endpoints[] = ['PUT', '/{id}/status', 'status', 'status'];
}
$base = '/adminapi/' . $module . '/' . $modelKebab;
$pathPad = 0;
foreach ($endpoints as $endpoint) {
    $pathPad = max($pathPad, strlen($base . $endpoint[1]) + 2);
}
?>
<?= '<?php' ?>


declare(strict_types=1);

namespace app\adminapi\controller\<?= $module ?>;

use app\service\<?= $module ?>\<?= $model ?>Service;
use core\base\Controller;
use core\permission\Permission;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/**
 * <?= $tableCommentPhpDoc ?>（由代码生成器生成）。
 *
 * 端点（具名/静态路径必须排在 {id} 通配路由之前注册，见 config/route/<?= $module ?>.php）：
<?php foreach ($endpoints as $endpoint) { ?>
 *   <?= str_pad($endpoint[0], 7) . str_pad($base . $endpoint[1], $pathPad) . str_pad($endpoint[2], 13) . $permission . '.' . $endpoint[3] ?>

<?php } ?>
 *
 * update 场景用 sometimes|required：字段不传时跳过（局部更新），传了空值必须校验失败。
 * 唯一性不在这里做成校验规则，由 <?= $model ?>Service 查重 + 唯一索引异常兜底（spec 决策 12）。
 *
 * <?= $rulesMethod ?>() 的 return 必须保持字面量形状（键是字段名、值是字符串，只有 {$required}
 * 一个插值）：M2b 的 OpenAPI 推导器要从这个私有方法里读接口参数（spec §14），改成运行时拼装
 * 就等于把这个模块从 API 文档里摘掉。
 */
class <?= $model ?>Controller extends Controller
{
    #[Inject]
    protected <?= $model ?>Service $<?= $service ?>;

    #[Permission('<?= $permission ?>.list')]
    public function index(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request);

        return $this->paginate($this-><?= $service ?>->get<?= $model ?>List((array) $request->get(), $page, $limit));
    }

    #[Permission('<?= $permission ?>.list')]
    public function show(Request $request, string $id): Response
    {
        return $this->success($this-><?= $service ?>->get<?= $model ?>Detail((int) $id), lang('messages.get_success'));
    }

    #[Permission('<?= $permission ?>.create')]
    public function store(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this-><?= $rulesMethod ?>('create'), $this->messages());

        return $this->success($this-><?= $service ?>->create<?= $model ?>($data), lang('messages.create_success'));
    }

    #[Permission('<?= $permission ?>.update')]
    public function update(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this-><?= $rulesMethod ?>('update'), $this->messages());
        $this-><?= $service ?>->update<?= $model ?>((int) $id, $data);

        return $this->success([], lang('messages.update_success'));
    }

    #[Permission('<?= $permission ?>.delete')]
    public function delete(Request $request, string $id): Response
    {
        $this-><?= $service ?>->delete<?= $model ?>((int) $id);

        return $this->success([], lang('messages.delete_success'));
    }

    #[Permission('<?= $permission ?>.delete')]
    public function batchDelete(Request $request): Response
    {
        $data = $this->validate($this->body($request), [
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
        ], [
            'ids.required'  => '<?= $langPrefix ?>_ids_require',
            'ids.array'     => '<?= $langPrefix ?>_ids_require',
            'ids.min'       => '<?= $langPrefix ?>_ids_require',
            'ids.*.integer' => '<?= $langPrefix ?>_ids_integer',
        ]);
        $this-><?= $service ?>->batchDelete(array_map('intval', (array) $data['ids']));

        return $this->success([], lang('messages.batch_delete_success'));
    }
<?php if ($hasStatus) { ?>

    #[Permission('<?= $permission ?>.status')]
    public function status(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), ['status' => 'required|integer|in:0,1'], [
            'status.required' => '<?= $langPrefix ?>_status_require',
            'status.integer'  => '<?= $langPrefix ?>_status_integer',
            'status.in'       => '<?= $langPrefix ?>_status_invalid',
        ]);
        $this-><?= $service ?>->updateStatus((int) $id, (int) $data['status']);

        return $this->success([], lang('messages.status_update_success'));
    }
<?php } ?>

    /**
     * 字段校验规则。create 场景必填、update 场景 sometimes|required（不传就跳过，传了空值要拒绝）。
     *
     * 这个数组必须一直是字面量：键是字段名、值是字符串，唯一允许的插值是 {$required}。
     * M2b 的 OpenAPI 推导器按这个形状读参数（spec §14）。
     *
     * @return array<string, string>
     */
    private function <?= $rulesMethod ?>(string $scene): array
    {
        $required = $scene === 'create' ? 'required' : 'sometimes|required';

        return [
<?php foreach ($ruleValues as $name => $value) { ?>
            <?= str_pad("'" . $name . "'", $rulePad) . ' => ' . $value . ',' ?>

<?php } ?>
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
<?php foreach ($messageKeys as $key => $langKey) { ?>
            <?= str_pad((string) $key, $messagePad) . " => '" . $langKey . "'," ?>

<?php } ?>
        ];
    }
}
