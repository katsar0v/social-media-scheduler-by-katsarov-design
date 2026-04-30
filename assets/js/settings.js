(function (wp, config) {
	'use strict';

	const { __, sprintf } = wp.i18n;

	function setStatus(message, tone = 'info') {
		const status = document.getElementById('sms-settings-status');
		if (!status) return;
		status.textContent = message;
		status.dataset.tone = tone;
	}

	async function request(path, options = {}) {
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
			throw new Error(payload?.message || sprintf(__('Request failed with status %d.', 'social-media-scheduler'), response.status));
		}
		return payload;
	}

	function formPayload(form) {
		const data = new FormData(form);
		return {
			timezone: data.get('timezone'),
			defaultPlatform: data.get('defaultPlatform'),
			defaultPostStatus: data.get('defaultPostStatus'),
			brandHashtags: data.get('brandHashtags'),
			calendarWeekStart: Number(data.get('calendarWeekStart')),
			metaAppId: data.get('metaAppId'),
			metaAppSecret: data.get('metaAppSecret'),
			tiktokClientKey: data.get('tiktokClientKey'),
			tiktokClientSecret: data.get('tiktokClientSecret'),
			tiktokRedirectUri: data.get('tiktokRedirectUri'),
			baseUrl: data.get('baseUrl'),
			removeOnUninstall: Boolean(data.get('removeOnUninstall')),
		};
	}

	document.addEventListener('DOMContentLoaded', () => {
		document.getElementById('sms-settings-form')?.addEventListener('submit', async (event) => {
			event.preventDefault();
			try {
				setStatus(__('Saving...', 'social-media-scheduler'));
				await request('settings', {
					method: 'PUT',
					body: JSON.stringify(formPayload(event.currentTarget)),
				});
				setStatus(__('Settings saved.', 'social-media-scheduler'), 'success');
			} catch (error) {
				setStatus(error.message, 'error');
			}
		});
	});
})(window.wp, window.smsSettings || {});
