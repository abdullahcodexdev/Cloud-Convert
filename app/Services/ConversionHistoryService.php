<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class ConversionHistoryService
{
    private string $path;

    public function __construct()
    {
        $this->path = storage_path('app/conversion-history.json');
    }

    public function userKey(?array $user): ?string
    {
        if (! $user) {
            return null;
        }

        $provider = strtolower((string) ($user['provider'] ?? 'local'));
        $identifier = (string) ($user['provider_id'] ?? $user['email'] ?? '');
        if ($identifier === '') {
            return null;
        }

        return hash('sha256', "{$provider}:{$identifier}");
    }

    public function add(?array $user, array $record): void
    {
        $userKey = $this->userKey($user);
        if (! $userKey) {
            return;
        }

        $this->withData(function (array $data) use ($userKey, $record): array {
            $data[$userKey] ??= [];
            array_unshift($data[$userKey], array_merge($record, [
                'created_at' => now()->toISOString(),
            ]));
            $data[$userKey] = array_slice($data[$userKey], 0, 100);

            return $data;
        });
    }

    public function list(?array $user): array
    {
        $userKey = $this->userKey($user);
        if (! $userKey || ! is_file($this->path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($this->path), true);
        $records = is_array($data[$userKey] ?? null) ? $data[$userKey] : [];

        return array_values(array_filter($records, function (array $record): bool {
            $fileId = (string) ($record['id'] ?? '');
            if (! preg_match('/^[a-f0-9]{32}$/i', $fileId)) {
                return false;
            }

            foreach (Storage::files('converted') as $path) {
                if (str_starts_with(basename($path), "{$fileId}_")) {
                    return true;
                }
            }

            return false;
        }));
    }

    private function withData(callable $callback): void
    {
        $directory = dirname($this->path);
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $handle = fopen($this->path, 'c+');
        if (! $handle) {
            return;
        }

        try {
            flock($handle, LOCK_EX);
            $contents = stream_get_contents($handle);
            $data = json_decode($contents !== false ? $contents : '', true);
            $data = is_array($data) ? $data : [];
            $data = $callback($data);
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
