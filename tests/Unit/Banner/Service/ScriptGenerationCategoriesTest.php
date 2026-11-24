<?php

declare(strict_types=1);

namespace Tests\Unit\Banner\Service;

use OCI\Banner\Service\ScriptGenerationService;
use PHPUnit\Framework\TestCase;

final class ScriptGenerationCategoriesTest extends TestCase
{
    public function testSiteAssignmentsExcludeGlobalMarketingFromPreferenceCenter(): void
    {
        $service = (new \ReflectionClass(ScriptGenerationService::class))->newInstanceWithoutConstructor();
        $siteCategories = [
            ['slug' => 'necessary'],
            ['slug' => 'analytics'],
        ];
        $selected = $this->selectCategories(
            $service,
            $siteCategories,
            static fn (): array => [
                ['slug' => 'necessary'],
                ['slug' => 'analytics'],
                ['slug' => 'marketing'],
            ],
        );

        self::assertSame($siteCategories, $selected);
        $html = (new \ReflectionMethod($service, 'buildCategoryHtml'))->invoke(
            $service,
            array_map(static fn (array $category): array => ['value' => $category['slug']], $selected),
        );
        self::assertStringContainsString('data-cookie-category="necessary"', $html);
        self::assertStringContainsString('data-cookie-category="analytics"', $html);
        self::assertStringNotContainsString('data-cookie-category="marketing"', $html);
    }

    public function testDefaultsRemainAvailableForSitesWithoutAssignments(): void
    {
        $service = (new \ReflectionClass(ScriptGenerationService::class))->newInstanceWithoutConstructor();
        $defaults = [['slug' => 'necessary']];

        self::assertSame(
            $defaults,
            $this->selectCategories($service, [], static fn (): array => $defaults),
        );
    }

    private function selectCategories(ScriptGenerationService $service, array $siteCategories, callable $loadDefaults): array
    {
        return (new \ReflectionMethod($service, 'selectBannerCategories'))->invoke(
            $service,
            $siteCategories,
            $loadDefaults,
        );
    }
}
