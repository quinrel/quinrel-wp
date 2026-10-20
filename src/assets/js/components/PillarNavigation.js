import React, { useRef } from 'react';
import { __ } from '@wordpress/i18n';
import {
    OverviewIcon,
    VisibilityIcon,
    IntelligenceIcon,
    HealthIcon,
    TitlesIcon,
    SitemapIcon,
    HtmlSitemapIcon,
    SchemaIcon,
    SocialIcon,
    ImageIcon,
    AiBotIcon,
    RankIcon,
    AnalyticsIcon,
    AuditIcon,
    TechIcon,
    LinkIcon,
    BrokenLinkIcon,
    ComplianceIcon,
    RolesIcon,
    SettingsIcon,
} from './QuinrelIcons';

const TOOL_ICONS = {
    dashboard: OverviewIcon,
    bulk_meta: TitlesIcon,
    sitemaps: SitemapIcon,
    html_sitemap: HtmlSitemapIcon,
    schema: SchemaIcon,
    social: SocialIcon,
    image_seo: ImageIcon,
    llms: AiBotIcon,
    rank_tracker: RankIcon,
    analytics: AnalyticsIcon,
    site_audit: AuditIcon,
    technical: TechIcon,
    links: LinkIcon,
    broken_links: BrokenLinkIcon,
    compliance: ComplianceIcon,
    roles: RolesIcon,
    settings: SettingsIcon,
    network_seo: TechIcon,
};

const PILLAR_CONFIG = [
    {
        id: 'overview',
        title: __('Overview', 'quinrel'),
        subtitle: __('Executive KPIs & AI crawlers', 'quinrel'),
        icon: OverviewIcon,
        color: 'indigo',
    },
    {
        id: 'visibility',
        title: __('Visibility', 'quinrel'),
        subtitle: __('Meta, sitemaps, schema & media', 'quinrel'),
        icon: VisibilityIcon,
        color: 'sky',
    },
    {
        id: 'intelligence',
        title: __('Intelligence', 'quinrel'),
        subtitle: __('LLMs, rankings & analytics', 'quinrel'),
        icon: IntelligenceIcon,
        color: 'purple',
    },
    {
        id: 'health',
        title: __('Site Health', 'quinrel'),
        subtitle: __('Audits, redirects & security', 'quinrel'),
        icon: HealthIcon,
        color: 'emerald',
    },
];

