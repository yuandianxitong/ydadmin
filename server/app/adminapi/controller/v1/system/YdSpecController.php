<?php
// server/app/adminapi/controller/v1/system/YdSpecController.php
declare(strict_types=1);

namespace app\adminapi\controller\v1\system;

use app\service\system\YdSpecService;
use core\attribute\Permission;
use core\base\Controller;

class YdSpecController extends Controller
{
    protected YdSpecService $ydSpecService;

    #[Permission('ai.ydspec.use')]
    public function refine()
    {
        $data = $this->request->post();
        $description = trim((string) ($data['description'] ?? ''));
        if ($description === '') {
            return $this->error('请先描述业务');
        }
        $answers = is_array($data['answers'] ?? null) ? $data['answers'] : [];
        $draft = is_array($data['draft'] ?? null) ? $data['draft'] : null;

        return $this->success('ok', $this->ydSpecService->refine($description, $answers, $draft));
    }

    #[Permission('ai.ydspec.use')]
    public function confirm()
    {
        $spec = $this->request->post('spec/a');
        if (!is_array($spec) || !$spec) {
            return $this->error('规格为空');
        }
        $versions = $this->request->post('versions/a');
        $versions = is_array($versions) && $versions ? $versions : null;

        return $this->success('规格已保存', $this->ydSpecService->confirm($spec, $versions));
    }
}
