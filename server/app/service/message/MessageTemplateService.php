<?php

declare(strict_types=1);

namespace app\service\message;

use app\repository\message\MessageTemplateRepository;
use core\base\Service;
use core\exception\BusinessException;
use core\exception\NotFoundException;
use core\exception\ValidationException;
use DI\Attribute\Inject;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * 管理端消息模板（M6b spec §4.1）。全局运营数据，不受数据权限约束。
 *
 * 字段白名单：只写表单字段（name、code 仅新增、remark、status、三个外发通道的开关 / 模板 id / 预览或跳转）。
 * wechat_official_data、wechat_mini_data、site_*、variables 管理端界面编辑不了，这里一律不写：
 * 编辑弹窗把整行回传时，这些列也不会被覆盖。控制器规则里本来就没有它们，这里再按固定列名挑一遍，两道保险。
 *
 * code：新增时先查重（含软删行，唯一索引也含软删行），重复 → 422 errors.code；并发插入撞唯一索引时转成同一个 422，
 * 不返回 500。编辑时忽略 code。
 *
 * 容器单例，无实例态。
 */
class MessageTemplateService extends Service
{
    /** 表单里的 0/1 开关列。 */
    private const SWITCH_COLUMNS = ['status', 'sms_enabled', 'wechat_official_enabled', 'wechat_mini_enabled'];

    /** 表单里的文本列（表结构 NOT NULL DEFAULT ''：null 按空串写）。 */
    private const TEXT_COLUMNS = [
        'sms_template_id', 'sms_content',
        'wechat_official_template_id', 'wechat_official_url',
        'wechat_mini_template_id', 'wechat_mini_page',
    ];

    #[Inject]
    protected MessageTemplateRepository $messageTemplateRepository;

    /**
     * @param array<string, mixed> $params 控制器 validate() 的返回值：keyword、status
     * @return array{list: array<int, array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function getList(array $params, int $page, int $limit): array
    {
        return $this->messageTemplateRepository->getAdminList(self::filters($params), $page, $limit);
    }

    /** @return array<string, mixed> */
    public function getDetail(int $id): array
    {
        return $this->findOrFail($id);
    }

    /** @param array<string, mixed> $data 已校验：name、code 必有，其余可选 */
    public function create(array $data): void
    {
        $code = (string) $data['code'];
        if ($this->messageTemplateRepository->codeExists($code)) {
            throw self::codeTaken();
        }

        $row = array_merge([
            'code'                        => $code,
            'remark'                      => null,
            'status'                      => MessageTemplateRepository::STATUS_ENABLED,
            'sms_enabled'                 => 0,
            'sms_template_id'             => '',
            'sms_content'                 => '',
            'wechat_official_enabled'     => 0,
            'wechat_official_template_id' => '',
            'wechat_official_url'         => '',
            'wechat_mini_enabled'         => 0,
            'wechat_mini_template_id'     => '',
            'wechat_mini_page'            => '',
        ], $this->formColumns($data));

        try {
            $this->messageTemplateRepository->create($row);
        } catch (UniqueConstraintViolationException) {
            throw self::codeTaken();
        }
    }

    /** @param array<string, mixed> $data 已校验，字段均可选（部分更新）；code 不在其中 */
    public function update(int $id, array $data): void
    {
        $this->findOrFail($id);

        $row = $this->formColumns($data);
        if ($row === []) {
            return;
        }

        $this->messageTemplateRepository->update($id, $row);
    }

    /** 软删；内置模板（MessageTemplateRepository::BUILTIN_CODES）不可删除。 */
    public function delete(int $id): void
    {
        $template = $this->findOrFail($id);
        if (in_array((string) $template['code'], MessageTemplateRepository::BUILTIN_CODES, true)) {
            throw new BusinessException(lang('message.builtin_template_undeletable'));
        }

        if (!$this->messageTemplateRepository->delete($id)) {
            throw new NotFoundException();
        }
    }

    /** @return array<string, mixed> 未软删的模板行；不存在抛 NotFoundException（code 404） */
    private function findOrFail(int $id): array
    {
        return $this->messageTemplateRepository->find($id) ?? throw new NotFoundException();
    }

    /**
     * 从已校验数据里按固定列名挑出表单字段（不含 code）。
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function formColumns(array $data): array
    {
        $row = [];
        if (array_key_exists('name', $data)) {
            $row['name'] = (string) $data['name'];
        }
        if (array_key_exists('remark', $data)) {
            $row['remark'] = $data['remark'] === null ? null : (string) $data['remark'];
        }
        foreach (self::SWITCH_COLUMNS as $column) {
            if (array_key_exists($column, $data)) {
                $row[$column] = (int) $data[$column];
            }
        }
        foreach (self::TEXT_COLUMNS as $column) {
            if (array_key_exists($column, $data)) {
                $row[$column] = (string) ($data[$column] ?? '');
            }
        }

        return $row;
    }

    /**
     * 去掉「没填」的筛选项（null、空串）；'0' 保留（status=0 是有效筛选）。
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private static function filters(array $params): array
    {
        return array_filter($params, static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private static function codeTaken(): ValidationException
    {
        return new ValidationException(['code' => lang('message.template_code_exists')]);
    }
}
