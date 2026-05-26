<?php

namespace App\Http\Controllers;

use App\Services\UserAccountService;
use App\Support\AssetVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

class AuthPageController extends Controller
{
    private array $supportedProviders = ['google', 'microsoft'];

    public function signin(Request $request): View
    {
        return $this->authView($request, 'signin', 'Sign In | FluxConvert', 'Welcome back', 'Sign in to manage conversions, usage, and saved workflows in one place.', 'Sign In', "Don't have an account?", '/signup', 'Create one');
    }

    public function signup(Request $request): View
    {
        return $this->authView($request, 'signup', 'Sign Up | FluxConvert', 'Create your account', 'Start converting files faster with a polished workspace, cloud-ready flows, and flexible export tools.', 'Create Account', 'Already have an account?', '/signin', 'Sign in');
    }

    public function forgot(): View
    {
        return view('auth.forgot', [
            'assetVersion' => AssetVersion::current(),
            'currentUser' => session('auth_user'),
            'authMessage' => session('auth_message'),
            'email' => old('email', session('auth_user.email')),
        ]);
    }

    public function sendReset(Request $request, UserAccountService $accounts): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower(trim($validated['email']));
        $account = $accounts->findByEmail($email);
        $currentUser = session('auth_user');

        if (! $account && is_array($currentUser) && strtolower((string) ($currentUser['email'] ?? '')) === $email) {
            $account = $accounts->ensureFromSession($currentUser);
        }

        if (! $account) {
            return redirect()
                ->route('password.forgot')
                ->withInput(['email' => $validated['email']])
                ->with('auth_message', 'This email is not registered. Please create an account first.');
        }

        $code = (string) random_int(100000, 999999);
        session([
            'password_reset_email' => $email,
            'password_reset_code_hash' => hash('sha256', $code),
            'password_reset_code_expires_at' => time() + 600,
        ]);

        $this->sendPasswordResetCode($email, $code);

