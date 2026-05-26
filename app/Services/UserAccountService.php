<?php

namespace App\Services;

class UserAccountService
{
    private string $path;

    public function __construct()
    {
        $this->path = storage_path('app/user-accounts.json');
    }

    public function createLocal(array $data): array
    {
        $email = strtolower(trim((string) $data['email']));
        $name = trim((string) ($data['name'] ?? $email)) ?: $email;

        return $this->withData(function (array $accounts) use ($email, $name, $data): array {
            if ($this->findByEmailIn($accounts, $email)) {
                throw new \RuntimeException('Account already exists. Please sign in with the correct email and password.');
            }

            $account = [
                'id' => hash('sha256', 'local:' . $email),
                'provider' => 'local',
                'provider_id' => $email,
                'name' => $name,
                'email' => $email,
                'avatar' => null,
                'password_hash' => password_hash((string) $data['password'], PASSWORD_DEFAULT),
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ];

            $accounts[] = $account;

            return [$accounts, $account];
        });
    }

    public function createOrUpdateSocial(string $provider, array $data): array
    {
        $provider = strtolower($provider);
        $providerId = (string) $data['provider_id'];
        $email = strtolower(trim((string) ($data['email'] ?? '')));

        return $this->withData(function (array $accounts) use ($provider, $providerId, $email, $data): array {
            foreach ($accounts as $index => $account) {
                $sameSocial = ($account['provider'] ?? '') === $provider && (string) ($account['provider_id'] ?? '') === $providerId;
                $sameEmail = $email !== '' && strtolower((string) ($account['email'] ?? '')) === $email;
                if (! $sameSocial && ! $sameEmail) {
                    continue;
                }

                $accounts[$index]['provider'] = $provider;
                $accounts[$index]['provider_id'] = $providerId;
                $accounts[$index]['name'] = (string) ($data['name'] ?: $account['name'] ?? $email ?: 'Account');
                $accounts[$index]['email'] = $email ?: ($account['email'] ?? '');
                $accounts[$index]['avatar'] = $data['avatar'] ?? ($account['avatar'] ?? null);
                $accounts[$index]['updated_at'] = now()->toISOString();

                return [$accounts, $accounts[$index]];
            }

            $account = [
                'id' => hash('sha256', "{$provider}:{$providerId}"),
                'provider' => $provider,
                'provider_id' => $providerId,
                'name' => (string) ($data['name'] ?: $email ?: 'Account'),
                'email' => $email,
                'avatar' => $data['avatar'] ?? null,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ];

            $accounts[] = $account;

            return [$accounts, $account];
        });
    }

    public function verifyLocal(string $email, string $password): ?array
    {
        $email = strtolower(trim($email));
        $accounts = $this->read();
        foreach ($accounts as $account) {
            if (strtolower((string) ($account['email'] ?? '')) === $email
                && ! empty($account['password_hash'])
                && password_verify($password, (string) ($account['password_hash'] ?? ''))
            ) {
                return $account;
            }
        }

        return null;
    }

    public function localExists(string $email): bool
    {
        $email = strtolower(trim($email));
        foreach ($this->read() as $account) {
            if (($account['provider'] ?? '') === 'local' && strtolower((string) ($account['email'] ?? '')) === $email) {
                return true;
            }
        }

        return false;
    }

    public function findByEmail(string $email): ?array
    {
        return $this->findByEmailIn($this->read(), strtolower(trim($email)));
    }

    public function findForSession(array $sessionUser): ?array
    {
        $provider = (string) ($sessionUser['provider'] ?? '');
        $providerId = (string) ($sessionUser['provider_id'] ?? '');
        $email = strtolower((string) ($sessionUser['email'] ?? ''));

        foreach ($this->read() as $account) {
            $sameProvider = ($account['provider'] ?? '') === $provider && (string) ($account['provider_id'] ?? '') === $providerId;
            $sameEmail = $email !== '' && strtolower((string) ($account['email'] ?? '')) === $email;
            if ($sameProvider || $sameEmail) {
                return $account;
            }
        }

        return null;
    }

