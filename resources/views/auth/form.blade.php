<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="FluxConvert authentication page">
    <link rel="icon" type="image/svg+xml" href="{{ asset('img/fluxconvert-logo.svg') }}">
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}?v={{ $assetVersion }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.css') }}?v={{ $assetVersion }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}?v={{ $assetVersion }}">
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}?v={{ $assetVersion }}">
</head>
<body class="auth-body auth-{{ $mode }}">
    <header class="site-header">
        <nav class="navbar navbar-expand-lg">
            <div class="container">
                <a class="navbar-brand brand-mark" href="/">
                    <img class="brand-logo" src="{{ asset('img/fluxconvert-logo.svg') }}" alt="FluxConvert logo">
                    <span>FluxConvert</span>
                </a>
                <button class="navbar-toggler shadow-none border-0" type="button" data-bs-toggle="collapse" data-bs-target="#authMainNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="authMainNav">
                    <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                        <li class="nav-item"><a class="nav-link" href="/">Home</a></li>
                        <li class="nav-item"><a class="nav-link" href="/#converter">Tools</a></li>
                        <li class="nav-item"><a class="nav-link" href="/#features">Features</a></li>
                        <li class="nav-item"><a class="nav-link" href="/#solutions">Solutions</a></li>
                        @if($currentUser ?? false)
                        <li class="nav-item"><a class="nav-link nav-signin" href="/signout">Sign Out</a></li>
                        @else
                        <li class="nav-item"><a class="nav-link nav-signin" href="/signin">Sign In</a></li>
                        @endif
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="auth-shell">
        <div class="auth-card">
            <section class="auth-showcase">
                <span class="auth-badge">Inspired workflow design</span>
                <h1>Move from upload to delivery with a cleaner conversion workspace.</h1>
                <p>
                    Queue files, manage exports, and organize conversion jobs through a calmer,
                    more focused interface built for modern teams.
                </p>
                <div class="showcase-visual">
                    <div class="orb orb-a"></div>
                    <div class="orb orb-b"></div>
                    <div class="showcase-panel panel-large">
                        <span class="panel-label">Smart Queue</span>
                        <strong>12 Active Jobs</strong>
                        <small>Average completion: 1m 48s</small>
                    </div>
                    <div class="showcase-panel panel-small">
                        <span class="panel-label">Cloud Sync</span>
                        <strong>Drive, Dropbox, S3</strong>
                    </div>
                    <div class="showcase-panel panel-mini">
                        <span class="panel-label">Delivery</span>
                        <strong>Ready to export</strong>
                    </div>
                </div>
            </section>

            <section class="auth-form-wrap">
                <div class="auth-form-head">
                    <span class="auth-eyebrow">{{ $mode === 'signin' ? 'Sign In' : 'Sign Up' }}</span>
                    <h2>{{ $heading }}</h2>
                    <p>{{ $subheading }}</p>
                </div>

                <form class="auth-form" action="{{ $mode === 'signup' ? '/signup' : '/signin' }}" method="post" autocomplete="{{ $mode === 'signup' ? 'off' : 'on' }}">
                    @csrf
                    @if($mode === 'signup')
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="first_name">First Name</label>
                            <input class="form-control auth-input" id="first_name" name="first_name" type="text" placeholder="First Name">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="last_name">Last Name</label>
                            <input class="form-control auth-input" id="last_name" name="last_name" type="text" placeholder="Last Name">
                        </div>
                    </div>
                    @endif

                    <div class="mt-3">
                        <label class="form-label" for="email">Email Address</label>
                        <input class="form-control auth-input" id="email" name="email" type="email" placeholder="you@example.com" autocomplete="{{ $mode === 'signup' ? 'off' : 'username' }}" value="{{ $mode === 'signup' ? '' : old('email') }}" required>
                    </div>

                    <div class="mt-3">
                        <label class="form-label" for="password">Password</label>
                        <div class="auth-password-field">
                            <input class="form-control auth-input" id="password" name="password" type="password" placeholder="Enter your password" autocomplete="{{ $mode === 'signup' ? 'new-password' : 'current-password' }}" value="" required>
                            <button type="button" class="auth-eye">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    @if($mode === 'signup')
                    <!-- <div class="mt-3">
                        <label class="form-label" for="company">Company Name</label>
                        <input class="form-control auth-input" id="company" type="text" placeholder="FluxConvert Studio">
                    </div> -->
                    @endif

                    <div class="auth-row">
                        <label class="auth-check">
                            <input type="checkbox" name="{{ $mode === 'signin' ? 'remember' : 'terms' }}" value="1">
                            <span>{{ $mode === 'signin' ? 'Remember me' : 'I agree to the Terms and Privacy Policy' }}</span>
                        </label>
                        @if($mode === 'signin')
                        <a class="auth-inline-link" href="/forgot-password">Forgot password?</a>
                        @endif
                    </div>

                    <button class="btn auth-submit" type="submit">{{ $primaryAction }}</button>

                    <div class="auth-divider"><span>or continue with</span></div>

                    <div class="auth-socials">
                        <button type="submit" class="btn auth-social-btn" form="google-auth-form"><i class="bi bi-google"></i> Google</button>
                        <button type="submit" class="btn auth-social-btn" form="microsoft-auth-form"><i class="bi bi-microsoft"></i> Microsoft</button>
                    </div>
                    @if($authMessage)
                    <div class="auth-social-status" role="alert" aria-live="assertive">
                        <i class="bi bi-exclamation-circle"></i>
                        <span>{{ $authMessage }}</span>
                    </div>
                    @endif

                    <p class="auth-bottom-text">
                        {{ $alternateText }}
                        <a href="{{ $alternateHref }}">{{ $alternateLabel }}</a>
                    </p>
                </form>
                <form id="google-auth-form" action="/auth/google" method="get">
                    <input type="hidden" name="next" value="{{ $mode }}">
                </form>
                <form id="microsoft-auth-form" action="/auth/microsoft" method="get">
                    <input type="hidden" name="next" value="{{ $mode }}">
                </form>
            </section>
        </div>
    </main>

    <footer class="site-footer">
        <div class="container footer-shell">
            <div class="footer-brand-block">
                <a class="footer-brand" href="/">
                    <img class="brand-logo" src="{{ asset('img/fluxconvert-logo.svg') }}" alt="FluxConvert logo">
                    <span>FluxConvert</span>
                </a>
                <p class="mb-0">Modern file conversion for documents, media, images, and archives with a cleaner workflow built for speed and clarity.</p>
            </div>
            <div class="footer-links-grid">
                <div>
                    <span class="footer-title">Product</span>
                    <div class="footer-links">
                        <a href="/#features">Features</a>
                        <a href="/#solutions">Solutions</a>
                    </div>
                </div>
                <div>
                    <span class="footer-title">Access</span>
                    <div class="footer-links">
                        @if($currentUser ?? false)
                        <a href="/signout">Sign Out</a>
                        @else
                        <a href="/signin">Sign In</a>
                        <a href="/signup">Sign Up</a>
                        @endif
                        <a href="/#converter">Launch Tool</a>
                    </div>
                </div>
            </div>
            <div class="footer-socials">
                <a href="#" class="footer-social-link" aria-label="Facebook">
                    <span class="social-tooltip">Facebook</span>
                    <i class="bi bi-facebook"></i>
                </a>
                <a href="#" class="footer-social-link" aria-label="Instagram">
                    <span class="social-tooltip">Instagram</span>
                    <i class="bi bi-instagram"></i>
                </a>
                <a href="#" class="footer-social-link" aria-label="Twitter">
                    <span class="social-tooltip">Twitter</span>
                    <i class="bi bi-twitter-x"></i>
                </a>
                <a href="#" class="footer-social-link" aria-label="LinkedIn">
                    <span class="social-tooltip">LinkedIn</span>
                    <i class="bi bi-linkedin"></i>
                </a>
            </div>
        </div>
        <div class="container footer-bottom">
            <span>&copy; 2026 FluxConvert</span>
            <div class="footer-bottom-links">
                <a href="#">Privacy</a>
                <a href="#">Terms</a>
                <a href="#">Support</a>
            </div>
        </div>
    </footer>

    <footer class="auth-footer">
        <div class="container auth-footer-inner">
            <span>&copy; 2026 FluxConvert</span>
            <div class="auth-footer-links">
                <a href="/">Home</a>
                <a href="#">Privacy</a>
                <a href="#">Terms</a>
                <a href="#">Support</a>
            </div>
        </div>
    </footer>
    @include('partials.whatsapp-chat')
    <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}?v={{ $assetVersion }}"></script>
    <script>
        document.querySelectorAll(".auth-eye").forEach((button) => {
            button.addEventListener("click", () => {
                const input = button.closest(".auth-password-field")?.querySelector("input");
                const icon = button.querySelector("i");
                if (!input) {
                    return;
                }

                const isPassword = input.type === "password";
                input.type = isPassword ? "text" : "password";
                icon?.classList.toggle("bi-eye", !isPassword);
                icon?.classList.toggle("bi-eye-slash", isPassword);
            });
        });
    </script>
    @include('partials.performance')
</body>
</html>


