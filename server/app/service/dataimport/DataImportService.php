<?php

declare(strict_types=1);

namespace app\service\dataimport;

use app\repository\dataimport\DataImportRepository;
use core\base\Service;
use core\exception\BusinessException;
use DI\Attribute\Inject;

/**
 * 数据导入。CSV 原生解析（fopen + fgetcsv），默认空 rowHandler，不写任何业务表。
 *
 * 只调 Repository：状态常量取 DataImportRepository，不引用 Model 常量。
 */
class DataImportService extends Service
{
    #[Inject]
    protected DataImportRepository $dataImportRepository;

    /**
     * 解析一份已落到本地的 CSV，写入 data_imports 最终态。
     *
     * @param array<string, string> $fieldMap csv 列名 → 目标字段
     * @return array{id: int, total_count: int, success_count: int, fail_count: int, status: int, errors: list<array<string, mixed>>}
     */
    public function import(string $module, string $filePath, string $filename, array $fieldMap, int $adminId): array
    {
        $rowHandler = static fn (array $row) => null;

        if (!is_file($filePath)) {
            throw new BusinessException(lang('dataimport.file_not_exists'));
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new BusinessException(lang('dataimport.file_open_failed'));
        }

        $record = $this->dataImportRepository->create([
            'module'        => $module,
            'filename'      => $filename,
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

        try {
            $header = fgetcsv($handle, 0, ',', '"', '\\');
            if ($header !== false) {
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
                    $rowNumber++;
                    $totalCount++;

                    if ($csvRow === [] || empty(array_filter($csvRow))) {
                        continue;
                    }

                    $row = [];
                    foreach ($columnMap as $index => $field) {
                        $row[$field] = $csvRow[$index] ?? '';
                    }

                    try {
                        $rowHandler($row);
                        $successCount++;
                    } catch (\Throwable $e) {
                        $failCount++;
                        $errors[] = [
                            'row'     => $rowNumber,
                            'message' => $e->getMessage(),
                            'data'    => $row,
                        ];
                    }
                }
            }

            $status = $failCount === $totalCount
                ? DataImportRepository::STATUS_FAILED
                : DataImportRepository::STATUS_COMPLETED;

            $this->dataImportRepository->update((int) $record['id'], [
                'total_count'   => $totalCount,
                'success_count' => $successCount,
                'fail_count'    => $failCount,
                'status'        => $status,
                'errors'        => $errors,
            ]);
        } catch (\Throwable $e) {
            $this->dataImportRepository->update((int) $record['id'], [
                'total_count'   => $totalCount,
                'success_count' => $successCount,
                'fail_count'    => $failCount,
                'status'        => DataImportRepository::STATUS_FAILED,
                'errors'        => array_merge($errors, [[
                    'row'     => 0,
                    'message' => $e->getMessage(),
                ]]),
            ]);
            throw $e;
        } finally {
            fclose($handle);
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
