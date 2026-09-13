#!/usr/bin/env bash
# 常驻内存纪律检查（spec §3.4）。
#
# 规则一：app/、core/ 禁止可变静态属性——请求态必须放 support\Context。
#   白名单按「文件:属性名」登记（不按行号，改代码不会让白名单失效），每条都要写明为什么安全。
#   用反射（scripts/check-static-properties.php）而不是正则匹配源码：修饰符顺序
#   （private static / static private）、是否换行、逗号并列声明多个属性等书写方式的
#   变化都不会绕过反射得到的属性元数据。
# 规则二：Service 与 Controller 禁止直接调用 Db::（不区分大小写，PHP 类名不区分大小写）——查询一律封装在 Repository。
set -eo pipefail
cd "$(dirname "$0")/.."

STATIC_WHITELIST=(
  'core/auth/TokenManager.php:$instances'                          # scope → 实例：由部署期配置构造，构造后只读
  'core/validation/ValidatorFactory.php:$translator'               # 只读消息目录；每次使用前从 Context 取 locale
  'core/validation/ValidatorFactory.php:$factory'                  # 只读校验工厂
  'app/middleware/AdminPermissionMiddleware.php:$permissionCache'  # 注解反射结果：key=类@方法，部署期固定
)

fail=0

php scripts/check-static-properties.php "${STATIC_WHITELIST[@]}" || fail=1

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
scope_fail=0
scope_hits=$(grep -rnE --include='*.php' 'withoutGlobalScope' app core \
  | sed -E 's/withoutGlobalScope\(SoftDeletingScope::class\)//g' \
  | grep -E 'withoutGlobalScope' || true)
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
core_fail=0
core_hits=$(grep -rnE --include='*.php' --exclude-dir='core/generator/stubs' '^use +app\\' core || true)
if [ -n "$core_hits" ]; then
  echo "❌ core/ 依赖了应用层（core 不得 use app\\；改为 core\\contract 下的接口 + config/container.php 绑定）："
  echo "$core_hits"
  core_fail=1
fi
[ "$core_fail" = 0 ] && echo "✅ 规则六通过：core/ 未依赖 app/"

if [ "$fail" != 0 ] || [ "$db_fail" != 0 ] || [ "$model_fail" != 0 ] || [ "$repo_fail" != 0 ] || [ "$scope_fail" != 0 ] || [ "$core_fail" != 0 ]; then
  exit 1
fi
