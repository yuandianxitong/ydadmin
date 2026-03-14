# Phase 2: Core Capabilities Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build out Phase 2 core capabilities: error code system, feedback full-stack, C-end message API, UniApp payment/message/feedback modules, plugin system foundation, admin frontend components, test framework, and VitePress documentation.

**Architecture:** Backend follows existing Controller → Service → Repository → Model + Listener pattern with auto-DI. UniApp follows modular structure with api/ + modules/ + components/ + hooks/. Admin frontend uses Vue 3 + Element Plus + TypeScript with existing component patterns. Plugin system extends PluginManager with BasePlugin abstract class and CLI commands.

**Tech Stack:** ThinkPHP 8 + PHP 8.0+ / Vue 3 + TypeScript + Element Plus / UniApp + Wot Design Uni / Phinx migrations / PHPUnit / VitePress

**Pre-existing (no need to rebuild):**
- Payment backend complete: PaymentService, PaymentController, routes, PaymentManager + AlipayDriver + WechatPayDriver
- Message backend complete: MessageService, MessageTemplateController (admin), channels (SMS, WeChat Official, WeChat Mini)
- Rate limit middleware exists: ApiRateLimitMiddleware (basic 60/60)
- Exception hierarchy exists: AuthException(401), PermissionException(403), BusinessException(400), ValidationException(422), ApiException(custom)

---

## Chunk 1: Error Code System + Rate Limit Enhancement

### Task 1: Error Code Constants Class

**Files:**
- Create: `server/core/exception/ErrorCode.php`

- [ ] **Step 1: Create ErrorCode constants class**

```php
<?php
declare(strict_types=1);

namespace core\exception;

/**
 * 系统错误码分类
 *
 * 1xxx - 认证错误
 * 2xxx - 参数验证错误
 * 3xxx - 业务逻辑错误
 * 4xxx - 支付相关错误
 * 5xxx - 系统错误
 */
class ErrorCode
{
    // ---- 1xxx 认证错误 ----
    public const AUTH_TOKEN_EXPIRED     = 1001;
    public const AUTH_TOKEN_INVALID     = 1002;
    public const AUTH_TOKEN_MISSING     = 1003;
    public const AUTH_LOGIN_FAILED      = 1004;
    public const AUTH_ACCOUNT_DISABLED  = 1005;
    public const AUTH_ACCOUNT_LOCKED    = 1006;
    public const AUTH_PERMISSION_DENIED = 1007;
    public const AUTH_CAPTCHA_INVALID   = 1008;
    public const AUTH_SMS_CODE_INVALID  = 1009;
    public const AUTH_SMS_SEND_LIMIT    = 1010;

    // ---- 2xxx 参数验证错误 ----
    public const VALIDATE_FAILED       = 2001;
    public const VALIDATE_PARAM_MISSING = 2002;
    public const VALIDATE_FORMAT_ERROR = 2003;
    public const VALIDATE_UNIQUE_CONFLICT = 2004;

    // ---- 3xxx 业务逻辑错误 ----
    public const BIZ_RECORD_NOT_FOUND  = 3001;
    public const BIZ_RECORD_EXISTS     = 3002;
    public const BIZ_STATUS_INVALID    = 3003;
    public const BIZ_OPERATION_FAILED  = 3004;
    public const BIZ_UPLOAD_FAILED     = 3005;
    public const BIZ_TEMPLATE_NOT_FOUND = 3006;
    public const BIZ_FEEDBACK_CLOSED   = 3007;

    // ---- 4xxx 支付相关错误 ----
    public const PAY_CHANNEL_INVALID   = 4001;
    public const PAY_AMOUNT_INVALID    = 4002;
    public const PAY_ORDER_NOT_FOUND   = 4003;
    public const PAY_ORDER_PAID        = 4004;
    public const PAY_REFUND_EXCEED     = 4005;
    public const PAY_REFUND_FAILED     = 4006;
    public const PAY_NOTIFY_VERIFY_FAILED = 4007;

    // ---- 5xxx 系统错误 ----
    public const SYS_ERROR             = 5000;
    public const SYS_DB_ERROR          = 5001;
    public const SYS_CACHE_ERROR       = 5002;
    public const SYS_THIRD_PARTY_ERROR = 5003;
    public const SYS_RATE_LIMIT        = 5004;
}
```

- [ ] **Step 2: Verify PHP syntax**

Run: `cd server && php -l core/exception/ErrorCode.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Update existing exceptions to use ErrorCode**

Modify `server/core/exception/AuthException.php` — set default code to `ErrorCode::AUTH_TOKEN_INVALID`.
Modify `server/core/exception/ValidationException.php` — set default code to `ErrorCode::VALIDATE_FAILED`.

No changes to `ExceptionHandle.php` — it already uses `$e->getCode()` dynamically, so exceptions that carry ErrorCode values will flow through naturally.

- [ ] **Step 4: Commit**

```bash
git add server/core/exception/ErrorCode.php server/core/exception/AuthException.php server/core/exception/ValidationException.php
git commit -m "feat: add systematic error code classification (1xxx-5xxx)"
```

---

### Task 2: Rate Limit Enhancement

**Files:**
- Modify: `server/app/api/middleware/ApiRateLimitMiddleware.php`
- Modify: `server/app/api/route/auth.php` (add rate limit to auth routes)
- Create: `server/app/api/middleware/SmsRateLimitMiddleware.php`

- [ ] **Step 1: Create SMS-specific rate limit middleware**

```php
<?php
declare(strict_types=1);

namespace app\api\middleware;

use core\http\Middleware;
use Closure;
use think\Request;
use think\Response;

class SmsRateLimitMiddleware extends Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $mobile = (string) $request->param('mobile', '');
        if (empty($mobile)) {
            return $next($request);
        }

        // 同一手机号每分钟 1 次
        $minuteKey = 'sms_rate:minute:' . $mobile;
        if (!$this->checkRateLimit($minuteKey, 1, 60)) {
            return $this->errorResponse(lang('messages.sms_send_too_frequent'), 429);
        }

        // 同一手机号每天 10 次
        $dayKey = 'sms_rate:day:' . $mobile;
        if (!$this->checkRateLimit($dayKey, 10, 86400)) {
            return $this->errorResponse(lang('messages.sms_daily_limit'), 429);
        }

        return $next($request);
    }
}
```

- [ ] **Step 2: Update API rate limit middleware with differentiated limits**

Update `ApiRateLimitMiddleware.php`:

```php
<?php
declare(strict_types=1);

namespace app\api\middleware;

use core\http\Middleware;
use Closure;
use think\Request;
use think\Response;

class ApiRateLimitMiddleware extends Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->pathinfo();

        // 登录/注册接口：同一 IP 每分钟 10 次
        if ($this->isAuthPath($path)) {
            $key = 'api_rate:auth:' . $request->ip();
            if (!$this->checkRateLimit($key, 10, 60)) {
                return $this->errorResponse(lang('messages.too_many_requests'), 429);
            }
        }

        // 通用接口：同一用户每分钟 60 次（已认证用户）
        $userId = $request->userId ?? null;
        if ($userId) {
            $key = 'api_rate:user:' . $userId . ':' . $request->pathinfo();
            if (!$this->checkRateLimit($key, 60, 60)) {
                return $this->errorResponse(lang('messages.too_many_requests'), 429);
            }
        } else {
            // 未认证 IP 级别限流
            $key = 'api_rate:ip:' . $request->ip() . ':' . $request->pathinfo();
            if (!$this->checkRateLimit($key, 60, 60)) {
                return $this->errorResponse(lang('messages.too_many_requests'), 429);
            }
        }

        return $next($request);
    }

    protected function isAuthPath(string $path): bool
    {
        return str_contains($path, 'auth/login')
            || str_contains($path, 'auth/register')
            || str_contains($path, 'auth/sms-login');
    }
}
```

- [ ] **Step 3: Register SmsRateLimitMiddleware alias**

Add to `server/config/middleware.php`:
```php
'api_sms_rate_limit' => app\api\middleware\SmsRateLimitMiddleware::class,
```

- [ ] **Step 4: Apply SMS rate limit to SMS code route**

In `server/app/api/route/auth.php` (or common routes), add the `api_sms_rate_limit` middleware to the SMS code sending endpoint.

- [ ] **Step 5: Verify PHP syntax**

Run: `cd server && php -l app/api/middleware/SmsRateLimitMiddleware.php && php -l app/api/middleware/ApiRateLimitMiddleware.php`

- [ ] **Step 6: Commit**

```bash
git add server/app/api/middleware/ server/config/middleware.php server/app/api/route/
git commit -m "feat: enhance API rate limiting with per-endpoint and SMS-specific limits"
```

---

## Chunk 2: Feedback System Full Stack

### Task 3: Feedback Database Migration + Model + Repository

**Files:**
- Create: `server/database/migrations/YYYYMMDDHHMMSS_create_feedbacks_table.php`
- Create: `server/app/model/feedback/Feedback.php`
- Create: `server/app/repository/feedback/FeedbackRepository.php`

- [ ] **Step 1: Create feedbacks table migration**

```php
<?php
use think\migration\Migrator;

class CreateFeedbacksTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('feedbacks', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '用户反馈表',
        ]);

        $table
            ->addColumn('user_id', 'integer', ['signed' => false, 'comment' => '用户ID'])
            ->addColumn('type', 'string', ['limit' => 30, 'default' => 'suggestion', 'comment' => '类型：suggestion/bug/complaint/other'])
            ->addColumn('content', 'text', ['comment' => '反馈内容'])
            ->addColumn('images', 'text', ['null' => true, 'comment' => '图片路径JSON数组'])
            ->addColumn('contact', 'string', ['limit' => 100, 'null' => true, 'comment' => '联系方式'])
            ->addColumn('status', 'tinyinteger', ['default' => 0, 'comment' => '状态：0待处理 1处理中 2已回复 3已关闭'])
            ->addColumn('reply', 'text', ['null' => true, 'comment' => '管理员回复'])
            ->addColumn('replied_at', 'datetime', ['null' => true, 'comment' => '回复时间'])
            ->addColumn('replied_by', 'integer', ['null' => true, 'signed' => false, 'comment' => '回复人ID'])
            ->addColumn('created_at', 'datetime', ['comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间'])
            ->addIndex(['user_id'])
            ->addIndex(['status'])
            ->addIndex(['type'])
            ->create();
    }

    public function down(): void
    {
        $this->table('feedbacks')->drop()->save();
    }
}
```

- [ ] **Step 2: Run migration**

Run: `cd server && php think migrate:run`

- [ ] **Step 3: Create Feedback model**

```php
<?php
declare(strict_types=1);

namespace app\model\feedback;

use core\base\Model;

class Feedback extends Model
{
    protected $table = 'feedbacks';

    protected $fillable = [
        'user_id', 'type', 'content', 'images', 'contact',
        'status', 'reply', 'replied_at', 'replied_by',
    ];

    // 状态常量
    const STATUS_PENDING    = 0;
    const STATUS_PROCESSING = 1;
    const STATUS_REPLIED    = 2;
    const STATUS_CLOSED     = 3;

    protected $type = [
        'user_id'    => 'integer',
        'status'     => 'integer',
        'replied_by' => 'integer',
    ];

    public function getImagesAttr($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    public function setImagesAttr($value)
    {
        return is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
    }
}
```

- [ ] **Step 4: Create FeedbackRepository**

```php
<?php
declare(strict_types=1);

namespace app\repository\feedback;

use app\model\feedback\Feedback;
use core\base\Repository;

class FeedbackRepository extends Repository
{
    protected string $modelClass = Feedback::class;

    public function getSearchList(array $params, int $page = 1, int $limit = 20): array
    {
        $query = $this->model->order('created_at', 'desc');

        if (!empty($params['status']) || (isset($params['status']) && $params['status'] === 0)) {
            $query->where('status', (int) $params['status']);
        }
        if (!empty($params['type'])) {
            $query->where('type', $params['type']);
        }
        if (!empty($params['user_id'])) {
            $query->where('user_id', (int) $params['user_id']);
        }
        if (!empty($params['keyword'])) {
            $query->whereLike('content', "%{$params['keyword']}%");
        }

        return $this->getList($query, $page, $limit);
    }

    public function getUserFeedbacks(int $userId, int $page = 1, int $limit = 10): array
    {
        $query = $this->model->where('user_id', $userId)->order('created_at', 'desc');
        return $this->getList($query, $page, $limit);
    }
}
```

- [ ] **Step 5: Verify PHP syntax**

Run: `cd server && php -l app/model/feedback/Feedback.php && php -l app/repository/feedback/FeedbackRepository.php`

- [ ] **Step 6: Commit**

```bash
git add server/database/migrations/ server/app/model/feedback/ server/app/repository/feedback/
git commit -m "feat: add feedback system — migration, model, repository"
```

---

### Task 4: Feedback Service + Event Listener

**Files:**
- Create: `server/app/service/feedback/FeedbackService.php`
- Create: `server/app/listener/feedback/FeedbackCreatedListener.php`
- Modify: `server/app/event.php` (add feedback.created event)

- [ ] **Step 1: Create FeedbackService**

```php
<?php
declare(strict_types=1);

namespace app\service\feedback;

use app\model\feedback\Feedback;
use app\repository\feedback\FeedbackRepository;
use core\base\Service;
use core\exception\BusinessException;

class FeedbackService extends Service
{
    protected FeedbackRepository $feedbackRepository;

    /**
     * 用户提交反馈
     */
    public function submit(int $userId, array $data): array
    {
        $feedback = $this->feedbackRepository->create([
            'user_id' => $userId,
            'type'    => $data['type'] ?? 'suggestion',
            'content' => $data['content'],
            'images'  => $data['images'] ?? [],
            'contact' => $data['contact'] ?? '',
            'status'  => Feedback::STATUS_PENDING,
        ]);

        $this->trigger('feedback.created', [
            'feedback_id' => $feedback['id'],
            'user_id'     => $userId,
            'type'        => $data['type'] ?? 'suggestion',
        ]);

        return $feedback;
    }

    /**
     * 用户查看自己的反馈列表
     */
    public function getUserList(int $userId, array $params): array
    {
        $page = (int) ($params['page_no'] ?? 1);
        $limit = (int) ($params['page_size'] ?? 10);
        return $this->feedbackRepository->getUserFeedbacks($userId, $page, $limit);
    }

    /**
     * 管理员查看反馈列表
     */
    public function getList(array $params): array
    {
        $page = (int) ($params['page_no'] ?? 1);
        $limit = (int) ($params['page_size'] ?? 20);
        return $this->feedbackRepository->getSearchList($params, $page, $limit);
    }

    /**
     * 反馈详情
     */
    public function detail(int $id): ?array
    {
        return $this->feedbackRepository->find($id);
    }

    /**
     * 管理员回复反馈
     */
    public function reply(int $id, int $adminId, string $replyContent): bool
    {
        $feedback = $this->feedbackRepository->findModel($id);
        if (!$feedback) {
            throw new BusinessException(lang('business.record_not_found'));
        }

        if ($feedback->status === Feedback::STATUS_CLOSED) {
            throw new BusinessException(lang('business.feedback_closed'));
        }

        return (bool) $feedback->save([
            'reply'      => $replyContent,
            'replied_at' => date('Y-m-d H:i:s'),
            'replied_by' => $adminId,
            'status'     => Feedback::STATUS_REPLIED,
        ]);
    }

    /**
     * 管理员关闭反馈
     */
    public function close(int $id): bool
    {
        return $this->feedbackRepository->update($id, [
            'status' => Feedback::STATUS_CLOSED,
        ]);
    }

    /**
     * 删除反馈
     */
    public function delete(int $id): bool
    {
        return $this->feedbackRepository->delete($id);
    }
}
```

- [ ] **Step 2: Create FeedbackCreatedListener**

```php
<?php
declare(strict_types=1);

namespace app\listener\feedback;

use think\facade\Log;

class FeedbackCreatedListener
{
    public function handle(array $event): void
    {
        Log::info('用户提交反馈', [
            'feedback_id' => $event['feedback_id'],
            'user_id'     => $event['user_id'],
            'type'        => $event['type'],
        ]);

        // TODO: 通知管理员（邮件/站内消息）
    }
}
```

- [ ] **Step 3: Register event in event.php**

Add to `server/app/event.php` `listen` array:
```php
'feedback.created' => [\app\listener\feedback\FeedbackCreatedListener::class],
```

- [ ] **Step 4: Verify PHP syntax**

Run: `cd server && php -l app/service/feedback/FeedbackService.php && php -l app/listener/feedback/FeedbackCreatedListener.php`

- [ ] **Step 5: Commit**

```bash
git add server/app/service/feedback/ server/app/listener/feedback/ server/app/event.php
git commit -m "feat: add FeedbackService with event-driven side effects"
```

---

### Task 5: Feedback Admin Controller + Validate + Routes

**Files:**
- Create: `server/app/adminapi/controller/v1/feedback/FeedbackController.php`
- Create: `server/app/adminapi/validate/v1/feedback/FeedbackValidate.php`
- Create: `server/app/adminapi/route/feedback.php`

- [ ] **Step 1: Create FeedbackValidate**

```php
<?php
declare(strict_types=1);

namespace app\adminapi\validate\v1\feedback;

use core\base\Validate;

class FeedbackValidate extends Validate
{
    protected $rule = [
        'id'    => 'require|integer|>:0',
        'reply' => 'require|length:1,2000',
    ];

    protected $message = [
        'id.require'    => 'validation.id_require',
        'reply.require' => 'validation.reply_require',
        'reply.length'  => 'validation.reply_length',
    ];

    protected $scene = [
        'reply' => ['id', 'reply'],
    ];
}
```

- [ ] **Step 2: Create admin FeedbackController**

```php
<?php
declare(strict_types=1);

namespace app\adminapi\controller\v1\feedback;

use core\base\Controller;
use core\attribute\Permission;
use app\service\feedback\FeedbackService;
use app\adminapi\validate\v1\feedback\FeedbackValidate;
use think\Response;

class FeedbackController extends Controller
{
    protected FeedbackService $feedbackService;

    #[Permission('feedback.list')]
    public function list(): Response
    {
        $params = $this->request->only(['page_no', 'page_size', 'status', 'type', 'keyword']);
        $result = $this->feedbackService->getList($params);
        return $this->paginate($result);
    }

    #[Permission('feedback.detail')]
    public function detail(int $id): Response
    {
        $result = $this->feedbackService->detail($id);
        return $this->success(lang('messages.get_success'), $result);
    }

