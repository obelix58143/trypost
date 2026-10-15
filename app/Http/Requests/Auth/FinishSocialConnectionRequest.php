<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Support\Social\PendingConnection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinishSocialConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only identities the network offered for this connection may be picked,
     * and none already connected in the workspace unless it is being reconnected.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $pending = PendingConnection::current();

        return [
            'identities' => ['required', 'array', 'min:1'],
            'identities.*' => [
                'required',
                'string',
                'distinct',
                Rule::in($pending?->identityKeys() ?? []),
                Rule::notIn($pending?->lockedIdentityKeys() ?? []),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'identities.*.not_in' => __('accounts.connect.errors.identity_connected'),
        ];
    }
}
