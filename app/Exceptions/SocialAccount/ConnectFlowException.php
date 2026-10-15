<?php

declare(strict_types=1);

namespace App\Exceptions\SocialAccount;

use App\Enums\SocialAccount\Platform;
use App\Support\Social\PendingConnection;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

/**
 * Stops a channel connection and sends the user to the confirmation page, which
 * shows the reason. An expired or cancelled connection is a normal outcome, not
 * an incident, so this never reaches the error log.
 */
class ConnectFlowException extends RuntimeException implements ShouldntReport
{
    public const string CANCELLED = 'cancelled';

    public const string SESSION_EXPIRED = 'session_expired';

    public function __construct(
        public readonly string $messageKey,
        public readonly ?Platform $platform = null,
    ) {
        parent::__construct("Social connect aborted: {$messageKey}");
    }

    public function render(): RedirectResponse
    {
        if ($this->messageKey !== self::SESSION_EXPIRED) {
            PendingConnection::current()?->fail($this->messageKey);
        }

        return $this->platform !== null
            ? redirect()->route('app.social.connect.show', $this->platform)
            : redirect(PendingConnection::defaultReturnUrl());
    }
}
