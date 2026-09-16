<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Service;

use OCI\Identity\Service\EndpointrClient;
use OCI\Identity\Service\MailerService;
use OCI\Identity\Service\ResendApiClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class MailerServiceTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $originalEnv = [];

    protected function setUp(): void
    {
        foreach (['MAIL_TRANSPORT', 'MAIL_TIMEOUT', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME', 'RESEND_API_KEY'] as $name) {
            $this->originalEnv[$name] = $_ENV[$name] ?? false;
            unset($_ENV[$name]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnv as $name => $value) {
            if ($value === false) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $value;
            }
        }
    }

    public function testResendTransportBuildsTheExpectedMessage(): void
    {
        $_ENV['MAIL_TRANSPORT'] = 'resend';
        $_ENV['MAIL_TIMEOUT'] = '12';
        $_ENV['MAIL_FROM_ADDRESS'] = 'conzent@example.com';
        $_ENV['MAIL_FROM_NAME'] = 'Conzent';
        $_ENV['RESEND_API_KEY'] = 're_secret';

        $sent = null;
        $client = new ResendApiClient(static function (string $url, array $headers, string $body, int $connectTimeout, int $timeout) use (&$sent): array {
            $sent = compact('url', 'headers', 'body', 'connectTimeout', 'timeout');

            return [200, '{"id":"email-123"}', ''];
        });
        $logger = new CapturingLogger();
        $mailer = new MailerService($logger, new EndpointrClient($logger, ''), $client);

        self::assertTrue($mailer->send('person@example.net', 'Verify', '<strong>123456</strong>'));
        $payload = json_decode($sent['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Conzent <conzent@example.com>', $payload['from']);
        self::assertSame(['person@example.net'], $payload['to']);
        self::assertSame('Verify', $payload['subject']);
        self::assertSame('123456', $payload['text']);
        self::assertSame('conzent@example.com', $payload['reply_to']);
        self::assertSame(12, $sent['timeout']);
    }

    public function testMissingResendKeyFailsWithoutMakingARequest(): void
    {
        $_ENV['MAIL_TRANSPORT'] = 'resend';
        $called = false;
        $client = new ResendApiClient(static function () use (&$called): array {
            $called = true;

            return [200, '{"id":"unexpected"}', ''];
        });
        $logger = new CapturingLogger();
        $mailer = new MailerService($logger, new EndpointrClient($logger, ''), $client);

        self::assertFalse($mailer->send('person@example.net', 'Verify', '<p>Code</p>'));
        self::assertFalse($called);
        self::assertStringContainsString('RESEND_API_KEY is not configured', $logger->records[0]['message']);
    }

    public function testApiFailureDoesNotLeakTheApiKeyIntoLogs(): void
    {
        $_ENV['MAIL_TRANSPORT'] = 'resend';
        $_ENV['RESEND_API_KEY'] = 're_must_not_appear';
        $client = new ResendApiClient(static fn (): array => [500, '{"message":"failed"}', '']);
        $logger = new CapturingLogger();
        $mailer = new MailerService($logger, new EndpointrClient($logger, ''), $client);

        self::assertFalse($mailer->send('person@example.net', 'Verify', '<p>Code</p>'));
        self::assertStringNotContainsString('re_must_not_appear', json_encode($logger->records, JSON_THROW_ON_ERROR));
    }
}

final class CapturingLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<string, mixed>}> */
    public array $records = [];

    /** @param array<string, mixed> $context */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }
}
