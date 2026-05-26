<?php

namespace App\Support;

class AssetVersion
{
    public static function current(): int
    {
        static $version = null;

        if ($version !== null) {
            return $version;
        }

        $paths = [
            public_path('css/styles.css'),
            public_path('css/auth.css'),
            public_path('js/app.js'),
            public_path('vendor/bootstrap/bootstrap.min.css'),
            public_path('vendor/bootstrap/bootstrap.bundle.min.js'),
            public_path('vendor/bootstrap-icons/bootstrap-icons.css'),
            public_path('vendor/vue/vue.global.prod.js'),
        ];

        $version = collect($paths)
            ->filter(fn (string $path): bool => is_file($path))
            ->map(fn (string $path): int => filemtime($path) ?: 1)
            ->max() ?: 1;

        return $version;
    }
}
