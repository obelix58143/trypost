<?php

declare(strict_types=1);

namespace App\Support\Auth;

use Illuminate\Validation\Rules\Password;

final class PasswordRequirements
{
    public static function rule(): Password
    {
        return Password::min(12)
            ->mixedCase()
            ->letters()
            ->numbers()
            ->symbols()
            ->uncompromised();
    }

    /**
     * @return list<array{key: string, value?: int}>
     */
    public static function checklist(): array
    {
        $applied = Password::default()->appliedRules();

        return array_values(array_filter([
            ['key' => 'min', 'value' => (int) data_get($applied, 'min')],
            data_get($applied, 'mixedCase') ? ['key' => 'mixed_case'] : null,
            data_get($applied, 'numbers') ? ['key' => 'number'] : null,
            data_get($applied, 'symbols') ? ['key' => 'symbol'] : null,
        ]));
    }
}
