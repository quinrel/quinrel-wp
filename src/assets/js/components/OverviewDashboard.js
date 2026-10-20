import React from 'react';
import { __ } from '@wordpress/i18n';
import { Spinner } from '@wordpress/components';
import GscTrendChart from './GscTrendChart';
import {
    OverviewIcon,
    VisibilityIcon,
    IntelligenceIcon,
    HealthIcon,
    TitlesIcon,
    SitemapIcon,
    ImageIcon,
    AiBotIcon,
    AuditIcon,
    TechIcon,
    LinkIcon,
    BrokenLinkIcon,
    ExternalLinkIcon,
    CheckCircleIcon,
    AlertCircleIcon,
    ArrowRightIcon,
    WandIcon,
} from './QuinrelIcons';

export const OverviewDashboard = ({
    summary,
    nextMessage,
    setupFinished,
    setShowSetup,
    standalone,
    standaloneLoading,
    standaloneError,
    aiBotActivity,
    setActiveTab,
    setTechSubTab,
    cloudConnected,
    showQuinrelNote,
    setShowQuinrelNote,
    canConnectQuinrel,
    connectionLoading,
    connectionError,
    connectionMessage,
    connection,
    siteToken,
    setSiteToken,
    authorizationCode,
    setAuthorizationCode,
    connectionBusy,
    postConnection,
    setFocusIndexing,
    gscOpen,
    setGscOpen,
    gscData,
    setGscData,
    gscLoading,
    gscError,
    gscSubTab,
    setGscSubTab,
    cannData,
    setCannData,
    cannLoading,
    fmtNum,
    posColor,
}) => {
    return (
        <div className="quinrel-overview space-y-6">
            {/* ══════════ EXECUTIVE HERO COMMAND CENTER ══════════ */}
            <div className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 text-white p-6 sm:p-8 shadow-md border border-slate-800">
                {/* Decorative background glow accents */}
                <div
                    className="absolute -right-16 -top-16 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"
                    aria-hidden="true"
                />
                <div
                    className="absolute right-1/3 -bottom-16 w-64 h-64 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"
                    aria-hidden="true"
                />

                <div className="relative z-10 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">
                    <div className="space-y-2 max-w-2xl">
                        <div className="flex items-center gap-2.5">
                            <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" />
                                {__('Engine Active', 'quinrel')}
                            </span>
                            <span className="text-xs text-slate-400">
                                {setupFinished
                                    ? __('Full site optimization verified', 'quinrel')
                                    : __('Initial setup recommended', 'quinrel')}
                            </span>
                        </div>

                        <h2 className="text-xl sm:text-2xl font-bold tracking-tight text-white m-0">
                            {__('Visibility & Discovery Command Center', 'quinrel')}
                        </h2>

                        <p className="text-sm text-slate-300 leading-relaxed m-0">
                            {summary}
                        </p>
                    </div>

                    <div className="flex flex-wrap sm:flex-nowrap items-center gap-3 w-full lg:w-auto flex-shrink-0">
                        <button
                            type="button"
                            onClick={() => setActiveTab('site_audit')}
                            className="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/15 text-white text-xs font-semibold backdrop-blur-xs border border-white/10 transition-colors cursor-pointer"
                        >
                            <AuditIcon className="w-4 h-4 text-indigo-300" />
                            <span>{__('Site Audit', 'quinrel')}</span>
                        </button>

                        <button
                            type="button"
                            onClick={() => setShowSetup(true)}
                            className="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-indigo-500 to-brand-500 hover:from-indigo-600 hover:to-brand-600 text-white text-xs font-bold shadow-md transition-all cursor-pointer"
                        >
                            <WandIcon className="w-4 h-4" />
                            <span>{setupFinished ? __('Re-run Wizard', 'quinrel') : __('Launch Setup Wizard', 'quinrel')}</span>
                        </button>
                    </div>
                </div>
            </div>

            {/* Loading & Error States for Standalone Local Engine */}
            {standaloneLoading && (
                <div className="flex items-center gap-3 p-4 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-600">
                    <Spinner />
                    <span>{__('Analyzing local technical SEO status…', 'quinrel')}</span>
                </div>
            )}
            {standaloneError && (
                <div className="p-4 bg-amber-50 border border-amber-200 rounded-xl text-sm text-amber-900" role="alert">
                    ⚠️ {standaloneError}
                </div>
            )}

            {/* ══════════ LIVE AI SEARCH ENGINE RADAR (MARKET DIFFERENTIATOR) ══════════ */}
            <div className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-50/70 via-purple-50/40 to-white border border-indigo-100 p-5 sm:p-6 shadow-xs">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                    <div className="flex items-center gap-2.5">
                        <span className="flex h-3 w-3 relative">
                            <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75" />
                            <span className="relative inline-flex rounded-full h-3 w-3 bg-emerald-500" />
                        </span>
                        <div>
                            <h3 className="text-sm font-bold text-slate-900 m-0">
                                {__('AI Search Engine Crawlers (Live Activity Tracker)', 'quinrel')}
                            </h3>
                            <p className="text-xs text-slate-500 m-0 mt-0.5">
                                {__('Real-time tracking of AI models and LLM search agents indexing your site via llms.txt and semantic schema.', 'quinrel')}
                            </p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2 self-start sm:self-auto">
                        <span className="text-xs font-semibold px-2.5 py-1 rounded-full bg-indigo-100/80 text-indigo-800">
                            {aiBotActivity && aiBotActivity.total > 0
                                ? `${aiBotActivity.total} ${__('total AI crawler visits', 'quinrel')}`
                                : __('Awaiting first crawler hit', 'quinrel')}
                        </span>
                        <button
                            type="button"
                            onClick={() => setActiveTab('llms')}
                            className="text-xs font-semibold text-indigo-600 hover:text-indigo-800 inline-flex items-center gap-1 ml-1 cursor-pointer"
                        >
                            <span>{__('Configure llms.txt', 'quinrel')}</span>
                            <ArrowRightIcon className="w-3.5 h-3.5" />
                        </button>
                    </div>
                </div>

                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div className="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-2xs hover:border-indigo-200 transition-colors">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500">ChatGPT / GPTBot</span>
                            <span className="w-2 h-2 rounded-full bg-emerald-400" />
                        </div>
                        <div className="text-xl font-bold text-slate-900 mt-1">
                            {aiBotActivity ? aiBotActivity.gptbot : 0}
                        </div>
                        <span className="text-[10px] text-slate-400">{__('OpenAI Web Indexer', 'quinrel')}</span>
                    </div>

                    <div className="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-2xs hover:border-indigo-200 transition-colors">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500">Claude / Anthropic</span>
                            <span className="w-2 h-2 rounded-full bg-purple-400" />
                        </div>
                        <div className="text-xl font-bold text-slate-900 mt-1">
                            {aiBotActivity ? aiBotActivity.claudebot : 0}
                        </div>
                        <span className="text-[10px] text-slate-400">{__('ClaudeBot Search', 'quinrel')}</span>
                    </div>

                    <div className="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-2xs hover:border-indigo-200 transition-colors">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500">PerplexityBot</span>
                            <span className="w-2 h-2 rounded-full bg-sky-400" />
                        </div>
                        <div className="text-xl font-bold text-slate-900 mt-1">
                            {aiBotActivity ? aiBotActivity.perplexitybot : 0}
                        </div>
                        <span className="text-[10px] text-slate-400">{__('Perplexity Answer Engine', 'quinrel')}</span>
                    </div>

                    <div className="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-2xs hover:border-indigo-200 transition-colors">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-semibold text-slate-500">Google & Apple AI</span>
                            <span className="w-2 h-2 rounded-full bg-amber-400" />
                        </div>
                        <div className="text-xl font-bold text-slate-900 mt-1">
                            {aiBotActivity ? (aiBotActivity.google_extended + aiBotActivity.applebot) : 0}
                        </div>
                        <span className="text-[10px] text-slate-400">{__('Gemini & Apple Intelligence', 'quinrel')}</span>
                    </div>
                </div>

                {aiBotActivity && aiBotActivity.last_seen && (
                    <div className="text-xs text-slate-500 mt-3 pt-3 border-t border-indigo-100/80 flex items-center gap-2">
                        <span className="font-medium text-slate-600">{__('Last AI crawler hit:', 'quinrel')}</span>
                        <code className="px-1.5 py-0.5 rounded bg-white text-indigo-700 font-mono text-[11px] border border-slate-200">
                            {aiBotActivity.last_seen}
                        </code>
                        <span className="text-slate-400">({String(aiBotActivity.last_bot).toUpperCase()})</span>
                    </div>
                )}
            </div>

            {/* ══════════ 6 CORE VISIBILITY & TECHNICAL KPI ACTION CARDS ══════════ */}
            {standalone && (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5">
                    {/* 1. Indexability Card */}
                    <div className="bg-white rounded-xl p-4.5 border border-slate-200/90 shadow-2xs hover:shadow-xs transition-shadow flex flex-col justify-between">
                        <div>
                            <div className="flex items-center justify-between mb-2">
                                <span className="text-xs font-bold uppercase tracking-wider text-slate-400">
                                    {__('Indexability', 'quinrel')}
                                </span>
                                {standalone.indexable !== false ? (
                                    <CheckCircleIcon className="w-5 h-5 text-emerald-500" />
                                ) : (
                                    <AlertCircleIcon className="w-5 h-5 text-amber-500" />
                                )}
                            </div>
                            <div className="text-base font-semibold text-slate-900">
                                {standalone.indexability}
                            </div>
                            <p className="text-xs text-slate-500 mt-1 mb-0">
                                {standalone.indexable !== false
                                    ? __('Search engines are permitted to index and rank your pages.', 'quinrel')
                                    : __('Search engines are discouraged in Reading settings.', 'quinrel')}
                            </p>
                        </div>
                        {standalone.indexable === false && (
                            <div className="mt-3.5 pt-3 border-t border-slate-100">
                                <a
                                    href="options-reading.php"
                                    className="inline-flex items-center gap-1.5 text-xs font-semibold text-amber-700 hover:text-amber-800"
                                >
                                    <span>{__('Fix in Reading settings', 'quinrel')}</span>
                                    <ExternalLinkIcon className="w-3.5 h-3.5" />
                                </a>
                            </div>
                        )}
                    </div>

                    {/* 2. Sitemaps Card */}
                    <div className="bg-white rounded-xl p-4.5 border border-slate-200/90 shadow-2xs hover:shadow-xs transition-shadow flex flex-col justify-between">
                        <div>
                            <div className="flex items-center justify-between mb-2">
                                <span className="text-xs font-bold uppercase tracking-wider text-slate-400">
                                    {__('XML Sitemap', 'quinrel')}
                                </span>
                                <SitemapIcon className="w-5 h-5 text-indigo-500" />
                            </div>
                            <div className="text-base font-semibold text-slate-900">
                                {__('Active & Auto-Synchronized', 'quinrel')}
                            </div>
                            <p className="text-xs text-slate-500 mt-1 mb-0 break-all line-clamp-1">
                                {standalone.sitemap_url}
                            </p>
                        </div>
                        <div className="mt-3.5 pt-3 border-t border-slate-100 flex items-center justify-between">
                            <a
                                href={standalone.sitemap_url}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-800"
                            >
                                <span>{__('View sitemap.xml', 'quinrel')}</span>
                                <ExternalLinkIcon className="w-3.5 h-3.5" />
                            </a>
                            <button
                                type="button"
                                onClick={() => setActiveTab('sitemaps')}
                                className="text-xs font-semibold text-slate-600 hover:text-slate-900 bg-transparent border-0 p-0 cursor-pointer"
                            >
                                {__('Configure', 'quinrel')}
                            </button>
                        </div>
                    </div>

                    {/* 3. Titles & Meta Card */}
                    <div className="bg-white rounded-xl p-4.5 border border-slate-200/90 shadow-2xs hover:shadow-xs transition-shadow flex flex-col justify-between">
                        <div>
                            <div className="flex items-center justify-between mb-2">
                                <span className="text-xs font-bold uppercase tracking-wider text-slate-400">
                                    {__('Titles & Meta', 'quinrel')}
                                </span>
                                <TitlesIcon className="w-5 h-5 text-sky-500" />
                            </div>
                            <div className="text-base font-semibold text-slate-900">
                                {standalone.missing_titles_label}
                            </div>
                            <p className="text-xs text-slate-500 mt-1 mb-0">
                                {__('Semantic OpenGraph and Twitter cards injected automatically.', 'quinrel')}
                            </p>
                        </div>
                        <div className="mt-3.5 pt-3 border-t border-slate-100">
                            <button
                                type="button"
                                onClick={() => setActiveTab('bulk_meta')}
                                className="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-800 bg-transparent border-0 p-0 cursor-pointer"
                            >
                                <span>{__('Open Bulk Meta Editor', 'quinrel')}</span>
                                <ArrowRightIcon className="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>

                    {/* 4. Image SEO Card */}
                    <div className="bg-white rounded-xl p-4.5 border border-slate-200/90 shadow-2xs hover:shadow-xs transition-shadow flex flex-col justify-between">
                        <div>
                            <div className="flex items-center justify-between mb-2">
                                <span className="text-xs font-bold uppercase tracking-wider text-slate-400">
                                    {__('Image SEO', 'quinrel')}
                                </span>
                                <ImageIcon className="w-5 h-5 text-purple-500" />
                            </div>
                            <div className="text-base font-semibold text-slate-900">
                                {standalone.missing_alt_label}
                            </div>
                            <p className="text-xs text-slate-500 mt-1 mb-0">
                                {__('WebP conversion, responsive sizing and alt attribute audit.', 'quinrel')}
                            </p>
                        </div>
                        <div className="mt-3.5 pt-3 border-t border-slate-100">
                            <button
                                type="button"
                                onClick={() => setActiveTab('image_seo')}
                                className="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-800 bg-transparent border-0 p-0 cursor-pointer"
                            >
                                <span>{__('Audit & Compress Images', 'quinrel')}</span>
                                <ArrowRightIcon className="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>

                    {/* 5. Automated 301 Redirects Card */}
                    <div className="bg-white rounded-xl p-4.5 border border-slate-200/90 shadow-2xs hover:shadow-xs transition-shadow flex flex-col justify-between">
                        <div>
                            <div className="flex items-center justify-between mb-2">
                                <span className="text-xs font-bold uppercase tracking-wider text-slate-400">
                                    {__('Redirect Manager', 'quinrel')}
                                </span>
                                <TechIcon className="w-5 h-5 text-emerald-500" />
                            </div>
                            <div className="text-base font-semibold text-slate-900">
                                {standalone.redirects_label}
                            </div>
                            <p className="text-xs text-slate-500 mt-1 mb-0">
                                {__('Automatic 301 redirects on slug change with regex matching.', 'quinrel')}
                            </p>
                        </div>
                        <div className="mt-3.5 pt-3 border-t border-slate-100">
                            <button
                                type="button"
                                onClick={() => {
                                    setTechSubTab('redirects');
                                    setActiveTab('technical');
                                }}
                                className="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-800 bg-transparent border-0 p-0 cursor-pointer"
                            >
                                <span>{__('Manage Redirect Rules', 'quinrel')}</span>
                                <ArrowRightIcon className="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>

                    {/* 6. 404 Detection Card */}
                    <div className="bg-white rounded-xl p-4.5 border border-slate-200/90 shadow-2xs hover:shadow-xs transition-shadow flex flex-col justify-between">
                        <div>
                            <div className="flex items-center justify-between mb-2">
                                <span className="text-xs font-bold uppercase tracking-wider text-slate-400">
                                    {__('404 Monitor', 'quinrel')}
                                </span>
                                <BrokenLinkIcon className="w-5 h-5 text-rose-500" />
                            </div>
                            <div className="text-base font-semibold text-slate-900">
                                {standalone.not_found_label}
                            </div>
                            <p className="text-xs text-slate-500 mt-1 mb-0">
                                {__('Real-time capture of broken inbound URLs with referrer tracking.', 'quinrel')}
                            </p>
                        </div>
                        <div className="mt-3.5 pt-3 border-t border-slate-100">
                            <button
                                type="button"
                                onClick={() => {
                                    setTechSubTab('404s');
                                    setActiveTab('technical');
                                }}
                                className="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-800 bg-transparent border-0 p-0 cursor-pointer"
                            >
                                <span>{__('Inspect 404 Event Log', 'quinrel')}</span>
                                <ArrowRightIcon className="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* ══════════ QUINREL CLOUD (OPTIONAL ARCHITECTURE) ══════════ */}
            <div className="bg-white rounded-2xl border border-slate-200/90 shadow-xs p-5 sm:p-6 space-y-4">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <div className="flex items-center gap-2">
                            <h3 className="text-base font-bold text-slate-900 m-0">
                                {__('Quinrel Cloud & Agency Hub', 'quinrel')}
                            </h3>
                            <span
                                className={`text-[11px] font-semibold px-2 py-0.5 rounded-full ${
                                    cloudConnected
                                        ? 'bg-emerald-100 text-emerald-800'
                                        : 'bg-slate-100 text-slate-600'
                                }`}
                            >
                                {cloudConnected ? __('Connected', 'quinrel') : __('Standalone / Local', 'quinrel')}
                            </span>
                        </div>
                        <p className="text-xs text-slate-500 m-0 mt-1">
                            {__('Quinrel runs 100% of technical SEO locally with zero cloud dependencies. Connecting an agency account unlocks automated fix recommendations and team orchestration.', 'quinrel')}
                        </p>
                    </div>

                    <button
                        type="button"
                        aria-expanded={showQuinrelNote}
                        aria-controls="quinrel-connect-note"
                        onClick={() => setShowQuinrelNote((open) => !open)}
                        className="self-start sm:self-auto px-3.5 py-2 bg-white hover:bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 shadow-2xs transition-colors cursor-pointer"
                    >
                        {showQuinrelNote
                            ? __('Hide Details', 'quinrel')
                            : (cloudConnected ? __('Manage Connection', 'quinrel') : __('Connect Quinrel Cloud', 'quinrel'))}
                    </button>
                </div>

                {showQuinrelNote && (
                    <div id="quinrel-connect-note" className="mt-4 pt-4 border-t border-slate-100 space-y-4">
                        {!canConnectQuinrel ? (
                            <p className="text-xs text-slate-600 m-0">
                                {__('Only administrators can manage the Quinrel Cloud connection.', 'quinrel')}
                            </p>
                        ) : (
                            <div className="space-y-4 max-w-xl">
                                {connectionLoading && (
                                    <div className="flex items-center gap-2 text-xs text-slate-500">
                                        <Spinner />
                                        <span>{__('Loading Quinrel connection status…', 'quinrel')}</span>
                                    </div>
                                )}
                                {connectionError && (
                                    <p role="alert" className="text-xs text-amber-800 m-0">
                                        ⚠️ {connectionError}
                                    </p>
                                )}
                                {connectionMessage && (
                                    <p role="status" className="text-xs text-emerald-700 m-0">
                                        ✓ {connectionMessage}
                                    </p>
                                )}

                                <div className="space-y-1.5">
                                    <label htmlFor="quinrel-site-token" className="block text-xs font-semibold text-slate-700">
                                        {__('Site Token', 'quinrel')}
                                    </label>
                                    <div className="flex gap-2">
                                        <input
                                            id="quinrel-site-token"
                                            type="password"
                                            autoComplete="off"
                                            spellCheck={false}
                                            value={siteToken}
                                            onChange={(e) => setSiteToken(e.target.value)}
                                            placeholder={__('Paste site token…', 'quinrel')}
                                            className="flex-1 border border-slate-300 rounded-lg px-3 py-1.5 text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                                        />
                                        <button
                                            type="button"
                                            disabled={connectionBusy || siteToken.trim() === ''}
                                            onClick={() => postConnection('/connection/site-token', { site_token: siteToken.trim() })}
                                            className="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg text-xs font-semibold shadow-2xs transition-colors cursor-pointer"
                                        >
                                            {__('Connect', 'quinrel')}
                                        </button>
                                    </div>
                                </div>

                                <div className="space-y-1.5">
                                    <label htmlFor="quinrel-authorization-code" className="block text-xs font-semibold text-slate-700">
                                        {__('Authorization Code', 'quinrel')}
                                    </label>
                                    <div className="flex gap-2">
                                        <input
                                            id="quinrel-authorization-code"
                                            type="password"
                                            autoComplete="off"
                                            spellCheck={false}
                                            value={authorizationCode}
                                            onChange={(e) => setAuthorizationCode(e.target.value)}
                                            placeholder={__('Paste authorization code…', 'quinrel')}
                                            className="flex-1 border border-slate-300 rounded-lg px-3 py-1.5 text-xs focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                                        />
                                        <button
                                            type="button"
                                            disabled={connectionBusy || authorizationCode.trim() === ''}
                                            onClick={() => postConnection('/connection/authorization-code', { code: authorizationCode.trim() })}
                                            className="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white rounded-lg text-xs font-semibold shadow-2xs transition-colors cursor-pointer"
                                        >
                                            {__('Verify Code', 'quinrel')}
                                        </button>
                                    </div>
                                </div>

                                {connection && connection.credential_saved && (
                                    <div className="flex flex-wrap gap-2 pt-2">
                                        <button
                                            type="button"
                                            disabled={connectionBusy}
                                            onClick={() => postConnection('/connection/verify')}
                                            className="px-3 py-1.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-lg text-xs font-semibold shadow-2xs transition-colors cursor-pointer"
                                        >
                                            {__('Check Saved Credential', 'quinrel')}
                                        </button>
                                        <button
                                            type="button"
                                            disabled={connectionBusy}
                                            onClick={() => postConnection('/connection/rotate')}
                                            className="px-3 py-1.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-lg text-xs font-semibold shadow-2xs transition-colors cursor-pointer"
                                        >
                                            {__('Rotate Credential', 'quinrel')}
                                        </button>
                                        <button
                                            type="button"
                                            disabled={connectionBusy}
                                            onClick={() => postConnection('/connection/revoke')}
                                            className="px-3 py-1.5 bg-white border border-rose-300 hover:bg-rose-50 text-rose-700 rounded-lg text-xs font-semibold shadow-2xs transition-colors cursor-pointer"
                                        >
                                            {__('Disconnect', 'quinrel')}
                                        </button>
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                )}
            </div>

            {/* ══════════ SEARCH CONSOLE EXPANDABLE TELEMETRY ══════════ */}
            <div className="bg-white rounded-2xl border border-slate-200/90 shadow-xs p-5 sm:p-6 space-y-4">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h3 className="text-base font-bold text-slate-900 m-0">
                            {__('Google Search Console Telemetry', 'quinrel')}
                        </h3>
                        <p className="text-xs text-slate-500 m-0 mt-1">
                            {__('Direct click and impression metrics synced from Google Search Console API. Optional local reporting.', 'quinrel')}
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            onClick={() => {
                                setActiveTab('settings_gsc');
                                setFocusIndexing(true);
                            }}
                            className="px-3.5 py-2 bg-white hover:bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 shadow-2xs transition-colors cursor-pointer"
                        >
                            {__('API Configuration', 'quinrel')}
                        </button>
                        {!gscOpen ? (
                            <button
                                type="button"
                                onClick={() => setGscOpen(true)}
                                className="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold shadow-2xs transition-colors cursor-pointer"
                            >
                                {__('Load Console Data', 'quinrel')}
                            </button>
                        ) : (
                            <button
                                type="button"
                                onClick={() => {
                                    setGscData(null);
                                    setCannData(null);
                                }}
                                className="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold transition-colors cursor-pointer"
                            >
                                ↺ {__('Refresh', 'quinrel')}
                            </button>
                        )}
                    </div>
                </div>

                {gscOpen && (
                    <div className="space-y-5 pt-3 border-t border-slate-100">
                        {gscLoading && (
                            <div className="flex items-center gap-3 p-6 bg-slate-50 rounded-xl">
                                <Spinner />
                                <span className="text-xs text-slate-500">{__('Fetching Google Search Console telemetry…', 'quinrel')}</span>
                            </div>
                        )}
                        {gscError && (
                            <div className="p-4 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800">
                                ⚠ {gscError}
                            </div>
                        )}

                        {gscData?.summary && (
                            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                                <div className="p-4 rounded-xl border border-slate-200 bg-white">
                                    <div className="text-xs font-semibold text-slate-400">{__('Total Clicks', 'quinrel')}</div>
                                    <div className="text-2xl font-bold text-emerald-600 mt-1">{fmtNum(gscData.summary.clicks)}</div>
                                </div>
                                <div className="p-4 rounded-xl border border-slate-200 bg-white">
                                    <div className="text-xs font-semibold text-slate-400">{__('Impressions', 'quinrel')}</div>
                                    <div className="text-2xl font-bold text-slate-900 mt-1">{fmtNum(gscData.summary.impressions)}</div>
                                </div>
                                <div className="p-4 rounded-xl border border-slate-200 bg-white">
                                    <div className="text-xs font-semibold text-slate-400">{__('Avg. CTR', 'quinrel')}</div>
                                    <div className="text-2xl font-bold text-slate-900 mt-1">{gscData.summary.avg_ctr}%</div>
                                </div>
                                <div className="p-4 rounded-xl border border-slate-200 bg-white">
                                    <div className="text-xs font-semibold text-slate-400">{__('Avg. Position', 'quinrel')}</div>
                                    <div className="text-2xl font-bold text-indigo-600 mt-1">{String(gscData.summary.avg_position)}</div>
                                </div>
                            </div>
                        )}

                        {gscData && (
                            <div className="flex gap-2 border-b border-slate-200">
                                {[
                                    ['overview', __('Overview & Trends', 'quinrel')],
                                    ['pages', __('Top Pages', 'quinrel')],
                                    ['cannibalization', __('Keyword Cannibalization', 'quinrel')],
                                ].map(([id, label]) => (
                                    <button
                                        type="button"
                                        key={id}
                                        onClick={() => setGscSubTab(id)}
                                        className={`px-4 py-2 text-xs font-semibold border-b-2 transition-colors bg-transparent cursor-pointer ${
                                            gscSubTab === id
                                                ? 'border-indigo-600 text-indigo-600'
                                                : 'border-transparent text-slate-500 hover:text-slate-800'
                                        }`}
                                    >
                                        {label}
                                    </button>
                                ))}
                            </div>
                        )}

                        {gscData && gscSubTab === 'overview' && (
                            <div className="space-y-4">
                                {gscData.trend?.length > 0 && (
                                    <div className="bg-white border border-slate-200 rounded-xl p-4">
                                        <h4 className="text-xs font-bold text-slate-700 mb-3 m-0 uppercase tracking-wider">
                                            {__('28-Day Click Trend', 'quinrel')}
                                        </h4>
                                        <GscTrendChart data={gscData.trend} />
                                    </div>
                                )}
                                <div className="bg-white border border-slate-200 rounded-xl overflow-hidden">
                                    <div className="px-4 py-3 border-b bg-slate-50 font-bold text-xs text-slate-700">
                                        {__('Top Queries (28 days)', 'quinrel')}
                                    </div>
                                    <table className="w-full text-left border-collapse">
                                        <thead>
                                            <tr className="bg-slate-50 border-b">
                                                <th className="p-3 text-xs font-semibold text-slate-500 uppercase">{__('Query', 'quinrel')}</th>
                                                <th className="p-3 text-xs font-semibold text-slate-500 uppercase text-right">{__('Clicks', 'quinrel')}</th>
                                                <th className="p-3 text-xs font-semibold text-slate-500 uppercase text-right">{__('Impr.', 'quinrel')}</th>
                                                <th className="p-3 text-xs font-semibold text-slate-500 uppercase text-right">{__('CTR', 'quinrel')}</th>
                                                <th className="p-3 text-xs font-semibold text-slate-500 uppercase text-right">{__('Pos.', 'quinrel')}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {(gscData.top_queries || []).map((kw, i) => (
                                                <tr key={i} className="border-b last:border-0 hover:bg-slate-50">
                                                    <td className="p-3 text-xs text-slate-800 font-medium">{kw.query}</td>
                                                    <td className="p-3 text-xs font-semibold text-slate-700 text-right">{fmtNum(kw.clicks)}</td>
                                                    <td className="p-3 text-xs text-slate-500 text-right">{fmtNum(kw.impressions)}</td>
                                                    <td className="p-3 text-xs text-slate-500 text-right">{kw.ctr}%</td>
                                                    <td className="p-3 text-xs font-bold text-right" style={{ color: posColor(kw.position) }}>{kw.position}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}

                        {gscData && gscSubTab === 'pages' && (
                            <div className="bg-white border border-slate-200 rounded-xl overflow-hidden">
                                <div className="px-4 py-3 border-b bg-slate-50 font-bold text-xs text-slate-700">
                                    {__('Top Pages by Clicks (28 days)', 'quinrel')}
                                </div>
                                <table className="w-full text-left border-collapse">
                                    <thead>
                                        <tr className="bg-slate-50 border-b">
                                            <th className="p-3 text-xs font-semibold text-slate-500 uppercase">{__('Page', 'quinrel')}</th>
                                            <th className="p-3 text-xs font-semibold text-slate-500 uppercase text-right">{__('Clicks', 'quinrel')}</th>
                                            <th className="p-3 text-xs font-semibold text-slate-500 uppercase text-right">{__('Impr.', 'quinrel')}</th>
                                            <th className="p-3 text-xs font-semibold text-slate-500 uppercase text-right">{__('CTR', 'quinrel')}</th>
                                            <th className="p-3 text-xs font-semibold text-slate-500 uppercase text-right">{__('Pos.', 'quinrel')}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {(gscData.top_pages || []).map((pg, i) => (
                                            <tr key={i} className="border-b last:border-0 hover:bg-slate-50">
                                                <td className="p-3 text-xs">
                                                    <div className="font-semibold text-slate-800">{pg.label}</div>
                                                    <div className="text-[11px] text-slate-400 truncate max-w-xs">{pg.url}</div>
                                                </td>
                                                <td className="p-3 text-xs font-semibold text-slate-700 text-right">{fmtNum(pg.clicks)}</td>
                                                <td className="p-3 text-xs text-slate-500 text-right">{fmtNum(pg.impressions)}</td>
                                                <td className="p-3 text-xs text-slate-500 text-right">{pg.ctr}%</td>
                                                <td className="p-3 text-xs font-bold text-right" style={{ color: posColor(pg.position) }}>{pg.position}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {gscData && gscSubTab === 'cannibalization' && (
                            <div className="space-y-3">
                                <div className="p-3.5 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-900">
                                    {__('Keyword cannibalization occurs when multiple URLs compete for identical search queries. Consolidate or differentiate these pages to improve ranking signals.', 'quinrel')}
                                </div>
                                {cannLoading && (
                                    <div className="flex items-center gap-3 p-6 bg-slate-50 rounded-xl">
                                        <Spinner />
                                        <span className="text-xs text-slate-500">{__('Analyzing 90-day search queries…', 'quinrel')}</span>
                                    </div>
                                )}
                                {cannData && cannData.conflicts.length === 0 && (
                                    <div className="p-5 text-center text-emerald-800 bg-emerald-50 rounded-xl border border-emerald-200 text-xs font-semibold">
                                        ✓ {__('Zero keyword cannibalization detected in the last 90 days.', 'quinrel')}
                                    </div>
                                )}
                                {(cannData?.conflicts || []).map((c, i) => (
                                    <div key={i} className="border border-slate-200 rounded-xl overflow-hidden bg-white">
                                        <div className={`px-4 py-2.5 flex items-center gap-2 text-xs font-bold ${
                                            c.severity === 'high' ? 'bg-rose-50 text-rose-800 border-b border-rose-200' : 'bg-amber-50 text-amber-800 border-b border-amber-200'
                                        }`}>
                                            <span>{c.severity === 'high' ? '🔴' : '🟡'}</span>
                                            <span className="flex-1">"{c.query}"</span>
                                            <span className="text-[11px] font-normal opacity-80">
                                                {c.page_count} {__('competing URLs', 'quinrel')}
                                            </span>
                                        </div>
                                        <table className="w-full text-left border-collapse">
                                            <tbody>
                                                {c.pages.map((p, j) => (
                                                    <tr key={j} className="border-b last:border-0 hover:bg-slate-50">
                                                        <td className="p-3 text-xs">
                                                            <div className="font-semibold text-slate-800">{p.title}</div>
                                                            <div className="text-[11px] text-slate-400">{p.url}</div>
                                                        </td>
                                                        <td className="p-3 text-xs text-slate-600 text-right">{p.clicks} {__('clicks', 'quinrel')}</td>
                                                        <td className="p-3 text-xs font-bold text-right" style={{ color: posColor(p.position) }}>#{p.position}</td>
                                                        {p.post_id && (
                                                            <td className="p-3 text-right">
                                                                <a
                                                                    href={`/wp-admin/post.php?post=${p.post_id}&action=edit`}
                                                                    className="text-xs font-semibold text-indigo-600 hover:underline"
                                                                >
                                                                    {__('Edit Post →', 'quinrel')}
                                                                </a>
                                                            </td>
                                                        )}
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
};

export default OverviewDashboard;

