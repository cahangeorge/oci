<?php

declare(strict_types=1);

namespace Tests\Unit\Banner\Service;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class ConsentLayoutsTest extends TestCase
{
    public function testAllModernConsentLayoutsParse(): void
    {
        $root = dirname(__DIR__, 4) . '/resources/consent/layouts';
        $twig = new Environment(new FilesystemLoader($root));
        $count = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.twig')) {
                continue;
            }
            $name = substr($file->getPathname(), strlen($root) + 1);
            $twig->parse($twig->tokenize($twig->getLoader()->getSourceContext($name)));
            ++$count;
        }

        self::assertGreaterThan(0, $count);
    }

    public function testBothLanguagesRemainAvailableInAllFourRetentionLayouts(): void
    {
        $root = dirname(__DIR__, 4) . '/resources/consent/';
        foreach (['layouts/gdpr/base.html.twig', 'layouts/gdpr/hero.html.twig', 'layout/gdpr_template.html', 'layout/iab2_template.html'] as $name) {
            $markup = file_get_contents($root . $name);
            self::assertSame(1, substr_count($markup, 'class="cnz-retention-ro" lang="ro"'), $name);
            self::assertSame(1, substr_count($markup, 'class="cnz-retention-en" lang="en"'), $name);
            self::assertStringContainsString('pentru cel mult 12 luni.', $markup, $name);
            self::assertStringContainsString('for a maximum duration of 12 months.', $markup, $name);
        }
    }
}
