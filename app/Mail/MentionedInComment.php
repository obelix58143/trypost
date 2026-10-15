<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;

class MentionedInComment extends Mailable
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function __unserialize(array $values): void {}

    public function send($mailer): null
    {
        return null;
    }
}
