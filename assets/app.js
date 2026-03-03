import './bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-demo-form]');
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const statusNode = document.querySelector('[data-form-status]');
    const submitButton = form.querySelector('[data-submit-button]');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!(statusNode instanceof HTMLElement) || !(submitButton instanceof HTMLButtonElement)) {
            return;
        }

        submitButton.disabled = true;
        statusNode.textContent = 'Submitting your request...';
        statusNode.dataset.state = '';

        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        try {
            const response = await fetch('/api/demo-requests', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const body = await response.json();
            if (!response.ok) {
                const errors = Array.isArray(body.errors) ? body.errors.join(' · ') : (body.error ?? 'Request failed');
                throw new Error(errors);
            }

            statusNode.textContent = `Demo request accepted. Tenant ${body.tenant_slug} is being prepared. Check your inbox for the onboarding link.`;
            statusNode.dataset.state = 'success';
            form.reset();
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unexpected error';
            statusNode.textContent = message;
            statusNode.dataset.state = 'error';
        } finally {
            submitButton.disabled = false;
        }
    });
});
