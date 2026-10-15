<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Post;

use App\Models\SocialAccount;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ListComposerTakenSlotsRequest extends FormRequest
{
    /**
     * The widest window the schedule picker asks for: one day in any zone.
     */
    public const MAX_RANGE_HOURS = 48;

    public function authorize(): bool
    {
        $workspace = $this->user()?->currentWorkspace;
        $account = $this->route('account');

        return $workspace !== null
            && $account instanceof SocialAccount
            && $account->workspace_id === $workspace->id
            && $this->user()->can('createPost', $workspace);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after:from'],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->date('from')->diffInHours($this->date('to')) > self::MAX_RANGE_HOURS) {
                    $validator->errors()->add('to', __('validation.before_or_equal', [
                        'attribute' => 'to',
                        'date' => $this->date('from')->addHours(self::MAX_RANGE_HOURS)->toIso8601ZuluString(),
                    ]));
                }
            },
        ];
    }
}
