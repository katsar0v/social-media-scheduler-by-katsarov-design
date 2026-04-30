(function (wp, config) {
	'use strict';

	const { __, sprintf } = wp.i18n;
	const status = document.getElementById('sms-accounts-status');

	const setStatus = (message, tone = 'info') => {
		if (!status) return;
		status.textContent = message;
		status.dataset.tone = tone;
	};

	const request = async (path, options = {}) => {
		const response = await fetch(config.root + path.replace(/^\/+/, ''), {
			...options,
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce,
				...(options.headers || {}),
			},
		});
		const payload = response.status === 204 ? null : await response.json();
		if (!response.ok) {
			// translators: %d: HTTP status code returned by the REST API.
			throw new Error(payload?.message || payload?.error || sprintf(__('Request failed with status %d.', 'social-media-scheduler'), response.status));
		}
		return payload;
	};

	document.addEventListener('DOMContentLoaded', () => {
		document.querySelectorAll('.sms-disconnect-account').forEach((button) => {
			button.addEventListener('click', async () => {
				const id = button.dataset.accountId;
				if (!id || !window.confirm(__('Disconnect this account?', 'social-media-scheduler'))) return;
				try {
					setStatus(__('Disconnecting…', 'social-media-scheduler'));
					await request(`auth/accounts/${id}`, { method: 'DELETE' });
					document.querySelector(`tr[data-account-id="${id}"]`)?.remove();
					setStatus(__('Disconnected.', 'social-media-scheduler'), 'success');
				} catch (error) {
					setStatus(error.message, 'error');
				}
			});
		});
	});
})(window.wp, window.smsAccounts || {});
