<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Reset Code | FluxConvert</title>
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
                <span class="auth-eyebrow">Email Verification</span>
                <h2>Enter verification code</h2>
                <p>We sent a 6-digit code to <strong>{{ $email }}</strong>. Enter it below to create a new password.</p>
            </div>

            <form class="auth-form" action="/reset-code" method="post">
                @csrf
                <div class="mt-3">
                    <label class="form-label" for="code">Verification Code</label>
                    <input class="form-control auth-input" id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="123456" required>
                </div>
                <button class="btn auth-submit mt-3" type="submit">Verify Code</button>
            </form>

            @if($authMessage)
                <div class="auth-social-status" role="alert" aria-live="assertive">
                    <i class="bi bi-info-circle"></i>
                    <span>{{ $authMessage }}</span>
                </div>
            @endif

            @if($debugCode)
                <div class="auth-reset-link-box">
                    <i class="bi bi-shield-lock"></i>
                    <span>Local test code: {{ $debugCode }}</span>
                </div>
            @endif

            <p class="auth-bottom-text">
                Didn't receive the code?
                <a href="/forgot-password">Send again</a>
            </p>
        </section>
    </main>

    @include('partials.footer')
    <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}?v={{ $assetVersion }}"></script>
    @include('partials.performance')
</body>
</html>
