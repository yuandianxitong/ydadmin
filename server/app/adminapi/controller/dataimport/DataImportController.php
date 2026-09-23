<?php

declare(strict_types=1);

namespace app\adminapi\controller\dataimport;

use app\service\dataimport\DataImportService;
use app\service\system\SystemConfigService;
use core\base\Controller;
use core\context\RequestContext;
use core\exception\BusinessException;
use core\permission\Permission;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;
use Webman\Http\UploadFile;

/**
 * 数据导入。
 *
 * 端点：
 *   POST /adminapi/dataimport/upload   upload   dataimport.upload
 *   GET  /adminapi/dataimport/history  history  dataimport.history
 *
 * 不写 files 表：上传文件自行移到 runtime/imports/。不调 UploadService / FileService。
 * upload() 的校验规则由 uploadRules() 提供（规则七：validate 第二参必须是 $this->uploadRules()）。
 */
class DataImportController extends Controller
{
    private const DEFAULT_FILE_MAX_MB = 10;

    #[Inject]
    protected DataImportService $dataImportService;

    #[Inject]
    protected SystemConfigService $systemConfigService;

    #[Permission('dataimport.upload')]
    public function upload(Request $request): Response
    {
        $file = $request->file('file');
        if (!$file instanceof UploadFile || !$file->isValid()) {
            throw new BusinessException(lang('dataimport.file_required'));
        }

        if (strtolower($file->getUploadExtension()) !== 'csv') {
            throw new BusinessException(lang('dataimport.csv_only'));
        }

        $data = $this->validate($this->body($request), $this->uploadRules(), $this->uploadMessages());
        // 会员导入只走 /adminapi/user/import（权限 user.import）。通用导入权限不进角色树，
        // 放行 module=user 等于给 dataimport.upload 开了一条写会员表的后门。
        if ((string) $data['module'] === 'user') {
            throw new BusinessException(lang('dataimport.module_not_allowed'));
        }
        $this->assertSizeWithin($file);

        $dir = runtime_path() . '/imports';
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new BusinessException(lang('dataimport.file_open_failed'));
        }

        $originalName = (string) $file->getUploadName();
        $target = $dir . '/' . bin2hex(random_bytes(16)) . '.csv';
        $file->move($target);

        $fieldMapRaw = $request->post('field_map', '');
        $decoded = is_string($fieldMapRaw) ? json_decode($fieldMapRaw, true) : $fieldMapRaw;
        $fieldMap = is_array($decoded) ? $decoded : [];

        $result = $this->dataImportService->import(
            (string) $data['module'],
            $target,
            $originalName,
            $fieldMap,
            RequestContext::actingUser()
        );

        return $this->success($result);
    }

    #[Permission('dataimport.history')]
    public function history(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request, 20);

        return $this->paginate($this->dataImportService->getHistory((array) $request->get(), $page, $limit));
    }

    /**
     * @return array<string, string>
     */
    private function uploadRules(): array
    {
        return [
            'module' => 'required|string|max:50',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function uploadMessages(): array
    {
        return [
            'module.required' => 'dataimport.module_required',
            'module.string'   => 'dataimport.module_required',
            'module.max'      => 'dataimport.module_required',
        ];
    }

    private function assertSizeWithin(UploadFile $file): void
    {
        $maxMb = (int) $this->systemConfigService->getConfigValue('storage_upload_max_size', self::DEFAULT_FILE_MAX_MB);
        if ($maxMb <= 0) {
            $maxMb = self::DEFAULT_FILE_MAX_MB;
        }
        if ((int) $file->getSize() > $maxMb * 1024 * 1024) {
            throw new BusinessException(lang('business.file_size_exceeded', ['size' => $maxMb]));
        }
    }
}
