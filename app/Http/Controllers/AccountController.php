<?php

namespace App\Http\Controllers;

use App\Services\ConversionHistoryService;
use App\Services\UserAccountService;
use App\Support\AssetVersion;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function profile(ConversionHistoryService $historyService, UserAccountService $accounts): View|RedirectResponse
    {
        return $this->accountView('profile', $historyService, $accounts);
    }

    public function settings(ConversionHistoryService $historyService, UserAccountService $accounts): View|RedirectResponse
    {
        return $this->accountView('settings', $historyService, $accounts);
    }

    public function updateProfile(Request $request, UserAccountService $accounts): RedirectResponse
    {
        $currentUser = session('auth_user');
        if (! $currentUser) {
            return redirect()->route('signin');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'avatar_url' => ['nullable', 'url', 'max:500'],
            'avatar_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            'phone' => ['nullable', 'string', 'max:40'],
            'company' => ['nullable', 'string', 'max:120'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:120'],
            'timezone' => ['nullable', 'string', 'max:80'],
            'bio' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $validated['avatar'] = $currentUser['avatar'] ?? null;
            if ($request->hasFile('avatar_upload')) {
                $validated['avatar'] = $this->storeUploadedAvatar($request, $currentUser);
            } elseif (! empty($validated['avatar_url'])) {
                $validated['avatar'] = $this->storeAvatarFromUrl($validated['avatar_url'], $currentUser);
            }
            unset($validated['avatar_url'], $validated['avatar_upload']);

            $account = $accounts->updateProfile($currentUser, $validated);
        } catch (\Throwable $exception) {
            return redirect()->route('profile')->with('profile_error', $exception->getMessage());
        }

        if (! $account) {
            return redirect()->route('profile')->with('profile_error', 'Profile could not be updated.');
        }

        session(['auth_user' => $accounts->sessionUser($account)]);

        return redirect()->route('profile')->with('profile_message', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request, UserAccountService $accounts): RedirectResponse
    {
        $currentUser = session('auth_user');
        if (! $currentUser) {
            return redirect()->route('signin');
        }

        $validated = $request->validate([
            'current_password' => ['nullable', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        try {
            $account = $accounts->updatePassword($currentUser, $validated['current_password'] ?? null, $validated['password']);
        } catch (\Throwable $exception) {
            return redirect()->route('profile')->with('profile_error', $exception->getMessage());
        }

        if (! $account) {
            return redirect()->route('profile')->with('profile_error', 'Password could not be updated.');
        }

        session(['auth_user' => $accounts->sessionUser($account)]);

        return redirect()->route('profile')->with('profile_message', 'Password updated successfully.');
    }

    public function updateEmail(Request $request, UserAccountService $accounts): RedirectResponse
    {
        $currentUser = session('auth_user');
        if (! $currentUser) {
            return redirect()->route('signin');
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:160'],
            'current_password' => ['nullable', 'string'],
        ]);

        try {
            $account = $accounts->updateEmail($currentUser, $validated['email'], $validated['current_password'] ?? null);
        } catch (\Throwable $exception) {
            return redirect()->route('settings')->with('settings_error', $exception->getMessage());
        }

        if (! $account) {
            return redirect()->route('settings')->with('settings_error', 'Email address could not be updated.');
        }

        session(['auth_user' => $accounts->sessionUser($account)]);

        return redirect()->route('settings')->with('settings_message', 'Email address updated. Use the new email the next time you sign in.');
    }

    public function updateTwoStep(Request $request, UserAccountService $accounts): RedirectResponse
    {
        $currentUser = session('auth_user');
        if (! $currentUser) {
            return redirect()->route('signin');
        }

        $validated = $request->validate([
            'enabled' => ['nullable', 'in:1'],
            'current_password' => ['nullable', 'string'],
        ]);

        try {
            $account = $accounts->updateTwoStep($currentUser, ($validated['enabled'] ?? null) === '1', $validated['current_password'] ?? null);
        } catch (\Throwable $exception) {
            return redirect()->route('settings')->with('settings_error', $exception->getMessage());
        }

        if (! $account) {
            return redirect()->route('settings')->with('settings_error', 'Two-step verification could not be updated.');
        }

        session(['auth_user' => $accounts->sessionUser($account)]);

        return redirect()->route('settings')->with('settings_message', 'Two-step verification settings updated.');
    }

    private function accountView(string $page, ConversionHistoryService $historyService, UserAccountService $accounts): View|RedirectResponse
    {
        $currentUser = session('auth_user');
        if (! $currentUser) {
            return redirect()->route('signin');
        }

        $storedAccount = $accounts->findForSession($currentUser);
        if ($storedAccount) {
            $currentUser = $accounts->sessionUser($storedAccount);
            session(['auth_user' => $currentUser]);
        }

        $files = $historyService->list($currentUser);

        return view("account.{$page}", [
            'assetVersion' => AssetVersion::current(),
            'currentUser' => $currentUser,
            'files' => $files,
            'timezones' => $this->timezones(),
        ]);
    }

    private function timezones(): array
    {
        return Cache::remember('account.timezones.v1', now()->addDay(), function (): array {
            $now = new DateTimeImmutable('now');

            return collect(DateTimeZone::listIdentifiers())
                ->map(function (string $identifier) use ($now): array {
                    $zone = new DateTimeZone($identifier);
                    $offset = $zone->getOffset($now);
                    $hours = intdiv(abs($offset), 3600);
                    $minutes = intdiv(abs($offset) % 3600, 60);
                    $sign = $offset >= 0 ? '+' : '-';

                    return [
                        'value' => $identifier,
                        'label' => str_replace('_', ' ', $identifier) . " (UTC{$sign}" . str_pad((string) $hours, 2, '0', STR_PAD_LEFT) . ':' . str_pad((string) $minutes, 2, '0', STR_PAD_LEFT) . ')',
                        'offset' => $offset,
                    ];
                })
                ->sortBy([['offset', 'asc'], ['label', 'asc']])
                ->values()
                ->all();
        });
    }

    private function storeUploadedAvatar(Request $request, array $currentUser): string
    {
        $file = $request->file('avatar_upload');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $filename = $this->avatarFilename($currentUser, $extension);
        $file->move($this->avatarDirectory(), $filename);

        return '/profile-avatars/' . $filename;
    }

    private function storeAvatarFromUrl(string $url, array $currentUser): string
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 FluxConvert Profile Image Fetcher',
        ])->timeout(12)->get($url);
        $contentType = strtolower((string) $response->header('Content-Type'));

        if (! $response->successful() || $response->body() === '') {
            throw new \RuntimeException('Profile picture URL could not be loaded. Upload the image file or paste another image URL.');
        }

        if (! str_starts_with($contentType, 'image/')) {
            $imageUrl = $this->extractImageUrlFromHtml($url, $response->body());
            if (! $imageUrl) {
                throw new \RuntimeException('That link is a web page, not an image. Open the image in a new tab, copy its image address, or upload the file.');
            }

            return $this->storeAvatarFromUrl($imageUrl, $currentUser);
        }

        $extension = match (true) {
            str_contains($contentType, 'png') => 'png',
            str_contains($contentType, 'webp') => 'webp',
            str_contains($contentType, 'gif') => 'gif',
            default => 'jpg',
        };

        $filename = $this->avatarFilename($currentUser, $extension);
        file_put_contents($this->avatarDirectory() . DIRECTORY_SEPARATOR . $filename, $response->body());

        return '/profile-avatars/' . $filename;
    }

    private function extractImageUrlFromHtml(string $pageUrl, string $html): ?string
    {
        $patterns = [
            '/<meta[^>]+property=["\']og:image(?::secure_url)?["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image(?::secure_url)?["\']/i',
            '/<meta[^>]+name=["\']twitter:image(?::src)?["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']twitter:image(?::src)?["\']/i',
            '/<img[^>]+src=["\']([^"\']+)["\']/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                return $this->absoluteUrl(html_entity_decode($matches[1]), $pageUrl);
            }
        }

        return null;
    }

    private function absoluteUrl(string $url, string $baseUrl): string
    {
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $base = parse_url($baseUrl);
        if (! $base || empty($base['scheme']) || empty($base['host'])) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            return $base['scheme'] . ':' . $url;
        }

        if (str_starts_with($url, '/')) {
            return $base['scheme'] . '://' . $base['host'] . $url;
        }

        $path = $base['path'] ?? '/';
        $directory = rtrim(str_replace('\\', '/', dirname($path)), '/');

        return $base['scheme'] . '://' . $base['host'] . ($directory ? '/' . ltrim($directory, '/') : '') . '/' . ltrim($url, '/');
    }

    private function avatarDirectory(): string
    {
        $directory = public_path('profile-avatars');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        return $directory;
    }

    private function avatarFilename(array $currentUser, string $extension): string
    {
        $key = ($currentUser['provider'] ?? 'local') . ':' . ($currentUser['provider_id'] ?? $currentUser['email'] ?? uniqid('', true));

        return 'profile-' . hash('sha256', $key) . '-' . time() . '.' . preg_replace('/[^a-z0-9]/i', '', $extension);
    }
}
