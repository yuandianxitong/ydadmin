<?php

declare(strict_types=1);

namespace app\api\controller;

use app\middleware\ApiAuthMiddleware;
use core\base\Controller;
use support\annotation\Middleware;

#[Middleware(ApiAuthMiddleware::class)]
abstract class AuthenticatedController extends Controller
{
}
