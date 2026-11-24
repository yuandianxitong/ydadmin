<?php

declare(strict_types=1);

namespace core\plugin;

use core\exception\BusinessException;
use support\Log;
use ZipArchive;

/**
 * 插件前端产物的发布位置与拷贝。
 *
 * 目录名带 token（能力路径）：产物由 nginx 静态下发，没有运行时鉴权的机会，
 * 而闭源插件的前端整包放在可猜路径上等于公开，且 manifest 自带全部 chunk 文件名，
 * 顺带泄露每个部署装了哪些插件。token 由 APP_KEY 派生——它每个部署独立生成，
 * 因此同一插件在不同部署下路径不同，无法跨站枚举。
 *
 * 没有解决的：拿到 base 的人可以把 URL 分享出去，且长期有效。这是 capability URL
 * 的固有性质。真正的鉴权下发（比照 /plugin-icon 走 PHP）代价是每个 chunk 一次
 * PHP 请求，与长缓存冲突；签名 URL 需要 nginx secure_link 模块。
 *
 * APP_KEY 轮换会让所有已发布目录失效（token 变了，旧目录名对不上），修复手段是
 * 重新发布 + 清掉旧 token 目录（`saas:plugin-assets-sync`），本类只给出 removePath() 这一侧的闸。
 *
 * 升级时保留上一版产物一代，位置见 backupDir()。两条配套决定：
 *   - 保留副本落在 runtime/ 下（不可 web 访问），不复用 publish() 的 .old 中间态；
 *   - 升级**失败**不自动回滚产物，只保留。失败多半死在 SQL 或后端，此时把前端
 *     单独退回上一版，得到的正是「新后端配旧前端」——恰好是保留产物要防的那种
 *     错配。让人在知道失败原因之后再决定退不退，比替他决定更安全。回滚由
 *     restore() 支撑的手动命令触发。
 *     注意与升级事务内的回滚分支区分：那一支源码和产物一起退回，前后端同版。
 */
final class PluginAssetPublisher
{
    private const TOKEN_LENGTH = 16;

    /** APP_KEY 的长度下限，理由见 token() 的注释 */
    private const MIN_KEY_LENGTH = 16;

    /** 产物根，相对部署根目录。remove/removePath 的越界判定以它为界 */
    private const PUBLIC_PREFIX = 'public/plugin-assets';

    /**
     * 上一版产物的保留位置，相对部署根目录。理由见 backupDir()。
     */
    private const BACKUP_PREFIX = 'runtime/plugin-assets-backup';

    /**
     * 本类认识的宿主，取自宿主画像的 publishableFrontends() 键，同时是路径片段白名单（见 assertSegments()）。
     *
     * @return list<string>
     */
    public static function hosts(): array
    {
        return array_keys(PluginHostProfiles::get()->publishableFrontends());
    }

    /**
     * 允许发布到 `public/` 的宿主 = {@see hosts()} ∩
     * {@see HostSdkCompat::gatedHosts()}，保持 hosts() 的顺序。
     *
     * 不变式：发布集 ⊆ 把关集。没有任何闸检查的宿主产物，不许出现在
     * web 可访问目录下。用交集实现而不是另抄一份常量，是为了让这条不变式由
     * 构造保证——两份清单将来任何一边变动都不可能产出第三种不一致。
     *
     * 与 {@see hosts()} 的分工（两者都对，不要"统一"）：
     *   - hosts()            路径片段白名单 / GC 孤儿分类 / restore，去掉某个 host 会让它的历史产物无法回收
     *   - publishableHosts() 发布准入，取决于 HostSdkCompat 当下把关哪些 host
     *
     * 两者目前取值相等，但定义不同，不要合并成直接返回 hosts()。
     *
     * @return list<string>
     */
    public static function publishableHosts(): array
    {
        $gated = HostSdkCompat::gatedHosts();

        return array_values(array_filter(
            self::hosts(),
            static fn (string $host): bool => in_array($host, $gated, true)
        ));
    }

