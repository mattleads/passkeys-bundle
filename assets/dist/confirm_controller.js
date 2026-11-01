import { Controller } from '@hotwired/stimulus';

/**
 * Two-step confirmation without window.confirm(): the first submit arms the button and swaps its label;
 * a second submit within `timeout` ms goes through, otherwise it disarms. Override the template's
 * data-controller to plug in your own modal.
 *
 * Values: confirmLabel (default "Confirm revoke"), timeout (ms, default 4000)
 */
export default class extends Controller {
    static values = {
        confirmLabel: { type: String, default: 'Confirm revoke' },
        timeout: { type: Number, default: 4000 },
    };

    #armed = false;
    #timer = null;
    #originalLabel = null;

    confirm(event) {
        if (this.#armed) {
            this.#disarm();
            return; // let the submit proceed
        }
        event.preventDefault();
        const button = this.element.querySelector('button[type="submit"], button:not([type])');
        if (button) {
            this.#originalLabel = button.textContent;
            button.textContent = this.confirmLabelValue;
            button.setAttribute('aria-live', 'polite');
        }
        this.#armed = true;
        this.dispatch('armed');
        this.#timer = setTimeout(() => this.#disarm(), this.timeoutValue);
    }

    disconnect() {
        this.#disarm();
    }

    #disarm() {
        clearTimeout(this.#timer);
        this.#timer = null;
        if (this.#armed) {
            const button = this.element.querySelector('button[type="submit"], button:not([type])');
            if (button && null !== this.#originalLabel) {
                button.textContent = this.#originalLabel;
            }
        }
        this.#armed = false;
    }
}
