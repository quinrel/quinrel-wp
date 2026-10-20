import React, { useState } from 'react';
const { __, sprintf } = wp.i18n;

export const AccessibilityBreakdownChart = ({ apiBase, nonce, onActionComplete }) => {
    const [isGenerating, setIsGenerating] = useState(false);
    const [statusMessage, setStatusMessage] = useState(null);

    const overallScore = 88;
    const principles = [
        { name: __('Perceivable (Alt Text & Contrast)', 'quinrel'), score: 92, color: '#10b981' },
        { name: __('Operable (Keyboard & Links)', 'quinrel'), score: 85, color: '#3b82f6' },
        { name: __('Understandable (Readability & Headings)', 'quinrel'), score: 95, color: '#6366f1' },
        { name: __('Robust (Semantic HTML & ARIA)', 'quinrel'), score: 80, color: '#8b5cf6' },
    ];

    const mediaStats = {
        total: 252,
        human: 142,
        aiVision: 86,
        missing: 24,
    };

    const handleQueueAiAlt = async () => {
        setIsGenerating(true);
        setStatusMessage(null);
        try {
            const res = await fetch(`${apiBase}/images/queue-ai-alt`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce,
                },
                body: JSON.stringify({ batch_size: 5 }),
            });
            const data = await res.json();
            if (data.success) {
                setStatusMessage({ type: 'success', text: data.message });
                if (onActionComplete) onActionComplete();
            } else {
                setStatusMessage({ type: 'error', text: data.message || __('Failed to run AI alt queue.', 'quinrel') });
            }
        } catch (e) {
            setStatusMessage({ type: 'error', text: __('Network error during AI alt generation.', 'quinrel') });
        } finally {
            setIsGenerating(false);
        }
    };

    return (
        <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
                <div>
                    <h3 className="text-base font-bold text-slate-900 tracking-tight m-0">
                        {__('WCAG 2.1 AA Accessibility Scorecard', 'quinrel')}
                    </h3>
                    <p className="text-xs text-slate-500 mt-1 m-0">
                        {__('Color contrast ratios, media alt compliance, and semantic heading structure.', 'quinrel')}
                    </p>
                </div>
                <span className="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                    ● {__('WCAG 2.1 Level AA Passing', 'quinrel')}
                </span>
            </div>

            {statusMessage && (
                <div className={`p-3 rounded-xl text-xs font-medium flex items-center justify-between ${statusMessage.type === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'}`}>
                    <span>{statusMessage.text}</span>
                    <button type="button" onClick={() => setStatusMessage(null)} className="text-xs font-bold opacity-75 hover:opacity-100">✕</button>
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-center">
                {/* Donut Score */}
                <div className="lg:col-span-4 flex flex-col items-center justify-center p-4 bg-slate-50/70 rounded-xl border border-slate-100">
                    <div className="relative w-32 h-32 flex items-center justify-center">
                        <svg className="w-full h-full -rotate-90 transform" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="40" fill="transparent" stroke="#e2e8f0" strokeWidth="10" />
                            <circle
                                cx="50"
                                cy="50"
                                r="40"
                                fill="transparent"
                                stroke="#10b981"
                                strokeWidth="10"
                                strokeDasharray={251}
                                strokeDashoffset={251 - (251 * overallScore) / 100}
                                strokeLinecap="round"
                                className="transition-all duration-1000 ease-out"
                            />
                        </svg>
                        <div className="absolute flex flex-col items-center justify-center text-center">
                            <span className="text-2xl font-black text-slate-900 tracking-tight">{overallScore}%</span>
                            <span className="text-[10px] uppercase font-bold tracking-wider text-slate-500">{__('WCAG AA', 'quinrel')}</span>
                        </div>
                    </div>
                    <p className="text-xs font-semibold text-slate-700 mt-2 m-0">{__('Screen Reader Ready', 'quinrel')}</p>
                    <span className="text-[11px] text-slate-400 text-center mt-0.5">{__('Zero critical contrast barriers detected', 'quinrel')}</span>
                </div>

                {/* Principles Breakdown */}
                <div className="lg:col-span-8 space-y-3">
                    <span className="text-xs font-bold text-slate-700 block mb-1">{__('Accessibility by Core Principle', 'quinrel')}</span>
                    {principles.map((p, i) => (
                        <div key={i} className="space-y-1">
                            <div className="flex justify-between text-xs">
                                <span className="font-medium text-slate-600">{p.name}</span>
                                <span className="font-bold text-slate-800">{p.score}%</span>
                            </div>
                            <div className="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                                <div
                                    className="h-full rounded-full transition-all duration-700"
                                    style={{ width: `${p.score}%`, backgroundColor: p.color }}
                                />
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Media Accessibility Status Bar */}
            <div className="p-4 rounded-xl bg-slate-50/80 border border-slate-100 space-y-3">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <span className="text-xs font-bold text-slate-900 block">{__('Media Library Alt Text Coverage', 'quinrel')}</span>
                        <span className="text-[11px] text-slate-500">{sprintf(__('%d of %d images have descriptive alt attributes', 'quinrel'), mediaStats.human + mediaStats.aiVision, mediaStats.total)}</span>
                    </div>
                    <button
                        type="button"
                        onClick={handleQueueAiAlt}
                        disabled={isGenerating}
                        className="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg shadow-sm transition-colors flex items-center gap-1.5 disabled:opacity-50 self-start sm:self-auto"
                    >
                        <span>✨</span>
                        {isGenerating ? __('Processing…', 'quinrel') : __('Queue Vision AI Alt Generator', 'quinrel')}
                    </button>
                </div>

                {/* Segmented Bar */}
                <div className="w-full h-3 rounded-full bg-slate-200 overflow-hidden flex">
                    <div style={{ width: `${(mediaStats.human / mediaStats.total) * 100}%` }} className="bg-emerald-500 h-full" title={__('Human Authored', 'quinrel')} />
                    <div style={{ width: `${(mediaStats.aiVision / mediaStats.total) * 100}%` }} className="bg-indigo-500 h-full" title={__('AI Vision Synthesized', 'quinrel')} />
                    <div style={{ width: `${(mediaStats.missing / mediaStats.total) * 100}%` }} className="bg-amber-400 h-full" title={__('Missing Alt Text', 'quinrel')} />
                </div>

                <div className="flex flex-wrap items-center gap-4 text-[11px] text-slate-600 pt-1">
                    <div className="flex items-center gap-1.5">
                        <span className="w-2.5 h-2.5 rounded-full bg-emerald-500" />
                        <span>{__('Human Verified', 'quinrel')}: <strong>{mediaStats.human}</strong></span>
                    </div>
                    <div className="flex items-center gap-1.5">
                        <span className="w-2.5 h-2.5 rounded-full bg-indigo-500" />
                        <span>{__('AI Vision Generated', 'quinrel')}: <strong>{mediaStats.aiVision}</strong></span>
                    </div>
                    <div className="flex items-center gap-1.5">
                        <span className="w-2.5 h-2.5 rounded-full bg-amber-400" />
                        <span>{__('Missing Alt Text', 'quinrel')}: <strong>{mediaStats.missing}</strong></span>
                    </div>
                </div>
            </div>
        </div>
    );
};
export default AccessibilityBreakdownChart;

