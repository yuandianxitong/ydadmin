<?php
declare(strict_types=1);

namespace app\api\controller\v1\common;

use app\service\user\UserService;
use app\service\message\MessageService;
use core\base\Controller;
use core\storage\StorageManager;
use think\Response;
use think\facade\Filesystem;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '公共接口', description: '应用配置、短信验证码、文件上传')]
class CommonController extends Controller
{
    protected UserService $userService;
    protected MessageService $messageService;

    #[OA\Get(
        path: '/common/config',
        summary: '获取应用基础配置',
        tags: ['公共接口'],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
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

    #[OA\Post(
        path: '/common/upload/image',
        summary: '上传图片',
        security: [['bearerAuth' => []]],
        tags: ['公共接口'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['file'],
                    properties: [
                        new OA\Property(property: 'file', type: 'string', format: 'binary', description: '图片文件（jpg/png/gif/webp，最大2MB）'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '上传成功，返回文件URL', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
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

    #[OA\Post(
        path: '/common/sms-code',
        summary: '发送短信验证码',
        tags: ['公共接口'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['mobile', 'scene'],
                properties: [
                    new OA\Property(property: 'mobile', type: 'string', description: '手机号'),
                    new OA\Property(property: 'scene', type: 'string', description: '场景（login/register/reset_password/bind_mobile/change_mobile）'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '发送成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 400, description: '发送失败', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        ]
    )]
    public function sendSmsCode(): Response
    {
        try {
            $mobile = (string)$this->request->param('mobile', '');
            $scene = (string)$this->request->param('scene', 'login');

            if (empty($mobile) || !preg_match('/^1[3-9]\d{9}$/', $mobile)) {
                return $this->error(lang('business.invalid_mobile_format'));
            }

            // 场景校验
            $templateMap = [
                'login'          => 'login_captcha',
                'register'       => 'register_captcha',
                'reset_password' => 'reset_password',
                'bind_mobile'    => 'bind_mobile',
                'change_mobile'  => 'change_mobile',
            ];
            if (!isset($templateMap[$scene])) {
                return $this->error(lang('business.invalid_sms_scene'));
            }

            // 根据场景校验手机号存在性
            $exists = $this->userService->mobileExists($mobile);
            if ($scene === 'login' && !$exists) {
                return $this->error(lang('business.mobile_not_registered'));
            }
            if ($scene === 'register' && $exists) {
                return $this->error(lang('business.mobile_already_registered'));
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

            // 发送短信（模板变量只传 code，与阿里云/腾讯云模板一致）
            $this->messageService->send($templateMap[$scene], ['phone' => $mobile], [
                'code' => $code,
            ]);

            // 缓存验证码（5分钟有效）
            cache($cacheKey, $code, 300);
            cache($limitKey, time(), 60);

            return $this->success(lang('messages.sms_code_sent'));
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }
}
