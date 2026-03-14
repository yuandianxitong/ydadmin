# Phase 3: Ecosystem Completion Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete the Dev007 Framework ecosystem with 5 built-in business modules, code generator plugin mode, comprehensive documentation, CI/CD pipeline, and expanded test coverage.

**Architecture:** All new modules follow the existing Controller → Service → Repository → Model layered pattern with auto-DI. Admin frontend pages use dynamic routing from backend menus. C-end APIs share Service/Repository layers but have independent Controllers under `app/api/`.

**Tech Stack:** ThinkPHP 8, Vue 3 + TypeScript + Element Plus, UniApp + Wot UI, VitePress, GitHub Actions, PHPUnit

**Git Repos:** GitHub: https://github.com/yuandianxitong/ydadmin.git | Gitee: git@gitee.com:yuandianxitong/ydadmin.git

---

## Chunk 1: Built-in Modules — Data Layer

### Task 1: Migrations for 5 New Modules

**Files:**
- Create: `server/database/migrations/20260314200100_create_announcements_table.php`
- Create: `server/database/migrations/20260314200200_create_regions_table.php`
- Create: `server/database/migrations/20260314200300_create_agreements_table.php`
- Create: `server/database/migrations/20260314200400_create_app_versions_table.php`
- Create: `server/database/migrations/20260314200500_create_data_imports_table.php`

- [ ] **Step 1: Create announcements migration**

```php
<?php
use think\migration\Migrator;

class CreateAnnouncementsTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('announcements', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '系统公告表',
        ]);

        $table
            ->addColumn('title', 'string', ['limit' => 200, 'comment' => '公告标题'])
            ->addColumn('content', 'text', ['comment' => '公告内容（富文本）'])
            ->addColumn('type', 'tinyinteger', ['default' => 1, 'comment' => '类型：1通知 2更新 3活动'])
            ->addColumn('status', 'tinyinteger', ['default' => 0, 'comment' => '状态：0草稿 1已发布'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序（越大越靠前）'])
            ->addColumn('publish_at', 'datetime', ['null' => true, 'comment' => '发布时间'])
            ->addColumn('admin_id', 'integer', ['signed' => false, 'comment' => '发布管理员ID'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '删除时间'])
            ->addIndex(['status'])
            ->addIndex(['type'])
            ->addIndex(['sort'])
            ->create();
    }

    public function down(): void
    {
        $this->table('announcements')->drop()->save();
    }
}
```

- [ ] **Step 2: Create regions migration**

```php
<?php
use think\migration\Migrator;

class CreateRegionsTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('regions', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '地区数据表',
        ]);

        $table
            ->addColumn('parent_id', 'integer', ['default' => 0, 'signed' => false, 'comment' => '父级ID'])
            ->addColumn('name', 'string', ['limit' => 50, 'comment' => '地区名称'])
            ->addColumn('code', 'string', ['limit' => 20, 'default' => '', 'comment' => '行政区划代码'])
            ->addColumn('level', 'tinyinteger', ['default' => 1, 'comment' => '层级：1省 2市 3区'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序'])
            ->addColumn('status', 'tinyinteger', ['default' => 1, 'comment' => '状态：0禁用 1启用'])
            ->addIndex(['parent_id'])
            ->addIndex(['code'], ['unique' => true])
            ->addIndex(['level'])
            ->create();
    }

    public function down(): void
    {
        $this->table('regions')->drop()->save();
    }
}
```

- [ ] **Step 3: Create agreements migration**

```php
<?php
use think\migration\Migrator;

class CreateAgreementsTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('agreements', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '协议/内容页管理表',
        ]);

        $table
            ->addColumn('title', 'string', ['limit' => 200, 'comment' => '协议标题'])
            ->addColumn('code', 'string', ['limit' => 50, 'comment' => '唯一标识（如 user_agreement, privacy_policy）'])
            ->addColumn('content', 'text', ['comment' => '协议内容（富文本）'])
            ->addColumn('status', 'tinyinteger', ['default' => 1, 'comment' => '状态：0禁用 1启用'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addIndex(['code'], ['unique' => true])
            ->addIndex(['status'])
            ->create();
    }

    public function down(): void
    {
        $this->table('agreements')->drop()->save();
    }
}
```

- [ ] **Step 4: Create app_versions migration**

```php
<?php
use think\migration\Migrator;

class CreateAppVersionsTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('app_versions', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '应用版本管理表',
        ]);

        $table
            ->addColumn('platform', 'string', ['limit' => 20, 'comment' => '平台：android/ios/harmony'])
            ->addColumn('version', 'string', ['limit' => 20, 'comment' => '版本号（如 1.0.0）'])
            ->addColumn('version_code', 'integer', ['signed' => false, 'comment' => '版本号数值（用于比较）'])
            ->addColumn('download_url', 'string', ['limit' => 500, 'default' => '', 'comment' => '下载地址'])
            ->addColumn('description', 'text', ['null' => true, 'comment' => '更新说明'])
            ->addColumn('force_update', 'tinyinteger', ['default' => 0, 'comment' => '强制更新：0否 1是'])
            ->addColumn('status', 'tinyinteger', ['default' => 1, 'comment' => '状态：0禁用 1启用'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addIndex(['platform', 'version_code'])
            ->addIndex(['status'])
            ->create();
    }

    public function down(): void
    {
        $this->table('app_versions')->drop()->save();
    }
}
```

- [ ] **Step 5: Create data_imports migration**

```php
<?php
use think\migration\Migrator;

class CreateDataImportsTable extends Migrator
{
    public function up(): void
    {
        $table = $this->table('data_imports', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
            'comment' => '数据导入记录表',
        ]);

        $table
            ->addColumn('module', 'string', ['limit' => 50, 'comment' => '模块标识'])
            ->addColumn('filename', 'string', ['limit' => 200, 'comment' => '导入文件名'])
            ->addColumn('total_count', 'integer', ['default' => 0, 'comment' => '总行数'])
            ->addColumn('success_count', 'integer', ['default' => 0, 'comment' => '成功行数'])
            ->addColumn('fail_count', 'integer', ['default' => 0, 'comment' => '失败行数'])
            ->addColumn('status', 'tinyinteger', ['default' => 0, 'comment' => '状态：0处理中 1完成 2失败'])
            ->addColumn('errors', 'text', ['null' => true, 'comment' => '错误详情JSON'])
            ->addColumn('admin_id', 'integer', ['signed' => false, 'comment' => '操作管理员ID'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addIndex(['module'])
            ->addIndex(['admin_id'])
            ->create();
    }

    public function down(): void
    {
        $this->table('data_imports')->drop()->save();
    }
}
```

- [ ] **Step 6: Verify PHP syntax**

Run: `cd server && for f in database/migrations/20260314200*.php; do php -l "$f"; done`

- [ ] **Step 7: Commit**

