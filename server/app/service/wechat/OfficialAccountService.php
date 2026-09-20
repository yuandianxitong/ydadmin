<?php

declare(strict_types=1);

namespace app\service\wechat;

use core\exception\BusinessException;
use core\exception\ValidationException;
use core\wechat\exception\WechatApiException;
use core\wechat\exception\WechatNotConfiguredException;
use core\wechat\exception\WechatUnavailableException;
use core\wechat\OfficialAccountApi;
use support\Log;

final class OfficialAccountService
{
    public function __construct(private readonly OfficialAccountApi $api)
    {
    }

    /** @return array<string, mixed> */
    public function getMenu(): array
    {
        return $this->wechat(fn (): array => $this->api->getCurrentSelfMenu());
    }

    /** @param array<mixed> $button */
    public function createMenu(array $button): void
    {
        $clean = $this->validateButtons($button);
        $this->wechat(fn (): array => $this->api->createMenu($clean));
    }

    public function deleteMenu(): void
    {
        $this->wechat(fn (): array => $this->api->deleteMenu());
    }

    /**
     * @param array<mixed> $button
     * @return list<array<string, mixed>>
     */
    private function validateButtons(array $button): array
    {
        if (!array_is_list($button) || count($button) < 1 || count($button) > 3) {
            $this->invalidMenu();
        }

        $clean = [];
        foreach ($button as $item) {
            if (!is_array($item)) {
                $this->invalidMenu();
            }
            $clean[] = $this->validateItem($item);
        }

        return $clean;
    }

    /**
     * @param array<mixed> $item
     * @return array<string, mixed>
     */
    private function validateItem(array $item): array
    {
        $name = $item['name'] ?? null;
        if (!$this->validString($name, 16)) {
            $this->invalidMenu();
        }

        if (array_key_exists('sub_button', $item)) {
            $children = $item['sub_button'];
            if (!is_array($children) || !array_is_list($children) || count($children) > 5) {
                $this->invalidMenu();
            }
            if ($children !== []) {
                $cleanChildren = [];
                foreach ($children as $child) {
                    if (!is_array($child)) {
                        $this->invalidMenu();
                    }
                    $cleanChildren[] = $this->validateLeaf($child);
                }

                return ['name' => $name, 'sub_button' => $cleanChildren];
            }
        }

        return $this->validateLeaf($item);
    }

    /**
     * @param array<mixed> $item
     * @return array<string, string>
     */
    private function validateLeaf(array $item): array
    {
        $name = $item['name'] ?? null;
        $type = $item['type'] ?? null;
        if (!$this->validString($name, 16) || !is_string($type) || !in_array($type, ['view', 'click', 'miniprogram'], true)) {
            $this->invalidMenu();
        }

        $clean = ['name' => $name, 'type' => $type];
        if ($type === 'view') {
            $clean['url'] = $this->requiredString($item['url'] ?? null, 500);
        } elseif ($type === 'click') {
            $clean['key'] = $this->requiredString($item['key'] ?? null, 128);
        } else {
            $clean['appid'] = $this->requiredString($item['appid'] ?? null, 32);
            $clean['pagepath'] = $this->requiredString($item['pagepath'] ?? null, 200);
            $clean['url'] = $this->requiredString($item['url'] ?? null, 500);
        }

        return $clean;
    }

    private function requiredString(mixed $value, int $max): string
    {
        if (!$this->validString($value, $max)) {
            $this->invalidMenu();
        }

        return $value;
    }

    private function validString(mixed $value, int $max): bool
    {
        return is_string($value) && $value !== '' && mb_strlen($value) <= $max;
    }

    /** @return never */
    private function invalidMenu(): never
    {
        throw new ValidationException(['button' => lang('wechat.menu_invalid')]);
    }

    /**
     * @template T
     * @param \Closure(): T $fn
     * @return T
     */
    private function wechat(\Closure $fn): mixed
    {
        try {
            return $fn();
        } catch (WechatNotConfiguredException) {
            throw new BusinessException(lang('wechat.official_not_configured'));
        } catch (WechatApiException $e) {
            Log::warning('微信公众号接口拒绝请求', [
                'api' => $e->getApi(),
                'errcode' => $e->getErrcode(),
            ]);

            throw new BusinessException(lang('wechat.api_error', ['errmsg' => $e->getErrmsg()]));
        } catch (WechatUnavailableException) {
            throw new BusinessException(lang('wechat.unavailable'));
        }
    }
}