    #[Permission('feedback.reply')]
    public function reply(): Response
    {
        $data = $this->request->only(['id', 'reply']);
        $this->validate($data, FeedbackValidate::class, '', false, 'reply');
        $adminId = $this->getUserId();
        $this->feedbackService->reply((int) $data['id'], $adminId, $data['reply']);
        return $this->success(lang('messages.operation_success'));
    }

    #[Permission('feedback.close')]
    public function close(int $id): Response
    {
        $this->feedbackService->close($id);
        return $this->success(lang('messages.operation_success'));
    }

    #[Permission('feedback.delete')]
    public function delete(int $id): Response
    {
        $this->feedbackService->delete($id);
        return $this->success(lang('messages.delete_success'));
    }
}
```

- [ ] **Step 3: Create admin feedback routes**

```php
<?php
use think\facade\Route;

Route::group('feedback', function () {
    Route::get('list', 'v1.feedback.FeedbackController/list');
    Route::get('detail/:id', 'v1.feedback.FeedbackController/detail');
    Route::post('reply', 'v1.feedback.FeedbackController/reply');
    Route::post('close/:id', 'v1.feedback.FeedbackController/close');
    Route::delete(':id', 'v1.feedback.FeedbackController/delete');
})->middleware(['admin_auth', 'admin_permission', 'admin_log']);
```

- [ ] **Step 4: Verify PHP syntax**

Run: `cd server && php -l app/adminapi/controller/v1/feedback/FeedbackController.php && php -l app/adminapi/validate/v1/feedback/FeedbackValidate.php`

- [ ] **Step 5: Commit**

```bash
git add server/app/adminapi/controller/v1/feedback/ server/app/adminapi/validate/v1/feedback/ server/app/adminapi/route/feedback.php
git commit -m "feat: add feedback admin controller with RBAC permissions"
```

---

### Task 6: Feedback C-end API Controller + Routes

**Files:**
- Create: `server/app/api/controller/v1/feedback/FeedbackController.php`
- Create: `server/app/api/route/feedback.php`

- [ ] **Step 1: Create C-end FeedbackController**

```php
<?php
declare(strict_types=1);

namespace app\api\controller\v1\feedback;

use core\base\Controller;
use app\service\feedback\FeedbackService;
use think\Response;

class FeedbackController extends Controller
{
    protected FeedbackService $feedbackService;

    /**
     * 提交反馈
     */
    public function submit(): Response
    {
        $data = $this->request->only(['type', 'content', 'images', 'contact']);

        if (empty($data['content'])) {
            return $this->error(lang('validation.content_require'));
        }

        $userId = $this->getUserId();
        $result = $this->feedbackService->submit($userId, $data);
        return $this->success(lang('messages.submit_success'), $result);
    }

    /**
     * 我的反馈列表
     */
    public function list(): Response
    {
        $params = $this->request->only(['page_no', 'page_size']);
        $userId = $this->getUserId();
        $result = $this->feedbackService->getUserList($userId, $params);
        return $this->paginate($result);
    }

    /**
     * 反馈详情
     */
    public function detail(int $id): Response
    {
        $result = $this->feedbackService->detail($id);
        return $this->success(lang('messages.get_success'), $result);
    }
}
```

- [ ] **Step 2: Create C-end feedback routes**

```php
<?php
use think\facade\Route;

Route::group('feedback', function () {
    Route::post('submit', 'v1.feedback.FeedbackController/submit');
    Route::get('list', 'v1.feedback.FeedbackController/list');
    Route::get('detail/:id', 'v1.feedback.FeedbackController/detail');
})->middleware(['api_auth']);
```

- [ ] **Step 3: Verify PHP syntax**

Run: `cd server && php -l app/api/controller/v1/feedback/FeedbackController.php`

- [ ] **Step 4: Commit**

```bash
git add server/app/api/controller/v1/feedback/ server/app/api/route/feedback.php
git commit -m "feat: add C-end feedback API (submit, list, detail)"
```

---

## Chunk 3: C-end Message API + UniApp Modules

### Task 7: C-end User Message API

The existing MessageService handles template-based sending. C-end users need: message list, mark read, unread count. This requires a user-facing notifications model (leveraging existing `notification_reads` migration).

**Files:**
- Create: `server/app/model/notification/UserNotification.php`
- Create: `server/app/repository/notification/UserNotificationRepository.php`
- Create: `server/app/service/notification/UserNotificationService.php`
- Create: `server/app/api/controller/v1/message/MessageController.php`
- Create: `server/app/api/route/message.php`

- [ ] **Step 1: Check existing notification tables**

Run: `cd server && php think migrate:status` to verify `notification_reads` table exists.
Also check if there's a `notifications` migration — if not, create one.

- [ ] **Step 2: Create notifications migration if needed**

If `notifications` table doesn't exist, create migration:

```php
<?php
use think\migration\Migrator;

class CreateNotificationsTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('notifications', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '站内通知表',
        ]);

        $table
            ->addColumn('user_id', 'integer', ['signed' => false, 'default' => 0, 'comment' => '用户ID，0为全体'])
            ->addColumn('title', 'string', ['limit' => 200, 'comment' => '标题'])
            ->addColumn('content', 'text', ['comment' => '内容'])
            ->addColumn('type', 'string', ['limit' => 30, 'default' => 'system', 'comment' => '类型：system/order/payment/feedback'])
            ->addColumn('biz_id', 'integer', ['null' => true, 'comment' => '关联业务ID'])
            ->addColumn('extra', 'text', ['null' => true, 'comment' => '额外数据JSON'])
            ->addColumn('created_at', 'datetime', ['comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间'])
            ->addIndex(['user_id'])
            ->addIndex(['type'])
            ->create();
    }

    public function down(): void
    {
        $this->table('notifications')->drop()->save();
    }
}
```

- [ ] **Step 3: Create UserNotification model**

```php
<?php
declare(strict_types=1);

namespace app\model\notification;

use core\base\Model;

class UserNotification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'user_id', 'title', 'content', 'type', 'biz_id', 'extra',
    ];

    protected $type = [
        'user_id' => 'integer',
        'biz_id'  => 'integer',
    ];

    public function getExtraAttr($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    public function setExtraAttr($value)
    {
        return is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
    }
}
```

- [ ] **Step 4: Create UserNotificationRepository**

```php
<?php
declare(strict_types=1);

namespace app\repository\notification;

use app\model\notification\UserNotification;
use core\base\Repository;
use think\facade\Db;

class UserNotificationRepository extends Repository
{
    protected string $modelClass = UserNotification::class;

    /**
     * 获取用户消息列表（包含个人 + 全体通知）
     */
    public function getUserMessages(int $userId, int $page = 1, int $limit = 10): array
    {
        $query = $this->model
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->whereOr('user_id', 0);
            })
            ->order('created_at', 'desc');

        return $this->getList($query, $page, $limit);
    }

    /**
     * 获取未读消息数
     */
    public function getUnreadCount(int $userId): int
    {
        $readIds = Db::table('notification_reads')
            ->where('user_id', $userId)
            ->column('notification_id');

        $query = $this->model->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)->whereOr('user_id', 0);
        });

        if (!empty($readIds)) {
            $query->whereNotIn('id', $readIds);
        }

        return $query->count();
    }

    /**
     * 标记已读
     */
    public function markAsRead(int $userId, array $notificationIds): void
    {
        $existing = Db::table('notification_reads')
            ->where('user_id', $userId)
            ->whereIn('notification_id', $notificationIds)
            ->column('notification_id');

        $inserts = [];
        foreach ($notificationIds as $id) {
            if (!in_array($id, $existing)) {
                $inserts[] = [
                    'user_id'         => $userId,
                    'notification_id' => $id,
                    'read_at'         => date('Y-m-d H:i:s'),
                ];
            }
        }

        if (!empty($inserts)) {
            Db::table('notification_reads')->insertAll($inserts);
        }
    }

    /**
     * 标记全部已读
     */
    public function markAllAsRead(int $userId): void
    {
        $unreadIds = $this->model
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->whereOr('user_id', 0);
            })
            ->column('id');

        if (!empty($unreadIds)) {
            $this->markAsRead($userId, $unreadIds);
        }
    }
}
```

- [ ] **Step 5: Create UserNotificationService**

```php
<?php
declare(strict_types=1);

namespace app\service\notification;

use app\repository\notification\UserNotificationRepository;
use core\base\Service;

class UserNotificationService extends Service
{
    protected UserNotificationRepository $notificationRepository;

    public function getUserMessages(int $userId, array $params): array
    {
        $page = (int) ($params['page_no'] ?? 1);
        $limit = (int) ($params['page_size'] ?? 10);
        return $this->notificationRepository->getUserMessages($userId, $page, $limit);
    }

    public function getUnreadCount(int $userId): int
    {
        return $this->notificationRepository->getUnreadCount($userId);
    }

    public function markAsRead(int $userId, array $ids): void
    {
        $this->notificationRepository->markAsRead($userId, $ids);
    }

    public function markAllAsRead(int $userId): void
    {
        $this->notificationRepository->markAllAsRead($userId);
    }

    /**
     * 创建通知（供 Listener 调用）
     */
    public function createNotification(array $data): array
    {
        return $this->notificationRepository->create($data);
    }
}
```

- [ ] **Step 6: Create C-end MessageController**

```php
<?php
declare(strict_types=1);

namespace app\api\controller\v1\message;

