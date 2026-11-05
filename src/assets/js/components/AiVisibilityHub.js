import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { TextControl, TextareaControl, ToggleControl, SelectControl, Button, Spinner, Notice } from '@wordpress/components';
import {
    AiBotIcon,
    CheckCircleIcon,
    AlertCircleIcon,
    ExternalLinkIcon,
} from './QuinrelIcons';

export const AiVisibilityHub = ({
    quinrelAdminConfig,
    aiBotActivity,
    refreshAiBotActivity,
}) => {
    const [activeSubTab, setActiveSubTab] = useState('telemetry');
    const [statusNotice, setStatusNotice] = useState(null);

    // ── 1. llms.txt Studio State ─────────────────────────────────────────────
    const [llmsConfig, setLlmsConfig] = useState({
        description: '',
        allow_index: true,
        allow_training: false,
        contact_url: '',
        extra_notes: '',
        post_types: ['post', 'page'],
        manual_override: false,
        manual_content: '',
        preview: '',
    });
    const [isLoadingLlms, setIsLoadingLlms] = useState(false);
    const [isSavingLlms, setIsSavingLlms] = useState(false);
    const [previewType, setPreviewType] = useState('summary');
    const [previewContent, setPreviewContent] = useState('');
    const [isLoadingPreview, setIsLoadingPreview] = useState(false);

    // ── 2. AI Crawler Shield State ───────────────────────────────────────────
    const [botPolicy, setBotPolicy] = useState('allow_all');
    const [customBotsText, setCustomBotsText] = useState('');
    const [mergedBots, setMergedBots] = useState([]);
    const [isLoadingShield, setIsLoadingShield] = useState(false);
    const [isSavingShield, setIsSavingShield] = useState(false);

    // ── 3. Content Governance & Opt-Out State ────────────────────────────────
    const [trainingOptOut, setTrainingOptOut] = useState(false);
    const [disclosureText, setDisclosureText] = useState('');
    const [disclosurePosition, setDisclosurePosition] = useState('after');
    const [isLoadingGov, setIsLoadingGov] = useState(false);
    const [isSavingGov, setIsSavingGov] = useState(false);

    const siteUrl = window.location.origin;

    // ── Fetch all settings on mount ──────────────────────────────────────────
    useEffect(() => {
        fetchAllSettings();
    }, []);

    const fetchAllSettings = async () => {
        setIsLoadingLlms(true);
        setIsLoadingShield(true);
        setIsLoadingGov(true);

        const headers = { 'X-WP-Nonce': quinrelAdminConfig.nonce };

        try {
            // 1. llms.txt config
            const resLlms = await fetch(`${quinrelAdminConfig.apiUrl}/llms-txt/config`, { headers });
            if (resLlms.ok) {
                const data = await resLlms.json();
                setLlmsConfig(data);
                setPreviewContent(data.preview || '');
            }

            // 2. robots-txt / bot policy
            const resRobots = await fetch(`${quinrelAdminConfig.apiUrl}/robots-txt`, { headers });
            if (resRobots.ok) {
                const data = await resRobots.json();
                setBotPolicy(data.ai_bot_policy || (data.block_ai_bots ? 'block_all' : 'allow_all'));
                setTrainingOptOut(Boolean(data.training_optout));
            }

            // 3. custom bots
            const resBots = await fetch(`${quinrelAdminConfig.apiUrl}/ai-bots`, { headers });
            if (resBots.ok) {
                const data = await resBots.json();
                setCustomBotsText((data.custom || []).join(', '));
                setMergedBots(data.merged || []);
            }

            // 4. disclosure settings
            const resDisc = await fetch(`${quinrelAdminConfig.apiUrl}/settings/ai-disclosure`, { headers });
            if (resDisc.ok) {
                const data = await resDisc.json();
                setDisclosureText(data.text || '');
                setDisclosurePosition(data.position || 'after');
            }
        } catch (e) {
            setStatusNotice({
                type: 'error',
                message: __('Failed to load AI governance settings.', 'quinrel'),
            });
        } finally {
            setIsLoadingLlms(false);
            setIsLoadingShield(false);
            setIsLoadingGov(false);
        }
    };

    // ── Save llms.txt settings ───────────────────────────────────────────────
    const handleSaveLlms = async () => {
        setIsSavingLlms(true);
        setStatusNotice(null);

        try {
            const res = await fetch(`${quinrelAdminConfig.apiUrl}/llms-txt/config`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': quinrelAdminConfig.nonce,
                },
                body: JSON.stringify(llmsConfig),
            });

            if (res.ok) {
                const data = await res.json();
                setLlmsConfig(data.config);
                setPreviewContent(data.config.preview || '');
                setStatusNotice({
                    type: 'success',
                    message: __('llms.txt configuration saved successfully.', 'quinrel'),
                });
            } else {
                throw new Error();
            }
        } catch (e) {
            setStatusNotice({
                type: 'error',
                message: __('Failed to save llms.txt configuration.', 'quinrel'),
            });
        } finally {
            setIsSavingLlms(false);
        }
    };

    // ── Fetch dynamic preview ────────────────────────────────────────────────
    const handleRefreshPreview = async (type) => {
        setIsLoadingPreview(true);
        try {
            const res = await fetch(`${quinrelAdminConfig.apiUrl}/ai/llms-preview?type=${type}`, {
                headers: { 'X-WP-Nonce': quinrelAdminConfig.nonce },
            });
            if (res.ok) {
                const data = await res.json();
                setPreviewContent(data.content || '');
                setPreviewType(type);
            }
        } catch (e) {
            // Silently fallback to existing preview
        } finally {
            setIsLoadingPreview(false);
        }
    };

    // ── Save AI Crawler Shield Policy ────────────────────────────────────────
    const handleSaveShield = async () => {
        setIsSavingShield(true);
        setStatusNotice(null);

        const customBotsArray = customBotsText
            .split(',')
            .map((b) => b.trim())
            .filter(Boolean);

        try {
            // Save policy to robots-txt endpoint
            const resPolicy = await fetch(`${quinrelAdminConfig.apiUrl}/robots-txt`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': quinrelAdminConfig.nonce,
                },
                body: JSON.stringify({ ai_bot_policy: botPolicy }),
            });

            // Save custom bots
            const resBots = await fetch(`${quinrelAdminConfig.apiUrl}/ai-bots`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': quinrelAdminConfig.nonce,
                },
                body: JSON.stringify({ custom_bots: customBotsArray }),
            });

            if (resPolicy.ok && resBots.ok) {
                const botData = await resBots.json();
                setMergedBots(botData.merged || []);
                setStatusNotice({
                    type: 'success',
                    message: __('AI Crawler Shield policy updated successfully.', 'quinrel'),
                });
            } else {
                throw new Error();
            }
        } catch (e) {
            setStatusNotice({
                type: 'error',
                message: __('Failed to save AI Crawler Shield settings.', 'quinrel'),
            });
        } finally {
            setIsSavingShield(false);
        }
    };

    // ── Save Governance & Opt-Out Settings ───────────────────────────────────
    const handleSaveGovernance = async () => {
        setIsSavingGov(true);
        setStatusNotice(null);

        try {
            // Save training opt-out toggle
            const resOpt = await fetch(`${quinrelAdminConfig.apiUrl}/ai-training-optout`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': quinrelAdminConfig.nonce,
                },
                body: JSON.stringify({ enabled: trainingOptOut }),
            });

            // Save disclosure text and position
            const resDisc = await fetch(`${quinrelAdminConfig.apiUrl}/settings/ai-disclosure`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': quinrelAdminConfig.nonce,
                },
                body: JSON.stringify({
                    text: disclosureText,
                    position: disclosurePosition,
                }),
            });

            if (resOpt.ok && resDisc.ok) {
                setStatusNotice({
                    type: 'success',
                    message: __('AI Governance and opt-out directives saved.', 'quinrel'),
                });
            } else {
                throw new Error();
            }
        } catch (e) {
            setStatusNotice({
                type: 'error',
                message: __('Failed to save AI governance directives.', 'quinrel'),
            });
        } finally {
            setIsSavingGov(false);
        }
    };

    // Sub-tab definitions
    const subTabs = [
        { id: 'telemetry', label: __('Crawler Telemetry', 'quinrel') },
        { id: 'llms_txt', label: __('llms.txt Studio', 'quinrel') },
        { id: 'shield', label: __('AI Crawler Shield', 'quinrel') },
        { id: 'governance', label: __('Content Governance & Opt-Out', 'quinrel') },
    ];

    const botStats = [
        {
            key: 'oai_searchbot',
            name: 'OAI-SearchBot',
            type: 'ChatGPT search',
            count: aiBotActivity ? (aiBotActivity.oai_searchbot || 0) : 0,
            badgeClass: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        },
        {
            key: 'gptbot',
            name: 'OpenAI (GPTBot)',
            type: 'Training crawler',
            count: aiBotActivity ? (aiBotActivity.gptbot || 0) : 0,
            badgeClass: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        },
        {
            key: 'claude_searchbot',
            name: 'Claude-SearchBot',
            type: 'Claude search',
            count: aiBotActivity ? (aiBotActivity.claude_searchbot || 0) : 0,
            badgeClass: 'bg-amber-50 text-amber-700 border-amber-200',
        },
        {
            key: 'claudebot',
            name: 'Anthropic (ClaudeBot)',
            type: 'Training crawler',
            count: aiBotActivity ? (aiBotActivity.claudebot || 0) : 0,
            badgeClass: 'bg-amber-50 text-amber-700 border-amber-200',
        },
        {
            key: 'perplexitybot',
            name: 'Perplexity AI',
            type: 'Search Agent (Attribution)',
            count: aiBotActivity ? (aiBotActivity.perplexitybot || 0) : 0,
            badgeClass: 'bg-sky-50 text-sky-700 border-sky-200',
        },
        {
            key: 'google_extended',
            name: 'Google-Extended',
            type: 'Model Training Only',
            count: aiBotActivity ? (aiBotActivity.google_extended || 0) : 0,
            badgeClass: 'bg-indigo-50 text-indigo-700 border-indigo-200',
        },
        {
            key: 'applebot',
            name: 'Applebot-Extended',
            type: 'Apple AI Training',
            count: aiBotActivity ? (aiBotActivity.applebot || 0) : 0,
            badgeClass: 'bg-slate-50 text-slate-700 border-slate-200',
        },
        {
            key: 'other_ai',
            name: 'Other AI Crawlers',
            type: 'CCBot, Cohere, ByteSpider',
            count: aiBotActivity ? (aiBotActivity.other_ai || 0) : 0,
            badgeClass: 'bg-purple-50 text-purple-700 border-purple-200',
        },
    ];

    return (
        <div className="space-y-6">
            {/* ── Header & Primary Disclaimer ── */}
            <div className="bg-white p-6 border rounded-xl shadow-xs">
                <div className="flex items-center gap-3 mb-2">
                    <span className="p-2.5 bg-indigo-50 text-indigo-600 rounded-lg">
                        <AiBotIcon className="w-6 h-6" />
                    </span>
                    <div>
                        <h2 className="text-xl font-bold text-slate-800 m-0">
                            {__('AI Visibility & Control', 'quinrel')}
                        </h2>
                        <p className="text-xs text-slate-500 m-0">
                            {__('Manage how AI assistants, LLMs, and search agents index and reference your website.', 'quinrel')}
                        </p>
                    </div>
                </div>

                <div className="bg-slate-50 border border-slate-200 rounded-lg p-3.5 mt-4 text-xs text-slate-600">
                    <p className="m-0">
                        {__('Quinrel publishes an llms.txt file on this site. It does not submit the site to ChatGPT, Perplexity, or Claude, and it does not check whether they mention it.', 'quinrel')}
                    </p>
                    <p className="mt-1 mb-0 text-slate-500">
                        {__('It does not score AI mentions, Google presence, or competitors.', 'quinrel')}
                    </p>
                </div>
            </div>

            {/* ── Notification Feedback ── */}
            {statusNotice && (
                <div role="status" aria-live="polite">
                    <Notice status={statusNotice.type} onRemove={() => setStatusNotice(null)}>
                        {statusNotice.message}
                    </Notice>
                </div>
            )}

            {/* ── Sub-navigation ── */}
            <div className="flex border-b border-slate-200 gap-2">
                {subTabs.map((tab) => (
                    <button
                        key={tab.id}
                        type="button"
                        onClick={() => setActiveSubTab(tab.id)}
                        className={`px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors cursor-pointer ${
                            activeSubTab === tab.id
                                ? 'border-indigo-600 text-indigo-600'
                                : 'border-transparent text-slate-600 hover:text-slate-900'
                        }`}
                    >
                        {tab.label}
                    </button>
                ))}
            </div>

            {/* ═════════════════════════════════════════════════════════════════ */}
            {/* SUB-TAB 1: TELEMETRY & CRAWLERS                                  */}
            {/* ═════════════════════════════════════════════════════════════════ */}
            {activeSubTab === 'telemetry' && (
                <div className="space-y-6">
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div className="bg-white p-5 border rounded-xl shadow-xs">
                            <span className="text-xs font-semibold text-slate-500 uppercase tracking-wide">
                                {__('Total AI Crawler Hits', 'quinrel')}
                            </span>
                            <div className="text-3xl font-extrabold text-slate-900 mt-2">
                                {aiBotActivity ? (aiBotActivity.total || 0) : 0}
                            </div>
                            <p className="text-xs text-slate-500 mt-1 mb-0">
                                {__('Buffered count over last 30 days', 'quinrel')}
                            </p>
                        </div>

                        <div className="bg-white p-5 border rounded-xl shadow-xs">
                            <span className="text-xs font-semibold text-slate-500 uppercase tracking-wide">
                                {__('Last Active Crawler', 'quinrel')}
                            </span>
                            <div className="text-xl font-bold text-slate-900 mt-2">
                                {aiBotActivity && aiBotActivity.last_bot
                                    ? String(aiBotActivity.last_bot).toUpperCase()
                                    : __('None recorded', 'quinrel')}
                            </div>
                            <p className="text-xs text-slate-500 mt-1 mb-0">
                                {__('Identified by HTTP User-Agent', 'quinrel')}
                            </p>
                        </div>

                        <div className="bg-white p-5 border rounded-xl shadow-xs">
                            <span className="text-xs font-semibold text-slate-500 uppercase tracking-wide">
                                {__('Last Seen Timestamp', 'quinrel')}
                            </span>
                            <div className="text-sm font-medium text-slate-800 mt-2">
                                {aiBotActivity && aiBotActivity.last_seen
                                    ? aiBotActivity.last_seen
                                    : __('Awaiting first crawler hit', 'quinrel')}
                            </div>
                            <div className="mt-2">
                                <Button
                                    isSecondary
                                    isSmall
                                    onClick={refreshAiBotActivity}
                                    className="cursor-pointer"
                                >
                                    {__('Refresh Telemetry', 'quinrel')}
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white p-6 border rounded-xl shadow-xs">
                        <h3 className="text-base font-bold text-slate-800 mb-1">
                            {__('Identified AI Agent Visits', 'quinrel')}
                        </h3>
                        <p className="text-xs text-slate-500 mb-4">
                            {__('Telemetry captured passively with zero database writes during visitor requests.', 'quinrel')}
                        </p>

                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            {botStats.map((bot) => (
                                <div
                                    key={bot.key}
                                    className="p-4 border rounded-lg bg-slate-50/50 flex flex-col justify-between"
                                >
                                    <div>
                                        <div className="flex items-center justify-between mb-1">
                                            <span className="font-semibold text-slate-800 text-sm">
                                                {bot.name}
                                            </span>
                                            <span className={`text-[10px] font-medium px-2 py-0.5 rounded border ${bot.badgeClass}`}>
                                                {bot.type}
                                            </span>
                                        </div>
                                    </div>
                                    <div className="mt-3 flex items-baseline justify-between">
                                        <span className="text-xs text-slate-500">{__('Recorded visits:', 'quinrel')}</span>
                                        <span className="text-lg font-bold text-slate-900">{bot.count}</span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            )}

            {/* ═════════════════════════════════════════════════════════════════ */}
            {/* SUB-TAB 2: LLMS.TXT STUDIO                                       */}
            {/* ═════════════════════════════════════════════════════════════════ */}
            {activeSubTab === 'llms_txt' && (
                <div className="space-y-6">
                    <div className="bg-white p-6 border rounded-xl shadow-xs space-y-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <h3 className="text-base font-bold text-slate-800 m-0">
                                    {__('llms.txt Generation Parameters', 'quinrel')}
                                </h3>
                                <p className="text-xs text-slate-500 mt-1 mb-0">
                                    {__('Controls the structured markdown digest served at /llms.txt', 'quinrel')}
                                </p>
                            </div>
                            <a
                                href={`${siteUrl}/llms.txt`}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-1.5 text-xs text-indigo-600 font-semibold hover:underline"
                            >
                                <span>{__('View Live File', 'quinrel')}</span>
                                <ExternalLinkIcon className="w-3.5 h-3.5" />
                            </a>
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-700 mb-1">
                                {__('Site Description for AI Agents', 'quinrel')}
                            </label>
                            <TextareaControl
                                value={llmsConfig.description || ''}
                                onChange={(val) => setLlmsConfig({ ...llmsConfig, description: val })}
                                help={__('A concise 1–2 sentence summary explaining the core topic and purpose of this site.', 'quinrel')}
                                rows={2}
                            />
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="p-3.5 border rounded-lg bg-slate-50">
                                <ToggleControl
                                    label={__('Allow Search Indexing', 'quinrel')}
                                    help={__('Permit AI search engines (like Perplexity and ChatGPT search) to retrieve and cite content.', 'quinrel')}
                                    checked={Boolean(llmsConfig.allow_index)}
                                    onChange={(val) => setLlmsConfig({ ...llmsConfig, allow_index: val })}
                                />
                            </div>
                            <div className="p-3.5 border rounded-lg bg-slate-50">
                                <ToggleControl
                                    label={__('Allow Foundation Model Training', 'quinrel')}
                                    help={__('Grant permission for crawlers to use your content to train foundational AI weights.', 'quinrel')}
                                    checked={Boolean(llmsConfig.allow_training)}
                                    onChange={(val) => setLlmsConfig({ ...llmsConfig, allow_training: val })}
                                />
                            </div>
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-700 mb-1">
                                {__('Contact / Author URL', 'quinrel')}
                            </label>
                            <TextControl
                                value={llmsConfig.contact_url || ''}
                                onChange={(val) => setLlmsConfig({ ...llmsConfig, contact_url: val })}
                                placeholder="https://example.com/contact or mailto:editor@example.com"
                                help={__('Where AI providers or publishers can reach the editorial team.', 'quinrel')}
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-700 mb-1">
                                {__('Additional Guidelines & Notes', 'quinrel')}
                            </label>
                            <TextareaControl
                                value={llmsConfig.extra_notes || ''}
                                onChange={(val) => setLlmsConfig({ ...llmsConfig, extra_notes: val })}
                                help={__('Any licensing terms, attribution guidelines, or special restrictions.', 'quinrel')}
                                rows={3}
                            />
                        </div>

                        <div className="p-4 border rounded-lg bg-amber-50/50 border-amber-200">
                            <ToggleControl
                                label={__('Enable Manual Override Mode', 'quinrel')}
                                help={__('Serve custom raw markdown instead of automated cornerstone content aggregation.', 'quinrel')}
                                checked={Boolean(llmsConfig.manual_override)}
                                onChange={(val) => setLlmsConfig({ ...llmsConfig, manual_override: val })}
                            />
                            {llmsConfig.manual_override && (
                                <div className="mt-3">
                                    <TextareaControl
                                        value={llmsConfig.manual_content || ''}
                                        onChange={(val) => setLlmsConfig({ ...llmsConfig, manual_content: val })}
                                        placeholder="# Custom llms.txt content..."
                                        rows={8}
                                    />
                                </div>
                            )}
                        </div>

                        <div>
                            <Button
                                isPrimary
                                isBusy={isSavingLlms}
                                disabled={isSavingLlms}
                                onClick={handleSaveLlms}
                                className="cursor-pointer"
                            >
                                {__('Save llms.txt Configuration', 'quinrel')}
                            </Button>
                        </div>
                    </div>

                    {/* Preview Deck */}
                    <div className="bg-white p-6 border rounded-xl shadow-xs">
                        <div className="flex items-center justify-between mb-3">
                            <div>
                                <h3 className="text-base font-bold text-slate-800 m-0">
                                    {__('Live Output Preview', 'quinrel')}
                                </h3>
                                <p className="text-xs text-slate-500 m-0">
                                    {__('Generated standard markdown index.', 'quinrel')}
                                </p>
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    isSecondary
                                    isSmall
                                    isPressed={previewType === 'summary'}
                                    onClick={() => handleRefreshPreview('summary')}
                                    disabled={isLoadingPreview}
                                    className="cursor-pointer"
                                >
                                    {__('Standard Index', 'quinrel')}
                                </Button>
                                <Button
                                    isSecondary
                                    isSmall
                                    isPressed={previewType === 'full'}
                                    onClick={() => handleRefreshPreview('full')}
                                    disabled={isLoadingPreview}
                                    className="cursor-pointer"
                                >
                                    {__('Full Digest', 'quinrel')}
                                </Button>
                            </div>
                        </div>

                        <div className="bg-slate-900 text-slate-50 p-4 rounded-lg font-mono text-xs overflow-auto max-h-96 whitespace-pre leading-relaxed">
                            {isLoadingPreview ? (
                                <div className="flex items-center justify-center p-8 text-slate-400">
                                    <Spinner />
                                    <span className="ml-2">{__('Generating preview...', 'quinrel')}</span>
                                </div>
                            ) : (
                                previewContent || __('No preview available.', 'quinrel')
                            )}
                        </div>
                    </div>
                </div>
            )}

            {/* ═════════════════════════════════════════════════════════════════ */}
            {/* SUB-TAB 3: AI CRAWLER SHIELD                                     */}
            {/* ═════════════════════════════════════════════════════════════════ */}
            {activeSubTab === 'shield' && (
                <div className="bg-white p-6 border rounded-xl shadow-xs space-y-6">
                    <div>
                        <h3 className="text-base font-bold text-slate-800 m-0">
                            {__('AI Crawler Access Policy', 'quinrel')}
                        </h3>
                        <p className="text-xs text-slate-500 mt-1 mb-0">
                            {__('Govern automated AI bot access at both the HTTP header level and via robots.txt.', 'quinrel')}
                        </p>
                    </div>

                    <div className="space-y-3">
                        <label className="block text-xs font-semibold text-slate-700">
                            {__('Global Enforcement Mode', 'quinrel')}
                        </label>

                        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <button
                                type="button"
                                onClick={() => setBotPolicy('allow_all')}
                                className={`p-4 border rounded-xl text-left cursor-pointer transition-all ${
                                    botPolicy === 'allow_all'
                                        ? 'border-indigo-600 bg-indigo-50/30 ring-2 ring-indigo-600/10'
                                        : 'border-slate-200 bg-white hover:bg-slate-50'
                                }`}
                            >
                                <div className="font-bold text-sm text-slate-900">
                                    {__('Allow All AI Crawlers', 'quinrel')}
                                </div>
                                <p className="text-xs text-slate-500 mt-1 mb-0">
                                    {__('Permits all search agents and model training scrapers for maximum potential visibility.', 'quinrel')}
                                </p>
                            </button>

                            <button
                                type="button"
                                onClick={() => setBotPolicy('search_only')}
                                className={`p-4 border rounded-xl text-left cursor-pointer transition-all ${
                                    botPolicy === 'search_only'
                                        ? 'border-indigo-600 bg-indigo-50/30 ring-2 ring-indigo-600/10'
                                        : 'border-slate-200 bg-white hover:bg-slate-50'
                                }`}
                            >
                                <div className="font-bold text-sm text-slate-900 flex items-center justify-between">
                                    <span>{__('Search Agents Only', 'quinrel')}</span>
                                    <span className="text-[10px] font-semibold uppercase bg-emerald-100 text-emerald-800 px-1.5 py-0.5 rounded">
                                        {__('Recommended', 'quinrel')}
                                    </span>
                                </div>
                                <p className="text-xs text-slate-500 mt-1 mb-0">
                                    {__('Blocks training crawlers such as GPTBot, ClaudeBot, and CCBot. OAI-SearchBot, Claude-SearchBot, and PerplexityBot stay allowed.', 'quinrel')}
                                </p>
                            </button>

                            <button
                                type="button"
                                onClick={() => setBotPolicy('block_all')}
                                className={`p-4 border rounded-xl text-left cursor-pointer transition-all ${
                                    botPolicy === 'block_all'
                                        ? 'border-indigo-600 bg-indigo-50/30 ring-2 ring-indigo-600/10'
                                        : 'border-slate-200 bg-white hover:bg-slate-50'
                                }`}
                            >
                                <div className="font-bold text-sm text-slate-900">
                                    {__('Block All AI Crawlers', 'quinrel')}
                                </div>
                                <p className="text-xs text-slate-500 mt-1 mb-0">
                                    {__('Strict protection: Returns 403 HTTP Access Denied and robots.txt Disallow for all recognized AI user agents.', 'quinrel')}
                                </p>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label className="block text-xs font-semibold text-slate-700 mb-1">
                            {__('Custom AI Bot User-Agents', 'quinrel')}
                        </label>
                        <TextControl
                            value={customBotsText}
                            onChange={(val) => setCustomBotsText(val)}
                            placeholder="CustomCrawler, DeepSeek-Scraper, MyBot"
                            help={__('Comma-separated list of additional crawler user-agent tokens to include in blocking rules.', 'quinrel')}
                        />
                    </div>

                    <div className="p-4 bg-slate-50 border rounded-lg">
                        <span className="text-xs font-semibold text-slate-700 block mb-2">
                            {__('Currently Monitored / Governed Bots:', 'quinrel')}
                        </span>
                        <div className="flex flex-wrap gap-1.5">
                            {mergedBots.map((b) => (
                                <span
                                    key={b}
                                    className="text-[11px] font-mono bg-white border border-slate-200 px-2 py-0.5 rounded text-slate-700"
                                >
                                    {b}
                                </span>
                            ))}
                        </div>
                    </div>

                    <div>
                        <Button
                            isPrimary
                            isBusy={isSavingShield}
                            disabled={isSavingShield}
                            onClick={handleSaveShield}
                            className="cursor-pointer"
                        >
                            {__('Save Crawler Shield Settings', 'quinrel')}
                        </Button>
                    </div>
                </div>
            )}

            {/* ═════════════════════════════════════════════════════════════════ */}
            {/* SUB-TAB 4: CONTENT GOVERNANCE & OPT-OUT                          */}
            {/* ═════════════════════════════════════════════════════════════════ */}
            {activeSubTab === 'governance' && (
                <div className="bg-white p-6 border rounded-xl shadow-xs space-y-6">
                    <div>
                        <h3 className="text-base font-bold text-slate-800 m-0">
                            {__('Machine Directives & AI Transparency', 'quinrel')}
                        </h3>
                        <p className="text-xs text-slate-500 mt-1 mb-0">
                            {__('Embed legal reservation headers and frontend transparency disclosures.', 'quinrel')}
                        </p>
                    </div>

                    <div className="p-4 border rounded-xl bg-slate-50/70 space-y-2">
                        <ToggleControl
                            label={__('Machine-Readable AI Training Opt-Out', 'quinrel')}
                            help={__('Automatically sends X-Robots-Tag: noai, noimageai and EU DSM Directive TDM-Reservation: 1 HTTP headers on all public requests.', 'quinrel')}
                            checked={trainingOptOut}
                            onChange={(val) => setTrainingOptOut(val)}
                        />
                        <p className="text-xs text-slate-500 m-0">
                            {__('Can be overridden on a per-post basis within the post editor.', 'quinrel')}
                        </p>
                    </div>

                    <div className="space-y-4">
                        <h4 className="text-sm font-bold text-slate-800 m-0">
                            {__('FTC-Compliant AI Content Disclosure Notice', 'quinrel')}
                        </h4>
                        <p className="text-xs text-slate-500 m-0">
                            {__('Automatically prepends or appends a transparency notice on articles marked as AI-assisted.', 'quinrel')}
                        </p>

                        <div>
                            <label className="block text-xs font-semibold text-slate-700 mb-1">
                                {__('Notice Copy', 'quinrel')}
                            </label>
                            <TextareaControl
                                value={disclosureText}
                                onChange={(val) => setDisclosureText(val)}
                                rows={3}
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-slate-700 mb-1">
                                {__('Notice Display Position', 'quinrel')}
                            </label>
                            <SelectControl
                                value={disclosurePosition}
                                onChange={(val) => setDisclosurePosition(val)}
                                options={[
                                    { label: __('After Post Content (Footer Notice)', 'quinrel'), value: 'after' },
                                    { label: __('Before Post Content (Header Notice)', 'quinrel'), value: 'before' },
                                ]}
                            />
                        </div>
                    </div>

                    <div>
                        <Button
                            isPrimary
                            isBusy={isSavingGov}
                            disabled={isSavingGov}
                            onClick={handleSaveGovernance}
                            className="cursor-pointer"
                        >
                            {__('Save Directives & Disclosure Settings', 'quinrel')}
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
};

