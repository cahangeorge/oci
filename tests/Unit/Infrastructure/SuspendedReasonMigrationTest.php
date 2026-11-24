<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use OCI\Database\Migrations\Version20261020_001_SuspendedReasonEverywhere;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/migrations/versions/Version20261020_001_SuspendedReasonEverywhere.php';

final class SuspendedReasonMigrationTest extends TestCase
{
    public function testUpgradeAddsTheColumnWithoutOverwritingExistingInstallations(): void
    {
        $db = $this->createMock(Connection::class);
        $db->expects(self::once())->method('executeStatement')->with(self::callback(
            static fn (string $sql): bool => str_contains($sql, 'ALTER TABLE `oci_sites`')
                && str_contains($sql, 'ADD COLUMN IF NOT EXISTS `suspended_reason` VARCHAR(40) NULL DEFAULT NULL'),
        ))->willReturn(0);

        (new Version20261020_001_SuspendedReasonEverywhere($db))->up();
    }

    public function testRollbackDoesNotDropTheCloudOwnedColumn(): void
    {
        $db = $this->createMock(Connection::class);
        $db->expects(self::never())->method('executeStatement');

        (new Version20261020_001_SuspendedReasonEverywhere($db))->down();
    }
}