use core\base\Controller;
use app\service\notification\UserNotificationService;
use think\Response;

class MessageController extends Controller
{
    protected UserNotificationService $notificationService;

    public function list(): Response
    {
        $params = $this->request->only(['page_no', 'page_size']);
        $userId = $this->getUserId();
        $result = $this->notificationService->getUserMessages($userId, $params);
        return $this->paginate($result);
    }

    public function unreadCount(): Response
    {
        $userId = $this->getUserId();
        $count = $this->notificationService->getUnreadCount($userId);
        return $this->success(lang('messages.get_success'), ['count' => $count]);
    }

    public function read(): Response
    {
        $ids = $this->request->param('ids', []);
        $userId = $this->getUserId();

        if (empty($ids)) {
            $this->notificationService->markAllAsRead($userId);
        } else {
            $this->notificationService->markAsRead($userId, (array) $ids);
        }

        return $this->success(lang('messages.operation_success'));
    }
}
```

- [ ] **Step 7: Create C-end message routes**

```php
<?php
use think\facade\Route;

Route::group('message', function () {
    Route::get('list', 'v1.message.MessageController/list');
    Route::get('unread-count', 'v1.message.MessageController/unreadCount');
    Route::post('read', 'v1.message.MessageController/read');
})->middleware(['api_auth']);
```

- [ ] **Step 8: Verify PHP syntax**

Run: `cd server && php -l app/model/notification/UserNotification.php && php -l app/repository/notification/UserNotificationRepository.php && php -l app/service/notification/UserNotificationService.php && php -l app/api/controller/v1/message/MessageController.php`

- [ ] **Step 9: Commit**

```bash
git add server/database/migrations/ server/app/model/notification/ server/app/repository/notification/ server/app/service/notification/ server/app/api/controller/v1/message/ server/app/api/route/message.php
git commit -m "feat: add C-end message/notification API (list, unread count, mark read)"
```

---

### Task 8: UniApp Payment Module

**Files:**
- Create: `uniapp/src/api/payment.ts`
- Create: `uniapp/src/modules/payment/composables/usePayment.ts`
- Create: `uniapp/src/modules/payment/pages/pay-result.vue`
- Create: `uniapp/src/components/d-payment-popup/d-payment-popup.vue`
- Create: `uniapp/src/components/d-pay-result/d-pay-result.vue`
- Create: `uniapp/src/components/d-price/d-price.vue`
- Modify: `uniapp/src/pages.json` (add payment subpackage)

- [ ] **Step 1: Create payment API**

```typescript
import { http } from '@/utils/request'

export const paymentApi = {
  createOrder: (data: {
    channel: 'alipay' | 'wechat'
    subject: string
    total_amount: number
    trade_type?: string
    openid?: string
  }) => http.post<{ order_no: string; payment_id: number; payment_data: any }>('/api/payment/create', data),

  queryOrder: (channel: string, orderNo: string) =>
    http.get<{ status: string; trade_no: string }>('/api/payment/query', { channel, order_no: orderNo }),

  refund: (data: { channel: string; order_no: string; refund_amount: number; reason?: string }) =>
    http.post('/api/payment/refund', data),
}
```

- [ ] **Step 2: Create usePayment composable**

```typescript
import { ref } from 'vue'
import { paymentApi } from '@/api/payment'
import { getPlatform } from '@/utils/platform'

export function usePayment() {
  const loading = ref(false)
  const orderNo = ref('')

  async function pay(options: {
    channel: 'alipay' | 'wechat'
    subject: string
    amount: number
    openid?: string
  }): Promise<boolean> {
    loading.value = true
    try {
      const tradeType = getTradeType(options.channel)
      const result = await paymentApi.createOrder({
        channel: options.channel,
        subject: options.subject,
        total_amount: options.amount,
        trade_type: tradeType,
        openid: options.openid,
      })

      orderNo.value = result.order_no

      if (options.channel === 'wechat') {
        return await callWechatPay(result.payment_data)
      } else {
        return await callAlipay(result.payment_data)
      }
    } catch {
      return false
    } finally {
      loading.value = false
    }
  }

  function getTradeType(channel: string): string {
    const platform = getPlatform()
    if (channel === 'wechat') {
      if (platform === 'mp-weixin') return 'jsapi'
      if (platform === 'app') return 'app'
      return 'h5'
    }
    if (platform === 'app') return 'app'
    return 'page'
  }

  async function callWechatPay(paymentData: any): Promise<boolean> {
    return new Promise((resolve) => {
      uni.requestPayment({
        provider: 'wxpay',
        ...paymentData,
        success: () => resolve(true),
        fail: () => resolve(false),
      })
    })
  }

  async function callAlipay(paymentData: any): Promise<boolean> {
    return new Promise((resolve) => {
      // #ifdef APP-PLUS
      uni.requestPayment({
        provider: 'alipay',
        orderInfo: paymentData.orderInfo || paymentData,
        success: () => resolve(true),
        fail: () => resolve(false),
      })
      // #endif
      // #ifdef H5
      // H5 端跳转支付页面
      if (paymentData.url) {
        window.location.href = paymentData.url
      }
      resolve(false)
      // #endif
    })
  }

  async function checkPayResult(channel: string): Promise<string> {
    if (!orderNo.value) return 'unknown'
    const result = await paymentApi.queryOrder(channel, orderNo.value)
    return result.status
  }

  return { loading, orderNo, pay, checkPayResult }
}
```

- [ ] **Step 3: Create d-payment-popup component**

A popup that lets users select payment method and triggers payment.

```vue
<template>
  <wd-popup v-model="show" position="bottom" round closable>
    <view class="payment-popup">
      <view class="popup-title">选择支付方式</view>
      <view class="payment-list">
        <view
          v-for="method in availableMethods"
          :key="method.channel"
          class="payment-item"
          :class="{ active: selected === method.channel }"
          @tap="selected = method.channel"
        >
          <wd-icon :name="method.icon" size="48rpx" />
          <text class="payment-name">{{ method.name }}</text>
          <wd-icon v-if="selected === method.channel" name="check" color="#4d80f0" />
        </view>
      </view>
      <wd-button
        block
        :loading="loading"
        :disabled="!selected || loading"
        class="pay-btn"
        @click="handlePay"
      >
        确认支付 ¥{{ amount.toFixed(2) }}
      </wd-button>
    </view>
  </wd-popup>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { isWeixin } from '@/utils/platform'

const props = defineProps<{
  modelValue: boolean
  amount: number
  loading?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  'pay': [channel: 'alipay' | 'wechat']
}>()

const show = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val),
})

const selected = ref<'alipay' | 'wechat' | ''>('')

const availableMethods = computed(() => {
  const methods = []
  methods.push({ channel: 'wechat', name: '微信支付', icon: 'chat' })
  if (!isWeixin()) {
    methods.push({ channel: 'alipay', name: '支付宝支付', icon: 'money-circle' })
  }
  return methods
})

function handlePay() {
  if (selected.value) {
    emit('pay', selected.value as 'alipay' | 'wechat')
  }
}
</script>

<style lang="scss" scoped>
.payment-popup {
  padding: 40rpx;
}
.popup-title {
  font-size: 32rpx;
  font-weight: 600;
  text-align: center;
  margin-bottom: 40rpx;
}
.payment-item {
  display: flex;
  align-items: center;
  padding: 28rpx 20rpx;
  border-bottom: 1rpx solid #f0f0f0;
  .payment-name { flex: 1; margin-left: 20rpx; font-size: 30rpx; }
  &.active { background: #f0f4ff; border-radius: 12rpx; }
}
.pay-btn {
  margin-top: 40rpx;
  border-radius: 16rpx !important;
  height: 96rpx !important;
  font-size: 32rpx !important;
}
</style>
```

- [ ] **Step 4: Create d-price component**

```vue
<template>
  <view class="d-price" :class="{ 'has-original': originalPrice }">
    <text class="symbol">¥</text>
    <text class="integer">{{ integer }}</text>
    <text v-if="decimal !== '00'" class="decimal">.{{ decimal }}</text>
    <text v-if="originalPrice" class="original">¥{{ originalPrice.toFixed(2) }}</text>
  </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  price: number
  originalPrice?: number
}>()

const integer = computed(() => Math.floor(props.price).toString())
const decimal = computed(() => {
  const d = (props.price % 1).toFixed(2).slice(2)
  return d
})
</script>

<style lang="scss" scoped>
.d-price {
  display: inline-flex;
  align-items: baseline;
  color: #e63946;
  .symbol { font-size: 24rpx; }
  .integer { font-size: 36rpx; font-weight: 700; }
  .decimal { font-size: 24rpx; }
  .original {
    font-size: 24rpx;
    color: #999;
    text-decoration: line-through;
    margin-left: 8rpx;
  }
}
</style>
```

- [ ] **Step 5: Create d-pay-result component**

```vue
<template>
  <view class="pay-result">
    <wd-icon :name="success ? 'check' : 'close'" :size="120" :color="success ? '#07c160' : '#e63946'" />
    <text class="result-text">{{ success ? '支付成功' : '支付失败' }}</text>
    <text v-if="message" class="result-msg">{{ message }}</text>
    <slot />
  </view>
</template>

<script setup lang="ts">
defineProps<{
  success: boolean
  message?: string
}>()
</script>

