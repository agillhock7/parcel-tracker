<?php
declare(strict_types=1);

namespace App\Auth;

use RuntimeException;

final class OAuthService
{
    private const PROVIDERS = [
        'github' => 'GitHub',
        'discord' => 'Discord',
    ];

    public function __construct(
        private UserRepository $users,
        private string $baseUrl,
        private string $logDir
    ) {
    }

    /** @return array<string, array{label:string,start_path:string}> */
    public function availableProviders(): array
    {
        $out = [];
        foreach (self::PROVIDERS as $provider => $label) {
            if ($this->isConfigured($provider)) {
                $out[$provider] = [
                    'label' => $label,
                    'start_path' => '/auth/' . $provider . '/start',
                ];
            }
        }
        return $out;
    }

    /** @return array{ok:bool,error?:string,redirect?:string} */
    public function start(string $provider): array
    {
        $cfg = $this->providerConfig($provider);
        if (!$cfg) {
            return ['ok' => false, 'error' => 'OAuth provider is not configured.'];
        }

        $state = bin2hex(random_bytes(16));
        $_SESSION[$this->stateKey($provider)] = $state;
        $_SESSION[$this->stateKey($provider) . '_created_at'] = time();

        $params = [
            'client_id' => $cfg['client_id'],
            'redirect_uri' => $cfg['redirect_uri'],
            'response_type' => 'code',
            'scope' => $cfg['scope'],
            'state' => $state,
        ];
        if ($provider === 'github') {
            $params['allow_signup'] = 'true';
        }
        if ($provider === 'discord') {
            $params['prompt'] = 'consent';
        }

        return [
            'ok' => true,
            'redirect' => $cfg['authorize_url'] . '?' . http_build_query($params),
        ];
    }

    /** @return array{ok:bool,error?:string,user_id?:int} */
    public function callback(string $provider, string $code, string $state): array
    {
        $provider = strtolower(trim($provider));
        $code = trim($code);
        $state = trim($state);

        if ($code === '' || $state === '') {
            return ['ok' => false, 'error' => 'Authorization code missing from provider callback.'];
        }
        if (!$this->consumeValidState($provider, $state)) {
            return ['ok' => false, 'error' => 'OAuth state mismatch. Please try again.'];
        }

        $cfg = $this->providerConfig($provider);
        if (!$cfg) {
            return ['ok' => false, 'error' => 'OAuth provider is not configured.'];
        }

        try {
            $token = $this->exchangeCodeForToken($code, $cfg);
            $profile = $this->fetchProfile($provider, $token);
            if (($profile['provider_user_id'] ?? '') === '') {
                return ['ok' => false, 'error' => 'OAuth profile is missing a provider ID.'];
            }

            $providerUserId = (string)$profile['provider_user_id'];
            $email = $this->normalizeEmailNullable($profile['email'] ?? null);
            $name = $this->normalizeDisplayName((string)($profile['name'] ?? ''));
            $avatarUrl = $this->normalizeAvatar((string)($profile['avatar_url'] ?? ''));

            $user = $this->users->findByOAuthAccount($provider, $providerUserId);
            if ($user) {
                $userId = (int)$user['id'];
                $this->users->updateProfileFromOAuth($userId, $name, $avatarUrl);
                return ['ok' => true, 'user_id' => $userId];
            }

            if ($email !== null) {
                $user = $this->users->findByEmail($email);
            } else {
                $user = null;
            }

            if ($user) {
                $userId = (int)$user['id'];
            } else {
                $emailForCreate = $email ?? $this->fallbackEmail($provider, $providerUserId);
                $passwordHash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
                if (!is_string($passwordHash) || $passwordHash === '') {
                    throw new RuntimeException('Unable to create OAuth password hash.');
                }

                $userId = $this->users->create(
                    $name !== '' ? $name : ucfirst($provider) . ' user',
                    $emailForCreate,
                    $passwordHash,
                    $avatarUrl
                );
            }

            $this->users->linkOAuthAccount($userId, $provider, $providerUserId, $email);
            $this->users->updateProfileFromOAuth($userId, $name, $avatarUrl);

            return ['ok' => true, 'user_id' => $userId];
        } catch (\Throwable $e) {
            $this->log('oauth-callback error [' . $provider . ']: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Unable to complete social login right now.'];
        }
    }

    public function isConfigured(string $provider): bool
    {
        return $this->providerConfig($provider) !== null;
    }