```bash
git add server/database/migrations/20260314200*.php
git commit -m "feat: add migrations for announcement, region, agreement, version, data-import modules"
```

---

### Task 2: Models for 5 New Modules

**Files:**
- Create: `server/app/model/announcement/Announcement.php`
- Create: `server/app/model/region/Region.php`
- Create: `server/app/model/agreement/Agreement.php`
- Create: `server/app/model/version/AppVersion.php`
- Create: `server/app/model/dataimport/DataImport.php`

- [ ] **Step 1: Create all 5 models**

Follow the pattern of `app/model/feedback/Feedback.php`: extend `core\base\Model`, set `$table`, `$fillable`, `$type`, status constants, and accessors.

**Announcement.php:**
```php
<?php
declare(strict_types=1);

namespace app\model\announcement;

use core\base\Model;

class Announcement extends Model
{
    protected $table = 'announcements';

    protected $fillable = [
        'title', 'content', 'type', 'status', 'sort', 'publish_at', 'admin_id',
    ];

    const TYPE_NOTICE   = 1;
    const TYPE_UPDATE   = 2;
    const TYPE_ACTIVITY = 3;

    const STATUS_DRAFT     = 0;
    const STATUS_PUBLISHED = 1;

    protected $type = [
        'type'     => 'integer',
        'status'   => 'integer',
        'sort'     => 'integer',
        'admin_id' => 'integer',
    ];

    public function getTypeTextAttr($value, $data): string
    {
        $map = [self::TYPE_NOTICE => '通知', self::TYPE_UPDATE => '更新', self::TYPE_ACTIVITY => '活动'];
        return $map[(int) ($data['type'] ?? 0)] ?? '未知';
    }

    public function getStatusTextAttr($value, $data): string
    {
        return $this->getStatusText((int) ($data['status'] ?? 0), [
            self::STATUS_DRAFT => '草稿', self::STATUS_PUBLISHED => '已发布',
        ]);
    }
}
```

**Region.php:**
```php
<?php
declare(strict_types=1);

namespace app\model\region;

use core\base\Model;

class Region extends Model
{
    protected $table = 'regions';
    protected $autoWriteTimestamp = false;

    protected $fillable = ['parent_id', 'name', 'code', 'level', 'sort', 'status'];

    const LEVEL_PROVINCE = 1;
    const LEVEL_CITY     = 2;
    const LEVEL_DISTRICT = 3;

    protected $type = [
        'parent_id' => 'integer',
        'level'     => 'integer',
        'sort'      => 'integer',
        'status'    => 'integer',
    ];

    public function children()
    {
        return $this->hasMany(Region::class, 'parent_id', 'id');
    }
}
```

**Agreement.php:**
```php
<?php
declare(strict_types=1);

namespace app\model\agreement;

use core\base\Model;

class Agreement extends Model
{
    protected $table = 'agreements';

    protected $fillable = ['title', 'code', 'content', 'status'];

    protected $type = [
        'status' => 'integer',
    ];
}
```

**AppVersion.php:**
```php
<?php
declare(strict_types=1);

namespace app\model\version;

use core\base\Model;

class AppVersion extends Model
{
    protected $table = 'app_versions';

    protected $fillable = [
        'platform', 'version', 'version_code', 'download_url',
        'description', 'force_update', 'status',
    ];

    protected $type = [
        'version_code' => 'integer',
        'force_update' => 'integer',
        'status'       => 'integer',
    ];
}
```

**DataImport.php:**
```php
<?php
declare(strict_types=1);

namespace app\model\dataimport;

use core\base\Model;

class DataImport extends Model
{
    protected $table = 'data_imports';

    protected $fillable = [
        'module', 'filename', 'total_count', 'success_count',
        'fail_count', 'status', 'errors', 'admin_id',
    ];

    const STATUS_PROCESSING = 0;
    const STATUS_COMPLETED  = 1;
    const STATUS_FAILED     = 2;

    protected $type = [
        'total_count'   => 'integer',
        'success_count' => 'integer',
        'fail_count'    => 'integer',
        'status'        => 'integer',
        'admin_id'      => 'integer',
    ];

    public function getErrorsAttr($value): array
    {
        return $this->getJsonAttr($value);
    }

    public function setErrorsAttr($value): string
    {
        return $this->setJsonAttr($value);
    }
}
```

- [ ] **Step 2: Verify PHP syntax**

Run: `cd server && php -l app/model/announcement/Announcement.php && php -l app/model/region/Region.php && php -l app/model/agreement/Agreement.php && php -l app/model/version/AppVersion.php && php -l app/model/dataimport/DataImport.php`

- [ ] **Step 3: Commit**

```bash
git add server/app/model/announcement/ server/app/model/region/ server/app/model/agreement/ server/app/model/version/ server/app/model/dataimport/
git commit -m "feat: add models for announcement, region, agreement, version, data-import"
```

---

### Task 3: Repositories for 5 New Modules

**Files:**
- Create: `server/app/repository/announcement/AnnouncementRepository.php`
- Create: `server/app/repository/region/RegionRepository.php`
- Create: `server/app/repository/agreement/AgreementRepository.php`
- Create: `server/app/repository/version/AppVersionRepository.php`
- Create: `server/app/repository/dataimport/DataImportRepository.php`

- [ ] **Step 1: Create all 5 repositories**

Follow the pattern of `app/repository/feedback/FeedbackRepository.php`: extend `core\base\Repository`, implement `getModel()`, add `findModel()` for update operations, add search/list methods.

**Key points:**
- Base Repository uses `abstract protected function getModel(): Model`
- Base provides: `find($id)`, `findWhere(array)`, `create(array)`, `update($id, array)`, `delete($id)`, `getList(array $where, $page, $limit, $order)`
- Add `findModel(int $id)` for when Model instance is needed (updates via `$model->save()`)
- Add custom search methods with `$where` array building for admin list pages

**AnnouncementRepository:**
```php
<?php
declare(strict_types=1);

namespace app\repository\announcement;

use app\model\announcement\Announcement;
use core\base\Repository;
use think\Model;

class AnnouncementRepository extends Repository
{
    protected function getModel(): Model
    {
        return new Announcement();
    }

    public function findModel(int $id): ?Announcement
    {
        return Announcement::find($id);
    }

    public function getSearchList(array $params, int $page = 1, int $limit = 20): array
    {
        $where = [];
        if (isset($params['status']) && $params['status'] !== '') {
            $where[] = ['status', '=', (int) $params['status']];
        }
        if (!empty($params['type'])) {
            $where[] = ['type', '=', (int) $params['type']];
        }
        if (!empty($params['keyword'])) {
            $where[] = ['title', 'like', "%{$params['keyword']}%"];
        }
        return $this->getList($where, $page, $limit, 'sort desc, id desc');
    }

    public function getPublishedList(int $page = 1, int $limit = 10): array
    {
        $where = [
            ['status', '=', Announcement::STATUS_PUBLISHED],
            ['publish_at', '<=', date('Y-m-d H:i:s')],
        ];
        return $this->getList($where, $page, $limit, 'sort desc, id desc');
    }
}
```