    /**
     * 派生源可用性的前置体检，不可用则抛出 token() 的原文。
     *
     * 给运维命令用：token() 的硬失败发生在每一次路径计算里，`saas:plugin-assets-sync`
     * 不先探一次就会在第一个插件上抛 500，而它恰恰是给「APP_KEY 出问题」的场景准备的
     * ——运维看到的将是一句与 APP_KEY 毫无关联的「插件产物路径」报错加一段栈。
     *
     * code 传 'preflight' 只为满足片段校验，与任何真插件无关；token 与 host/code 有关
     * 但可用性判定与它们无关，探哪一组都一样。
     */
    public static function assertKeyUsable(): void
    {
        self::token(self::hosts()[0], 'preflight');
    }

    public static function dirName(string $host, string $code): string
    {
        self::assertSegments($host, $code);

        return $code . '-' . self::token($host, $code);
    }

    public static function baseUrl(string $host, string $code): string
    {
        return '/plugin-assets/' . $host . '/' . self::dirName($host, $code) . '/';
    }

    public static function publicDir(string $root, string $host, string $code): string
    {
        return self::assetsRoot($root) . '/' . $host . '/' . self::dirName($host, $code);
    }

    /** 产物是否已编译（判定插件是否为预编译插件的唯一依据） */
    public static function hasDist(string $pluginDir, string $host): bool
    {
        return is_file(rtrim($pluginDir, '/\\') . '/dist/' . $host . '/manifest.json');
    }

    /**
     * 带 tenant 前端的插件必须自带产物。生产不再把插件编进宿主 bundle。
     *
     * 只看 tenant：platform 还没有构建器，不能拿 src/platform 当拒装条件。
     * 纯后端（没有 src/tenant/）放行。pc 见 {@see assertPcDistIfNeeded}。
     */
    public static function assertTenantDistIfNeeded(string $pluginDir): void
    {
        $root = rtrim($pluginDir, '/\\');
        if (!is_dir($root . '/src/tenant')) {
            return;
        }
        if (self::hasDist($root, 'tenant')) {
            return;
        }

        $code = basename($root);
        throw new BusinessException(
            "插件 {$code} 带 src/tenant/ 前端，但没有 dist/tenant/manifest.json。"
            . '本阶段不再把插件编进宿主 bundle，请用当前版本的 plugin:pack 打出预编译产物后再装。',
            422
        );
    }

    /**
     * zip 版的 {@see assertTenantDistIfNeeded}，不落盘。
     *
     * 调用方需自行保证 zip 已经 open()。本方法不 close 它。
     */
    public static function assertTenantDistInZipIfNeeded(ZipArchive $zip, string $code): void
    {
        if (!self::zipHasTenantSrc($zip)) {
            return;
        }
        if ($zip->locateName('dist/tenant/manifest.json') !== false) {
            return;
        }

        throw new BusinessException(
            "插件 {$code} 带 src/tenant/ 前端，但没有 dist/tenant/manifest.json。"
            . '本阶段不再把插件编进宿主 bundle，请用当前版本的 plugin:pack 打出预编译产物后再装。',
            422
        );
    }

    /**
     * 带 pc 前端的插件必须自带产物。生产不再把插件编进宿主 bundle。
     *
     * 纯后端（没有 src/pc/）放行。tenant 见 {@see assertTenantDistIfNeeded}。
     */
    public static function assertPcDistIfNeeded(string $pluginDir): void
    {
        $root = rtrim($pluginDir, '/\\');
        if (!is_dir($root . '/src/pc')) {
            return;
        }
        if (self::hasDist($root, 'pc')) {
            return;
        }

        $code = basename($root);
        throw new BusinessException(
            "插件 {$code} 带 src/pc/ 前端，但没有 dist/pc/manifest.json。"
            . '本阶段不再把插件编进宿主 bundle，请用当前版本的 plugin:pack 打出预编译产物后再装。',
            422
        );
    }

    /**
     * zip 版的 {@see assertPcDistIfNeeded}，不落盘。
     *
     * 调用方需自行保证 zip 已经 open()。本方法不 close 它。
     */
    public static function assertPcDistInZipIfNeeded(ZipArchive $zip, string $code): void
    {
        if (!self::zipHasPcSrc($zip)) {
            return;
        }
        if ($zip->locateName('dist/pc/manifest.json') !== false) {
            return;
        }

        throw new BusinessException(
            "插件 {$code} 带 src/pc/ 前端，但没有 dist/pc/manifest.json。"
            . '本阶段不再把插件编进宿主 bundle，请用当前版本的 plugin:pack 打出预编译产物后再装。',
            422
        );
    }

