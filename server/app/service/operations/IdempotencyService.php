<?php

declare(strict_types=1);

namespace app\service\operations;

use app\repository\operations\IdempotencyRepository;
use core\base\Service;
use core\exception\BusinessException;
use DI\Attribute\Inject;
use Webman\Http\Response;

final class RejectedIdempotentResponse extends \RuntimeException
{
    public function __construct(public readonly Response $response)
    {
        parent::__construct('Rejected idempotent operation');
    }
}

final class IdempotencyService extends Service
{
    #[Inject]
    protected IdempotencyRepository $repository;

    /**
     * @param \Closure(): Response $execute */
    public function execute(int $actor, string $operation, string $key, string $fingerprint, \Closure $execute): Response
    {
        if ($actor <= 0 || preg_match('/^[A-Za-z0-9._:-]{8,128}\z/', $key) !== 1) {
            throw new \core\exception\ValidationException(['idempotency_key' => lang('operations.invalid_idempotency_key')]);
        }
        try {
            return $this->runInTransaction(function () use ($actor, $operation, $key, $fingerprint, $execute): Response {
                $record = $this->repository->reserve(['actor_id' => $actor, 'operation' => hash('sha256', $operation), 'key_hash' => hash('sha256', $key)], $fingerprint);
                if ((int) $record['expires_at'] < time()) {
                    $this->repository->update((int) $record['id'], ['fingerprint' => $fingerprint, 'response_body' => null, 'expires_at' => time() + 86400]);
                    $record['fingerprint'] = $fingerprint;
                    $record['response_body'] = null;
                }
                if (!hash_equals((string) $record['fingerprint'], $fingerprint)) {
                    throw new BusinessException(lang('operations.idempotency_conflict'), 409);
                }
                if ($record['response_body'] !== null) {
                    return new Response((int) $record['http_status'], ['Content-Type' => 'application/json', 'Idempotency-Replayed' => 'true'], (string) $record['response_body']);
                }
                $response = $execute();
                $body = (string) $response->rawBody();
                $decoded = json_decode($body, true);
                if ($response->getStatusCode() !== 200 || !is_array($decoded) || ($decoded['code'] ?? 0) !== 200) {
                    throw new RejectedIdempotentResponse($response);
                }
                if (strlen($body) > 1024 * 1024) {
                    throw new BusinessException(lang('operations.response_too_large'));
                }
                $this->repository->update((int) $record['id'], ['response_body' => $body, 'http_status' => $response->getStatusCode()]);

                return $response;
            });
        } catch (RejectedIdempotentResponse $rejected) {
            return $rejected->response;
        }
    }
}
