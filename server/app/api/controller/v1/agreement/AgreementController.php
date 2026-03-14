<?php
declare(strict_types=1);

namespace app\api\controller\v1\agreement;

use core\base\Controller;
use app\service\agreement\AgreementService;
use think\Response;

class AgreementController extends Controller
{
    protected AgreementService $agreementService;

    /**
     * 根据标识码获取协议内容
     */
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
