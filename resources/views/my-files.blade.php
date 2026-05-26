<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Files | FluxConvert</title>
    <meta name="description" content="Saved FluxConvert file conversions.">
    <link rel="icon" type="image/svg+xml" href="{{ asset('img/fluxconvert-logo.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/fluxconvert-logo.svg') }}">
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}?v={{ $assetVersion }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.css') }}?v={{ $assetVersion }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}?v={{ $assetVersion }}">
</head>
<body>
    @include('partials.header')

    <main class="my-files-page">
        <section class="my-files-hero">
            <div class="container">
                <div class="my-files-hero-grid">
                    <div>
                        <span class="eyebrow">My Files</span>
                        <h1>Saved conversions</h1>
                        <p>Files converted while signed in stay attached to your account, so you can return later and download them again.</p>
                    </div>
                    <div class="my-files-account-card">
                        @php
                            $profileName = $currentUser['name'] ?? $currentUser['email'] ?? 'Account';
                            $profileEmail = $currentUser['email'] ?? '';
                            $profileInitials = collect(explode(' ', trim($profileName)))->filter()->take(2)->map(fn ($part) => strtoupper(substr($part, 0, 1)))->implode('') ?: 'U';
                        @endphp
                        <span class="profile-avatar profile-avatar-large">
                            @if(!empty($currentUser['avatar']))
                                <img src="{{ $currentUser['avatar'] }}" alt="">
                            @else
                                <span>{{ $profileInitials }}</span>
                            @endif
                        </span>
                        <div>
                            <strong>{{ $profileName }}</strong>
                            <span>{{ $currentUser['provider'] ?? 'account' }} account</span>
                            <small>{{ count($files) }} saved {{ count($files) === 1 ? 'file' : 'files' }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="my-files-section">
            <div class="container">
                @if(count($files))
                    <div class="my-files-toolbar">
                        <h2>Conversion history</h2>
                        <a class="btn queue-primary-btn" href="/#converter">
                            <i class="bi bi-arrow-repeat"></i>
                            <span>Convert More</span>
                        </a>
                    </div>
                    <div class="history-list my-files-list">
                        @foreach($files as $file)
                            @php
                                $downloadUrl = !empty($file['id'])
                                    ? route('download', ['fileId' => $file['id']], false)
                                    : ($file['download_url'] ?? '#');
                            @endphp
                            <article class="history-item">
                                <div class="history-item-meta">
                                    <i class="bi bi-file-earmark-check"></i>
                                    <div>
                                        <strong>{{ $file['converted_name'] ?? 'converted-file' }}</strong>
                                        <span>
                                            {{ $file['original_name'] ?? 'Uploaded file' }}
                                            @if(!empty($file['source_format']) && !empty($file['target_format']))
                                                · {{ $file['source_format'] }} to {{ $file['target_format'] }}
                                            @endif
                                            @if(!empty($file['size']))
                                                · {{ number_format(((int) $file['size']) / 1024, 1) }} KB
                                            @endif
                                        </span>
                                    </div>
                                </div>
                                <a class="btn queue-inline-download-btn" href="{{ $downloadUrl }}" download>
                                    Download
                                </a>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="my-files-empty">
                        <i class="bi bi-folder2-open"></i>
                        <h2>No saved conversions yet</h2>
                        <p>Convert a file while signed in and it will appear here automatically.</p>
                        <a class="btn queue-primary-btn" href="/#converter">Start Converting</a>
                    </div>
                @endif
            </div>
        </section>
    </main>

    @include('partials.footer')

    <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}?v={{ $assetVersion }}"></script>
    @include('partials.performance')
</body>
</html>