**RegionRepository:**
```php
<?php
declare(strict_types=1);

namespace app\repository\region;

use app\model\region\Region;
use core\base\Repository;
use think\Model;

class RegionRepository extends Repository
{
    protected function getModel(): Model
    {
        return new Region();
    }

    public function findModel(int $id): ?Region
    {
        return Region::find($id);
    }

    public function getByParentId(int $parentId): array
    {
        $where = [['parent_id', '=', $parentId], ['status', '=', 1]];
        return $this->getAll($where, 'sort asc, id asc');
    }

    public function getTree(): array
    {
        $all = Region::where('status', 1)->order('sort asc, id asc')->select()->toArray();
        return $this->buildTree($all);
    }

    protected function buildTree(array $items, int $parentId = 0): array
    {
        $tree = [];
        foreach ($items as $item) {
            if ((int) $item['parent_id'] === $parentId) {
                $children = $this->buildTree($items, (int) $item['id']);
                $node = ['value' => $item['id'], 'label' => $item['name']];
                if (!empty($children)) {
                    $node['children'] = $children;
                }
                $tree[] = $node;
            }
        }
        return $tree;
    }

    public function getSearchList(array $params, int $page = 1, int $limit = 20): array
    {
        $where = [];
        if (isset($params['parent_id']) && $params['parent_id'] !== '') {
            $where[] = ['parent_id', '=', (int) $params['parent_id']];
        }
        if (isset($params['level']) && $params['level'] !== '') {
            $where[] = ['level', '=', (int) $params['level']];
        }
        if (!empty($params['keyword'])) {
            $where[] = ['name', 'like', "%{$params['keyword']}%"];
        }
        return $this->getList($where, $page, $limit, 'sort asc, id asc');
    }
}
```

**AgreementRepository:**
```php
<?php
declare(strict_types=1);

namespace app\repository\agreement;

use app\model\agreement\Agreement;
use core\base\Repository;
use think\Model;

class AgreementRepository extends Repository
{
    protected function getModel(): Model
    {
        return new Agreement();
    }

    public function findModel(int $id): ?Agreement
    {
        return Agreement::find($id);
    }

    public function findByCode(string $code): ?array
    {
        return $this->findWhere(['code' => $code, 'status' => 1]);
    }

    public function getSearchList(array $params, int $page = 1, int $limit = 20): array
    {
        $where = [];
        if (isset($params['status']) && $params['status'] !== '') {
            $where[] = ['status', '=', (int) $params['status']];
        }
        if (!empty($params['keyword'])) {
            $where[] = ['title', 'like', "%{$params['keyword']}%"];
        }
        return $this->getList($where, $page, $limit, 'id desc');
    }
}
```

**AppVersionRepository:**
```php
<?php
declare(strict_types=1);

namespace app\repository\version;

use app\model\version\AppVersion;
use core\base\Repository;
use think\Model;

class AppVersionRepository extends Repository
{
    protected function getModel(): Model
    {
        return new AppVersion();
    }

    public function findModel(int $id): ?AppVersion
    {
        return AppVersion::find($id);
    }

    public function getLatestVersion(string $platform): ?array
    {
        return $this->findWhere(['platform' => $platform, 'status' => 1]);
    }

    public function getSearchList(array $params, int $page = 1, int $limit = 20): array
    {
        $where = [];
        if (!empty($params['platform'])) {
            $where[] = ['platform', '=', $params['platform']];
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $where[] = ['status', '=', (int) $params['status']];
        }
        return $this->getList($where, $page, $limit, 'id desc');
    }
}
```

**DataImportRepository:**
```php
<?php
declare(strict_types=1);

namespace app\repository\dataimport;

use app\model\dataimport\DataImport;
use core\base\Repository;
use think\Model;

class DataImportRepository extends Repository
{
    protected function getModel(): Model
    {
        return new DataImport();
    }

    public function getSearchList(array $params, int $page = 1, int $limit = 20): array
    {
        $where = [];
        if (!empty($params['module'])) {
            $where[] = ['module', '=', $params['module']];
        }
        return $this->getList($where, $page, $limit, 'id desc');
    }
}
```

- [ ] **Step 2: Verify PHP syntax**

Run: `cd server && php -l app/repository/announcement/AnnouncementRepository.php && php -l app/repository/region/RegionRepository.php && php -l app/repository/agreement/AgreementRepository.php && php -l app/repository/version/AppVersionRepository.php && php -l app/repository/dataimport/DataImportRepository.php`

- [ ] **Step 3: Commit**

```bash
git add server/app/repository/announcement/ server/app/repository/region/ server/app/repository/agreement/ server/app/repository/version/ server/app/repository/dataimport/
git commit -m "feat: add repositories for announcement, region, agreement, version, data-import"
```

---

## Chunk 2: Built-in Modules — Backend Services + Admin API

### Task 4: Announcement Module — Service + Admin CRUD

**Files:**
- Create: `server/app/service/announcement/AnnouncementService.php`
- Create: `server/app/adminapi/controller/v1/announcement/AnnouncementController.php`
- Create: `server/app/adminapi/validate/v1/announcement/AnnouncementValidate.php`
- Create: `server/app/adminapi/route/announcement.php`

- [ ] **Step 1: Create AnnouncementService**

Follow `app/service/feedback/FeedbackService.php` pattern. Methods: `getList()`, `detail()`, `create()`, `update()`, `updateStatus()`, `delete()`. Use auto-DI for `AnnouncementRepository`.

- [ ] **Step 2: Create AnnouncementController**

Follow `app/adminapi/controller/v1/feedback/FeedbackController.php` pattern. Methods: `list()`, `detail()`, `create()`, `update()`, `updateStatus()`, `delete()`. Use `#[Permission('announcement.xxx')]` attributes.

- [ ] **Step 3: Create AnnouncementValidate**

Scenes: `create` (title, content, type required), `update` (id, title, content required).

- [ ] **Step 4: Create admin route**

```php
<?php
use think\facade\Route;

Route::group('announcement', function () {
    Route::get('list', 'v1.announcement.AnnouncementController/list');
    Route::get('detail/:id', 'v1.announcement.AnnouncementController/detail');
    Route::post('', 'v1.announcement.AnnouncementController/create');
    Route::put(':id', 'v1.announcement.AnnouncementController/update');
    Route::put(':id/status', 'v1.announcement.AnnouncementController/updateStatus');
    Route::delete(':id', 'v1.announcement.AnnouncementController/delete');
})->middleware(['admin_auth', 'admin_permission', 'admin_log']);
```

- [ ] **Step 5: Verify PHP syntax and commit**

