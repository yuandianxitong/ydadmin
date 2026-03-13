<?php
declare(strict_types=1);

namespace app\service\message\channel;

use app\repository\system\SystemConfigRepository;

class SmsChannel implements ChannelInterface
{
    public function send(string $receiver, string $templateId, array $data, array $extra = []): array
    {
        $configRepo = app()->make(SystemConfigRepository::class);
        $driver = (string)$configRepo->getConfigValue('sms_driver', 'aliyun');

        try {
            if ($driver === 'tencent') {
                return $this->sendViaTencent($configRepo, $receiver, $templateId, $data);
            }
            return $this->sendViaAliyun($configRepo, $receiver, $templateId, $data);
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function sendViaAliyun(SystemConfigRepository $configRepo, string $phone, string $templateCode, array $data): array
    {
        $accessKey = (string)$configRepo->getConfigValue('sms_access_key', '');
        $accessSecret = (string)$configRepo->getConfigValue('sms_access_secret', '');
        $signName = (string)$configRepo->getConfigValue('sms_sign_name', '');

        if (empty($accessKey) || empty($accessSecret) || empty($signName)) {
            return ['success' => false, 'error' => lang('business.aliyun_sms_config_incomplete')];
        }

        \AlibabaCloud\SDK\Dysmsapi\Dysmsapi::setEndpoint('dysmsapi.aliyuncs.com');

        $config = new \Darabonba\OpenApi\Models\Config([
            'accessKeyId'     => $accessKey,
            'accessKeySecret' => $accessSecret,
            'endpoint'        => 'dysmsapi.aliyuncs.com',
        ]);

        $client = new \AlibabaCloud\SDK\Dysmsapi\Dysmsapi($config);

        $request = new \AlibabaCloud\SDK\Dysmsapi\Models\SendSmsRequest([
            'phoneNumbers'  => $phone,
            'signName'      => $signName,
            'templateCode'  => $templateCode,
            'templateParam' => json_encode($data, JSON_UNESCAPED_UNICODE),
        ]);

        $response = $client->sendSms($request);
        $body = $response->body;

        if ($body->code !== 'OK') {
            return ['success' => false, 'error' => $body->message ?? lang('business.send_failed')];
        }

        return ['success' => true, 'error' => ''];
    }

    protected function sendViaTencent(SystemConfigRepository $configRepo, string $phone, string $templateId, array $data): array
    {
        $accessKey = (string)$configRepo->getConfigValue('sms_access_key', '');
        $accessSecret = (string)$configRepo->getConfigValue('sms_access_secret', '');
        $signName = (string)$configRepo->getConfigValue('sms_sign_name', '');

        if (empty($accessKey) || empty($accessSecret) || empty($signName)) {
            return ['success' => false, 'error' => lang('business.tencent_sms_config_incomplete')];
        }

        $cred = new \TencentCloud\Common\Credential($accessKey, $accessSecret);
        $client = new \TencentCloud\Sms\V20210111\SmsClient($cred, 'ap-guangzhou');

        $req = new \TencentCloud\Sms\V20210111\Models\SendSmsRequest();
        $req->SmsSdkAppId = (string)$configRepo->getConfigValue('sms_sdk_app_id', '');
        $req->SignName = $signName;
        $req->TemplateId = $templateId;
        $req->TemplateParamSet = array_values(array_map('strval', $data));
        $req->PhoneNumberSet = ['+86' . $phone];

        $resp = $client->SendSms($req);
        $status = $resp->SendStatusSet[0] ?? null;

        if (!$status || strtolower($status->Code) !== 'ok') {
            return ['success' => false, 'error' => $status->Message ?? lang('business.send_failed')];
        }

        return ['success' => true, 'error' => ''];
    }
}
