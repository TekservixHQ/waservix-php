<?php

declare(strict_types=1);

namespace Tekservix\WAservix;

/** Thrown when the WAservix API answers with an error. */
class WAservixException extends \RuntimeException
{
    /** @var array<string, string[]>|null */
    public ?array $errors;
    /** @var mixed */
    public $body;
    /** Seconds to wait, sent with HTTP 429. A 503 means the device is reconnecting. */
    public ?float $retryAfter;
    public int $status;

    public function __construct(string $message, int $status = 0, ?array $errors = null, $body = null, ?float $retryAfter = null)
    {
        parent::__construct($message, $status);
        $this->status = $status;
        $this->errors = $errors;
        $this->body = $body;
        $this->retryAfter = $retryAfter;
    }
}
