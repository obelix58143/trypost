<?php

declare(strict_types=1);

namespace App\Enums\SocialAccount;

/**
 * What an identity offered on the connect confirmation page is on its network,
 * shown under its name (`accounts.connect.types.<value>`).
 */
enum IdentityType: string
{
    case Profile = 'profile';
    case Page = 'page';
    case Channel = 'channel';
    case Location = 'location';
    case Server = 'server';
}