        return redirect()
            ->route('password.code')
            ->with('auth_message', 'Verification code sent to your email. Enter it to continue.');
    }

    public function resetCode(): View|RedirectResponse
    {
        if (! session('password_reset_email')) {
            return redirect()->route('password.forgot');
        }

        return view('auth.reset-code', [
            'assetVersion' => AssetVersion::current(),
            'currentUser' => session('auth_user'),
            'authMessage' => session('auth_message'),
            'email' => session('password_reset_email'),
            'debugCode' => app()->environment('local') ? session('password_reset_debug_code') : null,
        ]);
    }

    public function verifyResetCode(Request $request, UserAccountService $accounts): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $email = (string) session('password_reset_email');
        $codeHash = (string) session('password_reset_code_hash');
        $expiresAt = (int) session('password_reset_code_expires_at', 0);

        if ($email === '' || $codeHash === '' || $expiresAt < time() || ! hash_equals($codeHash, hash('sha256', $validated['code']))) {
            return redirect()
                ->route('password.code')
                ->with('auth_message', 'Verification code is incorrect or expired. Please request a new code.');
        }

        $token = $accounts->createPasswordResetToken($email);
        if (! $token) {
            return redirect()
                ->route('password.forgot')
                ->with('auth_message', 'This email is not registered. Please create an account first.');
        }

        session()->forget([
            'password_reset_email',
            'password_reset_code_hash',
            'password_reset_code_expires_at',
            'password_reset_debug_code',
        ]);

        return redirect()->route('password.reset', ['token' => $token]);
    }

    public function reset(string $token): View
    {
        return view('auth.reset', [
            'assetVersion' => AssetVersion::current(),
            'currentUser' => session('auth_user'),
            'token' => $token,
            'authMessage' => session('auth_message'),
        ]);
    }

    public function twoStep(): View|RedirectResponse
    {
        if (! session('pending_two_step_user')) {
            return redirect()->route('signin');
        }

        return view('auth.two-step', [
            'assetVersion' => AssetVersion::current(),
            'currentUser' => null,
            'authMessage' => session('auth_message'),
            'verificationCode' => session('pending_two_step_code'),
        ]);
    }

    public function verifyTwoStep(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $pendingUser = session('pending_two_step_user');
        $pendingCode = (string) session('pending_two_step_code');
        $expiresAt = (int) session('pending_two_step_expires_at', 0);

        if (! $pendingUser || $pendingCode === '' || $expiresAt < time() || ! hash_equals($pendingCode, $validated['code'])) {
            return redirect()
                ->route('two-step')
                ->with('auth_message', 'Verification code is incorrect or expired.');
        }

        session()->forget(['pending_two_step_user', 'pending_two_step_code', 'pending_two_step_expires_at']);
        session()->regenerate();
        session(['auth_user' => $pendingUser]);

        return redirect()->route('home');
    }

    public function updatePassword(Request $request, UserAccountService $accounts): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $account = $accounts->resetPassword($validated['token'], $validated['password']);
        if (! $account) {
            return redirect()
                ->route('password.forgot')
                ->with('auth_message', 'Password reset link is invalid or expired.');
        }

        return redirect()
            ->route('signin')
            ->with('auth_message', 'Password updated. Sign in with your new password.');
    }

    public function localSignin(Request $request): RedirectResponse
    {
        return $this->localAuth($request, 'signin');
    }

    public function localSignup(Request $request): RedirectResponse
    {
        return $this->localAuth($request, 'signup');
    }

    public function social(string $provider, Request $request): RedirectResponse
    {
        $provider = strtolower($provider);
        if (! in_array($provider, $this->supportedProviders, true)) {
            return redirect()->route('signin')->with('auth_message', 'This sign-in provider is not supported.');
        }

        if (! config("services.{$provider}.client_id") || ! config("services.{$provider}.client_secret")) {
            $label = ucfirst($provider);

            return redirect()
                ->route($request->query('next') === 'signup' ? 'signup' : 'signin')
                ->with('auth_message', "{$label} sign-in is not configured yet. Add {$label} client ID and client secret to .env.");
        }

        session(['auth_next' => $request->query('next') === 'signup' ? 'signup' : 'signin']);

        return Socialite::driver($provider)
            ->scopes($this->scopesFor($provider))
            ->redirect();
    }

    public function callback(string $provider, Request $request, UserAccountService $accounts): RedirectResponse
    {
        $provider = strtolower($provider);
        if (! in_array($provider, $this->supportedProviders, true)) {
            return redirect()->route('signin')->with('auth_message', 'This sign-in provider is not supported.');
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (InvalidStateException) {
            return redirect()
                ->route(session('auth_next', 'signin'))
                ->with('auth_message', ucfirst($provider) . ' sign-in expired. Please try again.');
        } catch (Throwable $exception) {
            Log::warning(ucfirst($provider) . ' OAuth callback failed.', [
                'message' => $exception->getMessage(),
            ]);

            return redirect()
                ->route(session('auth_next', 'signin'))
                ->with('auth_message', ucfirst($provider) . ' sign-in could not be completed.');
        }

        $mode = session('auth_next', 'signin');
        $socialAccount = $this->socialAccountData($provider, $socialUser, $request);

        try {
            $account = $accounts->createOrUpdateSocial($provider, $socialAccount);
        } catch (Throwable $exception) {
            return redirect()
                ->route($mode === 'signup' ? 'signup' : 'signin')
                ->with('auth_message', $exception->getMessage());
        }

        session()->forget('auth_next');

        return $this->completeAuthentication($accounts->sessionUser($account));
    }

    public function signout(): RedirectResponse
    {
        session()->forget(['auth_user', 'auth_next']);
        session()->invalidate();
        session()->regenerateToken();

        return redirect()->route('home');
    }

    private function authView(Request $request, string $mode, string $title, string $heading, string $subheading, string $primaryAction, string $alternateText, string $alternateHref, string $alternateLabel): View
    {
        return view('auth.form', [
            'assetVersion' => AssetVersion::current(),
            'pageTitle' => $title,
            'heading' => $heading,
            'subheading' => $subheading,
            'mode' => $mode,
            'primaryAction' => $primaryAction,
            'alternateText' => $alternateText,
            'alternateHref' => $alternateHref,
            'alternateLabel' => $alternateLabel,
            'authMessage' => session('auth_message'),
            'currentUser' => session('auth_user'),
        ]);
    }

    private function localAuth(Request $request, string $mode, ?UserAccountService $accounts = null): RedirectResponse
    {
        $accounts ??= app(UserAccountService::class);
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:1'],
            'first_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
        ]);

        $name = trim(($validated['first_name'] ?? '') . ' ' . ($validated['last_name'] ?? ''));
        if ($name === '') {
            $name = $validated['email'];
        }

        try {
            if ($mode === 'signup') {
                $account = $accounts->createLocal([
                    'email' => $validated['email'],
                    'password' => $validated['password'],
                    'name' => $name,
                ]);

                return redirect()
                    ->route('signin')
                    ->with('auth_message', 'Account created successfully. Please sign in with your email and password.');
            } else {
                $existingAccount = $accounts->findByEmail($validated['email']);
                if (! $existingAccount) {
                    return redirect()
                        ->route('signin')
                        ->with('auth_message', 'This email is not registered. Please create an account first.');
                }

                if (empty($existingAccount['password_hash'])) {
                    return redirect()
                        ->route('signin')
                        ->with('auth_message', 'This account was created with ' . ucfirst($existingAccount['provider'] ?? 'social login') . '. Continue with that provider or use forgot password to create a password.');
                }

                $account = $accounts->verifyLocal($validated['email'], $validated['password']);
                if (! $account) {
                    return redirect()
                        ->route('signin')
                        ->with('auth_message', 'Wrong password. Please enter the correct password.');
                }
            }
        } catch (Throwable $exception) {
            return redirect()
                ->route($mode === 'signup' ? 'signup' : 'signin')
                ->with('auth_message', $exception->getMessage());
        }

        session()->regenerate();
        return $this->completeAuthentication($accounts->sessionUser($account));
    }

    private function completeAuthentication(array $sessionUser): RedirectResponse
    {
        session()->regenerate();

        if (! empty($sessionUser['two_step_enabled'])) {
            session()->forget('auth_user');
            session([
                'pending_two_step_user' => $sessionUser,
                'pending_two_step_code' => (string) random_int(100000, 999999),
                'pending_two_step_expires_at' => time() + 300,
            ]);

            return redirect()->route('two-step');
        }

        session(['auth_user' => $sessionUser]);

        return redirect()->route('home');
    }

    private function sendPasswordResetCode(string $email, string $code): void
    {
        session()->forget('password_reset_debug_code');

        try {
            Mail::raw("Your FluxConvert password reset verification code is: {$code}\n\nThis code expires in 10 minutes.", function ($message) use ($email): void {
                $message->to($email)->subject('FluxConvert password reset code');
            });
        } catch (Throwable $exception) {
            Log::warning('Password reset code email could not be sent.', [
                'email' => $email,
                'message' => $exception->getMessage(),
            ]);

            if (app()->environment('local')) {
                session(['password_reset_debug_code' => $code]);
            }
        }
    }

    private function scopesFor(string $provider): array
    {
        return $provider === 'microsoft'
            ? ['openid', 'profile', 'email', 'User.Read']
            : ['openid', 'profile', 'email'];
    }

    private function socialAccountData(string $provider, mixed $socialUser, Request $request): array
    {
        $raw = is_array($socialUser->user ?? null) ? $socialUser->user : [];
        $email = $socialUser->getEmail()
            ?: ($raw['email'] ?? null)
            ?: ($raw['mail'] ?? null)
            ?: ($raw['userPrincipalName'] ?? null);
        $email = is_string($email) ? strtolower(trim($email)) : '';

        $givenName = trim((string) ($raw['given_name'] ?? $raw['givenName'] ?? ''));
        $familyName = trim((string) ($raw['family_name'] ?? $raw['surname'] ?? ''));
        $providerName = trim((string) (
            $socialUser->getName()
            ?: ($raw['name'] ?? null)
            ?: ($raw['displayName'] ?? null)
            ?: trim($givenName . ' ' . $familyName)
            ?: $socialUser->getNickname()
            ?: ''
        ));

        $avatar = $socialUser->getAvatar()
            ?: ($raw['picture'] ?? null)
            ?: ($raw['avatar_url'] ?? null);

        if (! $avatar && $provider === 'microsoft') {
            $avatar = $this->microsoftAvatarUrl((string) ($socialUser->token ?? ''), (string) $socialUser->getId(), $request);
        }

        return [
            'provider_id' => (string) $socialUser->getId(),
            'name' => $providerName ?: $this->displayNameFromEmail($email),
            'email' => $email,
            'avatar' => is_string($avatar) ? $avatar : null,
        ];
    }

    private function microsoftAvatarUrl(string $accessToken, string $providerId, Request $request): ?string
    {
        if ($accessToken === '' || $providerId === '') {
            return null;
        }

        try {
            $response = Http::withToken($accessToken)
                ->accept('image/jpeg')
                ->timeout(10)
                ->get('https://graph.microsoft.com/v1.0/me/photo/$value');

            if (! $response->successful() || $response->body() === '') {
                return null;
            }

            $directory = public_path('oauth-avatars');
            if (! is_dir($directory)) {
                mkdir($directory, 0775, true);
            }

            $filename = 'microsoft-' . hash('sha256', $providerId) . '.jpg';
            file_put_contents($directory . DIRECTORY_SEPARATOR . $filename, $response->body());

            return $request->getSchemeAndHttpHost() . '/oauth-avatars/' . $filename;
        } catch (Throwable $exception) {
            Log::info('Microsoft profile photo could not be loaded.', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function displayNameFromEmail(string $email): string
    {
        if ($email === '') {
            return 'Account';
        }

        $localPart = preg_replace('/[._-]+/', ' ', strstr($email, '@', true) ?: $email);

        return trim(ucwords($localPart)) ?: $email;
    }
}
