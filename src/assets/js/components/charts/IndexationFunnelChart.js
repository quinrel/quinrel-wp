import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';

export const IndexationFunnelChart = ({ apiBase, nonce, onActionComplete }) => {
    const [payload, setPayload] = useState(null);
    const [isLoading, setIsLoading] = useState(true);
    const [loadFailed, setLoadFailed] = useState(false);
    const [isPushing, setIsPushing] = useState(false);
    const [statusMessage, setStatusMessage] = useState(null);

    const fetchFunnelData = async () => {
        setIsLoading(true);
        try {
            const res = await fetch(`${apiBase}/indexing/funnel-data`, {
                headers: { 'X-WP-Nonce': nonce },
            });
            const data = await res.json();
            if (res.ok && Array.isArray(data?.funnel)) {
                setPayload(data);
                setLoadFailed(false);
            } else {
                setPayload(null);
                setLoadFailed(true);
            }
        } catch (e) {
            setPayload(null);
            setLoadFailed(true);
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        fetchFunnelData();
    }, []);

    const handleBulkPush = async () => {
        setIsPushing(true);
        setStatusMessage(null);
        try {
            const res = await fetch(`${apiBase}/indexing/bulk-push`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce,
                },
            });
            const data = await res.json();
            if (res.ok && data.success) {
                setStatusMessage({ type: 'success', text: data.message });
                fetchFunnelData();
                if (onActionComplete) onActionComplete();
            } else {
                setStatusMessage({ type: 'error', text: data.message || __('IndexNow did not accept the URLs.', 'quinrel') });
            }
        } catch (e) {
            setStatusMessage({ type: 'error', text: __('Network error during IndexNow submit.', 'quinrel') });
        } finally {
            setIsPushing(false);
        }
    };

    const stages = Array.isArray(payload?.funnel) ? payload.funnel : [];
    const coverage = Array.isArray(payload?.coverage_groups) ? payload.coverage_groups : [];

    return (
        <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
                <div>
                    <h3 className="text-base font-bold text-slate-900 tracking-tight m-0">
                        {__('Indexation funnel', 'quinrel')}
                    </h3>
                    <p className="text-xs text-slate-500 mt-1 m-0">
                        {__('Published and sitemap counts come from this site. Google stages appear only after index status is stored.', 'quinrel')}
                    </p>
                </div>
                <button
                    type="button"
                    onClick={handleBulkPush}
                    disabled={isPushing}
                    className="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg shadow-sm transition-colors disabled:opacity-50"
                >
                    {isPushing ? __('Submitting…', 'quinrel') : __('Submit to IndexNow', 'quinrel')}
                </button>
            </div>

            {statusMessage && (
                <div role="status" className={`p-3 rounded-xl text-xs font-medium flex items-center justify-between ${statusMessage.type === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'}`}>
                    <span>{statusMessage.text}</span>
                    <button type="button" onClick={() => setStatusMessage(null)} className="text-xs font-bold opacity-75 hover:opacity-100">
                        <span aria-hidden="true">✕</span>
                        <span className="screen-reader-text">{__('Dismiss', 'quinrel')}</span>
                    </button>
                </div>
            )}

            {isLoading && (
                <p role="status" className="text-sm text-slate-600 m-0">{__('Loading indexation counts.', 'quinrel')}</p>
            )}
            {loadFailed && (
                <p role="alert" className="text-sm text-amber-800 m-0">{__('Indexation counts could not be loaded.', 'quinrel')}</p>
            )}

            {stages.length > 0 && (
                <div className="space-y-3">
                    {stages.map((stage, index) => {
                        const measured = stage.measured === true;
                        const width = measured ? Math.min(100, Math.max(0, Number(stage.percent) || 0)) : 0;
                        return (
                            <div key={`${stage.stage || 'stage'}-${index}`}>
                                <div className="flex justify-between items-center text-xs mb-1 gap-3">
                                    <span className="font-semibold text-slate-700">{stage.stage}</span>
                                    {measured ? (
                                        <span className="font-bold text-slate-900">
                                            {Number(stage.count) || 0}
                                            <span className="text-slate-400 font-normal"> ({Number(stage.percent) || 0}%)</span>
                                        </span>
                                    ) : (
                                        <span className="font-medium text-slate-500">{__('Not measured', 'quinrel')}</span>
                                    )}
                                </div>
                                <div className="w-full bg-slate-100 rounded-lg h-3 overflow-hidden">
                                    <div
                                        className="h-full rounded-lg"
                                        style={{ width: `${width}%`, backgroundColor: measured ? (stage.color || '#64748b') : 'transparent' }}
                                    />
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}

            <div className="p-4 rounded-xl bg-slate-50/80 border border-slate-100">
                <span className="text-xs font-bold text-slate-700 block mb-2">{__('Search Console coverage', 'quinrel')}</span>
                {payload?.coverage_measured === true && coverage.length > 0 ? (
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        {coverage.map((group, index) => (
                            <div key={`${group.coverage_state || 'state'}-${index}`} className="p-2.5 rounded-lg bg-white border border-slate-200/80">
                                <span className="text-[11px] text-slate-500 block">{group.coverage_state || __('Unknown', 'quinrel')}</span>
                                <span className="text-sm font-bold text-slate-800 block mt-0.5">{Number(group.count) || 0}</span>
                            </div>
                        ))}
                    </div>
                ) : (
                    <p role="status" className="text-sm text-slate-600 m-0">
                        {__('Search Console coverage has not been measured on this site.', 'quinrel')}
                    </p>
                )}
            </div>
        </div>
    );
};
export default IndexationFunnelChart;
