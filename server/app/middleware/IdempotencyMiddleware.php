<?php

declare(strict_types=1);

namespace app\middleware;

use app\service\operations\IdempotencyService;
use core\auth\PermissionCheckerInterface;
use core\context\RequestContext;
use core\exception\ForbiddenException;
use core\http\Idempotent;
use core\support\PayloadFingerprint;
use DI\Attribute\Inject;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

final class IdempotencyMiddleware implements MiddlewareInterface
{
    #[Inject]
    protected IdempotencyService $service;
    #[Inject]
    protected PermissionCheckerInterface $permissions;

    public function process(Request $request, callable $handler): Response
    {
        $key = $request->header('idempotency-key');
        if (!is_string($key) || $key === '' || !is_string($request->controller) || !is_string($request->action)) {
            return $handler($request);
        }
        $attributes = (new \ReflectionMethod($request->controller, $request->action))->getAttributes(Idempotent::class);
        if ($attributes === []) {
            return $handler($request);
        }
        $policy = $attributes[0]->newInstance();
        if ($policy->permissionField !== null) {
            $value = $request->post($policy->permissionField);
            $permission = is_string($value) ? ($policy->permissions[$value] ?? null) : null;
            if ($permission === null || !$this->permissions->check(RequestContext::actingUser(), $permission)) {
                throw new ForbiddenException(lang('auth.permission_denied'));
            }
        }
        $files = [];
        foreach ((array) $request->file() as $name => $file) {
            if ($file instanceof \Webman\Http\UploadFile && $file->isValid()) {
                $files[$name] = ['name' => $file->getUploadName(), 'sha256' => hash_file('sha256', $file->getPathname())];
            }
        }
        $fingerprint = PayloadFingerprint::hash(['path' => $request->path(), 'query' => $request->get(), 'body' => $request->post(), 'files' => $files]);

        return $this->service->execute(
            RequestContext::actingUser(),
            $request->method() . ':' . $request->controller . '::' . $request->action,
            $key,
            $fingerprint,
            static fn (): Response => $handler($request)
        );
    }
}
