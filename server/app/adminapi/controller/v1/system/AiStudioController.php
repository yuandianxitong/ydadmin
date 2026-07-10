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
            $emit('error', ['message' => '请填写需求描述（≤500 字）并至少选择一张数据表']);
            exit;
        }

        try {
            $result = $this->aiStudioService->generateToStage(
                $instruction,
                $tables,
                $layers,
                static fn (string $chunk) => $emit('chunk', ['content' => $chunk])
            );
            $emit('done', $result);
        } catch (AiClientException $e) {
            $emit('error', ['message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $emit('error', ['message' => '生成失败：' . $e->getMessage()]);
        }
        exit;
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