    /** @param array<string,string> $cfg */
    private function exchangeCodeForToken(string $code, array $cfg): string
    {
        $body = http_build_query([
            'client_id' => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'code' => $code,
            'redirect_uri' => $cfg['redirect_uri'],
            'grant_type' => 'authorization_code',
        ]);

        $res = $this->requestJson(
            'POST',
            $cfg['token_url'],
            [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
                'User-Agent: ParcelTracker/1.0',
            ],
            $body
        );
        if ($res['status'] < 200 || $res['status'] >= 300) {
            throw new RuntimeException('Token request failed with status ' . $res['status']);
        }

        $token = (string)($res['json']['access_token'] ?? '');
        if ($token === '') {
            throw new RuntimeException('Provider response did not include access_token.');
        }

        return $token;
    }

    /** @return array{provider_user_id:string,name:string,email:?string,avatar_url:?string} */
    private function fetchProfile(string $provider, string $accessToken): array
    {
        if ($provider === 'github') {
            $profileRes = $this->requestJson(
                'GET',
                'https://api.github.com/user',
                [
                    'Accept: application/vnd.github+json',
                    'Authorization: Bearer ' . $accessToken,
                    'User-Agent: ParcelTracker/1.0',
                ],
                null
            );
            if ($profileRes['status'] < 200 || $profileRes['status'] >= 300) {
                throw new RuntimeException('GitHub profile request failed with status ' . $profileRes['status']);
            }

            $json = $profileRes['json'];
            $id = (string)($json['id'] ?? '');
            $name = trim((string)($json['name'] ?? $json['login'] ?? ''));
            $email = $this->normalizeEmailNullable($json['email'] ?? null);
            if ($email === null) {
                $email = $this->fetchGithubPrimaryEmail($accessToken);
            }
            $avatar = $this->normalizeAvatar((string)($json['avatar_url'] ?? ''));

            return [
                'provider_user_id' => $id,
                'name' => $name,
                'email' => $email,
                'avatar_url' => $avatar,
            ];
        }

        if ($provider === 'discord') {
            $profileRes = $this->requestJson(
                'GET',
                'https://discord.com/api/users/@me',
                [
                    'Accept: application/json',
                    'Authorization: Bearer ' . $accessToken,
                    'User-Agent: ParcelTracker/1.0',
                ],
                null
            );
            if ($profileRes['status'] < 200 || $profileRes['status'] >= 300) {
                throw new RuntimeException('Discord profile request failed with status ' . $profileRes['status']);
            }

            $json = $profileRes['json'];
            $id = (string)($json['id'] ?? '');
            $name = trim((string)($json['global_name'] ?? $json['username'] ?? ''));
            $email = $this->normalizeEmailNullable($json['email'] ?? null);
            $avatarHash = trim((string)($json['avatar'] ?? ''));
            $avatar = null;
            if ($id !== '' && $avatarHash !== '') {
                $avatar = $this->normalizeAvatar('https://cdn.discordapp.com/avatars/' . rawurlencode($id) . '/' . rawurlencode($avatarHash) . '.png');
            }

            return [
                'provider_user_id' => $id,
                'name' => $name,
                'email' => $email,
                'avatar_url' => $avatar,
            ];
        }

        throw new RuntimeException('Unsupported provider: ' . $provider);
    }

    private function fetchGithubPrimaryEmail(string $accessToken): ?string
    {
        $res = $this->requestJson(
            'GET',
            'https://api.github.com/user/emails',
            [
                'Accept: application/vnd.github+json',
                'Authorization: Bearer ' . $accessToken,
                'User-Agent: ParcelTracker/1.0',
            ],
            null
        );
        if ($res['status'] < 200 || $res['status'] >= 300 || !is_array($res['json'])) {
            return null;
        }

        foreach ($res['json'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $email = $this->normalizeEmailNullable($row['email'] ?? null);
            if ($email === null) {
                continue;
            }
            $isPrimary = (bool)($row['primary'] ?? false);
            $isVerified = (bool)($row['verified'] ?? false);
            if ($isPrimary && $isVerified) {
                return $email;
            }
        }

        foreach ($res['json'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $email = $this->normalizeEmailNullable($row['email'] ?? null);
            if ($email !== null) {
                return $email;
            }
        }
        return null;
    }

    /** @param array<int,string> $headers */
    private function requestJson(string $method, string $url, array $headers, ?string $body): array
    {
        $opts = [
            'http' => [
                'method' => strtoupper($method),
                'ignore_errors' => true,
                'timeout' => 18,
                'header' => implode("\r\n", $headers) . "\r\n",
            ],
        ];
        if ($body !== null) {
            $opts['http']['content'] = $body;
        }

        $context = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context);
        $status = $this->extractStatusCode($http_response_header ?? null);
        $json = [];
        if (is_string($response) && $response !== '') {
            $decoded = json_decode($response, true);
            if (is_array($decoded)) {
                $json = $decoded;
            }
        }

        return [
            'status' => $status,
            'json' => $json,
            'raw' => is_string($response) ? $response : '',
        ];
    }

    private function extractStatusCode($responseHeaders): int
    {
        if (!is_array($responseHeaders)) {
            return 0;
        }
        foreach ($responseHeaders as $line) {
            if (!is_string($line)) {
                continue;
            }
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                return (int)$m[1];
            }
        }
        return 0;
    }

