<?php

declare(strict_types=1);

namespace app\service\dataimport;

use app\repository\dataimport\DataImportRepository;
use app\service\dataimport\handler\UserImportHandler;
use core\base\Service;
use core\exception\BusinessException;
use core\realtime\RealtimePublisher;
use DI\Attribute\Inject;
use support\Log;

/**
 * 数据导入。CSV 原生解析（fopen + fgetcsv）。
 * module=user 写入会员；其它 module 仍只计数。进度经 RealtimePublisher::progress。
 *
 * 只调 Repository：状态常量取 DataImportRepository，不引用 Model 常量。
 */
class DataImportService extends Service
{
    /** 整份导入同步跑在 HTTP 请求里，超过这个行数直接拒收，让人拆文件。 */
    public const MAX_ROWS = 5000;

    /** data_imports.errors 是 TEXT（64KB）：失败行太多会写不进去，只留前这么多条并标记截断。 */
    private const MAX_ERRORS = 200;

    #[Inject]
    protected DataImportRepository $dataImportRepository;

    #[Inject]
    protected UserImportHandler $userImportHandler;

    #[Inject]
    protected RealtimePublisher $realtimePublisher;

    /**
     * 落库的行错误只留业务文案：底层异常（如 QueryException）会把绑定值——手机号、bcrypt 密码哈希——
     * 连同库主机、库名一起拼进 message，那份 message 会经 data_imports.errors 回显给管理员。
     * 原始异常只进应用日志，且只记类名与位置。
     */
    private function rowErrorMessage(\Throwable $e, int $recordId, int $rowNumber): string
    {
        if ($e instanceof BusinessException) {
            return $e->getMessage();
        }

        Log::error('导入行失败', [
            'import_id' => $recordId,
            'row'       => $rowNumber,
            'exception' => $e::class,
        ]);

        return lang('dataimport.row_failed');
    }

    /**
     * 失败行数超过保留上限时补一条说明，别让管理员以为只错了 200 行。
     *
     * @param list<array<string, mixed>> $errors
     * @return list<array<string, mixed>>
     */
    private function markTruncated(array $errors, int $failCount): array
    {
        if ($failCount <= count($errors)) {
            return $errors;
        }

        $errors[] = ['row' => 0, 'message' => lang('dataimport.errors_truncated', ['count' => $failCount, 'kept' => self::MAX_ERRORS])];

        return $errors;
    }

