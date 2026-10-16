<?php

declare(strict_types=1);

namespace app\service\operations;

use app\repository\announcement\AnnouncementRepository;
use app\repository\article\ArticleRepository;
use app\repository\dataimport\DataImportRepository;
use app\repository\operations\TaskRepository;
use app\repository\user\UserRepository;
use app\service\dataimport\handler\UserImportHandler;
use core\auth\PermissionCheckerInterface;
use core\base\Service;
use core\context\RequestContext;
use core\exception\BusinessException;
use core\exception\ForbiddenException;
use core\exception\NotFoundException;
use core\queue\PermanentJobFailure;
use core\queue\QueueDispatcher;
use core\queue\ReliableQueue;
use DI\Attribute\Inject;
use Illuminate\Database\UniqueConstraintViolationException;
use support\Context;
use Webman\Http\UploadFile;

final class TaskService extends Service
{
    private const EXPORTS = [
        'user' => ['permission' => 'user.list', 'fields' => ['id', 'mobile', 'nickname', 'email', 'gender', 'status', 'created_at']],
        'article' => ['permission' => 'article.list', 'fields' => ['id', 'category_id', 'title', 'author', 'status', 'view_count', 'created_at']],
        'announcement' => ['permission' => 'announcement.list', 'fields' => ['id', 'title', 'type', 'status', 'sort', 'publish_at']],
    ];
    #[Inject]
    protected TaskRepository $tasks;
    #[Inject]
    protected DataImportRepository $imports;
    #[Inject]
    protected UserImportHandler $userImport;
    #[Inject]
    protected UserRepository $users;
    #[Inject]
    protected ArticleRepository $articles;
    #[Inject]
    protected AnnouncementRepository $announcements;
    #[Inject]
    protected PermissionCheckerInterface $permissions;
    #[Inject]
    protected QueueDispatcher $queue;
    #[Inject]
    protected ReliableQueue $reliable;
    #[Inject]
    protected PrivateTaskFiles $files;

    /**
     * @return array<string, mixed> */
    public function submitImport(UploadFile $file): array
    {
        $actor = RequestContext::actingUser();
        $this->authorize($actor, 'user.import');
        if (!$file->isValid() || strtolower($file->getUploadExtension()) !== 'csv'
            || $file->getSize() > (int) config('operations.task_max_file_bytes')) {
            throw new BusinessException(lang('operations.invalid_import'));
        }
        $name = bin2hex(random_bytes(16)) . '.csv';
        $path = $this->files->path($name);
        $file->move($path);
        chmod($path, 0o600);
        try {
            return $this->submit('import', 'user', 'user.import', ['file' => $name, 'filename' => mb_substr((string) $file->getUploadName(), 0, 200)]);
        } catch (\Throwable $e) {
            $this->files->remove($name);
            throw $e;
        }
    }

