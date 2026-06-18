(function () {
  'use strict';

  const PUBKEY_URL   = '/backend/auth/hybrid_pubkey.php';
  const REGISTER_URL = '/backend/auth/register.php';

  function b64(buf) {
    const bytes = new Uint8Array(buf);
    let s = '';
    for (let i = 0; i < bytes.length; i++) s += String.fromCharCode(bytes[i]);
    return btoa(s);
  }

  function pemToDer(pem) {
    const body = pem.replace(/-----BEGIN [^-]+-----/g, '')
                    .replace(/-----END [^-]+-----/g, '')
                    .replace(/\s+/g, '');
    const bin = atob(body);
    const out = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
    return out.buffer;
  }

  async function fetchPublicKey() {
    const r = await fetch(PUBKEY_URL, { credentials: 'same-origin' });
    if (!r.ok) throw new Error('No se pudo obtener clave pública.');
    const j = await r.json();
    if (!j.success || !j.public_key) throw new Error('Resposta de pubkey inválida.');
    console.log('[hybrid] S.3.1.a — clave pública RSA-2048 do server:\n' + j.public_key);
    const pub = await crypto.subtle.importKey(
      'spki',
      pemToDer(j.public_key),
      { name: 'RSA-OAEP', hash: 'SHA-1' },
      false,
      ['encrypt']
    );
    return pub;
  }

  async function generateSessionKey() {
    const key = await crypto.subtle.generateKey(
      { name: 'AES-GCM', length: 256 },
      true,
      ['encrypt', 'decrypt']
    );
    const raw = await crypto.subtle.exportKey('raw', key);
    const hex = Array.from(new Uint8Array(raw))
      .map(b => b.toString(16).padStart(2, '0')).join('');
    console.log('[hybrid] S.3.1.b — AES-256-GCM session key gerada (' + raw.byteLength + ' bytes): ' + hex);
    return { key, raw };
  }

  async function encryptSessionKey(pub, rawKey) {
    const enc = await crypto.subtle.encrypt({ name: 'RSA-OAEP' }, pub, rawKey);
    const b = b64(enc);
    console.log('[hybrid] S.3.1.c — session key cifrada com RSA-OAEP (base64, '
                + enc.byteLength + ' bytes): ' + b);
    return b;
  }

  async function encryptPayload(sessionKey, plaintextStr) {
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const enc = await crypto.subtle.encrypt(
      { name: 'AES-GCM', iv: iv },
      sessionKey,
      new TextEncoder().encode(plaintextStr)
    );
    return { ivB64: b64(iv.buffer), cipherB64: b64(enc) };
  }

  function collectForm(form) {
    const fd = new FormData(form);
    const obj = {};
    for (const [k, v] of fd.entries()) obj[k] = typeof v === 'string' ? v : '';
    const t = form.querySelector('[name="cf-turnstile-response"]');
    if (t && t.value) obj['cf-turnstile-response'] = t.value;
    return obj;
  }

  function showError(msg) {
    const el = document.getElementById('msg-servidor') || document.getElementById('msg-erro');
    if (el) el.textContent = msg;
  }

  async function onSubmit(ev) {
    ev.preventDefault();
    const form = ev.target;
    const btn  = document.getElementById('btn-cadastrar');
    if (btn) btn.disabled = true;

    try {
      const pub = await fetchPublicKey();
      const { key, raw } = await generateSessionKey();
      const encKey = await encryptSessionKey(pub, raw);
      const payload = JSON.stringify(collectForm(form));
      const { ivB64, cipherB64 } = await encryptPayload(key, payload);

      console.log('[hybrid] S.3.1.d/e — POST cifrado a register.php (corpo abaixo é o que viaja na rede)');

      const r = await fetch(REGISTER_URL, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          hybrid: true,
          encKey: encKey,
          iv:     ivB64,
          cipher: cipherB64,
        }),
      });

      let data = null;
      try { data = await r.json(); } catch (e) { /* response no-JSON */ }

      if (r.ok && data && data.success) {
        window.location.href = data.redirect || '../../frontend/pages/confirmacao-cadastro.html';
      } else {
        const msg = (data && data.error) || ('Erro ' + r.status);
        showError(msg);
        if (btn) btn.disabled = false;
        if (window.turnstile && typeof window.turnstile.reset === 'function') {
          window.turnstile.reset();
        }
      }
    } catch (e) {
      console.error('[hybrid] erro:', e);
      showError('Erro de criptografia: ' + e.message);
      if (btn) btn.disabled = false;
    }
  }

  function init() {
    const form = document.getElementById('form-cadastro');
    if (!form) return;
    if (!window.crypto || !window.crypto.subtle) {
      console.warn('[hybrid] WebCrypto indisponível — fallback a form POST normal.');
      return;
    }
    form.addEventListener('submit', onSubmit);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
