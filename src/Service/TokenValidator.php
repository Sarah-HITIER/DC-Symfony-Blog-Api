<?php

namespace App\Service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class TokenValidator
{
    private $jwtSecret;

    public function __construct(string $jwtSecret)
    {
        $this->jwtSecret = $jwtSecret;
    }

    public function validateToken(array $headers): ?array
    {
        if (isset($headers['token']) && !empty($headers['token'])) {
            $jwt = current($headers['token']); // Récupère la cellule 0 avec current()
            try { // On essaie de décoder le jwt
                $decoded = JWT::decode($jwt, new Key($this->jwtSecret, 'HS256'));
                return ['decoded' => $decoded, 'status' => 200];
            } catch (\Exception $e) { // Token invalide
                return ['message' => $e->getMessage(), 'status' => 403];
            }
        }
        return ['message' => "No token provided", 'status' => 403];
    }
}
