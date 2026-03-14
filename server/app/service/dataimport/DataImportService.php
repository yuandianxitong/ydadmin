<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\service\dataimport;

use app\model\dataimport\DataImport;
use app\repository\dataimport\DataImportRepository;
use core\base\Service;
use core\exception\BusinessException;

class DataImportService extends Service
{
    protected DataImportRepository $dataImportRepository;

    /**
     * 导入CSV数据
     *
     * @param string   $module     模块名称
     * @param string   $filePath   CSV文件路径
     * @param array    $fieldMap   字段映射 ['CSV列名' => '目标字段名']
     * @param callable $rowHandler 行处理回调函数
     * @param int      $adminId   操作管理员ID
     * @return array 导入结果
     */
    public function import(string $module, string $filePath, array $fieldMap, callable $rowHandler, int $adminId): array
    {
        if (!file_exists($filePath)) {
            throw new BusinessException('导入文件不存在');
        }

        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new BusinessException('无法打开导入文件');
        }

        // 创建导入记录
        $record = $this->dataImportRepository->create([
            'module'        => $module,
            'filename'      => basename($filePath),
            'total_count'   => 0,
            'success_count' => 0,
            'fail_count'    => 0,
            'status'        => DataImport::STATUS_PROCESSING,
            'errors'        => [],
            'admin_id'      => $adminId,
        ]);

        $totalCount = 0;
        $successCount = 0;
        $failCount = 0;
        $errors = [];

        try {
            // 读取表头行
            $header = fgetcsv($handle);
            if ($header === false) {
                throw new BusinessException('CSV文件格式错误，无法读取表头');
            }

            // 处理BOM
            if (isset($header[0])) {
                $header[0] = preg_replace('/^\x{FEFF}/u', '', $header[0]);
            }

            // 构建列索引映射
            $columnMap = [];
            foreach ($fieldMap as $csvColumn => $targetField) {
                $index = array_search($csvColumn, $header);
                if ($index !== false) {
                    $columnMap[$index] = $targetField;
                }
            }

            // 逐行读取并处理数据
            $rowNumber = 1;
            while (($csvRow = fgetcsv($handle)) !== false) {
                $rowNumber++;
                $totalCount++;

                // 跳过空行
                if (empty(array_filter($csvRow))) {
                    continue;
                }

                // 映射字段
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

            // 更新导入记录
            $status = $failCount === $totalCount
                ? DataImport::STATUS_FAILED
                : DataImport::STATUS_COMPLETED;

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
                'status'        => DataImport::STATUS_FAILED,
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
            'id'            => $record['id'],
            'total_count'   => $totalCount,
            'success_count' => $successCount,
            'fail_count'    => $failCount,
            'errors'        => $errors,
        ];
    }

    /**
     * 获取导入历史记录
     */
    public function getHistory(array $params): array
    {
        $page = (int) ($params['page_no'] ?? 1);
        $limit = (int) ($params['page_size'] ?? 20);
        return $this->dataImportRepository->getSearchList($params, $page, $limit);
    }
}
