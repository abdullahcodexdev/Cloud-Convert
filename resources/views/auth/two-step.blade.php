<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Step Verification | FluxConvert</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('img/fluxconvert-logo.svg') }}">
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}?v={{ $assetVersion }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.css') }}?v={{ $assetVersion }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}?v={{ $assetVersion }}">
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ $assetVersion }}">
</head>
<body class="auth-body">
    <header class="site-header">
        <nav class="navbar navbar-expand-lg">
            <div class="container">
                <a class="navbar-brand brand-mark" href="/">
                    <img class="brand-logo" src="{{ asset('img/fluxconvert-logo.svg') }}" alt="FluxConvert logo">
                    <span>FluxConvert</span>
                </a>
            </div>
        </nav>
    </header>

    <main class="auth-shell">
        <section class="auth-simple-shell">
            <div class="auth-simple-card p-4 p-md-5">
                <div class="auth-form-head">
                    <span class="auth-eyebrow">Security Check</span>
                    <h2>Two-step verification</h2>
                    <p>Enter the 6-digit verification code to finish signing in.</p>
                </div>

                @if($authMessage)
                    <div class="auth-social-status mb-3" role="alert" aria-live="assertive">
                        <i class="bi bi-exclamation-circle"></i>
                        <span>{{ $authMessage }}</span>
                    </div>
                @endif

                <div class="two-step-code-box">
                    <span>Verification code</span>
                    <strong>{{ $verificationCode }}</strong>
                </div>

                <form class="auth-form" action="/two-step" method="post">
                    @csrf
                    <label class="form-label" for="code">6-digit code</label>
                    <input class="form-control auth-input auth-code-input" id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
                    <button class="btn auth-submit mt-3" type="submit">Verify & Continue</button>
                </form>
            </div>
        </section>
    </main>

    @include('partials.whatsapp-chat')

    <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}?v={{ $assetVersion }}"></script>
    @include('partials.performance')
</body>
</html>