export const PillarNavigation = ({ navPillars, activeTab, setActiveTab }) => {
    const subNavRef = useRef(null);

    // Determine current active pillar based on activeTab
    const currentPillarIndex = navPillars.findIndex((pillar) =>
        pillar.items.some((item) => item.id === activeTab)
    );
    const activePillarIdx = currentPillarIndex >= 0 ? currentPillarIndex : 0;
    const activePillar = navPillars[activePillarIdx];
    const currentSubItems = activePillar ? activePillar.items : [];

    const handlePillarClick = (pillarIndex) => {
        const pillar = navPillars[pillarIndex];
        if (!pillar) return;

        // If clicking a pillar whose items do not contain the active tab, switch to the first item
        const hasActiveTab = pillar.items.some((item) => item.id === activeTab);
        if (!hasActiveTab && pillar.items.length > 0) {
            setActiveTab(pillar.items[0].id);
        }
    };

    const handleSubKeyDown = (event) => {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
            return;
        }
        event.preventDefault();
        const currentIndex = currentSubItems.findIndex((item) => item.id === activeTab);
        let nextIndex = currentIndex < 0 ? 0 : currentIndex;

        if (event.key === 'ArrowRight') {
            nextIndex = (currentIndex + 1) % currentSubItems.length;
        } else if (event.key === 'ArrowLeft') {
            nextIndex = (currentIndex - 1 + currentSubItems.length) % currentSubItems.length;
        } else if (event.key === 'Home') {
            nextIndex = 0;
        } else if (event.key === 'End') {
            nextIndex = currentSubItems.length - 1;
        }

        const nextItem = currentSubItems[nextIndex];
        if (nextItem) {
            setActiveTab(nextItem.id);
            window.requestAnimationFrame(() => {
                const el = document.getElementById(`quinrel-subtab-${nextItem.id}`);
                if (el) el.focus();
            });
        }
    };

    return (
        <div className="quinrel-navigation space-y-4 mb-6">
            {/* ══════════ MASTER 4-PILLAR SEGMENTED DECK ══════════ */}
            <div
                className="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-3"
                role="tablist"
                aria-label={__('Quinrel Core Pillars', 'quinrel')}
            >
                {PILLAR_CONFIG.map((config, index) => {
                    const pillarData = navPillars[index];
                    if (!pillarData) return null;

                    const isPillarActive = activePillarIdx === index;
                    const IconComponent = config.icon;
                    const itemCount = pillarData.items.length;

                    return (
                        <button
                            key={config.id}
                            type="button"
                            role="tab"
                            id={`quinrel-pillar-${config.id}`}
                            aria-selected={isPillarActive}
                            aria-controls="quinrel-subnav-strip"
                            onClick={() => handlePillarClick(index)}
                            className={`group relative text-left p-3.5 sm:p-4 rounded-xl transition-all duration-200 border cursor-pointer ${
                                isPillarActive
                                    ? 'bg-gradient-to-br from-white via-indigo-50/20 to-indigo-50/40 border-indigo-500 shadow-sm ring-2 ring-indigo-500/10'
                                    : 'bg-white hover:bg-slate-50/80 border-slate-200/90 hover:border-slate-300 shadow-xs'
                            }`}
                        >
                            {/* Active Top Highlight Bar */}
                            {isPillarActive && (
                                <span
                                    className="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-indigo-500 via-brand-500 to-indigo-600 rounded-t-xl"
                                    aria-hidden="true"
                                />
                            )}

                            <div className="flex items-center justify-between gap-2">
                                <div
                                    className={`w-9 h-9 rounded-lg flex items-center justify-center transition-colors ${
                                        isPillarActive
                                            ? 'bg-indigo-600 text-white shadow-xs'
                                            : 'bg-slate-100 text-slate-600 group-hover:bg-slate-200/80 group-hover:text-slate-800'
                                    }`}
                                >
                                    <IconComponent className="w-5 h-5" />
                                </div>
                                <span
                                    className={`text-[11px] font-semibold px-2 py-0.5 rounded-full ${
                                        isPillarActive
                                            ? 'bg-indigo-100 text-indigo-700 font-bold'
                                            : 'bg-slate-100 text-slate-500 group-hover:bg-slate-200/60'
                                    }`}
                                >
                                    {itemCount} {itemCount === 1 ? __('tool', 'quinrel') : __('tools', 'quinrel')}
                                </span>
                            </div>

                            <div className="mt-2.5">
                                <h2
                                    className={`text-sm font-bold tracking-tight m-0 transition-colors ${
                                        isPillarActive ? 'text-slate-900 font-bold' : 'text-slate-700 group-hover:text-slate-900'
                                    }`}
                                >
                                    {config.title}
                                </h2>
                                <p className="text-xs text-slate-500 m-0 mt-0.5 line-clamp-1">
                                    {config.subtitle}
                                </p>
                            </div>
                        </button>
                    );
                })}
            </div>

            {/* ══════════ CONTEXTUAL SUB-NAVIGATION STRIP ══════════ */}
            <div
                id="quinrel-subnav-strip"
                ref={subNavRef}
                className="bg-slate-100/70 p-1.5 rounded-xl border border-slate-200/80 flex items-center gap-1 overflow-x-auto scrollbar-none"
                role="tablist"
                aria-label={sprintf(__('%s Tools', 'quinrel'), activePillar ? activePillar.title : __('Tools', 'quinrel'))}
            >
                {currentSubItems.map((item) => {
                    const isTabActive = activeTab === item.id;
                    const ItemIcon = TOOL_ICONS[item.id] || TechIcon;

                    return (
                        <button
                            key={item.id}
                            type="button"
                            role="tab"
                            id={`quinrel-subtab-${item.id}`}
                            aria-selected={isTabActive}
                            aria-controls="quinrel-tabpanel"
                            tabIndex={isTabActive ? 0 : -1}
                            onClick={() => setActiveTab(item.id)}
                            onKeyDown={handleSubKeyDown}
                            className={`flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-semibold whitespace-nowrap transition-all duration-150 cursor-pointer ${
                                isTabActive
                                    ? 'bg-white text-indigo-700 shadow-xs border border-slate-200/70'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-white/60 border border-transparent'
                            }`}
                        >
                            <ItemIcon className={`w-4 h-4 ${isTabActive ? 'text-indigo-600' : 'text-slate-400'}`} />
                            <span>{item.label}</span>
                        </button>
                    );
                })}
            </div>
        </div>
    );
};

export default PillarNavigation;

