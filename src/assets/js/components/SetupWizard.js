import { useState, useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Notice, Spinner, TextControl } from '@wordpress/components';

const SITE_TYPES = [
    { id: 'blog', label: __('Blog / Personal', 'quinrel'), icon: '✍️', desc: __('Content-focused site with regular posts', 'quinrel') },
    { id: 'news', label: __('News / Media', 'quinrel'), icon: '📰', desc: __('High-frequency publishing with timely content', 'quinrel') },
    { id: 'business', label: __('Business / Agency', 'quinrel'), icon: '🏢', desc: __('Company website with pages and services', 'quinrel') },
    { id: 'ecommerce', label: __('eCommerce', 'quinrel'), icon: '🛒', desc: __('Online store with product pages', 'quinrel') },
];

const SetupWizard = ({ onComplete }) => {
    const config = window.quinrelAdminConfig || {};
    const [step, setStep] = useState(0); // 0 = loading, 1 = configure, 2 = done
    const [autoData, setAutoData] = useState(null);
    const [siteType, setSiteType] = useState('blog');
    const [social, setSocial] = useState({ facebook: '', twitter: '', instagram: '', linkedin: '', youtube: '' });
    const [migrationResults, setMigrationResults] = useState({});
    const [migrationPreview, setMigrationPreview] = useState({});
    const [isMigrating, setIsMigrating] = useState({});
    const [isSaving, setIsSaving] = useState(false);
    const [error, setError] = useState(null);

    // Auto-detect on mount
    useEffect(() => {
        fetch(`${config.apiUrl}/setup/auto-detect`, {
            headers: { 'X-WP-Nonce': config.nonce }
        })
        .then(r => r.json())
        .then(data => {
            setAutoData(data);
            setSiteType(data.site_type || 'blog');
            setSocial(data.social || social);
            setStep(1);
        })
        .catch(() => {
            setStep(1); // Continue even if detection fails
        });
    }, []);

    const runMigration = async (pluginId, confirm) => {
        setIsMigrating(prev => ({ ...prev, [pluginId]: true }));
        try {
            const response = await fetch(`${config.apiUrl}/migration/run`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
                body: JSON.stringify({ plugin_id: pluginId, confirm: !!confirm })
            });
            const result = await response.json();
            if (!confirm) {
                setMigrationPreview(prev => ({ ...prev, [pluginId]: result }));
            } else {
                setMigrationResults(prev => ({ ...prev, [pluginId]: result }));
            }
        } catch (e) {
            setMigrationResults(prev => ({ ...prev, [pluginId]: { success: false, message: __('Migration failed.', 'quinrel') } }));
        } finally {
            setIsMigrating(prev => ({ ...prev, [pluginId]: false }));
        }
    };

    const deactivateCompetitor = async (pluginId) => {
        try {
            const response = await fetch(`${config.apiUrl}/migration/deactivate-competitor`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
                body: JSON.stringify({ plugin_id: pluginId }),
            });
            const result = await response.json();
            if (response.ok && result.success) {
                setMigrationResults(prev => ({
                    ...prev,
                    [pluginId]: {
                        ...prev[pluginId],
                        deactivated: true,
                        message: `${prev[pluginId]?.message || ''} — ${result.message}`,
                    },
                }));
            }
        } catch (e) {
            // Keep existing message
        }
    };

    const finishSetup = async (skip) => {
        setIsSaving(true);
        try {
            await fetch(`${config.apiUrl}/setup/complete`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
                body: JSON.stringify(skip ? { skip: true } : { site_type: siteType, social })
            });
            if (skip) {
                onComplete();
                return;
            }
            setStep(2);
            setTimeout(() => onComplete(), 2000);
        } catch (e) {
            setError(__('Failed to save. Please try again.', 'quinrel'));
        } finally {
            setIsSaving(false);
        }
    };

    // ── Loading State ──
    if (step === 0) {
        return (
            <div style={styles.container}>
                <div style={styles.card}>
                    <div style={{ textAlign: 'center', padding: '48px 0' }}>
                        <Spinner />
                        <p style={{ marginTop: '16px', color: '#64748b' }} role="status">{__('Analyzing your site...', 'quinrel')}</p>
                    </div>
                </div>
            </div>
        );
    }

    // ── Complete State ──
    if (step === 2) {
        return (
            <div style={styles.container}>
                <div style={styles.card}>
                    <div style={{ textAlign: 'center', padding: '48px 0' }}>
                        <div style={{ fontSize: '48px', marginBottom: '16px' }} aria-hidden="true">🎉</div>
                        <h2 style={{ margin: '0 0 8px', fontSize: '22px', color: '#0f172a' }}>{__('You are all set.', 'quinrel')}</h2>
                        <p style={{ margin: 0, color: '#64748b' }}>{__('Quinrel is using the choices you saved.', 'quinrel')}</p>
                    </div>
                </div>
            </div>
        );
    }

    // ── Main Wizard (Single Page) ──
    const detectedPlugins = autoData?.detected_plugins || [];
    const hasSocialData = Object.values(social).some(v => v);

    return (
        <div style={styles.container}>
            <div style={styles.card}>
                {/* Header */}
                <div style={{ textAlign: 'center', marginBottom: '32px' }}>
                    <div style={{ fontSize: '36px', marginBottom: '8px' }} aria-hidden="true">🚀</div>
                    <h2 style={{ margin: '0 0 4px', fontSize: '22px', color: '#0f172a' }}>{__('Welcome to Quinrel', 'quinrel')}</h2>
                    <p style={{ margin: 0, color: '#64748b', fontSize: '14px' }}>
                        {__('Technical SEO is already working on this site. A Quinrel account is optional.', 'quinrel')}
                    </p>
                </div>

                {error && (
                    <Notice status="error" isDismissible={true} onRemove={() => setError(null)}>
                        {error}
                    </Notice>
                )}

                {/* Section 1: Site Type */}
                <div style={styles.section}>
                    <h3 style={styles.sectionTitle}>{__('What type of site is this?', 'quinrel')}</h3>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '10px' }}>
                        {SITE_TYPES.map(type => (
                            <button
                                key={type.id}
                                onClick={() => setSiteType(type.id)}
                                style={{
                                    ...styles.typeButton,
                                    borderColor: siteType === type.id ? '#3b82f6' : '#e2e8f0',
                                    background: siteType === type.id ? '#eff6ff' : '#fff',
                                }}
                            >
                                <span style={{ fontSize: '20px' }} aria-hidden="true">{type.icon}</span>
                                <div>
                                    <div style={{ fontWeight: 600, fontSize: '13px', color: '#0f172a' }}>{type.label}</div>
                                    <div style={{ fontSize: '11px', color: '#64748b', marginTop: '2px' }}>{type.desc}</div>
                                </div>
                            </button>
                        ))}
                    </div>
                </div>

                {/* Section 2: Migration (only if plugins detected) */}
                {detectedPlugins.length > 0 && (
                    <div style={styles.section}>
                        <h3 style={styles.sectionTitle}>{__('Import existing SEO data', 'quinrel')}</h3>
                        <p style={{ fontSize: '13px', color: '#64748b', margin: '0 0 12px' }}>
                            {__('We detected SEO data from other plugins. Preview the counts first. Existing Quinrel fields are not overwritten. Deactivate the other plugin after import so both plugins do not print titles and schema.', 'quinrel')}
                        </p>
                        {detectedPlugins.map(plugin => (
                            <div key={plugin.id} style={styles.migrationRow}>
                                <div>
                                    <strong style={{ fontSize: '14px', color: '#0f172a' }}>{plugin.name}</strong>
                                    <span style={{ fontSize: '12px', color: '#64748b', marginLeft: '8px' }}>
                                        {sprintf(
                                            /* translators: %d: number of posts with imported SEO data. */
                                            __('%d posts with data', 'quinrel'),
                                            plugin.posts
                                        )}
                                    </span>
                                </div>
                                {migrationResults[plugin.id] ? (
                                    <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                                        <span style={{
                                            fontSize: '12px', fontWeight: 500,
                                            color: migrationResults[plugin.id].success ? '#166534' : '#991b1b'
                                        }}>
                                            {migrationResults[plugin.id].message}
                                        </span>
                                        {plugin.active && !migrationResults[plugin.id].deactivated && (
                                            <Button
                                                isSecondary
                                                isSmall
                                                onClick={() => deactivateCompetitor(plugin.id)}
                                            >
                                                {sprintf(__('Deactivate %s to prevent duplicate tags', 'quinrel'), plugin.name)}
                                            </Button>
                                        )}
                                    </div>
                                ) : migrationPreview[plugin.id] ? (
                                    <div>
                                        <p style={{ margin: '0 0 8px', fontSize: '12px', color: '#0f172a' }}>{migrationPreview[plugin.id].message}</p>
                                        {migrationPreview[plugin.id].samples && migrationPreview[plugin.id].samples.length > 0 && (
                                            <div style={{ margin: '8px 0', border: '1px solid #e2e8f0', borderRadius: '4px', overflow: 'hidden' }}>
                                                <table style={{ width: '100%', fontSize: '11px', borderCollapse: 'collapse', textAlign: 'left' }}>
                                                    <thead style={{ background: '#f8fafc', color: '#64748b' }}>
                                                        <tr>
                                                            <th style={{ padding: '4px 8px' }}>{__('Post', 'quinrel')}</th>
                                                            <th style={{ padding: '4px 8px' }}>{__('Source Template', 'quinrel')}</th>
                                                            <th style={{ padding: '4px 8px' }}>{__('Quinrel Resolved Title', 'quinrel')}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {migrationPreview[plugin.id].samples.map(s => (
                                                            <tr key={s.post_id} style={{ borderTop: '1px solid #e2e8f0' }}>
                                                                <td style={{ padding: '4px 8px', fontWeight: 600 }}>{s.post_title}</td>
                                                                <td style={{ padding: '4px 8px', color: '#64748b' }}><code>{s.source_title}</code></td>
                                                                <td style={{ padding: '4px 8px', color: '#166534', fontWeight: 500 }}>{s.resolved_title}</td>
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                        )}
                                        <Button
                                            isPrimary
                                            isSmall
                                            isBusy={isMigrating[plugin.id]}
                                            onClick={() => runMigration(plugin.id, true)}
                                        >
                                            {isMigrating[plugin.id] ? __('Importing...', 'quinrel') : __('Confirm import', 'quinrel')}
                                        </Button>
                                    </div>
                                ) : (
                                    <Button
                                        isSecondary
                                        isSmall
                                        isBusy={isMigrating[plugin.id]}
                                        onClick={() => runMigration(plugin.id, false)}
                                    >
                                        {isMigrating[plugin.id] ? __('Checking...', 'quinrel') : __('Preview import', 'quinrel')}
                                    </Button>
                                )}
                            </div>
                        ))}
                    </div>
                )}

                {/* Section 3: Social (collapsed by default, pre-filled) */}
                <details style={styles.section}>
                    <summary style={{ ...styles.sectionTitle, cursor: 'pointer', userSelect: 'none' }}>
                        {__('Social profiles', 'quinrel')} {hasSocialData && <span style={{ fontSize: '12px', color: '#166534', fontWeight: 400 }}>{__('Auto-detected', 'quinrel')}</span>}
                    </summary>
                    <div style={{ marginTop: '12px' }}>
                        <TextControl label={__('Facebook', 'quinrel')} value={social.facebook} onChange={v => setSocial({ ...social, facebook: v })} placeholder="https://facebook.com/yourpage" />
                        <TextControl label={__('Twitter / X', 'quinrel')} value={social.twitter} onChange={v => setSocial({ ...social, twitter: v })} placeholder="@yourhandle" />
                        <TextControl label={__('Instagram', 'quinrel')} value={social.instagram} onChange={v => setSocial({ ...social, instagram: v })} placeholder="https://instagram.com/yourpage" />
                        <TextControl label={__('LinkedIn', 'quinrel')} value={social.linkedin} onChange={v => setSocial({ ...social, linkedin: v })} placeholder="https://linkedin.com/company/yours" />
                        <TextControl label={__('YouTube', 'quinrel')} value={social.youtube} onChange={v => setSocial({ ...social, youtube: v })} placeholder="https://youtube.com/@yourchannel" />
                    </div>
                </details>

                {/* Finish Button */}
                <div style={{ marginTop: '24px', textAlign: 'center' }}>
                    <Button isPrimary isBusy={isSaving} onClick={() => finishSetup(false)} style={{ padding: '8px 40px', fontSize: '14px' }}>
                        {isSaving ? __('Saving...', 'quinrel') : __('Finish setup', 'quinrel')}
                    </Button>
                    <Button isSecondary onClick={() => finishSetup(true)} style={{ marginLeft: '8px' }}>
                        {__('Skip setup', 'quinrel')}
                    </Button>
                    <p style={{ fontSize: '12px', color: '#475569', marginTop: '8px' }}>
                        {__('You can change any of these later in Settings. Skip leaves the current defaults in place.', 'quinrel')}
                    </p>
                </div>
            </div>
        </div>
    );
};

const styles = {
    container: {
        maxWidth: '640px',
        margin: '0 auto',
        padding: '20px 0',
    },
    card: {
        background: '#fff',
        border: '1px solid #e2e8f0',
        borderRadius: '12px',
        padding: '32px',
        boxShadow: '0 1px 3px rgba(0,0,0,0.06)',
    },
    section: {
        marginTop: '24px',
        paddingTop: '24px',
        borderTop: '1px solid #f1f5f9',
    },
    sectionTitle: {
        fontSize: '15px',
        fontWeight: 600,
        color: '#0f172a',
        margin: '0 0 12px',
    },
    typeButton: {
        display: 'flex',
        alignItems: 'center',
        gap: '10px',
        padding: '12px 14px',
        border: '2px solid',
        borderRadius: '8px',
        cursor: 'pointer',
        textAlign: 'left',
        transition: 'all 0.15s',
    },
    migrationRow: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        padding: '10px 14px',
        background: '#f8fafc',
        borderRadius: '6px',
        marginBottom: '8px',
        border: '1px solid #e2e8f0',
    },
};

export default SetupWizard;
