import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

/* ═══════════════════════════════════════════════════════════════════
   BUTTON LOADING-STATE UTILITY (THEME_REFERENCE §5)
   ─────────────────────────────────────────────────────────────────────
   setButtonLoading(button, label)   — disables a submit button, shows a
                                       spinner + label, remembers the
                                       original content for later restore.
   resetButtonLoading(button)        — restores the original content and
                                       re-enables the button.

   A single global 'submit' listener auto-wires PLAIN form submissions:
   any form that does NOT call e.preventDefault() and does NOT opt out
   via data-no-loading gets the spinner automatically. AJAX/fetch/Swal
   flows (POS checkout, add_item, edit_item, …) call preventDefault()
   and are therefore skipped — they keep their own loading UX.
   ═══════════════════════════════════════════════════════════════════ */

const LOADING_CLASSES = ['opacity-75', 'cursor-not-allowed'];

/**
 * Put a submit button into its "Processing..." state.
 * The original innerHTML is stashed on a data attribute so it can be
 * restored exactly (including any nested <svg>/<i> icons).
 *
 * @param {HTMLButtonElement} button
 * @param {string} label
 */
function setButtonLoading(button, label = 'Processing...') {
    if (!button || button.disabled) {
        return;
    }

    // Save original content only once (don't clobber on double-invoke).
    if (!button.dataset.originalHtml) {
        button.dataset.originalHtml = button.innerHTML;
    }

    button.disabled = true;
    button.classList.add(...LOADING_CLASSES);

    // THEME_REFERENCE §5 spinner markup. Uses Font Awesome icons loaded
    // in the layouts (fa-circle-notch fa-spin) + label.
    button.innerHTML = `
        <i class="fas fa-circle-notch fa-spin text-lg" aria-hidden="true"></i>
        <span class="ml-2">${label}</span>
    `;
}

/**
 * Restore a button from its loading state back to its original content.
 *
 * @param {HTMLButtonElement} button
 */
function resetButtonLoading(button) {
    if (!button) {
        return;
    }

    if (button.dataset.originalHtml) {
        button.innerHTML = button.dataset.originalHtml;
        delete button.dataset.originalHtml;
    }

    button.disabled = false;
    button.classList.remove(...LOADING_CLASSES);
}

/* Expose for manual opt-in on AJAX/fetch flows. */
window.setButtonLoading = setButtonLoading;
window.resetButtonLoading = resetButtonLoading;

/* ── Auto-wire plain form submissions ──────────────────────────────── */
document.addEventListener('submit', (event) => {
    // CRITICAL guard: pages that call preventDefault() (POS checkout,
    // add_item, edit_item, purchase/quotation save, quick-add modals, …)
    // manage their own submit + loading state — never touch them.
    if (event.defaultPrevented) {
        return;
    }

    const form = event.target;
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    // Explicit opt-out (whole form or the button itself).
    if (form.hasAttribute('data-no-loading')) {
        return;
    }

    // Prefer the explicit submit button; fall back to the form's default
    // submitter (works for implicit <Enter> submissions too).
    const button =
        (event.submitter && event.submitter instanceof HTMLButtonElement
            ? event.submitter
            : form.querySelector('button[type="submit"]')) ||
        form.querySelector('button[type="submit"]');

    if (!button || button.hasAttribute('data-no-loading')) {
        return;
    }

    // Plain server-bound submission: show the spinner. No reset needed —
    // the page navigates away on success; a validation re-render ships a
    // fresh button automatically.
    setButtonLoading(button);
});