    public function ensureFromSession(array $sessionUser): ?array
    {
        $email = strtolower(trim((string) ($sessionUser['email'] ?? '')));
        if ($email === '') {
            return null;
        }

        $provider = trim((string) ($sessionUser['provider'] ?? 'local')) ?: 'local';
        $providerId = trim((string) ($sessionUser['provider_id'] ?? $email)) ?: $email;
        $name = trim((string) ($sessionUser['name'] ?? $email)) ?: $email;

        return $this->withData(function (array $accounts) use ($email, $provider, $providerId, $name, $sessionUser): array {
            foreach ($accounts as $index => $account) {
                $sameProvider = ($account['provider'] ?? '') === $provider && (string) ($account['provider_id'] ?? '') === $providerId;
                $sameEmail = strtolower((string) ($account['email'] ?? '')) === $email;
                if (! $sameProvider && ! $sameEmail) {
                    continue;
                }

                $accounts[$index]['provider'] = $provider;
                $accounts[$index]['provider_id'] = $providerId;
                $accounts[$index]['name'] = $name;
                $accounts[$index]['email'] = $email;
                $accounts[$index]['avatar'] = $sessionUser['avatar'] ?? ($accounts[$index]['avatar'] ?? null);
                $accounts[$index]['updated_at'] = now()->toISOString();

                return [$accounts, $accounts[$index]];
            }

            $account = [
                'id' => hash('sha256', "{$provider}:{$providerId}"),
                'provider' => $provider,
                'provider_id' => $providerId,
                'name' => $name,
                'email' => $email,
                'avatar' => $sessionUser['avatar'] ?? null,
                'phone' => $sessionUser['phone'] ?? null,
                'company' => $sessionUser['company'] ?? null,
                'job_title' => $sessionUser['job_title'] ?? null,
                'location' => $sessionUser['location'] ?? null,
                'timezone' => $sessionUser['timezone'] ?? null,
                'bio' => $sessionUser['bio'] ?? null,
                'two_step_enabled' => (bool) ($sessionUser['two_step_enabled'] ?? false),
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ];

            $accounts[] = $account;

            return [$accounts, $account];
        });
    }

    public function createPasswordResetToken(string $email): ?string
    {
        $email = strtolower(trim($email));
        $token = bin2hex(random_bytes(32));
        $expiresAt = now()->addMinutes(30)->toISOString();

        return $this->withData(function (array $accounts) use ($email, $token, $expiresAt): array {
            foreach ($accounts as $index => $account) {
                if (strtolower((string) ($account['email'] ?? '')) === $email) {
                    $accounts[$index]['reset_token_hash'] = hash('sha256', $token);
                    $accounts[$index]['reset_token_expires_at'] = $expiresAt;
                    $accounts[$index]['updated_at'] = now()->toISOString();

                    return [$accounts, ['token' => $token]];
                }
            }

            return [$accounts, ['token' => null]];
        })['token'] ?? null;
    }

