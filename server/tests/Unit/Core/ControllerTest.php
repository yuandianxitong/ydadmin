<?php

declare(strict_types=1);

namespace tests\Unit\Core;

use core\base\Controller;
use core\exception\ValidationException;
use tests\TestCase;
use Webman\Http\Request;

final class ProbeController extends Controller
{
    /** @return array{0: int, 1: int} */
    public function page(Request $request): array
    {
        return $this->pageParams($request);
    }

    /** @return array<string, mixed> */
    public function check(array $data): array
    {
        return $this->validate($data, ['name' => 'required']);
    }
}

final class ControllerTest extends TestCase
{
    private function get(string $query): Request
    {
        return new Request("GET /adminapi/x?{$query} HTTP/1.1\r\nHost: localhost\r\n\r\n");
    }

    public function test_page_params_accept_page_and_limit(): void
    {
        $this->assertSame([3, 20], (new ProbeController())->page($this->get('page=3&limit=20')));
    }

    public function test_page_params_accept_page_no_and_page_size(): void
    {
        $this->assertSame([2, 50], (new ProbeController())->page($this->get('page_no=2&page_size=50')));
    }

    public function test_page_params_defaults_and_lower_bounds(): void
    {
        $this->assertSame([1, 15], (new ProbeController())->page($this->get('')));
        $this->assertSame([1, 1], (new ProbeController())->page($this->get('page=-1&limit=0')));
    }

    public function test_validate_delegates_to_validator_factory(): void
    {
        $this->assertSame(['name' => 'a'], (new ProbeController())->check(['name' => 'a', 'x' => 1]));
        $this->expectException(ValidationException::class);
        (new ProbeController())->check([]);
    }
}
