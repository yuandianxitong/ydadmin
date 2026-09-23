<?php

declare(strict_types=1);

namespace app\service\diy;

use app\repository\diy\DiyLinkRepository;
use core\base\Service;
use core\exception\NotFoundException;
use core\exception\ValidationException;
use DI\Attribute\Inject;

/**
 * 装修链接库 CRUD。
 *
 * 只调 Repository。找不到 → NotFoundException。
 */
class DiyLinkService extends Service
{
    /** @var list<string> */
    private const WRITE_FIELDS = ['label', 'path', 'category', 'icon', 'sort', 'status'];

    #[Inject]
    protected DiyLinkRepository $diyLinkRepository;

    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        return $this->diyLinkRepository->listAll();
    }

    /**
     * @param array<string, mixed> $data 控制器 validate() 的返回值（字段白名单）
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        return $this->diyLinkRepository->create($this->payload($data, true));
    }

    /**
     * @param array<string, mixed> $data 控制器 validate() 的返回值（字段白名单）
     */
    public function update(int $id, array $data): void
    {
        $this->findOrFail($id);
        $patch = $this->payload($data, false);
        if ($patch === []) {
            return;
        }

        $this->diyLinkRepository->update($id, $patch);
    }

    public function delete(int $id): void
    {
        $this->findOrFail($id);
        $this->diyLinkRepository->delete($id);
    }

    /** @return array<string, mixed> */
    private function findOrFail(int $id): array
    {
        return $this->diyLinkRepository->find($id) ?? throw new NotFoundException();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function payload(array $data, bool $creating): array
    {
        $row = array_intersect_key($data, array_flip(self::WRITE_FIELDS));

        if ($creating || array_key_exists('label', $row)) {
            // 数组值会触发 Array to string conversion（webman 转成 ErrorException → 500）
            $label = is_scalar($row['label'] ?? '') ? trim((string) ($row['label'] ?? '')) : '';
            if ($label === '') {
                throw new ValidationException(['label' => lang('messages.invalid_params')]);
            }
            $row['label'] = $label;
        }
        if ($creating || array_key_exists('path', $row)) {
            $path = is_scalar($row['path'] ?? '') ? trim((string) ($row['path'] ?? '')) : '';
            // 站内路径要以单个 / 开头（`//evil.com` 是协议相对的站外地址），站外只认 http(s)：
            // 否则 javascript: 这类值会被当成站内链接存下来并展示给管理员。
            if ($path === ''
                || (!str_starts_with($path, 'http://') && !str_starts_with($path, 'https://')
                    && (!str_starts_with($path, '/') || str_starts_with($path, '//')))
            ) {
                throw new ValidationException(['path' => lang('messages.invalid_params')]);
            }
            $row['path'] = $path;
        }

        if ($creating && !array_key_exists('category', $row)) {
            $row['category'] = '我的链接';
        } elseif (array_key_exists('category', $row)) {
            $row['category'] = (string) ($row['category'] ?: '我的链接');
        }

        if (array_key_exists('sort', $row)) {
            $row['sort'] = (int) ($row['sort'] ?? 0);
        } elseif ($creating) {
            $row['sort'] = 0;
        }

        if (array_key_exists('status', $row)) {
            $status = (int) $row['status'];
            if ($status !== 0 && $status !== 1) {
                throw new ValidationException(['status' => lang('messages.invalid_params')]);
            }
            $row['status'] = $status;
        } elseif ($creating) {
            $row['status'] = 1;
        }

        return $row;
    }
}
