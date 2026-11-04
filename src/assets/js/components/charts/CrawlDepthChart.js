import React, { useState } from 'react';
import { __ } from '@wordpress/i18n';

export const CrawlDepthChart = ({ apiBase, nonce, onActionComplete }) => {
    const [isHealing, setIsHealing] = useState(false);
    const [isCollapsing, setIsCollapsing] = useState(false);
    const [statusMessage, setStatusMessage] = useState(null);

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
            <div className="pb-4 border-b border-slate-100">
                <h3 className="text-base font-bold text-slate-900 tracking-tight m-0">
                    {__('Site crawl depth', 'quinrel')}
                </h3>
                <p role="status" className="text-sm text-slate-600 mt-2 m-0">
                    {__('Click depth has not been measured on this site.', 'quinrel')}
                </p>
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

            <div className="p-4 rounded-xl bg-slate-50 border border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <p className="text-xs text-slate-600 m-0">
                    {__('These actions update redirects and in-content links. They do not measure click depth.', 'quinrel')}
                </p>
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={handleAutoHealLinks}
                        disabled={isHealing}
                        className="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg shadow-sm transition-colors disabled:opacity-50"
                    >
                        {isHealing ? __('Healing…', 'quinrel') : __('Rewrite links to redirects', 'quinrel')}
                    </button>
                    <button
                        type="button"
                        onClick={handleCollapseChains}
                        disabled={isCollapsing}
                        className="px-3 py-1.5 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 text-xs font-medium rounded-lg shadow-sm transition-colors disabled:opacity-50"
                    >
                        {isCollapsing ? __('Collapsing…', 'quinrel') : __('Collapse redirect chains', 'quinrel')}
                    </button>
                </div>
            </div>
        </div>
    );
};
export default CrawlDepthChart;
