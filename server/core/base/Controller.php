<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\base;

use think\App;
use think\Request;
use think\Response;
use think\facade\Log;
use core\http\Response as CoreResponse;
use core\exception\ValidationException;
use core\exception\BusinessException;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: '元点Admin API',
    description: '元点Admin 管理后台 RESTful API 文档',
    contact: new OA\Contact(name: 'mashanglai Team', url: 'https://www.dev007.cn')
)]
#[OA\Server(url: '/adminapi', description: '管理后台API')]
#[OA\Server(url: '/api', description: '前端API')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'JWT Token 认证，格式: Bearer {token}'
)]
#[OA\Schema(
    schema: 'SuccessResponse',
    properties: [
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: '操作成功'),
        new OA\Property(property: 'data', type: 'object'),
    ]
)]
#[OA\Schema(
    schema: 'ErrorResponse',
    properties: [
        new OA\Property(property: 'code', type: 'integer', example: 400),
        new OA\Property(property: 'message', type: 'string', example: '操作失败'),
        new OA\Property(property: 'data', type: 'object'),
    ]
)]
#[OA\Schema(
    schema: 'PaginatedResponse',
    properties: [
        new OA\Property(property: 'code', type: 'integer', example: 200),
        new OA\Property(property: 'message', type: 'string', example: '获取成功'),
        new OA\Property(property: 'data', properties: [
            new OA\Property(property: 'total', type: 'integer', example: 100),
            new OA\Property(property: 'per_page', type: 'integer', example: 20),
            new OA\Property(property: 'current_page', type: 'integer', example: 1),
            new OA\Property(property: 'last_page', type: 'integer', example: 5),
            new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
        ], type: 'object'),
    ]
)]
abstract class Controller
{
    protected App $app;
    protected Request $request;

    /**
     * 依赖解析缓存（进程级）
     */
    private static array $resolveCache = [];

    public function __construct()
    {
        $this->app = app();
        $this->request = $this->app->request;
        $this->resolveDependencies();
        $this->initialize();
    }

    /**
     * 自动从容器解析子类声明的类型属性
     *
     * 子类只需声明带类型的 protected 属性，基类会自动从容器注入：
     * ```php
     * class AdminController extends Controller
     * {
     *     protected AdminService $adminService; // 自动注入
     * }
     * ```
     */
    private function resolveDependencies(): void
    {
        $class = static::class;

        if (!isset(self::$resolveCache[$class])) {
            $props = [];
            $ref = new \ReflectionClass($class);
            foreach ($ref->getProperties(\ReflectionProperty::IS_PROTECTED) as $prop) {
                // 跳过基类自身声明的属性（app, request）
                if ($prop->getDeclaringClass()->getName() === self::class) continue;
                $type = $prop->getType();
                if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) continue;
                $props[] = $prop;
            }
            self::$resolveCache[$class] = $props;
        }

        foreach (self::$resolveCache[$class] as $prop) {
            if ($prop->isInitialized($this)) continue;
            try {
                $prop->setValue($this, $this->app->make($prop->getType()->getName()));
            } catch (\Throwable $e) {
                // 无法解析的属性保持未初始化，开发者可在 initialize() 中手动设置。
                // 但底层异常（如表不存在、配置缺失）会被静默吞掉，
                // 调用方读到属性时只会看到 "must not be accessed before initialization"，
                // 难以定位真正的故障点。开 debug 时把原始异常落到 Log，便于排查。
                if (env('APP_DEBUG', false)) {
                    Log::warning(sprintf(
                        '[DI] 注入 %s::$%s 失败：%s（@ %s:%d）',
                        $class,
                        $prop->getName(),
                        $e->getMessage(),
                        $e->getFile(),
                        $e->getLine()
                    ));
                }
            }
        }
    }

    /**
     * 初始化方法
     */
    protected function initialize(): void
    {
        // 子类可重写此方法
    }

    /**
     * 成功响应
     */
    protected function success(string $message = '', $data = [], int $code = 200): Response
    {
        return CoreResponse::success($message, $data, $code);
    }

    /**
     * 错误响应
     */
    protected function error(string $message = '', int $code = 400, $data = []): Response
    {
        return CoreResponse::error($message, $code, $data);
    }

    /**
     * 分页响应
     */
    protected function paginate(array $data, string $message = ''): Response
    {
        return CoreResponse::paginate($data, $message);
    }

    /**
     * 验证数据
     * 兼容场景参数：
     * - 新签名：validate($data, $validate, $message = [], $batch = false, $scene = null)
     * - 向后兼容：若第4个参数传入的是字符串，则视为 scene
     */
    protected function validate(array $data, $validate, array $message = [], bool|string $batch = false, ?string $scene = null): array
    {
        // 兼容历史用法：第4个参数传 scene 字符串
        if (is_string($batch) && $scene === null) {
            $scene = $batch;
            $batch = false;
        }

        if (is_array($validate)) {
            $v = new \think\Validate();
            $v->rule($validate);
        } else {
            if (is_string($validate)) {
                $validate = new $validate;
            }
            $v = $validate;
        }

        if (!empty($message)) {
            $v->message($message);
        }

        // 应用验证场景
        if (is_string($scene) && $scene !== '') {
            $v->scene($scene);
        }

        if (!$v->batch($batch)->check($data)) {
            throw new ValidationException($v->getError());
        }

        return $data;
    }

    /**
     * 获取请求参数
     *
     * 分页字段自动双向归一化：
     *   - 前端无论传 `page/limit` 还是 `page_no/page_size`，两套 key 都会存在
     *   - 统一约定：**新写的 Service 优先使用 `page/limit`**；
     *     历史遗留 Service 读取 `page_no/page_size` 也能正常工作
     */
    protected function getRequestData(array $rules = []): array
    {
        $data = $this->request->param();

        // 分页参数双向归一化
        if (isset($data['page']) && !isset($data['page_no'])) {
            $data['page_no'] = $data['page'];
        } elseif (isset($data['page_no']) && !isset($data['page'])) {
            $data['page'] = $data['page_no'];
        }
        if (isset($data['limit']) && !isset($data['page_size'])) {
            $data['page_size'] = $data['limit'];
        } elseif (isset($data['page_size']) && !isset($data['limit'])) {
            $data['limit'] = $data['page_size'];
        }

        if (!empty($rules)) {
            // 允许 page/limit 始终透传（即使 rules 只声明了 page_no/page_size）
            $rules = array_merge([
                'page' => null, 'limit' => null,
                'page_no' => null, 'page_size' => null,
            ], $rules);
            $data = array_intersect_key($data, $rules);
        }

        return $data;
    }

    /**
     * 获取用户ID
     */
    protected function getUserId(): int
    {
        return (int) $this->request->userId ?? 0;
    }

    /**
     * 获取用户信息
     */
    protected function getUserInfo(): array
    {
        return $this->request->userInfo ?? [];
    }

    /**
     * 获取客户端类型
     * 从 X-Client-Type 请求头读取，白名单校验
     */
    protected function getClientType(): string
    {
        $clientType = $this->request->header('X-Client-Type', '');
        $allowed = ['miniapp', 'wechat_h5', 'h5', 'app', 'pc'];
        if (!in_array($clientType, $allowed, true)) {
            throw new BusinessException('缺少或无效的客户端类型标识（X-Client-Type）');
        }
        return $clientType;
    }
}
