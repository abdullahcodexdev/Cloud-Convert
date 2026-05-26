<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupOldFiles extends Command
{
    protected $signature = 'files:cleanup {--hours=48 : Delete files older than this many hours}';

    protected $description = 'Delete leftover uploaded and converted files older than the given age.';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $cutoff = now()->subHours($hours)->getTimestamp();

        $deleted = 0;
        $freedBytes = 0;

        // Files stored on the "local" disk (storage/app).
        foreach (['uploads', 'converted'] as $directory) {
            foreach (Storage::files($directory) as $path) {
                $absolute = Storage::path($path);
                if (is_file($absolute) && filemtime($absolute) < $cutoff) {
                    $freedBytes += (int) (filesize($absolute) ?: 0);
                    if (Storage::delete($path)) {
                        $deleted++;
                    }
                }
            }
        }

        // Publicly served copies.
        $publicDir = public_path('converted-downloads');
        if (is_dir($publicDir)) {
            foreach (glob($publicDir . DIRECTORY_SEPARATOR . '*') ?: [] as $absolute) {
                if (is_file($absolute) && filemtime($absolute) < $cutoff) {
                    $freedBytes += (int) (filesize($absolute) ?: 0);
                    if (@unlink($absolute)) {
                        $deleted++;
                    }
                }
            }
        }

        $this->info(sprintf(
            'Cleanup complete: removed %d file(s), freed %.2f MB (older than %d hours).',
            $deleted,
            $freedBytes / 1048576,
            $hours
        ));

        return self::SUCCESS;
    }
}
