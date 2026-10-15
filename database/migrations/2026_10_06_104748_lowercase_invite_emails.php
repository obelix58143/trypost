<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Lowercases every stored invite email. When two invites of one account
     * collide once lowercased, the accepted one wins, then the newest.
     */
    public function up(): void
    {
        $mixedCaseIds = DB::table('invites')
            ->orderBy('id')
            ->get(['id', 'email'])
            ->filter(fn (object $invite): bool => $invite->email !== Str::lower($invite->email))
            ->pluck('id');

        foreach ($mixedCaseIds as $id) {
            DB::transaction(function () use ($id): void {
                $invite = DB::table('invites')->where('id', $id)->lockForUpdate()->first();

                if (! $invite) {
                    return;
                }

                $email = Str::lower($invite->email);

                $collision = DB::table('invites')
                    ->where('account_id', $invite->account_id)
                    ->where('email', $email)
                    ->where('id', '!=', $invite->id)
                    ->lockForUpdate()
                    ->first();

                if (! $collision) {
                    DB::table('invites')->where('id', $invite->id)->update(['email' => $email]);

                    return;
                }

                if ($this->outranks($invite, $collision)) {
                    DB::table('invites')->where('id', $collision->id)->delete();
                    DB::table('invites')->where('id', $invite->id)->update(['email' => $email]);

                    return;
                }

                DB::table('invites')->where('id', $invite->id)->delete();
            });
        }
    }

    private function outranks(object $invite, object $other): bool
    {
        if (($invite->accepted_at === null) !== ($other->accepted_at === null)) {
            return $invite->accepted_at !== null;
        }

        if ($invite->created_at !== $other->created_at) {
            return $invite->created_at > $other->created_at;
        }

        return strcmp($invite->id, $other->id) > 0;
    }
};
