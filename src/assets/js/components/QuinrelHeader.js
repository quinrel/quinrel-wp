import React from 'react';
import { __ } from '@wordpress/i18n';
import { LogoMark, WandIcon, AuditIcon, ExternalLinkIcon, TodoIcon, SettingsIcon } from './QuinrelIcons';

export const QuinrelHeader = ({
    onOpenSetup,
    onRunAudit,
    onOpenTodo,
    onOpenSettings,
    sitemapUrl = '/sitemap.xml',
    cloudConnected = false,
    version,
}) => {
    const displayVersion = version || window.quinrelAdminConfig?.version || '1.1.0';
    const markUrl = window.quinrelAdminConfig?.markUrl || '';

    return (
        <header className="mb-6 bg-white border border-slate-200/90 rounded-2xl p-5 shadow-sm">
            <div className="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                {/* Brand & Status */}
                <div className="flex items-center gap-3.5">
                    {markUrl ? (
                        <img src={markUrl} alt="" width="44" height="44" className="h-11 w-11 flex-shrink-0 object-contain" />
                    ) : (
                        <div className="h-11 w-11 rounded-xl bg-gradient-to-tr from-indigo-600 via-indigo-700 to-violet-600 flex items-center justify-center text-white shadow-md shadow-indigo-500/25 flex-shrink-0">
                            <LogoMark className="w-6 h-6 text-white" />
                        </div>
                    )}
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-black tracking-tight text-slate-900 m-0">Quinrel</h1>
                            <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                v{displayVersion}
                            </span>
                            <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/70">
                                <span className="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                {cloudConnected ? __('Cloud Enhanced', 'quinrel') : __('Engine Active', 'quinrel')}
                            </span>
                        </div>
                        <p className="text-xs text-slate-500 m-0 mt-0.5 font-medium">
                            {__('Visibility & Discovery Intelligence for WordPress, with technical SEO included.', 'quinrel')}
                        </p>
                    </div>
                </div>

                {/* Quick Utility Actions */}
                <div className="flex flex-wrap items-center gap-2 self-stretch md:self-auto justify-end">
                    {onOpenTodo && (
                        <button
                            type="button"
                            onClick={onOpenTodo}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-lg shadow-sm transition-all cursor-pointer"
                            title={__('View pending action items', 'quinrel')}
                        >
                            <TodoIcon className="w-3.5 h-3.5 text-indigo-600" />
                            <span>{__('Action Items', 'quinrel')}</span>
                        </button>
                    )}

                    <button
                        type="button"
                        onClick={onRunAudit}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-slate-50 hover:bg-slate-100 border border-slate-200 rounded-lg shadow-sm transition-all cursor-pointer"
                        title={__('Run instant site audit', 'quinrel')}
                    >
                        <AuditIcon className="w-3.5 h-3.5 text-indigo-600" />
                        <span>{__('Site Audit', 'quinrel')}</span>
                    </button>

                    <button
                        type="button"
                        onClick={onOpenSetup}
                        className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-indigo-700 bg-indigo-50/80 hover:bg-indigo-100/80 border border-indigo-200/80 rounded-lg shadow-sm transition-all cursor-pointer"
                        title={__('Open Setup Wizard', 'quinrel')}
                    >
                        <WandIcon className="w-3.5 h-3.5 text-indigo-600" />
                        <span>{__('Setup Wizard', 'quinrel')}</span>
                    </button>

                    {onOpenSettings && (
                        <button
                            type="button"
                            onClick={onOpenSettings}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white hover:bg-slate-50 border border-slate-200 rounded-lg shadow-sm transition-all cursor-pointer"
                            title={__('Open Global Settings & Integrations', 'quinrel')}
                        >
                            <SettingsIcon className="w-3.5 h-3.5 text-indigo-600" />
                            <span>{__('Settings', 'quinrel')}</span>
                        </button>
                    )}

                    {sitemapUrl && (
                        <a
                            href={sitemapUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-600 hover:text-slate-900 bg-white hover:bg-slate-50 border border-slate-200 rounded-lg shadow-sm transition-all text-decoration-none"
                            title={__('Open XML Sitemap in new tab', 'quinrel')}
                        >
                            <span>{__('View Sitemap', 'quinrel')}</span>
                            <ExternalLinkIcon className="w-3 h-3 text-slate-400" />
                        </a>
                    )}
                </div>
            </div>
        </header>
    );
};

export default QuinrelHeader;