<style lang="scss" scoped>
.pay-result {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 80rpx 40rpx;
  .result-text { font-size: 36rpx; font-weight: 600; margin-top: 32rpx; }
  .result-msg { font-size: 28rpx; color: #999; margin-top: 16rpx; }
}
</style>
```

- [ ] **Step 6: Create pay-result page**

```vue
<template>
  <d-page :safe-area="true">
    <d-pay-result :success="paySuccess" :message="resultMessage">
      <view class="result-actions">
        <wd-button block @click="goHome">返回首页</wd-button>
        <wd-button v-if="!paySuccess" block plain @click="retry">重新支付</wd-button>
      </view>
    </d-pay-result>
  </d-page>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { usePayment } from '../composables/usePayment'

const { checkPayResult } = usePayment()

const paySuccess = ref(false)
const resultMessage = ref('')

onMounted(async () => {
  const channel = uni.getStorageSync('last_pay_channel') || 'wechat'
  const status = await checkPayResult(channel)
  paySuccess.value = status === 'paid'
  resultMessage.value = paySuccess.value ? '感谢您的购买' : '支付未完成，请重试'
})

function goHome() {
  uni.reLaunch({ url: '/pages/index/index' })
}

function retry() {
  uni.navigateBack()
}
</script>

<style lang="scss" scoped>
.result-actions {
  margin-top: 60rpx;
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 20rpx;
}
</style>
```

- [ ] **Step 7: Update pages.json with payment subpackage**

Add to `uniapp/src/pages.json` subPackages array:
```json
{
  "root": "modules/payment",
  "pages": [
    { "path": "pages/pay-result", "style": { "navigationBarTitleText": "支付结果" } }
  ]
}
```

- [ ] **Step 8: TypeScript check**

Run: `cd uniapp && pnpm exec vue-tsc --noEmit`

- [ ] **Step 9: Commit**

```bash
git add uniapp/src/api/payment.ts uniapp/src/modules/payment/ uniapp/src/components/d-payment-popup/ uniapp/src/components/d-pay-result/ uniapp/src/components/d-price/ uniapp/src/pages.json
git commit -m "feat: add UniApp payment module (popup, result, price components)"
```

---

### Task 9: UniApp Message Module

**Files:**
- Create: `uniapp/src/api/message.ts`
- Create: `uniapp/src/modules/message/pages/message-list.vue`
- Create: `uniapp/src/modules/message/pages/message-detail.vue`
- Modify: `uniapp/src/pages.json` (add message subpackage)

- [ ] **Step 1: Create message API**

```typescript
import { http } from '@/utils/request'
import type { PageResult } from '@/types/api'

export interface NotificationInfo {
  id: number
  title: string
  content: string
  type: string
  biz_id: number | null
  extra: Record<string, any>
  created_at: string
}

export const messageApi = {
  getList: (params: { page_no: number; page_size: number }) =>
    http.get<PageResult<NotificationInfo>>('/api/message/list', params),

  getUnreadCount: () =>
    http.get<{ count: number }>('/api/message/unread-count'),

  markAsRead: (ids?: number[]) =>
    http.post('/api/message/read', { ids }),
}
```

- [ ] **Step 2: Create message-list page**

```vue
<template>
  <d-page :safe-area="true">
    <view class="message-list-page">
      <view v-if="list.length > 0" class="mark-all" @tap="markAllRead">
        <text>全部已读</text>
      </view>
      <d-list-loader :loading="loading" :finished="finished" @load="getList">
        <view v-for="item in list" :key="item.id" class="message-item" @tap="goDetail(item)">
          <view class="message-header">
            <text class="message-type">{{ typeLabel(item.type) }}</text>
            <text class="message-time">{{ item.created_at }}</text>
          </view>
          <text class="message-title">{{ item.title }}</text>
          <text class="message-content">{{ item.content }}</text>
        </view>
      </d-list-loader>
      <d-empty v-if="!loading && list.length === 0" description="暂无消息" />
    </view>
  </d-page>
</template>

<script setup lang="ts">
import { messageApi, type NotificationInfo } from '@/api/message'
import { usePaging } from '@/hooks/usePaging'

const { list, loading, finished, getList } = usePaging<NotificationInfo>({
  fetchFun: (params) => messageApi.getList(params),
})

getList()

function typeLabel(type: string): string {
  const map: Record<string, string> = {
    system: '系统通知',
    order: '订单消息',
    payment: '支付通知',
    feedback: '反馈回复',
  }
  return map[type] || '通知'
}

function goDetail(item: NotificationInfo) {
  messageApi.markAsRead([item.id])
  uni.navigateTo({ url: `/modules/message/pages/message-detail?id=${item.id}` })
}

async function markAllRead() {
  await messageApi.markAsRead()
  uni.showToast({ title: '已全部标记为已读', icon: 'none' })
}
</script>

<style lang="scss" scoped>
.message-list-page { padding: 0; }
.mark-all {
  text-align: right;
  padding: 16rpx 24rpx;
  font-size: 26rpx;
  color: #4d80f0;
}
.message-item {
  background: #fff;
  padding: 28rpx 32rpx;
  margin-bottom: 2rpx;
  .message-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12rpx;
  }
  .message-type { font-size: 24rpx; color: #4d80f0; }
  .message-time { font-size: 24rpx; color: #999; }
  .message-title { display: block; font-size: 30rpx; font-weight: 600; margin-bottom: 8rpx; }
  .message-content {
    display: block;
    font-size: 26rpx;
    color: #666;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}
</style>
```

- [ ] **Step 3: Create message-detail page**

A simple page that receives message ID via route query, displays full content.

- [ ] **Step 4: Update pages.json**

Add message subpackage:
```json
{
  "root": "modules/message",
  "pages": [
    { "path": "pages/message-list", "style": { "navigationBarTitleText": "消息中心" } },
    { "path": "pages/message-detail", "style": { "navigationBarTitleText": "消息详情" } }
  ]
}
```

- [ ] **Step 5: TypeScript check and commit**

```bash
cd uniapp && pnpm exec vue-tsc --noEmit
git add uniapp/src/api/message.ts uniapp/src/modules/message/ uniapp/src/pages.json
git commit -m "feat: add UniApp message module (list, detail, mark read)"
```

---

### Task 10: UniApp Feedback Module

**Files:**
- Create: `uniapp/src/api/feedback.ts`
- Create: `uniapp/src/modules/feedback/pages/feedback.vue`
- Create: `uniapp/src/components/d-image-preview/d-image-preview.vue`
- Modify: `uniapp/src/pages.json` (add feedback subpackage)

- [ ] **Step 1: Create feedback API**

```typescript
import { http } from '@/utils/request'
import type { PageResult } from '@/types/api'

export interface FeedbackInfo {
  id: number
  type: string
  content: string
  images: string[]
  contact: string
  status: number
  reply: string | null
  replied_at: string | null
  created_at: string
}

export const feedbackApi = {
  submit: (data: {
    type: string
    content: string
    images?: string[]
    contact?: string
  }) => http.post<FeedbackInfo>('/api/feedback/submit', data),

  getList: (params: { page_no: number; page_size: number }) =>
    http.get<PageResult<FeedbackInfo>>('/api/feedback/list', params),

  getDetail: (id: number) =>
    http.get<FeedbackInfo>(`/api/feedback/detail/${id}`),
}
```

- [ ] **Step 2: Create feedback page**

```vue
<template>
  <d-page :safe-area="true">
    <view class="feedback-page">
      <!-- 反馈类型 -->
      <view class="section-card">
        <view class="section-title">反馈类型</view>
        <view class="type-list">
          <view
            v-for="t in types"
            :key="t.value"
            class="type-item"
            :class="{ active: form.type === t.value }"
            @tap="form.type = t.value"
          >
            {{ t.label }}
          </view>
        </view>
      </view>

      <!-- 反馈内容 -->
      <view class="section-card">
        <view class="section-title">问题描述</view>
        <wd-textarea
          v-model="form.content"
          placeholder="请详细描述您遇到的问题或建议..."
          :maxlength="500"
          show-word-limit
          no-border
        />
      </view>

      <!-- 图片上传 -->
      <view class="section-card">
        <view class="section-title">上传截图（可选）</view>
        <wd-upload
          v-model:file-list="imageList"
          :limit="3"
          :before-upload="beforeUpload"
        />
      </view>

      <!-- 联系方式 -->
      <view class="section-card">
        <wd-input
          v-model="form.contact"
          placeholder="联系方式（手机/邮箱，方便我们回复您）"
          no-border
        />
      </view>

      <!-- 提交按钮 -->
      <wd-button
        block
        :loading="loading"
        :disabled="loading || !form.content.trim()"
        class="submit-btn"
        @click="handleSubmit"
      >
        提交反馈
      </wd-button>
    </view>
  </d-page>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { feedbackApi } from '@/api/feedback'
import { useUpload } from '@/hooks/useUpload'

const loading = ref(false)
const imageList = ref<any[]>([])
const { chooseAndUpload } = useUpload({ maxSize: 5 })

const types = [
  { label: '功能建议', value: 'suggestion' },
  { label: '问题反馈', value: 'bug' },
  { label: '投诉', value: 'complaint' },
  { label: '其他', value: 'other' },
]

const form = reactive({
  type: 'suggestion',
  content: '',
  contact: '',
})

async function beforeUpload(file: any) {
  try {
    const path = await chooseAndUpload()
    return { url: path }
  } catch {
    return false
  }
}

async function handleSubmit() {
  if (!form.content.trim()) {
    uni.showToast({ title: '请输入反馈内容', icon: 'none' })
    return
  }

  loading.value = true
  try {
    const images = imageList.value.map((f: any) => f.url).filter(Boolean)
    await feedbackApi.submit({
      type: form.type,
      content: form.content,
      images,
      contact: form.contact,
    })
    uni.showToast({ title: '提交成功', icon: 'success' })
    setTimeout(() => uni.navigateBack(), 1500)
  } finally {
    loading.value = false
  }
}
</script>

<style lang="scss" scoped>
.feedback-page { padding: 0; }
.section-card {
  background: #fff;
  border-radius: 24rpx;
  padding: 28rpx 32rpx;
  margin-bottom: 24rpx;
  box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.05);
}
.section-title { font-size: 28rpx; font-weight: 600; margin-bottom: 20rpx; }
.type-list { display: flex; flex-wrap: wrap; gap: 16rpx; }
.type-item {
  padding: 12rpx 28rpx;
  background: #f5f5f5;
  border-radius: 32rpx;
  font-size: 26rpx;
  &.active { background: #e8f0fe; color: #4d80f0; }
}
.submit-btn {
  border-radius: 16rpx !important;
  height: 96rpx !important;
  font-size: 32rpx !important;
  margin-top: 12rpx;
}
</style>
```

- [ ] **Step 3: Update pages.json**

Add feedback subpackage:
```json
{
  "root": "modules/feedback",
  "pages": [
    { "path": "pages/feedback", "style": { "navigationBarTitleText": "意见反馈" } }
  ]
}
```

- [ ] **Step 4: TypeScript check and commit**

```bash
cd uniapp && pnpm exec vue-tsc --noEmit
git add uniapp/src/api/feedback.ts uniapp/src/modules/feedback/ uniapp/src/pages.json
git commit -m "feat: add UniApp feedback module with image upload"
```

---

## Chunk 4: Plugin System Foundation

### Task 11: BasePlugin Abstract Class

**Files:**
- Create: `server/core/plugin/BasePlugin.php`

- [ ] **Step 1: Create BasePlugin**

PluginManager::getInstance() returns `BasePlugin`. The class needs: install/uninstall/enable/disable lifecycle, migration support, dependency checking, metadata access.

```php
<?php
declare(strict_types=1);

namespace core\plugin;

use think\facade\Db;
use think\facade\Log;

abstract class BasePlugin
{
    protected string $name;
    protected array $info = [];

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->loadInfo();
    }

    protected function loadInfo(): void
    {
        $infoFile = $this->getPath() . 'plugin.json';
        if (file_exists($infoFile)) {
            $this->info = json_decode(file_get_contents($infoFile), true) ?: [];
        }
    }

    public function getPath(): string
    {
        return root_path('plugins') . $this->name . '/';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTitle(): string
    {
        return $this->info['title'] ?? $this->name;
    }

    public function getVersion(): string
    {
        return $this->info['version'] ?? '1.0.0';
    }

    public function getAuthor(): string
    {
        return $this->info['author'] ?? '';
    }

    public function getDescription(): string
    {
        return $this->info['description'] ?? '';
    }

    /**
     * 检查依赖
     */
    public function checkDependencies(): bool
    {
        $requires = $this->info['require'] ?? [];
        foreach ($requires as $plugin => $version) {
            if (!PluginManager::isInstalled($plugin)) {
                Log::warning("插件依赖未满足: {$this->name} 需要 {$plugin}");
                return false;
            }
        }
        return true;
    }

    /**
     * 执行数据库迁移
     */
    public function runMigrations(): void
    {
        $migrationPath = $this->getPath() . 'backend/migration/';
        if (!is_dir($migrationPath)) {
            return;
        }

        $files = glob($migrationPath . '*.php');
        sort($files);

        foreach ($files as $file) {
            require_once $file;
            $className = $this->getMigrationClassName($file);
            if (class_exists($className)) {
                $migration = new $className();
                if (method_exists($migration, 'up')) {
                    $migration->up();
                }
            }
        }
    }

    /**
     * 回滚数据库迁移
     */
    public function rollbackMigrations(): void
    {
        $migrationPath = $this->getPath() . 'backend/migration/';
        if (!is_dir($migrationPath)) {
            return;
        }

        $files = glob($migrationPath . '*.php');
        rsort($files); // 逆序回滚

        foreach ($files as $file) {
            require_once $file;
            $className = $this->getMigrationClassName($file);
            if (class_exists($className)) {
                $migration = new $className();
                if (method_exists($migration, 'down')) {
                    $migration->down();
                }
            }
        }
    }

    protected function getMigrationClassName(string $file): string
    {
        $basename = pathinfo($file, PATHINFO_FILENAME);
        // Remove timestamp prefix: 20260101120000_create_xxx_table → CreateXxxTable
        $parts = explode('_', $basename, 2);
        $name = $parts[1] ?? $basename;
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
    }

    /**
     * 安装（子类可覆盖）
     */
    public function install(): bool
    {
        $installFile = $this->getPath() . 'install.php';
        if (file_exists($installFile)) {
            include $installFile;
        }
        return true;
    }

    /**
     * 卸载（子类可覆盖）
     */
    public function uninstall(): bool
    {
        $uninstallFile = $this->getPath() . 'uninstall.php';
        if (file_exists($uninstallFile)) {
            include $uninstallFile;
        }
        return true;
    }

    /**
     * 启用（子类可覆盖）
     */
    public function enable(): bool
    {
        return true;
    }

    /**
     * 禁用（子类可覆盖）
     */
    public function disable(): bool
    {
        return true;
    }

    /**
     * 升级（子类可覆盖）
     */
    public function upgrade(string $version): bool
    {
        return true;
    }
}
```

- [ ] **Step 2: Verify PHP syntax**

Run: `cd server && php -l core/plugin/BasePlugin.php`

- [ ] **Step 3: Commit**

```bash
git add server/core/plugin/BasePlugin.php
git commit -m "feat: add BasePlugin abstract class for plugin lifecycle management"
```

---

### Task 12: Plugin CLI Commands

**Files:**
- Create: `server/core/plugin/command/ListCommand.php`
- Create: `server/core/plugin/command/InstallCommand.php`
- Create: `server/core/plugin/command/UninstallCommand.php`
- Create: `server/core/plugin/command/EnableCommand.php`
- Create: `server/core/plugin/command/DisableCommand.php`
- Modify: `server/config/console.php` (register commands)

- [ ] **Step 1: Create ListCommand**

```php
<?php
declare(strict_types=1);

namespace core\plugin\command;

use core\plugin\PluginManager;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class ListCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('plugin:list')
            ->setDescription('List all available plugins');
    }

    protected function execute(Input $input, Output $output): int
    {
        $plugins = PluginManager::scanAvailablePlugins();

        if (empty($plugins)) {
            $output->writeln('<info>No plugins found in plugins/ directory.</info>');
            return 0;
        }

        $output->writeln('<info>Available plugins:</info>');
        $output->writeln(str_pad('Name', 20) . str_pad('Version', 12) . str_pad('Status', 12) . 'Description');
        $output->writeln(str_repeat('-', 70));

        foreach ($plugins as $name => $info) {
            $status = $info['enabled'] ? '<fg=green>enabled</>' : ($info['installed'] ? '<fg=yellow>disabled</>' : '<fg=gray>not installed</>');
            $output->writeln(
                str_pad($name, 20)
                . str_pad($info['version'] ?? '-', 12)
                . str_pad($status, 22)
                . ($info['description'] ?? '')
            );
        }

        return 0;
    }
}
```

- [ ] **Step 2: Create InstallCommand**

```php
<?php
declare(strict_types=1);

namespace core\plugin\command;

use core\plugin\PluginManager;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;

class InstallCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('plugin:install')
            ->addArgument('name', Argument::REQUIRED, 'Plugin name')
            ->setDescription('Install a plugin');
    }

    protected function execute(Input $input, Output $output): int
    {
        $name = $input->getArgument('name');

        if (PluginManager::isInstalled($name)) {
            $output->writeln("<error>Plugin '{$name}' is already installed.</error>");
            return 1;
        }

        $output->writeln("<info>Installing plugin '{$name}'...</info>");

        if (PluginManager::install($name)) {
            $output->writeln("<info>Plugin '{$name}' installed successfully.</info>");
            return 0;
        }

        $output->writeln("<error>Failed to install plugin '{$name}'.</error>");
        return 1;
    }
}
```

- [ ] **Step 3: Create UninstallCommand, EnableCommand, DisableCommand**

Same pattern as InstallCommand — each calls the corresponding PluginManager method with appropriate checks and output.

- [ ] **Step 4: Register commands in console.php**

Add to `server/config/console.php` commands array:
```php
'commands' => [
    'plugin:list'      => \core\plugin\command\ListCommand::class,
    'plugin:install'   => \core\plugin\command\InstallCommand::class,
    'plugin:uninstall' => \core\plugin\command\UninstallCommand::class,
    'plugin:enable'    => \core\plugin\command\EnableCommand::class,
    'plugin:disable'   => \core\plugin\command\DisableCommand::class,
],
```

- [ ] **Step 5: Verify all commands syntax**

Run: `cd server && php -l core/plugin/command/ListCommand.php && php -l core/plugin/command/InstallCommand.php && php -l core/plugin/command/UninstallCommand.php && php -l core/plugin/command/EnableCommand.php && php -l core/plugin/command/DisableCommand.php`

- [ ] **Step 6: Verify commands register**

Run: `cd server && php think list` — should show `plugin:list`, `plugin:install`, etc.

- [ ] **Step 7: Commit**

```bash
git add server/core/plugin/command/ server/config/console.php
git commit -m "feat: add plugin CLI commands (list, install, uninstall, enable, disable)"
```

---

## Chunk 5: Admin Frontend Components

### Task 13: SearchForm Component

Collapsible search form with responsive grid layout, used in list pages.

**Files:**
- Create: `admin/src/components/SearchForm/index.vue`

- [ ] **Step 1: Create SearchForm component**

```vue
<template>
  <el-form :model="modelValue" inline class="search-form" @submit.prevent="$emit('search')">
    <slot />
    <el-form-item class="search-actions">
      <el-button type="primary" @click="$emit('search')">
        <el-icon><Search /></el-icon>搜索
      </el-button>
      <el-button @click="$emit('reset')">
        <el-icon><Refresh /></el-icon>重置
      </el-button>
      <el-button
        v-if="collapsible && slotCount > showCount"
        link
        type="primary"
        @click="collapsed = !collapsed"
      >
        {{ collapsed ? '展开' : '收起' }}
        <el-icon><ArrowDown v-if="collapsed" /><ArrowUp v-else /></el-icon>
      </el-button>
    </el-form-item>
  </el-form>
