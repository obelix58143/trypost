<?php

declare(strict_types=1);

namespace App\Support\Requests\Label;

/**
 * Single source of the label rules shared by the web and API FormRequests and
 * the MCP create and update tools.
 */
class LabelRequestRules
{
    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'max:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }
}
