import { Controller } from '@hotwired/stimulus';

/** Tiny confirm-before-submit; replace with your own modal by overriding the template's data-controller. */
export default class extends Controller {
    static values = { message: { type: String, default: 'Revoke this passkey? Devices using it will no longer be able to sign in.' } };

    confirm(event) {
        if (!window.confirm(this.messageValue)) {
            event.preventDefault();
        }
    }
}
