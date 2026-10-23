'use strict';
// Big translation tables: send only the cells that changed, so the form stays far below
// PHP's max_input_vars. Without JavaScript every cell is sent and the server still accepts it.
(() => {
  for (const form of document.querySelectorAll('form[data-changed-only]')) {
    form.addEventListener('submit', () => {
      for (const field of form.querySelectorAll('input[name^="strings["], textarea[name^="strings["]')) {
        if (field.value === field.defaultValue && !field.hasAttribute('data-unsaved')) field.disabled = true;
      }
    });
    // Re-enable after a back/forward cache restore.
    window.addEventListener('pageshow', () => { for (const field of form.querySelectorAll('[disabled]')) field.disabled = false; });
  }
})();
(() => {
  const login = document.getElementById('passkey-login');
  const add = document.getElementById('passkey-add');
  if (!login && !add) return;
  const message = document.getElementById('passkey-message');
  const decode = text => Uint8Array.from(atob(text.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - text.length % 4) % 4)), c => c.charCodeAt(0));
  const encode = bytes => btoa(String.fromCharCode(...new Uint8Array(bytes))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  const post = async (path, data = {}) => {
    const response = await fetch(path, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content}, body: JSON.stringify(data)});
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'Please reload and try again.');
    return result;
  };
  const options = json => {
    json.challenge = decode(json.challenge);
    if (json.user) json.user.id = decode(json.user.id);
    for (const list of ['excludeCredentials', 'allowCredentials']) {
      if (json[list]) json[list] = json[list].map(item => ({...item, id: decode(item.id)}));
    }
    return json;
  };
  const serialize = credential => {
    const response = {clientDataJSON: encode(credential.response.clientDataJSON)};
    for (const key of ['attestationObject', 'authenticatorData', 'signature', 'userHandle']) {
      if (credential.response[key]) response[key] = encode(credential.response[key]);
    }
    if (credential.response.getTransports) response.transports = credential.response.getTransports();
    return {id: credential.id, type: credential.type, response};
  };
  if (!window.PublicKeyCredential || !navigator.credentials) {
    message.textContent = 'Passkeys are not supported here. Use your password to sign in.';
    if (login) login.disabled = true;
    if (add) add.querySelector('button').disabled = true;
    return;
  }
  let pending;
  let operation = Promise.resolve();
  const signIn = async conditional => {
    const previous = operation;
    if (pending) pending.abort();
    let finish;
    operation = new Promise(resolve => { finish = resolve; });
    await previous;
    pending = new AbortController();
    try {
      if (!conditional) { login.disabled = true; message.textContent = 'Waiting for your passkey…'; }
      const publicKey = options(await post('/admin/passkey/login/options'));
      const credential = await navigator.credentials.get({publicKey, signal: pending.signal, ...(conditional ? {mediation: 'conditional'} : {})});
      const result = await post('/admin/passkey/login/verify', serialize(credential));
      window.location.assign(result.redirect);
    } catch (error) {
      if (error.name !== 'AbortError' && !conditional) message.textContent = error.name === 'NotAllowedError' ? 'Passkey sign-in was cancelled or timed out. Try again or use your password.' : error.message;
    } finally { if (login) login.disabled = false; finish(); }
  };
  if (login) {
    login.addEventListener('click', () => signIn(false));
    if (PublicKeyCredential.isConditionalMediationAvailable) {
      PublicKeyCredential.isConditionalMediationAvailable().then(available => { if (available) signIn(true); }).catch(() => {});
    }
  }
  if (add) add.addEventListener('submit', async event => {
    event.preventDefault();
    const button = add.querySelector('button'); button.disabled = true;
    try {
      message.textContent = 'Follow your device’s instructions to create a passkey.';
      const publicKey = options(await post('/admin/security/passkeys/options'));
      const credential = await navigator.credentials.create({publicKey});
      const result = await post('/admin/security/passkeys', {...serialize(credential), name: document.getElementById('passkey-name').value});
      window.location.assign(result.redirect);
    } catch (error) { message.textContent = error.name === 'NotAllowedError' ? 'Passkey creation was cancelled or timed out. You can try again.' : error.message; }
    finally { button.disabled = false; }
  });
})();
