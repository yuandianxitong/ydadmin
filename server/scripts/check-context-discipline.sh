#!/usr/bin/env bash
# 常驻内存纪律检查（spec §3.4）。
#
# 用法：scripts/check-context-discipline.sh [target_root]
#   不传参数：检查本仓库的 app/ 与 core/（与之前完全一致）。
#   传参数：检查 <target_root>/app 与 <target_root>/core——M2a 代码生成器的门禁测试用它把同一套
#   规则跑在生成产物的临时目录上，而不是真实仓库。
#
#   例外：规则一（可变静态属性）委托给 scripts/check-static-properties.php，那个脚本内部硬编码
#   扫描本仓库真实的 app/、core/（`dirname(__DIR__)` 取的是它自己的真实安装路径，不接受任何参数）。
#   它不在本次改动范围内——本脚本这里加的可选路径参数只覆盖规则二～六——所以规则一无法跟着这个
#   路径参数走：传参数运行时，规则一检查的仍然是本仓库真实代码，不会覆盖临时目录里的生成产物。
#   这是已知限制，不是遗漏：生成产物的静态属性检查因此只能靠 phpstan/cs-fixer 的常规规则间接兜底，
#   门禁测试里会写清楚这一点。
#
# 规则一：app/、core/ 禁止可变静态属性——请求态必须放 support\Context。
#   白名单按「文件:属性名」登记（不按行号，改代码不会让白名单失效），每条都要写明为什么安全。
#   用反射（scripts/check-static-properties.php）而不是正则匹配源码：修饰符顺序
#   （private static / static private）、是否换行、逗号并列声明多个属性等书写方式的
#   变化都不会绕过反射得到的属性元数据。
# 规则二：Service 与 Controller 禁止直接调用 Db::（不区分大小写，PHP 类名不区分大小写）——查询一律封装在 Repository。
set -eo pipefail

# 脚本自身的绝对路径：下面要 cd 到检查目标（可能是仓库外的临时目录），之后仍要能找到
# 同目录下的 check-static-properties.php，不能再用相对路径 "scripts/check-static-properties.php"。
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
TARGET_ROOT="${1:-$REPO_ROOT}"
if [ ! -d "$TARGET_ROOT" ]; then
  echo "❌ 目标目录不存在：$TARGET_ROOT"
  exit 1
fi
TARGET_ROOT="$(cd "$TARGET_ROOT" && pwd)"
cd "$TARGET_ROOT"
if [ "$TARGET_ROOT" != "$REPO_ROOT" ]; then
  # 用 printf 而不是 echo "...$TARGET_ROOT（..."：同一个 bash 3.2 / zh_CN.UTF-8 的「变量名后面
  # 紧跟多字节字符」解析 bug（规则三那条注释描述的那个），实测会把 $TARGET_ROOT 的内容吃掉。
  printf 'ℹ️  按自定义路径检查：%s（规则一仍检查本仓库真实的 app/、core/，见脚本头部说明）\n' "$TARGET_ROOT"
fi

STATIC_WHITELIST=(
  'core/auth/TokenManager.php:$instances'                          # scope → 实例：由部署期配置构造，构造后只读
  'core/validation/ValidatorFactory.php:$translator'               # 只读消息目录；每次使用前从 Context 取 locale
  'core/validation/ValidatorFactory.php:$factory'                  # 只读校验工厂
  'app/middleware/AdminPermissionMiddleware.php:$permissionCache'  # 注解反射结果：key=类@方法，部署期固定
  'app/service/system/ApiDocService.php:$documentCache'            # 部署期固定：路由表与注解在运行期不变
)

fail=0

php "$SCRIPT_DIR/check-static-properties.php" "${STATIC_WHITELIST[@]}" || fail=1

# 新增例外时在这里登记，格式 "文件路径"，并写明理由；目前没有例外。
DB_WHITELIST=""
db_dirs=""
for d in app/service app/controller app/adminapi/controller app/api/controller; do
  [ -d "$d" ] && db_dirs="$db_dirs $d"
done
db_fail=0
if [ -n "$db_dirs" ]; then
  # shellcheck disable=SC2086
  db_hits=$(grep -rliE --include='*.php' '\bdb::' $db_dirs || true)
  while IFS= read -r f; do
    [ -z "$f" ] && continue
    case " $DB_WHITELIST " in
      *" $f "*) continue ;;
    esac
    echo "❌ Service/Controller 直接调用了 Db::（应封装进 Repository）：$f"
    db_fail=1
  done <<< "$db_hits"
