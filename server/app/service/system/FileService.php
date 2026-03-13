<?php
declare(strict_types=1);

namespace app\service\system;

use app\repository\system\FileRepository;
use core\base\Service;
use core\storage\StorageManager;
use core\exception\BusinessException;

class FileService extends Service
{
    protected FileRepository $fileRepo;

    /**
     * 获取文件列表
     */
    public function getFileList(array $params): array
    {
        $where = [];

        if (!empty($params['keyword'])) {
            $where[] = ['name', 'like', '%' . $params['keyword'] . '%'];
        }
        if (!empty($params['group'])) {
            $where[] = ['group', '=', $params['group']];
        }
        if (!empty($params['mime_type'])) {
            if ($params['mime_type'] === 'image') {
                $where[] = ['mime_type', 'like', 'image/%'];
            } else {
                $where[] = ['mime_type', 'not like', 'image/%'];
            }
        }

        $page  = (int)($params['page'] ?? 1);
        $limit = (int)($params['limit'] ?? 20);

        return $this->fileRepo->getFileList($where, $page, $limit);
    }

    /**
     * 记录上传的文件
     */
    public function recordFile(array $data): array
    {
        return $this->fileRepo->create([
            'name'      => $data['name'],
            'path'      => $data['path'],
            'url'       => $data['url'],
            'mime_type' => $data['mime_type'],
            'extension' => $data['extension'],
            'size'      => $data['size'],
            'group'     => $data['group'] ?? '默认',
            'upload_by' => $data['upload_by'] ?? 0,
            'storage'   => $data['storage'] ?? 'local',
        ]);
    }

    /**
     * 移动到分组
     */
    public function moveToGroup(array $ids, string $group): bool
    {
        foreach ($ids as $id) {
            $this->fileRepo->update((int)$id, ['group' => $group]);
        }
        return true;
    }

    /**
     * 删除文件
     */
    public function deleteFile(int $id): bool
    {
        $file = $this->fileRepo->find($id);
        if (!$file) {
            throw new BusinessException(lang('business.file_not_found'));
        }

        // 尝试删除物理文件
        $path = $file['path'];
        $storageType = $file['storage'] ?? 'local';

        if ($path) {
            try {
                $storage = StorageManager::disk($storageType);
                $storage->delete($path);
            } catch (\Exception $e) {
                // 物理文件删除失败不阻断数据库记录删除
            }
        }

        return $this->fileRepo->delete($id);
    }

    /**
     * 批量删除
     */
    public function batchDelete(array $ids): int
    {
        $count = 0;
        foreach ($ids as $id) {
            try {
                $this->deleteFile((int)$id);
                $count++;
            } catch (\Exception $e) {
                // 跳过失败的
            }
        }
        return $count;
    }

    /**
     * 获取分组列表
     */
    public function getGroups(): array
    {
        return $this->fileRepo->getGroups();
    }

    /**
     * 重命名文件
     */
    public function renameFile(int $id, string $name): bool
    {
        $file = $this->fileRepo->find($id);
        if (!$file) {
            throw new BusinessException(lang('business.file_not_found'));
        }
        return $this->fileRepo->update($id, ['name' => $name]);
    }
}
