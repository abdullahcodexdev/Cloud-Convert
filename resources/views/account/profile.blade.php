<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile | FluxConvert</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('img/fluxconvert-logo.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/fluxconvert-logo.svg') }}">
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}?v={{ $assetVersion }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.css') }}?v={{ $assetVersion }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/styles.css') }}?v={{ $assetVersion }}">
</head>
<body>
    @include('partials.header')

    @php
        $profileName = $currentUser['name'] ?? $currentUser['email'] ?? 'Account';
        $profileEmail = $currentUser['email'] ?? '';
        $profileAvatar = $currentUser['avatar'] ?? '';
        $profilePhone = $currentUser['phone'] ?? '';
        $profileCompany = $currentUser['company'] ?? '';
        $profileJobTitle = $currentUser['job_title'] ?? '';
        $profileLocation = $currentUser['location'] ?? '';
        $profileTimezone = $currentUser['timezone'] ?? '';
        $profileBio = $currentUser['bio'] ?? '';
        $profileInitials = collect(explode(' ', trim($profileName)))->filter()->take(2)->map(fn ($part) => strtoupper(substr($part, 0, 1)))->implode('') ?: 'U';
    @endphp

    <main class="account-page">
        <section class="account-section">
            <div class="container">
                @if(session('profile_message'))
                    <div class="profile-alert profile-alert-success">
                        <i class="bi bi-check-circle"></i>
                        <span>{{ session('profile_message') }}</span>
                    </div>
                @endif
                @if(session('profile_error') || $errors->any())
                    <div class="profile-alert profile-alert-error">
                        <i class="bi bi-exclamation-circle"></i>
                        <span>{{ session('profile_error') ?: $errors->first() }}</span>
                    </div>
                @endif

                <div class="profile-layout">
                    <aside class="profile-sidebar-card">
                        <span class="profile-avatar account-avatar">
                            <span class="avatar-fallback">{{ $profileInitials }}</span>
                            @if($profileAvatar)
                                <img src="{{ $profileAvatar }}" alt="" onerror="this.remove()">
                            @endif
                        </span>
                        <h2>{{ $profileName }}</h2>
                        <p>{{ $profileJobTitle ?: ucfirst($currentUser['provider'] ?? 'local') . ' account' }}</p>
                        <div class="profile-sidebar-stats">
                            <div><strong>{{ count($files) }}</strong><span>Saved files</span></div>
                            <div><strong>{{ $profileCompany ?: 'Personal' }}</strong><span>Workspace</span></div>
                        </div>
                        @if($profileBio)
                            <p class="profile-sidebar-bio">{{ $profileBio }}</p>
                        @endif
                        <a class="btn queue-secondary-btn" href="/my-files">Open My Files</a>
                    </aside>

                    <div class="profile-edit-stack">
                        <article class="account-card">
                            <div class="profile-card-head">
                                <div>
                                    <h2>Personal Information</h2>
                                    <p>These details appear in your profile menu and account pages.</p>
                                </div>
                            </div>
                            <form class="profile-form" action="/profile" method="post" enctype="multipart/form-data">
                                @csrf
                                <div class="profile-picture-editor">
                                    <div class="profile-picture-preview">
                                        <span class="profile-avatar account-avatar">
                                            <span class="avatar-fallback">{{ $profileInitials }}</span>
                                            @if($profileAvatar)
                                                <img src="{{ $profileAvatar }}" alt="" onerror="this.remove()">
                                            @endif
                                        </span>
                                        <div>
                                            <strong>Profile picture</strong>
                                            <span>Upload a photo or use an image URL from the web.</span>
                                        </div>
                                    </div>
                                    <details class="profile-picture-options">
                                        <summary><i class="bi bi-pencil-square"></i> Edit picture</summary>
                                        <div class="profile-picture-grid">
                                            <div>
                                                <label class="form-label" for="avatar_upload">Upload Picture</label>
                                                <input class="form-control auth-input profile-file-input" id="avatar_upload" name="avatar_upload" type="file" accept="image/png,image/jpeg,image/webp,image/gif">
                                                <small>JPG, PNG, WEBP, or GIF. Maximum 2 MB.</small>
                                            </div>
                                            <div>
                                                <label class="form-label" for="avatar_url">Picture URL from Web</label>
                                                <input class="form-control auth-input" id="avatar_url" name="avatar_url" type="url" value="{{ old('avatar_url') }}" placeholder="https://example.com/photo.jpg">
                                                <small>Paste a direct image URL if you do not want to upload.</small>
                                            </div>
                                        </div>
                                    </details>
                                </div>
                                <div class="profile-form-grid">
                                    <div>
                                        <label class="form-label" for="name">Display Name</label>
                                        <input class="form-control auth-input" id="name" name="name" value="{{ old('name', $profileName) }}" required>
                                    </div>
                                    <div>
                                        <label class="form-label" for="email">Email Address</label>
                                        <input class="form-control auth-input" id="email" name="email" type="email" value="{{ old('email', $profileEmail) }}" required>
                                    </div>
                                    <div>
                                        <label class="form-label" for="phone">Phone Number</label>
                                        <input class="form-control auth-input" id="phone" name="phone" value="{{ old('phone', $profilePhone) }}" placeholder="+1 555 0100">
                                    </div>
                                    <div>
                                        <label class="form-label" for="job_title">Role / Title</label>
                                        <input class="form-control auth-input" id="job_title" name="job_title" value="{{ old('job_title', $profileJobTitle) }}" placeholder="Designer, Manager, Student">
                                    </div>
                                    <div>
                                        <label class="form-label" for="company">Company / Workspace</label>
                                        <input class="form-control auth-input" id="company" name="company" value="{{ old('company', $profileCompany) }}" placeholder="Personal, Agency, Team name">
                                    </div>
                                    <div>
                                        <label class="form-label" for="location">Location</label>
                                        <input class="form-control auth-input" id="location" name="location" value="{{ old('location', $profileLocation) }}" placeholder="City, Country">
                                    </div>
                                    <div>
                                        <label class="form-label" for="timezone">Timezone</label>
                                        <select class="form-control auth-input profile-select" id="timezone" name="timezone">
                                            <option value="">Select timezone</option>
                                            @foreach($timezones as $timezone)
                                                <option value="{{ $timezone['value'] }}" @selected(old('timezone', $profileTimezone) === $timezone['value'])>{{ $timezone['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="profile-form-wide">
                                        <label class="form-label" for="bio">Bio</label>
                                        <textarea class="form-control auth-input profile-textarea" id="bio" name="bio" maxlength="500" placeholder="Short professional bio">{{ old('bio', $profileBio) }}</textarea>
                                        <small>Maximum 500 characters.</small>
                                    </div>
                                </div>
                                <button class="btn queue-primary-btn" type="submit">Save Profile</button>
                            </form>
                        </article>

                        <article class="account-card">
                            <div class="profile-card-head">
                                <div>
                                    <h2>Password & Security</h2>
                                    <p>Change or create an email/password login for this account.</p>
                                </div>
                            </div>
                            <form class="profile-form" action="/profile/password" method="post">
                                @csrf
                                <div class="profile-form-grid">
                                    <div>
                                        <label class="form-label" for="current_password">Current Password</label>
                                        <input class="form-control auth-input" id="current_password" name="current_password" type="password" placeholder="Required if password already exists">
                                    </div>
                                    <div>
                                        <label class="form-label" for="password">New Password</label>
                                        <input class="form-control auth-input" id="password" name="password" type="password" required>
                                    </div>
                                    <div>
                                        <label class="form-label" for="password_confirmation">Confirm New Password</label>
                                        <input class="form-control auth-input" id="password_confirmation" name="password_confirmation" type="password" required>
                                    </div>
                                </div>
                                <button class="btn queue-secondary-btn" type="submit">Update Password</button>
                            </form>
                        </article>
                    </div>
                </div>
            </div>
        </section>
    </main>

    @include('partials.footer')
    <script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}?v={{ $assetVersion }}"></script>
    @include('partials.performance')
</body>
</html>