```bash
git add server/app/service/announcement/ server/app/adminapi/controller/v1/announcement/ server/app/adminapi/validate/v1/announcement/ server/app/adminapi/route/announcement.php
git commit -m "feat: add announcement admin module (service, controller, validate, route)"
```

---

### Task 5: Agreement Module — Service + Admin CRUD

**Files:**
- Create: `server/app/service/agreement/AgreementService.php`
- Create: `server/app/adminapi/controller/v1/agreement/AgreementController.php`
- Create: `server/app/adminapi/validate/v1/agreement/AgreementValidate.php`
- Create: `server/app/adminapi/route/agreement.php`

- [ ] **Step 1: Create AgreementService**

Methods: `getList()`, `detail()`, `findByCode()`, `create()`, `update()`, `delete()`. The `code` field must be unique — validate before creating.

- [ ] **Step 2: Create AgreementController**

Standard CRUD with `#[Permission('agreement.xxx')]`. The `findByCode()` method is for C-end use (shared Service layer).

- [ ] **Step 3: Create AgreementValidate**

Scenes: `create` (title, code, content required, code alphanumeric+underscore), `update` (id, title, content required).

- [ ] **Step 4: Create admin route**

Same pattern as announcement. REST endpoints for CRUD.

- [ ] **Step 5: Verify and commit**

```bash
git add server/app/service/agreement/ server/app/adminapi/controller/v1/agreement/ server/app/adminapi/validate/v1/agreement/ server/app/adminapi/route/agreement.php
git commit -m "feat: add agreement admin module (service, controller, validate, route)"
```

---

### Task 6: Region Data Module — Service + Admin CRUD + Common API

**Files:**
- Create: `server/app/service/region/RegionService.php`
- Create: `server/app/adminapi/controller/v1/region/RegionController.php`
- Create: `server/app/adminapi/validate/v1/region/RegionValidate.php`
- Create: `server/app/adminapi/route/region.php`
- Modify: `server/app/adminapi/route/common.php` — add regions tree endpoint

- [ ] **Step 1: Create RegionService**

Methods: `getList()`, `getTree()`, `getByParentId()`, `detail()`, `create()`, `update()`, `delete()`. The `getTree()` method returns the cascader-compatible format `[{value, label, children}]` used by both admin Region component and C-end.

- [ ] **Step 2: Create RegionController (admin)**

CRUD + tree endpoint. The tree endpoint is also exposed under `/adminapi/common/regions` (used by the existing admin Region component at `admin/src/components/Region/index.vue`).

- [ ] **Step 3: Create RegionValidate**

Scenes: `create` (name, level required; code unique), `update` (id, name required).

- [ ] **Step 4: Create admin route + common endpoint**

```php
// server/app/adminapi/route/region.php
Route::group('region', function () {
    Route::get('list', 'v1.region.RegionController/list');
    Route::get('tree', 'v1.region.RegionController/tree');
    Route::get('detail/:id', 'v1.region.RegionController/detail');
    Route::post('', 'v1.region.RegionController/create');
    Route::put(':id', 'v1.region.RegionController/update');
    Route::delete(':id', 'v1.region.RegionController/delete');
})->middleware(['admin_auth', 'admin_permission', 'admin_log']);
```

Also add to `server/app/adminapi/route/common.php` a public regions endpoint (read the file first, then append):
```php
Route::get('regions', 'v1.region.RegionController/tree');
```
This endpoint is already called by `admin/src/components/Region/index.vue` via `/adminapi/common/regions`.

- [ ] **Step 5: Verify and commit**

```bash
git add server/app/service/region/ server/app/adminapi/controller/v1/region/ server/app/adminapi/validate/v1/region/ server/app/adminapi/route/region.php server/app/adminapi/route/common.php
git commit -m "feat: add region data admin module with tree API for cascader"
```

---

### Task 7: Version Management Module — Service + Admin CRUD

**Files:**
- Create: `server/app/service/version/AppVersionService.php`
- Create: `server/app/adminapi/controller/v1/version/AppVersionController.php`
- Create: `server/app/adminapi/validate/v1/version/AppVersionValidate.php`
- Create: `server/app/adminapi/route/version.php`

- [ ] **Step 1: Create AppVersionService**

Methods: `getList()`, `detail()`, `create()`, `update()`, `delete()`, `checkUpdate(string $platform, int $versionCode)`. The `checkUpdate()` method finds the latest enabled version for a platform and compares `version_code`.

- [ ] **Step 2: Create AppVersionController**

Standard CRUD with `#[Permission('version.xxx')]`.

- [ ] **Step 3: Create AppVersionValidate**

Scenes: `create` (platform, version, version_code required), `update` (id, platform, version, version_code required).

- [ ] **Step 4: Create admin route**

Standard CRUD REST endpoints.

- [ ] **Step 5: Verify and commit**

```bash
git add server/app/service/version/ server/app/adminapi/controller/v1/version/ server/app/adminapi/validate/v1/version/ server/app/adminapi/route/version.php
git commit -m "feat: add version management admin module"
```

---

### Task 8: Data Import Service

**Files:**
- Create: `server/app/service/dataimport/DataImportService.php`
- Create: `server/app/adminapi/controller/v1/dataimport/DataImportController.php`
- Create: `server/app/adminapi/route/dataimport.php`

- [ ] **Step 1: Create DataImportService**

Generic import service that:
1. Receives uploaded file (CSV/Excel)
2. Parses rows using PHP built-in functions (`fgetcsv` for CSV; for Excel, use `PhpSpreadsheet` if available, otherwise CSV-only)
3. Validates each row against provided rules
4. Calls a provided callback for each valid row
5. Records import results to `data_imports` table

```php
public function import(string $module, string $filePath, array $fieldMap, callable $rowHandler, int $adminId): array
```

- [ ] **Step 2: Create DataImportController**

Endpoints: `upload` (receive file + module + field mapping), `history` (list import records for a module).

- [ ] **Step 3: Create admin route**

```php
Route::group('data-import', function () {
    Route::post('upload', 'v1.dataimport.DataImportController/upload');
    Route::get('history', 'v1.dataimport.DataImportController/history');
})->middleware(['admin_auth', 'admin_permission', 'admin_log']);
```

- [ ] **Step 4: Verify and commit**

```bash
git add server/app/service/dataimport/ server/app/adminapi/controller/v1/dataimport/ server/app/adminapi/route/dataimport.php
git commit -m "feat: add data import service with CSV parsing and import history"
```

---

## Chunk 3: Admin Frontend Pages

### Task 9: Admin Announcement Pages

**Files:**
- Create: `admin/src/api/announcement.ts`
- Create: `admin/src/views/announcement/index.vue`
- Create: `admin/src/views/announcement/components/AnnouncementForm.vue`

- [ ] **Step 1: Create announcement API**

