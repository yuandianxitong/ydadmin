<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\controller\v1\diy;

use app\adminapi\validate\v1\diy\DiyLinkValidate;
use app\service\diy\DiyLinkService;
use core\attribute\Permission;
use core\base\Controller;
use think\Response;

class DiyLinkController extends Controller
{
    protected DiyLinkService $diyLinkService;

    #[Permission('diy.link.list')]
    public function index(): Response
    {
        return $this->success('ok', $this->diyLinkService->list());
    }

    #[Permission('diy.link.create')]
    public function save(): Response
    {
        $data = $this->request->post();
        $this->validate($data, DiyLinkValidate::class, [], false, 'create');
        $id = $this->diyLinkService->create($data);
        return $this->success('创建成功', ['id' => $id]);
    }

    #[Permission('diy.link.update')]
    public function update(): Response
    {
        $id   = (int) $this->request->param('id');
        $data = $this->request->put();
        $this->validate($data, DiyLinkValidate::class, [], false, 'update');
        $this->diyLinkService->update($id, $data);
        return $this->success('保存成功');
    }

    #[Permission('diy.link.delete')]
    public function delete(): Response
    {
        $this->diyLinkService->delete((int) $this->request->param('id'));
        return $this->success('已删除');
    }
}
