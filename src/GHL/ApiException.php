<?php

declare(strict_types=1);

namespace BricksGhlConnector\GHL;

use RuntimeException;

final class ApiException extends RuntimeException
{
    private int $statusCode;

    /** @var array<string, mixed> */
    private array $responseBody;

    /** @param array<string, mixed> $responseBody */
    public function __construct(string $message, int $statusCode = 0, array $responseBody = [])
    {
        parent::__construct($message, $statusCode);

        $this->statusCode = $statusCode;
        $this->responseBody = $responseBody;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /** @return array<string, mixed> */
    public function responseBody(): array
    {
        return $this->responseBody;
    }

    public function isDuplicateContactError(): bool
    {
        $message = strtolower((string) ($this->responseBody['message'] ?? $this->getMessage()));
        $matchingField = (string) ($this->responseBody['meta']['matchingField'] ?? '');

        return strpos($message, 'duplicated contacts') !== false
            || strpos($message, 'duplicate') !== false
            || in_array($matchingField, ['email', 'phone'], true);
    }

    public function duplicateMatchingField(): string
    {
        return (string) ($this->responseBody['meta']['matchingField'] ?? '');
    }
}
