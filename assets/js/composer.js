(function (wp, config) {
	'use strict';

	const { __, sprintf } = wp.i18n;
	let attachmentIds = [];

	function setStatus(message, tone = 'info') {
		const status = document.getElementById('sms-composer-status');
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

	function toIso(value) {
		if (!value) return '';
		return new Date(value).toISOString();
	}

	function postPayload(forcePublish = false) {
		const account = document.getElementById('sms-account');
		const platform = document.getElementById('sms-platform');
		const status = document.getElementById('sms-status');
		return {
			caption: document.getElementById('sms-caption')?.value || '',
			platform: platform?.value || 'instagram',
			socialAccountId: Number(account?.value || 0),
			scheduledAt: toIso(document.getElementById('sms-scheduled-at')?.value || ''),
			status: forcePublish ? 'PUBLISHED' : (status?.value || 'DRAFT'),
			isStory: Boolean(document.getElementById('sms-is-story')?.checked),
			notes: document.getElementById('sms-notes')?.value || '',
		};
	}

	function renderMediaList(items) {
		const list = document.getElementById('sms-media-list');
		if (!list) return;
		list.replaceChildren();
		items.forEach((item) => {
			const row = document.createElement('li');
			row.className = 'sms-media-list__item';
			row.dataset.attachmentId = String(item.id);
			const thumb = item.sizes?.thumbnail?.url || item.icon || '';
			row.innerHTML = `
				${thumb ? `<img src="${thumb}" alt="" />` : '<span class="dashicons dashicons-format-image" aria-hidden="true"></span>'}
				<span></span>
				<button class="button-link-delete" type="button">${__('Remove', 'social-media-scheduler')}</button>
			`;
			row.querySelector('span:last-of-type').textContent = item.filename || item.title || String(item.id);
			row.querySelector('button')?.addEventListener('click', () => {
				attachmentIds = attachmentIds.filter((id) => id !== item.id);
				row.remove();
			});
			list.appendChild(row);
		});
	}

	function openMediaPicker() {
		const frame = wp.media({
			title: __('Choose Media', 'social-media-scheduler'),
			button: { text: __('Use selected media', 'social-media-scheduler') },
			library: { type: ['image', 'video'] },
			multiple: true,
		});
		frame.on('select', () => {
			const selected = frame.state().get('selection').toJSON();
			attachmentIds = selected.map((item) => item.id);
			renderMediaList(selected);
		});
		frame.open();
	}

	async function savePost(forcePublish = false) {
		const payload = postPayload(forcePublish);
		if (!payload.scheduledAt) {
			throw new Error(__('Date and time are required.', 'social-media-scheduler'));
		}

		const post = await request('posts', {
			method: 'POST',
			body: JSON.stringify(payload),
		});

		for (const attachmentId of attachmentIds) {
			await request(`posts/${post.id}/media`, {
				method: 'POST',
				body: JSON.stringify({ attachmentId }),
			});
		}

		return post;
	}

	async function publishPost(post) {
		if (post.platform === 'tiktok') {
			return request('publish/tiktok', {
				method: 'POST',
				body: JSON.stringify({ postId: post.id }),
			});
		}

		return request('publish/meta', {
			method: 'POST',
			body: JSON.stringify({ postId: post.id, targetPlatforms: [post.platform] }),
		});
	}

	document.addEventListener('DOMContentLoaded', () => {
		const account = document.getElementById('sms-account');
		const platform = document.getElementById('sms-platform');
		account?.addEventListener('change', () => {
			const option = account.selectedOptions?.[0];
			if (platform && option?.dataset.platform) {
				platform.value = option.dataset.platform;
			}
		});

		document.getElementById('sms-media-picker')?.addEventListener('click', openMediaPicker);

		document.getElementById('sms-composer-form')?.addEventListener('submit', async (event) => {
			event.preventDefault();
			try {
				setStatus(__('Saving...', 'social-media-scheduler'));
				await savePost(false);
				setStatus(__('Post saved.', 'social-media-scheduler'), 'success');
			} catch (error) {
				setStatus(error.message, 'error');
			}
		});

		document.getElementById('sms-publish-now')?.addEventListener('click', async () => {
			try {
				setStatus(__('Saving and publishing...', 'social-media-scheduler'));
				const post = await savePost(true);
				await publishPost(post);
				setStatus(__('Publish request sent.', 'social-media-scheduler'), 'success');
			} catch (error) {
				setStatus(error.message, 'error');
			}
		});
	});
})(window.wp, window.smsComposer || {});
