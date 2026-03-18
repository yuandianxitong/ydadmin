<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\service\system;

use app\repository\system\SystemConfigRepository;
use core\base\Service;

class SystemConfigService extends Service
{
    protected SystemConfigRepository $configRepository;

    /**
     * 获取配置分组
     * @return array
     */
    public function getConfigGroups(): array
    {
        return [
            'basic'            => lang('messages.config_group_basic'),
            'email'            => lang('messages.config_group_email'),
            'sms'              => lang('messages.config_group_sms'),
            'storage'          => lang('messages.config_group_storage'),
            'payment'          => lang('messages.config_group_payment'),
            'wechat_official'  => lang('messages.config_group_wechat_official'),
            'wechat_open'      => lang('messages.config_group_wechat_open'),
            'wechat_mini'      => lang('messages.config_group_wechat_mini'),
        ];
    }

    /**
     * 根据分组获取配置列表
     * @param string $group
     * @return array
     */
    public function getConfigsByGroup(string $group = 'basic'): array
    {
        return $this->configRepository->getByGroup($group);
    }

    /**
     * 获取单个配置
     * @param int $id
     * @return array
     * @throws \Exception
     */
    public function getConfigById(int $id): array
    {
        $config = $this->configRepository->find($id);
        if (!$config) {
            throw new \Exception(lang('business.config_not_found'));
        }

        return $config;
    }

    /**
     * 更新配置
     * @param int $id
     * @param array $data
     * @return bool
     * @throws \Exception
     */
    public function updateConfig(int $id, array $data): bool
    {
        $config = $this->configRepository->findModel($id);
        if (!$config) {
            throw new \Exception(lang('business.config_not_found'));
        }

        // 根据类型处理值
        if ($config->config_type === 'json') {
            $data['config_value'] = json_encode($data['config_value'], JSON_UNESCAPED_UNICODE);
        }

        $config->config_value = $data['config_value'];
        $result = $config->save();

        if ($result) {
            $this->trigger('config.changed', ['keys' => [$config->config_key]]);
        }

        return $result;
    }

    /**
     * 批量更新配置
     * @param array $configs
     * @return bool
     * @throws \Exception
     */
    public function batchUpdateConfigs(array $configs): bool
    {
        $changedKeys = [];
        foreach ($configs as $configData) {
            if (!isset($configData['config_key']) || !isset($configData['config_value'])) {
                continue;
            }

            $config = $this->configRepository->findModelByKey($configData['config_key']);
            if (!$config) {
                continue;
            }

            // 根据类型处理值
            $value = $configData['config_value'];
            if ($config->config_type === 'json') {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }

            $config->config_value = $value;
            $config->save();
            $changedKeys[] = $configData['config_key'];
        }

        if (!empty($changedKeys)) {
            $this->trigger('config.changed', ['keys' => $changedKeys]);
        }

        return true;
    }

    /**
     * 获取全局配置（用于前端应用）
     * @return array
     */
    public function getGlobalConfigs(): array
    {
        return $this->configRepository->getAllConfigs();
    }

    /**
     * 获取配置值
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getConfigValue(string $key, $default = null)
    {
        return $this->configRepository->getConfigValue($key, $default);
    }

    /**
     * 设置配置值
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public function setConfigValue(string $key, $value): bool
    {
        $result = $this->configRepository->setConfigValue($key, $value);

        if ($result) {
            $this->trigger('config.changed', ['keys' => [$key]]);
        }

        return $result;
    }
}
