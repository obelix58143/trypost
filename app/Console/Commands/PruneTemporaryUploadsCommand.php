<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Media\PruneTemporaryUploads;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('media:prune-uploads {--dry-run : Count the files without deleting anything}')]
#[Description('Delete unused temporary uploads past their retention, stale publish crops and abandoned chunk files')]
class PruneTemporaryUploadsCommand extends Command
{
    public function handle(): int
    {
        if ((int) config('trypost.media.upload_retention_hours') < 1) {
            $this->error('MEDIA_UPLOAD_RETENTION_HOURS must be 1 or more.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $result = PruneTemporaryUploads::execute(now(), $dryRun);
        $verb = $dryRun ? 'would be deleted' : 'deleted';

        $this->info("{$result['uploads']} temporary upload(s), {$result['crops']} crop(s) and {$result['chunks']} abandoned chunk file(s) {$verb}.");

        return self::SUCCESS;
    }
}
