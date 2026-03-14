<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\adminapi\controller\v1\dataimport;

use core\base\Controller;
use core\attribute\Permission;
use app\service\dataimport\DataImportService;
use think\Response;

class DataImportController extends Controller
{
    protected DataImportService $dataImportService;

    /**
     * 上传并导入数据
     */
    #[Permission('dataimport.upload')]
    public function upload(): Response
    {
        $file = $this->request->file('file');
        if (!$file) {
            return $this->error('请上传文件');
        }

        $module = $this->request->post('module', '');
        if (empty($module)) {
            return $this->error('请指定导入模块');
        }

        // 保存上传文件
        $saveName = \think\facade\Filesystem::disk('public')->putFile('imports', $file);
        $filePath = app()->getRuntimePath() . '../public/storage/' . $saveName;

        $adminId = $this->getUserId();

        // 获取字段映射（前端传入JSON字符串）
        $fieldMapJson = $this->request->post('field_map', '{}');
        $fieldMap = json_decode($fieldMapJson, true) ?: [];

        // 默认行处理器：仅验证不为空
        $rowHandler = function (array $row) {
            // 默认处理逻辑，具体模块可通过扩展覆盖
        };

        $result = $this->dataImportService->import($module, $filePath, $fieldMap, $rowHandler, $adminId);
        return $this->success(lang('messages.operation_success'), $result);
    }

    /**
     * 导入历史记录
     */
    #[Permission('dataimport.history')]
    public function history(): Response
    {
        $params = $this->getRequestData([
            'page_no'   => 1,
            'page_size' => 20,
            'module'    => '',
        ]);
        $result = $this->dataImportService->getHistory($params);
        return $this->paginate($result);
    }
}