</template>

<script setup lang="ts">
import { ref, useSlots, computed, provide } from 'vue'
import { Search, Refresh, ArrowDown, ArrowUp } from '@element-plus/icons-vue'

const props = withDefaults(defineProps<{
  modelValue: Record<string, any>
  collapsible?: boolean
  showCount?: number
}>(), {
  collapsible: true,
  showCount: 3,
})

defineEmits<{
  search: []
  reset: []
}>()

const collapsed = ref(true)
const slots = useSlots()
const slotCount = computed(() => {
  const defaultSlot = slots.default?.()
  return defaultSlot?.length ?? 0
})

provide('searchFormCollapsed', collapsed)
provide('searchFormShowCount', props.showCount)
</script>

<style lang="scss" scoped>
.search-form {
  display: flex;
  flex-wrap: wrap;
  gap: 0;
}
.search-actions {
  margin-left: auto;
}
</style>
```

- [ ] **Step 2: TypeScript check and commit**

```bash
cd admin && npx vue-tsc --noEmit
git add admin/src/components/SearchForm/
git commit -m "feat: add SearchForm component with collapsible support"
```

---

### Task 14: TableColumnSetting Component

Dropdown that lets users show/hide and reorder table columns.

**Files:**
- Create: `admin/src/components/TableColumnSetting/index.vue`

- [ ] **Step 1: Create TableColumnSetting component**

```vue
<template>
  <el-popover placement="bottom-end" :width="250" trigger="click">
    <template #reference>
      <el-button :icon="Setting" circle />
    </template>
    <div class="column-setting">
      <div class="column-setting-header">
        <el-checkbox v-model="checkAll" :indeterminate="isIndeterminate" @change="handleCheckAll">
          列展示
        </el-checkbox>
        <el-button link type="primary" @click="handleReset">重置</el-button>
      </div>
      <el-checkbox-group v-model="checkedColumns" @change="handleCheckedChange">
        <div v-for="col in allColumns" :key="col.prop" class="column-item">
          <el-checkbox :value="col.prop">{{ col.label }}</el-checkbox>
        </div>
      </el-checkbox-group>
    </div>
  </el-popover>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Setting } from '@element-plus/icons-vue'

