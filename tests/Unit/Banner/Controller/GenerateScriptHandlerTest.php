<?php

declare(strict_types=1);

namespace Tests\Unit\Banner\Controller;

use Nyholm\Psr7\ServerRequest;
use OCI\Banner\Controller\GenerateScriptHandler;
use OCI\Banner\Service\ScriptGenerationService;
use OCI\Site\Repository\SiteRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class GenerateScriptHandlerTest extends TestCase
{
    public function testSessionDataAloneCannotAuthorizeGeneration(): void
    {
        $sites = $this->createMock(SiteRepositoryInterface::class);
        $sites->expects(self::never())->method('belongsToUser');
        $response = $this->handler($sites)->handle((new ServerRequest('POST', '/unused'))
            ->withAttribute('session', ['user_id' => 42])->withAttribute('id', 91));

        self::assertSame(401, $response->getStatusCode());
    }

    public function testForeignSiteIsRejectedBeforeGeneratingFiles(): void
    {
        $sites = $this->createMock(SiteRepositoryInterface::class);
        $sites->expects(self::once())->method('belongsToUser')->with(91, 42)->willReturn(false);
        $response = $this->handler($sites)->handle((new ServerRequest('POST', '/unused'))
            ->withAttribute('user', ['id' => 42])->withAttribute('id', 91));

        self::assertSame(404, $response->getStatusCode());
    }

    public function testInvalidSiteIsRejectedBeforeOwnershipLookup(): void
    {
        $sites = $this->createMock(SiteRepositoryInterface::class);
        $sites->expects(self::never())->method('belongsToUser');
        $response = $this->handler($sites)->handle((new ServerRequest('POST', '/unused'))
            ->withAttribute('user', ['id' => 42])->withAttribute('id', -1));

        self::assertSame(400, $response->getStatusCode());
    }

    private function handler(SiteRepositoryInterface $sites): GenerateScriptHandler
    {
        // A real final service with uninitialized dependencies fails if a rejected
        // request accidentally reaches generation; no database/filesystem is used.
        $service = (new \ReflectionClass(ScriptGenerationService::class))->newInstanceWithoutConstructor();

        return new GenerateScriptHandler($service, $sites);
    }
}