    public function resetPassword(string $token, string $password): ?array
    {
        $tokenHash = hash('sha256', $token);

        return $this->withData(function (array $accounts) use ($tokenHash, $password): array {
            foreach ($accounts as $index => $account) {
                if (($account['reset_token_hash'] ?? '') !== $tokenHash) {
                    continue;
                }

                $expiresAt = strtotime((string) ($account['reset_token_expires_at'] ?? ''));
                if (! $expiresAt || $expiresAt < time()) {
                    return [$accounts, null];
                }

                $accounts[$index]['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                unset($accounts[$index]['reset_token_hash'], $accounts[$index]['reset_token_expires_at']);
                $accounts[$index]['updated_at'] = now()->toISOString();

                return [$accounts, $accounts[$index]];
            }

            return [$accounts, null];
        });
    }

    public function createSocial(string $provider, array $data): array
    {
        $provider = strtolower($provider);
        $providerId = (string) $data['provider_id'];
        $email = strtolower(trim((string) ($data['email'] ?? '')));

        return $this->withData(function (array $accounts) use ($provider, $providerId, $email, $data): array {
            if ($this->findSocialIn($accounts, $provider, $providerId) || ($email !== '' && $this->findByEmailIn($accounts, $email))) {
                throw new \RuntimeException('Account already exists. Please sign in instead.');
            }

            $account = [
                'id' => hash('sha256', "{$provider}:{$providerId}"),
                'provider' => $provider,
                'provider_id' => $providerId,
                'name' => (string) ($data['name'] ?: $email ?: 'Account'),
                'email' => $email,
                'avatar' => $data['avatar'] ?? null,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ];

            $accounts[] = $account;

            return [$accounts, $account];
        });
    }

    public function findSocial(string $provider, string $providerId, ?string $email = null): ?array
    {
        $provider = strtolower($provider);
        $email = strtolower(trim((string) $email));
        foreach ($this->read() as $account) {
            if (($account['provider'] ?? '') === $provider && (string) ($account['provider_id'] ?? '') === $providerId) {
                return $account;
            }
            if ($email !== '' && ($account['provider'] ?? '') === $provider && strtolower((string) ($account['email'] ?? '')) === $email) {
                return $account;
            }
        }

        return null;
    }

    public function sessionUser(array $account): array
    {
        return [
            'provider' => $account['provider'] ?? 'local',
            'provider_id' => $account['provider_id'] ?? $account['email'] ?? '',
            'name' => $account['name'] ?? $account['email'] ?? 'Account',
            'email' => $account['email'] ?? null,
            'avatar' => $account['avatar'] ?? null,
            'phone' => $account['phone'] ?? null,
            'company' => $account['company'] ?? null,
            'job_title' => $account['job_title'] ?? null,
            'location' => $account['location'] ?? null,
            'timezone' => $account['timezone'] ?? null,
            'bio' => $account['bio'] ?? null,
            'two_step_enabled' => (bool) ($account['two_step_enabled'] ?? false),
            'demo' => false,
        ];
    }

    public function updateProfile(array $sessionUser, array $data): ?array
    {
        $userProvider = (string) ($sessionUser['provider'] ?? '');
        $userProviderId = (string) ($sessionUser['provider_id'] ?? '');
        $currentEmail = strtolower((string) ($sessionUser['email'] ?? ''));
        $newEmail = strtolower(trim((string) ($data['email'] ?? '')));

        return $this->withData(function (array $accounts) use ($userProvider, $userProviderId, $currentEmail, $newEmail, $data): array {
            $targetIndex = null;
            foreach ($accounts as $index => $account) {
                $sameProvider = ($account['provider'] ?? '') === $userProvider && (string) ($account['provider_id'] ?? '') === $userProviderId;
                $sameEmail = $currentEmail !== '' && strtolower((string) ($account['email'] ?? '')) === $currentEmail;
                if ($sameProvider || $sameEmail) {
                    $targetIndex = $index;
                    break;
                }
            }

            if ($targetIndex === null) {
                $account = [
                    'id' => hash('sha256', ($userProvider ?: 'local') . ':' . ($userProviderId ?: $newEmail)),
                    'provider' => $userProvider ?: 'local',
                    'provider_id' => $userProviderId ?: $newEmail,
                    'name' => trim((string) ($data['name'] ?? '')) ?: $newEmail ?: 'Account',
                    'email' => $newEmail,
                    'avatar' => trim((string) ($data['avatar'] ?? '')) ?: null,
                    'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
                    'company' => trim((string) ($data['company'] ?? '')) ?: null,
                    'job_title' => trim((string) ($data['job_title'] ?? '')) ?: null,
                    'location' => trim((string) ($data['location'] ?? '')) ?: null,
                    'timezone' => trim((string) ($data['timezone'] ?? '')) ?: null,
                    'bio' => trim((string) ($data['bio'] ?? '')) ?: null,
                    'created_at' => now()->toISOString(),
                    'updated_at' => now()->toISOString(),
                ];
                $accounts[] = $account;

                return [$accounts, $account];
            }

            foreach ($accounts as $index => $account) {
                if ($index !== $targetIndex && $newEmail !== '' && strtolower((string) ($account['email'] ?? '')) === $newEmail) {
                    throw new \RuntimeException('This email is already used by another account.');
                }
            }

            $accounts[$targetIndex]['name'] = trim((string) ($data['name'] ?? '')) ?: ($accounts[$targetIndex]['name'] ?? $newEmail);
            $accounts[$targetIndex]['email'] = $newEmail;
            $accounts[$targetIndex]['avatar'] = trim((string) ($data['avatar'] ?? '')) ?: null;
            $accounts[$targetIndex]['phone'] = trim((string) ($data['phone'] ?? '')) ?: null;
            $accounts[$targetIndex]['company'] = trim((string) ($data['company'] ?? '')) ?: null;
            $accounts[$targetIndex]['job_title'] = trim((string) ($data['job_title'] ?? '')) ?: null;
            $accounts[$targetIndex]['location'] = trim((string) ($data['location'] ?? '')) ?: null;
            $accounts[$targetIndex]['timezone'] = trim((string) ($data['timezone'] ?? '')) ?: null;
            $accounts[$targetIndex]['bio'] = trim((string) ($data['bio'] ?? '')) ?: null;
            $accounts[$targetIndex]['updated_at'] = now()->toISOString();

            return [$accounts, $accounts[$targetIndex]];
        });
    }

    public function updatePassword(array $sessionUser, ?string $currentPassword, string $newPassword): ?array
    {
        $userProvider = (string) ($sessionUser['provider'] ?? '');
        $userProviderId = (string) ($sessionUser['provider_id'] ?? '');
        $currentEmail = strtolower((string) ($sessionUser['email'] ?? ''));

        return $this->withData(function (array $accounts) use ($userProvider, $userProviderId, $currentEmail, $currentPassword, $newPassword): array {
            foreach ($accounts as $index => $account) {
                $sameProvider = ($account['provider'] ?? '') === $userProvider && (string) ($account['provider_id'] ?? '') === $userProviderId;
                $sameEmail = $currentEmail !== '' && strtolower((string) ($account['email'] ?? '')) === $currentEmail;
                if (! $sameProvider && ! $sameEmail) {
                    continue;
                }

                if (! empty($account['password_hash']) && ! password_verify((string) $currentPassword, (string) $account['password_hash'])) {
                    throw new \RuntimeException('Current password is incorrect.');
                }

                $accounts[$index]['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                $accounts[$index]['updated_at'] = now()->toISOString();

                return [$accounts, $accounts[$index]];
            }

            return [$accounts, null];
        });
    }

    public function updateEmail(array $sessionUser, string $newEmail, ?string $currentPassword): ?array
    {
        $userProvider = (string) ($sessionUser['provider'] ?? '');
        $userProviderId = (string) ($sessionUser['provider_id'] ?? '');
        $currentEmail = strtolower((string) ($sessionUser['email'] ?? ''));
        $newEmail = strtolower(trim($newEmail));

        return $this->withData(function (array $accounts) use ($userProvider, $userProviderId, $currentEmail, $newEmail, $currentPassword): array {
            $targetIndex = null;
            foreach ($accounts as $index => $account) {
                $sameProvider = ($account['provider'] ?? '') === $userProvider && (string) ($account['provider_id'] ?? '') === $userProviderId;
                $sameEmail = $currentEmail !== '' && strtolower((string) ($account['email'] ?? '')) === $currentEmail;
                if ($sameProvider || $sameEmail) {
                    $targetIndex = $index;
                    break;
                }
            }

            if ($targetIndex === null) {
                return [$accounts, null];
            }

            foreach ($accounts as $index => $account) {
                if ($index !== $targetIndex && strtolower((string) ($account['email'] ?? '')) === $newEmail) {
                    throw new \RuntimeException('This email is already used by another account.');
                }
            }

            if (! empty($accounts[$targetIndex]['password_hash']) && ! password_verify((string) $currentPassword, (string) $accounts[$targetIndex]['password_hash'])) {
                throw new \RuntimeException('Current password is incorrect.');
            }

            $accounts[$targetIndex]['email'] = $newEmail;
            $accounts[$targetIndex]['updated_at'] = now()->toISOString();

            return [$accounts, $accounts[$targetIndex]];
        });
    }

    public function updateTwoStep(array $sessionUser, bool $enabled, ?string $currentPassword): ?array
    {
        $userProvider = (string) ($sessionUser['provider'] ?? '');
        $userProviderId = (string) ($sessionUser['provider_id'] ?? '');
        $currentEmail = strtolower((string) ($sessionUser['email'] ?? ''));

        return $this->withData(function (array $accounts) use ($userProvider, $userProviderId, $currentEmail, $enabled, $currentPassword): array {
            foreach ($accounts as $index => $account) {
                $sameProvider = ($account['provider'] ?? '') === $userProvider && (string) ($account['provider_id'] ?? '') === $userProviderId;
                $sameEmail = $currentEmail !== '' && strtolower((string) ($account['email'] ?? '')) === $currentEmail;
                if (! $sameProvider && ! $sameEmail) {
                    continue;
                }

                if (! empty($account['password_hash']) && ! password_verify((string) $currentPassword, (string) $account['password_hash'])) {
                    throw new \RuntimeException('Current password is incorrect.');
                }

                $accounts[$index]['two_step_enabled'] = $enabled;
                $accounts[$index]['updated_at'] = now()->toISOString();

                return [$accounts, $accounts[$index]];
            }

            return [$accounts, null];
        });
    }

    private function read(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($this->path), true);

        return $this->normalizeAccounts($data);
    }

    private function withData(callable $callback): mixed
    {
        $directory = dirname($this->path);
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $handle = fopen($this->path, 'c+');
        if (! $handle) {
            throw new \RuntimeException('Unable to open user account store.');
        }

        try {
            flock($handle, LOCK_EX);
            $contents = stream_get_contents($handle);
            $accounts = json_decode($contents !== false ? $contents : '', true);
            $accounts = $this->normalizeAccounts($accounts);
            [$accounts, $account] = $callback($accounts);
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $account;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function findByEmailIn(array $accounts, string $email): ?array
    {
        foreach ($accounts as $account) {
            if (strtolower((string) ($account['email'] ?? '')) === $email) {
                return $account;
            }
        }

        return null;
    }

    private function findSocialIn(array $accounts, string $provider, string $providerId): ?array
    {
        foreach ($accounts as $account) {
            if (($account['provider'] ?? '') === $provider && (string) ($account['provider_id'] ?? '') === $providerId) {
                return $account;
            }
        }

        return null;
    }

    private function normalizeAccounts(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        if (isset($data['value']) && is_array($data['value'])) {
            return $this->normalizeAccounts($data['value']);
        }

        if (isset($data['id']) || isset($data['email']) || isset($data['provider'])) {
            return [$data];
        }

        return array_values(array_filter($data, 'is_array'));
    }
}
