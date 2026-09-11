<?php

declare(strict_types=1);

namespace core\base;

use core\response\Api;
use core\validation\ValidatorFactory;
use support\Response;
use Webman\Http\Request;

/**
 * Controller 基类。Controller 只调用 Service。
 * validate() 的返回值就是字段白名单：写库只能用它，禁止直接透传请求体。
 */
abstract class Controller
{
    protected function success(mixed $data = [], string $message = '操作成功'): Response
    {
        return Api::success($data, $message);
    }

    protected function error(string $message, int $code = 400, mixed $data = []): Response
    {
        return Api::error($message, $code, $data);
    }

    /** @param array{list: array<int, mixed>, pagination: array<string, int>} $result */
    protected function paginate(array $result): Response
    {
        return Api::paginate($result);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $rules
     * @param array<string, string> $messages
     * @param array<string, string> $attributes
     * @return array<string, mixed>
     */
    protected function validate(array $data, array $rules, array $messages = [], array $attributes = []): array
    {
        return ValidatorFactory::validate($data, $rules, $messages, $attributes);
    }

    /**
     * 分页参数：兼容 page/limit 与 page_no/page_size 两套写法（TP8 版契约）。
     *
     * @return array{0: int, 1: int} [page, limit]
     */
    protected function pageParams(Request $request, int $defaultLimit = 15): array
    {
        $page = (int) ($request->input('page') ?? $request->input('page_no') ?? 1);
        $limit = (int) ($request->input('limit') ?? $request->input('page_size') ?? $defaultLimit);

        return [max(1, $page), min(Repository::MAX_PAGE_SIZE, max(1, $limit))];
    }

    /**
     * 请求体：JSON 与表单提交都支持（Workerman 会把 JSON 请求体解析进 post()，
     * 这里再兜底解析一次原始 body）。只作为 validate() 的输入，写库必须用 validate() 的返回值。
     *
     * @return array<string, mixed>
     */
    protected function body(Request $request): array
    {
        $data = $request->post();
        if (is_array($data) && $data !== []) {
            return $data;
        }
        $json = json_decode((string) $request->rawBody(), true);

        return is_array($json) ? $json : [];
    }
}
