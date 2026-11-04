import React, { useEffect, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';

const SCORE_CARDS = [
    { key: 'performance', label: __('Performance', 'quinrel') },
    { key: 'accessibility', label: __('Accessibility', 'quinrel') },
    { key: 'best_practices', label: __('Best Practices', 'quinrel') },
    { key: 'seo', label: __('SEO', 'quinrel') },
];

const METRICS = {
    lcp: { name: __('LCP', 'quinrel'), target: __('≤ 2.5s', 'quinrel') },
    inp: { name: __('INP', 'quinrel'), target: __('≤ 200ms', 'quinrel') },
    cls: { name: __('CLS', 'quinrel'), target: __('≤ 0.1', 'quinrel') },
    ttfb: { name: __('TTFB', 'quinrel'), target: __('≤ 800ms', 'quinrel') },
};

const ratingText = (rating) => {
    if (rating === 'good') {
        return __('Good', 'quinrel');
    }
    if (rating === 'needs-improvement') {
        return __('Needs improvement', 'quinrel');
    }
    if (rating === 'poor') {
        return __('Poor', 'quinrel');
    }
    return __('No result', 'quinrel');
};

const scoreRating = (score) => {
    if (score === null || score === undefined) {
        return '';
    }
    if (score >= 90) {
        return 'good';
    }
    if (score >= 50) {
        return 'needs-improvement';
    }
    return 'poor';
};

const ratingClass = (rating) => {
    if (rating === 'good') {
        return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    }
    if (rating === 'needs-improvement') {
        return 'bg-amber-50 text-amber-800 border-amber-200';
    }
    if (rating === 'poor') {
        return 'bg-red-50 text-red-700 border-red-200';
    }
    return 'bg-slate-50 text-slate-600 border-slate-200';
};

const strokeFor = (rating) => {
    if (rating === 'good') {
        return '#10b981';
    }
    if (rating === 'needs-improvement') {
        return '#f59e0b';
    }
    if (rating === 'poor') {
        return '#ef4444';
    }
    return '#cbd5e1';
};

const savingsText = (opportunity) => {
    if (opportunity.savings_ms) {
        return sprintf(__('Google estimates %d ms', 'quinrel'), opportunity.savings_ms);
    }
    if (opportunity.savings_bytes) {
        return sprintf(__('Google estimates %d KB', 'quinrel'), Math.max(1, Math.round(opportunity.savings_bytes / 1024)));
    }
    return '';
};

const homeUrl = () => (window.quinrelAdminConfig && window.quinrelAdminConfig.homeUrl) || '';

export const CoreWebVitalsGauge = ({ apiBase, nonce }) => {
    const [strategy, setStrategy] = useState('mobile');
    const [url, setUrl] = useState(homeUrl);
    const [report, setReport] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    const load = async (nextStrategy, nextUrl, force) => {
        setLoading(true);
        setError('');
        const params = new URLSearchParams({ strategy: nextStrategy });
        if (nextUrl) {
            params.set('url', nextUrl);
        }
        if (force) {
            params.set('force', '1');
        }
        try {
            const res = await fetch(`${apiBase}/analytics/pagespeed?${params}`, {
                headers: { 'X-WP-Nonce': nonce },
            });
            const data = await res.json();
            if (!res.ok) {
                setReport(null);
                setError(data.message || __('Google PageSpeed did not return an audit.', 'quinrel'));
                return;
            }
            setReport(data);
        } catch (e) {
            setReport(null);
            setError(__('The PageSpeed check could not be completed.', 'quinrel'));
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        load(strategy, url, false);
    }, [strategy]);

    const ready = report && report.status === 'ready' && report.scores;
    const scores = ready ? report.scores : {};

    return (
        <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-6" aria-busy={loading}>
            <div className="flex flex-col gap-4 pb-4 border-b border-slate-100">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h3 className="text-base font-bold text-slate-900 tracking-tight m-0">
                            {__('Core Web Vitals', 'quinrel')}
                        </h3>
                        <p className="text-xs text-slate-500 mt-1 m-0">
                            {__('Scores come from a Google PageSpeed Insights audit. Nothing is shown until that audit returns.', 'quinrel')}
                        </p>
                    </div>
                    <div className="flex items-center gap-1 bg-slate-100 p-1 rounded-xl" role="group" aria-label={__('Device', 'quinrel')}>
                        <button
                            type="button"
                            onClick={() => setStrategy('mobile')}
                            aria-pressed={strategy === 'mobile'}
                            className={`px-3 py-1 rounded-lg text-xs font-semibold transition-colors ${strategy === 'mobile' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'}`}
                        >
                            {__('Mobile', 'quinrel')}
                        </button>
                        <button
                            type="button"
                            onClick={() => setStrategy('desktop')}
                            aria-pressed={strategy === 'desktop'}
                            className={`px-3 py-1 rounded-lg text-xs font-semibold transition-colors ${strategy === 'desktop' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'}`}
                        >
                            {__('Desktop', 'quinrel')}
                        </button>
                    </div>
                </div>
                <form
                    className="flex flex-col sm:flex-row gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        load(strategy, url, true);
                    }}
                >
                    <label className="sr-only" htmlFor={`quinrel-psi-url-${strategy}`}>{__('URL to check', 'quinrel')}</label>
                    <input
                        id={`quinrel-psi-url-${strategy}`}
                        type="url"
                        required
                        value={url}
                        onChange={(event) => setUrl(event.target.value)}
                        className="flex-1 text-sm px-3 py-2 bg-white border border-slate-300 rounded-lg"
                    />
                    <button
                        type="submit"
                        disabled={loading}
                        className="px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                    >
                        {loading ? __('Checking…', 'quinrel') : __('Run PageSpeed check', 'quinrel')}
                    </button>
                </form>
            </div>

            {error && <p className="text-sm text-red-700 m-0" role="alert">{error}</p>}

            {!ready && !error && (
                <p className="text-sm text-slate-600 m-0" role="status">
                    {loading
                        ? __('Loading the saved audit…', 'quinrel')
                        : __('No audit yet. Google cannot reach a local address. Enter a public URL to run a check. A PageSpeed Insights API key in Settings makes repeated checks more reliable.', 'quinrel')}
                </p>
            )}

            {ready && (
                <>
                    <p className="text-xs text-slate-500 m-0">
                        {sprintf(
                            /* translators: 1: checked URL, 2: time the audit was stored. */
                            __('Checked %1$s at %2$s.', 'quinrel'),
                            report.url,
                            report.fetched_at
                        )}
                    </p>
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 sm:gap-6">
                        {SCORE_CARDS.map((card) => {
                            const score = scores[card.key];
                            const rating = scoreRating(score);
                            const shown = score === null || score === undefined ? '—' : score;
                            const offset = score === null || score === undefined ? 238 : 238 - (238 * score) / 100;
                            return (
                                <div key={card.key} className="p-5 sm:p-6 rounded-2xl bg-slate-50/70 border border-slate-200/80 flex flex-col items-center justify-center text-center">
                                    <div className="relative w-24 h-24 sm:w-28 sm:h-28 flex items-center justify-center mb-3">
                                        <svg className="w-full h-full -rotate-90 transform" viewBox="0 0 100 100" aria-hidden="true">
                                            <circle cx="50" cy="50" r="38" fill="transparent" stroke="#e2e8f0" strokeWidth="8" />
                                            <circle
                                                cx="50"
                                                cy="50"
                                                r="38"
                                                fill="transparent"
                                                stroke={strokeFor(rating)}
                                                strokeWidth="8"
                                                strokeDasharray={238}
                                                strokeDashoffset={offset}
                                                strokeLinecap="round"
                                            />
                                        </svg>
                                        <span className="absolute text-xl sm:text-2xl font-black text-slate-900">{shown}</span>
                                    </div>
                                    <span className="text-sm font-bold text-slate-800">{card.label}</span>
                                    <span className={`text-[11px] font-semibold uppercase mt-0.5 tracking-wider px-2 py-0.5 rounded border ${ratingClass(rating)}`}>
                                        {ratingText(rating)}
                                    </span>
                                </div>
                            );
                        })}
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                        {(report.metrics || []).map((metric) => {
                            const meta = METRICS[metric.id] || { name: metric.id, target: '' };
                            const points = (report.series && report.series[metric.id]) || [];
                            const peak = points.reduce((max, point) => Math.max(max, point), 0);
                            return (
                                <div key={metric.id} className="p-4 rounded-2xl border border-slate-200/80 bg-white space-y-2">
                                    <div className="flex justify-between items-center text-xs gap-2">
                                        <span className="font-bold text-slate-700">{meta.name}</span>
                                        <span className={`px-2 py-0.5 rounded text-[10px] font-bold border ${ratingClass(metric.rating)}`}>
                                            {metric.value ? ratingText(metric.rating) : __('Not in this run', 'quinrel')}
                                        </span>
                                    </div>
                                    <div className="flex items-baseline justify-between">
                                        <span className="text-2xl font-black text-slate-900 tracking-tight">{metric.value || '—'}</span>
                                        <span className="text-xs text-slate-400 font-medium">{meta.target}</span>
                                    </div>
                                    <p className="text-[11px] text-slate-500 m-0">
                                        {metric.origin === 'field' ? __('Field data', 'quinrel') : metric.origin === 'lab' ? __('Lab data', 'quinrel') : ''}
                                    </p>
                                    {points.length >= 2 && (
                                        <div className="h-8 w-full flex items-end gap-1" aria-hidden="true">
                                            {points.map((point, index) => (
                                                <div
                                                    key={`${metric.id}-${index}`}
                                                    className="flex-1 bg-indigo-500/25 rounded-xs"
                                                    style={{ height: `${Math.max(12, peak > 0 ? (point / peak) * 100 : 12)}%` }}
                                                />
                                            ))}
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>

                    <div className="p-5 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-3">
                        <span className="text-xs font-bold uppercase tracking-wider text-slate-500 block">{__('Opportunities from this audit', 'quinrel')}</span>
                        {(report.opportunities || []).length === 0 && (
                            <p className="text-sm text-slate-600 m-0">{__('This audit did not report a time or size saving.', 'quinrel')}</p>
                        )}
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            {(report.opportunities || []).map((opportunity) => (
                                <div key={opportunity.id} className="text-sm p-4 rounded-xl bg-white border border-slate-200/80 space-y-1">
                                    <p className="text-slate-800 font-semibold m-0">{opportunity.title}</p>
                                    {savingsText(opportunity) !== '' && (
                                        <p className="text-xs text-slate-500 m-0">{savingsText(opportunity)}</p>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                </>
            )}
        </div>
    );
};

export default CoreWebVitalsGauge;
