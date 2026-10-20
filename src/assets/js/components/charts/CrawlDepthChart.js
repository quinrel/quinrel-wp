import React, { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';

export const CrawlDepthChart = ({ apiBase, nonce, onActionComplete }) => {
    const [isHealing, setIsHealing] = useState(false);
    const [isCollapsing, setIsCollapsing] = useState(false);
    const [statusMessage, setStatusMessage] = useState(null);

    // Mock/Initial baseline crawl stats
    const healthScore = 94;
    const depthLevels = [
        { level: __('Level 1 (Homepage)', 'quinrel'), count: 1, percent: 1, color: '#6366f1' },
        { level: __('Level 2 (1 Click)', 'quinrel'), count: 86, percent: 43, color: '#3b82f6' },
        { level: __('Level 3 (2 Clicks)', 'quinrel'), count: 105, percent: 52, color: '#0ea5e9' },
        { level: __('Level 4+ (Deep / Orphan)', 'quinrel'), count: 8, percent: 4, color: '#f59e0b' },
    ];

    const httpDistribution = [
        { status: '200 OK', count: 184, percent: 92, color: 'bg-emerald-500' },
        { status: '301 Redirect', count: 12, percent: 6, color: 'bg-indigo-500' },
        { status: '404 Not Found', count: 4, percent: 2, color: 'bg-rose-500' },
        { status: '500 Server Error', count: 0, percent: 0, color: 'bg-red-700' },
    ];

    const handleAutoHealLinks = async () => {
        setIsHealing(true);
        setStatusMessage(null);
        try {
            const res = await fetch(`${apiBase}/broken-links/heal-all`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce,
                },
            });
            const data = await res.json();
            if (data.success) {
                setStatusMessage({ type: 'success', text: data.message });
                if (onActionComplete) onActionComplete();
            } else {
                setStatusMessage({ type: 'error', text: data.message || __('Failed to heal links.', 'quinrel') });
            }
        } catch (e) {
            setStatusMessage({ type: 'error', text: __('Network error while healing links.', 'quinrel') });
        } finally {
            setIsHealing(false);
        }
    };

    const handleCollapseChains = async () => {
        setIsCollapsing(true);
        setStatusMessage(null);
        try {
            const res = await fetch(`${apiBase}/redirects/collapse-chains`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce,
                },
            });
            const data = await res.json();
            if (data.success) {
                setStatusMessage({ type: 'success', text: data.message });
                if (onActionComplete) onActionComplete();
            } else {
                setStatusMessage({ type: 'error', text: data.message || __('Failed to collapse chains.', 'quinrel') });
            }
        } catch (e) {
            setStatusMessage({ type: 'error', text: __('Network error while collapsing chains.', 'quinrel') });
        } finally {
            setIsCollapsing(false);
        }
    };

    return (
        <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
                <div>
                    <h3 className="text-base font-bold text-slate-900 tracking-tight m-0">
                        {__('Site Crawl Health & Click Depth', 'quinrel')}
                    </h3>
                    <p className="text-xs text-slate-500 mt-1 m-0">
                        {__('Internal crawl graph, click depth analysis, and autonomous self-healing redirects.', 'quinrel')}
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <span className="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        ● {__('Live Link Index Active', 'quinrel')}
                    </span>
                </div>
            </div>

            {statusMessage && (
                <div className={`p-3 rounded-xl text-xs font-medium flex items-center justify-between ${statusMessage.type === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'}`}>
                    <span>{statusMessage.text}</span>
                    <button type="button" onClick={() => setStatusMessage(null)} className="text-xs font-bold opacity-75 hover:opacity-100">✕</button>
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
                {/* Radial Meter */}
                <div className="lg:col-span-4 flex flex-col items-center justify-center p-4 bg-slate-50/70 rounded-xl border border-slate-100">
                    <div className="relative w-32 h-32 flex items-center justify-center">
                        <svg className="w-full h-full -rotate-90 transform" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="42" fill="transparent" stroke="#e2e8f0" strokeWidth="8" />
                            <circle
                                cx="50"
                                cy="50"
                                r="42"
                                fill="transparent"
                                stroke="#10b981"
                                strokeWidth="8"
                                strokeDasharray={264}
                                strokeDashoffset={264 - (264 * healthScore) / 100}
                                strokeLinecap="round"
                                className="transition-all duration-1000 ease-out"
                            />
                        </svg>
                        <div className="absolute flex flex-col items-center justify-center text-center">
                            <span className="text-2xl font-black text-slate-900 tracking-tight">{healthScore}%</span>
                            <span className="text-[10px] uppercase font-bold tracking-wider text-slate-500">{__('Health Index', 'quinrel')}</span>
                        </div>
                    </div>
                    <p className="text-xs font-semibold text-slate-700 mt-2 m-0">{__('Excellent Crawl Efficiency', 'quinrel')}</p>
                    <span className="text-[11px] text-slate-400 text-center mt-0.5">{__('92% URLs reachable in ≤2 clicks', 'quinrel')}</span>
                </div>

                {/* Depth & HTTP Distribution */}
                <div className="lg:col-span-8 space-y-4">
                    <div>
                        <div className="flex justify-between items-center text-xs font-bold text-slate-700 mb-1.5">
                            <span>{__('Click Depth Distribution', 'quinrel')}</span>
                            <span className="text-slate-400 font-normal">{__('Total: 200 Pages', 'quinrel')}</span>
                        </div>
                        <div className="space-y-2">
                            {depthLevels.map((d, i) => (
                                <div key={i} className="flex items-center gap-3 text-xs">
                                    <span className="w-36 text-slate-600 truncate">{d.level}</span>
                                    <div className="flex-1 bg-slate-100 rounded-full h-2.5 overflow-hidden">
                                        <div
                                            className="h-full rounded-full transition-all duration-700"
                                            style={{ width: `${d.percent}%`, backgroundColor: d.color }}
                                        />
                                    </div>
                                    <span className="w-16 text-right font-semibold text-slate-700">{d.count} ({d.percent}%)</span>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="pt-3 border-t border-slate-100">
                        <span className="text-xs font-bold text-slate-700 block mb-2">{__('HTTP Response Breakdown', 'quinrel')}</span>
                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            {httpDistribution.map((h, i) => (
                                <div key={i} className="p-2 rounded-lg bg-slate-50 border border-slate-100">
                                    <div className="flex items-center gap-1.5">
                                        <span className={`w-2 h-2 rounded-full ${h.color}`} />
                                        <span className="text-[11px] font-medium text-slate-600">{h.status}</span>
                                    </div>
                                    <span className="text-sm font-bold text-slate-900 block mt-1">{h.count}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>

            {/* Self-Healing Actions Bar */}
            <div className="p-4 rounded-xl bg-gradient-to-r from-indigo-50/80 to-slate-50 border border-indigo-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div className="space-y-0.5">
                    <span className="text-xs font-bold text-indigo-950 flex items-center gap-1.5">
                        <span className="text-indigo-600">⚡</span> {__('Autonomous Link Self-Healing', 'quinrel')}
                    </span>
                    <p className="text-[11px] text-indigo-800/80 m-0">
                        {__('Rewrite internal links pointing to 301 redirects and collapse multi-hop redirect chains in 1 click.', 'quinrel')}
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={handleAutoHealLinks}
                        disabled={isHealing}
                        className="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg shadow-sm transition-colors flex items-center gap-1.5 disabled:opacity-50"
                    >
                        {isHealing ? __('Healing…', 'quinrel') : __('Auto-Heal Outdated Links', 'quinrel')}
                    </button>
                    <button
                        type="button"
                        onClick={handleCollapseChains}
                        disabled={isCollapsing}
                        className="px-3 py-1.5 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 text-xs font-medium rounded-lg shadow-sm transition-colors flex items-center gap-1.5 disabled:opacity-50"
                    >
                        {isCollapsing ? __('Collapsing…', 'quinrel') : __('Collapse Chains', 'quinrel')}
                    </button>
                </div>
            </div>
        </div>
    );
};
export default CrawlDepthChart;

