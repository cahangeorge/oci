<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Repository;

use OCI\Identity\Repository\UserRepositoryInterface;
use OCI\Shared\Repository\NullPlanRepository;
use PHPUnit\Framework\TestCase;

final class NullPlanRepositoryTest extends TestCase
{
    public function testSelfHostedCompanyLookupUsesTheRequestedUser(): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $company = ['company_name' => 'Example Company'];
        $users->expects(self::once())->method('getUserCompany')->with(42)->willReturn($company);

        self::assertSame($company, (new NullPlanRepository($users))->getUserCompany(42));
    }

    public function testMissingCompanyRemainsNullAndPlanAccessStaysUnlocked(): void
    {
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::once())->method('getUserCompany')->with(7)->willReturn(null);
        $plans = new NullPlanRepository($users);

        self::assertNull($plans->getUserCompany(7));
        self::assertTrue($plans->isSubscribed(7));
        self::assertTrue($plans->isEnterprise(7));
        self::assertSame('oci', $plans->getPriceModel(7));
    }
}