    /**
     * @return array<string, mixed> */
    public function submitExport(string $module, ?int $status): array
    {
        $definition = self::EXPORTS[$module] ?? throw new BusinessException(lang('operations.invalid_export'));
        $this->authorize(RequestContext::actingUser(), $definition['permission']);

        return $this->submit('export', $module, $definition['permission'], ['status' => $status]);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed> */
    private function submit(string $kind, string $module, string $permission, array $input): array
    {
        return $this->runInTransaction(function () use ($kind, $module, $permission, $input): array {
            $task = $this->tasks->create([
                'public_id' => bin2hex(random_bytes(16)), 'admin_id' => RequestContext::actingUser(),
                'kind' => $kind, 'module' => $module, 'permission' => $permission, 'input' => $input,
                'status' => 'queued', 'errors' => [], 'result' => [], 'created_at' => time(), 'updated_at' => time(),
            ]);
            $this->queue->dispatchReliable('background-task', ['task_id' => (int) $task['id']], 'task:' . $task['public_id']);

            return $this->present($task);
        });
    }

    /**
     * @return array<string, mixed> */
    public function show(string $publicId): array
    {
        return $this->present($this->owned($publicId));
    }

    /**
     * @return array<string, mixed> */
    private function owned(string $publicId): array
    {
        $task = $this->tasks->owned($publicId, RequestContext::actingUser()) ?? throw new NotFoundException(lang('operations.task_not_found'));
        $this->authorize(RequestContext::actingUser(), (string) $task['permission']);

        return $task;
    }

    public function cancel(string $publicId): void
    {
        $owned = $this->owned($publicId);
        $this->runInTransaction(function () use ($owned): void {
            $task = $this->tasks->lock((int) $owned['id']);
            if ($task !== null && in_array($task['status'], ['queued', 'running'], true)) {
                $this->tasks->update((int) $task['id'], ['status' => 'cancelled', 'updated_at' => time()]);
            }
        });
        $current = $this->tasks->find((int) $owned['id']);
        if ($current !== null && $current['status'] === 'cancelled' && $current['kind'] === 'import') {
            $this->files->remove((string) $current['input']['file']);
        }
    }

    public function download(string $publicId): string
    {
        $task = $this->owned($publicId);
        if ($task['kind'] !== 'export' || $task['status'] !== 'completed' || !isset($task['result']['file'])) {
            throw new BusinessException(lang('operations.task_not_ready'), 409);
        }
        $path = $this->files->path((string) $task['result']['file']);
        if (!is_file($path)) {
            throw new NotFoundException(lang('operations.task_not_found'));
        }

        return $path;
    }

    public function execute(int $id): void
    {
        $token = (string) Context::get('queue.delivery_token');
        if ($token === '') {
            throw new PermanentJobFailure('后台任务必须通过可靠投递执行');
        }
        $task = $this->runInTransaction(function () use ($id, $token): ?array {
            $task = $this->tasks->lock($id);
            if ($task === null || in_array($task['status'], ['completed', 'failed', 'cancelled'], true)) {
                return null;
            }
            $this->tasks->update($id, ['status' => 'running', 'worker_token' => $token, 'updated_at' => time()]);

            return $this->tasks->find($id);
        });
        if ($task === null) {
            return;
        }
        $previous = [];
        foreach (['ctx.acting_user', 'ctx.datascope.snapshot', 'ctx.datascope.bypass'] as $key) {
            $previous[$key] = Context::get($key);
            Context::set($key, null);
        }
        RequestContext::setActingUser((int) $task['admin_id']);
        try {
            $this->checkExecution($task);
            if ($task['kind'] === 'import') {
                $this->executeImport($task, $token);
            } else {
                $this->executeExport($task, $token);
            }
        } catch (ForbiddenException $e) {
            throw new PermanentJobFailure('任务执行权限已失效', 0, $e);
        } finally {
            foreach ($previous as $key => $value) {
                if ($value === null) {
                    Context::set($key, null);
                } else {
                    Context::set($key, $value);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $task */
    private function checkExecution(array $task): void
    {
        $this->authorize((int) $task['admin_id'], (string) $task['permission']);
        \support\Container::get(\core\observability\Heartbeat::class)->beat('tasks');
        Context::set('ctx.datascope.snapshot', null);
        $this->reliable->heartbeat((int) Context::get('queue.delivery_id'), (string) Context::get('queue.delivery_token'));
    }

    private function authorize(int $actor, string $permission): void
    {
        if ($actor <= 0 || !$this->permissions->check($actor, $permission)) {
            throw new ForbiddenException(lang('auth.permission_denied'));
        }
    }

    /**
     * @param array<string, mixed> $task */
    private function executeImport(array $task, string $token): void
    {
        $path = $this->files->path((string) $task['input']['file']);
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new PermanentJobFailure('任务文件不可用');
        }
        try {
            $header = fgetcsv($handle, 0, ',', '"', '\\');
            if ($header === false) {
                throw new PermanentJobFailure('CSV 没有表头');
            }
            $header = array_map(static fn ($value): string => ltrim(trim((string) $value), "\xEF\xBB\xBF"), $header);
            if (!in_array('mobile', $header, true)) {
                throw new PermanentJobFailure('CSV 缺少 mobile 列');
            }
            $rows = 0;
            while (fgetcsv($handle, 0, ',', '"', '\\') !== false) {
                if (++$rows > (int) config('operations.task_max_rows')) {
                    throw new PermanentJobFailure('导入行数超过上限');
                }
            }
            rewind($handle);
            fgetcsv($handle, 0, ',', '"', '\\');
            $cursor = 0;
            while (($values = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                $cursor++;
                if ($cursor <= (int) $task['cursor']) {
                    continue;
                }
                if ($cursor % 50 === 1) {
                    $this->checkExecution($task);
                }
                $row = [];
                foreach (['mobile', 'nickname', 'password', 'email', 'gender', 'status'] as $field) {
                    $index = array_search($field, $header, true);
                    $value = $index === false ? '' : (string) ($values[$index] ?? '');
                    $row[$field] = mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'GB18030');
                }
                try {
                    $this->runInTransaction(function () use ($task, $token, $cursor, $row): void {
                        $current = $this->tasks->lock((int) $task['id']);
                        if ($current === null || (int) $current['cursor'] >= $cursor) {
                            return;
                        }
                        $this->assertOwner($current, $token);
                        if (implode('', array_map('trim', $row)) === '') {
                            $this->tasks->checkpoint((int) $task['id'], $token, ['cursor' => $cursor]);
                            return;
                        }
                        $this->userImport->handle($row);
                        $this->tasks->checkpoint((int) $task['id'], $token, ['cursor' => $cursor, 'processed' => (int) $current['processed'] + 1, 'success_count' => (int) $current['success_count'] + 1]);
                    });
                } catch (BusinessException|UniqueConstraintViolationException $e) {
                    $this->runInTransaction(function () use ($task, $token, $cursor, $e): void {
                        $current = $this->tasks->lock((int) $task['id']);
                        if ($current === null || (int) $current['cursor'] >= $cursor) {
                            return;
                        }
                        $this->assertOwner($current, $token);
                        $errors = $current['errors'];
                        if (count($errors) < 200) {
                            $errors[] = ['row' => $cursor + 1, 'message' => $e instanceof BusinessException ? $e->getMessage() : lang('dataimport.mobile_taken')];
                        }
                        $this->tasks->checkpoint((int) $task['id'], $token, ['cursor' => $cursor, 'processed' => (int) $current['processed'] + 1, 'fail_count' => (int) $current['fail_count'] + 1, 'errors' => $errors]);
                    });
                }
            }
            $this->runInTransaction(function () use ($task, $token): void {
                $current = $this->tasks->lock((int) $task['id']);
                if ($current === null) {
                    throw new PermanentJobFailure('任务不存在');
                }
                $this->assertOwner($current, $token);
                $record = $this->imports->create([
                    'module' => 'user', 'filename' => $task['input']['filename'], 'admin_id' => $task['admin_id'],
                    'status' => (int) $current['success_count'] > 0 ? DataImportRepository::STATUS_COMPLETED : DataImportRepository::STATUS_FAILED,
                    'total_count' => $current['processed'], 'success_count' => $current['success_count'], 'fail_count' => $current['fail_count'], 'errors' => $current['errors'],
                ]);
                $this->tasks->checkpoint((int) $task['id'], $token, ['status' => 'completed', 'result' => ['import_id' => (int) $record['id'], 'errors_truncated' => (int) $current['fail_count'] > 200]]);
            });
            $this->files->remove((string) $task['input']['file']);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param array<string, mixed> $task */
    private function executeExport(array $task, string $token): void
    {
        $definition = self::EXPORTS[$task['module']] ?? throw new PermanentJobFailure('不支持的导出模块');
        $repository = match ($task['module']) {
            'user' => $this->users, 'article' => $this->articles, 'announcement' => $this->announcements,
        };
        $name = bin2hex(random_bytes(16)) . '.csv';
        $path = $this->files->path($name);
        $handle = fopen($path, 'x');
        if ($handle === false) {
            throw new \RuntimeException('无法创建导出文件');
        }
        chmod($path, 0o600);
        $committed = false;
        try {
            fwrite($handle, "\xEF\xBB\xBF");
            if (fputcsv($handle, $definition['fields'], ',', '"', '') === false) {
                throw new \RuntimeException('无法写入导出文件');
            }
            $after = 0;
            $processed = 0;
            do {
                $this->checkExecution($task);
                $rows = $repository->exportChunk($after, $definition['fields'], isset($task['input']['status']) ? (int) $task['input']['status'] : null);
                foreach ($rows as $row) {
                    if (++$processed > (int) config('operations.task_max_rows')) {
                        throw new PermanentJobFailure('导出行数超过上限，请缩小筛选范围');
                    }
                    if (fputcsv($handle, array_map(static fn (string $field): string => PrivateTaskFiles::csvCell($row[$field] ?? null), $definition['fields']), ',', '"', '') === false) {
                        throw new \RuntimeException('无法写入导出文件');
                    }
                    $after = (int) $row['id'];
                }
                $this->tasks->checkpoint((int) $task['id'], $token, ['processed' => $processed]);
            } while (count($rows) === 500);
            if (!fflush($handle)) {
                throw new \RuntimeException('无法写入导出文件');
            }
            $this->tasks->checkpoint((int) $task['id'], $token, ['status' => 'completed', 'success_count' => $processed, 'result' => ['file' => $name]]);
            $committed = true;
        } finally {
            fclose($handle);
            if (!$committed) {
                $this->files->remove($name);
            }
        }
    }

    /**
     * @param array<string, mixed> $task */
    private function assertOwner(array $task, string $token): void
    {
        if ($task['status'] !== 'running' || !hash_equals((string) $task['worker_token'], $token)) {
            throw new PermanentJobFailure('任务已取消或执行权已转移');
        }
    }

    public function markFailed(int $id): void
    {
        $token = (string) Context::get('queue.delivery_token');
        if ($token === '') {
            return;
        }
        $this->tasks->fail($id, $token);
        $task = $this->tasks->find($id);
        if ($task !== null && in_array($task['status'], ['failed', 'cancelled'], true) && $task['kind'] === 'import' && isset($task['input']['file'])) {
            $this->files->remove((string) $task['input']['file']);
        }
    }

    public function cleanup(): int
    {
        $count = 0;
        foreach ($this->tasks->expired(time() - (int) config('operations.task_retention_seconds')) as $task) {
            foreach ([$task['input']['file'] ?? null, $task['result']['file'] ?? null] as $name) {
                if (is_string($name)) {
                    $this->files->remove($name);
                }
            }
            $count += (int) $this->tasks->delete((int) $task['id']);
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $task
     * @return array<string, mixed> */
    private function present(array $task): array
    {
        $result = (array) ($task['result'] ?? []);
        unset($result['file']);

        return \app\resource\operations\TaskResource::make([
            'task_id' => $task['public_id'], 'kind' => $task['kind'], 'module' => $task['module'], 'status' => $task['status'],
            'processed' => (int) ($task['processed'] ?? 0), 'success_count' => (int) ($task['success_count'] ?? 0),
            'fail_count' => (int) ($task['fail_count'] ?? 0), 'errors' => $task['errors'] ?? [], 'result' => $result === [] ? new \stdClass() : $result,
            'download_ready' => $task['kind'] === 'export' && $task['status'] === 'completed',
        ]);
    }
}
