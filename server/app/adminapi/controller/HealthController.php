<?php

declare(strict_types=1);

namespace app\adminapi\controller;

use core\base\Controller;
use support\Response;
use Webman\Http\Request;

class HealthController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->success([
            'status'  => 'ok',
            'version' => (string) config('version.version'),
        ]);
    }
}
