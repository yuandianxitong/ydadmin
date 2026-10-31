<?php

declare(strict_types=1);

namespace core\generator;

/** CLI 与后台共用的生成后操作指引；命令仅供人工执行，不读取或输出凭证。 */
final class GenerationGuide
{
    /** @return array{menu_sql: string, route: string, manual: list<string>, docker: list<string>} */
    public static function forRequest(GeneratorRequest $request): array
    {
        $names = new NameConvention();
        $sql = "server/database/generated/{$request->moduleName}-{$names->snake($request->modelName)}-menu.sql";
        return [
            'menu_sql' => $sql,
            'route' => "/admin/{$request->moduleName}/{$names->kebab($request->modelName)}",
            'manual' => [
                'mysql --default-character-set=utf8mb4 -u USER -p DATABASE < ' . $sql,
                '(cd server && php start.php reload)',
                'pnpm --dir admin build',
            ],
            'docker' => [
                'docker compose -f docker/docker-compose.yml exec -T mysql sh -c \'MYSQL_PWD="$MYSQL_PASSWORD" mysql --default-character-set=utf8mb4 -u"$MYSQL_USER" "$MYSQL_DATABASE"\' < ' . $sql,
                'docker compose -f docker/docker-compose.yml exec -T webman php start.php reload',
                'pnpm --dir admin build',
            ],
        ];
    }
}
