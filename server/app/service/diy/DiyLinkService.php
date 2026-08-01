<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\service\diy;

use app\repository\diy\DiyLinkRepository;
use core\base\Service;

class DiyLinkService extends Service
{
    protected DiyLinkRepository $linkRepository;

    public function list(): array
    {
        return $this->linkRepository->listAll();
    }

    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        return (int) ($this->linkRepository->create([
            'label'      => (string) ($data['label'] ?? ''),
            'path'       => (string) ($data['path'] ?? ''),
            'category'   => (string) ($data['category'] ?? '我的链接'),
            'icon'       => $data['icon'] ?? null,
            'sort'       => (int) ($data['sort'] ?? 0),
            'status'     => (int) ($data['status'] ?? 1),
            'created_at' => $now,
            'updated_at' => $now,
        ])['id'] ?? 0);
    }

    public function update(int $id, array $data): void
    {
        $patch = [];
        foreach (['label', 'path', 'category', 'icon', 'sort', 'status'] as $f) {
            if (array_key_exists($f, $data)) {
                $patch[$f] = $data[$f];
            }
        }
        $patch['updated_at'] = date('Y-m-d H:i:s');
        $this->linkRepository->update($id, $patch);
    }

    public function delete(int $id): void
    {
        $this->linkRepository->delete($id);
    }
}
