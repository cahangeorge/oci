<?php

declare(strict_types=1);

namespace OCI\Identity\Service;

/**
 * Minimal client for Resend's HTTPS email API.
 *
 * The optional request closure keeps transport behavior unit-testable without
 * making real network calls. It receives URL, headers, JSON body, connect
 * timeout, and total timeout, and returns [status, body, error].
 */
final class ResendApiClient
{
    private const API_URL = 'https://api.resend.com/emails';

    /**
     * @var \Closure(string, list<string>, string, int, int): array{0: int, 1: string|false, 2: string}
     */
    private readonly \Closure $request;

    /**
     * @param (\Closure(string, list<string>, string, int, int): array{0: int, 1: string|false, 2: string})|null $request
     */
    public function __construct(?\Closure $request = null)
    {
        $this->request = $request ?? self::defaultRequest(...);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function send(string $apiKey, array $payload, int $timeout): string
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ];

        [$status, $response, $error] = ($this->request)(
            self::API_URL,
            $headers,
            $body,
            min(5, $timeout),
            $timeout,
        );

        if (!\is_string($response)) {
            throw new \RuntimeException('Resend transport failure' . ($error !== '' ? ': ' . $error : ''));
        }

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException("Resend API returned HTTP {$status}");
        }

        $decoded = json_decode($response, true);
        if (!\is_array($decoded) || !isset($decoded['id']) || trim((string) $decoded['id']) === '') {
            throw new \RuntimeException('Resend API response missing email id');
        }

        return (string) $decoded['id'];
    }

    /**
     * @param list<string> $headers
     * @return array{0: int, 1: string|false, 2: string}
     */
    private static function defaultRequest(
        string $url,
        array $headers,
        string $body,
        int $connectTimeout,
        int $timeout,
    ): array {
        if (!\function_exists('curl_init')) {
            return [0, false, 'PHP cURL extension is unavailable'];
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT => $timeout,
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [$status, \is_string($response) ? $response : false, $error];
    }
}
