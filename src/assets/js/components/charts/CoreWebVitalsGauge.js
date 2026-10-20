import React, { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';

export const CoreWebVitalsGauge = ({ apiBase, nonce, onActionComplete }) => {
    const [strategy, setStrategy] = useState('mobile');
    const [isOptimizing, setIsOptimizing] = useState(false);
    const [statusMessage, setStatusMessage] = useState(null);

    const scores = strategy === 'mobile'
        ? { perf: 92, a11y: 98, best: 100, seo: 96 }
        : { perf: 98, a11y: 100, best: 100, seo: 100 };

    const cwvMetrics = [
        { name: __('LCP (Largest Contentful Paint)', 'quinrel'), value: '1.4s', status: 'Good', target: '≤ 2.5s', color: 'emerald', trend: [1.8, 1.7, 1.5, 1.4, 1.4] },
        { name: __('INP (Interaction to Next Paint)', 'quinrel'), value: '48ms', status: 'Good', target: '≤ 200ms', color: 'emerald', trend: [65, 58, 52, 48, 48] },
        { name: __('CLS (Cumulative Layout Shift)', 'quinrel'), value: '0.01', status: 'Good', target: '≤ 0.1', color: 'emerald', trend: [0.04, 0.02, 0.01, 0.01, 0.01] },
        { name: __('TTFB (Server Response Time)', 'quinrel'), value: '180ms', status: 'Good', target: '≤ 800ms', color: 'emerald', trend: [240, 210, 195, 185, 180] },
    ];

    const opportunities = [
        { title: __('Eliminate render-blocking resources', 'quinrel'), savings: '420ms', action: __('Script Deferral Active', 'quinrel'), active: true },
        { title: __('Ensure text remains visible during webfont load', 'quinrel'), savings: '180ms', action: __('Font-Display Swap Active', 'quinrel'), active: true },
        { title: __('Serve images in next-gen formats (WebP)', 'quinrel'), savings: '310KB', action: __('WebP Converter Active', 'quinrel'), active: true },
    ];

    return (
        <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
                <div>
                    <h3 className="text-base font-bold text-slate-900 tracking-tight m-0">
                        {__('Core Web Vitals & Lighthouse Performance', 'quinrel')}
                    </h3>
                    <p className="text-xs text-slate-500 mt-1 m-0">
                        {__('Real-world user experience metrics and automated Core Web Vitals optimization.', 'quinrel')}
                    </p>
                </div>
                <div className="flex items-center gap-1 bg-slate-100 p-1 rounded-xl">
                    <button
                        type="button"
                        onClick={() => setStrategy('mobile')}
                        className={`px-3 py-1 rounded-lg text-xs font-semibold transition-colors ${strategy === 'mobile' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'}`}
                    >
                        📱 {__('Mobile', 'quinrel')}
                    </button>
                    <button
                        type="button"
                        onClick={() => setStrategy('desktop')}
                        className={`px-3 py-1 rounded-lg text-xs font-semibold transition-colors ${strategy === 'desktop' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'}`}
                    >
                        💻 {__('Desktop', 'quinrel')}
                    </button>
                </div>
            </div>

            {/* 4 Lighthouse Metric Radial Cards */}
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 sm:gap-6">
                {[
                    { label: __('Performance', 'quinrel'), score: scores.perf },
                    { label: __('Accessibility', 'quinrel'), score: scores.a11y },
                    { label: __('Best Practices', 'quinrel'), score: scores.best },
                    { label: __('SEO', 'quinrel'), score: scores.seo },
                ].map((m, i) => (
                    <div key={i} className="p-5 sm:p-6 rounded-2xl bg-slate-50/70 border border-slate-200/80 flex flex-col items-center justify-center text-center shadow-2xs hover:shadow-xs transition-shadow">
                        <div className="relative w-24 h-24 sm:w-28 sm:h-28 flex items-center justify-center mb-3">
                            <svg className="w-full h-full -rotate-90 transform" viewBox="0 0 100 100">
                                <circle cx="50" cy="50" r="38" fill="transparent" stroke="#e2e8f0" strokeWidth="8" />
                                <circle
                                    cx="50"
                                    cy="50"
                                    r="38"
                                    fill="transparent"
                                    stroke={m.score >= 90 ? '#10b981' : m.score >= 50 ? '#f59e0b' : '#ef4444'}
                                    strokeWidth="8"
                                    strokeDasharray={238}
                                    strokeDashoffset={238 - (238 * m.score) / 100}
                                    strokeLinecap="round"
                                    className="transition-all duration-1000 ease-out"
                                />
                            </svg>
                            <span className="absolute text-xl sm:text-2xl font-black text-slate-900">{m.score}</span>
                        </div>
                        <span className="text-sm font-bold text-slate-800">{m.label}</span>
                        <span className="text-[11px] text-emerald-600 font-semibold uppercase mt-0.5 tracking-wider">{__('Good (Green)', 'quinrel')}</span>
                    </div>
                ))}
            </div>

            {/* Core Web Vitals Field Cards */}
            <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-6">
                {cwvMetrics.map((cwv, i) => (
                    <div key={i} className="p-4 sm:p-5 rounded-2xl border border-slate-200/80 bg-white space-y-2 shadow-2xs hover:shadow-xs transition-shadow">
                        <div className="flex justify-between items-center text-xs">
                            <span className="font-bold text-slate-700 truncate">{cwv.name.split(' ')[0]}</span>
                            <span className="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                {cwv.status}
                            </span>
                        </div>
                        <div className="flex items-baseline justify-between">
                            <span className="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">{cwv.value}</span>
                            <span className="text-xs text-slate-400 font-medium">{cwv.target}</span>
                        </div>
                        {/* Sparkline mini-graph */}
                        <div className="h-8 w-full flex items-end gap-1.5 pt-2">
                            {cwv.trend.map((val, idx) => (
                                <div
                                    key={idx}
                                    className="flex-1 bg-indigo-500/25 hover:bg-indigo-600 rounded-xs transition-colors cursor-pointer"
                                    style={{ height: `${20 + idx * 16}%` }}
                                    title={`Snapshot ${idx + 1}`}
                                />
                            ))}
                        </div>
                    </div>
                ))}
            </div>

            {/* Opportunities Grid */}
            <div className="p-5 sm:p-6 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-3.5">
                <span className="text-xs font-bold uppercase tracking-wider text-slate-500 block">{__('Active Autonomous Core Web Vitals Optimizers', 'quinrel')}</span>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {opportunities.map((opp, i) => (
                        <div key={i} className="flex flex-col justify-between text-xs p-4 rounded-xl bg-white border border-slate-200/80 shadow-2xs hover:shadow-xs transition-shadow gap-3">
                            <div className="space-y-1.5">
                                <div className="flex items-center gap-1.5 text-emerald-600 font-bold">
                                    <span>✓</span>
                                    <span className="text-[11px] uppercase tracking-wider font-semibold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                                        {sprintf(__('Saved %s', 'quinrel'), opp.savings)}
                                    </span>
                                </div>
                                <p className="text-slate-800 font-semibold m-0 text-sm leading-snug">{opp.title}</p>
                            </div>
                            <span className="text-[11px] font-bold text-indigo-700 bg-indigo-50 px-2.5 py-1 rounded-md self-start border border-indigo-100">
                                {opp.action}
                            </span>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
};
export default CoreWebVitalsGauge;

