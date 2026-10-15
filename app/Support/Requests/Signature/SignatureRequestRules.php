<?php

declare(strict_types=1);

namespace App\Support\Requests\Signature;

/**
 * Single source of the signature rules shared by the web and API FormRequests
 * and the MCP create and update tools.
 */
class SignatureRequestRules
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ];
    }
}
