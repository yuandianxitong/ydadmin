<?php
declare(strict_types=1);

namespace app\api\controller\v1\user;

use core\base\Controller;
use app\service\user\UserService;
use think\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '用户中心', description: '用户个人信息、余额、积分、充值')]
class UserController extends Controller
{
    protected UserService $userService;
    protected \app\service\user\UserManageService $userManageService;
    protected \app\service\payment\PaymentService $paymentService;

    #[OA\Get(
        path: '/user/profile',
        summary: '获取个人信息',
        security: [['bearerAuth' => []]],
        tags: ['用户中心'],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
    public function profile(): Response
    {
        try {
            $userInfo = $this->userService->getUserInfo($this->getUserId());
            return $this->success(lang('messages.get_success'), $userInfo);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/user/profile',
        summary: '更新个人信息',
        security: [['bearerAuth' => []]],
        tags: ['用户中心'],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'nickname', type: 'string', description: '昵称'),
                new OA\Property(property: 'avatar', type: 'string', description: '头像URL'),
                new OA\Property(property: 'gender', type: 'integer', description: '性别(0未知 1男 2女)', enum: [0, 1, 2]),
                new OA\Property(property: 'birthday', type: 'string', description: '生日(Y-m-d)'),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: '更新成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
    public function updateProfile(): Response
    {
        try {
            $data = $this->request->only(['nickname', 'avatar', 'gender', 'birthday']);
            $this->userService->updateProfile($this->getUserId(), $data);
            return $this->success(lang('messages.update_success'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    #[OA\Put(
        path: '/user/change-password',
        summary: '修改密码',
        security: [['bearerAuth' => []]],
        tags: ['用户中心'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['new_password'],
                properties: [
                    new OA\Property(property: 'old_password', type: 'string', description: '旧密码（已设置密码时必填）'),
                    new OA\Property(property: 'new_password', type: 'string', description: '新密码（至少6位）'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '修改成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
    public function changePassword(): Response
    {
        try {
            $oldPassword = (string)$this->request->param('old_password', '');
            $newPassword = (string)$this->request->param('new_password', '');

            if (empty($newPassword) || strlen($newPassword) < 6) {
                return $this->error(lang('business.password_min_length'));
            }

            $this->userService->changePassword($this->getUserId(), $oldPassword, $newPassword);
            return $this->success(lang('messages.password_change_success'));
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    #[OA\Get(
        path: '/user/balance',
        summary: '获取余额',
        security: [['bearerAuth' => []]],
        tags: ['用户中心'],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
    public function balance(): Response
    {
        $userId = $this->getUserId();
        $result = $this->userManageService->getUserBalance($userId);
        return $this->success('ok', $result);
    }

    #[OA\Get(
        path: '/user/balance-logs',
        summary: '获取余额记录',
        security: [['bearerAuth' => []]],
        tags: ['用户中心'],
        parameters: [
            new OA\Parameter(name: 'page_no', in: 'query', description: '页码', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'page_size', in: 'query', description: '每页数量', schema: new OA\Schema(type: 'integer', default: 10)),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedResponse')),
        ]
    )]
    public function balanceLogs(): Response
    {
        $userId = $this->getUserId();
        $params = $this->getRequestData();
        $result = $this->userManageService->getUserBalanceLogs($userId, $params);
        return $this->success('ok', $result);
    }

    #[OA\Get(
        path: '/user/points',
        summary: '获取积分',
        security: [['bearerAuth' => []]],
        tags: ['用户中心'],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
    public function points(): Response
    {
        $userId = $this->getUserId();
        $result = $this->userManageService->getUserPoints($userId);
        return $this->success('ok', $result);
    }

    #[OA\Get(
        path: '/user/points-logs',
        summary: '获取积分记录',
        security: [['bearerAuth' => []]],
        tags: ['用户中心'],
        parameters: [
            new OA\Parameter(name: 'page_no', in: 'query', description: '页码', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'page_size', in: 'query', description: '每页数量', schema: new OA\Schema(type: 'integer', default: 10)),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/PaginatedResponse')),
        ]
    )]
    public function pointsLogs(): Response
    {
        $userId = $this->getUserId();
        $params = $this->getRequestData();
        $result = $this->userManageService->getUserPointsLogs($userId, $params);
        return $this->success('ok', $result);
    }

    #[OA\Post(
        path: '/user/recharge',
        summary: '余额充值',
        security: [['bearerAuth' => []]],
        tags: ['用户中心'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['amount'],
                properties: [
                    new OA\Property(property: 'amount', type: 'number', description: '充值金额（1.00~10000.00）'),
                    new OA\Property(property: 'channel', type: 'string', description: '支付渠道（wechat/alipay）', default: 'wechat'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '创建充值订单成功，返回支付参数', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
    public function recharge(): Response
    {
        try {
            $amount = (float) $this->request->post('amount');
            $channel = $this->request->post('channel', 'wechat');

            if ($amount < 1 || $amount > 10000) {
                return $this->error('充值金额范围为 1.00 ~ 10000.00 元');
            }

            $userId = $this->getUserId();
            $clientType = $this->getClientType();

            $payParams = [
                'subject'      => '余额充值',
                'body'         => '账户余额充值 ' . $amount . ' 元',
                'total_amount' => $amount,
                'user_id'      => $userId,
                'biz_type'     => 'recharge',
                'client_type'  => $clientType,
            ];

            if ($channel === 'wechat') {
                $resolved = $this->paymentService->resolveWechatPayParams($clientType, $userId);
                $payParams['trade_type'] = $resolved['trade_type'];
                $payParams['appid']      = $resolved['appid'];
                if ($resolved['openid']) {
                    $payParams['openid'] = $resolved['openid'];
                }
                if ($resolved['trade_type'] === 'h5') {
                    $payParams['client_ip'] = $this->request->ip();
                }
            }

            $result = $this->paymentService->createOrder($channel, $payParams);

            return $this->success('ok', $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    #[OA\Post(
        path: '/user/bind-oa-openid',
        summary: '绑定公众号 openid',
        security: [['bearerAuth' => []]],
        tags: ['用户中心'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['oa_openid'],
                properties: [
                    new OA\Property(property: 'oa_openid', type: 'string', description: '公众号 openid'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '绑定成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
    public function bindOaOpenid(): Response
    {
        try {
            $oaOpenid = (string)$this->request->post('oa_openid', '');
            if (empty($oaOpenid)) {
                return $this->error('缺少 oa_openid');
            }

            $userId = $this->getUserId();
            $this->userService->bindOaOpenid($userId, $oaOpenid);

            return $this->success('ok');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
