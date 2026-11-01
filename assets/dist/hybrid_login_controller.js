import { Controller } from '@hotwired/stimulus';
import { runCeremony, supportsAutofill, isUserCancellation } from './webauthn_service.js';

/**
 * One form, two credentials. On connect it offers passkey autofill (conditional UI). On submit it asks the
 * flow endpoint whether the identifier has a passkey, then either runs the WebAuthn ceremony or reveals the
 * password field and lets the form post normally.
 *
 * Values:  flowUrl, optionsUrl, resultUrl, redirectUrl (optional), csrfHeader + csrfToken (optional)
 * Targets: identifier, passwordContainer, password, continueButton, loginButton, passwordFallback, error
 */
export default class extends Controller {
    static values = {
        flowUrl: String,
        optionsUrl: String,
        resultUrl: String,
        redirectUrl: String,
        csrfHeader: String,
        csrfToken: String,
    };

    static targets = ['identifier', 'passwordContainer', 'password', 'continueButton', 'loginButton', 'passwordFallback', 'error'];

    #abortAutofill = null;

    async connect() {
        if (!(await supportsAutofill())) {
            return;
        }
        try {
            await runCeremony({ ...this.#ceremonyOptions(), mode: 'login', useAutofill: true });
            this.#onAuthenticated();
        } catch (error) {
            if (!isUserCancellation(error)) {
                console.warn('[passkeys] autofill ceremony failed:', error);
            }
        }
    }

    async submit(event) {
        if (this.#passwordVisible()) {
            return; // legacy password login: let the browser post the form
        }
        event.preventDefault();
        this.#clearError();

        const identifier = this.identifierTarget.value.trim();
        if (!identifier) {
            this.#showError('Please enter your email or username.');
            return;
        }

        let flow = 'password';
        try {
            const response = await fetch(`${this.flowUrlValue}?identifier=${encodeURIComponent(identifier)}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (response.ok) {
                flow = (await response.json()).flow ?? 'password';
            }
        } catch (error) {
            console.warn('[passkeys] flow lookup failed, falling back to password:', error);
        }

        if (flow !== 'passkey') {
            this.#revealPassword();
            return;
        }

        this.passwordFallbackTarget.hidden = false;
        try {
            await runCeremony({ ...this.#ceremonyOptions(), mode: 'login', username: identifier });
            this.#onAuthenticated();
        } catch (error) {
            if (!isUserCancellation(error)) {
                this.#showError(error.message || 'Passkey sign-in failed.');
            }
            this.#revealPassword();
        }
    }

    usePassword(event) {
        event?.preventDefault();
        this.#revealPassword();
    }

    #ceremonyOptions() {
        const headers = this.hasCsrfHeaderValue && this.hasCsrfTokenValue ? { [this.csrfHeaderValue]: this.csrfTokenValue } : {};
        return { optionsUrl: this.optionsUrlValue, resultUrl: this.resultUrlValue, headers };
    }

    #onAuthenticated() {
        this.dispatch('authenticated');
        window.location.assign(this.hasRedirectUrlValue && this.redirectUrlValue ? this.redirectUrlValue : window.location.href);
    }

    #passwordVisible() {
        return this.hasPasswordContainerTarget && !this.passwordContainerTarget.hidden;
    }

    #revealPassword() {
        this.passwordContainerTarget.hidden = false;
        this.passwordTarget.required = true;
        this.continueButtonTarget.hidden = true;
        this.loginButtonTarget.hidden = false;
        this.passwordFallbackTarget.hidden = true;
        this.passwordTarget.focus();
    }

    #showError(message) {
        if (this.hasErrorTarget) {
            this.errorTarget.textContent = message;
            this.errorTarget.hidden = false;
        }
        this.dispatch('error', { detail: { message } });
    }

    #clearError() {
        if (this.hasErrorTarget) {
            this.errorTarget.hidden = true;
            this.errorTarget.textContent = '';
        }
    }
}
