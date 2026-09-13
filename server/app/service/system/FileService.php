<?php

declare(strict_types=1);

namespace app\service\system;

use app\repository\system\FileRepository;
use core\base\Service;
use core\exception\BusinessException;
use core\storage\StorageInterface;
use core\storage\StorageManager;
use DI\Attribute\Inject;
use support\Log;

/**
 * 文件管理（契约 §2.9.1、spec §6.5）。
 *
 * 不受数据权限约束：spec §5.5 的受控表只有管理员（M1a）与两类日志（M1b），素材库对所有
 * 持有 system.file.* 权限位的管理员是同一份（FileRepository 的 $dataScoped 保持 false）。
 *
 * 分组就是 files.group 字符串，没有分类表（spec §3.1 明确 M1c 不建 file_categories）。
 *
 * 删除的顺序与异常分流见 deleteFile() 的注释。
 */
class FileService extends Service
{
    #[Inject]
    protected FileRepository $fileRepository;

    #[Inject]
    protected StorageManager $storageManager;

    /**
     * 列表。$params 里 keyword / group / mime_type 均已由控制器 validate() 过白名单。
     *
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getFileList(array $params, int $page, int $limit): array
    {
        return $this->fileRepository->getFileList($params, $page, $limit);
    }

    /**
     * 分组聚合，形如 [{group, count}]，count 降序（契约 §2.9.1）。
     *
     * @return list<array{group: string, count: int}>
     */
    public function getGroups(): array
    {
        return $this->fileRepository->getGroupCounts();
    }

    /**
     * 批量移动到分组：契约写的是「逐条 update group 字段」，这里照做，外面包一个事务，
     * 保证要么全动要么不动。group 是 MySQL 保留字，交给 Eloquent 加反引号，不手写 SQL。
     *
     * @param list<int> $ids
     */
    public function moveToGroup(array $ids, string $group): void
    {
        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return;
        }

        $this->runInTransaction(function () use ($ids, $group): void {
            foreach ($ids as $id) {
                $this->fileRepository->update($id, ['group' => $group]);
            }
        });
    }

    public function renameFile(int $id, string $name): bool
    {
        $this->findOrFail($id);

        return $this->fileRepository->update($id, ['name' => $name]);
    }

    /**
     * 删除（契约 §2.9.1 的顺序）：先删物理文件，再软删 DB 行。
     *
     * 物理文件经 diskOf() 解析磁盘：按**这一行自己记录的** storage 取驱动，而不是当前的
     * storage_driver。文件落在哪个驱动上是既成事实，与现在配的是哪个驱动无关；用当前驱动去删，
     * 在切换过驱动之后只会删不掉、留下孤儿，而接口还报成功。
     *
     * 物理删除的异常分两类，处理方式不同：
     *   - BusinessException 向上抛，不吞。LocalDriver::delete() 对「文件本来就不在盘上」返回 false
     *     而不是抛异常，所以能走到这个分支的只可能是驱动层的结构性问题（云驱动配置不全、
     *     未知驱动名，见 StorageManager）。吞掉它就会造出「接口说删成功了、配额也放了，
     *     物理文件其实还躺在对象存储里」的静默失败——正是 spec §6.5「不静默回退到本地」
     *     要杜绝的那一类。
     *   - 其余 \Throwable（权限、IO 等真实可容忍的物理删除失败）按契约只记 warning，
     *     不阻断 DB 删除：库里留着一条指向已经删不掉的文件的记录，对使用者更糟。
     */
    public function deleteFile(int $id): bool
    {
        $file = $this->findOrFail($id);
        $path = (string) ($file['path'] ?? '');

        if ($path !== '') {
            try {
                $this->diskOf($file)->delete($path);
            } catch (BusinessException $e) {
                throw $e;
            } catch (\Throwable $e) {
                Log::warning('物理文件删除失败', [
                    'file_id' => $id,
                    'path'    => $path,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        return $this->fileRepository->delete($id);
    }

    /**
     * 批量删除：非事务，单条失败跳过继续，返回成功数（契约 §2.9.1）。
     *
     * @param list<int> $ids
     */
    public function batchDelete(array $ids): int
    {
        $count = 0;
        foreach ($ids as $id) {
            try {
                if ($this->deleteFile((int) $id)) {
                    $count++;
                }
            } catch (\Throwable $e) {
                Log::warning('文件删除失败', ['file_id' => $id, 'error' => $e->getMessage()]);
            }
        }

        return $count;
    }

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

    /**
     * 这一行该用哪个磁盘：按它自己记录的 storage 解析（契约 §2.9.1 的 disk($file.storage)），
     * 只有该值为空（TP8 老数据没有这一列的值）时才回落到当前配置的驱动。
     * 驱动名不认识时 diskFor() 抛 BusinessException，由 deleteFile() 原样向上抛。
     *
     * @param array<string, mixed> $file
     */
    private function diskOf(array $file): StorageInterface
    {
        $driver = trim((string) ($file['storage'] ?? ''));

        return $driver !== '' ? $this->storageManager->diskFor($driver) : $this->storageManager->disk();
    }

    /** @return array<string, mixed> */
    private function findOrFail(int $id): array
    {
        return $this->fileRepository->find($id) ?? throw new BusinessException(lang('business.file_not_found'));
    }
}
