<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | FluxConvert</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('img/fluxconvert-logo.svg') }}">
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}?v={{ $assetVersion }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.css') }}?v={{ $assetVersion }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}?v={{ $assetVersion }}">
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ $assetVersion }}">
</head>
<body class="auth-body">
    @include('partials.header')

    <main class="auth-shell auth-simple-shell">
        <section class="auth-form-wrap auth-simple-card">
            <div class="auth-form-head">
                <span class="auth-eyebrow">New Password</span>
                <h2>Create a new password</h2>
                <p>Use a password you will remember. Minimum length is 6 characters.</p>
            </div>

            <form class="auth-form" action="/reset-password" method="post">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="mt-3">
                    <label class="form-label" for="password">New Password</label>
                    <input class="form-control auth-input" id="password" name="password" type="password" required>
                </div>
                <div class="mt-3">
                    <label class="form-label" for="password_confirmation">Confirm Password</label>
                    <input class="form-control auth-input" id="password_confirmation" name="password_confirmation" type="password" required>
                </div>
                <button class="btn auth-submit mt-3" type="submit">Update Password</button>
            </form>

            @if($authMessage)
                <div class="auth-social-status" role="alert" aria-live="assertive">
                    <i class="bi bi-exclamation-circle"></i>
                    <span>{{ $authMessage }}</span>
                </div>
            @endif
        </section>
    </main>

    @include('partials.footer')
    <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}?v={{ $assetVersion }}"></script>
    @include('partials.performance')
</body>
</html>
