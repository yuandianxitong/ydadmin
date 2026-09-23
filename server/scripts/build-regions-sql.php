<?php

declare(strict_types=1);

/**
 * 把 regions-source/pca-code.json 转成 database/install/regions.sql。
 * 省 2 位、市 4 位补成 6 位（110000 / 110100）；区已是 6 位。
 */

$root = dirname(__DIR__);
$source = $root . '/database/install/regions-source/pca-code.json';
$target = $root . '/database/install/regions.sql';

$tree = json_decode((string) file_get_contents($source), true);
if (!is_array($tree)) {
    fwrite(STDERR, "无法解析 {$source}\n");
    exit(1);
}

$pad = static function (string $code): string {
    return str_pad($code, 6, '0');
};

$rows = [];
foreach ($tree as $provinceSort => $province) {
    $provinceCode = $pad((string) $province['code']);
    $prefix = (int) substr($provinceCode, 0, 2);
    if ($prefix < 11 || $prefix > 65) {
        continue;
    }
    $rows[] = [$provinceCode, '0', (string) $province['name'], $provinceCode, 1, $provinceSort + 1];
    foreach ((array) ($province['children'] ?? []) as $citySort => $city) {
        $cityCode = $pad((string) $city['code']);
        $rows[] = [$cityCode, $provinceCode, (string) $city['name'], $cityCode, 2, $citySort + 1];
        foreach ((array) ($city['children'] ?? []) as $districtSort => $district) {
            $districtCode = $pad((string) $district['code']);
            // 源数据里东莞、中山、儋州、嘉峪关这几个不设区的市，第三级给的是街道/镇，编码是 9 位。
            // 本表的约定是 GB/T 2260 六位码，多出来的街道会让人在「市」下面看到几十个街道而不是区县。
            if (strlen($districtCode) !== 6) {
                continue;
            }
            $rows[] = [$districtCode, $cityCode, (string) $district['name'], $districtCode, 3, $districtSort + 1];
        }
    }
}

$esc = static fn (string $value): string => str_replace("'", "''", $value);
$values = array_map(static function (array $row) use ($esc): string {
    return sprintf(
        "(%s,%s,'%s','%s',%d,%d,1)",
        $row[0],
        $row[1],
        $esc($row[2]),
        $row[3],
        $row[4],
        $row[5]
    );
}, $rows);

$header = <<<'SQL'
-- ============================================================
-- 区域数据（省市区三级，大陆 31 省）
-- 来源见 database/install/regions-source/SOURCE.md
-- 由 scripts/build-regions-sql.php 生成，请勿手改
-- ============================================================

INSERT INTO `regions` (`id`, `parent_id`, `name`, `code`, `level`, `sort`, `status`) VALUES

SQL;

file_put_contents($target, $header . implode(",\n", $values) . ";\n");
fwrite(STDOUT, 'wrote ' . count($rows) . " rows to {$target}\n");
