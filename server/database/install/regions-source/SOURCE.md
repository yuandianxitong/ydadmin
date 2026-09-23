# 地区种子来源

- 标准：GB/T 2260 六位县级以上代码，`regions.id = regions.code`
- 快照：`modood/Administrative-divisions-of-China` 的 `dist/pca-code.json`（省/市/区三级）
- 范围：大陆 31 省（11–15、21–23、31–37、41–46、50–54、61–65）。不含港澳台
- 生成：`php scripts/build-regions-sql.php`
- 安装期与 `yd:update` 不访问网络

现网库补齐走 `database/updates/v2.0.1/update.php`：把本文件对应的 `regions.sql` 改成 `INSERT IGNORE`，不覆盖已改名称、不 TRUNCATE。