    /**
     * 先数行再决定收不收：在循环里边导边拒会留下一半已写库的会员。
     * 用 fgetcsv 数而不是数换行，带引号的多行字段才不会被算成多行。
     */
    private function assertRowCountWithin(string $filePath): void
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new BusinessException(lang('dataimport.file_open_failed'));
        }

        try {
            $rows = -1; // 表头不算
            while (fgetcsv($handle, 0, ',', '"', '\\') !== false) {
                $rows++;
                if ($rows > self::MAX_ROWS) {
                    throw new BusinessException(lang('dataimport.too_many_rows', ['max' => self::MAX_ROWS]));
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Excel 在中文 Windows 上默认存 GBK：整行按 UTF-8 读会是乱码，落库要么报错要么存进去一堆问号。
     * 逐值探一次，非 UTF-8 的按 GB18030（GBK 超集）转。
     *
     * @param list<string|null> $row
     * @return list<string>
     */
    private function toUtf8(array $row): array
    {
        $out = [];
        foreach ($row as $value) {
            $value = (string) $value;
            $out[] = mb_check_encoding($value, 'UTF-8')
                ? $value
                : (string) mb_convert_encoding($value, 'UTF-8', 'GB18030');
        }

        return $out;
    }

    /**
     * 解析一份已落到本地的 CSV，写入 data_imports 最终态。
     *
     * @param array<string, string> $fieldMap csv 列名 → 目标字段
     * @return array{id: int, total_count: int, success_count: int, fail_count: int, status: int, errors: list<array<string, mixed>>}
     */
    public function import(string $module, string $filePath, string $filename, array $fieldMap, int $adminId): array
    {
        if (!is_file($filePath)) {
            throw new BusinessException(lang('dataimport.file_not_exists'));
        }

        $this->assertRowCountWithin($filePath);

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new BusinessException(lang('dataimport.file_open_failed'));
        }

        $record = $this->dataImportRepository->create([
            'module'        => $module,
            'filename'      => mb_substr($filename, 0, 200),
            'total_count'   => 0,
            'success_count' => 0,
            'fail_count'    => 0,
            'status'        => DataImportRepository::STATUS_PROCESSING,
            'errors'        => [],
            'admin_id'      => $adminId,
        ]);

        $totalCount = 0;
        $successCount = 0;
        $failCount = 0;
        $errors = [];
        $status = DataImportRepository::STATUS_FAILED;
        $taskId = 'import:' . $record['id'];
        $this->realtimePublisher->progress($adminId, $taskId, 0, lang('dataimport.progress_start'));

        try {
            $header = fgetcsv($handle, 0, ',', '"', '\\');
            if ($header !== false) {
                $header = $this->toUtf8($header);
                if (isset($header[0])) {
                    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]) ?? (string) $header[0];
                    $header[0] = preg_replace('/^\x{FEFF}/u', '', (string) $header[0]) ?? (string) $header[0];
                }

                $columnMap = [];
                foreach ($fieldMap as $csvColumn => $targetField) {
                    $index = array_search($csvColumn, $header, true);
                    if ($index !== false) {
                        $columnMap[$index] = $targetField;
                    }
                }

                $rowNumber = 1;
                while (($csvRow = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                    $csvRow = $this->toUtf8($csvRow);
                    $rowNumber++;

                    // 整行空白才跳过，且跳过的行不计入 total（否则 total ≠ 成功 + 失败）；
                    // array_filter 会把 "0" 当空，这里按去空白后的字符串判。
                    if (implode('', array_map('trim', $csvRow)) === '') {
                        continue;
                    }
                    $totalCount++;

                    $row = [];
                    foreach ($columnMap as $index => $field) {
                        $row[$field] = $csvRow[$index] ?? '';
                    }

                    try {
                        if ($module === 'user') {
                            $this->userImportHandler->handle($row);
                        }
                        $successCount++;
                    } catch (\Throwable $e) {
                        $failCount++;
                        if (count($errors) < self::MAX_ERRORS) {
                            $errors[] = [
                                'row'     => $rowNumber,
                                'message' => $this->rowErrorMessage($e, (int) $record['id'], $rowNumber),
                            ];
                        }
                    }

                    $processed = $successCount + $failCount;
                    if ($processed % 50 === 0) {
                        $this->realtimePublisher->progress(
                            $adminId,
                            $taskId,
                            min(99, intdiv($processed, 50) * 5),
                            lang('dataimport.progress_running')
                        );
                    }
                }
            }

            $status = $failCount === $totalCount
                ? DataImportRepository::STATUS_FAILED
                : DataImportRepository::STATUS_COMPLETED;
            $errors = $this->markTruncated($errors, $failCount);

            $this->dataImportRepository->update((int) $record['id'], [
                'total_count'   => $totalCount,
                'success_count' => $successCount,
                'fail_count'    => $failCount,
                'status'        => $status,
                'errors'        => $errors,
            ]);
        } catch (\Throwable $e) {
            // 这里再抛就把原始异常盖掉了，而且记录会永远停在「处理中」；写不进去只记日志。
            try {
                $this->dataImportRepository->update((int) $record['id'], [
                    'total_count'   => $totalCount,
                    'success_count' => $successCount,
                    'fail_count'    => $failCount,
                    'status'        => DataImportRepository::STATUS_FAILED,
                    'errors'        => array_merge($this->markTruncated($errors, $failCount), [[
                        'row'     => 0,
                        'message' => $this->rowErrorMessage($e, (int) $record['id'], 0),
                    ]]),
                ]);
            } catch (\Throwable $writeBack) {
                Log::error('导入失败态写回失败', [
                    'import_id' => (int) $record['id'],
                    'exception' => $writeBack::class,
                ]);
            }
            throw $e;
        } finally {
            fclose($handle);
            // 上传的临时 CSV 带着手机号与明文密码，读完即删（两个入口都把文件交给这里）。
            @unlink($filePath);
            $this->realtimePublisher->progress($adminId, $taskId, 100, lang('dataimport.progress_done'));
        }

        return [
            'id'            => (int) $record['id'],
            'total_count'   => $totalCount,
            'success_count' => $successCount,
            'fail_count'    => $failCount,
            'status'        => $status,
            'errors'        => $errors,
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getHistory(array $params, int $page, int $limit): array
    {
        return $this->dataImportRepository->getHistory($params, $page, $limit);
    }
}
