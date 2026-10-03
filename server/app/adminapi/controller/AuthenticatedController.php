<?php

declare(strict_types=1);

namespace app\adminapi\controller;

use app\middleware\AdminAuthMiddleware;
use app\middleware\AdminLogMiddleware;
use app\middleware\AdminPermissionMiddleware;
use core\base\Controller;
use support\annotation\Middleware;

#[Middleware(AdminAuthMiddleware::class, AdminPermissionMiddleware::class, AdminLogMiddleware::class)]
abstract class AuthenticatedController extends Controller
{
}
