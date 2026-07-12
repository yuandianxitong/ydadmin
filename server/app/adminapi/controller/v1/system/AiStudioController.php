<?php
// server/app/adminapi/controller/v1/system/AiStudioController.php
declare(strict_types=1);

namespace app\adminapi\controller\v1\system;

use app\service\system\AiStudioService;
use core\ai\AiClientException;
use core\attribute\Permission;
use core\base\Controller;
use think\Response;

class AiStudioController extends Controller
{
    protected AiStudioService $aiStudioService;

    /** SSE 流式生成（权限点 ai.studio.generate） */
    #[Permission('ai.studio.generate')]
    public function stream(): void
    {
        $instruction = trim((string) $this->request->post('instruction', ''));
        $tables = array_slice(array_filter((array) $this->request->post('tables', [])), 0, AiStudioService::MAX_TABLES);
        $genType = (string) $this->request->post('gen_type', 'crud');
        $layers = AiStudioService::LAYER_PRESETS[$genType] ?? AiStudioService::LAYER_PRESETS['crud'];

        // SSE 准备：清空 think/PHP 输出缓冲，直接写响应
        ignore_user_abort(true);
        set_time_limit(0);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');

        $emit = static function (string $event, array $data): void {
            echo "event: {$event}\n";
            echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();
        };

        if ($instruction === '' || mb_strlen($instruction) > AiStudioService::MAX_INSTRUCTION || empty($tables)) {
            $emit('error', ['code' => 'INPUT_INVALID', 'message' => '请填写需求描述（≤500 字）并至少选择一张数据表', 'request_id' => '']);
            exit;
        }

        $startTime = microtime(true);
        try {
            $result = $this->aiStudioService->generateToStage(
                $instruction,
                $tables,
                $layers,
                static fn (string $chunk) => $emit('chunk', ['content' => $chunk])
            );
            $emit('done', $result);
            $this->recordStreamLog($instruction, $tables, $genType, 200, (string) ($result['request_id'] ?? ''), microtime(true) - $startTime);
        } catch (AiClientException $e) {
            $emit('error', [
                'code'       => $e->getErrorCode() !== '' ? $e->getErrorCode() : 'ENGINE_INTERNAL_ERROR',
                'message'    => $e->getMessage(),
                'request_id' => $e->getRequestId(),
            ]);
            $this->recordStreamLog($instruction, $tables, $genType, 500, $e->getRequestId(), microtime(true) - $startTime);
        } catch (\Throwable $e) {
            $emit('error', ['code' => 'ENGINE_INTERNAL_ERROR', 'message' => $this->sanitizeError($e), 'request_id' => '']);
            $this->recordStreamLog($instruction, $tables, $genType, 500, '', microtime(true) - $startTime);
        }
        exit;
    }

    /**
     * stream 端点因 exit() 绕过 AdminLogMiddleware 的后置日志，此处手动补录同构操作日志。
     * 补录失败静默（不影响 SSE 主流程）。
     */
    protected function recordStreamLog(string $instruction, array $tables, string $genType, int $code, string $requestId, float $executionTime): void
    {
        $userId = $this->request->userId ?? 0;
        if (!$userId) {
            return;
        }
        try {
            \core\queue\QueueManager::push(\app\job\AdminOperationLogJob::class, [
                'admin_id'       => $userId,
                'username'       => $this->request->username ?? '',
                'method'         => 'POST',
                'path'           => $this->request->pathinfo(),
                'ip'             => $this->request->ip(),
                'user_agent'     => $this->request->header('User-Agent', ''),
                'action'         => 'ai_studio_stream',
                'description'    => 'AI Studio 流式生成',
                'params'         => [
                    'instruction' => mb_substr($instruction, 0, 200),
                    'tables'      => $tables,
                    'gen_type'    => $genType,
                    'request_id'  => $requestId,
                ],
                'result'         => ['code' => $code, 'message' => $code === 200 ? '生成成功' : '生成失败'],
                'execution_time' => round($executionTime, 3),
            ]);
        } catch (\Throwable $e) {
            trace('AI Studio 操作日志补录失败（已忽略）：' . $e->getMessage(), 'debug');
        }
    }

    /**
     * 按 isDebug 门控脱敏异常消息
     *
     * @param \Throwable $e 异常对象
     * @return string 脱敏后的错误消息
     */
    protected function sanitizeError(\Throwable $e): string
    {
        if (app()->isDebug()) {
            return '生成失败：' . $e->getMessage();
        }
        return '生成失败，请稍后重试或联系管理员';
    }

    /** 预览暂存文件（权限点 ai.studio.generate） */
    #[Permission('ai.studio.generate')]
    public function preview(): Response
    {
        $stageId = (string) $this->request->post('stage_id', '');
        $path = (string) $this->request->post('path', '');
        return $this->success('获取成功', ['code' => $this->aiStudioService->previewFile($stageId, $path)]);
    }

    /** diff（权限点 ai.studio.generate） */
    #[Permission('ai.studio.generate')]
    public function diff(): Response
    {
        $stageId = (string) $this->request->post('stage_id', '');
        return $this->success('获取成功', ['diff' => $this->aiStudioService->diff($stageId)]);
    }

    /** 写入选中文件（权限点 ai.studio.apply） */
    #[Permission('ai.studio.apply')]
    public function apply(): Response
    {
        $stageId = (string) $this->request->post('stage_id', '');
        $paths = array_filter((array) $this->request->post('paths', []));
        if (empty($paths)) {
            return $this->error('请选择要写入的文件');
        }
        $written = $this->aiStudioService->apply($stageId, $paths);
        return $this->success('已写入 ' . count($written) . ' 个文件', ['written' => $written]);
    }

    /** 反馈转发（权限点 ai.studio.generate） */
    #[Permission('ai.studio.generate')]
    public function feedback(): Response
    {
        $this->aiStudioService->forwardFeedback(
            (string) $this->request->post('generation_id', ''),
            (string) $this->request->post('action', '')
        );
        return $this->success('感谢反馈');
    }
}
