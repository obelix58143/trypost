<?php

declare(strict_types=1);

namespace App\Support\Requests\Post;

use App\Support\PostApproval;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Single source of the approve rules shared by the web and API FormRequests
 * and the MCP approve tool. Rejecting takes no input.
 */
class ApprovalRequestRules
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function approve(): array
    {
        return PostApproval::rules();
    }
}