Follow the pattern of `admin/src/api/admin.ts`: export an `announcementApi` object with `getList`, `getDetail`, `create`, `update`, `updateStatus`, `delete` methods. Define `AnnouncementInfo` and `AnnouncementQuery` TypeScript interfaces.

- [ ] **Step 2: Create announcement list page**

Follow the pattern of existing admin list pages (e.g., `admin/src/views/system/admin/index.vue`):
- SearchForm with keyword, type (select), status (select) filters
- el-table with columns: title, type, status, sort, publish_at, actions
- Status toggle switch
- Add/Edit form dialog
- Delete confirmation

- [ ] **Step 3: Create AnnouncementForm component**

Dialog form with: title (input), type (select: notice/update/activity), content (Editor rich text), sort (number), status (radio), publish_at (datetime picker).

- [ ] **Step 4: Commit**

```bash
git add admin/src/api/announcement.ts admin/src/views/announcement/
git commit -m "feat: add admin announcement management pages"
```

---

### Task 10: Admin Agreement Pages

**Files:**
- Create: `admin/src/api/agreement.ts`
- Create: `admin/src/views/agreement/index.vue`
- Create: `admin/src/views/agreement/components/AgreementForm.vue`

- [ ] **Step 1: Create agreement API and list page**

Same pattern as announcement. Columns: title, code, status, updated_at, actions.

- [ ] **Step 2: Create AgreementForm component**

Fields: title (input), code (input, disabled on edit), content (Editor rich text), status (radio).

- [ ] **Step 3: Commit**

```bash
git add admin/src/api/agreement.ts admin/src/views/agreement/
git commit -m "feat: add admin agreement management pages"
```

---

### Task 11: Admin Region Data Pages

**Files:**
- Create: `admin/src/api/region.ts`
- Create: `admin/src/views/region/index.vue`
- Create: `admin/src/views/region/components/RegionForm.vue`

- [ ] **Step 1: Create region API**

Methods: `getList`, `getTree`, `getDetail`, `create`, `update`, `delete`.

- [ ] **Step 2: Create region management page**

Use el-table with tree structure (`row-key="id"`, `:tree-props="{ children: 'children' }"`). Load root level first, lazy-load children on expand. Columns: name, code, level, sort, status, actions.

- [ ] **Step 3: Create RegionForm component**

Fields: parent (cascader from tree), name, code, level (auto-calculated from parent), sort, status.

- [ ] **Step 4: Commit**

```bash
git add admin/src/api/region.ts admin/src/views/region/
git commit -m "feat: add admin region data management pages"
```

---

### Task 12: Admin Version Management Pages

**Files:**
- Create: `admin/src/api/app-version.ts`
- Create: `admin/src/views/version/index.vue`
- Create: `admin/src/views/version/components/VersionForm.vue`

- [ ] **Step 1: Create version API and pages**

Same CRUD pattern. Columns: platform, version, version_code, download_url, force_update, status, actions.

- [ ] **Step 2: Create VersionForm component**

Fields: platform (select: android/ios/harmony), version (input), version_code (number), download_url (input), description (textarea), force_update (switch), status (radio).

- [ ] **Step 3: Commit**

```bash
git add admin/src/api/app-version.ts admin/src/views/version/
git commit -m "feat: add admin version management pages"
```

---

## Chunk 4: C-end API + UniApp Pages

### Task 13: C-end APIs (Announcement, Agreement, Version, Region)

**Files:**
- Create: `server/app/api/controller/v1/announcement/AnnouncementController.php`
- Create: `server/app/api/controller/v1/agreement/AgreementController.php`
- Create: `server/app/api/controller/v1/version/VersionController.php`
- Create: `server/app/api/route/announcement.php`
- Create: `server/app/api/route/agreement.php`
- Create: `server/app/api/route/version.php`
- Modify: `server/app/api/route/common.php` — add regions tree endpoint

- [ ] **Step 1: Create C-end AnnouncementController**

Read-only: `list()` (published only, paginated), `detail(int $id)`. No auth required.

```php
<?php
declare(strict_types=1);

namespace app\api\controller\v1\announcement;

use core\base\Controller;
use app\service\announcement\AnnouncementService;
use think\Response;

class AnnouncementController extends Controller
{
    protected AnnouncementService $announcementService;

    public function list(): Response
    {
        $params = $this->getRequestData(['page_no' => 1, 'page_size' => 10]);
        $result = $this->announcementService->getPublishedList($params);
        return $this->paginate($result);
    }

    public function detail(int $id): Response
    {
        $result = $this->announcementService->detail($id);
        if (!$result || (int) $result['status'] !== 1) {
            return $this->error(lang('business.record_not_found'));
        }
        return $this->success(lang('messages.get_success'), $result);
    }
}
```

- [ ] **Step 2: Create C-end AgreementController**

Read-only: `detail(string $code)` — find by code. No auth required.

- [ ] **Step 3: Create C-end VersionController**

Read-only: `check()` — accepts `platform` and `version_code` query params, returns latest version info + `need_update` boolean. No auth required.

- [ ] **Step 4: Create C-end routes**

```php
// announcement.php — no auth
Route::group('announcement', function () {
    Route::get('list', 'v1.announcement.AnnouncementController/list');
    Route::get('detail/:id', 'v1.announcement.AnnouncementController/detail');
});

// agreement.php — no auth
Route::group('agreement', function () {
    Route::get('detail/:code', 'v1.agreement.AgreementController/detail');
});

// version.php — no auth
Route::group('version', function () {
    Route::get('check', 'v1.version.VersionController/check');
});
```

Also add to `server/app/api/route/common.php`:
```php
Route::get('regions', 'v1.common.CommonController/regions');
```

And add `regions()` method to `app/api/controller/v1/common/CommonController.php` that calls `RegionService::getTree()`.

- [ ] **Step 5: Verify and commit**

```bash
git add server/app/api/controller/v1/announcement/ server/app/api/controller/v1/agreement/ server/app/api/controller/v1/version/ server/app/api/route/announcement.php server/app/api/route/agreement.php server/app/api/route/version.php server/app/api/route/common.php server/app/api/controller/v1/common/
git commit -m "feat: add C-end APIs for announcement, agreement, version check, region data"
```

---

### Task 14: UniApp Announcement + Agreement Pages

**Files:**
- Create: `uniapp/src/api/announcement.ts`
- Create: `uniapp/src/api/agreement.ts`
- Create: `uniapp/src/modules/announcement/pages/announcement-list.vue`
- Create: `uniapp/src/modules/announcement/pages/announcement-detail.vue`
- Create: `uniapp/src/modules/webview/pages/agreement.vue`
- Modify: `uniapp/src/pages.json` — add announcement and agreement pages

- [ ] **Step 1: Create API files**

