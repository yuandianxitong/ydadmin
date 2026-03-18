<?php
declare(strict_types=1);

namespace app\api\controller\v1\common;

use core\base\Controller;
use core\storage\StorageManager;
use think\Response;
use think\facade\Filesystem;

class CommonController extends Controller
{
    /**
     * 获取应用基础配置（无需登录）
     */
    public function config(): Response
    {
        try {
            $configs = \app\model\system\SystemConfig::getConfigsByGroup('basic');

            // 合并开放平台的公开配置（仅 AppID，不暴露 Secret）
            $wechatOpenConfigs = \app\model\system\SystemConfig::getConfigsByGroup('wechat_open');

            // 只返回前端需要的公开配置
            $publicKeys = ['site_name', 'site_logo', 'site_description', 'site_status', 'site_close_tip', 'user_register', 'banner_list'];
            $result = array_intersect_key($configs, array_flip($publicKeys));
            if (!empty($wechatOpenConfigs['wechat_open_app_id'])) {
                $result['wechat_open_app_id'] = $wechatOpenConfigs['wechat_open_app_id'];
            }

            return $this->success(lang('messages.get_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 上传图片
     */
    public function uploadImage(): Response
    {
        try {
            $file = $this->request->file('file');
            if (!$file) {
                return $this->error(lang('business.please_select_upload'));
            }

            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file->getMime(), $allowedTypes)) {
                return $this->error(lang('business.upload_image_only'));
            }

            if ($file->getSize() > 2 * 1024 * 1024) {
                return $this->error(lang('business.file_size_2mb'));
            }

            $extension = $file->extension();
            $filename = date('Ymd') . '/' . uniqid() . '.' . $extension;

            $storage = StorageManager::disk();
            $driverName = $storage->getDriver();

            if ($driverName === 'local') {
                $saveName = Filesystem::disk('public')->putFileAs('uploads/images', $file, $filename);
                if (!$saveName) {
                    return $this->error(lang('business.upload_failed'));
                }
                $url = '/storage/' . str_replace('\\', '/', $saveName);
            } else {
                $remotePath = 'uploads/images/' . $filename;
                $url = $storage->upload($file->getPathname(), $remotePath);
            }

            return $this->success(lang('messages.upload_success'), [
                'url'  => $url,
                'size' => $file->getSize(),
            ]);
        } catch (\Exception $e) {
            return $this->error(lang('business.upload_failed') . ': ' . $e->getMessage());
        }
    }

    /**
     * 发送短信验证码
     */
    public function sendSmsCode(): Response
    {
        try {
            $mobile = (string)$this->request->param('mobile', '');
            $scene = (string)$this->request->param('scene', 'login');

            if (empty($mobile) || !preg_match('/^1[3-9]\d{9}$/', $mobile)) {
                return $this->error(lang('business.invalid_mobile_format'));
            }

            // 频率限制
            $limitKey = 'sms_limit:' . $mobile;
            $lastSend = cache($limitKey);
            if ($lastSend && (time() - (int)$lastSend) < 60) {
                return $this->error(lang('business.sms_rate_limit'));
            }

            // 生成验证码
            $code = (string)mt_rand(100000, 999999);
            $cacheKey = 'sms_code:' . $scene . ':' . $mobile;

            // TODO: 调用短信服务发送验证码
            // $smsService->send($mobile, $code);

            // 缓存验证码（5分钟有效）
            cache($cacheKey, $code, 300);
            cache($limitKey, time(), 60);

            return $this->success(lang('messages.sms_code_sent'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
