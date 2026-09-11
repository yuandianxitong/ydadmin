<?php

return [
    // 反向代理 IP（.env 的 TRUSTED_PROXIES，逗号分隔，精确匹配）；只有直连地址在列表里时才读 X-Forwarded-For，见 core\http\ClientIp
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),
];