    private static function zipHasTenantSrc(ZipArchive $zip): bool
    {
        return self::zipHasSrcPrefix($zip, 'src/tenant');
    }

    private static function zipHasPcSrc(ZipArchive $zip): bool
    {
        return self::zipHasSrcPrefix($zip, 'src/pc');
    }

    private static function zipHasSrcPrefix(ZipArchive $zip, string $prefix): bool
    {
        $count = $zip->numFiles;
        for ($i = 0; $i < $count; $i++) {
            $name = $zip->getNameIndex($i);
            if (!is_string($name)) {
                continue;
            }
            if ($name === $prefix || $name === $prefix . '/' || str_starts_with($name, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    public static function publish(string $root, string $pluginDir, string $host, string $code): void
    {
        $source = rtrim($pluginDir, '/\\') . '/dist/' . $host;
        if (!is_dir($source)) {
            throw new BusinessException("插件 {$code} 没有 {$host} 产物可发布：{$source}", 404);
        }

        self::swapIn($source, self::publicDir($root, $host, $code));
    }

    /**
     * 上一版产物的保留位置。
     *
     * 刻意在 runtime/ 下而不是沿用 publish() 的中间态 `<code>-<token>.old`：后者
     * 与在用目录同一个 token、只差后缀，且就在 nginx 的 plugin-assets alias 之内
     * ——任何拿到过自己那份 base 的租户，把尾部换成 .old/ 就能取到上一版的完整
     * 闭源前端。作为转瞬即逝的中间态尚可接受，转成长期保留这个前提就没了，
     * 而结论正好与能力路径要防的事相反。runtime/ 则完全不可 web 访问。
     *
     * 目录名不带 token：它不经 nginx，token 在这里挡不住任何人，而稳定的名字
     * 让手动回滚与运维巡检能直接按 code 找到它。
     */
    public static function backupDir(string $root, string $host, string $code): string
    {
        self::assertSegments($host, $code);

        return self::backupRoot($root) . '/' . $host . '/' . $code;
    }

    /**
     * 把某一版插件树里的产物留存为「上一版副本」，覆盖更早的那一份。
     *
     * 参数形状与 publish() 刻意一致（源都是 <pluginDir>/dist/<host>），只是目的地
     * 不同：publish 换入在用位置，archive 换入保留位置。调用方通常传升级期备份下来
     * 的**旧版**源码树（plugins/.backup/<code>-<version>-<ts>），而不是在用目录——
     * 字节相同，但从旧树取源能让「留存」发生在升级成功之后，从而不会在升级失败时
     * 把更早的那一代覆盖掉。
     *
     * 只留一代：每一代都是一份完整的闭源前端，攒着等于把插件的全部历史版本
     * 长期堆在磁盘上，而这批目录没有任何入口引用、也不会有人再去看。
     *
     * @return bool false = 这一版本来就没有该 host 的产物，没什么可留（不是错误）
     */
    public static function archive(string $root, string $pluginDir, string $host, string $code): bool
    {
        $source = rtrim($pluginDir, '/\\') . '/dist/' . $host;
        if (!is_dir($source)) {
            return false;
        }

        self::swapIn($source, self::backupDir($root, $host, $code));

        return true;
    }

    /**
     * 把保留的上一版换回在用位置（手动回滚）。
     *
     * 不消耗副本：换入是拷贝，失败可以原样重试。回滚是低频操作，走一次全量
     * 拷贝换来「可重复执行」是值得的。
     *
     * @return bool false = 没有保留副本可用。调用方要靠它区分「回滚成功」与
     *              「本来就没得回滚」，对操作者是完全不同的结论
     */
    public static function restore(string $root, string $host, string $code): bool
    {
        $source = self::backupDir($root, $host, $code);
        if (!is_dir($source)) {
            return false;
        }

        self::swapIn($source, self::publicDir($root, $host, $code));

        return true;
    }

    /** 幂等，同 remove()：purge 会无条件调它，不看插件目录里还剩什么 */
    public static function removeBackup(string $root, string $host, string $code): void
    {
        self::rrmdirOrFail(self::backupDir($root, $host, $code));
    }

    /**
     * 把 $source 整体原子换入 $target（目标已有内容则被替换并删除）。
     *
     * publish / archive / restore 共用同一套换入语义，最要紧的一条是
     * 「$target 要么是完整的旧内容、要么是完整的新内容」。
     */
    private static function swapIn(string $source, string $target): void
    {
        // 先写 .tmp 再整体换入：直接往目标目录拷贝会有一段「拷到一半」的窗口，
        // 期间浏览器可能取到不完整的 chunk 集合——症状是随机页面 404，重试就好了，
        // 属最难复现的一类故障。
        //
        // 覆盖范围要说准：拷贝阶段抛异常时既有产物一个字节没动过；但 rename 是
        // 两步（target→.old、.tmp→target，POSIX 的 rename 无法覆盖非空目录），
        // 进程在两步之间被杀会留下 .old 而没有 target，站点全线 404 且不自愈
        // ——下一次换入会先删 .old 而不是把它换回来。这个窗口本方案不覆盖。
        //
        // .tmp 刻意与 target 同父目录而不是放 sys_get_temp_dir()：跨文件系统的
        // rename() 会直接失败（EXDEV），而 /tmp 与部署目录不同挂载点是常态。
        $tmp = $target . '.tmp';
        // 残留 .tmp 必须真清掉再拷：copyDir 是合并式的，清不干净就会把上一次
        // 失败发布的旧 chunk 一并换入。
        self::rrmdirOrFail($tmp);

        try {
            self::copyDir($source, $tmp);
        } catch (\Throwable $e) {
            // 拷到一半失败：丢掉半成品，既有产物一个字节都没动过
            self::rrmdir($tmp);

            throw $e;
        }

        $old = $target . '.old';

        try {
            self::rrmdirOrFail($old);
        } catch (\Throwable $e) {
            // 这条抛出路径在 copyDir 之后，$tmp 已经装满新产物。不清就会留下一份
            // 完整的新版副本，且错误文案只提 .old、完全不提它——本方法四条抛出
            // 路径里唯一没留痕的残留。与上面的 copyDir 失败同形处理。
            self::rrmdir($tmp);

            throw $e;
        }

        // target 若被人手动换成了普通文件，rename 目录到它会失败；先清掉
        if (file_exists($target) && !is_dir($target)) {
            @unlink($target);
        }
        if (is_dir($target) && !rename($target, $old)) {
            self::rrmdir($tmp);

            throw new BusinessException("无法移走旧产物：{$target}", 500);
        }
        if (!rename($tmp, $target)) {
            if (is_dir($old)) {
                rename($old, $target);
            }
            self::rrmdir($tmp);

            throw new BusinessException("无法换入新产物：{$target}", 500);
        }

        // 到这里新内容已经在位，换入本身是成功的：清不掉 .old 不该让调用方回滚。
        // 但 .old 是上一版产物的完整副本，且与 target 同名只差后缀；target 在
        // public/ 下时它就落在 nginx alias 之内，留着等于把历史版本继续挂在线上。
        // 必须留下可查的痕迹。
        if (!self::rrmdir($old)) {
            Log::warning("[plugin-assets] 换入后旧副本未能清除，历史版本仍在磁盘上：{$old}");
        }
    }

    public static function remove(string $root, string $host, string $code): void
    {
        self::rrmdirOrFail(self::publicDir($root, $host, $code));
    }

    /**
     * 清理孤儿产物目录（APP_KEY 轮换后旧 token 目录、插件已卸载但产物尚在等）。
     *
     * 与 remove() 的区别：这里的路径由调用方算出来（扫描目录得到的孤儿名），
     * 不经过 dirName() 的片段校验，所以必须在这里自证它落在产物根之内。
     * 允许的形态只有 <root>/public/plugin-assets/<host>/<dir>（正好两级）：
     * 再浅一级就是「一把梭删掉某个 host 的全部插件产物」，那不是清孤儿。
     *
     * $root 刻意必填、且与 publish()/remove() 一样排在首位：调用方扫的是哪个根，
     * 就得拿哪个根来判越界。留缺省值虽然是 fail-closed，但两者不一致时报错
     * 文案会指向「越界」而真因是「根传漏了」，排查方向全错。
     */
    public static function removePath(string $root, string $path): void
    {
        $realBase = realpath(self::assetsRoot($root));
        if ($realBase === false) {
            // 产物根都不存在，自然没有孤儿可清
            return;
        }

        // 目标本身可能是软链：不解析它，只解析它的父目录，随后按「断链」处理。
        // 直接 realpath($path) 会跟到链接目标，把越界判定绕过去。
        $name = basename($path);

        // 但「不解析目标」的代价是 basename 可能是 . 或 ..，它们会让下面两道闸
        // 全部失效：<base>/tenant/.. 拼出来仍以 <base>/ 开头、层数仍是 2，而
        // rrmdir 的 is_dir() 为真，照常递归——一个 /.. 就删光 tenant 与 platform
        // 两个 host 下的全部产物，而裸写 <base>/tenant 反而会被拒。
        // scandir() 默认就返回 . 和 ..，这不是臆想的输入。
        if ($name === '' || $name === '.' || $name === '..') {
            throw new BusinessException("拒绝按相对片段删除产物目录：{$path}", 400);
        }

        $parent = realpath(dirname($path));
        if ($parent === false) {
            return;
        }
        $resolved = $parent . DIRECTORY_SEPARATOR . $name;

        if (!str_starts_with($resolved, $realBase . DIRECTORY_SEPARATOR)) {
            throw new BusinessException("拒绝删除产物目录之外的路径：{$path}", 400);
        }

        $rel = trim(substr($resolved, strlen($realBase)), DIRECTORY_SEPARATOR);
        if (substr_count($rel, DIRECTORY_SEPARATOR) !== 1) {
            throw new BusinessException("产物目录只能按 <host>/<dir> 两级删除：{$path}", 400);
        }

        self::rrmdirOrFail($resolved);
    }

    /**
     * 每个自带前端的 host 都有产物 → 该插件无需云编译。
     *
     * 「自带前端」以 src/<host>/ 是否存在为准，「有产物」以 dist/<host>/manifest.json
     * 为准。纯后端插件（两者都没有）返回 false。
     */
    public static function isFullyPrebuilt(string $pluginDir): bool
    {
        $hosts = array_filter(
            self::hosts(),
            fn (string $h): bool => is_dir(rtrim($pluginDir, '/\\') . '/src/' . $h)
        );

        if ($hosts === []) {
            return false;
        }

        foreach ($hosts as $host) {
            if (!self::hasDist($pluginDir, $host)) {
                return false;
            }
        }

        return true;
    }

    private static function assetsRoot(string $root): string
    {
        return rtrim($root, '/\\') . '/' . self::PUBLIC_PREFIX;
    }

    private static function backupRoot(string $root): string
    {
        return rtrim($root, '/\\') . '/' . self::BACKUP_PREFIX;
    }

    /**
     * host / code 直接拼进要删除的目录路径，必须自己校验而不是指望调用方。
     *
     * code 的正则须带 `D` 修饰符：不带它 `$` 会放行结尾换行，`cms\n` 就能混进路径。
     */
    private static function assertSegments(string $host, string $code): void
    {
        if (!in_array($host, self::hosts(), true)) {
            throw new BusinessException("非法的插件产物宿主：{$host}", 400);
        }
        if ($code === '' || !preg_match(PluginCodePattern::ASSET, $code)) {
            throw new BusinessException("非法的插件 code：{$code}", 400);
        }
    }

    /**
     * token 的派生源。
     *
     * 读 config('app.app_key')，与 core\utils\Encryption 的密钥来源一致。
     *
     * 缺失时硬失败而不是回退空串：回退没有任何报错，但所有部署会算出同一个
     * token，能力路径当场退化成可猜路径——安全性静默归零是最坏的失败形态。
     *
     * 占位符 change-me-app-key 与空串同等对待：它是 `.env.example` 的出厂值，随
     * 开源仓库公开，谁都能据此算出全网通用的 token，退化程度与空串完全一样。
     *
     * 另加一道长度下限，理由是这里的密钥强度比 APP_KEY 的其他用途更要紧：
     * 任何持有自己那份 base 的租户，手里就是一组已知的 (message, 截断 MAC)，
     * 可以离线爆破 APP_KEY，成功后即可推出**其他插件**的 token——一条从「有一个
     * 插件的权益」到「拿到全部插件产物」的提权路径。相比之下 Encryption 的用途
     * 要先拿到数据库才谈得上攻击。我方真实 APP_KEY 是 64 个 hex 字符，16 这个
     * 下限只拦得住手敲短串，不可能误伤正常安装。
     *
     * 说清它不是什么：这是长度下限，不是熵检测。`0123456789abcdef` 一样能过。
     * 真正的把关应当在安装/体检环节做，这里只是最后一道。
     */
    private static function token(string $host, string $code): string
    {
        $key = (string) config('app.app_key', '');
        if ($key === '' || stripos($key, 'change-me') !== false) {
            throw new BusinessException(
                'APP_KEY 未配置或仍是 .env.example 的占位符，无法计算插件产物路径',
                500
            );
        }
        if (strlen($key) < self::MIN_KEY_LENGTH) {
            throw new BusinessException(
                sprintf(
                    'APP_KEY 太短（%d 字符，至少需要 %d），无法安全派生插件产物路径。'
                    . '请用 `openssl rand -hex 32` 重新生成',
                    strlen($key),
                    self::MIN_KEY_LENGTH
                ),
                500
            );
        }

        return substr(hash_hmac('sha256', "plugin-assets|{$host}|{$code}", $key), 0, self::TOKEN_LENGTH);
    }

    private static function copyDir(string $from, string $to): void
    {
        if (!is_dir($to) && !mkdir($to, 0o755, true) && !is_dir($to)) {
            throw new BusinessException("无法创建目录：{$to}", 500);
        }
        foreach (array_diff(scandir($from) ?: [], ['.', '..']) as $name) {
            $src = $from . '/' . $name;
            $dst = $to . '/' . $name;

            // 不跟随目录软链：dist/ 是我们自己的构建产物，本不该有；真出现了
            // 跟进去可能无限递归，也可能把构建机上的无关目录整个搬进 public/
            if (is_link($src) && is_dir($src)) {
                continue;
            }

            if (is_dir($src)) {
                self::copyDir($src, $dst);

                continue;
            }

            // copy() 的返回值必须看：忽略它等于把「拷到一半」当成功，
            // 原子换入随后照常执行，缺文件的产物就上线了。前缀 @ 只是压掉
            // PHP 自身的 E_WARNING，不影响下面这行对返回值的判断。
            if (!@copy($src, $dst)) {
                throw new BusinessException("无法拷贝产物文件：{$src}", 500);
            }
        }
    }

    /** 删不干净就抛。删除是安全动作，静默失败等于「以为删了、其实还在」 */
    private static function rrmdirOrFail(string $dir): void
    {
        if (!self::rrmdir($dir)) {
            throw new BusinessException("插件产物目录删除失败（权限或占用）：{$dir}", 500);
        }
    }

    /**
     * @return bool 目录（及其内容）是否已全部消失。false 表示至少有一项没删掉
     */
    private static function rrmdir(string $dir): bool
    {
        // 软链只断链不跟进：跟进去删的是链接目标里的内容，而目标可能是
        // 宿主 SPA 目录甚至部署根
        if (is_link($dir)) {
            return @unlink($dir);
        }
        if (!is_dir($dir)) {
            // 不存在 → 已经没了，返回 true。这条必须保持：remove() 是补偿动作，
            // 会被重试，第二次调用不能抛。
            // 但「存在且是普通文件」不等于「已全部消失」，直接 return true 就是
            // 谎报删除——该删掉它再回报结果。
            return !file_exists($dir) || @unlink($dir);
        }

        // 返回值必须逐层回传：@unlink/@rmdir 抑制的是 warning，不是失败本身。
        // 全程忽略它们，remove() 就会在只读目录上「成功」返回，而闭源插件的前端
        // 整包仍留在租户已知的 base 路径上——权益撤了、产物还在。
        $ok = true;
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $f) {
            $p = $dir . '/' . $f;
            if (is_link($p) || !is_dir($p)) {
                $ok = @unlink($p) && $ok;

                continue;
            }
            $ok = self::rrmdir($p) && $ok;
        }

        return @rmdir($dir) && $ok;
    }
}
