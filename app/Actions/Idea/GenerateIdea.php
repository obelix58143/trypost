<?php

declare(strict_types=1);

namespace App\Actions\Idea;

use App\Ai\Agents\IdeaGenerator;
use App\Models\User;
use App\Support\AiPromptRules;
use Illuminate\Support\Str;

class GenerateIdea
{
    /**
     * @return array{title: string, body: string}|null
     */
    public static function execute(User $user, string $business, string $audience, ?string $notes): ?array
    {
        $response = (new IdeaGenerator($user->locale, $business, $audience, $notes))
            ->prompt('Suggest one content idea.');

        $title = Str::substr(trim((string) data_get($response, 'title')), 0, 255);

        if ($title === '') {
            return null;
        }

        return [
            'title' => $title,
            'body' => Str::substr(trim((string) data_get($response, 'body')), 0, AiPromptRules::PROMPT_MAX_LENGTH),
        ];
    }
}
