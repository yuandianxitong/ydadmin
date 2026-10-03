<?php

declare(strict_types=1);

namespace app\adminapi\controller\upload;

use app\adminapi\controller\AuthenticatedController;
use app\service\system\UploadService;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use support\Response;
use Webman\Http\Request;

/**
 * 上传（契约 §2.9.2、spec §6.5）。
 *
 * 端点：
 *   POST /adminapi/upload/image  image  PermissionSkip
 *   POST /adminapi/upload/file   file   PermissionSkip
 *
 * 两个端点都是「已登录即可」的公共能力，没有独立权限位：素材库、编辑器插图、配置项里的
 * logo/favicon、管理员头像等入口共用。#[PermissionSkip] 是显式声明，不是遗漏——
 * AdminPermissionMiddleware 对无注解的方法默认拒绝。
 *
 * 校验（MIME / 扩展名 / 大小）与落盘全在 UploadService 里，失败抛 BusinessException，
 * 由全局异常处理器转成 HTTP 200 + code 400，与 TP8 的 $this->error() 同形。
 */
#[RouteGroup('/adminapi/upload')]
class UploadController extends AuthenticatedController
{
    #[Inject]
    protected UploadService $uploadService;

    #[Post('/image')]
    #[PermissionSkip]
    public function image(Request $request): Response
    {
        return $this->success($this->uploadService->uploadImage($request->file('file')), lang('messages.upload_success'));
    }

    #[Post('/file')]
    #[PermissionSkip]
    public function file(Request $request): Response
    {
        return $this->success($this->uploadService->uploadFile($request->file('file')), lang('messages.upload_success'));
    }
}