```typescript
// uniapp/src/api/announcement.ts
import http from '@/utils/request'

export interface AnnouncementInfo {
  id: number
  title: string
  content: string
  type: number
  type_text: string
  publish_at: string
}

export const announcementApi = {
  getList: (params: { page_no: number; page_size: number }) =>
    http.get<{ list: AnnouncementInfo[]; total: number }>('/api/announcement/list', { params }),
  getDetail: (id: number) =>
    http.get<AnnouncementInfo>(`/api/announcement/detail/${id}`),
}
```

```typescript
// uniapp/src/api/agreement.ts
import http from '@/utils/request'

export interface AgreementInfo {
  id: number
  title: string
  code: string
  content: string
}

export const agreementApi = {
  getDetail: (code: string) =>
    http.get<AgreementInfo>(`/api/agreement/detail/${code}`),
}
```

- [ ] **Step 2: Create announcement list page**

Use `d-page` + `d-list-loader` with `usePaging` hook. Each item shows title, type_text badge, publish_at. Tap navigates to detail.

- [ ] **Step 3: Create announcement detail page**

Simple page: title, publish_at, and rich-text content rendered via `rich-text` component.

- [ ] **Step 4: Create agreement page**

Receives `code` param via route query. Loads agreement content via `agreementApi.getDetail(code)`. Renders title + rich-text. Used for user_agreement, privacy_policy links.

- [ ] **Step 5: Update pages.json**

Read current `uniapp/src/pages.json`, then add:
```json
{
  "root": "modules/announcement",
  "pages": [
    { "path": "pages/announcement-list", "style": { "navigationBarTitleText": "系统公告" } },
    { "path": "pages/announcement-detail", "style": { "navigationBarTitleText": "公告详情" } }
  ]
}
```

Add agreement page to the existing webview subpackage (or create a new one if more appropriate).

- [ ] **Step 6: Commit**

```bash
git add uniapp/src/api/announcement.ts uniapp/src/api/agreement.ts uniapp/src/modules/announcement/ uniapp/src/modules/webview/ uniapp/src/pages.json
git commit -m "feat: add UniApp announcement and agreement pages"
```

---

### Task 15: UniApp Version Check Composable

**Files:**
- Create: `uniapp/src/api/version.ts`
- Create: `uniapp/src/hooks/useVersionCheck.ts`

- [ ] **Step 1: Create version API**

```typescript
import http from '@/utils/request'

export interface VersionInfo {
  version: string
  version_code: number
  download_url: string
  description: string
  force_update: number
  need_update: boolean
}

export const versionApi = {
  check: (platform: string, versionCode: number) =>
    http.get<VersionInfo>('/api/version/check', { params: { platform, version_code: versionCode } }),
}
```

- [ ] **Step 2: Create useVersionCheck composable**

```typescript
import { ref } from 'vue'
import { versionApi } from '@/api/version'
import type { VersionInfo } from '@/api/version'

export function useVersionCheck() {
  const updateInfo = ref<VersionInfo | null>(null)

  async function checkUpdate() {
    // #ifdef APP-PLUS
    const systemInfo = uni.getSystemInfoSync()
    const platform = systemInfo.platform === 'ios' ? 'ios' : 'android'

    // Get current app version code from manifest
    const appInfo = plus.runtime.getProperty(plus.runtime.appid!, (info) => info)
    const versionCode = (appInfo as any)?.versionCode || 0

    try {
      const result = await versionApi.check(platform, versionCode)
      if (result.need_update) {
        updateInfo.value = result
        showUpdateDialog(result)
      }
    } catch {
      // Silently fail on version check
    }
    // #endif
  }

  function showUpdateDialog(info: VersionInfo) {
    const actions: UniApp.ShowModalOptions = {
      title: `发现新版本 ${info.version}`,
      content: info.description || '修复已知问题，提升使用体验',
      showCancel: info.force_update !== 1,
      confirmText: '立即更新',
    }

    uni.showModal({
      ...actions,
      success: (res) => {
        if (res.confirm && info.download_url) {
          // #ifdef APP-PLUS
          plus.runtime.openURL(info.download_url)
          // #endif
        }
        if (res.cancel && info.force_update === 1) {
          // Force update: exit app
          // #ifdef APP-PLUS
          plus.runtime.quit()
          // #endif
        }
      },
    })
  }

  return { updateInfo, checkUpdate }
}
```

- [ ] **Step 3: Commit**

```bash
git add uniapp/src/api/version.ts uniapp/src/hooks/useVersionCheck.ts
git commit -m "feat: add UniApp version check composable with update dialog"
```

---

## Chunk 5: Code Generator Enhancement + Documentation

### Task 16: Code Generator `--as-plugin` Mode

**Files:**
- Modify: `server/app/command/MakeCrudCommand.php`
- Modify: `server/app/service/system/CodeGeneratorService.php`

- [ ] **Step 1: Add `--as-plugin` option to MakeCrudCommand**

Read `server/app/command/MakeCrudCommand.php` first. Add:
```php
->addOption('as-plugin', null, Option::VALUE_NONE, '生成为插件到 plugins/ 目录')
```

When `--as-plugin` is set, pass `'as_plugin' => true` in the `$config` array to `CodeGeneratorService::generate()`.

- [ ] **Step 2: Modify CodeGeneratorService to support plugin output paths**

Read `server/app/service/system/CodeGeneratorService.php`. When `$config['as_plugin']` is true, change the output paths:

| File Type | Normal Path | Plugin Path |
|-----------|------------|-------------|
| Model | `app/model/{module}/` | `plugins/{module}/backend/model/` |
| Repository | `app/repository/{module}/` | `plugins/{module}/backend/repository/` |
| Service | `app/service/{module}/` | `plugins/{module}/backend/service/` |
| Controller | `app/adminapi/controller/v1/{module}/` | `plugins/{module}/backend/controller/` |
| Validate | `app/adminapi/validate/v1/{module}/` | `plugins/{module}/backend/validate/` |
| Route | `app/adminapi/route/{module}.php` | `plugins/{module}/backend/config/routes.php` |
| Migration | `database/migrations/` | `plugins/{module}/backend/migration/` |
| Frontend | `admin/src/` paths | `admin/src/plugins/{module}/` paths |

Also generate `plugins/{module}/plugin.json` with basic metadata.

- [ ] **Step 3: Verify and commit**

```bash
git add server/app/command/MakeCrudCommand.php server/app/service/system/CodeGeneratorService.php
git commit -m "feat: add --as-plugin mode to code generator for plugin directory output"
```

---

### Task 17: Documentation — Backend Detailed Docs

**Files:**
- Create: `docs/backend/controller.md`
- Create: `docs/backend/service.md`
- Create: `docs/backend/repository.md`
- Create: `docs/backend/model.md`
- Create: `docs/backend/event-listener.md`
- Create: `docs/backend/middleware.md`
- Create: `docs/backend/permission.md`
- Create: `docs/backend/code-generator.md`
- Create: `docs/backend/api-convention.md`
- Create: `docs/guide/directory-structure.md`
- Create: `docs/guide/configuration.md`
- Create: `docs/guide/deployment.md`
- Modify: `docs/.vitepress/config.ts` — update sidebar

