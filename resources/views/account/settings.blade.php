<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings | FluxConvert</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('img/fluxconvert-logo.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/fluxconvert-logo.svg') }}">
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}?v={{ $assetVersion }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.css') }}?v={{ $assetVersion }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}?v={{ $assetVersion }}">
</head>
<body>
    @include('partials.header')

    @php
        $email = $currentUser['email'] ?? '';
        $provider = ucfirst($currentUser['provider'] ?? 'local');
        $twoStepEnabled = (bool) ($currentUser['two_step_enabled'] ?? false);
    @endphp

    <main class="account-page">
        <section class="account-hero">
            <div class="container">
                <span class="eyebrow">Settings</span>
                <h1>Account settings</h1>
                <p>Manage sign-in security, email access, and account controls for your FluxConvert profile.</p>
            </div>
        </section>

        <section class="account-section">
            <div class="container">
                @if(session('settings_message'))
                    <div class="profile-alert profile-alert-success">
                        <i class="bi bi-check-circle"></i>
                        <span>{{ session('settings_message') }}</span>
                    </div>
                @endif
                @if(session('settings_error') || $errors->any())
                    <div class="profile-alert profile-alert-error">
                        <i class="bi bi-exclamation-circle"></i>
                        <span>{{ session('settings_error') ?: $errors->first() }}</span>
                    </div>
                @endif

                <div class="settings-page-grid">
                    <aside class="settings-summary-card">
                        <div class="settings-summary-icon">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <h2>Security status</h2>
                        <p>{{ $twoStepEnabled ? 'Two-step verification is active on this account.' : 'Add two-step verification for stronger sign-in protection.' }}</p>
                        <div class="settings-status-list">
                            <div>
                                <span>Sign-in provider</span>
                                <strong>{{ $provider }}</strong>
                            </div>
                            <div>
                                <span>Saved files</span>
                                <strong>{{ count($files) }}</strong>
                            </div>
                            <div>
                                <span>Two-step verification</span>
                                <strong class="{{ $twoStepEnabled ? 'status-on' : 'status-off' }}">{{ $twoStepEnabled ? 'Enabled' : 'Disabled' }}</strong>
                            </div>
                        </div>
                    </aside>

                    <div class="settings-main-stack">
                        <article class="account-card">
                            <div class="settings-card-head">
                                <div>
                                    <h2>Change email address</h2>
                                    <p>Your new email is saved to your account and will be used for future password sign-ins.</p>
                                </div>
                                <span class="settings-chip">Account</span>
                            </div>
                            <form class="settings-form" action="/settings/email" method="post">
                                @csrf
                                <div class="profile-form-grid">
                                    <div>
                                        <label class="form-label" for="email">New Email Address</label>
                                        <input class="form-control auth-input" id="email" name="email" type="email" value="{{ old('email', $email) }}" required>
                                    </div>
                                    <div>
                                        <label class="form-label" for="current_password">Current Password</label>
                                        <input class="form-control auth-input" id="current_password" name="current_password" type="password" placeholder="Required for password accounts">
                                    </div>
                                </div>
                                <button class="btn queue-primary-btn" type="submit">Update Email</button>
                            </form>
                        </article>

                        <article class="account-card">
                            <div class="settings-card-head">
                                <div>
                                    <h2>Two-step verification</h2>
                                    <p>Require a 6-digit verification code after a successful sign-in attempt.</p>
                                </div>
                                <span class="settings-chip {{ $twoStepEnabled ? 'settings-chip-on' : '' }}">{{ $twoStepEnabled ? 'On' : 'Off' }}</span>
                            </div>
                            <form class="settings-form" action="/settings/two-step" method="post">
                                @csrf
                                <div class="settings-toggle-row">
                                    <span>
                                        <strong>Require verification code</strong>
                                        <small>Enter your current password, switch this on or off, then save the security setting.</small>
                                    </span>
                                    <span class="settings-switch-wrap">
                                        <input class="settings-switch-input" id="two_step_enabled" name="enabled" value="1" type="checkbox" @checked($twoStepEnabled)>
                                        <label class="settings-switch" for="two_step_enabled" role="switch" aria-label="Toggle two-step verification">
                                            <span class="settings-switch-knob"></span>
                                        </label>
                                        <strong class="settings-switch-state" data-on="On" data-off="Off">{{ $twoStepEnabled ? 'On' : 'Off' }}</strong>
                                    </span>
                                </div>
                                <div class="profile-form-grid">
                                    <div>
                                        <label class="form-label" for="two_step_current_password">Current Password</label>
                                        <input class="form-control auth-input" id="two_step_current_password" name="current_password" type="password" placeholder="Required for password accounts">
                                    </div>
                                </div>
                                <button class="btn queue-secondary-btn" type="submit">Save Security Setting</button>
                            </form>
                        </article>

                        <article class="account-card">
                            <div class="settings-card-head">
                                <div>
                                    <h2>Account shortcuts</h2>
                                    <p>Quick access to profile, saved files, and session controls.</p>
                                </div>
                            </div>
                            <div class="settings-action-grid">
                                <a href="/profile"><i class="bi bi-person"></i><span>Edit Profile</span></a>
                                <a href="/my-files"><i class="bi bi-folder2-open"></i><span>My Files</span></a>
                                <a class="settings-danger-link" href="/signout"><i class="bi bi-box-arrow-right"></i><span>Sign Out</span></a>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </section>
    </main>

    @include('partials.footer')
    <script>
        document.querySelectorAll(".settings-switch-input").forEach((input) => {
            const state = input.closest(".settings-switch-wrap")?.querySelector(".settings-switch-state");
            const syncState = () => {
                if (state) {
                    state.textContent = input.checked ? state.dataset.on : state.dataset.off;
                }
            };
            input.addEventListener("change", syncState);
            syncState();
        });
    </script>
    <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}?v={{ $assetVersion }}"></script>
    @include('partials.performance')
</body>
</html>