export interface ColumnConfig {
  prop: string
  label: string
  visible?: boolean
}

const props = defineProps<{
  columns: ColumnConfig[]
}>()

const emit = defineEmits<{
  change: [visibleProps: string[]]
}>()

const allColumns = computed(() => props.columns)
const defaultChecked = computed(() => props.columns.filter(c => c.visible !== false).map(c => c.prop))

const checkedColumns = ref<string[]>([...defaultChecked.value])
const checkAll = ref(true)
const isIndeterminate = ref(false)

function handleCheckAll(val: boolean) {
  checkedColumns.value = val ? allColumns.value.map(c => c.prop) : []
  isIndeterminate.value = false
  emit('change', checkedColumns.value)
}

function handleCheckedChange(val: string[]) {
  const total = allColumns.value.length
  checkAll.value = val.length === total
  isIndeterminate.value = val.length > 0 && val.length < total
  emit('change', val)
}

function handleReset() {
  checkedColumns.value = [...defaultChecked.value]
  checkAll.value = true
  isIndeterminate.value = false
  emit('change', checkedColumns.value)
}
</script>

<style lang="scss" scoped>
.column-setting-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--el-border-color-lighter);
}
.column-item {
  padding: 4px 0;
}
</style>
```

- [ ] **Step 2: Commit**

```bash
git add admin/src/components/TableColumnSetting/
git commit -m "feat: add TableColumnSetting component for dynamic column visibility"
```

---

### Task 15: ImportData Component

Excel/CSV import with field mapping, validation, and error display.

**Files:**
- Create: `admin/src/components/ImportData/index.vue`

- [ ] **Step 1: Create ImportData component**

```vue
<template>
  <div>
    <el-button @click="visible = true">
      <el-icon><Upload /></el-icon>导入
    </el-button>
    <el-dialog v-model="visible" title="数据导入" width="560px" :close-on-click-modal="false">
      <el-upload
        ref="uploadRef"
        drag
        :auto-upload="false"
        :limit="1"
        accept=".xlsx,.xls,.csv"
        :on-change="handleFileChange"
        :on-exceed="handleExceed"
      >
        <el-icon class="el-icon--upload"><UploadFilled /></el-icon>
        <div class="el-upload__text">拖拽文件到此处，或 <em>点击上传</em></div>
        <template #tip>
          <div class="el-upload__tip">
            支持 .xlsx / .xls / .csv 格式
            <el-button v-if="templateUrl" link type="primary" @click="downloadTemplate">
              下载导入模板
            </el-button>
          </div>
        </template>
      </el-upload>

      <div v-if="importResult" class="import-result">
        <el-alert
          :title="`导入完成：成功 ${importResult.success} 条，失败 ${importResult.fail} 条`"
          :type="importResult.fail > 0 ? 'warning' : 'success'"
          show-icon
          :closable="false"
        />
        <div v-if="importResult.errors?.length" class="error-list">
          <div v-for="(err, i) in importResult.errors" :key="i" class="error-item">
            第{{ err.row }}行：{{ err.message }}
          </div>
        </div>
      </div>

      <template #footer>
        <el-button @click="visible = false">取消</el-button>
        <el-button type="primary" :loading="loading" :disabled="!selectedFile" @click="handleImport">
          开始导入
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { Upload, UploadFilled } from '@element-plus/icons-vue'
import type { UploadFile, UploadInstance, UploadRawFile } from 'element-plus'
import { ElMessage, genFileId } from 'element-plus'

const props = defineProps<{
  importFun: (file: File) => Promise<{ success: number; fail: number; errors?: { row: number; message: string }[] }>
  templateUrl?: string
}>()

const emit = defineEmits<{ success: [] }>()

const visible = ref(false)
const loading = ref(false)
const selectedFile = ref<File | null>(null)
const importResult = ref<{ success: number; fail: number; errors?: { row: number; message: string }[] } | null>(null)
const uploadRef = ref<UploadInstance>()

function handleFileChange(file: UploadFile) {
  selectedFile.value = file.raw || null
  importResult.value = null
}

function handleExceed(files: File[]) {
  uploadRef.value?.clearFiles()
  const file = files[0] as UploadRawFile
  file.uid = genFileId()
  uploadRef.value?.handleStart(file)
}

async function handleImport() {
  if (!selectedFile.value) return
  loading.value = true
  try {
    importResult.value = await props.importFun(selectedFile.value)
    if (importResult.value.success > 0) {
      emit('success')
    }
  } catch (e: any) {
    ElMessage.error(e.message || '导入失败')
  } finally {
    loading.value = false
  }
}

function downloadTemplate() {
  if (props.templateUrl) {
    window.open(props.templateUrl)
  }
}
</script>

<style lang="scss" scoped>
.import-result {
  margin-top: 16px;
}
.error-list {
  max-height: 200px;
  overflow-y: auto;
  margin-top: 8px;
  padding: 8px;
  background: #fef0f0;
  border-radius: 4px;
  font-size: 13px;
}
.error-item {
  color: #f56c6c;
  line-height: 1.8;
}
</style>
```

- [ ] **Step 2: Commit**

```bash
git add admin/src/components/ImportData/
git commit -m "feat: add ImportData component for Excel/CSV import with error display"
```

---

### Task 16: Region Component

Province-city-district cascader component.

**Files:**
- Create: `admin/src/components/Region/index.vue`

- [ ] **Step 1: Create Region component**

Uses Element Plus `el-cascader` with lazy loading from API (region data loaded from backend or static JSON).

```vue
<template>
  <el-cascader
    v-model="modelValue"
    :options="regionData"
    :props="cascaderProps"
    :placeholder="placeholder"
    clearable
    filterable
    @change="handleChange"
  />
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { myRequest } from '@/utils/request'

interface RegionNode {
  value: string | number
  label: string
  children?: RegionNode[]
}

