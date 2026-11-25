<?php

declare(strict_types=1);

namespace Convaincre\Connect;

use RuntimeException;

final class ApiClient
{
    public const MANIFEST_PATH = '/api/v1/public/connect/manifest/';
    public const FORM_PATH = '/api/v1/public/connect/forms/';
    public const SUBMISSION_PATH = '/api/v1/public/connect/submissions/';

    public function manifest(): array
    {
        return $this->request('GET', self::MANIFEST_PATH, null);
    }

    public function createForm(array $payload): array
    {
        return $this->request('POST', self::FORM_PATH, $payload);
    }

    public function submit(array $payload): array
    {
        foreach (['contact', 'answers'] as $field) {
            if (
                array_key_exists($field, $payload)
                && is_array($payload[$field])
                && $payload[$field] === []
            ) {
                // An empty PHP array is serialized as [] while the Connect API
                // contract expects these two maps to be JSON objects.
                $payload[$field] = (object) [];
            }
        }
        return $this->request('POST', self::SUBMISSION_PATH, $payload);
    }

    private function request(string $method, string $path, ?array $payload): array
    {
        if (! Settings::configured()) {
            throw new ApiException('not_configured', 0, true);
        }
        $body = $payload === null
            ? ''
            : wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! is_string($body)) {
            throw new RuntimeException('json_encoding_failed');
        }
        $url = Settings::apiUrl() . $path;
        $headers = Signer::headers(
            Settings::keyId(),
            Settings::secret(),
            Settings::origin(),
            $method,
            $path,
            $body,
            CONVAINCRE_CONNECT_VERSION
        );
        $headers['Accept'] = 'application/json';
        if ($payload !== null) {
            $headers['Content-Type'] = 'application/json';
        }

        $response = wp_remote_request($url, [
            'method' => $method,
            'headers' => $headers,
            'body' => $body,
            'timeout' => 15,
            'redirection' => 0,
            'reject_unsafe_urls' => true,
            'sslverify' => true,
            'user-agent' => 'ConvaincreConnect/' . CONVAINCRE_CONNECT_VERSION . '; ' . home_url('/'),
        ]);

        if (is_wp_error($response)) {
            throw new ApiException('network_error', 0, true);
        }
        $status = (int) wp_remote_retrieve_response_code($response);
        $responseBody = (string) wp_remote_retrieve_body($response);
        $decoded = json_decode($responseBody, true);
        if ($status < 200 || $status >= 300) {
            $retryable = $status === 0 || $status === 408 || $status === 425 || $status === 429 || $status >= 500;
            // Only the allowlisted machine error code becomes the exception message.
            // The status and retryable flag are typed metadata, not rendered output.
            $exception = ApiException::fromResponse(self::safeErrorCode($status, $decoded), $status, $retryable);
            throw $exception;
        }
        if (! is_array($decoded)) {
            $exception = ApiException::fromResponse('response_invalid', $status, true);
            throw $exception;
        }
        return $decoded;
    }

    private static function safeErrorCode(int $status, mixed $decoded): string
    {
        if ($status !== 400 || ! is_array($decoded)) {
            return 'http_' . $status;
        }

        $known = [
            'binding_version' => 'form_outdated',
            'binding_key' => 'form_unknown',
            'contact' => 'contact_invalid',
            'answers' => 'answers_invalid',
            'consents' => 'consent_invalid',
            'submitted_at' => 'submission_date_invalid',
            'external_submission_id' => 'submission_id_invalid',
        ];
        foreach ($known as $field => $code) {
            if (array_key_exists($field, $decoded)) {
                return $code;
            }
        }
        return 'http_400';
    }
}

final class ApiException extends RuntimeException
{
    public readonly string $safeCode;
    public readonly int $httpStatus;
    public readonly bool $retryable;

    public function __construct(string $safeCode, int $httpStatus, bool $retryable)
    {
        $this->safeCode = sanitize_key($safeCode);
        $this->httpStatus = $httpStatus;
        $this->retryable = $retryable;
        parent::__construct(esc_html($this->safeCode));
    }

    public static function fromResponse(string $safeCode, int $httpStatus, bool $retryable): self
    {
        // Numeric status and boolean retry flag are internal metadata, never HTML output.
        return new self($safeCode, $httpStatus, $retryable);
    }
}
