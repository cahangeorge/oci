<?php

declare(strict_types=1);

namespace Tests\Unit\Cookie\Controller;

use Nyholm\Psr7\ServerRequest;
use OCI\Cookie\Controller\CategoryDeleteHandler;
use OCI\Cookie\Controller\CategoryUpdateHandler;
use OCI\Cookie\Controller\CookieDeleteHandler;
use OCI\Cookie\Controller\CookieUpdateHandler;
use OCI\Cookie\Repository\CookieCategoryRepositoryInterface;
use OCI\Cookie\Repository\CookieRepositoryInterface;
use OCI\Cookie\Service\CookieService;
use OCI\Site\Repository\LanguageRepositoryInterface;
use OCI\Site\Repository\SiteRepositoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RouteIdHandlersTest extends TestCase
{
    public static function handlers(): array
    {
        return [
            [CategoryUpdateHandler::class, true],
            [CategoryDeleteHandler::class, true],
            [CookieUpdateHandler::class, false],
            [CookieDeleteHandler::class, false],
        ];
    }

    #[DataProvider('handlers')]
    public function testDirectRouterIdReachesTheOwnershipCheck(string $handlerClass, bool $category): void
    {
        $cookies = $this->createMock(CookieRepositoryInterface::class);
        $categories = $this->createMock(CookieCategoryRepositoryInterface::class);
        $sites = $this->createMock(SiteRepositoryInterface::class);
        $languages = $this->createMock(LanguageRepositoryInterface::class);
        $repository = $category ? $categories : $cookies;
        $method = $category ? 'findSiteCategory' : 'findById';
        $repository->expects(self::once())->method($method)->with(91)
            ->willReturn(['id' => 91, 'site_id' => 5, 'slug' => 'analytics']);
        // A foreign site must still fail after resolving the correct route ID.
        $sites->expects(self::once())->method('findById')->with(5)->willReturn(['user_id' => 999]);
        $handler = new $handlerClass(new CookieService($cookies, $categories, $sites, $languages));
        $request = (new ServerRequest('PUT', '/unused'))
            ->withAttribute('user', ['id' => 42])->withAttribute('id', 91);
        $result = json_decode((string) $handler->handle($request)->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertStringContainsString('not found', json_encode($result, JSON_THROW_ON_ERROR));
    }

    #[DataProvider('handlers')]
    public function testOwnerCanMutateTheResourceUsingTheDirectRouteId(string $handlerClass, bool $category): void
    {
        $cookies = $this->createMock(CookieRepositoryInterface::class);
        $categories = $this->createMock(CookieCategoryRepositoryInterface::class);
        $sites = $this->createMock(SiteRepositoryInterface::class);
        $languages = $this->createMock(LanguageRepositoryInterface::class);
        $repository = $category ? $categories : $cookies;
        $repository->expects(self::once())->method($category ? 'findSiteCategory' : 'findById')
            ->with(91)->willReturn(['id' => 91, 'site_id' => 5, 'slug' => 'analytics']);
        $sites->expects(self::once())->method('findById')->with(5)->willReturn(['user_id' => 42]);
        $updating = str_contains($handlerClass, 'Update');
        $method = $category
            ? ($updating ? 'updateSiteCategory' : 'deleteSiteCategory')
            : ($updating ? 'update' : 'delete');
        $mutation = $repository->expects(self::once())->method($method);
        if ($updating) {
            $mutation->with(91, ['sort_order' => 2]);
        } else {
            $mutation->with(91);
        }
        $request = (new ServerRequest($updating ? 'PUT' : 'DELETE', '/unused', [], '{"sort_order":2}'))
            ->withAttribute('user', ['id' => 42])->withAttribute('id', 91);
        $response = (new $handlerClass(new CookieService($cookies, $categories, $sites, $languages)))->handle($request);
        $result = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($result['success']);
    }

    #[DataProvider('handlers')]
    public function testUnauthenticatedRequestsAreRejected(string $handlerClass, bool $category): void
    {
        $service = new CookieService(
            $this->createMock(CookieRepositoryInterface::class),
            $this->createMock(CookieCategoryRepositoryInterface::class),
            $this->createMock(SiteRepositoryInterface::class),
            $this->createMock(LanguageRepositoryInterface::class),
        );
        $response = (new $handlerClass($service))->handle((new ServerRequest('PUT', '/unused'))->withAttribute('id', 91));

        self::assertSame(401, $response->getStatusCode());
    }
}
