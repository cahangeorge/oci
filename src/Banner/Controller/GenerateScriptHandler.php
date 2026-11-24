<?php

declare(strict_types=1);

namespace OCI\Banner\Controller;

use OCI\Banner\Service\ScriptGenerationService;
use OCI\Http\Handler\RequestHandlerInterface;
use OCI\Http\Response\ApiResponse;
use OCI\Site\Repository\SiteRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /app/sites/{id}/generate-script — Regenerate the consent script for a site.
 */
final class GenerateScriptHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly ScriptGenerationService $scriptService,
        private readonly SiteRepositoryInterface $siteRepo,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute('user');
        if ($user === null) {
            return ApiResponse::error('Unauthorized', 401);
        }

        $siteId = (int) $request->getAttribute('id', 0);
        if ($siteId <= 0) {
            return ApiResponse::error('Invalid site ID', 400);
        }

        if (!$this->siteRepo->belongsToUser($siteId, (int) $user['id'])) {
            return ApiResponse::error('Site not found', 404);
        }

        if ($this->scriptService->generate($siteId)) {
            return ApiResponse::success(['message' => 'Script generated successfully']);
        }

        return ApiResponse::error('Script generation failed', 500);
    }
}