const props = withDefaults(defineProps<{
  modelValue?: (string | number)[]
  placeholder?: string
  level?: 2 | 3  // 2=省市, 3=省市区
}>(), {
  placeholder: '请选择地区',
  level: 3,
})

const emit = defineEmits<{
  'update:modelValue': [value: (string | number)[]]
  change: [value: (string | number)[], labels: string[]]
}>()

const regionData = ref<RegionNode[]>([])

const cascaderProps = {
  value: 'value',
  label: 'label',
  children: 'children',
  checkStrictly: props.level === 2,
}

onMounted(async () => {
  try {
    const res = await myRequest.get('/adminapi/common/regions')
    regionData.value = res as unknown as RegionNode[]
  } catch {
    // fallback: empty
  }
})

function handleChange(val: (string | number)[]) {
  emit('update:modelValue', val)
}
</script>
```

- [ ] **Step 2: Commit**

```bash
git add admin/src/components/Region/
git commit -m "feat: add Region cascader component for province-city-district selection"
```

---

## Chunk 6: Testing Framework + VitePress Documentation

### Task 17: PHPUnit Test Framework + Auth Tests

**Files:**
- Modify: `server/phpunit.xml` (add Feature test suite)
- Create: `server/tests/TestCase.php`
- Create: `server/tests/Feature/Auth/LoginTest.php`
- Create: `server/tests/Feature/Auth/RegisterTest.php`

- [ ] **Step 1: Create base TestCase**

```php
<?php
declare(strict_types=1);

namespace tests;

use think\App;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected App $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = new App(dirname(__DIR__));
        $this->app->initialize();
    }

    protected function getService(string $class)
    {
        return $this->app->make($class);
    }

    protected function assertApiSuccess(array $response): void
    {
        $this->assertEquals(200, $response['code'], 'API response should be success: ' . ($response['message'] ?? ''));
    }

    protected function assertApiError(array $response, int $expectedCode = 0): void
    {
        $this->assertNotEquals(200, $response['code']);
        if ($expectedCode > 0) {
            $this->assertEquals($expectedCode, $response['code']);
        }
    }
}
```

- [ ] **Step 2: Update phpunit.xml to add Feature suite**

Add Feature test suite alongside existing Unit suite:
```xml
<testsuites>
    <testsuite name="Unit">
        <directory>tests/Unit</directory>
    </testsuite>
    <testsuite name="Feature">
        <directory>tests/Feature</directory>
    </testsuite>
</testsuites>
```

- [ ] **Step 3: Create LoginTest**

```php
<?php
declare(strict_types=1);

namespace tests\Feature\Auth;

use tests\TestCase;
use app\service\user\UserService;
use core\auth\TokenManager;

class LoginTest extends TestCase
{
    private UserService $userService;
    private TokenManager $tokenManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userService = $this->getService(UserService::class);
        $this->tokenManager = $this->getService(TokenManager::class);
    }

    public function testLoginWithValidCredentials(): void
    {
        // This test requires a seeded user in the database
        // Seed: mobile=13800138000, password=123456
        $result = $this->userService->loginByPassword('13800138000', '123456');

        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertNotEmpty($result['token']);
    }

    public function testLoginWithInvalidPassword(): void
    {
        $this->expectException(\core\exception\BusinessException::class);
        $this->userService->loginByPassword('13800138000', 'wrong_password');
    }

    public function testTokenVerification(): void
    {
        $payload = ['type' => 'user', 'user_id' => 1];
        $token = $this->tokenManager->generate($payload);

        $this->assertNotEmpty($token);

        $decoded = $this->tokenManager->verify($token);
        $this->assertEquals(1, $decoded['user_id']);
        $this->assertEquals('user', $decoded['type']);
    }

    public function testTokenRefresh(): void
    {
        $payload = ['type' => 'user', 'user_id' => 1];
        $token = $this->tokenManager->generate($payload);

        $newToken = $this->tokenManager->refresh($token);

        $this->assertNotEmpty($newToken);
        $this->assertNotEquals($token, $newToken);

        // Old token should be blacklisted
        $this->expectException(\Exception::class);
        $this->tokenManager->verify($token);
    }
}
```

- [ ] **Step 4: Create RegisterTest**

```php
<?php
declare(strict_types=1);

namespace tests\Feature\Auth;

use tests\TestCase;
use app\service\user\UserService;

class RegisterTest extends TestCase
{
    private UserService $userService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userService = $this->getService(UserService::class);
    }

    public function testRegisterWithValidData(): void
    {
        $mobile = '138' . str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);

        $result = $this->userService->register($mobile, 'Test@123456', '');

        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertEquals($mobile, $result['user']['mobile']);
    }

    public function testRegisterDuplicateMobile(): void
    {
        $mobile = '13900000001';

        // First registration
        try {
            $this->userService->register($mobile, 'Test@123456', '');
        } catch (\Exception $e) {
            // May already exist
        }

        // Second registration should fail
        $this->expectException(\core\exception\BusinessException::class);
        $this->userService->register($mobile, 'Test@123456', '');
    }
}
```

- [ ] **Step 5: Run tests**

Run: `cd server && vendor/bin/phpunit --testsuite Feature -v`

- [ ] **Step 6: Commit**

```bash
git add server/phpunit.xml server/tests/
git commit -m "feat: add PHPUnit test framework with auth feature tests"
```

---

### Task 18: VitePress Documentation Skeleton

**Files:**
- Create: `docs/package.json`
- Create: `docs/.vitepress/config.ts`
- Create: `docs/index.md`
- Create: `docs/guide/introduction.md`
- Create: `docs/guide/quick-start.md`
- Create: `docs/backend/architecture.md`

- [ ] **Step 1: Initialize VitePress**

Create `docs/package.json`:
```json
{
  "name": "dev007-docs",
  "private": true,
  "scripts": {
    "dev": "vitepress dev",
    "build": "vitepress build",
    "preview": "vitepress preview"
  },
  "devDependencies": {
    "vitepress": "^1.5.0"
  }
}
```

- [ ] **Step 2: Create VitePress config**

```typescript
import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Dev007 Framework',
  description: '开源通用软件系统管理后台框架',
  lang: 'zh-CN',
  themeConfig: {
    nav: [
      { text: '指南', link: '/guide/introduction' },
      { text: '后端', link: '/backend/architecture' },
      { text: '前端', link: '/frontend/architecture' },
      { text: '移动端', link: '/mobile/getting-started' },
    ],
    sidebar: {
      '/guide/': [
        {
          text: '入门',
          items: [
            { text: '项目介绍', link: '/guide/introduction' },
            { text: '快速开始', link: '/guide/quick-start' },
          ],
        },
      ],
      '/backend/': [
        {
          text: '后端开发',
          items: [
            { text: '分层架构', link: '/backend/architecture' },
          ],
        },
      ],
    },
    socialLinks: [
      { icon: 'github', link: 'https://github.com/user/dev007-framework' },
    ],
  },
})
```

- [ ] **Step 3: Create index.md (landing page)**

```markdown
---
layout: home
hero:
  name: Dev007 Framework
  text: 开源通用管理后台框架
  tagline: ThinkPHP 8 + Vue 3 + UniApp，快速搭建管理系统
  actions:
    - theme: brand
      text: 快速开始
      link: /guide/quick-start
    - theme: alt
      text: GitHub
      link: https://github.com/user/dev007-framework
features:
  - title: 管理后台
    details: Vue 3 + Element Plus + TypeScript，动态路由、RBAC权限、代码生成器
  - title: 移动端一体化
    details: UniApp + Wot UI，登录、支付、消息等模块开箱即用
  - title: 插件生态
    details: 可插拔功能模块，按需组合，快速扩展业务
---
```

- [ ] **Step 4: Create guide/introduction.md**

Write project introduction covering: positioning, tech stack, features, architecture overview.

- [ ] **Step 5: Create guide/quick-start.md**

Cover: requirements, clone, setup.sh, access URLs, default credentials.

- [ ] **Step 6: Create backend/architecture.md**

Cover: layered architecture (Controller → Service → Repository → Model), auto-DI, event system, middleware, exception handling.

- [ ] **Step 7: Install deps and verify build**

Run: `cd docs && npm install && npm run build`

- [ ] **Step 8: Commit**

```bash
git add docs/package.json docs/.vitepress/ docs/index.md docs/guide/ docs/backend/
git commit -m "feat: add VitePress documentation skeleton with quick-start and architecture guides"
```

---

## Phase 2 Integration Verification

### Task 19: Integration Verification

- [ ] **Step 1: Backend verification**

```bash
cd server
php -l app/api/controller/v1/feedback/FeedbackController.php
php -l app/api/controller/v1/message/MessageController.php
php -l core/plugin/BasePlugin.php
php -l core/exception/ErrorCode.php
php think migrate:status
php think list | grep plugin
```

- [ ] **Step 2: UniApp TypeScript verification**

```bash
cd uniapp && pnpm exec vue-tsc --noEmit
```

- [ ] **Step 3: Admin TypeScript verification**

```bash
cd admin && npx vue-tsc --noEmit
```

- [ ] **Step 4: Run tests**

```bash
cd server && vendor/bin/phpunit -v
```

- [ ] **Step 5: VitePress build verification**

```bash
cd docs && npm run build
```

- [ ] **Step 6: Final commit for any fixes**

```bash
git add -A
git commit -m "fix: Phase 2 integration fixes"
```
