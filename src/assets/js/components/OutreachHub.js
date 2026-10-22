import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { TextControl, TextareaControl, SelectControl, Button, Spinner, Notice } from '@wordpress/components';
import {
    LinkIcon,
    CheckCircleIcon,
    AlertCircleIcon,
    ExternalLinkIcon,
} from './QuinrelIcons';

export const OutreachHub = ({ quinrelAdminConfig }) => {
    const [activeSubTab, setActiveSubTab] = useState('pipeline');
    const [statusNotice, setStatusNotice] = useState(null);

    // ── Pipeline State ────────────────────────────────────────────────────────
    const [prospects, setProspects] = useState([]);
    const [isLoadingProspects, setIsLoadingProspects] = useState(false);
    const [showAddModal, setShowAddModal] = useState(false);
    const [isSavingProspect, setIsSavingProspect] = useState(false);
    const [newProspect, setNewProspect] = useState({
        domain: '',
        target_url: '',
        contact_name: '',
        contact_email: '',
        pitch_type: 'guest_post',
        stage: 'prospect',
        da_tier: 'medium',
        notes: '',
    });

    // ── Broken Backlinks State ────────────────────────────────────────────────
    const [brokenLinks, setBrokenLinks] = useState([]);
    const [isLoadingBroken, setIsLoadingBroken] = useState(false);
    const [redirectTargetMap, setRedirectTargetMap] = useState({});
    const [isCreatingRedirect, setIsCreatingRedirect] = useState(false);

    // ── Anchor Diversity State ────────────────────────────────────────────────
    const [anchorPostId, setAnchorPostId] = useState('');
    const [anchorAnalysis, setAnchorAnalysis] = useState(null);
    const [isLoadingAnchors, setIsLoadingAnchors] = useState(false);

    const loadProspects = async () => {
        setIsLoadingProspects(true);
        try {
            const res = await fetch(`${quinrelAdminConfig.apiUrl}/outreach/prospects`, {
                headers: { 'X-WP-Nonce': quinrelAdminConfig.nonce }
            });
            const data = await res.json();
            if (res.ok) {
                setProspects(data.prospects || []);
            }
        } catch (e) {
            console.error(e);
        } finally {
            setIsLoadingProspects(false);
        }
    };

    const loadBrokenBacklinks = async () => {
        setIsLoadingBroken(true);
        try {
            const res = await fetch(`${quinrelAdminConfig.apiUrl}/outreach/broken-backlinks`, {
                headers: { 'X-WP-Nonce': quinrelAdminConfig.nonce }
            });
            const data = await res.json();
            if (res.ok) {
                setBrokenLinks(data.opportunities || []);
            }
        } catch (e) {
            console.error(e);
        } finally {
            setIsLoadingBroken(false);
        }
    };

    const analyzeAnchors = async () => {
        if (!anchorPostId) return;
        setIsLoadingAnchors(true);
        try {
            const res = await fetch(`${quinrelAdminConfig.apiUrl}/outreach/anchor-diversity?post_id=${anchorPostId}`, {
                headers: { 'X-WP-Nonce': quinrelAdminConfig.nonce }
            });
            const data = await res.json();
            if (res.ok) {
                setAnchorAnalysis(data);
            }
        } catch (e) {
            console.error(e);
        } finally {
            setIsLoadingAnchors(false);
        }
    };

    useEffect(() => {
        if (activeSubTab === 'pipeline') {
            loadProspects();
        } else if (activeSubTab === 'broken_backlinks') {
            loadBrokenBacklinks();
        }
    }, [activeSubTab]);

    const handleSaveProspect = async () => {
        if (!newProspect.domain) {
            setStatusNotice({ type: 'error', text: __('Domain is required.', 'quinrel') });
            return;
        }
        setIsSavingProspect(true);
        try {
            const res = await fetch(`${quinrelAdminConfig.apiUrl}/outreach/prospects`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': quinrelAdminConfig.nonce
                },
                body: JSON.stringify(newProspect)
            });
            const data = await res.json();
            if (res.ok && data.success) {
                setStatusNotice({ type: 'success', text: __('Prospect added to pipeline.', 'quinrel') });
                setShowAddModal(false);
                setNewProspect({
                    domain: '',
                    target_url: '',
                    contact_name: '',
                    contact_email: '',
                    pitch_type: 'guest_post',
                    stage: 'prospect',
                    da_tier: 'medium',
                    notes: '',
                });
                loadProspects();
            } else {
                setStatusNotice({ type: 'error', text: data.message || __('Failed to save prospect.', 'quinrel') });
            }
        } catch (e) {
            setStatusNotice({ type: 'error', text: __('Request failed.', 'quinrel') });
        } finally {
            setIsSavingProspect(false);
        }
    };

    const updateProspectStage = async (id, stage) => {
        try {
            const target = prospects.find(p => parseInt(p.id, 10) === parseInt(id, 10));
            if (!target) return;
            const updated = { ...target, stage };
            await fetch(`${quinrelAdminConfig.apiUrl}/outreach/prospects`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': quinrelAdminConfig.nonce
                },
                body: JSON.stringify(updated)
            });
            loadProspects();
        } catch (e) {
            console.error(e);
        }
    };

    const handleDeleteProspect = async (id) => {
        if (!window.confirm(__('Are you sure you want to delete this prospect?', 'quinrel'))) return;
        try {
            await fetch(`${quinrelAdminConfig.apiUrl}/outreach/prospects/${id}`, {
                method: 'DELETE',
                headers: { 'X-WP-Nonce': quinrelAdminConfig.nonce }
            });
            loadProspects();
        } catch (e) {
            console.error(e);
        }
    };

    const handleExportCsv = async () => {
        try {
            const res = await fetch(`${quinrelAdminConfig.apiUrl}/outreach/export`, {
                headers: { 'X-WP-Nonce': quinrelAdminConfig.nonce }
            });
            const data = await res.json();
            if (res.ok && data.csv) {
                const blob = new Blob([data.csv], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = data.filename || 'quinrel-outreach.csv';
                a.click();
                URL.revokeObjectURL(url);
            }
        } catch (e) {
            console.error(e);
        }
    };

    const handleReclaimRedirect = async (brokenItem) => {
        const target = redirectTargetMap[brokenItem.id];
        if (!target) {
            alert(__('Please enter a destination URL to redirect this broken backlink.', 'quinrel'));
            return;
        }
        setIsCreatingRedirect(true);
        try {
            const res = await fetch(`${quinrelAdminConfig.apiUrl}/errors/404/create-redirect`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': quinrelAdminConfig.nonce
                },
                body: JSON.stringify({
                    id: brokenItem.id,
                    target: target,
                    code: 301
                })
            });
            const data = await res.json();
            if (res.ok && data.success) {
                setStatusNotice({ type: 'success', text: __('301 Redirect created! Broken backlink juice reclaimed.', 'quinrel') });
                loadBrokenBacklinks();
            } else {
                setStatusNotice({ type: 'error', text: data.message || __('Failed to create redirect.', 'quinrel') });
            }
        } catch (e) {
            setStatusNotice({ type: 'error', text: __('Request failed.', 'quinrel') });
        } finally {
            setIsCreatingRedirect(false);
        }
    };

    const stages = [
        { id: 'prospect', label: __('Prospects', 'quinrel'), bg: '#f1f5f9', border: '#cbd5e1' },
        { id: 'contacted', label: __('Contacted', 'quinrel'), bg: '#eff6ff', border: '#bfdbfe' },
        { id: 'replied', label: __('Replied / In Negotiation', 'quinrel'), bg: '#fef3c7', border: '#fde68a' },
        { id: 'earned', label: __('Earned Links', 'quinrel'), bg: '#f0fdf4', border: '#bbf7d0' },
        { id: 'rejected', label: __('Declined / Archived', 'quinrel'), bg: '#f8fafc', border: '#e2e8f0' },
    ];

    return (
        <div className="space-y-6">
            {/* ── Sub Navigation Tabs ────────────────────────────────────────── */}
            <div className="flex flex-wrap gap-2 border-b border-slate-200 pb-3">
                <button
                    type="button"
                    onClick={() => setActiveSubTab('pipeline')}
                    className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors ${
                        activeSubTab === 'pipeline'
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'
                    }`}
                >
                    <LinkIcon className="w-4 h-4" />
                    {__('Outreach Pipeline & CRM', 'quinrel')}
                </button>
                <button
                    type="button"
                    onClick={() => setActiveSubTab('broken_backlinks')}
                    className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors ${
                        activeSubTab === 'broken_backlinks'
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'
                    }`}
                >
                    <ExternalLinkIcon className="w-4 h-4" />
                    {__('Broken Backlink Reclaimer', 'quinrel')}
                </button>
                <button
                    type="button"
                    onClick={() => setActiveSubTab('anchors')}
                    className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors ${
                        activeSubTab === 'anchors'
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'bg-white text-slate-600 hover:bg-slate-100 border border-slate-200'
                    }`}
                >
                    <AlertCircleIcon className="w-4 h-4" />
                    {__('Anchor Text Diversity Monitor', 'quinrel')}
                </button>
            </div>

            {statusNotice && (
                <Notice
                    status={statusNotice.type}
                    isDismissible
                    onDismiss={() => setStatusNotice(null)}
                >
                    {statusNotice.text}
                </Notice>
            )}

            {/* ── TAB 1: OUTREACH PIPELINE ───────────────────────────────────── */}
            {activeSubTab === 'pipeline' && (
                <div className="space-y-6">
                    <div className="flex flex-wrap items-center justify-between gap-4 bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                        <div>
                            <h3 className="text-sm font-bold text-slate-900">{__('Link Building Pipeline & Prospect CRM', 'quinrel')}</h3>
                            <p className="text-xs text-slate-500 mt-0.5">
                                {__('Manage editorial outreach campaigns, pitch guest posts, claim unlinked brand mentions, and track earned links.', 'quinrel')}
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            <Button isSecondary onClick={handleExportCsv} style={{ fontSize: '12px' }}>
                                {__('Export CSV', 'quinrel')}
                            </Button>
                            <Button isPrimary onClick={() => setShowAddModal(!showAddModal)} style={{ fontSize: '12px' }}>
                                {showAddModal ? __('Close Form', 'quinrel') : __('+ Add Target Prospect', 'quinrel')}
                            </Button>
                        </div>
                    </div>

                    {showAddModal && (
                        <div className="bg-slate-50 p-5 rounded-xl border border-slate-200 space-y-4">
                            <h4 className="text-xs font-bold text-slate-800 uppercase tracking-wider">{__('New Outreach Prospect', 'quinrel')}</h4>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <TextControl
                                    label={__('Prospect Domain', 'quinrel')}
                                    value={newProspect.domain}
                                    placeholder="e.g. forbes.com or searchenginejournal.com"
                                    onChange={(v) => setNewProspect({ ...newProspect, domain: v })}
                                />
                                <TextControl
                                    label={__('Our Target Post URL to Promote', 'quinrel')}
                                    value={newProspect.target_url}
                                    placeholder="https://mysite.com/pillar-guide"
                                    onChange={(v) => setNewProspect({ ...newProspect, target_url: v })}
                                />
                            </div>
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <TextControl
                                    label={__('Contact Name / Editor', 'quinrel')}
                                    value={newProspect.contact_name}
                                    placeholder="Jane Doe"
                                    onChange={(v) => setNewProspect({ ...newProspect, contact_name: v })}
                                />
                                <TextControl
                                    label={__('Contact Email', 'quinrel')}
                                    value={newProspect.contact_email}
                                    placeholder="editor@prospect.com"
                                    onChange={(v) => setNewProspect({ ...newProspect, contact_email: v })}
                                />
                                <SelectControl
                                    label={__('Pitch Type', 'quinrel')}
                                    value={newProspect.pitch_type}
                                    options={[
                                        { label: __('Guest Post / Contribution', 'quinrel'), value: 'guest_post' },
                                        { label: __('Unlinked Brand Mention Claim', 'quinrel'), value: 'unlinked_mention' },
                                        { label: __('Broken Link Replacement', 'quinrel'), value: 'broken_link' },
                                        { label: __('Resource Page Addition', 'quinrel'), value: 'resource_page' },
                                    ]}
                                    onChange={(v) => setNewProspect({ ...newProspect, pitch_type: v })}
                                />
                            </div>
                            <TextareaControl
                                label={__('Pitch Notes & Context', 'quinrel')}
                                value={newProspect.notes}
                                placeholder={__('Pitch angle, recent published article to reference, or draft angle.', 'quinrel')}
                                rows={2}
                                onChange={(v) => setNewProspect({ ...newProspect, notes: v })}
                            />
                            <div className="flex justify-end gap-2">
                                <Button isSecondary onClick={() => setShowAddModal(false)}>{__('Cancel', 'quinrel')}</Button>
                                <Button isPrimary isBusy={isSavingProspect} onClick={handleSaveProspect}>
                                    {__('Add Prospect', 'quinrel')}
                                </Button>
                            </div>
                        </div>
                    )}

                    {isLoadingProspects ? (
                        <div className="flex items-center justify-center p-12">
                            <Spinner />
                        </div>
                    ) : prospects.length === 0 ? (
                        <div className="p-8 text-center bg-white rounded-xl border border-slate-200">
                            <LinkIcon className="w-8 h-8 mx-auto text-slate-400 mb-2" />
                            <h4 className="text-sm font-semibold text-slate-800">{__('No Outreach Prospects Yet', 'quinrel')}</h4>
                            <p className="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                                {__('Click "+ Add Target Prospect" to begin tracking guest posts, brand mentions, and link outreach.', 'quinrel')}
                            </p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 md:grid-cols-5 gap-3">
                            {stages.map((st) => {
                                const stageProspects = prospects.filter(p => p.stage === st.id);
                                return (
                                    <div
                                        key={st.id}
                                        style={{ background: st.bg, borderColor: st.border }}
                                        className="rounded-xl border p-3 flex flex-col gap-2 min-h-[300px]"
                                    >
                                        <div className="flex items-center justify-between pb-2 border-b border-slate-200/60">
                                            <span className="text-xs font-bold text-slate-700">{st.label}</span>
                                            <span className="text-xs px-2 py-0.5 rounded-full bg-white font-semibold text-slate-600 shadow-xs">
                                                {stageProspects.length}
                                            </span>
                                        </div>
                                        <div className="space-y-2 flex-1 overflow-y-auto">
                                            {stageProspects.map((p) => (
                                                <div key={p.id} className="bg-white p-2.5 rounded-lg border border-slate-200 shadow-xs space-y-1.5">
                                                    <div className="flex items-start justify-between">
                                                        <span className="text-xs font-bold text-slate-900 break-all">{p.domain}</span>
                                                        <button
                                                            onClick={() => handleDeleteProspect(p.id)}
                                                            className="text-slate-400 hover:text-rose-600 text-xs ml-1"
                                                            title={__('Delete', 'quinrel')}
                                                        >
                                                            ×
                                                        </button>
                                                    </div>
                                                    {p.contact_name && (
                                                        <div className="text-[11px] text-slate-500">
                                                            {p.contact_name} {p.contact_email ? `(${p.contact_email})` : ''}
                                                        </div>
                                                    )}
                                                    <div className="text-[10px] text-indigo-600 font-medium">
                                                        {p.pitch_type.replace('_', ' ')}
                                                    </div>
                                                    {p.notes && (
                                                        <div className="text-[11px] text-slate-600 italic line-clamp-2">
                                                            {p.notes}
                                                        </div>
                                                    )}
                                                    <div className="pt-1.5 border-t border-slate-100 flex justify-between items-center">
                                                        <select
                                                            value={p.stage}
                                                            onChange={(e) => updateProspectStage(p.id, e.target.value)}
                                                            className="text-[10px] bg-slate-50 border border-slate-200 rounded px-1.5 py-0.5"
                                                        >
                                                            <option value="prospect">{__('Prospect', 'quinrel')}</option>
                                                            <option value="contacted">{__('Contacted', 'quinrel')}</option>
                                                            <option value="replied">{__('Replied', 'quinrel')}</option>
                                                            <option value="earned">{__('Earned', 'quinrel')}</option>
                                                            <option value="rejected">{__('Declined', 'quinrel')}</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            )}

            {/* ── TAB 2: BROKEN BACKLINK RECLAIMER ───────────────────────────── */}
            {activeSubTab === 'broken_backlinks' && (
                <div className="space-y-6">
                    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                        <h3 className="text-sm font-bold text-slate-900">{__('Broken Backlink Reclaimer', 'quinrel')}</h3>
                        <p className="text-xs text-slate-500 mt-0.5">
                            {__('Scans 404 access logs for visits originating from external websites. Reclaim lost link equity by creating instant 301 redirects to active pages.', 'quinrel')}
                        </p>
                    </div>

                    {isLoadingBroken ? (
                        <div className="flex items-center justify-center p-12">
                            <Spinner />
                        </div>
                    ) : brokenLinks.length === 0 ? (
                        <div className="p-8 text-center bg-white rounded-xl border border-slate-200">
                            <CheckCircleIcon className="w-8 h-8 mx-auto text-emerald-500 mb-2" />
                            <h4 className="text-sm font-semibold text-slate-800">{__('No Broken External Backlinks Detected', 'quinrel')}</h4>
                            <p className="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                                {__('When third-party websites link to URLs that return 404s on your site, Quinrel flags them here so you can redirect them in one click.', 'quinrel')}
                            </p>
                        </div>
                    ) : (
                        <div className="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
                            <table className="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr className="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold">
                                        <th className="p-3">{__('Referring Website', 'quinrel')}</th>
                                        <th className="p-3">{__('Broken URL on This Site', 'quinrel')}</th>
                                        <th className="p-3">{__('Hits', 'quinrel')}</th>
                                        <th className="p-3">{__('Reclaim Destination (Target URL)', 'quinrel')}</th>
                                        <th className="p-3 text-right">{__('Action', 'quinrel')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {brokenLinks.map((item) => (
                                        <tr key={item.id} className="border-b border-slate-100 hover:bg-slate-50">
                                            <td className="p-3">
                                                <a href={item.referrer} target="_blank" rel="noopener noreferrer" className="font-semibold text-indigo-600 hover:underline flex items-center gap-1">
                                                    {item.referrer_host}
                                                    <ExternalLinkIcon className="w-3 h-3 text-slate-400" />
                                                </a>
                                            </td>
                                            <td className="p-3 font-mono text-slate-700">{item.uri}</td>
                                            <td className="p-3">
                                                <span className="px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 font-bold border border-rose-200">
                                                    {item.hits}
                                                </span>
                                            </td>
                                            <td className="p-3">
                                                <input
                                                    type="text"
                                                    placeholder="/target-page"
                                                    value={redirectTargetMap[item.id] || ''}
                                                    onChange={(e) => setRedirectTargetMap({ ...redirectTargetMap, [item.id]: e.target.value })}
                                                    className="w-full text-xs px-2.5 py-1 border border-slate-300 rounded focus:ring-1 focus:ring-indigo-500"
                                                />
                                            </td>
                                            <td className="p-3 text-right">
                                                <Button
                                                    isPrimary
                                                    isBusy={isCreatingRedirect}
                                                    onClick={() => handleReclaimRedirect(item)}
                                                    style={{ fontSize: '11px', padding: '4px 8px' }}
                                                >
                                                    {__('Create 301', 'quinrel')}
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            )}

            {/* ── TAB 3: ANCHOR DIVERSITY ────────────────────────────────────── */}
            {activeSubTab === 'anchors' && (
                <div className="space-y-6">
                    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <h3 className="text-sm font-bold text-slate-900">{__('Anchor Text Diversity Monitor', 'quinrel')}</h3>
                            <p className="text-xs text-slate-500 mt-0.5">
                                {__('Audit anchor texts pointing to any post to prevent algorithmic over-optimization and unnatural link profile penalties.', 'quinrel')}
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            <input
                                type="number"
                                placeholder={__('Post ID', 'quinrel')}
                                value={anchorPostId}
                                onChange={(e) => setAnchorPostId(e.target.value)}
                                className="w-28 text-xs px-2.5 py-1.5 border border-slate-300 rounded focus:ring-1 focus:ring-indigo-500"
                            />
                            <Button isPrimary isBusy={isLoadingAnchors} onClick={analyzeAnchors} style={{ fontSize: '12px' }}>
                                {__('Analyze Post', 'quinrel')}
                            </Button>
                        </div>
                    </div>

                    {anchorAnalysis && (
                        <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm space-y-4">
                            <div className="flex items-center justify-between pb-3 border-b border-slate-100">
                                <div>
                                    <span className="text-xs text-slate-500">{__('Inbound Link Analysis for Post ID:', 'quinrel')}</span>
                                    <strong className="ml-1 text-slate-900">{anchorAnalysis.post_id}</strong>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="text-xs text-slate-500">{__('Anchor Health Score:', 'quinrel')}</span>
                                    <span className={`text-xs font-bold px-2.5 py-0.5 rounded-full ${
                                        anchorAnalysis.score >= 80 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200'
                                    }`}>
                                        {anchorAnalysis.score}% ({anchorAnalysis.status})
                                    </span>
                                </div>
                            </div>

                            {anchorAnalysis.warnings.length > 0 && (
                                <div className="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800">
                                    <strong>{__('Recommendation:', 'quinrel')}</strong> {anchorAnalysis.warnings[0]}
                                </div>
                            )}

                            {anchorAnalysis.anchors.length === 0 ? (
                                <p className="text-xs text-slate-500 italic">{__('No inbound links found pointing to this post yet.', 'quinrel')}</p>
                            ) : (
                                <div>
                                    <h4 className="text-xs font-bold text-slate-700 mb-2 uppercase tracking-wider">{__('Anchor Distribution', 'quinrel')}</h4>
                                    <div className="space-y-1.5">
                                        {anchorAnalysis.anchors.map((a, i) => (
                                            <div key={i} className="flex items-center justify-between p-2 bg-slate-50 rounded border border-slate-100 text-xs">
                                                <span className="font-medium text-slate-800">"{a.anchor}"</span>
                                                <div className="flex items-center gap-2">
                                                    {a.is_exact && (
                                                        <span className="text-[10px] font-bold px-1.5 py-0.5 rounded bg-rose-100 text-rose-700">
                                                            {__('Exact Match', 'quinrel')}
                                                        </span>
                                                    )}
                                                    <span className="text-slate-500 font-semibold">{a.count} {a.count === 1 ? 'link' : 'links'}</span>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
};

