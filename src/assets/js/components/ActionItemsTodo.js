import React, { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import {
    CheckCircleIcon,
    AlertCircleIcon,
    ArrowRightIcon,
    ExternalLinkIcon,
    TitlesIcon,
    ImageIcon,
    TechIcon,
    BrokenLinkIcon,
    SitemapIcon,
    AiBotIcon,
    SettingsIcon,
    WandIcon,
    AuditIcon,
} from './QuinrelIcons';

export const ActionItemsTodo = ({
    standalone,
    setupFinished,
    setShowSetup,
    cloudConnected,
    aiBotActivity,
    setActiveTab,
    setTechSubTab,
    activeCompetitor,
    deactivateCompetitorFromApp,
    linkStats,
}) => {
    const [filter, setFilter] = useState('all'); // 'all', 'pending', 'complete'

    // Build list of actionable items
    const items = [];

    // 1. Search Engine Indexability
    const isIndexable = standalone ? standalone.indexable !== false : true;
    items.push({
        id: 'indexability',
        title: __('Search Engine Visibility', 'quinrel'),
        severity: isIndexable ? 'pass' : 'critical',
        description: isIndexable
            ? __('Search engines are permitted to index and display your site.', 'quinrel')
            : __('Search engine visibility is discouraged in WordPress Reading settings. Google and Bing are blocked.', 'quinrel'),
        actionLabel: isIndexable ? null : __('Fix in Reading Settings', 'quinrel'),
        actionUrl: 'options-reading.php',
        icon: TechIcon,
    });

    // 2. Conflicting SEO Plugins
    if (activeCompetitor) {
        items.push({
            id: 'competitor',
            title: sprintf(__('%s Active Conflict', 'quinrel'), activeCompetitor.name),
            severity: 'critical',
            description: __('Running multiple SEO plugins causes duplicate meta tags and conflicting sitemaps.', 'quinrel'),
            actionLabel: sprintf(__('Deactivate %s', 'quinrel'), activeCompetitor.name),
            actionFn: deactivateCompetitorFromApp,
            icon: AlertCircleIcon,
        });
    }

    // 3. Missing Titles
    const missingTitlesCount = standalone?.missing_titles_count || 0;
    const hasMissingTitles = missingTitlesCount > 0;
    items.push({
        id: 'missing_titles',
        title: __('Post & Page SEO Titles', 'quinrel'),
        severity: hasMissingTitles ? 'warning' : 'pass',
        description: hasMissingTitles
            ? sprintf(__('%d published posts or pages are missing custom SEO titles.', 'quinrel'), missingTitlesCount)
            : __('All published posts and pages have optimized titles.', 'quinrel'),
        actionLabel: hasMissingTitles ? __('Edit Missing Titles', 'quinrel') : null,
        actionFn: () => setActiveTab('bulk_meta'),
        icon: TitlesIcon,
    });

    // 4. Missing Alt Text on Images
    const missingAltCount = standalone?.missing_alt_count || 0;
    const hasMissingAlt = missingAltCount > 0;
    items.push({
        id: 'missing_alt',
        title: __('Image Accessibility & Alt Text', 'quinrel'),
        severity: hasMissingAlt ? 'warning' : 'pass',
        description: hasMissingAlt
            ? sprintf(__('%d media images are missing alt text descriptions.', 'quinrel'), missingAltCount)
            : __('Media library images have alt text descriptions.', 'quinrel'),
        actionLabel: hasMissingAlt ? __('Audit & Fix Images', 'quinrel') : null,
        actionFn: () => setActiveTab('image_seo'),
        icon: ImageIcon,
    });

    // 5. Inbound 404 Errors
    const notFoundCount = standalone?.not_found_count || 0;
    const has404s = notFoundCount > 0;
    items.push({
        id: 'not_found',
        title: __('Inbound 404 URL Errors', 'quinrel'),
        severity: has404s ? 'warning' : 'pass',
        description: has404s
            ? sprintf(__('%d broken 404 requests recorded. Redirect them to recover lost traffic.', 'quinrel'), notFoundCount)
            : __('Zero 404 errors detected in recent traffic.', 'quinrel'),
        actionLabel: has404s ? __('Inspect 404 Log', 'quinrel') : null,
        actionFn: () => {
            setTechSubTab('404s');
            setActiveTab('technical');
        },
        icon: BrokenLinkIcon,
    });

    // 6. XML Sitemaps
    const sitemapUrl = standalone?.sitemap_url || '/sitemap.xml';
    items.push({
        id: 'sitemaps',
        title: __('XML Sitemaps Generation', 'quinrel'),
        severity: 'pass',
        description: sprintf(__('Sitemap is actively published at %s with automatic update pings.', 'quinrel'), sitemapUrl),
        actionLabel: __('View Sitemap', 'quinrel'),
        actionUrl: sitemapUrl,
        icon: SitemapIcon,
    });

    // 7. AI Discovery (llms.txt)
    const totalAiHits = aiBotActivity?.total || 0;
    items.push({
        id: 'llms',
        title: __('AI Discovery (llms.txt)', 'quinrel'),
        severity: totalAiHits > 0 ? 'pass' : 'info',
        description: totalAiHits > 0
            ? sprintf(__('%d AI crawler visits tracked (GPTBot, ClaudeBot, Perplexity).', 'quinrel'), totalAiHits)
            : __('llms.txt is published. Awaiting first crawler discovery.', 'quinrel'),
        actionLabel: __('Review AI Permissions', 'quinrel'),
        actionFn: () => setActiveTab('llms'),
        icon: AiBotIcon,
    });

    // 8. Setup Wizard Completion
    items.push({
        id: 'setup',
        title: __('Initial Site Profile Configuration', 'quinrel'),
        severity: setupFinished ? 'pass' : 'warning',
        description: setupFinished
            ? __('Site profile and default SEO preferences configured.', 'quinrel')
            : __('Complete the 3-step setup wizard to customize SEO defaults for your site type.', 'quinrel'),
        actionLabel: setupFinished ? __('Re-run Setup Wizard', 'quinrel') : __('Launch Setup Wizard', 'quinrel'),
        actionFn: () => setShowSetup(true),
        icon: WandIcon,
    });

    // 9. Instant Indexing Keys (IndexNow / GSC)
    items.push({
        id: 'indexing_keys',
        title: __('Search Engine Indexing API Keys', 'quinrel'),
        severity: 'info',
        description: __('Configure Bing IndexNow and Google Search Console keys for instant URL submission upon publishing.', 'quinrel'),
        actionLabel: __('Configure Indexing', 'quinrel'),
        actionFn: () => setActiveTab('settings'),
        icon: SettingsIcon,
    });

    // 10. Autonomous Link Self-Healing
    items.push({
        id: 'self_healing_links',
        title: __('Autonomous In-Content Link Healing', 'quinrel'),
        severity: 'pass',
        description: __('Safely rewrites internal links in published post HTML pointing to 301 redirects directly to destination URLs.', 'quinrel'),
        actionLabel: __('Crawl Health & Depth', 'quinrel'),
        actionFn: () => setActiveTab('site_audit'),
        icon: BrokenLinkIcon,
    });

    // 11. Core Web Vitals Optimization
    items.push({
        id: 'cwv_autonomy',
        title: __('Core Web Vitals & PageSpeed Autonomy', 'quinrel'),
        severity: 'pass',
        description: __('Non-critical script deferral, font-display: swap, and WebP compression are optimizing LCP, INP, and CLS.', 'quinrel'),
        actionLabel: __('View Performance Gauges', 'quinrel'),
        actionFn: () => setActiveTab('site_audit'),
        icon: AuditIcon,
    });

    // 12. Google Indexation Lifecycle Funnel
    items.push({
        id: 'indexation_funnel',
        title: __('Google Indexation Funnel & Discovery', 'quinrel'),
        severity: 'pass',
        description: __('Tracks published URLs through sitemap discovery, Googlebot crawling, indexation, and search impressions.', 'quinrel'),
        actionLabel: __('View Funnel Chart', 'quinrel'),
        actionFn: () => setActiveTab('site_audit'),
        icon: SitemapIcon,
    });

    // Calculate metrics
    const completedCount = items.filter((i) => i.severity === 'pass').length;
    const totalCount = items.length;
    const completionPercentage = Math.round((completedCount / totalCount) * 100);

    const filteredItems = items.filter((item) => {
        if (filter === 'pending') return item.severity !== 'pass';
        if (filter === 'complete') return item.severity === 'pass';
        return true;
    });

    return (
        <div className="quinrel-todo space-y-6">
            {/* ══════════ ACTION CENTER HEADER & HEALTH METER ══════════ */}
            <div className="bg-white rounded-2xl border border-slate-200/90 p-6 shadow-xs">
                <div className="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <h2 className="text-xl font-bold text-slate-900 m-0">
                                {__('Action Items & Pending Tasks', 'quinrel')}
                            </h2>
                            <span className="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                {sprintf(__('%d of %d complete', 'quinrel'), completedCount, totalCount)}
                            </span>
                        </div>
                        <p className="text-xs text-slate-500 m-0 mt-1">
                            {__('Track items requiring your approval or action to achieve 100% search engine readiness.', 'quinrel')}
                        </p>
                    </div>

                    <div className="w-full md:w-64 space-y-1.5 flex-shrink-0">
                        <div className="flex justify-between text-xs font-semibold">
                            <span className="text-slate-600">{__('Readiness Score', 'quinrel')}</span>
                            <span className="text-indigo-600">{completionPercentage}%</span>
                        </div>
                        <div className="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden">
                            <div
                                className="h-full bg-gradient-to-r from-indigo-500 to-emerald-500 rounded-full transition-all duration-500"
                                style={{ width: `${completionPercentage}%` }}
                            />
                        </div>
                    </div>
                </div>

                {/* Filter Tabs */}
                <div className="flex items-center gap-2 mt-6 pt-4 border-t border-slate-100">
                    <button
                        type="button"
                        onClick={() => setFilter('all')}
                        className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors cursor-pointer ${
                            filter === 'all'
                                ? 'bg-indigo-600 text-white'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                        }`}
                    >
                        {sprintf(__('All Tasks (%d)', 'quinrel'), totalCount)}
                    </button>
                    <button
                        type="button"
                        onClick={() => setFilter('pending')}
                        className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors cursor-pointer ${
                            filter === 'pending'
                                ? 'bg-amber-600 text-white'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                        }`}
                    >
                        {sprintf(__('Action Needed (%d)', 'quinrel'), totalCount - completedCount)}
                    </button>
                    <button
                        type="button"
                        onClick={() => setFilter('complete')}
                        className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors cursor-pointer ${
                            filter === 'complete'
                                ? 'bg-emerald-600 text-white'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                        }`}
                    >
                        {sprintf(__('Resolved (%d)', 'quinrel'), completedCount)}
                    </button>
                </div>
            </div>

            {/* ══════════ TASK CARDS LIST ══════════ */}
            <div className="space-y-3">
                {filteredItems.map((item) => {
                    const ItemIcon = item.icon || CheckCircleIcon;

                    return (
                        <div
                            key={item.id}
                            className={`p-4 sm:p-5 rounded-xl border transition-all flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 ${
                                item.severity === 'critical'
                                    ? 'bg-rose-50/60 border-rose-200 shadow-2xs'
                                    : item.severity === 'warning'
                                    ? 'bg-amber-50/60 border-amber-200 shadow-2xs'
                                    : 'bg-white border-slate-200/80 shadow-2xs hover:border-slate-300'
                            }`}
                        >
                            <div className="flex items-start gap-3.5">
                                <div
                                    className={`w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5 ${
                                        item.severity === 'critical'
                                            ? 'bg-rose-100 text-rose-700'
                                            : item.severity === 'warning'
                                            ? 'bg-amber-100 text-amber-700'
                                            : 'bg-emerald-100 text-emerald-700'
                                    }`}
                                >
                                    <ItemIcon className="w-5 h-5" />
                                </div>
                                <div>
                                    <div className="flex items-center gap-2">
                                        <h3 className="text-sm font-bold text-slate-900 m-0">
                                            {item.title}
                                        </h3>
                                        <span
                                            className={`text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider ${
                                                item.severity === 'critical'
                                                    ? 'bg-rose-100 text-rose-800'
                                                    : item.severity === 'warning'
                                                    ? 'bg-amber-100 text-amber-800'
                                                    : 'bg-emerald-100 text-emerald-800'
                                            }`}
                                        >
                                            {item.severity === 'critical'
                                                ? __('Critical', 'quinrel')
                                                : item.severity === 'warning'
                                                ? __('Action Needed', 'quinrel')
                                                : __('Resolved', 'quinrel')}
                                        </span>
                                    </div>
                                    <p className="text-xs text-slate-600 m-0 mt-1 max-w-2xl leading-relaxed">
                                        {item.description}
                                    </p>
                                </div>
                            </div>

                            {item.actionLabel && (
                                <div className="flex-shrink-0 self-end sm:self-center">
                                    {item.actionUrl ? (
                                        <a
                                            href={item.actionUrl}
                                            target={item.actionUrl.startsWith('http') ? '_blank' : '_self'}
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-xs font-semibold bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 shadow-2xs transition-colors"
                                        >
                                            <span>{item.actionLabel}</span>
                                            <ExternalLinkIcon className="w-3.5 h-3.5" />
                                        </a>
                                    ) : (
                                        <button
                                            type="button"
                                            onClick={item.actionFn}
                                            className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-xs font-semibold bg-indigo-600 hover:bg-indigo-700 text-white shadow-2xs transition-colors cursor-pointer"
                                        >
                                            <span>{item.actionLabel}</span>
                                            <ArrowRightIcon className="w-3.5 h-3.5" />
                                        </button>
                                    )}
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
};

export default ActionItemsTodo;

