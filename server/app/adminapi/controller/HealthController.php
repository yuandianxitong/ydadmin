<?php

declare(strict_types=1);

namespace app\adminapi\controller;

use core\base\Controller;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use support\Response;
use Webman\Http\Request;

#[RouteGroup('/adminapi')]
class HealthController extends Controller
{
    #[Get('/health')]
    public function index(Request $request): Response
    {
        return $this->success([
            'status'  => 'ok',
            'version' => (string) config('version.version'),
        ]);
    }
}
