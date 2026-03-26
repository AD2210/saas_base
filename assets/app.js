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

    const formCard = form.closest('.hero__form-card');
    const statusNode = document.querySelector('[data-form-status]');
    const submitButton = form.querySelector('[data-submit-button]');

    const escapeHtml = (value) => String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');

    const setStatusHtml = (state, fragments) => {
        statusNode.innerHTML = fragments.join('');
        statusNode.dataset.state = state;
    };

    const renderSuccess = (body, payload) => {
        const appName = typeof body.child_app_name === 'string' && body.child_app_name !== '' ? body.child_app_name : 'the requested app';
        const email = typeof payload.email === 'string' ? payload.email : 'your email address';
        const tenantSlug = typeof body.tenant_slug === 'string' && body.tenant_slug !== '' ? body.tenant_slug : 'pending';

        if (formCard instanceof HTMLElement) {
            formCard.dataset.requested = 'true';
        }

        form.hidden = true;
        form.setAttribute('aria-hidden', 'true');
        setStatusHtml('success', [
            '<strong>Request accepted.</strong>',
            `<span>${escapeHtml(appName)} is being prepared for tenant <code>${escapeHtml(tenantSlug)}</code>.</span>`,
            `<span>An onboarding email was sent to <code>${escapeHtml(email)}</code>.</span>`,
            '<span>If you do not see it within a minute, check spam or retry with the same email.</span>',
        ]);
    };

    const renderResent = (body) => {
        if (formCard instanceof HTMLElement) {
            formCard.dataset.requested = 'true';
        }

        form.hidden = true;
        form.setAttribute('aria-hidden', 'true');
        setStatusHtml('resent', [
            '<strong>Invitation resent.</strong>',
            `<span>A fresh onboarding email was sent to <code>${escapeHtml(body.email ?? '')}</code>.</span>`,
            `<span>We reused the existing tenant <code>${escapeHtml(body.tenant_slug ?? 'pending')}</code> and the data already stored in the platform.</span>`,
            '<span>Check spam if it does not appear within a minute.</span>',
        ]);
    };

    const renderExisting = (body, payload) => {
        const email = typeof body.email === 'string' && body.email !== '' ? body.email : (typeof payload.email === 'string' ? payload.email : '');
        const tenantSlug = typeof body.tenant_slug === 'string' && body.tenant_slug !== '' ? body.tenant_slug : 'existing';
        const appName = typeof body.child_app_name === 'string' && body.child_app_name !== '' ? body.child_app_name : 'this app';
        const loginUrl = typeof body.login_url === 'string' ? body.login_url : '';
        const canResendInvitation = body.can_resend_invitation === true;

        const fragments = [
            '<strong>Email already present.</strong>',
            `<span><code>${escapeHtml(email)}</code> already has a saved onboarding record for <code>${escapeHtml(tenantSlug)}</code> in ${escapeHtml(appName)}.</span>`,
        ];

        if (canResendInvitation) {
            fragments.push('<span>You can resend the invitation using the stored information.</span>');
            fragments.push('<button type="button" class="status-action" data-resend-invitation>Resend invitation</button>');
        } else if (loginUrl !== '') {
            fragments.push('<span>This account is already activated. Continue from the login page.</span>');
            fragments.push(`<a class="status-action" href="${escapeHtml(loginUrl)}">Open login</a>`);
        }

        setStatusHtml('warning', fragments);
    };

    const resendInvitation = async (payload) => {
        const resendUrl = statusNode.dataset.resendInvitationUrl;
        if (typeof resendUrl !== 'string' || resendUrl === '') {
            return;
        }

        const resendButton = statusNode.querySelector('[data-resend-invitation]');
        if (resendButton instanceof HTMLButtonElement) {
            resendButton.disabled = true;
        }

        setStatusHtml('pending', [
            '<strong>Resending invitation…</strong>',
            '<span>Using the data already registered for this email.</span>',
        ]);

        try {
            const response = await fetch(resendUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    email: payload.email,
                    child_app_key: payload.child_app_key,
                }),
            });

            const body = await response.json();
            if (!response.ok) {
                const errors = Array.isArray(body.errors) ? body.errors.join(' · ') : (body.message ?? body.error ?? 'Invitation resend failed');
                throw new Error(errors);
            }

            renderResent(body);
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Invitation resend failed';
            statusNode.textContent = message;
            statusNode.dataset.state = 'error';
        }
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!(statusNode instanceof HTMLElement) || !(submitButton instanceof HTMLButtonElement)) {
            return;
        }

        submitButton.disabled = true;
        statusNode.textContent = 'Submitting your request...';
        statusNode.dataset.state = '';
        delete statusNode.dataset.resendInvitationUrl;

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
            if (!response.ok && (body.status === 'email_already_present' || body.status === 'already_onboarded')) {
                statusNode.dataset.resendInvitationUrl = typeof body.resend_invitation_url === 'string' ? body.resend_invitation_url : '';
                renderExisting(body, payload);
                return;
            }

            if (!response.ok) {
                const errors = Array.isArray(body.errors) ? body.errors.join(' · ') : (body.error ?? 'Request failed');
                throw new Error(errors);
            }

            renderSuccess(body, payload);
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Unexpected error';
            statusNode.textContent = message;
            statusNode.dataset.state = 'error';
        } finally {
            submitButton.disabled = false;
        }
    });

    statusNode?.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement) || !target.matches('[data-resend-invitation]')) {
            return;
        }

        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());
        void resendInvitation(payload);
    });
});
