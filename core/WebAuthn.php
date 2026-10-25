<?php
declare(strict_types=1);
namespace Core;

final class WebAuthn
{
    public function __construct(private Database $db, private string $origin, private string $rpId, private string $rpName = 'Meteor Blog') {}
    public static function encode(string $bytes): string { return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '='); }
    public static function decode(string $text): string
    {
        if ($text === '' || !preg_match('/^[A-Za-z0-9_-]+$/D', $text)) { throw new \InvalidArgumentException('Invalid base64url.'); }
        $bytes = base64_decode(strtr($text, '-_', '+/'), true);
        if ($bytes === false || self::encode($bytes) !== $text) { throw new \InvalidArgumentException('Invalid base64url.'); }
        return $bytes;
    }
    private function challenge(string $type, ?int $userId): string
    {
        $challenge = self::encode(random_bytes(32));
        $_SESSION['webauthn'] = ['challenge' => $challenge, 'type' => $type, 'user_id' => $userId, 'expires' => time() + 300];
        return $challenge;
    }
    public function registrationOptions(array $user): array
    {
        $keys = $this->db->all('SELECT credential_id FROM passkeys WHERE user_id = ?', [$user['id']]);
        return ['challenge' => $this->challenge('webauthn.create', (int) $user['id']),
            'rp' => ['id' => $this->rpId, 'name' => $this->rpName],
            'user' => ['id' => self::encode((string) $user['id']), 'name' => $user['username'], 'displayName' => $user['username']],
            'pubKeyCredParams' => [['type' => 'public-key', 'alg' => -7], ['type' => 'public-key', 'alg' => -257]],
            'timeout' => 300000, 'attestation' => 'none',
            'authenticatorSelection' => ['residentKey' => 'preferred', 'userVerification' => 'preferred'],
            'excludeCredentials' => array_map(fn ($k) => ['type' => 'public-key', 'id' => $k['credential_id']], $keys)];
    }
    public function authenticationOptions(?int $userId = null): array
    {
        return ['challenge' => $this->challenge('webauthn.get', $userId), 'rpId' => $this->rpId,
            'timeout' => 300000, 'userVerification' => 'preferred'];
    }
    private function client(array $credential, string $type, ?int $userId): array
    {
        $pending = $_SESSION['webauthn'] ?? null;
        unset($_SESSION['webauthn']); // Even invalid attempts consume the challenge.
        if (!$pending || $pending['expires'] < time() || $pending['type'] !== $type
            || ($pending['user_id'] !== null && $pending['user_id'] !== $userId)) { throw new \InvalidArgumentException('Expired or invalid challenge.'); }
        if (($credential['type'] ?? '') !== 'public-key') { throw new \InvalidArgumentException('Invalid credential type.'); }
        $raw = self::decode($credential['response']['clientDataJSON'] ?? '');
        $client = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        if (($client['type'] ?? '') !== $type || !hash_equals($pending['challenge'], $client['challenge'] ?? '')
            || ($client['origin'] ?? '') !== $this->origin || !empty($client['crossOrigin'])) {
            throw new \InvalidArgumentException('Invalid client data.');
        }
        return [$raw, self::encode(self::decode($credential['id'] ?? ''))];
    }
    private function authenticator(string $data): int
    {
        if (strlen($data) < 37 || !hash_equals(hash('sha256', $this->rpId, true), substr($data, 0, 32))
            || !(ord($data[32]) & 1)) { throw new \InvalidArgumentException('Invalid authenticator data.'); }
        return unpack('N', substr($data, 33, 4))[1];
    }
    public function register(array $credential, array $user, string $name): int
    {
        [, $id] = $this->client($credential, 'webauthn.create', (int) $user['id']);
        if (trim($name) === '' || mb_strlen($name) > 191) { throw new \InvalidArgumentException('Enter a passkey name (up to 191 characters).'); }
        $att = Cbor::decode(self::decode($credential['response']['attestationObject'] ?? ''));
        if (($att['fmt'] ?? '') !== 'none') { throw new \InvalidArgumentException('Only none attestation is supported.'); }
        $data = $att['authData'] ?? '';
        $count = $this->authenticator($data);
        if (strlen($data) < 55 || !(ord($data[32]) & 64)) { throw new \InvalidArgumentException('Missing credential data.'); }
        $length = unpack('n', substr($data, 53, 2))[1];
        if ($length < 1 || $length > 768 || strlen($data) < 55 + $length || self::encode(substr($data, 55, $length)) !== $id) {
            throw new \InvalidArgumentException('Credential ID mismatch.');
        }
        $offset = 55 + $length; $cose = Cbor::read($data, $offset);
        $pem = self::publicKey($cose);
        $transports = array_values(array_intersect((array) ($credential['response']['transports'] ?? []), ['usb', 'nfc', 'ble', 'internal', 'hybrid']));
        return $this->db->insert('INSERT INTO passkeys (user_id, credential_id, credential_hash, public_key, algorithm, sign_count, name, transports) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$user['id'], $id, hash('sha256', $id), $pem, $cose[3], $count, trim($name), json_encode($transports)]);
    }
    public function authenticate(array $credential): array
    {
        $id = $credential['id'] ?? '';
        $key = is_string($id) ? $this->db->one('SELECT * FROM passkeys WHERE credential_hash = ?', [hash('sha256', $id)]) : null;
        [$client, $id] = $this->client($credential, 'webauthn.get', $key ? (int) $key['user_id'] : null);
        if (!$key || !hash_equals($key['credential_id'], $id)) { throw new \InvalidArgumentException('Unknown credential.'); }
        $handle = $credential['response']['userHandle'] ?? null;
        if ($handle !== null && $handle !== '' && self::decode($handle) !== (string) $key['user_id']) { throw new \InvalidArgumentException('Wrong user.'); }
        $data = self::decode($credential['response']['authenticatorData'] ?? '');
        $count = $this->authenticator($data);
        if ($key['sign_count'] > 0 && $count > 0 && $count <= $key['sign_count']) { throw new \InvalidArgumentException('Counter replay.'); }
        if (openssl_verify($data . hash('sha256', $client, true), self::decode($credential['response']['signature'] ?? ''), $key['public_key'], OPENSSL_ALGO_SHA256) !== 1) {
            throw new \InvalidArgumentException('Invalid signature.');
        }
        $updated = $this->db->query('UPDATE passkeys SET sign_count = ?, last_used_at = ? WHERE id = ? AND sign_count = ?',
            [$count, gmdate('Y-m-d H:i:s'), $key['id'], $key['sign_count']]);
        if ($updated->rowCount() !== 1) { throw new \InvalidArgumentException('Concurrent authentication.'); }
        return $this->db->one('SELECT * FROM users WHERE id = ?', [$key['user_id']]);
    }
    private static function der(int $tag, string $bytes): string
    {
        $length = strlen($bytes);
        return chr($tag) . ($length < 128 ? chr($length) : ($length < 256 ? "\x81" . chr($length) : "\x82" . pack('n', $length))) . $bytes;
    }
    public static function publicKey(array $key): string
    {
        if (($key[3] ?? null) === -7 && ($key[1] ?? null) === 2 && ($key[-1] ?? null) === 1
            && strlen($key[-2] ?? '') === 32 && strlen($key[-3] ?? '') === 32) {
            $der = self::der(48, hex2bin('301306072a8648ce3d020106082a8648ce3d030107') . self::der(3, "\0\x04" . $key[-2] . $key[-3]));
        } elseif (($key[3] ?? null) === -257 && ($key[1] ?? null) === 3 && strlen($key[-1] ?? '') >= 256 && strlen($key[-1]) <= 1024 && strlen($key[-2] ?? '') > 0) {
            $integer = static function (string $s): string { $s = ltrim($s, "\0"); return self::der(2, (ord($s[0]) & 128 ? "\0" : '') . $s); };
            $rsa = self::der(48, $integer($key[-1]) . $integer($key[-2]));
            $der = self::der(48, hex2bin('300d06092a864886f70d0101010500') . self::der(3, "\0" . $rsa));
        } else { throw new \InvalidArgumentException('Unsupported COSE key.'); }
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
        if (!openssl_pkey_get_public($pem)) { throw new \InvalidArgumentException('Invalid public key.'); }
        return $pem;
    }
}
