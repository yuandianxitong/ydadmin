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
    ['GET', '/{id:\d+}', 'show', 'list'],
    ['POST', '', 'store', 'create'],
    ['PUT', '/{id:\d+}', 'update', 'update'],
    ['DELETE', '/{id:\d+}', 'delete', 'delete'],
];
if ($hasStatus) {
    $endpoints[] = ['PUT', '/{id:\d+}/status', 'status', 'status'];
}
$base = '/adminapi/' . $module . '/' . $modelKebab;
$pathPad = 0;
foreach ($endpoints as $endpoint) {
    $pathPad = max($pathPad, strlen($base . $endpoint[1]) + 2);
}
?>
<?= '<?php' ?>


// 由代码生成器生成，`php start.php reload` 后生效。

declare(strict_types=1);

namespace app\adminapi\controller\<?= $module ?>;

use app\adminapi\controller\AuthenticatedController;
use app\service\<?= $module ?>\<?= $model ?>Service;
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
 * <?= $tableCommentPhpDoc ?>（由代码生成器生成）。
 *
 * 端点：
<?php foreach ($endpoints as $endpoint) { ?>
 *   <?= str_pad($endpoint[0], 7) . str_pad($base . $endpoint[1], $pathPad) . str_pad($endpoint[2], 13) . $permission . '.' . $endpoint[3] ?>

<?php } ?>
 *
 * update 场景用 sometimes|required：字段不传时跳过（局部更新），传了空值必须校验失败。
 * 唯一性不在这里做成校验规则，由 <?= $model ?>Service 查重 + 唯一索引异常兜底（spec 决策 12）。
 *
 * store()/update()/batchDelete()/status() 各自的校验规则由同名的 xxxRules() 无参私有方法
 * 提供：M2b 的 RuleReflector 按动作名反射调用 "{action}Rules"（spec §5、§14），方法必须无参、
 * 纯函数——少了任何一个动作的这层包装，文档就会静默漏掉那个端点的参数，且不会有任何报错
 * （check:context 规则七拦这个，见 scripts/check-context-discipline.sh）。
 */
#[RouteGroup('/adminapi/<?= $module ?>/<?= $modelKebab ?>')]
class <?= $model ?>Controller extends AuthenticatedController
{
    #[Inject]
    protected <?= $model ?>Service $<?= $service ?>;

    #[Get('')]
    #[Permission('<?= $permission ?>.list')]
    public function index(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request);

        return $this->paginate($this-><?= $service ?>->get<?= $model ?>List((array) $request->get(), $page, $limit));
    }

    #[Get('/{id:\d+}')]
    #[Permission('<?= $permission ?>.list')]
    public function show(Request $request, string $id): Response
    {
        return $this->success($this-><?= $service ?>->get<?= $model ?>Detail((int) $id), lang('messages.get_success'));
    }

    #[Post('')]
    #[Permission('<?= $permission ?>.create')]
    public function store(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->storeRules(), $this->messages());

        return $this->success($this-><?= $service ?>->create<?= $model ?>($data), lang('messages.create_success'));
    }

    #[Put('/{id:\d+}')]
    #[Permission('<?= $permission ?>.update')]
    public function update(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->updateRules(), $this->messages());
        $this-><?= $service ?>->update<?= $model ?>((int) $id, $data);

        return $this->success([], lang('messages.update_success'));
    }

    #[Delete('/{id:\d+}')]
    #[Permission('<?= $permission ?>.delete')]
    public function delete(Request $request, string $id): Response
    {
        $this-><?= $service ?>->delete<?= $model ?>((int) $id);

        return $this->success([], lang('messages.delete_success'));
    }

    #[Post('/batch-delete')]
    #[Permission('<?= $permission ?>.delete')]
    public function batchDelete(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->batchDeleteRules(), [
            'ids.required'  => '<?= $langPrefix ?>_ids_require',
            'ids.array'     => '<?= $langPrefix ?>_ids_require',
            'ids.min'       => '<?= $langPrefix ?>_ids_require',
            'ids.*.integer' => '<?= $langPrefix ?>_ids_integer',
        ]);
        $this-><?= $service ?>->batchDelete(array_map('intval', (array) $data['ids']));

        return $this->success([], lang('messages.batch_delete_success'));
    }
<?php if ($hasStatus) { ?>

    #[Put('/{id:\d+}/status')]
    #[Permission('<?= $permission ?>.status')]
    public function status(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), $this->statusRules(), [
            'status.required' => '<?= $langPrefix ?>_status_require',
            'status.integer'  => '<?= $langPrefix ?>_status_integer',
            'status.in'       => '<?= $langPrefix ?>_status_invalid',
        ]);
        $this-><?= $service ?>->updateStatus((int) $id, (int) $data['status']);

        return $this->success([], lang('messages.status_update_success'));
    }
<?php } ?>

    /**
     * store 场景的字段校验规则。M2b 的 RuleReflector 按动作名反射调用 "storeRules"（spec §5、§14），
     * 这里薄包装委派给 <?= $rulesMethod ?>()：规则表只在那一处维护，不重复写。
     *
     * @return array<string, string>
     */
    private function storeRules(): array
    {
        return $this-><?= $rulesMethod ?>('create');
    }

    /**
     * update 场景同上，见 storeRules() 的说明。
     *
     * @return array<string, string>
     */
    private function updateRules(): array
    {
        return $this-><?= $rulesMethod ?>('update');
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
<?php if ($hasStatus) { ?>

    /**
     * status 端点固定校验：0/1 二值开关。
     *
     * @return array<string, string>
     */
    private function statusRules(): array
    {
        return ['status' => 'required|integer|in:0,1'];
    }
<?php } ?>

    /**
     * store/update 共用的字段校验规则表，由 storeRules()/updateRules() 按场景委派调用（不再被
     * store()/update() 直接调用）。create 场景必填、update 场景 sometimes|required（不传就跳过，
     * 传了空值要拒绝）。
     *
     * 这个数组必须一直是字面量：键是字段名、值是字符串，唯一允许的插值是 {$required}。这不是
     * 给 M2b 反射用的约束（反射直接执行 storeRules()/updateRules() 拿完全求值后的返回值，不管
     * 内部怎么实现）——而是给人读的：这张表本身就是接口契约，写成运行时拼装（foreach 塞、
     * array_merge、变量当键）会让人没法一眼看出这个模块收哪些字段。
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
