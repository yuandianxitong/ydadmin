<?php
declare(strict_types=1);

namespace app\service\wechat;

use core\base\Service;
use core\wechat\WechatManager;
use core\exception\BusinessException;

class MiniAppService extends Service
{
    /**
     * 小程序登录（code 换取 session_key + openid）
     */
    public function login(string $code): array
    {
        $app = WechatManager::miniApp();
        $utils = $app->getUtils();
        $session = $utils->codeToSession($code);

        if (empty($session['openid'])) {
            throw new BusinessException(lang('business.miniapp_login_failed') . ': ' . ($session['errmsg'] ?? ''));
        }

        return [
            'openid'      => $session['openid'],
            'session_key' => $session['session_key'],
            'unionid'     => $session['unionid'] ?? '',
        ];
    }

    /**
     * 解密手机号
     */
    public function decryptPhoneNumber(string $code): array
    {
        $app = WechatManager::miniApp();
        $api = $app->getClient();

        $response = $api->postJson('/wxa/business/getuserphonenumber', [
            'code' => $code,
        ]);

        $result = $response->toArray();

        if (($result['errcode'] ?? -1) !== 0) {
            throw new BusinessException(lang('business.get_phone_failed') . ': ' . ($result['errmsg'] ?? ''));
        }

        $phoneInfo = $result['phone_info'] ?? [];

        return [
            'phone_number'      => $phoneInfo['phoneNumber'] ?? '',
            'pure_phone_number' => $phoneInfo['purePhoneNumber'] ?? '',
            'country_code'      => $phoneInfo['countryCode'] ?? '',
        ];
    }

    /**
     * 生成小程序码
     */
    public function getQrCode(string $scene, string $page = '', int $width = 430): string
    {
        $app = WechatManager::miniApp();
        $api = $app->getClient();

        $params = [
            'scene' => $scene,
            'width' => $width,
        ];

        if ($page) {
            $params['page'] = $page;
        }

        $response = $api->postJson('/wxa/getwxacodeunlimit', $params);

        $contentType = $response->getHeaderLine('Content-Type');

        // 如果返回的是 JSON，说明出错了
        if (str_contains($contentType, 'application/json')) {
            $result = $response->toArray();
            throw new BusinessException(lang('business.generate_qrcode_failed') . ': ' . ($result['errmsg'] ?? ''));
        }

        // 返回二进制图片数据
        return $response->getContent();
    }
}