fi
[ "$db_fail" = 0 ] && echo "✅ 规则二通过：Service/Controller 未直接调用 Db::"

# ---------------------------------------------------------------------------
# 规则三：Service 与 Controller 禁止对 app\model\* 做静态调用（where/find/create/...）——
# 否则就绕开了 Repository::query() 注入的数据权限条件。
model_fail=0
for d in $db_dirs; do
  while IFS= read -r f; do
    [ -z "$f" ] && continue
    # 收集本文件 use 进来的 Model 短类名
    models=$(grep -oE '^use app\\model\\[A-Za-z0-9_\\]+;' "$f" | sed -E 's/.*\\([A-Za-z0-9_]+);/\1/' || true)
    for m in $models; do
      if grep -nE "\\b${m}::" "$f" >/dev/null; then
        # 用 printf 的 %s 而不是把 $f 直接拼进含全角括号的双引号字符串——
        # macOS 系统 bash 3.2 在 zh_CN.UTF-8 locale 下，"$f（" 这种「变量名
        # 后面紧跟多字节字符」的写法有解析 bug，会把变量值吃掉、只剩半个乱码字节。
        printf '❌ Service/Controller 对 Model 做了静态调用（应经 Repository）：%s（%s::）\n' "$f" "$m"
        model_fail=1
      fi
    done
    if grep -nE '\\app\\model\\[A-Za-z0-9_\\]+::' "$f" >/dev/null; then
      echo "❌ Service/Controller 用全限定名对 Model 做了静态调用：$f"
      model_fail=1
    fi
  done <<< "$(grep -rlE --include='*.php' 'app\\model' $db_dirs 2>/dev/null || true)"
done
[ "$model_fail" = 0 ] && echo "✅ 规则三通过：Service/Controller 未对 Model 做静态调用"

# ---------------------------------------------------------------------------
# 规则四：Repository 的查询必须从 $this->query() 起手；$this->model-> 会绕开数据权限。
repo_fail=0
if [ -d app/repository ]; then
  repo_hits=$(grep -rnE --include='*.php' '\$this->model->' app/repository || true)
  if [ -n "$repo_hits" ]; then
    echo "❌ Repository 直接使用了 \$this->model->（应从 \$this->query() 起手）："
    echo "$repo_hits"
    repo_fail=1
  fi
fi
[ "$repo_fail" = 0 ] && echo "✅ 规则四通过：Repository 未直接使用 \$this->model->"

# ---------------------------------------------------------------------------
# 规则五：数据权限是 Eloquent 全局作用域（core\datascope\DataScopeScope，由 Repository::query() 按查询挂载）。
# app/、core/ 禁止移除全局作用域：withoutGlobalScope / withoutGlobalScopes / withoutGlobalScopesExcept
# 都会连数据权限一起摘掉。唯一放行的写法是 withoutGlobalScope(SoftDeletingScope::class)——只摘软删作用域
# （查重要含软删行），与数据权限无关。判定时先删掉这种写法再找剩下的调用，同一行夹带别的移除照样报。
# 同理 Repository 禁止 ->getQuery()/->forceDelete()/->getModels()：全局作用域是惰性套用的，只在
# Builder::applyScopes() 里生效；这三个方法都绕过它直接操作底层查询——->getQuery() 拿到的底层查询
# 还没套用作用域，->forceDelete() 直接对底层查询发 delete，->getModels() 直接对底层查询发 get，全都
# 会绕开数据权限。（对已在范围内取到的模型实例调用 forceDelete() 是安全的，但 Repository 不得对
# query() 链式调用它；受控表的硬删一律走 query()->delete()。）
#
# 两个 grep 目标目录先按存在性过滤：传自定义路径时可能只有 app/ 没有 core/（生成器的产物本来就
# 不含 core 文件），直接 grep 一个不存在的目录会在 stderr 打一行噪音，不影响退出码（末尾有
# || true 兜底），但过滤掉更干净。
scope_fail=0
scope_dirs=""
for d in app core; do
  [ -d "$d" ] && scope_dirs="$scope_dirs $d"
done
scope_hits=""
if [ -n "$scope_dirs" ]; then
  # shellcheck disable=SC2086
  scope_hits=$(grep -rnE --include='*.php' 'withoutGlobalScope' $scope_dirs \
    | sed -E 's/withoutGlobalScope\(SoftDeletingScope::class\)//g' \
    | grep -E 'withoutGlobalScope' || true)
fi
if [ -n "$scope_hits" ]; then
  echo "❌ 移除了全局作用域（会连数据权限一起摘掉；输出行已去掉放行的软删写法）："
  echo "$scope_hits"
  scope_fail=1