    /** @return array<string,string>|null */
    private function providerConfig(string $provider): ?array
    {
        $provider = strtolower(trim($provider));
        if (!isset(self::PROVIDERS[$provider])) {
            return null;
        }

        if ($provider === 'github') {
            $clientId = trim((string)getenv('OAUTH_GITHUB_CLIENT_ID'));
            $clientSecret = trim((string)getenv('OAUTH_GITHUB_CLIENT_SECRET'));
            $redirectUri = trim((string)getenv('OAUTH_GITHUB_REDIRECT_URI'));
            if ($redirectUri === '') {
                $redirectUri = rtrim($this->baseUrl, '/') . '/auth/github/callback';
            }
            if ($clientId === '' || $clientSecret === '') {
                return null;
            }
            return [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'authorize_url' => 'https://github.com/login/oauth/authorize',
                'token_url' => 'https://github.com/login/oauth/access_token',
                'scope' => 'read:user user:email',
            ];
        }

        if ($provider === 'discord') {
            $clientId = trim((string)getenv('OAUTH_DISCORD_CLIENT_ID'));
            $clientSecret = trim((string)getenv('OAUTH_DISCORD_CLIENT_SECRET'));
            $redirectUri = trim((string)getenv('OAUTH_DISCORD_REDIRECT_URI'));
            if ($redirectUri === '') {
                $redirectUri = rtrim($this->baseUrl, '/') . '/auth/discord/callback';
            }
            if ($clientId === '' || $clientSecret === '') {
                return null;
            }
            return [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'authorize_url' => 'https://discord.com/oauth2/authorize',
                'token_url' => 'https://discord.com/api/oauth2/token',
                'scope' => 'identify email',
            ];
        }

        return null;
    }

    private function consumeValidState(string $provider, string $state): bool
    {
        $key = $this->stateKey($provider);
        $expected = (string)($_SESSION[$key] ?? '');
        $createdAt = (int)($_SESSION[$key . '_created_at'] ?? 0);

        unset($_SESSION[$key], $_SESSION[$key . '_created_at']);
        if ($expected === '' || $state === '') {
            return false;
        }
        if ($createdAt <= 0 || (time() - $createdAt) > 900) {
            return false;
        }
        return hash_equals($expected, $state);
    }

    private function stateKey(string $provider): string
    {
        return 'oauth_state_' . strtolower(trim($provider));
    }

    private function normalizeEmailNullable($email): ?string
    {
        $email = strtolower(trim((string)$email));
        if ($email === '') {
            return null;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        return substr($email, 0, 190);
    }

    private function normalizeDisplayName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }
        return substr($name, 0, 80);
    }

    private function normalizeAvatar(?string $avatar): ?string
    {
        $avatar = trim((string)$avatar);
        if ($avatar === '') {
            return null;
        }
        if (!preg_match('#^https?://#i', $avatar)) {
            return null;
        }
        return substr($avatar, 0, 255);
    }

    private function fallbackEmail(string $provider, string $providerUserId): string
    {
        $safeId = preg_replace('/[^a-zA-Z0-9]/', '', $providerUserId) ?? '';
        if ($safeId === '') {
            $safeId = bin2hex(random_bytes(8));
        }
        return strtolower($provider) . '_' . strtolower(substr($safeId, 0, 64)) . '@oauth.local';
    }

    private function log(string $message): void
    {
        $line = '[' . gmdate('c') . '] ' . $message . "\n";
        @file_put_contents($this->logDir . '/app.log', $line, FILE_APPEND);
    }
}
