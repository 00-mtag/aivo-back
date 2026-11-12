<?php

namespace App\Services;

use Google\Client;
use LogicException;

class GoogleIdentityVerifier
{
    public function verify(string $credential): ?array
    {
        $clientId = config('services.google.client_id');

        if (! is_string($clientId) || $clientId === '') {
            throw new LogicException('Google sign-in requires GOOGLE_CLIENT_ID to be configured.');
        }

        $client = new Client(['client_id' => $clientId]);
        $identity = $client->verifyIdToken($credential);

        if (! is_array($identity) || ($identity['aud'] ?? null) !== $clientId) {
            return null;
        }

        return [
            'name' => $identity['name'] ?? null,
            'email' => $identity['email'] ?? null,
            'email_verified' => $identity['email_verified'] ?? false,
        ];
    }
}
