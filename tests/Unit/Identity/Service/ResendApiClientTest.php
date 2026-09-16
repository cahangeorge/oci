<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Service;

use OCI\Identity\Service\ResendApiClient;
use PHPUnit\Framework\TestCase;

final class ResendApiClientTest extends TestCase
{
    public function testItSendsJsonWithBearerAuthentication(): void
    {
        $request = null;
        $client = new ResendApiClient(static function (...$args) use (&$request): array {
            $request = $args;

            return [200, '{"id":"email-123"}', ''];
        });

        $id = $client->send('re_secret', ['from' => 'Conzent <hello@example.com>'], 15);

        self::assertSame('email-123', $id);
        self::assertSame('https://api.resend.com/emails', $request[0]);
        self::assertContains('Authorization: Bearer re_secret', $request[1]);
        self::assertSame('{"from":"Conzent <hello@example.com>"}', $request[2]);
        self::assertSame(5, $request[3]);
        self::assertSame(15, $request[4]);
    }

    public function testItRejectsNonSuccessfulResponsesWithoutIncludingTheBody(): void
    {
        $client = new ResendApiClient(static fn (): array => [422, '{"message":"contains sensitive request data"}', '']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Resend API returned HTTP 422');
        $client->send('re_secret', ['to' => ['person@example.com']], 15);
    }

    public function testItRequiresAnEmailIdInTheResponse(): void
    {
        $client = new ResendApiClient(static fn (): array => [200, '{}', '']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Resend API response missing email id');
        $client->send('re_secret', ['to' => ['person@example.com']], 15);
    }
}
