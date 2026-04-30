(function (wp, config) {
	'use strict';

	const { __, sprintf } = wp.i18n;
	const grid = document.getElementById('sms-calendar-grid');
	const labelText = document.getElementById('sms-month-label-text');
	const loadingIndicator = document.getElementById('sms-calendar-loading-indicator');
	const status = document.getElementById('sms-calendar-status');
	const weekStart = Number(config.settings?.calendarWeekStart ?? 1);
	const today = startOfDay(new Date());
	const timeFormatter = new Intl.DateTimeFormat(undefined, {
		hour: 'numeric',
		minute: '2-digit',
	});
	const platformIcons = {
		facebook: '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.04C6.5 2.04 2 6.53 2 12.06C2 17.06 5.66 21.21 10.44 21.96V14.96H7.9V12.06H10.44V9.85C10.44 7.34 11.93 5.96 14.22 5.96C15.31 5.96 16.45 6.15 16.45 6.15V8.62H15.19C13.95 8.62 13.56 9.39 13.56 10.18V12.06H16.34L15.89 14.96H13.56V21.96C18.34 21.21 22 17.06 22 12.06C22 6.53 17.5 2.04 12 2.04Z"/></svg>',
		instagram: '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M7.8 2h8.4C19.4 2 22 4.6 22 7.8v8.4a5.8 5.8 0 0 1-5.8 5.8H7.8C4.6 22 2 19.4 2 16.2V7.8A5.8 5.8 0 0 1 7.8 2m-.2 2A3.6 3.6 0 0 0 4 7.6v8.8C4 18.39 5.61 20 7.6 20h8.8a3.6 3.6 0 0 0 3.6-3.6V7.6C20 5.61 18.39 4 16.4 4H7.6m9.65 1.5a1.25 1.25 0 0 1 1.25 1.25A1.25 1.25 0 0 1 17.25 8A1.25 1.25 0 0 1 16 6.75a1.25 1.25 0 0 1 1.25-1.25M12 7a5 5 0 0 1 5 5a5 5 0 0 1-5 5a5 5 0 0 1-5-5a5 5 0 0 1 5-5m0 2a3 3 0 0 0-3 3a3 3 0 0 0 3 3a3 3 0 0 0 3-3a3 3 0 0 0-3-3z"/></svg>',
		tiktok: '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1 0-5.78 2.92 2.92 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 3 15.57 6.33 6.33 0 0 0 9.37 22a6.33 6.33 0 0 0 6.36-6.22V8.79a8.18 8.18 0 0 0 3.86.96V6.69z"/></svg>',
	};
	const fallbackIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6C4.9 2 4 2.9 4 4V20C4 21.1 4.9 22 6 22H18C19.1 22 20 21.1 20 20V8L14 2Z"/></svg>';
	const storyIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5.5"/><circle cx="12" cy="12" r="2"/></svg>';
	const mediaIcons = {
		carousel: {
			className: 'dashicons-images-alt2',
			label: __('Media carousel', 'social-media-scheduler'),
		},
		video: {
			className: 'dashicons-video-alt3',
			label: __('Video media', 'social-media-scheduler'),
		},
		image: {
			className: 'dashicons-format-image',
			label: __('Image media', 'social-media-scheduler'),
		},
	};
	let visibleMonth = new Date(today.getFullYear(), today.getMonth(), 1);
	let scheduledPosts = [];
	let externalPosts = [];
	let activeStatus = 'ALL';
	let externalRefreshStarted = false;
	let pendingLoads = 0;

	function startOfDay(date) {
		return new Date(date.getFullYear(), date.getMonth(), date.getDate());
	}

	function addDays(date, amount) {
		const next = new Date(date);
		next.setDate(next.getDate() + amount);
		return next;
	}

	function isSameDate(left, right) {
		return left.getFullYear() === right.getFullYear()
			&& left.getMonth() === right.getMonth()
			&& left.getDate() === right.getDate();
	}

	function weekdayOffset(date) {
		return (date.getDay() - weekStart + 7) % 7;
	}

	function monthRange() {
		const monthStart = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth(), 1);
		const gridStart = addDays(monthStart, -weekdayOffset(monthStart));
		const gridEnd = addDays(gridStart, 42);
		return {
			from: gridStart.toISOString(),
			to: gridEnd.toISOString(),
			gridStart,
		};
	}

	function setStatus(message, tone = 'info') {
		if (!status) return;
		status.textContent = message;
		status.dataset.tone = tone;
	}

	function clearStatus() {
		setStatus('');
	}

	function setLoading(on) {
		if (!loadingIndicator) return;

		pendingLoads += on ? 1 : -1;
		if (pendingLoads < 0) pendingLoads = 0;
		loadingIndicator.hidden = pendingLoads === 0;
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

	async function loadPosts() {
		const range = monthRange();
		const params = new URLSearchParams({ from: range.from, to: range.to });
		scheduledPosts = await request(`posts?${params.toString()}`);
	}

	async function loadExternalPosts() {
		const month = visibleMonth.getMonth() + 1;
		const year = visibleMonth.getFullYear();
		externalPosts = await request(`external-posts?month=${month}&year=${year}`);
	}

	async function refreshExternalPostsInBackground() {
		if (externalRefreshStarted) return;

		externalRefreshStarted = true;
		setLoading(true);
		try {
			const refreshResult = await request('external-posts/refresh', { method: 'POST' });
			await loadExternalPosts();
			render();
			if (Array.isArray(refreshResult?.errors) && refreshResult.errors.length > 0) {
				setStatus(refreshResult.errors.join('\n'), 'error');
			} else {
				clearStatus();
			}
		} catch (error) {
			setStatus(error.message, 'error');
		} finally {
			setLoading(false);
		}
	}

	async function refresh(options = {}) {
		setLoading(true);
		try {
			setStatus(__('Loading calendar...', 'social-media-scheduler'));
			const results = await Promise.allSettled([loadPosts(), loadExternalPosts()]);
			const failure = results.find((result) => result.status === 'rejected');
			if (failure) {
				setStatus(failure.reason?.message || __('Could not load calendar posts.', 'social-media-scheduler'), 'error');
			} else {
				clearStatus();
			}
			render();
			if (options.refreshExternalPosts) {
				refreshExternalPostsInBackground();
			}
		} finally {
			setLoading(false);
		}
	}

	function dayKey(value) {
		return new Date(value).toDateString();
	}

	function postsForDay(date) {
		return scheduledPosts.filter((post) => {
			if (activeStatus !== 'ALL' && post.status !== activeStatus) return false;
			return dayKey(post.scheduledAt) === date.toDateString();
		});
	}

	function externalForDay(date) {
		return externalPosts.filter((post) => {
			const externalStatus = externalPostStatus(post);
			if (activeStatus !== 'ALL' && externalStatus !== activeStatus) return false;

			return dayKey(post.publishedAt) === date.toDateString();
		});
	}

	function platformLabel(platform) {
		const labels = {
			facebook: __('Facebook', 'social-media-scheduler'),
			instagram: __('Instagram', 'social-media-scheduler'),
			tiktok: __('TikTok', 'social-media-scheduler'),
		};
		return labels[platform] || platform;
	}

	function statusLabel(value) {
		const labels = {
			DRAFT: __('Draft', 'social-media-scheduler'),
			IN_REVIEW: __('In review', 'social-media-scheduler'),
			APPROVED: __('Approved', 'social-media-scheduler'),
			SCHEDULED: __('Scheduled', 'social-media-scheduler'),
			PUBLISHED: __('Published', 'social-media-scheduler'),
			FAILED: __('Failed', 'social-media-scheduler'),
			CANCELLED: __('Cancelled', 'social-media-scheduler'),
		};
		return labels[value] || value;
	}

	function parseMetadata(post) {
		if (!post?.metadata) return {};
		if (typeof post.metadata === 'object') return post.metadata;

		try {
			return JSON.parse(post.metadata);
		} catch {
			return {};
		}
	}

	function externalPostStatus(post) {
		const metadata = parseMetadata(post);
		const isScheduled = metadata.scheduled === true
			|| metadata.scheduled === 'true'
			|| metadata.scheduled === 1
			|| metadata.scheduled === '1';

		return isScheduled ? 'SCHEDULED' : 'PUBLISHED';
	}

	function isStoryPost(post) {
		const metadata = parseMetadata(post);
		return Boolean(post?.isStory || metadata.type === 'STORY' || metadata.type === 'story');
	}

	function isVideoMedia(media) {
		const type = String(media?.type || '').toLowerCase();
		const mimeType = String(media?.mimeType || '').toLowerCase();
		const filename = String(media?.filename || media?.url || '').toLowerCase();

		return type === 'video'
			|| mimeType.startsWith('video/')
			|| /\.(mp4|m4v|mov|webm|avi)$/i.test(filename);
	}

	function getMediaIcon(post) {
		if (!Array.isArray(post?.media) || post.media.length === 0) return null;
		if (post.media.length > 1) return mediaIcons.carousel;

		return isVideoMedia(post.media[0]) ? mediaIcons.video : mediaIcons.image;
	}

	function getExternalMediaIcon(post) {
		if (!post?.mediaUrl) return null;

		const metadata = parseMetadata(post);
		const mediaType = String(metadata.type || metadata.mediaType || metadata.media_type || '').toLowerCase();
		const mediaUrl = String(post.mediaUrl || '').toLowerCase();
		const isVideo = mediaType.includes('video') || /\.(mp4|m4v|mov|webm|avi)(\?|#|$)/i.test(mediaUrl);

		return isVideo ? mediaIcons.video : mediaIcons.image;
	}

	function postSummary(post, fallback) {
		return String(post?.title || post?.caption || post?.content || fallback || '').trim();
	}

	function buildItemLabel(parts, summary) {
		const label = parts.filter(Boolean).join(', ');
		return summary ? sprintf(__('%1$s: %2$s', 'social-media-scheduler'), label, summary) : label;
	}

	function createTimeBadge(value) {
		const time = document.createElement('span');
		time.className = 'sms-calendar-item__time';
		time.textContent = timeFormatter.format(new Date(value));
		return time;
	}

	function createPlatformBadge(platform, story = false) {
		const badge = document.createElement('span');
		badge.className = 'sms-calendar-item__platform-badge';
		badge.innerHTML = story ? storyIcon : platformIcons[platform] || fallbackIcon;
		badge.title = story
			? sprintf(__('%s story', 'social-media-scheduler'), platformLabel(platform))
			: platformLabel(platform);
		badge.setAttribute('aria-hidden', 'true');
		return badge;
	}

	function createMediaBadge(mediaIcon) {
		const badge = document.createElement('span');
		badge.className = `sms-calendar-item__media-badge dashicons ${mediaIcon.className}`;
		badge.title = mediaIcon.label;
		badge.setAttribute('aria-hidden', 'true');
		return badge;
	}

	function renderPost(post) {
		const item = document.createElement('a');
		item.className = `sms-calendar-item sms-calendar-item--${post.status.toLowerCase()}`;
		item.href = `${config.adminUrl}?page=sms-new-post&post=${post.id}`;

		const time = createTimeBadge(post.scheduledAt);
		const platform = createPlatformBadge(post.platform);
		const summary = postSummary(post, __('Untitled post', 'social-media-scheduler'));
		const label = buildItemLabel([platformLabel(post.platform), statusLabel(post.status), time.textContent], summary);
		item.setAttribute('aria-label', label);
		item.title = label;
		item.append(time, platform);

		const mediaIcon = getMediaIcon(post);
		if (mediaIcon) {
			item.append(createMediaBadge(mediaIcon));
		}

		return item;
	}

	function renderExternalPost(post) {
		const item = document.createElement('a');
		const externalStatus = externalPostStatus(post);
		item.className = externalStatus === 'PUBLISHED'
			? 'sms-calendar-item sms-calendar-item--published sms-calendar-item--external'
			: `sms-calendar-item sms-calendar-item--${externalStatus.toLowerCase()}`;
		item.href = post.permalink || '#';
		item.target = post.permalink ? '_blank' : '';
		item.rel = post.permalink ? 'noopener noreferrer' : '';

		const story = isStoryPost(post);
		const time = createTimeBadge(post.publishedAt);
		const platform = createPlatformBadge(post.platform, story);
		const platformText = story
			? sprintf(__('%s story', 'social-media-scheduler'), platformLabel(post.platform))
			: platformLabel(post.platform);
		const summary = postSummary(post, __('External post', 'social-media-scheduler'));
		const label = buildItemLabel([platformText, statusLabel(externalStatus), time.textContent], summary);
		item.setAttribute('aria-label', label);
		item.title = label;
		item.append(time, platform);

		const mediaIcon = getExternalMediaIcon(post);
		if (mediaIcon) {
			item.append(createMediaBadge(mediaIcon));
		}

		return item;
	}

	function render() {
		if (!grid || !labelText) return;

		labelText.textContent = new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' }).format(visibleMonth);
		grid.replaceChildren();

		const weekdayNames = weekStart === 1
			? [__('Mon', 'social-media-scheduler'), __('Tue', 'social-media-scheduler'), __('Wed', 'social-media-scheduler'), __('Thu', 'social-media-scheduler'), __('Fri', 'social-media-scheduler'), __('Sat', 'social-media-scheduler'), __('Sun', 'social-media-scheduler')]
			: [__('Sun', 'social-media-scheduler'), __('Mon', 'social-media-scheduler'), __('Tue', 'social-media-scheduler'), __('Wed', 'social-media-scheduler'), __('Thu', 'social-media-scheduler'), __('Fri', 'social-media-scheduler'), __('Sat', 'social-media-scheduler')];

		weekdayNames.forEach((name) => {
			const heading = document.createElement('div');
			heading.className = 'sms-calendar__weekday';
			heading.textContent = name;
			grid.appendChild(heading);
		});

		const range = monthRange();
		for (let index = 0; index < 42; index += 1) {
			const date = addDays(range.gridStart, index);
			const cell = document.createElement('section');
			cell.className = 'sms-calendar__day';
			if (date.getMonth() !== visibleMonth.getMonth()) cell.classList.add('is-outside-month');
			if (isSameDate(date, today)) cell.classList.add('is-today');

			const header = document.createElement('div');
			header.className = 'sms-calendar__day-header';
			header.textContent = String(date.getDate());
			cell.appendChild(header);

			postsForDay(date).forEach((post) => cell.appendChild(renderPost(post)));
			externalForDay(date).forEach((post) => cell.appendChild(renderExternalPost(post)));
			grid.appendChild(cell);
		}
	}

	document.addEventListener('DOMContentLoaded', () => {
		document.getElementById('sms-prev-month')?.addEventListener('click', () => {
			visibleMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() - 1, 1);
			refresh();
		});
		document.getElementById('sms-next-month')?.addEventListener('click', () => {
			visibleMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() + 1, 1);
			refresh();
		});
		document.getElementById('sms-today')?.addEventListener('click', () => {
			visibleMonth = new Date(today.getFullYear(), today.getMonth(), 1);
			refresh();
		});
		document.querySelectorAll('.sms-filter__button').forEach((button) => {
			button.addEventListener('click', () => {
				activeStatus = button.dataset.status || 'ALL';
				document.querySelectorAll('.sms-filter__button').forEach((item) => item.classList.remove('is-active'));
				button.classList.add('is-active');
				render();
			});
		});
		render();
		refresh({ refreshExternalPosts: true });
	});
})(window.wp, window.smsCalendar || {});
