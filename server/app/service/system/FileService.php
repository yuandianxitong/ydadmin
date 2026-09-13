<?php

declare(strict_types=1);

namespace app\service\system;

use app\repository\system\FileRepository;
use core\base\Service;
use DI\Attribute\Inject;

/**
 * 文件（files 表，契约 §2.9.1）。不受数据权限约束——spec §5.5 的受控表只有管理员与两类日志，
 * 素材库对所有有权限位的管理员是同一份（FileRepository 的 $dataScoped 保持 false）。
 *
 * 本任务只落 recordFile()（上传入库），列表 / 分组 / 移动 / 重命名 / 删除在 Task 6 补齐。
 */
class FileService extends Service
{
    #[Inject]
    protected FileRepository $fileRepository;

    /**
     * 记录一次上传。调用方（UploadService）自己兜异常：写不进去只记日志，不影响上传成功响应
     * （契约 §2.9.2）。
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function recordFile(array $data): array
    {
        return $this->fileRepository->create([
            'name'      => (string) $data['name'],
            'path'      => (string) $data['path'],
            'url'       => (string) ($data['url'] ?? ''),
            'mime_type' => (string) ($data['mime_type'] ?? ''),
            'extension' => (string) ($data['extension'] ?? ''),
            'size'      => (int) ($data['size'] ?? 0),
            'group'     => (string) ($data['group'] ?? '默认'),
            'upload_by' => (int) ($data['upload_by'] ?? 0),
            'storage'   => (string) ($data['storage'] ?? 'local'),
        ]);
    }
}