- [ ] **Step 1: Write backend documentation pages**

Each page should cover:
- **controller.md**: Base Controller, auto-DI, response methods, Permission attribute, request validation, file structure
- **service.md**: Base Service, auto-DI, event triggering, cache management, transaction handling, file structure
- **repository.md**: Base Repository, getModel() pattern, CRUD methods, findModel pattern, query building
- **model.md**: Base Model, fillable, type casting, accessors/mutators, JSON helpers, soft delete, timestamps
- **event-listener.md**: Event registration in event.php, listener creation, existing events list, when to use events vs inline logic
- **middleware.md**: Built-in middleware list (admin_auth, admin_permission, admin_log, api_auth, api_rate_limit, api_sms_rate_limit), creating custom middleware
- **permission.md**: RBAC system, Permission attribute, menu-permission relationship, frontend `v-has-perm` directive
- **code-generator.md**: CLI usage, web UI, generated files list, --as-plugin mode, customization
- **api-convention.md**: Response format, error codes (1xxx-5xxx), pagination, versioning, rate limiting

Read the actual source files to ensure documentation accuracy:
- `server/core/base/Controller.php`
- `server/core/base/Service.php`
- `server/core/base/Repository.php`
- `server/core/base/Model.php`
- `server/app/event.php`
- `server/core/exception/ErrorCode.php`

- [ ] **Step 2: Write guide pages**

- **directory-structure.md**: Full project directory tree with explanations
- **configuration.md**: .env file, system_configs, ThinkPHP config files
- **deployment.md**: Docker deployment, traditional server (宝塔/Nginx), production checklist

- [ ] **Step 3: Update VitePress sidebar config**

Read `docs/.vitepress/config.ts` and update the sidebar to include all new pages.

- [ ] **Step 4: Commit**

```bash
git add docs/backend/ docs/guide/ docs/.vitepress/config.ts
git commit -m "docs: add comprehensive backend and guide documentation"
```

---

### Task 18: Documentation — Frontend, Mobile, Plugin Docs

**Files:**
- Create: `docs/frontend/architecture.md`
- Create: `docs/frontend/router.md`
- Create: `docs/frontend/permission.md`
- Create: `docs/frontend/request.md`
- Create: `docs/frontend/components.md`
- Create: `docs/frontend/hooks.md`
- Create: `docs/frontend/theme.md`
- Create: `docs/mobile/getting-started.md`
- Create: `docs/mobile/modules.md`
- Create: `docs/mobile/components.md`
- Create: `docs/mobile/payment.md`
- Create: `docs/plugin/introduction.md`
- Create: `docs/plugin/create-plugin.md`
- Create: `docs/plugin/plugin-api.md`
- Create: `docs/changelog.md`
- Modify: `docs/.vitepress/config.ts` — update sidebar for all new pages

- [ ] **Step 1: Write frontend documentation**

Read the actual source files for accuracy:
- `admin/src/router/index.ts` for router docs
- `admin/src/utils/request.ts` for request docs
- `admin/src/components/` directory for components catalog
- Key hooks and stores

Cover: architecture overview, dynamic routing system, permission (RBAC + `v-has-perm`), request interceptors, built-in components catalog (with props/events for each), hooks documentation, theme/UnoCSS customization.

- [ ] **Step 2: Write mobile documentation**

Read UniApp source files:
- `uniapp/src/utils/request.ts`
- `uniapp/src/hooks/`
- `uniapp/src/components/`
- `uniapp/src/modules/`

Cover: getting started (setup, pages.json, easycom), module system (login, user, payment, message, feedback, announcement), custom components (d-* component catalog), payment integration guide, WeChat login guide.

- [ ] **Step 3: Write plugin documentation**

Read plugin system files:
- `server/core/plugin/BasePlugin.php`
- `server/core/plugin/PluginManager.php`
- `server/core/plugin/PluginLoader.php`
- Plugin CLI commands

Cover: plugin introduction, creating a plugin (directory structure, plugin.json, lifecycle hooks), plugin API (PluginManager methods, BasePlugin methods, CLI commands).

- [ ] **Step 4: Create changelog**

Basic changelog template with Phase 1-3 milestones.

- [ ] **Step 5: Update VitePress config and commit**

```bash
git add docs/frontend/ docs/mobile/ docs/plugin/ docs/changelog.md docs/.vitepress/config.ts
git commit -m "docs: add frontend, mobile, plugin documentation and changelog"
```

---

## Chunk 6: CI/CD Enhancement + Test Coverage

### Task 19: GitHub Actions CI Enhancement

**Files:**
- Modify: `.github/workflows/ci.yml`
- Create: `server/.php-cs-fixer.php`

- [ ] **Step 1: Read existing CI workflow**

Read `.github/workflows/ci.yml` to understand current structure.

- [ ] **Step 2: Enhance CI workflow**

Add the following to the existing workflow:

1. **PHP-CS-Fixer** step in `backend-lint` job:
```yaml
- run: vendor/bin/php-cs-fixer fix --dry-run --diff
```

2. **PHPUnit with coverage** in `backend-test` job:
```yaml
- run: |
    cp .env.example .env
    sed -i 's/DB_HOST=.*/DB_HOST=127.0.0.1/' .env
    sed -i 's/DB_DATABASE=.*/DB_DATABASE=dev007_test/' .env
    sed -i 's/DB_PASSWORD=.*/DB_PASSWORD=root/' .env
- run: php think migrate:run
- run: vendor/bin/phpunit --coverage-text --colors=never
```

3. **UniApp type check** as new job:
```yaml
uniapp-check:
  name: UniApp Type Check
  runs-on: ubuntu-latest
  defaults:
    run:
      working-directory: uniapp
  steps:
    - uses: actions/checkout@v4
    - uses: pnpm/action-setup@v4
      with:
        version: 9
    - uses: actions/setup-node@v4
      with:
        node-version: 20
        cache: pnpm
        cache-dependency-path: uniapp/pnpm-lock.yaml
    - run: pnpm install --frozen-lockfile
    - run: pnpm exec vue-tsc --noEmit
```

4. **Docs build verification** as new job:
```yaml
docs-build:
  name: Documentation Build
  runs-on: ubuntu-latest
  defaults:
    run:
      working-directory: docs
  steps:
    - uses: actions/checkout@v4
    - uses: actions/setup-node@v4
      with:
        node-version: 20
    - run: npm install
    - run: npm run build
```

- [ ] **Step 3: Create PHP-CS-Fixer config**

