import { startRegistration, startAuthentication, browserSupportsWebAuthnAutofill } from '@simplewebauthn/browser';

/**
 * Runs one WebAuthn ceremony against web-auth/webauthn-symfony-bundle endpoints:
 *   1. POST optionsUrl  (JSON body: {username, displayName} or {} for discoverable credentials)
 *   2. navigator.credentials.create()/get() through @simplewebauthn/browser
 *   3. POST resultUrl with the credential JSON
 * Resolves with the parsed JSON of the result response; rejects with an Error carrying `.name` from the browser API.
 */
export async function runCeremony({ optionsUrl, resultUrl, mode, username = null, useAutofill = false, headers = {} }) {
    const common = { 'Content-Type': 'application/json', 'Accept': 'application/json', ...headers };

    const optionsResponse = await fetch(optionsUrl, {
        method: 'POST',
        headers: common,
        credentials: 'same-origin',
        body: username ? JSON.stringify({ username, displayName: username }) : '{}',
    });
    if (!optionsResponse.ok) {
        throw new Error(await errorMessage(optionsResponse, `Could not fetch ${mode} options`));
    }
    const optionsJSON = await optionsResponse.json();

    const credential = mode === 'register'
        ? await startRegistration({ optionsJSON })
        : await startAuthentication({ optionsJSON, useBrowserAutofill: useAutofill });

    const resultResponse = await fetch(resultUrl, {
        method: 'POST',
        headers: common,
        credentials: 'same-origin',
        body: JSON.stringify(credential),
    });
    if (!resultResponse.ok) {
        throw new Error(await errorMessage(resultResponse, 'The server rejected the credential'));
    }
    return resultResponse.json().catch(() => ({}));
}

export function supportsAutofill() {
    return browserSupportsWebAuthnAutofill();
}

/** Errors a user caused by dismissing the prompt — not worth showing. */
export function isUserCancellation(error) {
    return ['NotAllowedError', 'AbortError', 'CanceledError'].includes(error?.name);
}

async function errorMessage(response, fallback) {
    try {
        const data = await response.json();
        return data.errorMessage || data.message || data.error || fallback;
    } catch {
        return fallback;
    }
}
