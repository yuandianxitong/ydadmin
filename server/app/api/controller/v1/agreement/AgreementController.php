<?php
declare(strict_types=1);

namespace app\api\controller\v1\agreement;

use core\base\Controller;
use app\service\agreement\AgreementService;
use think\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '协议', description: '用户协议、隐私政策等内容页')]
class AgreementController extends Controller
{
    protected AgreementService $agreementService;

    #[OA\Get(
        path: '/agreement/{code}',
        summary: '根据标识码获取协议内容',
        tags: ['协议'],
        parameters: [
            new OA\Parameter(name: 'code', in: 'path', required: true, description: '协议标识码（如 user_agreement、privacy_policy）', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
    public function getByCode(string $code): Response
    {
        try {
            if (empty($code)) {
                return $this->error('协议标识码不能为空');
            }
            $result = $this->agreementService->findByCode($code);
            if (!$result || (int) ($result['status'] ?? 0) !== 1) {
                return $this->error(lang('business.record_not_found'));
            }
            return $this->success(lang('messages.get_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
