<header class="site-header">
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand brand-mark" href="/">
                <img class="brand-logo" src="{{ asset('img/fluxconvert-logo.svg') }}" alt="FluxConvert logo">
                <span>FluxConvert</span>
            </a>
            <button class="navbar-toggler shadow-none border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                    <li class="nav-item"><a class="nav-link" href="/">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="/#converter">Tools</a></li>
                    <li class="nav-item"><a class="nav-link" href="/#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="/#solutions">Solutions</a></li>
                    @if($currentUser ?? false)
                        <li class="nav-item"><a class="nav-link" href="/my-files">My Files</a></li>
                        @php
                            $profileName = $currentUser['name'] ?? $currentUser['email'] ?? 'Account';
                            $profileEmail = $currentUser['email'] ?? '';
                            $profileInitials = collect(explode(' ', trim($profileName)))->filter()->take(2)->map(fn ($part) => strtoupper(substr($part, 0, 1)))->implode('') ?: 'U';
                        @endphp
                        <li class="nav-item nav-profile">
                            <button type="button" class="profile-trigger">
                                <span class="profile-avatar">
                                    <span class="avatar-fallback">{{ $profileInitials }}</span>
                                    @if(!empty($currentUser['avatar']))
                                        <img src="{{ $currentUser['avatar'] }}" alt="" onerror="this.remove()">
                                    @endif
                                </span>
                                <span class="profile-trigger-copy">
                                    <strong>{{ $profileName }}</strong>
                                    <small>{{ $currentUser['provider'] ?? 'account' }} account</small>
                                </span>
                                <i class="bi bi-chevron-down"></i>
                            </button>
                            <div class="profile-menu">
                                <div class="profile-menu-head">
                                    <span class="profile-avatar profile-avatar-large">
                                        <span class="avatar-fallback">{{ $profileInitials }}</span>
                                        @if(!empty($currentUser['avatar']))
                                            <img src="{{ $currentUser['avatar'] }}" alt="" onerror="this.remove()">
                                        @endif
                                    </span>
                                    <div>
                                        <strong>{{ $profileName }}</strong>
                                        <span>{{ $currentUser['provider'] ?? 'account' }} account</span>
                                    </div>
                                </div>
                                <a href="/my-files" class="profile-menu-item">
                                    <i class="bi bi-folder-check"></i>
                                    <span>Saved files</span>
                                </a>
                                <a href="/profile" class="profile-menu-item">
                                    <i class="bi bi-person"></i>
                                    <span>Profile</span>
                                </a>
                                <a href="/settings" class="profile-menu-item">
                                    <i class="bi bi-gear"></i>
                                    <span>Settings</span>
                                </a>
                                <a href="/signout" class="profile-menu-item profile-menu-signout">
                                    <i class="bi bi-box-arrow-right"></i>
                                    <span>Sign Out</span>
                                </a>
                            </div>
                        </li>
                    @else
                        <li class="nav-item"><a class="nav-link nav-signin" href="/signin">Sign In</a></li>
                    @endif
                </ul>
            </div>
        </div>
    </nav>
</header>
