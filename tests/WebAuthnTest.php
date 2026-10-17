<?php
use Core\{Cbor, WebAuthn};
function cborHead(int $major, int $n): string {
    return $n < 24 ? chr(($major << 5) | $n) : ($n < 256 ? chr(($major << 5) | 24) . chr($n) : ($n < 65536 ? chr(($major << 5) | 25) . pack('n', $n) : chr(($major << 5) | 26) . pack('N', $n)));
}
function cborEncode(mixed $value): string {
    if (is_int($value)) { return cborHead($value >= 0 ? 0 : 1, $value >= 0 ? $value : -1 - $value); }
    if (is_string($value)) { return cborHead(2, strlen($value)) . $value; }
    if (is_array($value)) { $out = cborHead(5, count($value)); foreach ($value as $k => $v) { $out .= cborEncode($k) . cborEncode($v); } return $out; }
    return $value === true ? "\xf5" : ($value === false ? "\xf4" : "\xf6");
}
function webFixture(int $algorithm): array {
    $db = database();
    $db->query('INSERT INTO users (username, password_hash) VALUES (?, ?)', ['editor', password_hash('long test password', PASSWORD_DEFAULT)]);
    $user = $db->one('SELECT * FROM users');
    $web = new WebAuthn($db, 'https://meteor.example', 'meteor.example');
    $private = openssl_pkey_new($algorithm === -7 ? ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'] : ['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
    $details = openssl_pkey_get_details($private);
    $cose = $algorithm === -7 ? [1 => 2, 3 => -7, -1 => 1, -2 => $details['ec']['x'], -3 => $details['ec']['y']] : [1 => 3, 3 => -257, -1 => $details['rsa']['n'], -2 => $details['rsa']['e']];
    $rawId = random_bytes(32); $id = WebAuthn::encode($rawId);
    return compact('db', 'user', 'web', 'private', 'cose', 'rawId', 'id');
}
function registration(array $f, array $overrides = []): array {
    $options = $f['web']->registrationOptions($f['user']);
    $client = ['type' => 'webauthn.create', 'origin' => 'https://meteor.example', 'challenge' => $options['challenge']];
    $client = array_replace($client, $overrides['client'] ?? []);
    $data = hash('sha256', $overrides['rp'] ?? 'meteor.example', true) . chr($overrides['flags'] ?? 65) . pack('N', 1) . str_repeat("\0", 16) . pack('n', strlen($f['rawId'])) . $f['rawId'] . cborEncode($f['cose']);
    return ['id' => $f['id'], 'type' => 'public-key', 'response' => ['clientDataJSON' => WebAuthn::encode(json_encode($client)), 'attestationObject' => WebAuthn::encode(cborEncode(['fmt' => 'none', 'authData' => $data, 'attStmt' => []])), 'transports' => ['internal']]];
}
function assertion(array $f, int $count = 2, array $overrides = []): array {
    $options = $f['web']->authenticationOptions();
    $client = json_encode(array_replace(['type' => 'webauthn.get', 'origin' => 'https://meteor.example', 'challenge' => $options['challenge']], $overrides['client'] ?? []));
    $data = hash('sha256', $overrides['rp'] ?? 'meteor.example', true) . chr($overrides['flags'] ?? 1) . pack('N', $count);
    openssl_sign($data . hash('sha256', $client, true), $signature, $f['private'], OPENSSL_ALGO_SHA256);
    return ['id' => $overrides['id'] ?? $f['id'], 'type' => 'public-key', 'response' => ['clientDataJSON' => WebAuthn::encode($client), 'authenticatorData' => WebAuthn::encode($data), 'signature' => WebAuthn::encode($signature), 'userHandle' => WebAuthn::encode((string) $f['user']['id'])]];
}
test('CBOR: integer, bytes, text, arrays, maps, tags, booleans, null and floats', function () {
    foreach (['00' => 0, '1818' => 24, '1903e8' => 1000, '1a000f4240' => 1000000, '20' => -1, '3903e7' => -1000,
        '4401020304' => "\x01\x02\x03\x04", '63666f6f' => 'foo', '83010203' => [1, 2, 3], 'a201020304' => [1 => 2, 3 => 4],
        'c101' => 1, 'f4' => false, 'f5' => true, 'f6' => null, 'f93e00' => 1.5, 'fa3fc00000' => 1.5, 'fbbff8000000000000' => -1.5, 'fb3ff8000000000000' => 1.5] as $hex => $expected) { same($expected, Cbor::decode(hex2bin((string) $hex))); }
    foreach (['', '18', '440102', '9f01ff', 'a201020103', '0000', '61ff'] as $hex) { throws(fn () => Cbor::decode(hex2bin($hex))); }
});
foreach ([-7, -257] as $algorithm) {
    test('WebAuthn: generated key registration/authentication and rejection vectors ' . $algorithm, function () use ($algorithm) {
        $f = webFixture($algorithm); $web = $f['web']; $db = $f['db'];
        $credential = registration($f);
        same('none', $web->registrationOptions($f['user'])['attestation']);
        $credential = registration($f);
        $keyId = $web->register($credential, $f['user'], 'Laptop');
        $key = $db->one('SELECT * FROM passkeys WHERE id = ?', [$keyId]); same($algorithm, (int) $key['algorithm']); same(hash('sha256', $f['id']), $key['credential_hash']);
        throws(fn () => $web->register($credential, $f['user'], 'Reused'));
        $signed = assertion($f); same($f['user']['id'], $web->authenticate($signed)['id']);
        throws(fn () => $web->authenticate($signed));
        throws(fn () => $web->authenticate(assertion($f, 2)));
        foreach ([['client' => ['origin' => 'https://evil.example']], ['client' => ['challenge' => 'wrong']], ['client' => ['type' => 'webauthn.create']], ['client' => ['crossOrigin' => true]], ['rp' => 'evil.example'], ['flags' => 0], ['id' => WebAuthn::encode('unknown')]] as $override) {
            throws(fn () => $web->authenticate(assertion($f, 3, $override)));
        }
        $signed = assertion($f, 3); $_SESSION['webauthn']['expires'] = time() - 1; throws(fn () => $web->authenticate($signed));
        $signed = assertion($f, 3); $signed['response']['signature'] = WebAuthn::encode('invalid'); throws(fn () => $web->authenticate($signed));
        $signed = assertion($f, 3); $signed['response']['userHandle'] = WebAuthn::encode('999'); throws(fn () => $web->authenticate($signed));
        same($f['user']['id'], $web->authenticate(assertion($f, 3))['id']);
        same($f['user']['id'], $web->authenticate(assertion($f, 0))['id']);
        foreach ([['client' => ['origin' => 'https://evil.example']], ['client' => ['challenge' => 'wrong']], ['rp' => 'evil.example'], ['flags' => 64], ['flags' => 1]] as $override) {
            throws(fn () => $web->register(registration($f, $override), $f['user'], 'Invalid'));
        }
        $credential = registration($f); $credential['id'] = WebAuthn::encode('mismatch'); throws(fn () => $web->register($credential, $f['user'], 'Invalid'));
        $signed = assertion($f, 4); $_SESSION['webauthn']['user_id'] = 999; throws(fn () => $web->authenticate($signed));
        $db->query('DELETE FROM users WHERE id = ?', [$f['user']['id']]); same([], $db->all('SELECT * FROM passkeys'));
    });
}
