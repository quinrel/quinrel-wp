export const BING_KEY_MASK = '••••••••••••';

export function bingTrafficReady(tab, apiKey) {
	return tab === 'webmasters' && apiKey === BING_KEY_MASK;
}

export function buildSettingsPayload(settings, hydrated) {
	if (hydrated) {
		return settings;
	}
	const payload = { ...settings };
	delete payload.indexnow_key;
	return payload;
}

export function mapSettingsResponse(data) {
	const source = data && typeof data === 'object' ? data : {};
	return {
		google_indexing_key: '',
		indexnow_key: source.indexnow_key || '',
		indexnow_key_url: source.indexnow_key_url || '',
		google_indexing_configured: Boolean(source.google_indexing_configured),
		indexnow_configured: Boolean(source.indexnow_configured),
		auto_index: source.auto_index !== undefined ? source.auto_index : false,
		enable_google_indexing_api: Boolean(source.enable_google_indexing_api),
		delete_data_on_uninstall: Boolean(source.delete_data_on_uninstall),
		google_verify: source.google_verify || '',
		bing_verify: source.bing_verify || '',
		yandex_verify: source.yandex_verify || '',
		pinterest_verify: source.pinterest_verify || '',
		breadcrumb_separator: source.breadcrumb_separator || '›',
		breadcrumb_home_label: source.breadcrumb_home_label || 'Home',
		breadcrumb_auto_insert: source.breadcrumb_auto_insert || 'none',
		ai_provider: source.ai_provider || 'openai',
		openai_key: source.openai_key || '',
		anthropic_key: source.anthropic_key || '',
		ollama_url: source.ollama_url || 'http://localhost:11434',
		ollama_remote_confirmed: Boolean(source.ollama_remote_ack),
		default_share_image: source.default_share_image || '',
		gsc_client_id: source.gsc_client_id || '',
		gsc_client_secret: source.gsc_client_secret || '',
		gsc_connected: Boolean(source.gsc_connected),
		gsc_property: source.gsc_property || '',
		bing_api_key: source.bing_api_key || '',
		rss_before_content: source.rss_before_content || '',
		rss_after_content: source.rss_after_content || '',
		rss_attribution: Boolean(source.rss_attribution),
		noindex_paged_archives: Boolean(source.noindex_paged_archives),
		owns_head: Boolean(source.owns_head),
		defer_non_critical_scripts: Boolean(source.defer_non_critical_scripts),
		defer_script_handles: source.defer_script_handles || '',
		font_display_swap: Boolean(source.font_display_swap),
		pagespeed_api_key: source.pagespeed_api_key || '',
	};
}

export function settingsAfterSuccessfulSave(current, readResult) {
	if (readResult && typeof readResult === 'object') {
		return mapSettingsResponse(readResult);
	}
	const typed = typeof current.bing_api_key === 'string' ? current.bing_api_key : '';
	if (typed !== '' && typed !== BING_KEY_MASK) {
		return { ...current, bing_api_key: BING_KEY_MASK };
	}
	return current;
}

export function ollamaHostIsLoopback(url) {
	try {
		const parsed = new URL(url);
		if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') {
			return false;
		}
		const host = parsed.hostname.replace(/^\[|\]$/g, '').toLowerCase();
		if (host === 'localhost' || host.endsWith('.localhost') || host === '::1') {
			return true;
		}
		if (host.startsWith('::ffff:')) {
			return ollamaHostIsLoopback(`${parsed.protocol}//${host.slice(7)}`);
		}
		const parts = host.split('.');
		if (parts.length === 4 && parts.every((part) => /^\d+$/.test(part) && Number(part) >= 0 && Number(part) <= 255)) {
			return Number(parts[0]) === 127;
		}
		return false;
	} catch (error) {
		return false;
	}
}

function aiSettingsUntouched(current, baseline) {
	if (!baseline || typeof baseline !== 'object' || !current || typeof current !== 'object') {
		return false;
	}
	return current.ai_provider === baseline.ai_provider
		&& current.openai_key === baseline.openai_key
		&& current.anthropic_key === baseline.anthropic_key
		&& current.ollama_url === baseline.ollama_url
		&& Boolean(current.ollama_remote_confirmed) === Boolean(baseline.ollama_remote_confirmed);
}

export function settingsSaveErrors(settings, baseline) {
	if (aiSettingsUntouched(settings, baseline)) {
		return {};
	}
	const errors = {};
	const openaiKey = typeof settings.openai_key === 'string' ? settings.openai_key : '';
	const anthropicKey = typeof settings.anthropic_key === 'string' ? settings.anthropic_key : '';
	const ollamaUrl = typeof settings.ollama_url === 'string' ? settings.ollama_url : '';
	if (settings.ai_provider === 'openai' && !openaiKey.trim()) {
		errors.openai_key = 'OpenAI API key is required when OpenAI is selected as the provider.';
	}
	if (settings.ai_provider === 'anthropic' && !anthropicKey.trim()) {
		errors.anthropic_key = 'Anthropic API key is required when Anthropic is selected as the provider.';
	}
	if (settings.ai_provider === 'ollama' && !ollamaUrl.trim()) {
		errors.ollama_url = 'Ollama URL is required when Ollama is selected as the provider.';
	}
	if (settings.ai_provider === 'ollama' && ollamaUrl.trim() && !ollamaHostIsLoopback(ollamaUrl) && !settings.ollama_remote_confirmed) {
		errors.ollama_url = 'Confirm that this remote Ollama host may receive post content.';
	}
	return errors;
}

export async function commitSettings({ settings, hydrated, post, read }) {
	if (!hydrated) {
		return { saved: false, posted: false, response: null, settings };
	}
	const response = await post(buildSettingsPayload(settings, true));
	if (!response || response.ok !== true) {
		return { saved: false, posted: true, response, settings };
	}
	let readResult = null;
	try {
		readResult = await read();
	} catch (error) {
		readResult = null;
	}
	return {
		saved: true,
		posted: true,
		response,
		settings: settingsAfterSuccessfulSave(settings, readResult),
	};
}