```php
<?php
$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/app', __DIR__ . '/core'])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'no_unused_imports' => true,
        'single_quote' => true,
        'trailing_comma_in_multiline' => true,
    ])
    ->setFinder($finder);
```

- [ ] **Step 4: Add CS-Fixer to composer.json dev dependencies**

Read `server/composer.json`, add `"friendsofphp/php-cs-fixer": "^3.0"` to `require-dev`, and add scripts:
```json
"scripts": {
    "lint": "php-cs-fixer fix --dry-run --diff",
    "lint:fix": "php-cs-fixer fix",
    "test": "phpunit"
}
```

- [ ] **Step 5: Commit**

```bash
git add .github/workflows/ci.yml server/.php-cs-fixer.php server/composer.json
git commit -m "feat: enhance CI/CD with PHP-CS-Fixer, UniApp check, docs build"
```

---

### Task 20: Test Coverage — CRUD Service Tests

**Files:**
- Create: `server/tests/Feature/Announcement/AnnouncementServiceTest.php`
- Create: `server/tests/Feature/Agreement/AgreementServiceTest.php`
- Create: `server/tests/Unit/Core/ErrorCodeTest.php`

- [ ] **Step 1: Create AnnouncementServiceTest**

```php
<?php
declare(strict_types=1);

namespace tests\Feature\Announcement;

use tests\TestCase;
use app\service\announcement\AnnouncementService;

class AnnouncementServiceTest extends TestCase
{
    private AnnouncementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->getService(AnnouncementService::class);
    }

    public function testCreateAnnouncement(): void
    {
        $data = [
            'title' => 'Test Announcement ' . time(),
            'content' => '<p>Test content</p>',
            'type' => 1,
            'status' => 1,
            'sort' => 0,
            'admin_id' => 1,
        ];
        $result = $this->service->create($data);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertEquals($data['title'], $result['title']);
    }

    public function testGetList(): void
    {
        $result = $this->service->getList(['page_no' => 1, 'page_size' => 10]);
        $this->assertArrayHasKey('list', $result);
        $this->assertArrayHasKey('total', $result);
    }

    public function testUpdateAnnouncement(): void
    {
        $created = $this->service->create([
            'title' => 'Update Test ' . time(),
            'content' => 'content',
            'type' => 1,
            'status' => 0,
            'sort' => 0,
            'admin_id' => 1,
        ]);
        $result = $this->service->update((int) $created['id'], ['title' => 'Updated Title']);
        $this->assertTrue($result);
    }

    public function testDeleteAnnouncement(): void
    {
        $created = $this->service->create([
            'title' => 'Delete Test ' . time(),
            'content' => 'content',
            'type' => 1,
            'status' => 0,
            'sort' => 0,
            'admin_id' => 1,
        ]);
        $result = $this->service->delete((int) $created['id']);
        $this->assertTrue($result);
    }
}
```

- [ ] **Step 2: Create AgreementServiceTest**

Same pattern: test create (with unique code), get by code, update, list.

- [ ] **Step 3: Create ErrorCodeTest**

```php
<?php
declare(strict_types=1);

namespace tests\Unit\Core;

use tests\TestCase;
use core\exception\ErrorCode;

class ErrorCodeTest extends TestCase
{
    public function testAuthCodesAre1xxx(): void
    {
        $this->assertGreaterThanOrEqual(1000, ErrorCode::AUTH_TOKEN_INVALID);
        $this->assertLessThan(2000, ErrorCode::AUTH_TOKEN_INVALID);
    }

    public function testValidateCodesAre2xxx(): void
    {
        $this->assertGreaterThanOrEqual(2000, ErrorCode::VALIDATE_FAILED);
        $this->assertLessThan(3000, ErrorCode::VALIDATE_FAILED);
    }

    public function testBusinessCodesAre3xxx(): void
    {
        $this->assertGreaterThanOrEqual(3000, ErrorCode::BUSINESS_ERROR);
        $this->assertLessThan(4000, ErrorCode::BUSINESS_ERROR);
    }

    public function testPaymentCodesAre4xxx(): void
    {
        $this->assertGreaterThanOrEqual(4000, ErrorCode::PAYMENT_ERROR);
        $this->assertLessThan(5000, ErrorCode::PAYMENT_ERROR);
    }

    public function testSystemCodesAre5xxx(): void
    {
        $this->assertGreaterThanOrEqual(5000, ErrorCode::SYSTEM_ERROR);
        $this->assertLessThan(6000, ErrorCode::SYSTEM_ERROR);
    }
}
```

- [ ] **Step 4: Run tests and commit**

Run: `cd server && vendor/bin/phpunit --testsuite Feature -v && vendor/bin/phpunit --testsuite Unit -v`

```bash
git add server/tests/
git commit -m "test: add CRUD service tests and error code unit tests"
```

---

### Task 21: Test Coverage — Core Utility Tests

**Files:**
- Create: `server/tests/Unit/Core/TokenManagerTest.php`
- Create: `server/tests/Unit/Core/RepositoryTest.php`

- [ ] **Step 1: Create TokenManagerTest**

Read `server/core/auth/TokenManager.php` to understand the actual API. Test: generate, verify, refresh, blacklist, getUserId, expired token handling.

- [ ] **Step 2: Create RepositoryTest**

Test the base Repository contract using a concrete repository (e.g., `DictionaryRepository`): find, create, update, delete, getList, findWhere, count, exists.

- [ ] **Step 3: Run tests and commit**

```bash
git add server/tests/Unit/Core/
git commit -m "test: add TokenManager and Repository unit tests"
```

---

### Task 22: Integration Verification + Push

- [ ] **Step 1: Backend PHP syntax check**

```bash
cd server
find app/model/announcement app/model/region app/model/agreement app/model/version app/model/dataimport \
     app/repository/announcement app/repository/region app/repository/agreement app/repository/version app/repository/dataimport \
     app/service/announcement app/service/agreement app/service/region app/service/version app/service/dataimport \
     app/adminapi/controller/v1/announcement app/adminapi/controller/v1/agreement app/adminapi/controller/v1/region app/adminapi/controller/v1/version app/adminapi/controller/v1/dataimport \
     app/api/controller/v1/announcement app/api/controller/v1/agreement app/api/controller/v1/version \
     -name "*.php" -exec php -l {} \;
```

- [ ] **Step 2: Run all PHP tests**

```bash
cd server && vendor/bin/phpunit -v
```

- [ ] **Step 3: Verify git log**

```bash
git log --oneline -25
```

- [ ] **Step 4: Push to remotes**

```bash
git remote add github https://github.com/yuandianxitong/ydadmin.git 2>/dev/null || true
git remote add gitee git@gitee.com:yuandianxitong/ydadmin.git 2>/dev/null || true
git push github main
git push gitee main
```

- [ ] **Step 5: Fix any remaining issues and final commit**

```bash
git add -A
git commit -m "fix: Phase 3 integration fixes"
```
