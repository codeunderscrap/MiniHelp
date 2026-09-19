<?php
// config/jwt.php — RS256 JWT verification against MM OS JWKS (pure PHP, no external deps)

require_once __DIR__ . '/mmos.php';

class JWTVerificationError extends Exception {}

class MMOSJwtVerifier {
    private string $jwks_url;
    private string $issuer;
    private string $audience;
    private ?array $cached_keys = null;
    private int $cache_expiry = 0;
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct() {
        $cfg = mmos_config();
        $this->jwks_url = $cfg->jwks_url();
        $this->issuer   = $cfg->issuer;
        $this->audience = $cfg->service_slug;
    }

    public function verify(string $token): array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new JWTVerificationError('malformed_token');
        }

        $header  = $this->decode_segment($parts[0]);
        $payload = $this->decode_segment($parts[1]);

        if (!$header || !isset($header['alg']) || $header['alg'] !== 'RS256') {
            throw new JWTVerificationError('unsupported_algorithm');
        }

        $kid = $header['kid'] ?? null;
        $pem = $this->get_public_key($kid);

        $data = $parts[0] . '.' . $parts[1];
        $signature = $this->base64url_decode($parts[2]);

        $result = openssl_verify($data, $signature, $pem, OPENSSL_ALGO_SHA256);
        if ($result !== 1) {
            throw new JWTVerificationError('bad_signature');
        }

        if (isset($payload['iss']) && $payload['iss'] !== $this->issuer) {
            throw new JWTVerificationError('invalid_issuer');
        }

        if (isset($payload['aud'])) {
            $aud = is_array($payload['aud']) ? $payload['aud'] : [$payload['aud']];
            if (!in_array($this->audience, $aud, true)) {
                throw new JWTVerificationError('invalid_audience');
            }
        }

        $now = time();
        if (isset($payload['exp']) && $payload['exp'] < $now) {
            throw new JWTVerificationError('expired');
        }
        if (isset($payload['iat']) && $payload['iat'] > $now + 60) {
            throw new JWTVerificationError('not_yet_valid');
        }

        return $payload;
    }

    private function get_public_key(?string $kid): string {
        $keys = $this->fetch_jwks();

        foreach ($keys as $key) {
            if ($kid !== null && isset($key['kid']) && $key['kid'] !== $kid) {
                continue;
            }
            if (($key['kty'] ?? '') !== 'RSA') {
                continue;
            }
            if (isset($key['use']) && $key['use'] !== 'sig') {
                continue;
            }
            return $this->jwk_to_pem($key);
        }

        // kid not found — refetch in case of key rotation
        $this->cached_keys = null;
        $keys = $this->fetch_jwks();

        foreach ($keys as $key) {
            if ($kid !== null && isset($key['kid']) && $key['kid'] !== $kid) {
                continue;
            }
            if (($key['kty'] ?? '') !== 'RSA') {
                continue;
            }
            return $this->jwk_to_pem($key);
        }

        throw new JWTVerificationError('key_not_found');
    }

    private function fetch_jwks(): array {
        if ($this->cached_keys !== null && time() < $this->cache_expiry) {
            return $this->cached_keys;
        }

        $ctx = stream_context_create([
            'http' => ['timeout' => 10, 'ignore_errors' => false],
            'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        $json = @file_get_contents($this->jwks_url, false, $ctx);
        if ($json === false) {
            if ($this->cached_keys !== null) {
                return $this->cached_keys;
            }
            throw new JWTVerificationError('jwks_fetch_failed');
        }

        $data = json_decode($json, true);
        if (!isset($data['keys']) || !is_array($data['keys'])) {
            throw new JWTVerificationError('jwks_invalid');
        }

        $this->cached_keys  = $data['keys'];
        $this->cache_expiry = time() + self::CACHE_TTL;
        return $this->cached_keys;
    }

    private function jwk_to_pem(array $jwk): string {
        $n = $this->base64url_decode($jwk['n']);
        $e = $this->base64url_decode($jwk['e']);

        $modulus  = $this->encode_asn1_integer($n);
        $exponent = $this->encode_asn1_integer($e);

        $pub_key_seq = $this->encode_asn1_sequence($modulus . $exponent);
        $bit_string  = chr(0x03) . $this->encode_asn1_length(strlen($pub_key_seq) + 1) . chr(0x00) . $pub_key_seq;

        // RSA OID: 1.2.840.113549.1.1.1
        $oid = pack('H*', '06092a864886f70d010101');
        $null_param = pack('H*', '0500');
        $algo_id = $this->encode_asn1_sequence($oid . $null_param);

        $der = $this->encode_asn1_sequence($algo_id . $bit_string);
        $pem = "-----BEGIN PUBLIC KEY-----\n"
             . chunk_split(base64_encode($der), 64, "\n")
             . "-----END PUBLIC KEY-----";

        return $pem;
    }

    private function encode_asn1_integer(string $bytes): string {
        if (ord($bytes[0]) > 0x7F) {
            $bytes = chr(0x00) . $bytes;
        }
        return chr(0x02) . $this->encode_asn1_length(strlen($bytes)) . $bytes;
    }

    private function encode_asn1_sequence(string $content): string {
        return chr(0x30) . $this->encode_asn1_length(strlen($content)) . $content;
    }

    private function encode_asn1_length(int $length): string {
        if ($length < 0x80) {
            return chr($length);
        }
        $temp = ltrim(pack('N', $length), chr(0));
        return chr(0x80 | strlen($temp)) . $temp;
    }

    private function decode_segment(string $segment): ?array {
        $json = $this->base64url_decode($segment);
        if ($json === '') return null;
        return json_decode($json, true);
    }

    private function base64url_decode(string $data): string {
        $padded = str_pad(strtr($data, '-_', '+/'), strlen($data) + (4 - strlen($data) % 4) % 4, '=');
        return base64_decode($padded, true) ?: '';
    }
}

function mmos_jwt_verifier(): MMOSJwtVerifier {
    static $instance = null;
    if ($instance === null) {
        $instance = new MMOSJwtVerifier();
    }
    return $instance;
}