fi
if [ -d app/repository ]; then
  getq_hits=$(grep -rnE --include='*.php' -e '->getQuery\(' -e '->forceDelete\(' -e '->getModels\(' app/repository || true)
  if [ -n "$getq_hits" ]; then
    echo "❌ Repository 使用了 ->getQuery()/->forceDelete()/->getModels()（都会绕开惰性套用的数据权限全局作用域）："
    echo "$getq_hits"
    scope_fail=1
  fi
fi
[ "$scope_fail" = 0 ] && echo "✅ 规则五通过：未移除全局作用域，Repository 未使用 ->getQuery()/->forceDelete()/->getModels()"

# ---------------------------------------------------------------------------
# 规则六：core/ 禁止 use app\——核心不得依赖应用层。core 要读系统配置之类的东西，就在 core\contract
# 下声明接口（如 ConfigValueReader），由 config/container.php 绑到 app 层的实现上；直接 use 应用层的
# 仓储，等于把仓库其他地方机械强制的分层（Controller → Service → Repository）反过来接一条线。
# 只匹配行首的 use 语句：注释与文档里提到 app\middleware\StaticFile 这类类名是正常的，不该被拦。
#
# --exclude-dir='core/generator/stubs'：core/generator/stubs/*.stub.php 是代码生成器的模板
# （Task 1 加的排除）——模板里为了原样拼出生成代码的 `use app\model\...` 之类语句，行首字面就会
# 出现 `use app\`，那是要落进被生成模块里的文本，不是这份模板本身依赖了应用层，排除掉避免误报。
#
# 先判存在性：传自定义路径时生成产物的临时 server 根下没有 core/ 目录，直接 grep 会在 stderr
# 打一行噪音（不影响退出码，但过滤掉更干净，与规则五同理）。
core_fail=0
core_hits=""
if [ -d core ]; then
  core_hits=$(grep -rnE --include='*.php' --exclude-dir='core/generator/stubs' '^use +app\\' core || true)
fi
if [ -n "$core_hits" ]; then
  echo "❌ core/ 依赖了应用层（core 不得 use app\\；改为 core\\contract 下的接口 + config/container.php 绑定）："
  echo "$core_hits"
  core_fail=1
fi
[ "$core_fail" = 0 ] && echo "✅ 规则六通过：core/ 未依赖 app/"

# ---------------------------------------------------------------------------
# 规则七（M2b spec §6/§14）：$this->validate( 的第二个参数必须恰好是 $this->{当前动作名}Rules()。
# core/apidoc/RuleReflector 按动作名反射调用 "{action}Rules"；校验规则一旦写成内联数组，
# 文档就会静默漏掉那个端点的参数——什么都不会报错，这正是本条门禁要堵的洞。
#
# 用 PHP 版检查器（scripts/check-validate-rules.php，token_get_all() 逐 token 比对）而不是
# awk 逐行正则：评审在隔离目录逐条实测过 awk 版两头都漏——箭头两侧带空格
# （$this -> validate(...)）、方法链跨行（$this\n->validate(...)）、行尾注释里恰好出现正确
# 方法名（// TODO: $this->storeRules()）会被 awk 版放行；格式正确但跨多行的 validate() 调用、
# 注释里提到 $this->validate( 反而被 awk 版误报——正则按「行」为单位，天然处理不了「这段
# 文本是不是注释」「参数是不是跨行」这类需要看语法结构才能回答的问题。token 流是词法分析的
# 结果，注释/字符串本身就是独立 token，不会被误当成代码，换行与空白也不影响比对；旧 awk
# 版在每条样本上的实跑结果见 task-11-report.md 补充记录。
rules_fail=0
rules_dirs=""
for d in app/controller app/adminapi/controller app/api/controller; do
  [ -d "$d" ] && rules_dirs="$rules_dirs $TARGET_ROOT/$d"
done
if [ -n "$rules_dirs" ]; then
  # shellcheck disable=SC2086
  php "$SCRIPT_DIR/check-validate-rules.php" "$TARGET_ROOT" $rules_dirs || rules_fail=1
else
  echo "✅ 规则七通过：\$this->validate( 的规则参数均为 \$this->{动作名}Rules()"
fi

if [ "$fail" != 0 ] || [ "$db_fail" != 0 ] || [ "$model_fail" != 0 ] || [ "$repo_fail" != 0 ] || [ "$scope_fail" != 0 ] || [ "$core_fail" != 0 ] || [ "$rules_fail" != 0 ]; then
  exit 1
fi
