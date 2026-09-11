<?php

declare(strict_types=1);

namespace app\adminapi\controller\system;

use app\service\system\SystemConfigService;
use core\base\Controller;
use core\permission\Permission;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/**
 * 系统配置（契约 §2.7）。
 *
 * 端点：
 *   GET  /adminapi/system/config               index        system.config.list
 *   GET  /adminapi/system/config/groups        groups       PermissionSkip
 *   GET  /adminapi/system/config/global        global       PermissionSkip
 *   POST /adminapi/system/config/batch-update  batchUpdate  system.config.update
 *   POST /adminapi/system/config/clear-cache   clearCache   PermissionSkip（同 TP8；只清配置缓存）
 *   GET  /adminapi/system/config/{id}          show         system.config.list
 *   PUT  /adminapi/system/config/{id}          update       system.config.update
 *
 * index/show 返回原始行（config_value 为库里的字符串，不做类型转换，契约如此）。
 * config_value 用 present 而不是 required：允许把配置清空（如把 site_icp 置 ''），与批量更新一致。
 */
class SystemConfigController extends Controller
{
    #[Inject]
    protected SystemConfigService $systemConfigService;

    #[Permission('system.config.list')]
    public function index(Request $request): Response
    {
        $data = $this->validate((array) $request->get(), ['group' => 'nullable|string|max:50'], [
            'group.string' => 'validation.config_group_invalid',
            'group.max'    => 'validation.config_group_invalid',
        ]);
        $group = (string) ($data['group'] ?? '');

        return $this->success($this->systemConfigService->getConfigsByGroup($group !== '' ? $group : 'basic'), lang('messages.config_list_success'));
    }

    #[PermissionSkip]
    public function groups(): Response
    {
        return $this->success($this->systemConfigService->getConfigGroups(), lang('messages.config_group_success'));
    }

    #[PermissionSkip]
    public function global(): Response
    {
        return $this->success($this->systemConfigService->getGlobalConfigs(), lang('messages.global_config_success'));
    }

    #[Permission('system.config.list')]
    public function show(Request $request, string $id): Response
    {
        return $this->success($this->systemConfigService->getConfigById((int) $id), lang('messages.config_get_success'));
    }

    #[Permission('system.config.update')]
    public function update(Request $request, string $id): Response
    {
        $data = $this->validate($this->body($request), ['config_value' => 'present'], [
            'config_value.present' => 'validation.config_value_present',
        ]);

        return $this->success($this->systemConfigService->updateConfig((int) $id, $data['config_value']), lang('messages.config_update_success'));
    }

    #[Permission('system.config.update')]
    public function batchUpdate(Request $request): Response
    {
        $data = $this->validate($this->body($request), [
            'configs'                => 'required|array|min:1',
            'configs.*.config_key'   => 'required|string|max:100',
            'configs.*.config_value' => 'present',
        ], [
            'configs.required'               => 'validation.configs_require',
            'configs.array'                  => 'validation.configs_array',
            'configs.min'                    => 'validation.configs_require',
            'configs.*.config_key.required'  => 'validation.config_key_require',
            'configs.*.config_key.string'    => 'validation.config_key_require',
            'configs.*.config_key.max'       => 'validation.config_key_max',
            'configs.*.config_value.present' => 'validation.config_value_present',
        ]);

        // 每项只取 config_key/config_value 两个字段（validate 已剔除其余键，这里再显式收窄）
        $configs = [];
        foreach ((array) $data['configs'] as $item) {
            $configs[] = ['config_key' => (string) $item['config_key'], 'config_value' => $item['config_value'] ?? null];
        }

        return $this->success($this->systemConfigService->batchUpdateConfigs($configs), lang('messages.config_update_success'));
    }

    #[PermissionSkip]
    public function clearCache(): Response
    {
        $this->systemConfigService->clearConfigCache();

        return $this->success([], lang('messages.cache_clear_success'));
    }
}
