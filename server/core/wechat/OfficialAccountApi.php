<?php

declare(strict_types=1);

namespace core\wechat;

final class OfficialAccountApi
{
    public function __construct(
        private readonly WechatHttpClient $http,
        private readonly AccessTokenProvider $tokens,
        private readonly WechatConfigResolver $configs,
    ) {
    }

    /** @return array<string, mixed> */
    public function getCurrentSelfMenu(): array
    {
        $config = $this->configs->official();

        return $this->http->get('cgi-bin/get_current_selfmenu_info', [
            'access_token' => $this->tokens->token($config),
        ]);
    }

    /**
     * @param list<array<string, mixed>> $button
     * @return array<string, mixed>
     */
    public function createMenu(array $button): array
    {
        $config = $this->configs->official();

        return $this->http->postJson(
            'cgi-bin/menu/create',
            ['access_token' => $this->tokens->token($config)],
            ['button' => $button],
        );
    }

    /** @return array<string, mixed> */
    public function deleteMenu(): array
    {
        $config = $this->configs->official();

        return $this->http->get('cgi-bin/menu/delete', [
            'access_token' => $this->tokens->token($config),
        ]);
    }
}
