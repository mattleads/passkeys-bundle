import { Controller } from '@hotwired/stimulus';
import { runCeremony, isUserCancellation } from './webauthn_service.js';

/**
 * Runs a single ceremony when the element is submitted/clicked. Use mode "register" on the management page
 * (add a passkey to the current account) or "login" for a dedicated passkey-only button.
 *
 * Values: optionsUrl, resultUrl, mode ("register"|"login"), username (optional), redirectUrl (optional), csrfHeader + csrfToken (optional)
 */
export default class extends Controller {
    static values = {
        optionsUrl: String,
        resultUrl: String,
        mode: { type: String, default: 'register' },
        username: String,
        redirectUrl: String,
        csrfHeader: String,
        csrfToken: String,
    };

    static targets = ['error'];

    async submit(event) {
        event.preventDefault();
        if (this.hasErrorTarget) {
            this.errorTarget.hidden = true;
        }

        const headers = this.hasCsrfHeaderValue && this.hasCsrfTokenValue ? { [this.csrfHeaderValue]: this.csrfTokenValue } : {};
        try {
            await runCeremony({
                optionsUrl: this.optionsUrlValue,
                resultUrl: this.resultUrlValue,
                mode: this.modeValue,
                username: this.hasUsernameValue && this.usernameValue ? this.usernameValue : null,
                headers,
            });
            this.dispatch('success', { detail: { mode: this.modeValue } });
            window.location.assign(this.hasRedirectUrlValue && this.redirectUrlValue ? this.redirectUrlValue : window.location.href);
        } catch (error) {
            if (isUserCancellation(error)) {
                return;
            }
            const message = error.message || 'Something went wrong with your passkey.';
            if (this.hasErrorTarget) {
                this.errorTarget.textContent = message;
                this.errorTarget.hidden = false;
            }
            this.dispatch('error', { detail: { message } });
        }
    }
}
