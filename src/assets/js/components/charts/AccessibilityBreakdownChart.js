import React, { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';

export const AccessibilityBreakdownChart = ({ apiBase, nonce, missingAlt, onActionComplete }) => {
    const [isGenerating, setIsGenerating] = useState(false);
    const [statusMessage, setStatusMessage] = useState(null);
    const hasAltCount = typeof missingAlt === 'number' && Number.isFinite(missingAlt);

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
        <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
            <div>
                <h3 className="text-base font-bold text-slate-900 tracking-tight m-0">
                    {__('Accessibility', 'quinrel')}
                </h3>
                <p role="status" className="text-sm text-slate-600 mt-2 m-0">
                    {__('A WCAG score has not been measured on this site.', 'quinrel')}
                </p>
                {hasAltCount && (
                    <p className="text-sm text-slate-700 mt-2 m-0">
                        {sprintf(__('%d images have no alternative text.', 'quinrel'), missingAlt)}
                    </p>
                )}
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

            <div>
                <button
                    type="button"
                    onClick={handleQueueAiAlt}
                    disabled={isGenerating}
                    className="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg shadow-sm transition-colors disabled:opacity-50"
                >
                    {isGenerating ? __('Processing…', 'quinrel') : __('Queue alt text for images', 'quinrel')}
                </button>
            </div>
        </div>
    );
};
export default AccessibilityBreakdownChart;
