<?php

declare(strict_types=1);

namespace App\Support\Requests\Repurpose;

use App\Enums\Repurpose\SourceFormat;
use App\Models\Repurpose;
use App\Support\Repurpose\DestinationMetaRules;
use App\Support\Repurpose\RepurposeRules;
use App\Support\Repurpose\SourceIsFree;
use App\Support\Repurpose\SourceIsNotADestination;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Validator;

/**
 * Single source of the repurpose create and update validation shared by the
 * web update, the API FormRequests and the MCP create and update tools.
 */
class RepurposeRequestRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(?string $workspaceId, bool $sourceRequired): array
    {
        return [
            ...RepurposeRules::settings($workspaceId, $sourceRequired),
            ...RepurposeRules::destinations($workspaceId),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return RepurposeRules::messages();
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return RepurposeRules::attributes();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function addCrossFieldErrors(
        Validator $validator,
        ?string $workspaceId,
        array $input,
        ?Repurpose $repurpose = null,
    ): void {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $sourceAccountId = data_get($input, 'source_social_account_id', $repurpose?->source_social_account_id);
        $destinations = (array) data_get($input, 'destinations', []);

        SourceIsFree::addErrors(
            $validator,
            $workspaceId,
            $sourceAccountId,
            SourceFormat::from(data_get($input, 'source_format', $repurpose?->source_format->value ?? SourceFormat::Reel->value)),
            $repurpose?->id,
        );

        SourceIsNotADestination::addErrors($validator, $destinations, $sourceAccountId);

        if ($repurpose instanceof Repurpose && DestinationMetaRules::enforcedFor($repurpose)) {
            DestinationMetaRules::addRequiredErrors($validator, $destinations, $workspaceId);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function validate(array $input, ?string $workspaceId, ?Repurpose $repurpose = null): array
    {
        $validator = ValidatorFacade::make(
            $input,
            self::rules($workspaceId, ! $repurpose instanceof Repurpose),
            self::messages(),
            self::attributes(),
        );

        $validator->after(fn (Validator $validator) => self::addCrossFieldErrors($validator, $workspaceId, $input, $repurpose));

        return $validator->validate();
    }
}
