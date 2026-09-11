<?php

return [
    // 允许跨域的来源（.env 的 CORS_ALLOWED_ORIGINS，逗号分隔）；为空时不输出任何跨域头，只允许同域访问
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))))),
];
