#!/usr/bin/env bash
# 常驻内存纪律检查（spec §3.4）。
#
# 规则一：app/、core/ 禁止可变静态属性——请求态必须放 support\Context。
#   白名单按「文件:属性名」登记（不按行号，改代码不会让白名单失效），每条都要写明为什么安全。
# 规则二：Service 与 Controller 禁止直接调用 Db::——查询一律封装在 Repository。
set -eo pipefail
cd "$(dirname "$0")/.."

STATIC_WHITELIST=(
  'core/auth/TokenManager.php:$instances'                          # scope → 实例：由部署期配置构造，构造后只读
  'core/validation/ValidatorFactory.php:$translator'               # 只读消息目录；每次使用前从 Context 取 locale
  'core/validation/ValidatorFactory.php:$factory'                  # 只读校验工厂
  'app/middleware/AdminPermissionMiddleware.php:$permissionCache'  # 注解反射结果：key=类@方法，部署期固定
)

fail=0

static_hits=$(grep -rnE --include='*.php' '(private|protected|public)[[:space:]]+static[[:space:]]+(\?|[A-Za-z]|\$)' app core \
  | grep -vE 'static[[:space:]]+function' | grep -v 'const ' || true)
while IFS= read -r line; do
  [ -z "$line" ] && continue
  file="${line%%:*}"
  prop=$(printf '%s' "$line" | grep -oE '\$[A-Za-z_][A-Za-z0-9_]*' | head -1)
  allowed=0
  for entry in "${STATIC_WHITELIST[@]}"; do
    if [ "$entry" = "${file}:${prop}" ]; then
      allowed=1
      break
    fi
  done
  if [ "$allowed" = 0 ]; then
    echo "❌ 可变静态属性（违反常驻内存纪律，请求态请放 support\\Context）：$line"
    fail=1
  fi
done <<< "$static_hits"
[ "$fail" = 0 ] && echo "✅ 规则一通过：无未登记的可变静态属性"

# 新增例外时在这里登记，格式 "文件路径"，并写明理由；目前没有例外。
DB_WHITELIST=""
db_dirs=""
for d in app/service app/adminapi/controller app/api/controller; do
  [ -d "$d" ] && db_dirs="$db_dirs $d"
done
db_fail=0
if [ -n "$db_dirs" ]; then
  # shellcheck disable=SC2086
  db_hits=$(grep -rlE --include='*.php' 'Db::' $db_dirs || true)
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

if [ "$fail" != 0 ] || [ "$db_fail" != 0 ]; then
  exit 1
fi
