import React, { useState, useEffect } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { BING_KEY_MASK, bingTrafficReady } from '../settingsSave.mjs';
import { TextControl, TextareaControl, ToggleControl, Button, Spinner, Notice } from '@wordpress/components';
import {
    CloudIcon,
    AnalyticsIcon,
    LinkIcon,
    SpeedIcon,
    AiBotIcon,
    RolesIcon,
    DatabaseIcon,
    CheckCircleIcon,
    AlertCircleIcon,
    ExternalLinkIcon,
    GoogleIcon,
    SettingsIcon,
} from './QuinrelIcons';

export const GlobalSettingsHub = ({
    settings,
    setSettings,
    isLoadingSettings,
    settingsLoaded,
    isSaving,
    saveSettings,
    saveStatus,
    setSaveStatus,
    formErrors,
    setFormErrors,
    subTab,
    setSubTab,
    // Quinrel Cloud Connection
    connection,
    siteToken,
    setSiteToken,
    authorizationCode,
    setAuthorizationCode,
    connectionBusy,
    connectionLoading,
    connectionError,
    connectionMessage,
    postConnection,
    // Google Search Console (Live Telemetry)
    gscOpen,
    setGscOpen,
    gscData,
    setGscData,
    gscLoading,
    gscError,
    gscSubTab,
    setGscSubTab,
    cannData,
    cannLoading,
    // AI Vault
    isTestingAi,
    testAiConnection,
    aiTestResult,
    setAiTestResult,
    ollamaHostIsLoopback,
    openMediaLibrary,
    // Roles & Capabilities
    rolesData,
    roleEdits,
    toggleCap,
    rolesSaving,
    rolesMsg,
    saveRoles,
    loadRoles,
    rolesLoading,
    // Import / Export
    exportMsg,
    downloadExport,
    importMsg,
    importBusy,
    handleImportFile,
    // Indexing Dispatch
    onManualIndexUrl,
}) => {
    const quinrelAdminConfig = window.quinrelAdminConfig || {};
    const [manualUrl, setManualUrl] = useState('');
    const [manualUrlBusy, setManualUrlBusy] = useState(false);
    const [manualUrlMsg, setManualUrlMsg] = useState(null);
    const [indexNowLog, setIndexNowLog] = useState([]);
    const [indexNowLogNote, setIndexNowLogNote] = useState(null);
    const [bingTraffic, setBingTraffic] = useState(null);
    const [bingTrafficError, setBingTrafficError] = useState('');
    const [bingTrafficLoading, setBingTrafficLoading] = useState(false);
    const [gscConnecting, setGscConnecting] = useState(false);
    const [gscActionMsg, setGscActionMsg] = useState(null);
    const [gscProperties, setGscProperties] = useState([]);
    const [loadingGscProperties, setLoadingGscProperties] = useState(false);
    const [selectedProperty, setSelectedProperty] = useState(settings.gsc_property || '');
    const [showCustomGscDrawer, setShowCustomGscDrawer] = useState(!settings.gsc_client_id);
    const [gscSyncing, setGscSyncing] = useState(false);
    const [gscSyncMsg, setGscSyncMsg] = useState(null);
    const [cloudAutoConnecting, setCloudAutoConnecting] = useState(false);
    const [showManualCloudDrawer, setShowManualCloudDrawer] = useState(false);

    const isCloudConnected = connection
        ? connection.connected === true
        : quinrelAdminConfig.cloudConfigured === '1';

    const SUBTABS = [
        { id: 'general', label: __('General & Breadcrumbs', 'quinrel'), icon: SettingsIcon },
        { id: 'cloud', label: __('Quinrel Cloud', 'quinrel'), icon: CloudIcon, badge: isCloudConnected ? __('Connected', 'quinrel') : null },
        { id: 'gsc', label: __('Google Search Console', 'quinrel'), icon: GoogleIcon, badge: settings.gsc_connected ? __('Connected', 'quinrel') : null },
        { id: 'webmasters', label: __('Bing & Verification', 'quinrel'), icon: AnalyticsIcon },
        { id: 'indexing', label: __('Indexing & APIs', 'quinrel'), icon: LinkIcon },
        { id: 'performance', label: __('Performance & Speed', 'quinrel'), icon: SpeedIcon },
        { id: 'ai', label: __('AI Engine Vault', 'quinrel'), icon: AiBotIcon },
        { id: 'roles', label: __('Roles & Capabilities', 'quinrel'), icon: RolesIcon },
        { id: 'advanced', label: __('Advanced & Data', 'quinrel'), icon: DatabaseIcon },
    ];

    const currentTab = subTab || 'general';

    const loadIndexNowLog = async () => {
        try {
            const resp = await fetch(`${quinrelAdminConfig.apiUrl}/indexing/indexnow-log`, {
                headers: { 'X-WP-Nonce': quinrelAdminConfig.nonce },
            });
            const data = await resp.json().catch(() => null);
            if (!resp.ok) {
                setIndexNowLog([]);
                setIndexNowLogNote({
                    type: 'error',
                    text: (data && data.message) ? data.message : __('You do not have permission to view IndexNow submissions.', 'quinrel'),
                });
                return;
            }
            setIndexNowLogNote(null);
            setIndexNowLog(Array.isArray(data) ? data.slice(0, 10) : []);
        } catch (e) {
            setIndexNowLog([]);
            setIndexNowLogNote({
                type: 'error',
                text: __('The submission list could not be loaded.', 'quinrel'),
            });
        }
    };

    useEffect(() => {
        if (currentTab === 'indexing') {
            loadIndexNowLog();
        }
    }, [currentTab]);

    useEffect(() => {
        if (!bingTrafficReady(currentTab, settings.bing_api_key)) {
            return undefined;
        }
        let cancelled = false;
        setBingTrafficLoading(true);
        setBingTrafficError('');
        fetch(`${quinrelAdminConfig.apiUrl}/analytics/bing/traffic`, {
            headers: { 'X-WP-Nonce': quinrelAdminConfig.nonce },
        })
            .then(async (resp) => {
                const data = await resp.json().catch(() => null);
                if (cancelled) {
                    return;
                }
                if (!resp.ok || !data || data.success !== true) {
                    setBingTraffic(null);
                    setBingTrafficError((data && data.message) ? data.message : __('Bing performance could not be loaded.', 'quinrel'));
                    return;
                }
                setBingTraffic(data);
            })
            .catch(() => {
                if (!cancelled) {
                    setBingTraffic(null);
                    setBingTrafficError(__('Bing performance could not be loaded.', 'quinrel'));
                }
            })
            .finally(() => {
                if (!cancelled) {
                    setBingTrafficLoading(false);
                }
            });
        return () => {
            cancelled = true;
        };
    }, [currentTab, settings.bing_api_key]);

    useEffect(() => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('gsc_connected') === '1') {
            setGscActionMsg({
                type: 'success',
                message: __('Google Search Console connected successfully! Live search performance metrics are now active.', 'quinrel'),
            });
            setSettings(prev => ({ ...prev, gsc_connected: true }));
            urlParams.delete('gsc_connected');
            const newSearch = urlParams.toString();
            const newUrl = window.location.pathname + (newSearch ? `?${newSearch}` : '') + window.location.hash;
            window.history.replaceState({}, document.title, newUrl);
        } else if (urlParams.get('gsc_error')) {
            setGscActionMsg({
                type: 'error',
                message: decodeURIComponent(urlParams.get('gsc_error')),
            });
            setShowCustomGscDrawer(true);
            urlParams.delete('gsc_error');
            const newSearch = urlParams.toString();
            const newUrl = window.location.pathname + (newSearch ? `?${newSearch}` : '') + window.location.hash;
            window.history.replaceState({}, document.title, newUrl);
        }
    }, []);

    useEffect(() => {
        if (settings.gsc_property && !selectedProperty) {
            setSelectedProperty(settings.gsc_property);
        }
    }, [settings.gsc_property]);

    useEffect(() => {
        if (settings.gsc_connected && currentTab === 'gsc') {
            setLoadingGscProperties(true);
            fetch(`${quinrelAdminConfig.apiUrl}/gsc/sites`, {
                headers: { 'X-WP-Nonce': quinrelAdminConfig.nonce },
            })
                .then(r => r.json())
                .then(data => {
                    if (data && data.sites && Array.isArray(data.sites)) {
                        setGscProperties(data.sites);
                    }
                })
                .catch(() => {})
                .finally(() => setLoadingGscProperties(false));
        }
    }, [settings.gsc_connected, currentTab]);

    const handlePropertyChange = async (e) => {
        const prop = e.target.value;
        setSelectedProperty(prop);
        setSettings(prev => ({ ...prev, gsc_property: prop }));
        try {
            await fetch(`${quinrelAdminConfig.apiUrl}/gsc/property`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': quinrelAdminConfig.nonce,
                },
                body: JSON.stringify({ property: prop }),
            });
            setGscActionMsg({
                type: 'success',
                message: __('Search Console property updated successfully.', 'quinrel'),
            });
        } catch (err) {}
    };

    const handleSyncGsc = async () => {
        setGscSyncing(true);
        setGscSyncMsg(null);
        try {
            const resp = await fetch(`${quinrelAdminConfig.apiUrl}/gsc/performance?days=28`, {
                headers: { 'X-WP-Nonce': quinrelAdminConfig.nonce },
            });
            const data = await resp.json();
            if (resp.ok) {
                if (setGscData && data.rows) {
                    setGscData(data);
                }
                setGscSyncMsg({
                    type: 'success',
                    message: __('Telemetry synchronized live with Google Search Console.', 'quinrel'),
                });
            } else {
                setGscSyncMsg({
                    type: 'error',
                    message: data.message || __('Failed to sync telemetry.', 'quinrel'),
                });
            }
        } catch (err) {
            setGscSyncMsg({
                type: 'error',
                message: __('Network error during synchronization.', 'quinrel'),
            });
        } finally {
            setGscSyncing(false);
        }
    };

    const handleAutoConnectCloud = async () => {
        setCloudAutoConnecting(true);
        try {
            const resp = await fetch(`${quinrelAdminConfig.apiUrl}/connection/auto-connect`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': quinrelAdminConfig.nonce,
                },
            });
            const data = await resp.json();
            if (resp.ok && data.connected) {
                setSaveStatus({
                    type: 'success',
                    message: __('Quinrel Cloud connected automatically! Autonomous intelligence features are now active.', 'quinrel'),
                });
                if (postConnection) postConnection('/connection/verify');
            } else if (data.auth_url) {
                window.location.href = data.auth_url;
            } else {
                setSaveStatus({
                    type: 'error',
                    message: data.message || __('Could not complete automated connection. Please try manual token entry.', 'quinrel'),
                });
                setShowManualCloudDrawer(true);
            }
        } catch (e) {
            setShowManualCloudDrawer(true);
        } finally {
            setCloudAutoConnecting(false);
        }
    };

    const handleConnectGsc = async () => {
        setGscConnecting(true);
        setGscActionMsg(null);
        try {
            const payload = {};
            if (settings.gsc_client_id) {
                payload.client_id = settings.gsc_client_id.trim();
            }
            if (settings.gsc_client_secret && settings.gsc_client_secret !== '••••••••••••') {
                payload.client_secret = settings.gsc_client_secret.trim();
            }

            const resp = await fetch(`${quinrelAdminConfig.apiUrl}/gsc/auth-url`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': quinrelAdminConfig.nonce,
                },
                body: JSON.stringify(payload),
            });
            const data = await resp.json().catch(() => null);

            if (resp.ok && data && data.auth_url) {
                window.location.href = data.auth_url;
            } else {
                const errorMsg = data?.message || (resp.status === 400
                    ? __('Save a Google OAuth client ID and client secret in the section below, then connect again.', 'quinrel')
                    : __('Failed to generate Google Search Console authorization URL.', 'quinrel'));
                setGscActionMsg({
                    type: 'error',
                    message: errorMsg,
                });
                setShowCustomGscDrawer(true);
            }
        } catch (e) {
            setGscActionMsg({
                type: 'error',
                message: __('Unable to connect to Google Search Console. Please verify your credentials and network connection.', 'quinrel'),
            });
            setShowCustomGscDrawer(true);
        } finally {
            setGscConnecting(false);
        }
    };

    const handleDisconnectGsc = async () => {
        if (!window.confirm(__('Are you sure you want to disconnect Google Search Console from this site?', 'quinrel'))) {
            return;
        }
        setGscConnecting(true);
        try {
            const resp = await fetch(`${quinrelAdminConfig.apiUrl}/gsc/disconnect`, {
                method: 'POST',
                headers: { 'X-WP-Nonce': quinrelAdminConfig.nonce },
            });
            const data = await resp.json();
            if (resp.ok) {
                setSettings(prev => ({ ...prev, gsc_connected: false }));
                setGscActionMsg({
                    type: 'success',
                    message: __('Google Search Console disconnected successfully.', 'quinrel'),
                });
            }
        } catch (e) {
            setGscActionMsg({
                type: 'error',
                message: __('Failed to disconnect Google Search Console.', 'quinrel'),
            });
        } finally {
            setGscConnecting(false);
        }
    };

    const handleTestManualIndex = async (e) => {
        e.preventDefault();
        if (!manualUrl.trim()) return;
        setManualUrlBusy(true);
        setManualUrlMsg(null);
        try {
            const resp = await fetch(`${quinrelAdminConfig.apiUrl}/indexing/indexnow-submit`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': quinrelAdminConfig.nonce,
                },
                body: JSON.stringify({ url: manualUrl.trim() }),
            });
            const data = await resp.json();
            if (resp.ok && data.success) {
                setManualUrlMsg({
                    type: 'success',
                    text: data.message || __('The URL was queued for IndexNow.', 'quinrel'),
                });
                loadIndexNowLog();
            } else {
                setManualUrlMsg({
                    type: 'error',
                    text: data.message || __('The URL was not queued.', 'quinrel'),
                });
            }
        } catch (err) {
            setManualUrlMsg({
                type: 'error',
                text: __('The URL was not queued. Check the connection and try again.', 'quinrel'),
            });
        } finally {
            setManualUrlBusy(false);
        }
    };

    const bingKeySaved = settings.bing_api_key === BING_KEY_MASK;
    const bingSiteMissing = bingKeySaved && Boolean(bingTraffic) && !bingTrafficError && bingTraffic.measured_days === 0;
    const showBingLink = !bingKeySaved || Boolean(bingTrafficError) || bingSiteMissing;

    const generateRandomIndexNowKey = () => {
        const chars = '0123456789abcdef';
        let key = '';
        for (let i = 0; i < 32; i++) {
            key += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        setSettings(prev => ({ ...prev, indexnow_key: key }));
        setSaveStatus({
            type: 'info',
            message: __('Generated random 32-character IndexNow key. Click "Save Settings" to apply.', 'quinrel'),
        });
    };

    return (
        <div className="quinrel-settings-hub space-y-6">
            {/* ══════════ SETTINGS MASTER HEADER ══════════ */}
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200">
                <div>
                    <h2 className="text-xl sm:text-2xl font-bold text-slate-900 m-0">
                        {__('Global Settings & Module Integrations', 'quinrel')}
                    </h2>
                    <p className="text-xs sm:text-sm text-slate-500 mt-1 mb-0">
                        {__('Configure search engine APIs, cloud intelligence, Core Web Vitals optimizations, and team capabilities.', 'quinrel')}
                    </p>
                </div>

                <div className="flex items-center gap-2.5 flex-shrink-0">
                    <button
                        type="button"
                        onClick={saveSettings}
                        disabled={isSaving || !settingsLoaded}
                        className="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs sm:text-sm font-semibold rounded-xl shadow-xs transition-all cursor-pointer disabled:opacity-50"
                    >
                        {isSaving ? (
                            <>
                                <Spinner />
                                <span>{__('Saving Settings...', 'quinrel')}</span>
                            </>
                        ) : (
                            <span>{__('Save Settings', 'quinrel')}</span>
                        )}
                    </button>
                </div>
            </div>

            {/* Save Status Notification */}
            {saveStatus && (
                <div role="status" aria-live="polite">
                    <Notice
                        status={saveStatus.type}
                        isDismissible={true}
                        onRemove={() => setSaveStatus(null)}
                        className="rounded-xl"
                    >
                        {saveStatus.message || saveStatus.text}
                    </Notice>
                </div>
            )}

            {/* ══════════ INTERNAL SUB-TAB PILL SELECTORS ══════════ */}
            <div
                className="flex items-center gap-1.5 p-1.5 bg-slate-100 rounded-xl overflow-x-auto border border-slate-200/80 scrollbar-none"
                role="tablist"
                aria-label={__('Settings Navigation Tabs', 'quinrel')}
            >
                {SUBTABS.map((tab) => {
                    const isTabActive = currentTab === tab.id;
                    const IconComp = tab.icon;
                    return (
                        <button
                            key={tab.id}
                            type="button"
                            role="tab"
                            id={`settings-tab-${tab.id}`}
                            aria-selected={isTabActive}
                            aria-controls={`settings-panel-${tab.id}`}
                            tabIndex={isTabActive ? 0 : -1}
                            onClick={() => setSubTab(tab.id)}
                            className={`flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-semibold whitespace-nowrap transition-all duration-150 cursor-pointer ${
                                isTabActive
                                    ? 'bg-white text-indigo-700 shadow-xs border border-slate-200/90'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-white/60 border border-transparent'
                            }`}
                        >
                            <IconComp className={`w-4 h-4 ${isTabActive ? 'text-indigo-600' : 'text-slate-400'}`} />
                            <span>{tab.label}</span>
                            {tab.badge && (
                                <span className="ml-1 text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800">
                                    {tab.badge}
                                </span>
                            )}
                        </button>
                    );
                })}
            </div>

            {isLoadingSettings ? (
                <div className="p-16 text-center bg-white rounded-2xl border border-slate-200 shadow-xs">
                    <Spinner />
                    <p className="text-sm text-slate-500 mt-3">{__('Loading configuration...', 'quinrel')}</p>
                </div>
            ) : (
                <div className="space-y-6">
                    {/* ══════════════════════════════════════════════════════════════
                        TAB 1: GENERAL & BREADCRUMBS
                       ══════════════════════════════════════════════════════════════ */}
                    {currentTab === 'general' && (
                        <div id="settings-panel-general" role="tabpanel" aria-labelledby="settings-tab-general" className="space-y-6">
                            {/* Breadcrumbs Configuration */}
                            <section className="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-4" aria-labelledby="quinrel-breadcrumbs-hdr">
                                <div>
                                    <h3 id="quinrel-breadcrumbs-hdr" className="text-base font-bold text-slate-900 m-0">
                                        {__('Breadcrumb Schema & Display', 'quinrel')}
                                    </h3>
                                    <p className="text-xs text-slate-500 mt-1 mb-0">
                                        {__('Configure breadcrumbs for both Schema.org BreadcrumbList structured data and user navigation.', 'quinrel')}
                                    </p>
                                </div>

                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <TextControl
                                        label={__('Home Crumb Label', 'quinrel')}
                                        value={settings.breadcrumb_home_label || ''}
                                        onChange={(val) => setSettings({ ...settings, breadcrumb_home_label: val })}
                                        placeholder="Home"
                                    />
                                    <TextControl
                                        label={__('Separator Symbol', 'quinrel')}
                                        value={settings.breadcrumb_separator || ''}
                                        onChange={(val) => setSettings({ ...settings, breadcrumb_separator: val })}
                                        placeholder="›"
                                    />
                                </div>

                                <div>
                                    <label htmlFor="quinrel-bc-auto" className="block text-xs font-semibold text-slate-700 mb-1.5">
                                        {__('Theme Insertion Mode', 'quinrel')}
                                    </label>
                                    <select
                                        id="quinrel-bc-auto"
                                        value={settings.breadcrumb_auto_insert || 'none'}
                                        onChange={(e) => setSettings({ ...settings, breadcrumb_auto_insert: e.target.value })}
                                        className="w-full text-xs px-3 py-2 bg-white border border-slate-300 rounded-lg shadow-2xs focus:border-indigo-500 focus:outline-none"
                                    >
                                        <option value="none">{__('Manual only (PHP callback or shortcode)', 'quinrel')}</option>
                                        <option value="before_content">{__('Prepend automatically before single post/page content', 'quinrel')}</option>
                                    </select>
                                    <p className="text-[11px] text-slate-400 mt-1 mb-0 font-mono">
                                        PHP: &lt;?php quinrel_breadcrumbs(); ?&gt; &nbsp;|&nbsp; Shortcode: [quinrel_breadcrumbs]
                                    </p>
                                </div>
                            </section>

                            <section className="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-4" aria-labelledby="quinrel-head-hdr">
                                <div>
                                    <h3 id="quinrel-head-hdr" className="text-base font-bold text-slate-900 m-0">
                                        {__('Titles and archive pages', 'quinrel')}
                                    </h3>
                                    <p className="text-xs text-slate-500 mt-1 mb-0">
                                        {__('A custom SEO title is used only when you have saved one. Archive pages after the first stay indexable.', 'quinrel')}
                                    </p>
                                </div>
                                <ToggleControl
                                    label={__('Noindex archive pages after the first', 'quinrel')}
                                    help={__('Off by default. Page 2 and later keep a canonical link to themselves. Turn this on to mark those pages noindex.', 'quinrel')}
                                    checked={!!settings.noindex_paged_archives}
                                    onChange={(val) => setSettings({ ...settings, noindex_paged_archives: val })}
                                />
                                <ToggleControl
                                    label={__('Quinrel owns the head while another SEO plugin is active', 'quinrel')}
                                    help={__('Off by default. Titles and meta stay with Yoast, Rank Math, All in One SEO, or SEOPress until you turn this on.', 'quinrel')}
                                    checked={!!settings.owns_head}
                                    onChange={(val) => setSettings({ ...settings, owns_head: val })}
                                />
                            </section>

                            {/* RSS Content Feed Customization */}
                            <section className="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-4" aria-labelledby="quinrel-rss-hdr">
                                <div>
                                    <h3 id="quinrel-rss-hdr" className="text-base font-bold text-slate-900 m-0">
                                        {__('RSS feed text', 'quinrel')}
                                    </h3>
                                    <p className="text-xs text-slate-500 mt-1 mb-0">
                                        {__('Add text before or after each feed item. The default attribution line stays off until you turn it on.', 'quinrel')}
                                    </p>
                                </div>

                                <ToggleControl
                                    label={__('Add the default attribution line', 'quinrel')}
                                    help={__('Off by default. When both boxes are empty, Quinrel adds “The post … first appeared on …” only if this is on. Text you save in the boxes is used either way.', 'quinrel')}
                                    checked={!!settings.rss_attribution}
                                    onChange={(val) => setSettings({ ...settings, rss_attribution: val })}
                                />

                                <div className="space-y-4">
                                    <TextareaControl
                                        label={__('Content to Prepend to RSS Items', 'quinrel')}
                                        value={settings.rss_before_content || ''}
                                        onChange={(val) => setSettings({ ...settings, rss_before_content: val })}
                                        rows={2}
                                        placeholder={__('e.g. <p>Originally published on %%BLOGLINK%%.</p>', 'quinrel')}
                                    />
                                    <TextareaControl
                                        label={__('Content to Append to RSS Items', 'quinrel')}
                                        value={settings.rss_after_content || ''}
                                        onChange={(val) => setSettings({ ...settings, rss_after_content: val })}
                                        rows={2}
                                        placeholder={__('e.g. <p>The post %%POSTLINK%% appeared first on %%BLOGDESCLINK%%.</p>', 'quinrel')}
                                    />
                                </div>
                            </section>

                            {/* Global Fallback Share Image */}
                            <section className="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-4" aria-labelledby="quinrel-social-hdr">
                                <div>
                                    <h3 id="quinrel-social-hdr" className="text-base font-bold text-slate-900 m-0">
                                        {__('Default Social Fallback Image', 'quinrel')}
                                    </h3>
                                    <p className="text-xs text-slate-500 mt-1 mb-0">
                                        {__('Displayed on OpenGraph / Twitter Cards when a shared post lacks a featured image or custom OG image tag.', 'quinrel')}
                                    </p>
                                </div>

                                <div className="flex flex-col sm:flex-row items-stretch sm:items-end gap-3">
                                    <div className="flex-1">
                                        <TextControl
                                            label={__('Image URL', 'quinrel')}
                                            value={settings.default_share_image || ''}
                                            onChange={(val) => setSettings({ ...settings, default_share_image: val })}
                                            placeholder="https://example.com/wp-content/uploads/default-og.jpg"
                                        />
                                    </div>
                                    {openMediaLibrary && (
                                        <button
                                            type="button"
                                            onClick={openMediaLibrary}
                                            className="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg border border-slate-300 transition-colors cursor-pointer self-start sm:self-auto mb-1"
                                        >
                                            {__('Select Image', 'quinrel')}
                                        </button>
                                    )}
                                </div>

                                {settings.default_share_image && (
                                    <div className="max-w-xs p-2 bg-slate-50 border border-slate-200 rounded-xl overflow-hidden">
                                        <img
                                            src={settings.default_share_image}
                                            alt={__('Social preview', 'quinrel')}
                                            className="w-full h-auto rounded-lg object-cover"
                                        />
                                    </div>
                                )}
                            </section>
                        </div>
                    )}

                    {/* ══════════════════════════════════════════════════════════════
                        TAB 2: QUINREL CLOUD (AUTOMATED 1-CLICK CONNECTION)
                       ══════════════════════════════════════════════════════════════ */}
                    {currentTab === 'cloud' && (
                        <div id="settings-panel-cloud" role="tabpanel" aria-labelledby="settings-tab-cloud" className="space-y-6">
                            <section className="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-5" aria-labelledby="quinrel-cloud-hdr">
                                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <h3 id="quinrel-cloud-hdr" className="text-base font-bold text-slate-900 m-0">
                                                {__('Quinrel Cloud Intelligent Engine', 'quinrel')}
                                            </h3>
                                            <span
                                                className={`text-[11px] font-bold px-2 py-0.5 rounded-full ${
                                                    isCloudConnected
                                                        ? 'bg-emerald-100 text-emerald-800 border border-emerald-200'
                                                        : 'bg-slate-100 text-slate-600 border border-slate-200'
                                                }`}
                                            >
                                                {isCloudConnected ? __('● Connected', 'quinrel') : __('○ Not Connected', 'quinrel')}
                                            </span>
                                        </div>
                                        <p className="text-xs text-slate-500 mt-1 mb-0">
                                            {__('Connect this WordPress site to Quinrel Cloud for autonomous AI audits, self-healing links, and actionable growth recommendations.', 'quinrel')}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <a
                                            href="https://quinrel.com/dashboard"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-lg transition-colors text-decoration-none"
                                        >
                                            <span>{__('Quinrel Cloud Portal', 'quinrel')}</span>
                                            <ExternalLinkIcon className="w-3 h-3 text-slate-400" />
                                        </a>
                                    </div>
                                </div>

                                {connectionLoading && (
                                    <div className="p-4 bg-slate-50 rounded-xl flex items-center gap-2 text-xs text-slate-600">
                                        <Spinner />
                                        <span>{__('Checking connection state...', 'quinrel')}</span>
                                    </div>
                                )}
                                {connectionError && (
                                    <div className="p-3 bg-red-50 border border-red-200 rounded-xl text-xs text-red-800" role="alert">
                                        {connectionError}
                                    </div>
                                )}
                                {connectionMessage && (
                                    <div className="p-3 bg-blue-50 border border-blue-200 rounded-xl text-xs text-blue-800" role="status">
                                        {connectionMessage}
                                    </div>
                                )}

                                {connection && connection.api_base && (
                                    <div className="p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-600 font-mono flex items-center justify-between">
                                        <span>{__('Endpoint:', 'quinrel')} {connection.api_base}</span>
                                        {connection.tier && (
                                            <span className="font-sans font-bold uppercase text-[10px] bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded">
                                                {connection.tier} {__('Tier', 'quinrel')}
                                            </span>
                                        )}
                                    </div>
                                )}

                                {!isCloudConnected ? (
                                    <div className="space-y-5">
                                        {/* Primary 1-Click Automated Connection Hero */}
                                        <div className="p-6 rounded-2xl bg-gradient-to-br from-indigo-50/80 via-white to-purple-50/60 border border-indigo-100/80 shadow-2xs space-y-4">
                                            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                                <div className="space-y-1">
                                                    <h4 className="text-sm font-bold text-slate-900 m-0 flex items-center gap-2">
                                                        <span className="text-base">⚡</span>
                                                        <span>{__('1-Click Automated Cloud Connection', 'quinrel')}</span>
                                                    </h4>
                                                    <p className="text-xs text-slate-600 m-0 max-w-xl">
                                                        {__('Pair this site with Quinrel Cloud automatically without copying or pasting API keys or tokens. Connecting Quinrel is optional.', 'quinrel')}
                                                    </p>
                                                </div>
                                                <button
                                                    type="button"
                                                    disabled={cloudAutoConnecting || connectionBusy}
                                                    onClick={handleAutoConnectCloud}
                                                    className="inline-flex items-center justify-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-xs sm:text-sm font-bold rounded-xl shadow-xs hover:shadow transition-all cursor-pointer disabled:opacity-50 flex-shrink-0"
                                                >
                                                    {cloudAutoConnecting ? (
                                                        <>
                                                            <Spinner />
                                                            <span>{__('Pairing Site...', 'quinrel')}</span>
                                                        </>
                                                    ) : (
                                                        <span>{__('Connect to Quinrel Cloud (Automated)', 'quinrel')}</span>
                                                    )}
                                                </button>
                                            </div>
                                            <p className="text-[11px] text-slate-500 m-0">
                                                {__('Technical SEO on this site keeps titles, sitemaps, redirects, and 404 logs completely functional without an account.', 'quinrel')}
                                            </p>
                                        </div>

                                        {/* Collapsible Advanced Manual Token Drawer */}
                                        <div className="border border-slate-200 rounded-xl overflow-hidden">
                                            <button
                                                type="button"
                                                onClick={() => setShowManualCloudDrawer(v => !v)}
                                                className="w-full px-4 py-3 bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-semibold flex items-center justify-between cursor-pointer border-none transition-colors"
                                            >
                                                <span className="flex items-center gap-2">
                                                    <span>⚙️</span>
                                                    <span>{__('Advanced: Manual Token Entry & Authorization Codes', 'quinrel')}</span>
                                                </span>
                                                <span className="text-slate-400 font-mono">{showManualCloudDrawer ? '▲' : '▼'}</span>
                                            </button>

                                            {showManualCloudDrawer && (
                                                <div className="p-5 bg-white space-y-4 border-t border-slate-200">
                                                    <p className="text-xs text-slate-500 m-0">
                                                        {__('The settings screen cannot change the Quinrel API host. Connecting Quinrel is optional.', 'quinrel')}
                                                    </p>
                                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-5 pt-1">
                                                        {/* Option A: Site Token */}
                                                        <div className="p-4 rounded-xl border border-slate-200 bg-slate-50/60 space-y-3">
                                                            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-700 m-0">
                                                                {__('Connect via Site Token', 'quinrel')}
                                                            </h4>
                                                            <p className="text-xs text-slate-500 m-0">
                                                                {__('Copy your site token from your Quinrel Cloud project dashboard.', 'quinrel')}
                                                            </p>
                                                            <input
                                                                type="password"
                                                                autoComplete="off"
                                                                spellCheck={false}
                                                                placeholder="qnl_live_..."
                                                                value={siteToken}
                                                                onChange={(e) => setSiteToken(e.target.value)}
                                                                className="w-full text-xs font-mono px-3 py-2 bg-white border border-slate-300 rounded-lg shadow-2xs focus:border-indigo-500 focus:outline-none"
                                                            />
                                                            <button
                                                                type="button"
                                                                disabled={connectionBusy || !siteToken.trim()}
                                                                onClick={() => postConnection('/connection/site-token', { site_token: siteToken.trim() })}
                                                                className="w-full py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg shadow-2xs transition-colors disabled:opacity-50 cursor-pointer"
                                                            >
                                                                {connectionBusy ? __('Connecting...', 'quinrel') : __('Connect with site token', 'quinrel')}
                                                            </button>
                                                        </div>

                                                        {/* Option B: Authorization Code */}
                                                        <div className="p-4 rounded-xl border border-slate-200 bg-slate-50/60 space-y-3">
                                                            <h4 className="text-xs font-bold uppercase tracking-wider text-slate-700 m-0">
                                                                {__('Connect via Auth Code', 'quinrel')}
                                                            </h4>
                                                            <p className="text-xs text-slate-500 m-0">
                                                                {__('One-time authorization code generated during manual registration.', 'quinrel')}
                                                            </p>
                                                            <input
                                                                type="password"
                                                                autoComplete="off"
                                                                spellCheck={false}
                                                                placeholder="qauth_..."
                                                                value={authorizationCode}
                                                                onChange={(e) => setAuthorizationCode(e.target.value)}
                                                                className="w-full text-xs font-mono px-3 py-2 bg-white border border-slate-300 rounded-lg shadow-2xs focus:border-indigo-500 focus:outline-none"
                                                            />
                                                            <button
                                                                type="button"
                                                                disabled={connectionBusy || !authorizationCode.trim()}
                                                                onClick={() => postConnection('/connection/authorization-code', { code: authorizationCode.trim() })}
                                                                className="w-full py-2 bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold rounded-lg shadow-2xs transition-colors disabled:opacity-50 cursor-pointer"
                                                            >
                                                                {connectionBusy ? __('Connecting...', 'quinrel') : __('Connect with authorization code', 'quinrel')}
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ) : (
                                    <div className="p-4 bg-emerald-50/60 border border-emerald-200 rounded-xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                                        <div className="flex items-center gap-2.5">
                                            <CheckCircleIcon className="w-5 h-5 text-emerald-600" />
                                            <div>
                                                <strong className="text-xs font-bold text-emerald-900">
                                                    {__('Your site is securely connected to Quinrel Cloud.', 'quinrel')}
                                                </strong>
                                                <p className="text-xs text-emerald-700 m-0 mt-0.5">
                                                    {__('Real-time synchronization and cloud intelligence features are fully functional.', 'quinrel')}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2 flex-shrink-0">
                                            <button
                                                type="button"
                                                disabled={connectionBusy}
                                                onClick={() => postConnection('/connection/verify')}
                                                className="px-3 py-1.5 bg-white border border-emerald-300 text-emerald-800 hover:bg-emerald-50 text-xs font-semibold rounded-lg transition-colors cursor-pointer"
                                            >
                                                {__('Check saved credential', 'quinrel')}
                                            </button>
                                            <button
                                                type="button"
                                                disabled={connectionBusy}
                                                onClick={() => postConnection('/connection/rotate')}
                                                className="px-3 py-1.5 bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 text-xs font-semibold rounded-lg transition-colors cursor-pointer"
                                            >
                                                {__('Rotate credential', 'quinrel')}
                                            </button>
                                            <button
                                                type="button"
                                                disabled={connectionBusy}
                                                onClick={() => postConnection('/connection/revoke')}
                                                className="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-lg transition-colors cursor-pointer"
                                            >
                                                {__('Disconnect', 'quinrel')}
                                            </button>
                                        </div>
                                    </div>
                                )}
                            </section>
                        </div>
                    )}

                    {/* ══════════════════════════════════════════════════════════════
                        TAB 3: GOOGLE SEARCH CONSOLE (SITE KIT-STYLE 1-CLICK FLOW)
                       ══════════════════════════════════════════════════════════════ */}
                    {currentTab === 'gsc' && (
                        <div id="settings-panel-gsc" role="tabpanel" aria-labelledby="settings-tab-gsc" className="space-y-6">
                            <section className="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-6" aria-labelledby="quinrel-gsc-hdr">
                                {/* Header */}
                                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100">
                                    <div className="flex items-start sm:items-center gap-3">
                                        <div className="p-2.5 rounded-xl bg-slate-50 border border-slate-200 flex-shrink-0">
                                            <GoogleIcon className="w-6 h-6" />
                                        </div>
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <h3 id="quinrel-gsc-hdr" className="text-base font-bold text-slate-900 m-0">
                                                    {__('Google Search Console', 'quinrel')}
                                                </h3>
                                                <span
                                                    className={`text-[11px] font-bold px-2 py-0.5 rounded-full ${
                                                        settings.gsc_connected
                                                            ? 'bg-emerald-100 text-emerald-800 border border-emerald-200'
                                                            : 'bg-slate-100 text-slate-600 border border-slate-200'
                                                    }`}
                                                >
                                                    {settings.gsc_connected ? __('● Connected', 'quinrel') : __('○ Not Connected', 'quinrel')}
                                                </span>
                                            </div>
                                            <p className="text-xs text-slate-500 mt-1 mb-0">
                                                {__('Stream search queries, organic clicks, impressions, CTR, and average rank positions directly from Google.', 'quinrel')}
                                            </p>
                                        </div>
                                    </div>

                                    {settings.gsc_connected && (
                                        <div className="flex items-center gap-2">
                                            <a
                                                href="https://search.google.com/search-console"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-lg transition-colors text-decoration-none"
                                            >
                                                <span>{__('Google Search Console ↗', 'quinrel')}</span>
                                            </a>
                                        </div>
                                    )}
                                </div>

                                {gscActionMsg && (
                                    <div
                                        className={`p-3 rounded-xl text-xs ${
                                            gscActionMsg.type === 'success'
                                                ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
                                                : 'bg-red-50 text-red-800 border border-red-200'
                                        }`}
                                        role="alert"
                                    >
                                        {gscActionMsg.message}
                                    </div>
                                )}

                                {gscSyncMsg && (
                                    <div
                                        className={`p-3 rounded-xl text-xs ${
                                            gscSyncMsg.type === 'success'
                                                ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
                                                : 'bg-red-50 text-red-800 border border-red-200'
                                        }`}
                                        role="status"
                                    >
                                        {gscSyncMsg.message}
                                    </div>
                                )}

                                {!settings.gsc_connected ? (
                                    <div className="space-y-6">
                                        <div className="p-6 rounded-2xl bg-gradient-to-br from-blue-50/70 via-white to-indigo-50/50 border border-blue-100 shadow-2xs space-y-4">
                                            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-5">
                                                <div className="space-y-1.5">
                                                    <h4 className="text-sm font-bold text-slate-900 m-0 flex items-center gap-2">
                                                        <GoogleIcon className="w-4 h-4" />
                                                        <span>{__('Google Search Console', 'quinrel')}</span>
                                                    </h4>
                                                    <p className="text-xs text-slate-600 m-0 max-w-xl">
                                                        {__('Save a Google OAuth client ID and client secret below. Connect opens Google so you can allow read-only access to this site\'s search data.', 'quinrel')}
                                                    </p>
                                                </div>

                                                <button
                                                    type="button"
                                                    disabled={gscConnecting}
                                                    onClick={handleConnectGsc}
                                                    className="inline-flex items-center justify-center gap-2.5 px-6 py-3 bg-white hover:bg-slate-50 text-slate-800 text-xs sm:text-sm font-bold rounded-xl border border-slate-300 shadow-xs hover:shadow transition-all cursor-pointer disabled:opacity-50 flex-shrink-0"
                                                >
                                                    <GoogleIcon className="w-4 h-4" />
                                                    {gscConnecting ? (
                                                        <>
                                                            <Spinner />
                                                            <span>{__('Connecting with Google...', 'quinrel')}</span>
                                                        </>
                                                    ) : (
                                                        <span>{__('Connect Google Search Console', 'quinrel')}</span>
                                                    )}
                                                </button>
                                            </div>

                                            <div className="pt-2 border-t border-blue-100/60 flex flex-wrap items-center gap-4 text-[11px] text-slate-500">
                                                <span>✓ {__('Read-only Webmasters scope', 'quinrel')}</span>
                                                <span>✓ {__('Direct Google OAuth 2.0 flow', 'quinrel')}</span>
                                                <span>✓ {__('No tracking or third-party brokers', 'quinrel')}</span>
                                            </div>
                                        </div>

                                        {/* Required Google OAuth client credentials */}
                                        <div className="border border-slate-200 rounded-xl overflow-hidden">
                                            <button
                                                type="button"
                                                onClick={() => setShowCustomGscDrawer(v => !v)}
                                                className="w-full px-4 py-3 bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-semibold flex items-center justify-between cursor-pointer border-none transition-colors"
                                            >
                                                <span className="flex items-center gap-2">
                                                    <span>⚙️</span>
                                                    <span>{__('Google OAuth client ID and client secret', 'quinrel')}</span>
                                                </span>
                                                <span className="text-slate-400 font-mono">{showCustomGscDrawer ? '▲' : '▼'}</span>
                                            </button>

                                            {showCustomGscDrawer && (
                                                <div className="p-5 bg-white space-y-4 border-t border-slate-200">
                                                    <p className="text-xs text-slate-500 m-0">
                                                        {__('Paste the client ID and client secret from your Google Cloud OAuth client. Add the redirect URI below to that client\'s authorized redirect URIs.', 'quinrel')}
                                                    </p>

                                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                        <TextControl
                                                            label={__('Google OAuth Client ID', 'quinrel')}
                                                            value={settings.gsc_client_id || ''}
                                                            onChange={(val) => setSettings({ ...settings, gsc_client_id: val })}
                                                            placeholder="1234567890-xxx.apps.googleusercontent.com"
                                                            help={
                                                                settings.gsc_client_id && settings.gsc_client_id.includes('@') && !settings.gsc_client_id.endsWith('.apps.googleusercontent.com')
                                                                    ? __('Note: Google OAuth Client ID should be the Client ID from Google Cloud Console (typically ending with .apps.googleusercontent.com), not an email address.', 'quinrel')
                                                                    : null
                                                            }
                                                        />
                                                        <TextControl
                                                            label={__('Google OAuth Client Secret', 'quinrel')}
                                                            value={settings.gsc_client_secret || ''}
                                                            type="password"
                                                            onChange={(val) => setSettings({ ...settings, gsc_client_secret: val })}
                                                            placeholder={settings.gsc_client_secret ? '••••••••••••' : 'GOCSPX-...'}
                                                        />
                                                    </div>

                                                    <div className="p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-600 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                                        <div>
                                                            <strong>{__('Authorized Redirect URI:', 'quinrel')}</strong>{' '}
                                                            <code className="bg-white px-2 py-0.5 rounded border border-slate-200 font-mono select-all">
                                                                {`${quinrelAdminConfig.apiUrl}/gsc/callback`}
                                                            </code>
                                                        </div>
                                                        <span className="text-[11px] text-slate-500">
                                                            {__('Copy this exact URI to Google Cloud Console authorized redirect URIs.', 'quinrel')}
                                                        </span>
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ) : (
                                    <div className="space-y-6">
                                        {/* Connected Property Management */}
                                        <div className="p-5 bg-emerald-50/60 border border-emerald-200 rounded-2xl space-y-4">
                                            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                                <div className="flex items-center gap-2.5">
                                                    <CheckCircleIcon className="w-5 h-5 text-emerald-600 flex-shrink-0" />
                                                    <div>
                                                        <strong className="text-xs font-bold text-emerald-900">
                                                            {__('Google Search Console is connected and streaming data.', 'quinrel')}
                                                        </strong>
                                                        <p className="text-xs text-emerald-700 m-0 mt-0.5">
                                                            {__('Quinrel reads search performance metrics and alerts you of declining keywords and cannibalization.', 'quinrel')}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-2 flex-shrink-0">
                                                    <button
                                                        type="button"
                                                        disabled={gscSyncing}
                                                        onClick={handleSyncGsc}
                                                        className="px-3.5 py-1.5 bg-white border border-emerald-300 text-emerald-800 hover:bg-emerald-50 text-xs font-semibold rounded-lg shadow-2xs transition-colors cursor-pointer flex items-center gap-1.5"
                                                    >
                                                        {gscSyncing ? <Spinner /> : <span>🔄</span>}
                                                        <span>{gscSyncing ? __('Syncing...', 'quinrel') : __('Sync Now', 'quinrel')}</span>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        disabled={gscConnecting}
                                                        onClick={handleDisconnectGsc}
                                                        className="px-3.5 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded-lg shadow-2xs transition-colors cursor-pointer"
                                                    >
                                                        {__('Disconnect', 'quinrel')}
                                                    </button>
                                                </div>
                                            </div>

                                            {/* Property Selector */}
                                            <div className="pt-3 border-t border-emerald-200/80">
                                                <label htmlFor="quinrel-gsc-property-select" className="block text-xs font-semibold text-emerald-950 mb-1.5">
                                                    {__('Active Search Console Property:', 'quinrel')}
                                                </label>
                                                <div className="flex flex-col sm:flex-row sm:items-center gap-3">
                                                    <select
                                                        id="quinrel-gsc-property-select"
                                                        value={selectedProperty}
                                                        onChange={handlePropertyChange}
                                                        disabled={loadingGscProperties}
                                                        className="w-full sm:max-w-md text-xs px-3 py-2 bg-white border border-emerald-300 rounded-lg shadow-2xs focus:border-indigo-500 focus:outline-none"
                                                    >
                                                        <option value="">{loadingGscProperties ? __('Loading verified properties…', 'quinrel') : __('Default Site URL (Auto)', 'quinrel')}</option>
                                                        {gscProperties.map((entry, idx) => (
                                                            <option key={idx} value={entry.siteUrl || entry}>
                                                                {entry.siteUrl || entry} {entry.permissionLevel ? `(${entry.permissionLevel})` : ''}
                                                            </option>
                                                        ))}
                                                    </select>
                                                    <span className="text-[11px] text-emerald-700">
                                                        {__('Select Domain Property or URL prefix property matching this installation.', 'quinrel')}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Performance Preview inside GSC Tab */}
                                        {gscData?.summary && (
                                            <div className="space-y-3 pt-2">
                                                <h4 className="text-xs font-bold uppercase tracking-wider text-slate-700 m-0">
                                                    {__('28-Day Google Performance Snapshot', 'quinrel')}
                                                </h4>
                                                <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                                                    <div className="p-4 rounded-xl border border-slate-200 bg-slate-50/50">
                                                        <div className="text-xs font-semibold text-slate-400">{__('Clicks', 'quinrel')}</div>
                                                        <div className="text-2xl font-bold text-emerald-600 mt-1">{gscData.summary.clicks || 0}</div>
                                                    </div>
                                                    <div className="p-4 rounded-xl border border-slate-200 bg-slate-50/50">
                                                        <div className="text-xs font-semibold text-slate-400">{__('Impressions', 'quinrel')}</div>
                                                        <div className="text-2xl font-bold text-slate-900 mt-1">{gscData.summary.impressions || 0}</div>
                                                    </div>
                                                    <div className="p-4 rounded-xl border border-slate-200 bg-slate-50/50">
                                                        <div className="text-xs font-semibold text-slate-400">{__('Avg. CTR', 'quinrel')}</div>
                                                        <div className="text-2xl font-bold text-slate-900 mt-1">{gscData.summary.avg_ctr || 0}%</div>
                                                    </div>
                                                    <div className="p-4 rounded-xl border border-slate-200 bg-slate-50/50">
                                                        <div className="text-xs font-semibold text-slate-400">{__('Avg. Position', 'quinrel')}</div>
                                                        <div className="text-2xl font-bold text-indigo-600 mt-1">{gscData.summary.avg_position || 0}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </section>
                        </div>
                    )}

                    {/* ══════════════════════════════════════════════════════════════
                        TAB 4: SEARCH ENGINE VERIFICATION & BING WEBMASTER
                       ══════════════════════════════════════════════════════════════ */}
                    {currentTab === 'webmasters' && (
                        <div id="settings-panel-webmasters" role="tabpanel" aria-labelledby="settings-tab-webmasters" className="space-y-6">
                            {/* Bing Webmaster Tools */}
                            <section className="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-4" aria-labelledby="quinrel-bing-hdr">
                                <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                                    <div>
                                        <h3 id="quinrel-bing-hdr" className="text-base font-bold text-slate-900 m-0">
                                            {__('Bing Webmaster Tools', 'quinrel')}
                                        </h3>
                                        <p className="text-xs text-slate-500 mt-1 mb-0">
                                            {bingKeySaved
                                                ? __('Opening this tab sends this site’s address to Bing and reads clicks and impressions.', 'quinrel')
                                                : __('In Bing, open Settings, then API Access, and generate a key. Paste it below and save. The site in Bing must match this site’s address.', 'quinrel')}
                                        </p>
                                    </div>
                                    {showBingLink && (
                                        <a
                                            href={bingSiteMissing ? 'https://www.bing.com/webmasters/' : 'https://www.bing.com/webmasters/home/api'}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs sm:text-sm font-semibold rounded-xl shadow-xs transition-colors text-decoration-none focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                                        >
                                            <span>
                                                {bingSiteMissing
                                                    ? __('Open Bing Webmaster Tools', 'quinrel')
                                                    : __('Open Bing API Access', 'quinrel')}
                                            </span>
                                            <ExternalLinkIcon className="w-3.5 h-3.5" />
                                            <span className="screen-reader-text">{__('(opens in a new tab)', 'quinrel')}</span>
                                        </a>
                                    )}
                                </div>

                                <TextControl
                                    label={__('Bing Webmaster API Key', 'quinrel')}
                                    value={settings.bing_api_key || ''}
                                    type="password"
                                    onChange={(val) => setSettings({ ...settings, bing_api_key: val })}
                                />
                                {settings.bing_api_key === BING_KEY_MASK && bingTrafficLoading && (
                                    <p className="text-xs text-slate-600 m-0" role="status">
                                        {__('Loading Bing performance…', 'quinrel')}
                                    </p>
                                )}
                                {settings.bing_api_key === BING_KEY_MASK && bingTrafficError && (
                                    <div className="p-3 rounded-xl text-xs bg-red-50 text-red-800 border border-red-200" role="alert">
                                        {bingTrafficError}
                                    </div>
                                )}
                                {settings.bing_api_key === BING_KEY_MASK && bingTraffic && !bingTrafficError && bingTraffic.measured_days > 0 && (
                                    <div className="space-y-3">
                                        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                                            <div className="p-4 rounded-xl border border-slate-200 bg-slate-50/50">
                                                <div className="text-xs font-semibold text-slate-500">{__('Clicks', 'quinrel')}</div>
                                                <div className="text-2xl font-bold text-slate-900 mt-1">{bingTraffic.clicks}</div>
                                            </div>
                                            <div className="p-4 rounded-xl border border-slate-200 bg-slate-50/50">
                                                <div className="text-xs font-semibold text-slate-500">{__('Impressions', 'quinrel')}</div>
                                                <div className="text-2xl font-bold text-slate-900 mt-1">{bingTraffic.impressions}</div>
                                            </div>
                                            {(typeof bingTraffic.clicks_change_percent === 'number' || typeof bingTraffic.impressions_change_percent === 'number') && (
                                                <div className="p-4 rounded-xl border border-slate-200 bg-slate-50/50">
                                                    <div className="text-xs font-semibold text-slate-500">{__('Change', 'quinrel')}</div>
                                                    <div className="text-sm font-semibold text-slate-900 mt-1">
                                                        {typeof bingTraffic.clicks_change_percent === 'number' && (
                                                            <div>
                                                                {sprintf(
                                                                    /* translators: %s: signed percentage change in clicks. */
                                                                    __('Clicks %s%%', 'quinrel'),
                                                                    `${bingTraffic.clicks_change_percent > 0 ? '+' : ''}${bingTraffic.clicks_change_percent}`
                                                                )}
                                                            </div>
                                                        )}
                                                        {typeof bingTraffic.impressions_change_percent === 'number' && (
                                                            <div>
                                                                {sprintf(
                                                                    /* translators: %s: signed percentage change in impressions. */
                                                                    __('Impressions %s%%', 'quinrel'),
                                                                    `${bingTraffic.impressions_change_percent > 0 ? '+' : ''}${bingTraffic.impressions_change_percent}`
                                                                )}
                                                            </div>
                                                        )}
                                                    </div>
                                                    <p className="text-xs text-slate-500 mt-1 mb-0">
                                                        {__('Compared with the previous 28 days.', 'quinrel')}
                                                    </p>
                                                </div>
                                            )}
                                        </div>
                                        {bingTraffic.message ? (
                                            <p className="text-xs text-slate-600 m-0">{bingTraffic.message}</p>
                                        ) : null}
                                    </div>
                                )}
                                {settings.bing_api_key === BING_KEY_MASK && bingTraffic && !bingTrafficError && bingTraffic.measured_days === 0 && bingTraffic.message && (
                                    <p className="text-xs text-slate-600 m-0" role="status">{bingTraffic.message}</p>
                                )}
                            </section>

                            {/* Webmaster Verification Meta Tags */}
                            <section className="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-4" aria-labelledby="quinrel-verify-hdr">
                                <div>
                                    <h3 id="quinrel-verify-hdr" className="text-base font-bold text-slate-900 m-0">
                                        {__('Search Engine Verification Codes', 'quinrel')}
                                    </h3>
                                    <p className="text-xs text-slate-500 mt-1 mb-0">
                                        {__('Enter the verification tag code provided by search engines (e.g. content="..." value). Quinrel will inject the proper meta tags into your HTML head automatically.', 'quinrel')}
                                    </p>
                                </div>

                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <TextControl
                                        label={__('Google Search Console Verification Tag', 'quinrel')}
                                        value={settings.google_verify || ''}
                                        onChange={(val) => setSettings({ ...settings, google_verify: val })}
                                        placeholder="e.g. AbC123xyz..."
                                    />
                                    <TextControl
                                        label={__('Bing Webmaster Verification', 'quinrel')}
                                        value={settings.bing_verify || ''}
                                        onChange={(val) => setSettings({ ...settings, bing_verify: val })}
                                        placeholder="e.g. 1234567890ABCDEF..."
                                    />
                                    <TextControl
                                        label={__('Yandex Webmaster Verification', 'quinrel')}
                                        value={settings.yandex_verify || ''}
                                        onChange={(val) => setSettings({ ...settings, yandex_verify: val })}
                                        placeholder="e.g. a1b2c3d4e5f6..."
                                    />
                                    <TextControl
                                        label={__('Pinterest Domain Verification', 'quinrel')}
                                        value={settings.pinterest_verify || ''}
                                        onChange={(val) => setSettings({ ...settings, pinterest_verify: val })}
                                        placeholder="e.g. 0987654321fedcba..."
                                    />
                                </div>
                            </section>
                        </div>
                    )}

                    {/* ══════════════════════════════════════════════════════════════
                        TAB 3: INDEXING & INSTANT PUSH
                       ══════════════════════════════════════════════════════════════ */}
                    {currentTab === 'indexing' && (
                        <div id="settings-panel-indexing" role="tabpanel" aria-labelledby="settings-tab-indexing" className="space-y-6">
                            {/* Google Indexing API Service Account */}
                            <section className="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-4" aria-labelledby="quinrel-google-idx-hdr">
                                <div>
                                    <h3 id="quinrel-google-idx-hdr" className="text-base font-bold text-slate-900 m-0">
                                        {__('Google Indexing API (Service Account)', 'quinrel')}
                                    </h3>
                                    <p className="text-xs text-slate-500 mt-1 mb-0">
                                        {__('Google limits Indexing API access to pages with JobPosting or BroadcastEvent structured data. Paste your Google Cloud Service Account JSON key below.', 'quinrel')}
                                    </p>
                                </div>

                                <div className="space-y-2">
                                    <label htmlFor="quinrel-g-key" className="block text-xs font-semibold text-slate-700">
                                        {__('Service Account JSON Key', 'quinrel')}
                                    </label>
                                    <TextareaControl
                                        id="quinrel-g-key"
                                        value={settings.google_indexing_key || ''}
                                        onChange={(val) => setSettings({ ...settings, google_indexing_key: val })}
                                        rows={5}
                                        placeholder='{"type": "service_account", "project_id": "...", "client_email": "...", ...}'
                                    />

                                    {/* Auto-extracted Service Account Email Banner */}
                                    {(() => {
                                        const emailMatch = settings.google_indexing_key
                                            ? settings.google_indexing_key.match(/"client_email"\s*:\s*"([^"]+)"/)
                                            : null;
                                        if (emailMatch && emailMatch[1]) {
                                            return (
                                                <div className="p-3 bg-blue-50 border border-blue-200 rounded-xl text-xs text-blue-900 flex items-center justify-between gap-3">
                                                    <div>
                                                        <strong>{__('Service Account Email:', 'quinrel')}</strong>{' '}
                                                        <code className="bg-white px-2 py-0.5 rounded border border-blue-200 font-mono select-all">
                                                            {emailMatch[1]}
                                                        </code>
                                                        <p className="mt-1 mb-0 text-blue-700 text-[11px]">
                                                            {__('Grant this service account "Owner" access in Google Search Console before testing.', 'quinrel')}
                                                        </p>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            navigator.clipboard.writeText(emailMatch[1]);
                                                            setSaveStatus({ type: 'success', message: __('Copied service account email!', 'quinrel') });
                                                        }}
                                                        className="px-3 py-1.5 bg-white border border-blue-300 text-blue-800 rounded-lg text-xs font-semibold hover:bg-blue-50 flex-shrink-0 cursor-pointer"
                                                    >
                                                        {__('Copy Email', 'quinrel')}
                                                    </button>
                                                </div>
                                            );
                                        }
                                        return null;
                                    })()}

                                    {settings.google_indexing_configured && (
                                        <p className="text-xs text-emerald-700 m-0">
                                            {__('✓ A valid Google Service Account credential is saved.', 'quinrel')}
                                        </p>
                                    )}

                                    <div className="pt-2">
                                        <ToggleControl
                                            label={__('Enable Google Indexing API automated push', 'quinrel')}
                                            help={__('Automatically dispatch push requests to Google when eligible JobPosting or Livestream pages are published.', 'quinrel')}
                                            checked={!!settings.enable_google_indexing_api}
                                            onChange={(val) => setSettings({ ...settings, enable_google_indexing_api: val })}
                                        />
                                    </div>
                                </div>
                            </section>

                            {/* Bing IndexNow */}
                            <section className="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-4" aria-labelledby="quinrel-indexnow-hdr">
                                <div>
                                    <h3 id="quinrel-indexnow-hdr" className="text-base font-bold text-slate-900 m-0">
                                        {__('IndexNow', 'quinrel')}
                                    </h3>
                                    <p className="text-xs text-slate-500 mt-1 mb-0">
                                        {__('When this is on, publishing a public post queues its URL. A background job sends up to 10 URLs at a time to https://api.indexnow.org/indexnow. Deleting a post does not send a notice.', 'quinrel')}
                                    </p>
                                </div>

                                <div className="space-y-3">
                                    <div className="flex flex-col sm:flex-row items-stretch sm:items-end gap-3">
                                        <div className="flex-1">
                                            <TextControl
                                                label={__('IndexNow API Key (8-128 hex/alphanumeric characters)', 'quinrel')}
                                                value={settings.indexnow_key || ''}
                                                onChange={(val) => setSettings({ ...settings, indexnow_key: val })}
                                                placeholder="e.g. 1a2b3c4d5e6f7g8h9i0j..."
                                            />
                                        </div>
                                        <button
                                            type="button"
                                            onClick={generateRandomIndexNowKey}
                                            className="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg border border-slate-300 transition-colors cursor-pointer self-start sm:self-auto mb-1"
                                        >
                                            {__('Generate Secure Key', 'quinrel')}
                                        </button>
                                    </div>

                                    {settings.indexnow_key_url ? (
                                        <p className="text-xs text-slate-600 m-0">
                                            <a href={settings.indexnow_key_url} className="text-indigo-700 underline break-all">
                                                {settings.indexnow_key_url}
                                            </a>
                                            {' '}
                                            {__('IndexNow fetches this file to verify the key.', 'quinrel')}
                                        </p>
                                    ) : null}

                                    <ToggleControl
                                        label={__('Notify IndexNow when a public post is published', 'quinrel')}
                                        help={__('The save finishes immediately. The notice is sent in the background.', 'quinrel')}
                                        checked={!!settings.auto_index}
                                        onChange={(val) => setSettings({ ...settings, auto_index: val })}
                                    />
                                </div>
                            </section>

                            <section className="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-4" aria-labelledby="quinrel-indexnow-log-hdr">
                                <div>
                                    <h3 id="quinrel-indexnow-log-hdr" className="text-base font-bold text-slate-900 m-0">
                                        {__('Recent IndexNow submissions', 'quinrel')}
                                    </h3>
                                    <p className="text-xs text-slate-500 mt-1 mb-0">
                                        {__('Accepted means the IndexNow hub took the batch. It does not mean the page was crawled or indexed.', 'quinrel')}
                                    </p>
                                </div>
                                {indexNowLogNote && (
                                    <div className="p-3 rounded-xl text-xs bg-red-50 text-red-800 border border-red-200" role="alert">
                                        {indexNowLogNote.text}
                                    </div>
                                )}
                                {indexNowLog.length === 0 && !indexNowLogNote ? (
                                    <p className="text-xs text-slate-600 m-0">
                                        {__('No IndexNow submissions yet. Publish a public post, or queue a test URL below.', 'quinrel')}
                                    </p>
                                ) : (
                                    <ul className="m-0 p-0 list-none space-y-2">
                                        {indexNowLog.map((row, index) => {
                                            const code = Number(row.status_code);
                                            const accepted = code === 200 || code === 202;
                                            return (
                                                <li key={`${row.submitted_at || 'row'}-${index}`} className="text-xs text-slate-700 break-all">
                                                    <span title={row.url || ''}>{row.url}</span>
                                                    {' · '}
                                                    <span>{row.submitted_at}</span>
                                                    {' · '}
                                                    <span>
                                                        {accepted
                                                            ? __('Accepted', 'quinrel')
                                                            : sprintf(
                                                                /* translators: %d: HTTP status code returned for an IndexNow submission. */
                                                                __('Not accepted (%d).', 'quinrel'),
                                                                code
                                                            )}
                                                    </span>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                )}
                            </section>

                            <section className="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-4" aria-labelledby="quinrel-manual-idx-hdr">
                                <div>
                                    <h3 id="quinrel-manual-idx-hdr" className="text-base font-bold text-slate-900 m-0">
                                        {__('Send a test URL', 'quinrel')}
                                    </h3>
                                    <p className="text-xs text-slate-500 mt-1 mb-0">
                                        {__('Queues one URL from this site. IndexNow is notified in the background after you turn notifications on and save a key.', 'quinrel')}
                                    </p>
                                </div>

                                {manualUrlMsg && (
                                    <div
                                        className={`p-3 rounded-xl text-xs ${
                                            manualUrlMsg.type === 'success'
                                                ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
                                                : 'bg-red-50 text-red-800 border border-red-200'
                                        }`}
                                        role={manualUrlMsg.type === 'success' ? 'status' : 'alert'}
                                    >
                                        {manualUrlMsg.text}
                                    </div>
                                )}

                                <form onSubmit={handleTestManualIndex} className="space-y-3">
                                    <label htmlFor="quinrel-indexnow-test-url" className="block text-xs font-semibold text-slate-700">
                                        {__('URL on this site', 'quinrel')}
                                    </label>
                                    <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                                        <input
                                            id="quinrel-indexnow-test-url"
                                            type="url"
                                            required
                                            value={manualUrl}
                                            onChange={(e) => setManualUrl(e.target.value)}
                                            className="flex-1 text-xs px-3 py-2 bg-white border border-slate-300 rounded-lg shadow-2xs focus:border-indigo-500 focus:outline-none"
                                        />
                                        <button
                                            type="submit"
                                            disabled={manualUrlBusy || !manualUrl.trim()}
                                            className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg shadow-2xs transition-colors disabled:opacity-50 cursor-pointer"
                                        >
                                            {manualUrlBusy ? __('Queueing…', 'quinrel') : __('Queue URL', 'quinrel')}
                                        </button>
                                    </div>
                                </form>
                            </section>
                        </div>
                    )}

                    {/* ══════════════════════════════════════════════════════════════
                        TAB 4: PERFORMANCE & SPEED
                       ══════════════════════════════════════════════════════════════ */}
                    {currentTab === 'performance' && (
                        <div id="settings-panel-performance" role="tabpanel" aria-labelledby="settings-tab-performance" className="space-y-6">
                            <section className="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-5" aria-labelledby="quinrel-perf-hdr">
                                <div>
                                    <h3 id="quinrel-perf-hdr" className="text-base font-bold text-slate-900 m-0">
                                        {__('Core Web Vitals & Asset Delivery', 'quinrel')}
                                    </h3>
                                    <p className="text-xs text-slate-500 mt-1 mb-0">
                                        {__('Both options stay off until you turn them on. Nothing here runs on a normal page view while they are off.', 'quinrel')}
                                    </p>
                                </div>

                                <div className="space-y-4 divide-y divide-slate-100">
                                    <div className="pt-2 space-y-3">
                                        <ToggleControl
                                            label={__('Defer listed front-end scripts', 'quinrel')}
                                            help={__('Off by default. Defers only the script handles you list. An empty list defers nothing. jquery and other WordPress core handles stay as they are.', 'quinrel')}
                                            checked={!!settings.defer_non_critical_scripts}
                                            onChange={(val) => setSettings({ ...settings, defer_non_critical_scripts: val })}
                                        />
                                        <TextControl
                                            label={__('Script handles to defer', 'quinrel')}
                                            help={__('Comma-separated handles, up to 40. Example: contact-form-7, woocommerce', 'quinrel')}
                                            value={settings.defer_script_handles || ''}
                                            onChange={(val) => setSettings({ ...settings, defer_script_handles: val })}
                                        />
                                    </div>

                                    <div className="pt-4 space-y-3">
                                        <TextControl
                                            label={__('PageSpeed Insights API key', 'quinrel')}
                                            help={__('Used only when you run a PageSpeed check. The URL you check is sent to Google. Leave this blank to try without a key. A QUINREL_PSI_KEY constant in wp-config.php overrides this field.', 'quinrel')}
                                            type="password"
                                            value={settings.pagespeed_api_key || ''}
                                            onChange={(val) => setSettings({ ...settings, pagespeed_api_key: val })}
                                            autoComplete="off"
                                        />
                                    </div>

                                    <div className="pt-4">
                                        <ToggleControl
                                            label={__('Swap Google Fonts and preconnect when a page already loads them', 'quinrel')}
                                            help={__('Off by default. When a page already loads fonts.googleapis.com, Quinrel adds display=swap and a preconnect for that request.', 'quinrel')}
                                            checked={!!settings.font_display_swap}
                                            onChange={(val) => setSettings({ ...settings, font_display_swap: val })}
                                        />
                                    </div>
                                </div>

                                <div className="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                                    <strong className="text-xs font-bold text-slate-800">
                                        {__('Engineering Best Practice Notice', 'quinrel')}
                                    </strong>
                                    <p className="text-xs text-slate-600 m-0">
                                        {__('Quinrel avoids aggressive JavaScript concatenation or file alteration that can break themes or third-party plugins. Optimization is handled using native WordPress filter hooks.', 'quinrel')}
                                    </p>
                                </div>
                            </section>
                        </div>
                    )}

                    {/* ══════════════════════════════════════════════════════════════
                        TAB 5: AI ENGINE VAULT
                       ══════════════════════════════════════════════════════════════ */}
                    {currentTab === 'ai' && (
                        <div id="settings-panel-ai" role="tabpanel" aria-labelledby="settings-tab-ai" className="space-y-6">
                            <section className="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-5" aria-labelledby="quinrel-ai-hdr">
                                <div>
                                    <h3 id="quinrel-ai-hdr" className="text-base font-bold text-slate-900 m-0">
                                        {__('AI Provider Integration Vault', 'quinrel')}
                                    </h3>
                                    <p className="text-xs text-slate-500 mt-1 mb-0">
                                        {__('Configure OpenAI, Anthropic, or local Ollama LLMs to power AI metadata generation, content readability analysis, and Vision alt-text creation.', 'quinrel')}
                                    </p>
                                </div>

                                {aiTestResult && (
                                    <div role="status" aria-live="polite">
                                        <Notice
                                            status={aiTestResult.type}
                                            isDismissible={true}
                                            onRemove={() => setAiTestResult(null)}
                                            className="rounded-xl"
                                        >
                                            {aiTestResult.message}
                                        </Notice>
                                    </div>
                                )}

                                <div>
                                    <label htmlFor="quinrel-ai-provider" className="block text-xs font-semibold text-slate-700 mb-1.5">
                                        {__('Active AI Engine Provider', 'quinrel')}
                                    </label>
                                    <select
                                        id="quinrel-ai-provider"
                                        value={settings.ai_provider || 'openai'}
                                        onChange={(e) => setSettings({ ...settings, ai_provider: e.target.value })}
                                        className="w-full text-xs px-3 py-2 bg-white border border-slate-300 rounded-lg shadow-2xs focus:border-indigo-500 focus:outline-none"
                                    >
                                        <option value="openai">{__('OpenAI (GPT-4o-mini / Vision)', 'quinrel')}</option>
                                        <option value="anthropic">{__('Anthropic (Claude 3.5 Sonnet / Vision)', 'quinrel')}</option>
                                        <option value="ollama">{__('Ollama (Local / Private LLM instance)', 'quinrel')}</option>
                                    </select>
                                </div>

                                {settings.ai_provider === 'openai' && (
                                    <div>
                                        <TextControl
                                            label={__('OpenAI API Key', 'quinrel')}
                                            value={settings.openai_key || ''}
                                            type="password"
                                            onChange={(val) => {
                                                setSettings({ ...settings, openai_key: val });
                                                setFormErrors(e => ({ ...e, openai_key: '' }));
                                            }}
                                            placeholder="sk-proj-..."
                                        />
                                        {formErrors.openai_key && (
                                            <p role="alert" className="text-xs text-red-600 mt-1">{formErrors.openai_key}</p>
                                        )}
                                    </div>
                                )}

                                {settings.ai_provider === 'anthropic' && (
                                    <div>
                                        <TextControl
                                            label={__('Anthropic API Key', 'quinrel')}
                                            value={settings.anthropic_key || ''}
                                            type="password"
                                            onChange={(val) => {
                                                setSettings({ ...settings, anthropic_key: val });
                                                setFormErrors(e => ({ ...e, anthropic_key: '' }));
                                            }}
                                            placeholder="sk-ant-..."
                                        />
                                        {formErrors.anthropic_key && (
                                            <p role="alert" className="text-xs text-red-600 mt-1">{formErrors.anthropic_key}</p>
                                        )}
                                    </div>
                                )}

                                {settings.ai_provider === 'ollama' && (
                                    <div className="space-y-3">
                                        <TextControl
                                            label={__('Ollama Host URL', 'quinrel')}
                                            value={settings.ollama_url || ''}
                                            onChange={(val) => {
                                                setSettings({
                                                    ...settings,
                                                    ollama_url: val,
                                                    ollama_remote_confirmed: ollamaHostIsLoopback && ollamaHostIsLoopback(val) ? false : settings.ollama_remote_confirmed,
                                                });
                                                setFormErrors(e => ({ ...e, ollama_url: '' }));
                                            }}
                                            placeholder="http://localhost:11434"
                                        />
                                        <p className="text-xs text-slate-500 m-0">
                                            {__('Local loopback http://localhost:11434 never transmits content outside your server.', 'quinrel')}
                                        </p>
                                        {settings.ollama_url && ollamaHostIsLoopback && !ollamaHostIsLoopback(settings.ollama_url) && (
                                            <label className="flex items-center gap-2 text-xs text-amber-900 bg-amber-50 p-3 rounded-lg border border-amber-200">
                                                <input
                                                    type="checkbox"
                                                    checked={!!settings.ollama_remote_confirmed}
                                                    onChange={(e) => setSettings({ ...settings, ollama_remote_confirmed: e.target.checked })}
                                                    className="w-4 h-4 rounded border-amber-300 text-indigo-600"
                                                />
                                                <span>{__('Acknowledge: Send content prompts to this remote Ollama host.', 'quinrel')}</span>
                                            </label>
                                        )}
                                    </div>
                                )}

                                <div>
                                    <button
                                        type="button"
                                        disabled={isTestingAi}
                                        onClick={testAiConnection}
                                        className="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold rounded-lg border border-slate-300 transition-colors disabled:opacity-50 cursor-pointer"
                                    >
                                        {isTestingAi ? __('Testing Connection...', 'quinrel') : __('Test AI Connection', 'quinrel')}
                                    </button>
                                </div>
                            </section>
                        </div>
                    )}

                    {/* ══════════════════════════════════════════════════════════════
                        TAB 6: ROLES & CAPABILITIES
                       ══════════════════════════════════════════════════════════════ */}
                    {currentTab === 'roles' && (
                        <div id="settings-panel-roles" role="tabpanel" aria-labelledby="settings-tab-roles" className="space-y-6">
                            <section className="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-5" aria-labelledby="quinrel-roles-hdr">
                                <div>
                                    <h3 id="quinrel-roles-hdr" className="text-base font-bold text-slate-900 m-0">
                                        {__('Team Roles & Access Control', 'quinrel')}
                                    </h3>
                                    <p className="text-xs text-slate-500 mt-1 mb-0">
                                        {__('Assign granular Quinrel permissions across standard and custom WordPress user roles. Administrators always retain unrestricted full access.', 'quinrel')}
                                    </p>
                                </div>

                                {rolesLoading && (
                                    <div className="flex items-center gap-3 p-6 bg-slate-50 rounded-xl">
                                        <Spinner />
                                        <span className="text-xs text-slate-500">{__('Loading user roles matrix...', 'quinrel')}</span>
                                    </div>
                                )}

                                {rolesData && (
                                    <div className="border border-slate-200 rounded-xl overflow-hidden shadow-2xs">
                                        {/* Capability Legend */}
                                        <div className="px-5 py-3 bg-slate-50 border-b border-slate-200 grid grid-cols-5 gap-2 text-xs font-semibold text-slate-700">
                                            <span className="col-span-1">{__('Role', 'quinrel')}</span>
                                            {rolesData.seo_capabilities.map((cap) => (
                                                <span key={cap} className="text-center font-mono text-[11px] bg-slate-200/70 text-slate-700 px-1.5 py-0.5 rounded">
                                                    {cap.replace(/_/g, ' ')}
                                                </span>
                                            ))}
                                        </div>

                                        {/* Role Rows */}
                                        <div className="divide-y divide-slate-100">
                                            {Object.entries(rolesData.roles).map(([slug, role]) => {
                                                const isAdmin = slug === 'administrator';
                                                return (
                                                    <div
                                                        key={slug}
                                                        className={`px-5 py-3.5 grid grid-cols-5 gap-2 items-center ${
                                                            isAdmin ? 'bg-indigo-50/50' : 'hover:bg-slate-50/60'
                                                        }`}
                                                    >
                                                        <div className="col-span-1 flex items-center gap-1.5">
                                                            <span className="text-xs font-bold text-slate-800">{role.label}</span>
                                                            {isAdmin && (
                                                                <span className="text-[10px] font-bold text-indigo-700 bg-indigo-100 px-1.5 py-0.2 rounded-full">
                                                                    {__('Full Access', 'quinrel')}
                                                                </span>
                                                            )}
                                                        </div>
                                                        {rolesData.seo_capabilities.map((cap) => {
                                                            const granted = isAdmin ? true : !!(roleEdits[slug]?.[cap]);
                                                            return (
                                                                <div key={cap} className="flex justify-center">
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={granted}
                                                                        disabled={isAdmin}
                                                                        onChange={(e) => toggleCap(slug, cap, e.target.checked)}
                                                                        className="w-4 h-4 rounded border-slate-300 text-indigo-600 cursor-pointer disabled:cursor-not-allowed disabled:opacity-50"
                                                                    />
                                                                </div>
                                                            );
                                                        })}
                                                    </div>
                                                );
                                            })}
                                        </div>

                                        {/* Capability Descriptions Footer */}
                                        <div className="px-5 py-3.5 bg-slate-50 border-t border-slate-200 grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px] text-slate-600">
                                            <div>🛡 <strong className="text-slate-800">manage_seo:</strong> {__('Full management of settings, integrations, and schemas.', 'quinrel')}</div>
                                            <div>📊 <strong className="text-slate-800">view_seo_reports:</strong> {__('Read-only access to audit reports, GSC, and health checks.', 'quinrel')}</div>
                                            <div>↩️ <strong className="text-slate-800">manage_redirects:</strong> {__('Create, edit, and delete 301/302 redirects and 404 monitors.', 'quinrel')}</div>
                                            <div>✏️ <strong className="text-slate-800">edit_seo_meta:</strong> {__('Edit per-post SEO titles, descriptions, and OpenGraph metadata.', 'quinrel')}</div>
                                        </div>
                                    </div>
                                )}

                                {rolesData && (
                                    <div className="flex items-center gap-3 pt-2">
                                        {rolesMsg && (
                                            <span className={`text-xs px-3 py-1.5 rounded-lg ${rolesMsg.type === 'success' ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800'}`}>
                                                {rolesMsg.text}
                                            </span>
                                        )}
                                        <button
                                            type="button"
                                            onClick={saveRoles}
                                            disabled={rolesSaving}
                                            className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg shadow-2xs transition-colors disabled:opacity-50 cursor-pointer"
                                        >
                                            {rolesSaving ? __('Saving Capabilities...', 'quinrel') : __('Save Capabilities', 'quinrel')}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={loadRoles}
                                            className="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-medium rounded-lg transition-colors cursor-pointer"
                                        >
                                            {__('Reset', 'quinrel')}
                                        </button>
                                    </div>
                                )}
                            </section>
                        </div>
                    )}

                    {/* ══════════════════════════════════════════════════════════════
                        TAB 7: ADVANCED & DATA LIFECYCLE
                       ══════════════════════════════════════════════════════════════ */}
                    {currentTab === 'advanced' && (
                        <div id="settings-panel-advanced" role="tabpanel" aria-labelledby="settings-tab-advanced" className="space-y-6">
                            {/* Uninstall Lifecycle */}
                            <section className="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-4" aria-labelledby="quinrel-uninstall-hdr">
                                <div>
                                    <h3 id="quinrel-uninstall-hdr" className="text-base font-bold text-slate-900 m-0">
                                        {__('Uninstall Data Lifecycle', 'quinrel')}
                                    </h3>
                                    <p className="text-xs text-slate-500 mt-1 mb-0">
                                        {__('Configure how Quinrel handles stored metadata, redirect tables, audit logs, and settings when uninstalled.', 'quinrel')}
                                    </p>
                                </div>

                                <ToggleControl
                                    label={__('Permanently delete all Quinrel data upon plugin deletion', 'quinrel')}
                                    help={__('Disabled by default. When enabled, all options, redirects, audit history, and database tables are permanently dropped if the plugin is deleted via the WordPress Plugins screen.', 'quinrel')}
                                    checked={!!settings.delete_data_on_uninstall}
                                    onChange={(val) => setSettings({ ...settings, delete_data_on_uninstall: val })}
                                />
                            </section>

                            {/* Import / Export Settings */}
                            <section className="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-5" aria-labelledby="quinrel-backup-hdr">
                                <div>
                                    <h3 id="quinrel-backup-hdr" className="text-base font-bold text-slate-900 m-0">
                                        {__('Import / Export Configuration', 'quinrel')}
                                    </h3>
                                    <p className="text-xs text-slate-500 mt-1 mb-0">
                                        {__('Back up your configuration or migrate SEO settings between staging and production. Sensitive private keys are never included in exports.', 'quinrel')}
                                    </p>
                                </div>

                                <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    {/* Export */}
                                    <div className="p-4 rounded-xl border border-slate-200 bg-slate-50/60 space-y-3">
                                        <h4 className="text-xs font-bold uppercase tracking-wider text-slate-700 m-0">
                                            {__('Export Settings', 'quinrel')}
                                        </h4>
                                        <p className="text-xs text-slate-500 m-0">
                                            {__('Download your complete configuration as a JSON file.', 'quinrel')}
                                        </p>
                                        {exportMsg && (
                                            <div className={`text-xs px-2.5 py-1.5 rounded ${exportMsg.type === 'success' ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800'}`}>
                                                {exportMsg.text}
                                            </div>
                                        )}
                                        <button
                                            type="button"
                                            onClick={downloadExport}
                                            className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg shadow-2xs transition-colors cursor-pointer"
                                        >
                                            {__('⬇ Download Settings JSON', 'quinrel')}
                                        </button>
                                    </div>

                                    {/* Import */}
                                    <div className="p-4 rounded-xl border border-slate-200 bg-slate-50/60 space-y-3">
                                        <h4 className="text-xs font-bold uppercase tracking-wider text-slate-700 m-0">
                                            {__('Import Settings', 'quinrel')}
                                        </h4>
                                        <p className="text-xs text-slate-500 m-0">
                                            {__('Restore settings from a valid Quinrel JSON backup file.', 'quinrel')}
                                        </p>
                                        {importMsg && (
                                            <div className={`text-xs px-2.5 py-1.5 rounded ${importMsg.type === 'success' ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800'}`}>
                                                {importMsg.text}
                                            </div>
                                        )}
                                        <label className={`inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-lg cursor-pointer border border-dashed border-slate-300 hover:border-indigo-500 bg-white text-slate-700 transition-colors ${importBusy ? 'opacity-50 pointer-events-none' : ''}`}>
                                            <input
                                                type="file"
                                                accept=".json,application/json"
                                                className="hidden"
                                                onChange={(e) => handleImportFile(e.target.files?.[0])}
                                            />
                                            <span>{importBusy ? __('Importing...', 'quinrel') : __('⬆ Upload JSON Backup', 'quinrel')}</span>
                                        </label>
                                    </div>
                                </div>
                            </section>

                            {/* System Diagnostic Information */}
                            <section className="bg-white border border-slate-200 rounded-2xl p-6 shadow-xs space-y-3" aria-labelledby="quinrel-diag-hdr">
                                <h3 id="quinrel-diag-hdr" className="text-base font-bold text-slate-900 m-0">
                                    {__('System Diagnostics & Environment', 'quinrel')}
                                </h3>
                                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-1">
                                    <div className="p-3 bg-slate-50 rounded-xl border border-slate-200">
                                        <span className="text-[11px] text-slate-500 font-semibold block">{__('Plugin Version', 'quinrel')}</span>
                                        <span className="text-sm font-bold text-slate-900 font-mono">v{quinrelAdminConfig.version || '1.1.0'}</span>
                                    </div>
                                    <div className="p-3 bg-slate-50 rounded-xl border border-slate-200">
                                        <span className="text-[11px] text-slate-500 font-semibold block">{__('Multisite Network', 'quinrel')}</span>
                                        <span className="text-sm font-bold text-slate-900">{quinrelAdminConfig.isMultisite === '1' ? __('Active', 'quinrel') : __('Single Site', 'quinrel')}</span>
                                    </div>
                                    <div className="p-3 bg-slate-50 rounded-xl border border-slate-200">
                                        <span className="text-[11px] text-slate-500 font-semibold block">{__('Setup Wizard', 'quinrel')}</span>
                                        <span className="text-sm font-bold text-emerald-700">{quinrelAdminConfig.setupComplete === '1' ? __('Complete', 'quinrel') : __('Recommended', 'quinrel')}</span>
                                    </div>
                                    <div className="p-3 bg-slate-50 rounded-xl border border-slate-200">
                                        <span className="text-[11px] text-slate-500 font-semibold block">{__('REST API Endpoint', 'quinrel')}</span>
                                        <span className="text-xs font-bold text-slate-700 truncate block">quinrel/v1</span>
                                    </div>
                                </div>
                            </section>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
};

export default GlobalSettingsHub;

