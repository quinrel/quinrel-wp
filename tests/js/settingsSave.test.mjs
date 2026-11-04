import assert from 'node:assert/strict';
import test from 'node:test';
import {
	BING_KEY_MASK,
	bingTrafficReady,
	buildSettingsPayload,
	commitSettings,
	ollamaHostIsLoopback,
	settingsSaveErrors,
} from '../../src/assets/js/settingsSave.mjs';

test('an unhydrated save does not post the empty IndexNow key', async () => {
	let posted = false;
	const result = await commitSettings({
		settings: { indexnow_key: '', auto_index: false, bing_api_key: '' },
		hydrated: false,
		post: () => {
			posted = true;
			return { ok: true };
		},
		read: async () => {
			throw new Error('read should not run');
		},
	});

	assert.equal(posted, false);
	assert.equal(result.posted, false);
	assert.equal(result.saved, false);
	assert.equal('indexnow_key' in buildSettingsPayload(result.settings, false), false);
});

test('a hydrated save can still clear the IndexNow key on purpose', async () => {
	let body = null;
	const result = await commitSettings({
		settings: { indexnow_key: '', auto_index: true, bing_api_key: BING_KEY_MASK },
		hydrated: true,
		post: async (payload) => {
			body = payload;
			return { ok: true };
		},
		read: async () => ({ indexnow_key: '', auto_index: true, bing_api_key: BING_KEY_MASK }),
	});

	assert.equal(body.indexnow_key, '');
	assert.equal(result.saved, true);
	assert.equal(result.settings.indexnow_key, '');
	assert.equal(result.settings.bing_api_key, BING_KEY_MASK);
});

test('saving a new Bing key refreshes it to the mask so the open tab can load traffic', async () => {
	const typed = 'bing-key-just-typed';
	assert.equal(bingTrafficReady('webmasters', typed), false);

	const result = await commitSettings({
		settings: { indexnow_key: 'kept-key', bing_api_key: typed, auto_index: true },
		hydrated: true,
		post: async () => ({ ok: true }),
		read: async () => ({ indexnow_key: 'kept-key', auto_index: true, bing_api_key: BING_KEY_MASK }),
	});

	assert.equal(result.settings.bing_api_key, BING_KEY_MASK);
	assert.equal(result.settings.indexnow_key, 'kept-key');
	assert.equal(bingTrafficReady('webmasters', result.settings.bing_api_key), true);
	assert.equal(bingTrafficReady('indexing', BING_KEY_MASK), false);
});

test('a failed settings refresh still masks a Bing key that was just saved', async () => {
	const result = await commitSettings({
		settings: { indexnow_key: 'kept-key', bing_api_key: 'bing-key-just-typed' },
		hydrated: true,
		post: async () => ({ ok: true }),
		read: async () => {
			throw new Error('settings read failed');
		},
	});

	assert.equal(result.saved, true);
	assert.equal(result.settings.bing_api_key, BING_KEY_MASK);
	assert.equal(result.settings.indexnow_key, 'kept-key');
	assert.equal(bingTrafficReady('webmasters', ''), false);
});

function aiBaseline(overrides = {}) {
	return {
		ai_provider: 'openai',
		openai_key: '',
		anthropic_key: '',
		ollama_url: 'http://localhost:11434',
		ollama_remote_confirmed: false,
		...overrides,
	};
}

test('an empty OpenAI key left as loaded does not block a Bing key save', () => {
	const baseline = aiBaseline();
	const current = { ...baseline, bing_api_key: 'typed-bing-key' };
	assert.deepEqual(settingsSaveErrors(current, baseline), {});
});

test('a changed AI provider still requires that provider key', () => {
	const baseline = aiBaseline();
	assert.deepEqual(settingsSaveErrors({ ...baseline, ai_provider: 'anthropic' }, baseline), {
		anthropic_key: 'Anthropic API key is required when Anthropic is selected as the provider.',
	});
	assert.deepEqual(settingsSaveErrors(baseline, null), {
		openai_key: 'OpenAI API key is required when OpenAI is selected as the provider.',
	});
});

test('a remote Ollama host still requires confirmation after the owner changes it', () => {
	const baseline = aiBaseline({ ai_provider: 'ollama' });
	const remote = { ...baseline, ollama_url: 'https://ollama.example.com' };
	assert.equal(ollamaHostIsLoopback(baseline.ollama_url), true);
	assert.equal(ollamaHostIsLoopback(remote.ollama_url), false);
	assert.deepEqual(settingsSaveErrors(remote, baseline), {
		ollama_url: 'Confirm that this remote Ollama host may receive post content.',
	});
	assert.deepEqual(settingsSaveErrors(remote, remote), {});
});
