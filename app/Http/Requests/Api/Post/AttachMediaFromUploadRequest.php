<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Post;

use App\Actions\Media\ResolveWorkspaceMedia;
use App\Models\Media;
use App\Support\Requests\Post\PostMediaRequestRules;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class AttachMediaFromUploadRequest extends FormRequest
{
    private ?Media $resolvedUpload = null;

    public function authorize(): Response
    {
        return Gate::forUser($this->user())->inspect('update', $this->route('post'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return PostMediaRequestRules::attachFromUpload();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('upload_token')) {
                return;
            }

            $this->resolvedUpload = ResolveWorkspaceMedia::byUploadTokens(
                $this->user()->currentWorkspace,
                [(string) $this->input('upload_token')],
            )->first();

            if ($this->resolvedUpload === null) {
                $validator->errors()->add('upload_token', __('posts.errors.media_expired'));
            }
        });
    }

    public function upload(): Media
    {
        return $this->resolvedUpload;
    }
}
