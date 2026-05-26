<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | FluxConvert</title>
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
                <span class="auth-eyebrow">Password Reset</span>
                <h2>Reset your password</h2>
                <p>Enter your registered email address. We will send a verification code before you create a new password.</p>
            </div>

            <form class="auth-form" action="/forgot-password" method="post">
                @csrf
                <div class="mt-3">
                    <label class="form-label" for="email">Email Address</label>
                    <input class="form-control auth-input" id="email" name="email" type="email" placeholder="you@example.com" value="{{ $email ?? '' }}" required>
                </div>
                <button class="btn auth-submit mt-3" type="submit">Send Verification Code</button>
            </form>

            @if($authMessage)
                <div class="auth-social-status" role="alert" aria-live="assertive">
                    <i class="bi bi-info-circle"></i>
                    <span>{{ $authMessage }}</span>
                </div>
            @endif

            <p class="auth-bottom-text">
                Remembered your password?
                <a href="/signin">Sign in</a>
            </p>
        </section>
    </main>

    @include('partials.footer')
    <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}?v={{ $assetVersion }}"></script>
    @include('partials.performance')
</body>
</html>
