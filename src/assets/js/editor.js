const quinrelEditorConfig = (typeof window !== 'undefined' && window.quinrelEditorConfig) || {};
import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import { PanelBody, TextControl, TextareaControl, ToggleControl, SelectControl, CheckboxControl, Button, Notice, Spinner } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState, useMemo, useEffect, useCallback } from '@wordpress/element';
import { __, sprintf, _n } from '@wordpress/i18n';
import { analyzeReadability, stripHtml } from './lib/readability';
import { calculateSeoScore } from './lib/seo-score';

// ── Gutenberg Native Schema Blocks ────────────────────────────────
import './blocks/faqBlock';
import './blocks/howtoBlock';
import './blocks/reviewBlock';
import './blocks/tocBlock';

// ── FAQ Schema Editor Component ──────────────────────────────────
const FaqEditor = ({ value, onChange }) => {
    const list = useMemo(() => {
        try {
            const parsed = JSON.parse(value);
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }, [value]);

    const updateItem = (index, field, val) => {
        const newList = [...list];
        newList[index][field] = val;
        onChange(JSON.stringify(newList));
    };

    const addItem = () => {
        onChange(JSON.stringify([...list, { question: '', answer: '' }]));
    };

    const removeItem = (index) => {
        const newList = list.filter((_, i) => i !== index);
        onChange(JSON.stringify(newList));
    };

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
            {list.map((item, idx) => (
                <div key={idx} style={{ padding: '8px', border: '1px solid #cbd5e1', borderRadius: '4px', background: '#fff', position: 'relative' }}>
                    <button
                        type="button"
                        onClick={() => removeItem(idx)}
                        style={{ position: 'absolute', top: '4px', right: '4px', background: 'none', border: 'none', color: '#ef4444', cursor: 'pointer', fontSize: '14px', fontWeight: 'bold' }}
                        title={__('Remove question', 'quinrel')}
                    >
                        ×
                    </button>
                    <div style={{ marginBottom: '6px', marginRight: '16px' }}>
                        <TextControl
                            label={sprintf(__('Question %d', 'quinrel'), idx + 1)}
                            value={item.question}
                            onChange={(val) => updateItem(idx, 'question', val)}
                        />
                    </div>
                    <TextareaControl
                        label={__('Answer', 'quinrel')}
                        value={item.answer}
                        onChange={(val) => updateItem(idx, 'answer', val)}
                        rows={2}
                    />
                </div>
            ))}
            <Button isSecondary onClick={addItem} style={{ alignSelf: 'start' }}>
                {__('+ Add FAQ Question', 'quinrel')}
            </Button>
        </div>
    );
};

// ── HowTo Steps Editor Component ──────────────────────────────────
const HowToStepsEditor = ({ value, onChange }) => {
    const list = useMemo(() => {
        try {
            const parsed = JSON.parse(value);
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }, [value]);

    const updateItem = (index, field, val) => {
        const newList = [...list];
        newList[index][field] = val;
        onChange(JSON.stringify(newList));
    };

    const addItem = () => {
        onChange(JSON.stringify([...list, { name: '', text: '' }]));
    };

    const removeItem = (index) => {
        const newList = list.filter((_, i) => i !== index);
        onChange(JSON.stringify(newList));
    };

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '10px', marginTop: '6px' }}>
            <strong>{__('HowTo Steps', 'quinrel')}</strong>
            {list.map((item, idx) => (
                <div key={idx} style={{ padding: '8px', border: '1px solid #cbd5e1', borderRadius: '4px', background: '#fff', position: 'relative' }}>
                    <button
                        type="button"
                        onClick={() => removeItem(idx)}
                        style={{ position: 'absolute', top: '4px', right: '4px', background: 'none', border: 'none', color: '#ef4444', cursor: 'pointer', fontSize: '14px', fontWeight: 'bold' }}
                        title={__('Remove step', 'quinrel')}
                    >
                        ×
                    </button>
                    <div style={{ marginBottom: '6px', marginRight: '16px' }}>
                        <TextControl
                            label={sprintf(__('Step %d Name', 'quinrel'), idx + 1)}
                            value={item.name}
                            placeholder={__('e.g. Prep the soil', 'quinrel')}
                            onChange={(val) => updateItem(idx, 'name', val)}
                        />
                    </div>
                    <TextareaControl
                        label={__('Step Instructions', 'quinrel')}
                        value={item.text}
                        onChange={(val) => updateItem(idx, 'text', val)}
                        rows={2}
                    />
                </div>
            ))}
            <Button isSecondary onClick={addItem} style={{ alignSelf: 'start' }}>
                {__('+ Add HowTo Step', 'quinrel')}
            </Button>
        </div>
    );
};

// ── Visual Custom Property Tree Builder Component (ASD-001) ──
const CustomPropertyTreeBuilder = ({ value, onChange }) => {
    const list = useMemo(() => {
        try {
            const parsed = JSON.parse(value);
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }, [value]);

    const updateItem = (index, field, val) => {
        const newList = [...list];
        newList[index][field] = val;
        onChange(JSON.stringify(newList));
    };

    const addItem = () => {
        onChange(JSON.stringify([...list, { key: '', value: '' }]));
    };

    const removeItem = (index) => {
        const newList = list.filter((_, i) => i !== index);
        onChange(JSON.stringify(newList));
    };

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
            <div style={{ fontSize: '12px', color: '#64748b', marginBottom: '4px' }}>
                {__('Add custom key-value pairs to extend your schema output. Key names should follow schema.org vocabulary (e.g., "brand", "color", "isbn").', 'quinrel')}
            </div>
            
            <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                {list.map((item, idx) => (
                    <div key={idx} style={{ display: 'flex', gap: '8px', alignItems: 'center', background: '#fff', padding: '6px', borderRadius: '4px', border: '1px solid #cbd5e1' }}>
                        <div style={{ flex: 1 }}>
                            <input
                                type="text"
                                placeholder={__('Key (e.g. brand)', 'quinrel')}
                                value={item.key}
                                onChange={(e) => updateItem(idx, 'key', e.target.value)}
                                style={{ width: '100%', fontSize: '12px', padding: '4px 6px', border: '1px solid #e2e8f0', borderRadius: '3px' }}
                            />
                        </div>
                        <div style={{ flex: 1 }}>
                            <input
                                type="text"
                                placeholder={__('Value (e.g. Nike)', 'quinrel')}
                                value={item.value}
                                onChange={(e) => updateItem(idx, 'value', e.target.value)}
                                style={{ width: '100%', fontSize: '12px', padding: '4px 6px', border: '1px solid #e2e8f0', borderRadius: '3px' }}
                            />
                        </div>
                        <button
                            type="button"
                            onClick={() => removeItem(idx)}
                            style={{ background: 'none', border: 'none', color: '#ef4444', fontSize: '16px', fontWeight: 'bold', cursor: 'pointer', padding: '0 4px' }}
                            title={__('Remove property', 'quinrel')}
                        >
                            ×
                        </button>
                    </div>
                ))}
            </div>

            <Button isSecondary onClick={addItem} style={{ alignSelf: 'start', fontSize: '12px' }}>
                {__('+ Add Custom Property', 'quinrel')}
            </Button>

            {/* Visual Custom Property Tree Graph Preview */}
            <div style={{ marginTop: '12px', background: '#0f172a', borderRadius: '6px', padding: '12px', color: '#38bdf8', fontFamily: 'monospace', fontSize: '11px', border: '1px solid #1e293b' }}>
                <div style={{ color: '#94a3b8', fontSize: '10px', fontWeight: 'bold', textTransform: 'uppercase', marginBottom: '8px', borderBottom: '1px solid #334155', paddingBottom: '4px' }}>
                    {__('Visual Schema Property Graph', 'quinrel')}
                </div>
                <div>
                    <span style={{ color: '#f43f5e' }}>{"{"}</span>
                    <div style={{ paddingLeft: '12px' }}>
                        <span style={{ color: '#38bdf8' }}>"@context"</span>: <span style={{ color: '#e2e8f0' }}>"https://schema.org"</span>,
                        <br />
                        <span style={{ color: '#38bdf8' }}>"@graph"</span>: <span style={{ color: '#f59e0b' }}>{"["}</span>
                        <div style={{ paddingLeft: '12px' }}>
                            <span style={{ color: '#a855f7' }}>{"{"}</span>
                            <div style={{ paddingLeft: '12px' }}>
                                <span style={{ color: '#38bdf8' }}>"@type"</span>: <span style={{ color: '#e2e8f0' }}>"..."</span>,
                                <br />
                                <span style={{ color: '#38bdf8' }}>"@id"</span>: <span style={{ color: '#e2e8f0' }}>"...#custom"</span>
                                {list.filter(item => item.key && item.value).map((item, idx) => (
                                    <div key={idx}>
                                        <span style={{ color: '#cbd5e1' }}>├── </span>
                                        <span style={{ color: '#38bdf8' }}>"{item.key}"</span>: <span style={{ color: '#22c55e' }}>"{item.value}"</span>
                                    </div>
                                ))}
                            </div>
                            <span style={{ color: '#a855f7' }}>{"}"}</span>
                        </div>
                        <span style={{ color: '#f59e0b' }}>{"]"}</span>
                    </div>
                    <span style={{ color: '#f43f5e' }}>{"}"}</span>
                </div>
            </div>
        </div>
    );
};

// ── Recipe Instructions Editor Component ──────────────────────────────────
const RecipeInstructionsEditor = ({ value, onChange }) => {
    const list = useMemo(() => {
        try {
            const parsed = JSON.parse(value);
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }, [value]);

    const updateItem = (index, field, val) => {
        const newList = [...list];
        newList[index][field] = val;
        onChange(JSON.stringify(newList));
    };

    const addItem = () => {
        onChange(JSON.stringify([...list, { name: '', text: '' }]));
    };

    const removeItem = (index) => {
        const newList = list.filter((_, i) => i !== index);
        onChange(JSON.stringify(newList));
    };

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '10px', marginTop: '6px' }}>
            <strong>{__('Recipe Instructions', 'quinrel')}</strong>
            {list.map((item, idx) => (
                <div key={idx} style={{ padding: '8px', border: '1px solid #cbd5e1', borderRadius: '4px', background: '#fff', position: 'relative' }}>
                    <button
                        type="button"
                        onClick={() => removeItem(idx)}
                        style={{ position: 'absolute', top: '4px', right: '4px', background: 'none', border: 'none', color: '#ef4444', cursor: 'pointer', fontSize: '14px', fontWeight: 'bold' }}
                        title={__('Remove step', 'quinrel')}
                    >
                        ×
                    </button>
                    <div style={{ marginBottom: '6px', marginRight: '16px' }}>
                        <TextControl
                            label={sprintf(__('Step %d Name', 'quinrel'), idx + 1)}
                            value={item.name}
                            placeholder={__('e.g. Mix the ingredients', 'quinrel')}
                            onChange={(val) => updateItem(idx, 'name', val)}
                        />
                    </div>
                    <TextareaControl
                        label={__('Step Instructions', 'quinrel')}
                        value={item.text}
                        onChange={(val) => updateItem(idx, 'text', val)}
                        rows={2}
                    />
                </div>
            ))}
            <Button isSecondary onClick={addItem} style={{ alignSelf: 'start' }}>
                {__('+ Add Recipe Step', 'quinrel')}
            </Button>
        </div>
    );
};

// ── Recipe SERP Preview Component ──────────────────────────────────
const RecipeSerpPreview = ({ name, url, image, calories, prepTime, cookTime, ingredientsCount, stepsCount }) => {
    const displayImage = image || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=400&h=400&fit=crop';
    let duration = '';
    const pTime = parseInt(prepTime) || 0;
    const cTime = parseInt(cookTime) || 0;
    const totalMins = pTime + cTime;
    if (totalMins > 0) {
        if (totalMins >= 60) {
            duration = `${Math.floor(totalMins/60)} hr ${totalMins%60 > 0 ? (totalMins%60) + ' min' : ''}`;
        } else {
            duration = `${totalMins} min`;
        }
    }

    return (
        <div style={{
            border: '1px solid #fcd34d', borderRadius: '12px', padding: '16px',
            background: '#fffbeb', fontFamily: 'Arial, sans-serif', marginTop: '16px',
            boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.05)'
        }}>
            <div style={{ fontSize: '11px', color: '#202124', marginBottom: '4px' }}>
                {url || 'https://example.com › your-recipe'}
            </div>
            <div style={{ display: 'flex', gap: '16px' }}>
                <div style={{ flex: 1 }}>
                    <div style={{
                        fontSize: '20px', color: '#1a0dab', lineHeight: '1.3',
                        overflow: 'hidden', textOverflow: 'ellipsis', display: '-webkit-box',
                        WebkitLineClamp: 2, WebkitBoxOrient: 'vertical',
                        cursor: 'pointer', marginBottom: '8px'
                    }}>
                        {name || __('Your Delicious Recipe Name', 'quinrel')}
                    </div>
                    
                    <div style={{ display: 'flex', gap: '8px', alignItems: 'center', flexWrap: 'wrap', marginBottom: '12px' }}>
                        <div style={{ color: '#f59e0b', fontSize: '14px' }}>★★★★★ <span style={{ color: '#70757a', fontSize: '13px' }}>(124)</span></div>
                        {duration && <div style={{ color: '#70757a', fontSize: '13px' }}>• {duration}</div>}
                    </div>

                    <div style={{ display: 'flex', gap: '12px', color: '#70757a', fontSize: '13px', flexWrap: 'wrap' }}>
                        {calories && <span>🔥 {calories}</span>}
                        {ingredientsCount > 0 && <span>🥗 {ingredientsCount} Ingredients</span>}
                        {stepsCount > 0 && <span>📋 {stepsCount} Steps</span>}
                    </div>
                </div>
                <div style={{ width: '100px', height: '100px', flexShrink: 0, borderRadius: '8px', overflow: 'hidden', border: '1px solid #fbbf24' }}>
                    <img src={displayImage} alt="Recipe" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                </div>
            </div>
        </div>
    );
};

// ── Event SERP Preview Component ──────────────────────────────────
const EventSerpPreview = ({ name, url, startDate, venue, address, performer, price }) => {
    let dateStr = 'TBD';
    if (startDate) {
        try {
            const d = new Date(startDate);
            dateStr = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        } catch (e) {}
    }

    return (
        <div style={{
            border: '1px solid #fcd34d', borderRadius: '12px', padding: '16px',
            background: '#fffbeb', fontFamily: 'Arial, sans-serif', marginTop: '16px',
            boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.05)'
        }}>
            <div style={{ fontSize: '11px', color: '#202124', marginBottom: '4px' }}>
                {url || 'https://example.com › your-event'}
            </div>
            
            <div style={{ display: 'flex', gap: '16px', alignItems: 'center' }}>
                <div style={{ 
                    display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
                    background: '#fef3c7', border: '1px solid #fde68a', borderRadius: '8px',
                    width: '60px', height: '60px', flexShrink: 0
                }}>
                    <div style={{ color: '#d97706', fontSize: '12px', fontWeight: 'bold', textTransform: 'uppercase' }}>
                        {dateStr !== 'TBD' ? dateStr.split(' ')[0] : 'DATE'}
                    </div>
                    <div style={{ color: '#b45309', fontSize: '20px', fontWeight: 'bold' }}>
                        {dateStr !== 'TBD' ? dateStr.split(' ')[1].replace(',', '') : '??'}
                    </div>
                </div>

                <div style={{ flex: 1 }}>
                    <div style={{
                        fontSize: '18px', color: '#1a0dab', lineHeight: '1.3',
                        overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap',
                        cursor: 'pointer', marginBottom: '4px'
                    }}>
                        {name || __('Amazing Live Event', 'quinrel')}
                    </div>
                    
                    <div style={{ fontSize: '13px', color: '#4d5156', lineHeight: '1.4' }}>
                        <div>{venue ? `📍 ${venue}` : '📍 Venue Name'} {address ? `- ${address}` : ''}</div>
                        {(performer || price) && (
                            <div style={{ marginTop: '4px' }}>
                                {performer && <span style={{ marginRight: '12px' }}>🎵 {performer}</span>}
                                {price && <span>🎟️ {price}</span>}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
};

// ── Score Ring Component ──────────────────────────────────
const ScoreRing = ({ score, color, label, size = 72 }) => {
    const radius = (size - 8) / 2;
    const circumference = 2 * Math.PI * radius;
    const offset = circumference - (score / 100) * circumference;

    return (
        <div style={{ display: 'flex', alignItems: 'center', gap: '12px', padding: '8px 0' }}>
            <svg width={size} height={size} style={{ flexShrink: 0 }}>
                <circle cx={size/2} cy={size/2} r={radius} fill="none" stroke="#e2e8f0" strokeWidth="6" />
                <circle
                    cx={size/2} cy={size/2} r={radius} fill="none"
                    stroke={color} strokeWidth="6" strokeLinecap="round"
                    strokeDasharray={circumference} strokeDashoffset={offset}
                    style={{ transform: 'rotate(-90deg)', transformOrigin: '50% 50%', transition: 'stroke-dashoffset 0.5s ease' }}
                />
                <text x="50%" y="50%" dominantBaseline="central" textAnchor="middle"
                    style={{ fontSize: '16px', fontWeight: '700', fill: color }}>
                    {score}
                </text>
            </svg>
            <div>
                <div style={{ fontSize: '14px', fontWeight: '600', color: '#0f172a' }}>{__('SEO Score', 'quinrel')}</div>
                <div style={{ fontSize: '12px', color: color, fontWeight: '500' }}>{label}</div>
            </div>
        </div>
    );
};

// ── SERP Preview Component ──────────────────────────────────
const SerpPreview = ({ title, url, description }) => (
    <div style={{
        border: '1px solid #e2e8f0', borderRadius: '8px', padding: '14px',
        background: '#fff', fontFamily: 'Arial, sans-serif', marginTop: '12px'
    }}>
        <div style={{ fontSize: '11px', color: '#202124', marginBottom: '2px' }}>
            {url || 'https://example.com › your-post-slug'}
        </div>
        <div style={{
            fontSize: '18px', color: '#1a0dab', lineHeight: '1.3',
            overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap',
            cursor: 'pointer', marginBottom: '4px'
        }}>
            {title || __('Your Page Title', 'quinrel')}
        </div>
        <div style={{ fontSize: '13px', color: '#4d5156', lineHeight: '1.4', 
            display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', overflow: 'hidden' }}>
            {description || __('Add a meta description to control how this page appears in search results...', 'quinrel')}
        </div>
    </div>
);

// ── Character Counter Component ──────────────────────────────────
const CharCount = ({ current, min, max }) => {
    let color = '#22c55e'; // green
    if (current === 0) color = '#94a3b8';
    else if (current > max) color = '#ef4444';
    else if (current < min) color = '#f59e0b';

    return (
        <span style={{ fontSize: '11px', fontWeight: '500', color, float: 'right' }}>
            {current} / {max}
        </span>
    );
};

// ── Check Item Component ──────────────────────────────────
const CheckItem = ({ label, pass }) => (
    <li style={{ display: 'flex', alignItems: 'flex-start', gap: '6px', fontSize: '13px', lineHeight: '1.4', padding: '3px 0' }}>
        <span style={{ color: pass ? '#22c55e' : '#ef4444', flexShrink: 0, fontSize: '14px', marginTop: '1px' }}>
            {pass ? '✓' : '✗'}
        </span>
        <span style={{ color: '#334155' }}>{label}</span>
    </li>
);

// ── Social Preview Component ──────────────────────────────────
const SocialCardPreview = ({ title, description, image, domain }) => {
    const displayImage = image || 'https://images.unsplash.com/photo-1432821596592-e2c18b78144f?w=1200&h=630&fit=crop';
    
    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '16px', marginTop: '16px' }}>
            {/* Facebook Card */}
            <div>
                <div style={{ fontSize: '11px', color: '#64748b', fontWeight: 'bold', textTransform: 'uppercase', marginBottom: '6px' }}>{__('Facebook Preview', 'quinrel')}</div>
                <div style={{
                    border: '1px solid #ced0d4',
                    borderRadius: '8px',
                    overflow: 'hidden',
                    background: '#f0f2f5',
                    cursor: 'pointer',
                    fontFamily: 'Helvetica, Arial, sans-serif',
                    transition: 'transform 0.2s ease, box-shadow 0.2s ease',
                }}
                className="fb-preview-card"
                onMouseEnter={(e) => {
                    e.currentTarget.style.transform = 'translateY(-2px)';
                    e.currentTarget.style.boxShadow = '0 4px 12px rgba(0,0,0,0.08)';
                }}
                onMouseLeave={(e) => {
                    e.currentTarget.style.transform = 'none';
                    e.currentTarget.style.boxShadow = 'none';
                }}
                >
                    <div style={{ position: 'relative', width: '100%', paddingTop: '52.35%' /* 1.91:1 */, background: '#e9ebee' }}>
                        <img 
                            src={displayImage} 
                            alt={__('Facebook Preview', 'quinrel')} 
                            style={{ position: 'absolute', top: 0, left: 0, width: '100%', height: '100%', objectFit: 'cover' }} 
                        />
                    </div>
                    <div style={{ padding: '12px', background: '#f2f3f5', borderTop: '1px solid #dddfe2' }}>
                        <div style={{ fontSize: '12px', color: '#606770', textTransform: 'uppercase', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                            {domain || 'example.com'}
                        </div>
                        <div style={{ fontSize: '16px', fontWeight: 'bold', color: '#1d2129', marginTop: '4px', overflow: 'hidden', textOverflow: 'ellipsis', display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', lineHeight: '20px', height: '40px' }}>
                            {title || __('Your Sharing Title', 'quinrel')}
                        </div>
                        <div style={{ fontSize: '13px', color: '#606770', marginTop: '4px', overflow: 'hidden', textOverflow: 'ellipsis', display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', lineHeight: '18px', height: '36px' }}>
                            {description || __('This is the description that will display when you share your link.', 'quinrel')}
                        </div>
                    </div>
                </div>
            </div>

            {/* Twitter/X Card */}
            <div>
                <div style={{ fontSize: '11px', color: '#64748b', fontWeight: 'bold', textTransform: 'uppercase', marginBottom: '6px' }}>{__('Twitter / X Preview', 'quinrel')}</div>
                <div style={{
                    border: '1px solid #e1e8ed',
                    borderRadius: '16px',
                    overflow: 'hidden',
                    background: '#fff',
                    cursor: 'pointer',
                    fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
                }}>
                    <div style={{ position: 'relative', width: '100%', paddingTop: '52.35%' }}>
                        <img 
                            src={displayImage} 
                            alt="Twitter Preview" 
                            style={{ position: 'absolute', top: 0, left: 0, width: '100%', height: '100%', objectFit: 'cover' }} 
                        />
                    </div>
                    <div style={{ padding: '12px', borderTop: '1px solid #e1e8ed' }}>
                        <div style={{ fontSize: '13px', color: '#536471', display: 'flex', alignItems: 'center', gap: '3px' }}>
                            <span>{domain || 'example.com'}</span>
                            <span style={{ fontSize: '10px' }}>↗</span>
                        </div>
                        <div style={{ fontSize: '15px', fontWeight: '700', color: '#0f1419', marginTop: '4px', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                            {title || __('Your Sharing Title', 'quinrel')}
                        </div>
                        <div style={{ fontSize: '14px', color: '#536471', marginTop: '4px', overflow: 'hidden', textOverflow: 'ellipsis', display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', lineHeight: '18px', height: '36px' }}>
                            {description || __('This is the description that will display when you share your link.', 'quinrel')}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

// ── Quinrel AI Assistant Component ──────────────────────────────────
const AiAssistantPanel = ({ title, content, focusKeyword, updateMeta, meta }) => {
    const [action, setAction] = useState('generate_titles_metas');
    const [isGenerating, setIsGenerating] = useState(false);
    const [aiResult, setAiResult] = useState('');
    const [errorMsg, setErrorMsg] = useState('');

    // Debounced Writing Assistant Suggestions
    const [realtimeSuggestion, setRealtimeSuggestion] = useState('');
    const [isDebouncingSuggestion, setIsDebouncingSuggestion] = useState(false);

    useEffect(() => {
        if (!focusKeyword || !content || content.length < 50) return;
        
        setIsDebouncingSuggestion(true);
        const delayDebounceFn = setTimeout(async () => {
            try {
                const response = await fetch(`${quinrelEditorConfig.apiUrl}/ai/generate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': quinrelEditorConfig.nonce,
                    },
                    body: JSON.stringify({
                        action: 'writing_assistant',
                        title,
                        content,
                        focus_keyword: focusKeyword
                    })
                });
                const data = await response.json();
                if (response.ok && data.success) {
                    setRealtimeSuggestion(data.result);
                }
            } catch (e) {
                // fail silently
            } finally {
                setIsDebouncingSuggestion(false);
            }
        }, 3000); // 3000ms debounce

        return () => clearTimeout(delayDebounceFn);
    }, [content, focusKeyword]);

    const runAiAction = async () => {
        setIsGenerating(true);
        setErrorMsg('');
        setAiResult('');
        try {
            const response = await fetch(`${quinrelEditorConfig.apiUrl}/ai/generate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': quinrelEditorConfig.nonce,
                },
                body: JSON.stringify({
                    action,
                    title,
                    content,
                    focus_keyword: focusKeyword
                })
            });
            const data = await response.json();
            if (response.ok && data.success) {
                setAiResult(data.result);
            } else {
                setErrorMsg(data.message || __('AI Generation failed. Make sure your API Key is verified under Quinrel Settings.', 'quinrel'));
            }
        } catch (error) {
            setErrorMsg(__('Network error occurred during generation.', 'quinrel'));
        } finally {
            setIsGenerating(false);
        }
    };

    const parsedResult = useMemo(() => {
        if (!aiResult) return null;
        try {
            return JSON.parse(aiResult);
        } catch (e) {
            return null;
        }
    }, [aiResult]);

    return (
        <PanelBody title={__('Quinrel AI Assistant', 'quinrel')} initialOpen={false}>
            <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                <SelectControl
                    label={__('AI Optimization Goal', 'quinrel')}
                    value={action}
                    options={[
                        { label: __('Generate CTR Titles & Metas', 'quinrel'), value: 'generate_titles_metas' },
                        { label: __('Generate Structured FAQs', 'quinrel'), value: 'generate_faqs' },
                        { label: __('Generate SEO Outline & Summary', 'quinrel'), value: 'generate_outline_summary' },
                        { label: __('SEO Writing Recommendations', 'quinrel'), value: 'writing_assistant' },
                    ]}
                    onChange={(val) => { setAction(val); setAiResult(''); setErrorMsg(''); }}
                />

                <Button 
                    isPrimary 
                    isBusy={isGenerating} 
                    onClick={runAiAction} 
                    className="w-full justify-center"
                    style={{ background: '#4f46e5', borderColor: '#4f46e5' }}
                >
                    {isGenerating ? __('Querying Agent Gateway...', 'quinrel') : __('Get AI SEO Insights', 'quinrel')}
                </Button>

                {errorMsg && (
                    <Notice status="error" isDismissible={false} className="mt-2">
                        {errorMsg}
                    </Notice>
                )}

                {isGenerating && (
                    <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: '8px', padding: '24px 0', background: '#f8fafc', borderRadius: '8px', border: '1px solid #e2e8f0' }}>
                        <Spinner />
                        <span style={{ fontSize: '12px', color: '#475569', fontWeight: '500' }}>{__('Structuring targeted queries...', 'quinrel')}</span>
                    </div>
                )}

                {aiResult && !isGenerating && (
                    <div style={{ marginTop: '12px', padding: '12px', background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: '8px' }}>
                        <h4 style={{ margin: '0 0 8px', fontSize: '13px', fontWeight: '600', color: '#0f172a' }}>{__('AI Gateway Response:', 'quinrel')}</h4>
                        
                        {action === 'generate_titles_metas' && parsedResult && (
                            <div style={{ display: 'flex', flexDirection: 'column', gap: '10px' }}>
                                <div style={{ borderBottom: '1px solid #e2e8f0', paddingBottom: '8px' }}>
                                    <strong style={{ fontSize: '11px', textTransform: 'uppercase', color: '#64748b' }}>{__('Suggested Titles', 'quinrel')}</strong>
                                    {parsedResult.titles?.map((t, i) => (
                                        <div key={i} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: '#fff', padding: '6px', border: '1px solid #cbd5e1', borderRadius: '4px', fontSize: '12px', marginTop: '4px' }}>
                                            <span style={{ fontWeight: '500', color: '#1e293b' }}>{t}</span>
                                            <Button isLink isSmall onClick={() => updateMeta('_quinrel_meta_title', t)}>{__('Apply', 'quinrel')}</Button>
                                        </div>
                                    ))}
                                </div>
                                <div>
                                    <strong style={{ fontSize: '11px', textTransform: 'uppercase', color: '#64748b' }}>{__('Suggested Descriptions', 'quinrel')}</strong>
                                    {parsedResult.descriptions?.map((d, i) => (
                                        <div key={i} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'start', background: '#fff', padding: '6px', border: '1px solid #cbd5e1', borderRadius: '4px', fontSize: '12px', marginTop: '4px', gap: '8px' }}>
                                            <span style={{ color: '#334155' }}>{d}</span>
                                            <Button isLink isSmall onClick={() => updateMeta('_quinrel_meta_description', d)}>{__('Apply', 'quinrel')}</Button>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {action === 'generate_faqs' && parsedResult && (
                            <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                                {parsedResult.map((faq, i) => (
                                    <div key={i} style={{ background: '#fff', padding: '8px', border: '1px solid #cbd5e1', borderRadius: '4px', fontSize: '12px' }}>
                                        <div style={{ fontWeight: 'bold', color: '#0f172a' }}>{faq.question}</div>
                                        <div style={{ color: '#475569', marginTop: '2px' }}>{faq.answer}</div>
                                    </div>
                                ))}
                                <Button 
                                    isSecondary 
                                    isSmall 
                                    onClick={() => {
                                        updateMeta('_quinrel_primary_schema', 'faq');
                                        updateMeta('_quinrel_schema_faq_questions', JSON.stringify(parsedResult));
                                    }}
                                    style={{ marginTop: '4px' }}
                                >
                                    {__('Apply as FAQ Schema', 'quinrel')}
                                </Button>
                            </div>
                        )}

                        {(action === 'generate_outline_summary' || action === 'writing_assistant') && (
                            <div style={{ fontSize: '12px', color: '#334155', whiteSpace: 'pre-wrap', lineHeight: '1.6' }}>
                                {aiResult}
                            </div>
                        )}
                    </div>
                )}

                {focusKeyword && (
                    <div style={{ marginTop: '12px', background: '#f0fdf4', border: '1px solid #bbf7d0', borderRadius: '6px', padding: '10px' }}>
                        <div style={{ fontSize: '11px', fontWeight: 'bold', color: '#16a34a', textTransform: 'uppercase', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                            <span>{__('Real-time SEO Copilot', 'quinrel')}</span>
                            {isDebouncingSuggestion && <span style={{ fontSize: '10px', color: '#65a30d', fontStyle: 'italic' }}>{__('(Analyzing...)', 'quinrel')}</span>}
                        </div>
                        <div style={{ fontSize: '12px', color: '#166534', marginTop: '6px', lineHeight: '1.5' }}>
                            {realtimeSuggestion || __('Start writing to receive real-time keyword placement and density tips.', 'quinrel')}
                        </div>
                    </div>
                )}
            </div>
        </PanelBody>
    );
};

// ── Internal Link Suggester Component ──────────────────────────────────
const InternalLinkPanel = ({ postId, content }) => {
    const [linksData, setLinksData] = useState(null);
    const [isLoading, setIsLoading] = useState(false);

    const scanInternalLinks = async () => {
        setIsLoading(true);
        try {
            const response = await fetch(`${quinrelEditorConfig.apiUrl}/content/internal-links`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': quinrelEditorConfig.nonce,
                },
                body: JSON.stringify({
                    post_id: postId,
                    content
                })
            });
            const data = await response.json();
            if (response.ok && data.success) {
                setLinksData(data);
            }
        } catch (e) {
            console.error(e);
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        scanInternalLinks();
    }, [postId]);

    const orphanStatus = linksData?.orphan_status;
    const recommendations = linksData?.recommendations || [];

    return (
        <PanelBody title={__('Internal Links & Orphan Finder', 'quinrel')} initialOpen={false}>
            <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
                {/* Orphan finder badge */}
                {orphanStatus && (
                    <div style={{
                        padding: '10px',
                        borderRadius: '6px',
                        border: '1px solid',
                        borderColor: orphanStatus.is_orphan ? '#fecaca' : '#bbf7d0',
                        background: orphanStatus.is_orphan ? '#fef2f2' : '#f0fdf4',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between'
                    }}>
                        <div>
                            <div style={{ fontSize: '13px', fontWeight: 'bold', color: orphanStatus.is_orphan ? '#991b1b' : '#166534' }}>
                                {orphanStatus.is_orphan ? __('Orphan Post Alert', 'quinrel') : __('Incoming Links OK', 'quinrel')}
                            </div>
                            <div style={{ fontSize: '11px', color: orphanStatus.is_orphan ? '#b91c1c' : '#15803d', marginTop: '2px' }}>
                                {orphanStatus.is_orphan 
                                    ? '0 pages link back to this post. It might be hard for Google to index!' 
                                    : `Verified: ${orphanStatus.incoming_count} other pages link to this post.`}
                            </div>
                        </div>
                        <span style={{ fontSize: '20px' }}>
                            {orphanStatus.is_orphan ? '⚠️' : '✅'}
                        </span>
                    </div>
                )}

                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <span style={{ fontSize: '13px', fontWeight: '600', color: '#475569' }}>{__('Suggestions (Max 5)', 'quinrel')}</span>
                    <Button isSmall isSecondary isBusy={isLoading} onClick={scanInternalLinks}>
                        {__('Re-Scan', 'quinrel')}
                    </Button>
                </div>

                {isLoading && <Spinner />}

                {!isLoading && recommendations.length === 0 && (
                    <p style={{ fontSize: '12px', color: '#64748b', fontStyle: 'italic', margin: 0 }}>
                        {__('No link suggestions available. Try adding some relevant categories/tags or writing more content.', 'quinrel')}
                    </p>
                )}

                {!isLoading && recommendations.map((rec, i) => (
                    <div key={i} style={{
                        padding: '10px',
                        border: '1px solid #cbd5e1',
                        borderRadius: '6px',
                        background: '#fff',
                        boxShadow: '0 1px 2px rgba(0,0,0,0.02)'
                    }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'start' }}>
                            <strong style={{ fontSize: '13px', color: '#0f172a', display: 'block', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', maxWidth: '180px' }}>
                                {rec.title}
                            </strong>
                            <a href={rec.url} target="_blank" rel="noopener noreferrer" style={{ fontSize: '10px', color: '#4f46e5', fontWeight: '600', textDecoration: 'none' }}>
                                {__('View ↗', 'quinrel')}
                            </a>
                        </div>
                        
                        <div style={{ fontSize: '11px', color: '#64748b', marginTop: '4px' }}>
                            {rec.reason}
                        </div>

                        <div style={{
                            marginTop: '8px',
                            background: '#f8fafc',
                            border: '1px solid #e2e8f0',
                            borderRadius: '4px',
                            padding: '6px',
                            fontSize: '11px',
                            fontFamily: 'monospace'
                        }}>
                            Use anchor text: <strong>"{rec.anchor}"</strong>
                        </div>
                    </div>
                ))}
            </div>
        </PanelBody>
    );
};

// ── ACF & Custom Fields SEO Panel ──────────────────────────────────
const CustomFieldsSeoPanel = ({ postId }) => {
    const [fields, setFields] = useState([]);
    const [activeFields, setActiveFields] = useState([]);
    const [isLoading, setIsLoading] = useState(false);
    const [isSaving, setIsSaving] = useState(false);
    const [notice, setNotice] = useState(null);

    const loadCustomFields = async () => {
        if (!postId) return;
        setIsLoading(true);
        try {
            const res = await fetch(`${quinrelEditorConfig.apiUrl}/custom-fields/discover?post_id=${postId}`, {
                headers: { 'X-WP-Nonce': quinrelEditorConfig.nonce }
            });
            const data = await res.json();
            if (res.ok) {
                setFields(data.discovered_fields || []);
                setActiveFields(data.active_fields || []);
            }
        } catch (e) {
            console.error(e);
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        loadCustomFields();
    }, [postId]);

    const toggleField = async (fieldName) => {
        const next = activeFields.includes(fieldName)
            ? activeFields.filter(f => f !== fieldName)
            : [...activeFields, fieldName];

        setActiveFields(next);
        setIsSaving(true);
        try {
            const res = await fetch(`${quinrelEditorConfig.apiUrl}/custom-fields/settings`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': quinrelEditorConfig.nonce
                },
                body: JSON.stringify({ fields: next })
            });
            const data = await res.json();
            if (res.ok && data.success) {
                setNotice({ type: 'success', text: __('Custom field preferences saved.', 'quinrel') });
                setTimeout(() => setNotice(null), 3000);
            }
        } catch (e) {
            setNotice({ type: 'error', text: __('Failed to update custom fields settings.', 'quinrel') });
        } finally {
            setIsSaving(false);
        }
    };

    return (
        <PanelBody title={__('ACF & Custom Fields Analysis', 'quinrel')} initialOpen={false}>
            <p style={{ fontSize: '12px', color: '#64748b', margin: '0 0 10px' }}>
                {__('Include custom fields (such as ACF Flexible Content, Repeaters, or custom meta) in Quinrel’s SEO, readability, and word count evaluation.', 'quinrel')}
            </p>
            {isLoading && <p style={{ fontSize: '12px', color: '#64748b' }}>{__('Scanning custom fields...', 'quinrel')}</p>}
            {!isLoading && fields.length === 0 && (
                <div style={{ background: '#f8fafc', padding: '10px', borderRadius: '6px', border: '1px solid #e2e8f0', fontSize: '12px', color: '#64748b' }}>
                    {__('No textual custom fields detected on this post. As you populate ACF or custom meta, they will appear here.', 'quinrel')}
                </div>
            )}
            {!isLoading && fields.length > 0 && (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
                    {fields.map((f) => {
                        const isChecked = activeFields.includes(f.name);
                        return (
                            <div
                                key={f.name}
                                style={{
                                    display: 'flex',
                                    justifyContent: 'space-between',
                                    alignItems: 'center',
                                    padding: '8px 10px',
                                    background: isChecked ? '#f0fdf4' : '#f8fafc',
                                    border: '1px solid',
                                    borderColor: isChecked ? '#bbf7d0' : '#e2e8f0',
                                    borderRadius: '6px'
                                }}
                            >
                                <div style={{ flex: 1, minWidth: 0, marginRight: '8px' }}>
                                    <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
                                        <span style={{ fontSize: '12px', fontWeight: '600', color: '#1e293b' }}>
                                            {f.label}
                                        </span>
                                        <span style={{
                                            fontSize: '10px',
                                            fontWeight: '500',
                                            padding: '1px 6px',
                                            borderRadius: '4px',
                                            background: f.source === 'ACF' ? '#e0e7ff' : '#f1f5f9',
                                            color: f.source === 'ACF' ? '#3730a3' : '#475569'
                                        }}>
                                            {f.source}
                                        </span>
                                    </div>
                                    {f.preview && (
                                        <div style={{ fontSize: '11px', color: '#64748b', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                            {f.preview}
                                        </div>
                                    )}
                                </div>
                                <CheckboxControl
                                    checked={isChecked}
                                    disabled={isSaving}
                                    onChange={() => toggleField(f.name)}
                                />
                            </div>
                        );
                    })}
                </div>
            )}
            {notice && (
                <div style={{
                    marginTop: '8px',
                    fontSize: '12px',
                    padding: '6px 8px',
                    borderRadius: '4px',
                    background: notice.type === 'success' ? '#f0fdf4' : '#fef2f2',
                    color: notice.type === 'success' ? '#166534' : '#991b1b',
                    border: '1px solid',
                    borderColor: notice.type === 'success' ? '#bbf7d0' : '#fecaca'
                }}>
                    {notice.text}
                </div>
            )}
        </PanelBody>
    );
};

// ── Schema Generation and Validation Helpers (SD-003) ──
const convertToIsoDuration = (time) => {
    if (!time) return '';
    const trimmed = time.trim();
    if (!trimmed) return '';
    if (trimmed.startsWith('P')) return trimmed;

    let hours = 0;
    let minutes = 0;

    const hrMatch = trimmed.match(/(\d+)\s*(?:hour|hr|h)/i);
    if (hrMatch) hours = parseInt(hrMatch[1], 10);

    const minMatch = trimmed.match(/(\d+)\s*(?:minute|min|m)/i);
    if (minMatch) minutes = parseInt(minMatch[1], 10);

    if (hours === 0 && minutes === 0 && !isNaN(trimmed)) {
        minutes = parseInt(trimmed, 10);
    }

    let duration = 'PT';
    if (hours > 0) duration += hours + 'H';
    if (minutes > 0 || hours === 0) duration += minutes + 'M';
    return duration;
};

const mergeCustomProperties = (meta, schemaObj) => {
    if (!schemaObj) return schemaObj;
    const customPropsJson = meta._quinrel_custom_schema_properties;
    if (customPropsJson) {
        try {
            const customProps = JSON.parse(customPropsJson);
            if (Array.isArray(customProps)) {
                customProps.forEach(prop => {
                    if (prop.key && prop.value !== undefined) {
                        schemaObj[prop.key] = prop.value;
                    }
                });
            }
        } catch (e) {}
    }
    return schemaObj;
};

const generateLdJson = (schemaType, meta, title, content, featuredMediaUrl, permalink, domain) => {
    if (!schemaType || schemaType === 'none') return null;

    const baseId = permalink || 'https://example.com/post';
    const baseUrl = permalink || 'https://example.com/post';

    const graphs = [];

    // 1. Article / NewsArticle Schema
    const isNews = meta._quinrel_is_news === 'yes';
    const article = {
        "@type": isNews ? "NewsArticle" : "Article",
        "@id": `${baseId}#article`,
        "headline": title || 'Untitled',
        "mainEntityOfPage": {
            "@type": "WebPage",
            "@id": baseUrl
        }
    };
    if (featuredMediaUrl) {
        article.image = {
            "@type": "ImageObject",
            "url": featuredMediaUrl
        };
    }
    graphs.push(article);

    // 2. Breadcrumbs
    graphs.push({
        "@type": "BreadcrumbList",
        "@id": `${baseId}#breadcrumb`,
        "itemListElement": [
            {
                "@type": "ListItem",
                "position": 1,
                "name": "Home",
                "item": "/"
            }
        ]
    });

    // 3. Custom point-and-click schemas
    if (schemaType === 'product') {
        const name = meta._quinrel_schema_product_name || title || __('Product Name', 'quinrel');
        const description = meta._quinrel_schema_product_description || '';
        const price = meta._quinrel_schema_product_price || '';
        const currency = meta._quinrel_schema_product_currency || 'USD';
        const rating = meta._quinrel_schema_product_rating || '';
        const availability = meta._quinrel_schema_product_availability || 'InStock';

        const product = {
            "@type": "Product",
            "@id": `${baseId}#product`,
            "name": name,
            "description": description,
            "url": baseUrl
        };
        if (featuredMediaUrl) {
            product.image = featuredMediaUrl;
        }
        if (price) {
            product.offers = {
                "@type": "Offer",
                "price": parseFloat(price),
                "priceCurrency": currency,
                "availability": `https://schema.org/${availability}`,
                "url": baseUrl
            };
        }
        if (rating) {
            product.aggregateRating = {
                "@type": "AggregateRating",
                "ratingValue": parseFloat(rating),
                "bestRating": 5,
                "ratingCount": 1
            };
        }
        graphs.push(mergeCustomProperties(meta, product));
    } else if (schemaType === 'faq') {
        let faqs = [];
        try {
            faqs = JSON.parse(meta._quinrel_schema_faq_questions || '[]');
        } catch (e) {
            faqs = [];
        }
        const mainEntities = faqs
            .filter(f => f.question && f.answer)
            .map(f => ({
                "@type": "Question",
                "name": f.question,
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": f.answer
                }
            }));
        
        if (mainEntities.length > 0) {
            graphs.push(mergeCustomProperties(meta, {
                "@type": "FAQPage",
                "@id": `${baseId}#faq`,
                "mainEntity": mainEntities
            }));
        }
    } else if (schemaType === 'howto') {
        const name = meta._quinrel_schema_howto_name || title || __('How-to Name', 'quinrel');
        const description = meta._quinrel_schema_howto_description || '';
        let steps = [];
        try {
            steps = JSON.parse(meta._quinrel_schema_howto_steps || '[]');
        } catch (e) {
            steps = [];
        }
        const suppliesText = meta._quinrel_schema_howto_supplies || '';
        const toolsText = meta._quinrel_schema_howto_tools || '';

        const howto = {
            "@type": "HowTo",
            "@id": `${baseId}#howto`,
            "name": name,
            "description": description
        };

        const stepElements = steps
            .filter(s => s.text)
            .map((s, idx) => ({
                "@type": "HowToStep",
                "name": s.name || `Step ${idx + 1}`,
                "text": s.text,
                "url": `${baseId}#step-${idx + 1}`
            }));
        if (stepElements.length > 0) {
            howto.step = stepElements;
        }

        if (suppliesText) {
            howto.supply = suppliesText.split(',').map(s => ({
                "@type": "HowToSupply",
                "name": s.trim()
            }));
        }

        if (toolsText) {
            howto.tool = toolsText.split(',').map(t => ({
                "@type": "HowToTool",
                "name": t.trim()
            }));
        }

        graphs.push(mergeCustomProperties(meta, howto));
    } else if (schemaType === 'localbusiness') {
        const name = meta._quinrel_schema_localbusiness_name || domain || __('Local Business', 'quinrel');
        const telephone = meta._quinrel_schema_localbusiness_telephone || '';
        const street = meta._quinrel_schema_localbusiness_street || '';
        const city = meta._quinrel_schema_localbusiness_city || '';
        const postal = meta._quinrel_schema_localbusiness_postal || '';
        const country = meta._quinrel_schema_localbusiness_country || 'US';

        const localbusiness = {
            "@type": "LocalBusiness",
            "@id": `${baseId}#localbusiness`,
            "name": name,
            "url": baseUrl,
            "telephone": telephone
        };
        if (featuredMediaUrl) {
            localbusiness.image = featuredMediaUrl;
        }
        if (street || city) {
            localbusiness.address = {
                "@type": "PostalAddress",
                "streetAddress": street,
                "addressLocality": city,
                "postalCode": postal,
                "addressCountry": country
            };
        }
        graphs.push(mergeCustomProperties(meta, localbusiness));
    } else if (schemaType === 'recipe') {
        const name = meta._quinrel_schema_recipe_name || title || __('Recipe Name', 'quinrel');
        const description = meta._quinrel_schema_recipe_description || '';
        const ingredientsText = meta._quinrel_schema_recipe_ingredients || '';
        let ingredients = [];
        if (ingredientsText) {
            try {
                const decoded = JSON.parse(ingredientsText);
                if (Array.isArray(decoded)) {
                    ingredients = decoded;
                } else {
                    ingredients = ingredientsText.split('\n').map(i => i.trim()).filter(Boolean);
                }
            } catch (e) {
                ingredients = ingredientsText.split('\n').map(i => i.trim()).filter(Boolean);
            }
        }

        let instructions = [];
        try {
            instructions = JSON.parse(meta._quinrel_schema_recipe_instructions || '[]');
        } catch (e) {
            instructions = [];
        }

        const prepTimeRaw = meta._quinrel_schema_recipe_prep_time || '';
        const cookTimeRaw = meta._quinrel_schema_recipe_cook_time || '';
        const calories = meta._quinrel_schema_recipe_calories || '';

        const recipe = {
            "@type": "Recipe",
            "@id": `${baseId}#recipe`,
            "name": name,
            "description": description,
            "url": baseUrl
        };

        if (featuredMediaUrl) {
            recipe.image = featuredMediaUrl;
        }

        if (ingredients.length > 0) {
            recipe.recipeIngredient = ingredients;
        }

        const stepElements = instructions
            .filter(s => s.text)
            .map((s, idx) => ({
                "@type": "HowToStep",
                "name": s.name || `Step ${idx + 1}`,
                "text": s.text
            }));
        if (stepElements.length > 0) {
            recipe.recipeInstructions = stepElements;
        }

        const prepIso = convertToIsoDuration(prepTimeRaw);
        if (prepIso) {
            recipe.prepTime = prepIso;
        }

        const cookIso = convertToIsoDuration(cookTimeRaw);
        if (cookIso) {
            recipe.cookTime = cookIso;
        }

        if (calories) {
            recipe.nutrition = {
                "@type": "NutritionInformation",
                "calories": calories + " calories"
            };
        }

        graphs.push(mergeCustomProperties(meta, recipe));
    } else if (schemaType === 'event') {
        const name = meta._quinrel_schema_event_name || title || __('Event Name', 'quinrel');
        const startDate = meta._quinrel_schema_event_start_date || '';
        const endDate = meta._quinrel_schema_event_end_date || '';
        const venue = meta._quinrel_schema_event_venue || 'Online';
        const address = meta._quinrel_schema_event_address || '';
        const performerName = meta._quinrel_schema_event_performer || '';
        const price = meta._quinrel_schema_event_price || '';
        const currency = meta._quinrel_schema_event_currency || 'USD';

        const event = {
            "@type": "Event",
            "@id": `${baseId}#event`,
            "name": name,
            "url": baseUrl,
            "eventStatus": "https://schema.org/EventScheduled",
            "eventAttendanceMode": address ? "https://schema.org/OfflineEventAttendanceMode" : "https://schema.org/OnlineEventAttendanceMode"
        };

        if (featuredMediaUrl) {
            event.image = featuredMediaUrl;
        }

        if (startDate) {
            try {
                event.startDate = new Date(startDate).toISOString();
            } catch (e) {
                event.startDate = startDate;
            }
        }

        if (endDate) {
            try {
                event.endDate = new Date(endDate).toISOString();
            } catch (e) {
                event.endDate = endDate;
            }
        }

        if (address) {
            event.location = {
                "@type": "Place",
                "name": venue,
                "address": {
                    "@type": "PostalAddress",
                    "streetAddress": address
                }
            };
        } else {
            event.location = {
                "@type": "VirtualLocation",
                "url": baseUrl
            };
        }

        if (performerName) {
            event.performer = {
                "@type": "Person",
                "name": performerName
            };
        }

        if (price) {
            event.offers = {
                "@type": "Offer",
                "price": parseFloat(price),
                "priceCurrency": currency,
                "availability": "https://schema.org/InStock",
                "url": baseUrl
            };
        }

        graphs.push(mergeCustomProperties(meta, event));
    } else if (schemaType === 'podcast') {
        const podcast = {
            "@type": "PodcastEpisode",
            "@id": `${baseId}#podcastepisode`,
            "name": meta._quinrel_podcast_title || effectiveTitle,
            "description": meta._quinrel_podcast_description || wpStripAllTags(content || '').slice(0, 160),
            "url": baseUrl
        };
        if (meta._quinrel_podcast_episode_number) podcast.episodeNumber = parseInt(meta._quinrel_podcast_episode_number, 10);
        if (meta._quinrel_podcast_season_number) podcast.seasonNumber = parseInt(meta._quinrel_podcast_season_number, 10);
        if (meta._quinrel_podcast_episode_type) podcast.episodeType = meta._quinrel_podcast_episode_type;
        if (meta._quinrel_podcast_series_name) {
            podcast.partOfSeries = {
                "@type": "PodcastSeries",
                "name": meta._quinrel_podcast_series_name,
                "url": meta._quinrel_podcast_series_url || domain
            };
        }
        if (meta._quinrel_podcast_audio_url) {
            podcast.associatedMedia = {
                "@type": "AudioObject",
                "contentUrl": meta._quinrel_podcast_audio_url,
                "encodingFormat": "audio/mpeg"
            };
            if (meta._quinrel_podcast_duration) podcast.associatedMedia.duration = meta._quinrel_podcast_duration;
            if (meta._quinrel_podcast_transcript) podcast.associatedMedia.transcript = meta._quinrel_podcast_transcript;
        }
        graphs.push(mergeCustomProperties(meta, podcast));
    } else if (schemaType === 'course') {
        const course = {
            "@type": "Course",
            "@id": `${baseId}#course`,
            "name": meta._quinrel_schema_course_name || effectiveTitle,
            "description": meta._quinrel_schema_course_description || wpStripAllTags(content || '').slice(0, 160),
            "url": baseUrl,
            "provider": {
                "@type": "Organization",
                "name": meta._quinrel_schema_course_provider || domain,
                "sameAs": domain
            }
        };
        if (meta._quinrel_schema_course_code) course.courseCode = meta._quinrel_schema_course_code;
        if (meta._quinrel_schema_course_level) course.educationalLevel = meta._quinrel_schema_course_level;
        if (meta._quinrel_schema_course_price) {
            course.offers = {
                "@type": "Offer",
                "price": parseFloat(meta._quinrel_schema_course_price),
                "priceCurrency": meta._quinrel_schema_course_currency || 'USD'
            };
        }
        graphs.push(mergeCustomProperties(meta, course));
    } else if (schemaType === 'jobposting') {
        const job = {
            "@type": "JobPosting",
            "@id": `${baseId}#jobposting`,
            "title": meta._quinrel_schema_job_title || effectiveTitle,
            "description": meta._quinrel_schema_job_description || wpStripAllTags(content || ''),
            "employmentType": meta._quinrel_schema_job_employment_type || 'FULL_TIME',
            "hiringOrganization": {
                "@type": "Organization",
                "name": meta._quinrel_schema_job_company || domain,
                "sameAs": meta._quinrel_schema_job_company_url || domain
            }
        };
        if (meta._quinrel_schema_job_location_type === 'REMOTE') {
            job.jobLocationType = 'TELECOMMUTE';
            job.applicantLocationRequirements = {
                "@type": "Country",
                "name": meta._quinrel_schema_job_country || 'Worldwide'
            };
        } else {
            job.jobLocation = {
                "@type": "Place",
                "address": {
                    "@type": "PostalAddress",
                    "streetAddress": meta._quinrel_schema_job_street || '',
                    "addressLocality": meta._quinrel_schema_job_city || '',
                    "addressCountry": meta._quinrel_schema_job_country || ''
                }
            };
        }
        if (meta._quinrel_schema_job_salary) {
            job.baseSalary = {
                "@type": "MonetaryAmount",
                "currency": meta._quinrel_schema_job_salary_currency || 'USD',
                "value": {
                    "@type": "QuantitativeValue",
                    "value": parseFloat(meta._quinrel_schema_job_salary),
                    "unitText": meta._quinrel_schema_job_salary_unit || 'YEAR'
                }
            };
        }
        graphs.push(mergeCustomProperties(meta, job));
    } else if (schemaType === 'book') {
        const book = {
            "@type": "Book",
            "@id": `${baseId}#book`,
            "name": meta._quinrel_schema_book_name || effectiveTitle,
            "url": baseUrl,
            "bookFormat": `https://schema.org/${meta._quinrel_schema_book_format || 'Paperback'}`
        };
        if (meta._quinrel_schema_book_author) book.author = { "@type": "Person", "name": meta._quinrel_schema_book_author };
        if (meta._quinrel_schema_book_isbn) book.isbn = meta._quinrel_schema_book_isbn;
        if (meta._quinrel_schema_book_publisher) book.publisher = { "@type": "Organization", "name": meta._quinrel_schema_book_publisher };
        graphs.push(mergeCustomProperties(meta, book));
    } else if (schemaType === 'software') {
        const sw = {
            "@type": "SoftwareApplication",
            "@id": `${baseId}#software`,
            "name": meta._quinrel_schema_software_name || effectiveTitle,
            "operatingSystem": meta._quinrel_schema_software_os || 'Web',
            "applicationCategory": meta._quinrel_schema_software_category || 'BusinessApplication',
            "url": baseUrl
        };
        if (meta._quinrel_schema_software_price) {
            sw.offers = {
                "@type": "Offer",
                "price": parseFloat(meta._quinrel_schema_software_price),
                "priceCurrency": meta._quinrel_schema_software_currency || 'USD'
            };
        }
        graphs.push(mergeCustomProperties(meta, sw));
    } else if (schemaType === 'restaurant') {
        const rest = {
            "@type": "Restaurant",
            "@id": `${baseId}#restaurant`,
            "name": meta._quinrel_schema_restaurant_name || effectiveTitle,
            "servesCuisine": meta._quinrel_schema_restaurant_cuisine || '',
            "priceRange": meta._quinrel_schema_restaurant_price_range || '$$',
            "url": baseUrl,
            "address": {
                "@type": "PostalAddress",
                "streetAddress": meta._quinrel_schema_restaurant_street || '',
                "addressLocality": meta._quinrel_schema_restaurant_city || '',
                "addressCountry": meta._quinrel_schema_restaurant_country || ''
            }
        };
        if (meta._quinrel_schema_restaurant_telephone) rest.telephone = meta._quinrel_schema_restaurant_telephone;
        if (meta._quinrel_schema_restaurant_menu_url) rest.hasMenu = meta._quinrel_schema_restaurant_menu_url;
        graphs.push(mergeCustomProperties(meta, rest));
    } else if (schemaType === 'service') {
        const srv = {
            "@type": "Service",
            "@id": `${baseId}#service`,
            "name": meta._quinrel_schema_service_name || effectiveTitle,
            "serviceType": meta._quinrel_schema_service_type || '',
            "provider": {
                "@type": "Organization",
                "name": meta._quinrel_schema_service_provider || domain
            }
        };
        graphs.push(mergeCustomProperties(meta, srv));
    } else if (schemaType === 'review') {
        const rev = {
            "@type": "Review",
            "@id": `${baseId}#review`,
            "itemReviewed": {
                "@type": meta._quinrel_schema_review_item_type || 'Product',
                "name": meta._quinrel_schema_review_item_name || effectiveTitle
            },
            "reviewRating": {
                "@type": "Rating",
                "ratingValue": parseFloat(meta._quinrel_schema_review_rating || '5'),
                "bestRating": 5,
                "worstRating": 1
            },
            "author": {
                "@type": "Person",
                "name": meta._quinrel_schema_review_author || 'Reviewer'
            },
            "reviewBody": meta._quinrel_schema_review_body || ''
        };
        graphs.push(mergeCustomProperties(meta, rev));
    } else if (schemaType === 'movie') {
        const mov = {
            "@type": "Movie",
            "@id": `${baseId}#movie`,
            "name": meta._quinrel_schema_movie_name || effectiveTitle,
            "url": baseUrl
        };
        if (meta._quinrel_schema_movie_director) mov.director = { "@type": "Person", "name": meta._quinrel_schema_movie_director };
        graphs.push(mergeCustomProperties(meta, mov));
    } else if (schemaType === 'music') {
        const mus = {
            "@type": "MusicRecording",
            "@id": `${baseId}#music`,
            "name": meta._quinrel_schema_music_name || effectiveTitle,
            "url": baseUrl
        };
        if (meta._quinrel_schema_music_artist) mus.byArtist = { "@type": "MusicGroup", "name": meta._quinrel_schema_music_artist };
        graphs.push(mergeCustomProperties(meta, mus));
    } else if (schemaType === 'dataset') {
        const ds = {
            "@type": "Dataset",
            "@id": `${baseId}#dataset`,
            "name": meta._quinrel_schema_dataset_name || effectiveTitle,
            "description": meta._quinrel_schema_dataset_description || wpStripAllTags(content || '').slice(0, 160),
            "url": baseUrl,
            "creator": {
                "@type": "Organization",
                "name": meta._quinrel_schema_dataset_creator || domain
            }
        };
        if (meta._quinrel_schema_dataset_download_url) {
            ds.distribution = {
                "@type": "DataDownload",
                "contentUrl": meta._quinrel_schema_dataset_download_url,
                "encodingFormat": meta._quinrel_schema_dataset_format || 'text/csv'
            };
        }
        graphs.push(mergeCustomProperties(meta, ds));
    } else if (schemaType === 'person') {
        const pers = {
            "@type": "Person",
            "@id": `${baseId}#person`,
            "name": meta._quinrel_schema_person_name || effectiveTitle,
            "url": meta._quinrel_schema_person_url || baseUrl
        };
        if (meta._quinrel_schema_person_job_title) pers.jobTitle = meta._quinrel_schema_person_job_title;
        if (meta._quinrel_schema_person_works_for) pers.worksFor = { "@type": "Organization", "name": meta._quinrel_schema_person_works_for };
        graphs.push(mergeCustomProperties(meta, pers));
    } else if (schemaType === 'custom') {
        const custom = {
            "@type": meta._quinrel_schema_custom_type || 'Thing',
            "@id": `${baseId}#custom`
        };
        graphs.push(mergeCustomProperties(meta, custom));
    }

    return {
        "@context": "https://schema.org",
        "@graph": graphs
    };
};

const auditSchema = (schemaType, meta, title, featuredMediaUrl) => {
    const checks = [];
    let score = 100;

    if (!schemaType || schemaType === 'none') {
        return { score: 100, warnings: [], checks: [] };
    }

    if (schemaType === 'product') {
        const hasName = !!meta._quinrel_schema_product_name || !!title;
        checks.push({ label: __('Product Name', 'quinrel'), pass: hasName, required: true });
        if (!hasName) score -= 30;

        const hasPrice = !!meta._quinrel_schema_product_price;
        checks.push({ label: __('Offer Price', 'quinrel'), pass: hasPrice, required: true });
        if (!hasPrice) score -= 30;

        const hasCurrency = !!meta._quinrel_schema_product_currency;
        checks.push({ label: __('Currency', 'quinrel'), pass: hasCurrency, required: true });
        if (!hasCurrency) score -= 10;

        const hasRating = !!meta._quinrel_schema_product_rating;
        checks.push({ label: __('Aggregate Rating', 'quinrel'), pass: hasRating, required: false });
        if (!hasRating) score -= 15;

        const hasImage = !!featuredMediaUrl;
        checks.push({ label: __('Product Image', 'quinrel'), pass: hasImage, required: false });
        if (!hasImage) score -= 15;
    } else if (schemaType === 'faq') {
        let faqs = [];
        try {
            faqs = JSON.parse(meta._quinrel_schema_faq_questions || '[]');
        } catch (e) {
            faqs = [];
        }
        const hasEntries = faqs.length > 0;
        checks.push({ label: __('FAQ Entries added', 'quinrel'), pass: hasEntries, required: true });
        if (!hasEntries) {
            score -= 50;
        } else {
            const hasCompleteFaqs = faqs.every(f => f.question && f.answer);
            checks.push({ label: __('All FAQ entries fully filled', 'quinrel'), pass: hasCompleteFaqs, required: true });
            if (!hasCompleteFaqs) score -= 50;
        }
    } else if (schemaType === 'howto') {
        const hasName = !!meta._quinrel_schema_howto_name || !!title;
        checks.push({ label: __('HowTo Name', 'quinrel'), pass: hasName, required: true });
        if (!hasName) score -= 25;

        const hasDesc = !!meta._quinrel_schema_howto_description;
        checks.push({ label: __('HowTo Description', 'quinrel'), pass: hasDesc, required: false });
        if (!hasDesc) score -= 20;

        let steps = [];
        try {
            steps = JSON.parse(meta._quinrel_schema_howto_steps || '[]');
        } catch (e) {
            steps = [];
        }
        const hasSteps = steps.length > 0;
        checks.push({ label: __('At least one HowTo step added', 'quinrel'), pass: hasSteps, required: true });
        if (!hasSteps) {
            score -= 25;
        } else {
            const hasCompleteSteps = steps.every(s => s.text);
            checks.push({ label: __('All HowTo steps have instructions', 'quinrel'), pass: hasCompleteSteps, required: true });
            if (!hasCompleteSteps) score -= 25;
        }

        const hasTools = !!meta._quinrel_schema_howto_tools;
        checks.push({ label: __('Recommended Tools configured', 'quinrel'), pass: hasTools, required: false });
        if (!hasTools) score -= 15;

        const hasSupplies = !!meta._quinrel_schema_howto_supplies;
        checks.push({ label: __('Recommended Supplies configured', 'quinrel'), pass: hasSupplies, required: false });
        if (!hasSupplies) score -= 15;
    } else if (schemaType === 'localbusiness') {
        const hasName = !!meta._quinrel_schema_localbusiness_name;
        checks.push({ label: __('Business Name', 'quinrel'), pass: hasName, required: true });
        if (!hasName) score -= 30;

        const hasPhone = !!meta._quinrel_schema_localbusiness_telephone;
        checks.push({ label: __('Telephone Contact', 'quinrel'), pass: hasPhone, required: false });
        if (!hasPhone) score -= 20;

        const hasStreet = !!meta._quinrel_schema_localbusiness_street;
        checks.push({ label: __('Street Address', 'quinrel'), pass: hasStreet, required: true });
        if (!hasStreet) score -= 20;

        const hasCity = !!meta._quinrel_schema_localbusiness_city;
        checks.push({ label: __('Address City', 'quinrel'), pass: hasCity, required: true });
        if (!hasCity) score -= 15;

        const hasZip = !!meta._quinrel_schema_localbusiness_postal;
        checks.push({ label: __('Postal/Zip Code', 'quinrel'), pass: hasZip, required: true });
        if (!hasZip) score -= 15;
    } else if (schemaType === 'recipe') {
        const hasName = !!meta._quinrel_schema_recipe_name || !!title;
        checks.push({ label: __('Recipe Name', 'quinrel'), pass: hasName, required: true });
        if (!hasName) score -= 25;

        const hasIngredients = !!meta._quinrel_schema_recipe_ingredients;
        checks.push({ label: __('Ingredients', 'quinrel'), pass: hasIngredients, required: true });
        if (!hasIngredients) score -= 25;

        let instructions = [];
        try {
            instructions = JSON.parse(meta._quinrel_schema_recipe_instructions || '[]');
        } catch (e) {}
        const hasInstructions = instructions.length > 0;
        checks.push({ label: __('Instructions', 'quinrel'), pass: hasInstructions, required: true });
        if (!hasInstructions) score -= 25;

        const hasPrepTime = !!meta._quinrel_schema_recipe_prep_time;
        checks.push({ label: __('Prep Time', 'quinrel'), pass: hasPrepTime, required: false });
        if (!hasPrepTime) score -= 10;
        
        const hasCookTime = !!meta._quinrel_schema_recipe_cook_time;
        checks.push({ label: __('Cook Time', 'quinrel'), pass: hasCookTime, required: false });
        if (!hasCookTime) score -= 10;

        const hasImage = !!featuredMediaUrl;
        checks.push({ label: __('Cover Image', 'quinrel'), pass: hasImage, required: true });
        if (!hasImage) score -= 15;
    } else if (schemaType === 'event') {
        const hasName = !!meta._quinrel_schema_event_name || !!title;
        checks.push({ label: __('Event Name', 'quinrel'), pass: hasName, required: true });
        if (!hasName) score -= 20;

        const hasStartDate = !!meta._quinrel_schema_event_start_date;
        checks.push({ label: __('Start Date', 'quinrel'), pass: hasStartDate, required: true });
        if (!hasStartDate) score -= 30;

        const hasVenue = !!meta._quinrel_schema_event_venue;
        checks.push({ label: __('Venue Name', 'quinrel'), pass: hasVenue, required: true });
        if (!hasVenue) score -= 20;

        const hasAddress = !!meta._quinrel_schema_event_address;
        checks.push({ label: __('Location Address', 'quinrel'), pass: hasAddress, required: true });
        if (!hasAddress) score -= 15;

        const hasPrice = !!meta._quinrel_schema_event_price;
        checks.push({ label: __('Ticket Price', 'quinrel'), pass: hasPrice, required: false });
        if (!hasPrice) score -= 15;
    } else if (schemaType === 'podcast') {
        const hasTitle = !!meta._quinrel_podcast_title || !!title;
        checks.push({ label: __('Episode Title', 'quinrel'), pass: hasTitle, required: true });
        if (!hasTitle) score -= 30;

        const hasAudio = !!meta._quinrel_podcast_audio_url || (content && content.includes('audio'));
        checks.push({ label: __('Audio URL or Audio Block', 'quinrel'), pass: hasAudio, required: true });
        if (!hasAudio) score -= 40;

        const hasSeries = !!meta._quinrel_podcast_series_name;
        checks.push({ label: __('Podcast Series Name', 'quinrel'), pass: hasSeries, required: false });
        if (!hasSeries) score -= 15;

        const hasDuration = !!meta._quinrel_podcast_duration;
        checks.push({ label: __('Episode Duration', 'quinrel'), pass: hasDuration, required: false });
        if (!hasDuration) score -= 15;
    } else if (schemaType === 'course') {
        const hasName = !!meta._quinrel_schema_course_name || !!title;
        checks.push({ label: __('Course Title', 'quinrel'), pass: hasName, required: true });
        if (!hasName) score -= 40;

        const hasDesc = !!meta._quinrel_schema_course_description || (content && content.length > 50);
        checks.push({ label: __('Course Description', 'quinrel'), pass: hasDesc, required: true });
        if (!hasDesc) score -= 30;

        const hasProvider = !!meta._quinrel_schema_course_provider;
        checks.push({ label: __('Course Provider Organization', 'quinrel'), pass: hasProvider, required: false });
        if (!hasProvider) score -= 30;
    } else if (schemaType === 'jobposting') {
        const hasTitle = !!meta._quinrel_schema_job_title || !!title;
        checks.push({ label: __('Job Title', 'quinrel'), pass: hasTitle, required: true });
        if (!hasTitle) score -= 30;

        const hasCompany = !!meta._quinrel_schema_job_company;
        checks.push({ label: __('Hiring Organization', 'quinrel'), pass: hasCompany, required: true });
        if (!hasCompany) score -= 30;

        const hasLoc = meta._quinrel_schema_job_location_type === 'REMOTE' || !!meta._quinrel_schema_job_city;
        checks.push({ label: __('Job Location or Remote Specification', 'quinrel'), pass: hasLoc, required: true });
        if (!hasLoc) score -= 25;

        const hasSalary = !!meta._quinrel_schema_job_salary;
        checks.push({ label: __('Base Salary', 'quinrel'), pass: hasSalary, required: false });
        if (!hasSalary) score -= 15;
    } else if (schemaType === 'book') {
        const hasName = !!meta._quinrel_schema_book_name || !!title;
        checks.push({ label: __('Book Title', 'quinrel'), pass: hasName, required: true });
        if (!hasName) score -= 40;

        const hasAuthor = !!meta._quinrel_schema_book_author;
        checks.push({ label: __('Book Author', 'quinrel'), pass: hasAuthor, required: false });
        if (!hasAuthor) score -= 30;

        const hasIsbn = !!meta._quinrel_schema_book_isbn;
        checks.push({ label: __('Book ISBN', 'quinrel'), pass: hasIsbn, required: false });
        if (!hasIsbn) score -= 30;
    } else if (schemaType === 'software') {
        const hasName = !!meta._quinrel_schema_software_name || !!title;
        checks.push({ label: __('Application Name', 'quinrel'), pass: hasName, required: true });
        if (!hasName) score -= 40;

        const hasOs = !!meta._quinrel_schema_software_os;
        checks.push({ label: __('Operating System', 'quinrel'), pass: hasOs, required: true });
        if (!hasOs) score -= 30;

        const hasCat = !!meta._quinrel_schema_software_category;
        checks.push({ label: __('Application Category', 'quinrel'), pass: hasCat, required: false });
        if (!hasCat) score -= 30;
    } else if (schemaType === 'restaurant') {
        const hasName = !!meta._quinrel_schema_restaurant_name || !!title;
        checks.push({ label: __('Restaurant Name', 'quinrel'), pass: hasName, required: true });
        if (!hasName) score -= 35;

        const hasCuisine = !!meta._quinrel_schema_restaurant_cuisine;
        checks.push({ label: __('Cuisine Type', 'quinrel'), pass: hasCuisine, required: false });
        if (!hasCuisine) score -= 25;

        const hasStreet = !!meta._quinrel_schema_restaurant_street;
        checks.push({ label: __('Street Address', 'quinrel'), pass: hasStreet, required: true });
        if (!hasStreet) score -= 40;
    } else if (schemaType === 'service') {
        const hasName = !!meta._quinrel_schema_service_name || !!title;
        checks.push({ label: __('Service Name', 'quinrel'), pass: hasName, required: true });
        if (!hasName) score -= 40;

        const hasType = !!meta._quinrel_schema_service_type;
        checks.push({ label: __('Service Type', 'quinrel'), pass: hasType, required: false });
        if (!hasType) score -= 30;

        const hasProvider = !!meta._quinrel_schema_service_provider;
        checks.push({ label: __('Service Provider', 'quinrel'), pass: hasProvider, required: false });
        if (!hasProvider) score -= 30;
    } else if (schemaType === 'review') {
        const hasItem = !!meta._quinrel_schema_review_item_name || !!title;
        checks.push({ label: __('Reviewed Item Name', 'quinrel'), pass: hasItem, required: true });
        if (!hasItem) score -= 40;

        const hasRating = !!meta._quinrel_schema_review_rating;
        checks.push({ label: __('Review Rating', 'quinrel'), pass: hasRating, required: true });
        if (!hasRating) score -= 30;

        const hasBody = !!meta._quinrel_schema_review_body || (content && content.length > 50);
        checks.push({ label: __('Review Body', 'quinrel'), pass: hasBody, required: false });
        if (!hasBody) score -= 30;
    } else if (schemaType === 'movie') {
        const hasName = !!meta._quinrel_schema_movie_name || !!title;
        checks.push({ label: __('Movie Title', 'quinrel'), pass: hasName, required: true });
        if (!hasName) score -= 50;

        const hasDirector = !!meta._quinrel_schema_movie_director;
        checks.push({ label: __('Movie Director', 'quinrel'), pass: hasDirector, required: false });
        if (!hasDirector) score -= 50;
    } else if (schemaType === 'music') {
        const hasName = !!meta._quinrel_schema_music_name || !!title;
        checks.push({ label: __('Track Title', 'quinrel'), pass: hasName, required: true });
        if (!hasName) score -= 50;

        const hasArtist = !!meta._quinrel_schema_music_artist;
        checks.push({ label: __('Recording Artist', 'quinrel'), pass: hasArtist, required: false });
        if (!hasArtist) score -= 50;
    } else if (schemaType === 'dataset') {
        const hasName = !!meta._quinrel_schema_dataset_name || !!title;
        checks.push({ label: __('Dataset Name', 'quinrel'), pass: hasName, required: true });
        if (!hasName) score -= 40;

        const hasDesc = !!meta._quinrel_schema_dataset_description || (content && content.length > 50);
        checks.push({ label: __('Dataset Description', 'quinrel'), pass: hasDesc, required: true });
        if (!hasDesc) score -= 30;

        const hasLicense = !!meta._quinrel_schema_dataset_license;
        checks.push({ label: __('Dataset License', 'quinrel'), pass: hasLicense, required: false });
        if (!hasLicense) score -= 30;
    } else if (schemaType === 'person') {
        const hasName = !!meta._quinrel_schema_person_name || !!title;
        checks.push({ label: __('Person Name', 'quinrel'), pass: hasName, required: true });
        if (!hasName) score -= 50;

        const hasJob = !!meta._quinrel_schema_person_job_title;
        checks.push({ label: __('Job Title', 'quinrel'), pass: hasJob, required: false });
        if (!hasJob) score -= 50;
    } else if (schemaType === 'custom') {
        const hasType = !!meta._quinrel_schema_custom_type;
        checks.push({ label: __('Schema Type', 'quinrel'), pass: hasType, required: true });
        if (!hasType) score -= 40;

        let props = [];
        try {
            props = JSON.parse(meta._quinrel_custom_schema_properties || '[]');
        } catch(e){}
        const hasProps = props.length > 0 && props.some(p => p.key && p.value);
        checks.push({ label: __('Custom Properties', 'quinrel'), pass: hasProps, required: true });
        if (!hasProps) score -= 60;
    }

    score = Math.max(0, score);
    const warnings = checks.filter(c => !c.pass).map(c => sprintf(
        c.required
            ? __('Required field "%s" is missing.', 'quinrel')
            : __('Recommended field "%s" is missing.', 'quinrel'),
        c.label
    ));

    return {
        score,
        warnings,
        checks
    };
};

// ── Schema Validator React Component (SD-003) ──
const SchemaValidator = ({ schemaType, meta, title, content, featuredMediaUrl, permalink, domain }) => {
    const [isOpen, setIsOpen] = useState(false);
    const [copied, setCopied] = useState(false);

    if (!schemaType || schemaType === 'none') {
        return null;
    }

    const ldJson = useMemo(() => {
        return generateLdJson(schemaType, meta, title, content, featuredMediaUrl, permalink, domain);
    }, [schemaType, meta, title, content, featuredMediaUrl, permalink, domain]);

    const audit = useMemo(() => {
        return auditSchema(schemaType, meta, title, featuredMediaUrl);
    }, [schemaType, meta, title, featuredMediaUrl]);

    const jsonString = ldJson ? JSON.stringify(ldJson, null, 2) : '';

    const handleCopy = () => {
        navigator.clipboard.writeText(`<script type="application/ld+json">\n${jsonString}\n</script>`);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const handleTest = () => {
        navigator.clipboard.writeText(jsonString);
        alert(__('Schema JSON-LD code copied to clipboard! Opening Google Rich Results Test in a new tab. Paste your code in the "Code" tab to validate.', 'quinrel'));
        window.open('https://search.google.com/test/rich-results', '_blank');
    };

    let healthColor = '#22c55e';
    if (audit.score < 50) healthColor = '#ef4444';
    else if (audit.score < 80) healthColor = '#f59e0b';

    return (
        <div style={{ marginTop: '16px', borderTop: '1px solid #e2e8f0', paddingTop: '12px' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
                <div style={{ fontSize: '11px', color: '#64748b', fontWeight: 'bold', textTransform: 'uppercase' }}>
                    {__('Schema Validator & Health', 'quinrel')}
                </div>
                <div style={{ fontSize: '12px', fontWeight: '600', color: healthColor }}>
                    Health: {audit.score}%
                </div>
            </div>

            {audit.warnings.length > 0 ? (
                <div style={{ background: '#fef2f2', border: '1px solid #fee2e2', borderRadius: '6px', padding: '8px 10px', fontSize: '12px', color: '#991b1b', marginBottom: '10px' }}>
                    <div style={{ fontWeight: '600', marginBottom: '4px' }}>{__('Schema Recommendations:', 'quinrel')}</div>
                    <ul style={{ margin: 0, paddingLeft: '14px', listStyleType: 'disc' }}>
                        {audit.warnings.map((w, idx) => (
                            <li key={idx} style={{ marginBottom: '2px' }}>{w}</li>
                        ))}
                    </ul>
                </div>
            ) : (
                <div style={{ background: '#f0fdf4', border: '1px solid #dcfce7', borderRadius: '6px', padding: '8px 10px', fontSize: '12px', color: '#166534', fontWeight: '500', marginBottom: '10px', display: 'flex', gap: '6px', alignItems: 'center' }}>
                    <span>✓</span> All required and recommended schema fields are configured.
                </div>
            )}

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px', marginBottom: '12px' }}>
                <Button isSecondary onClick={handleCopy} style={{ fontSize: '12px', padding: '6px 8px', justifyContent: 'center' }}>
                    {copied ? '✓ Copied!' : 'Copy Schema'}
                </Button>
                <Button isPrimary onClick={handleTest} style={{ fontSize: '12px', padding: '6px 8px', justifyContent: 'center' }}>
                    {__('Test on Google', 'quinrel')}
                </Button>
            </div>

            <div style={{ fontSize: '12px', color: '#475569' }}>
                <span onClick={() => setIsOpen(!isOpen)} style={{ textDecoration: 'underline', cursor: 'pointer', fontWeight: '500' }}>
                    {isOpen ? 'Hide generated JSON-LD code' : 'View generated JSON-LD code'}
                </span>
            </div>

            {isOpen && (
                <textarea
                    readOnly
                    value={jsonString}
                    style={{
                        width: '100%',
                        height: '180px',
                        fontFamily: 'monospace',
                        fontSize: '11px',
                        padding: '6px',
                        marginTop: '8px',
                        background: '#1e293b',
                        color: '#f8fafc',
                        border: '1px solid #0f172a',
                        borderRadius: '4px',
                        resize: 'none'
                    }}
                />
            )}
        </div>
    );
};

// ── Main Sidebar Component ──────────────────────────────────
// ── Unlimited Keywords Editor ─────────────────────────────────────────────
// Allows adding N additional focus keywords beyond the primary one.
// Each keyword is independently analyzed against the content.
const UnlimitedKeywordsEditor = ({ primaryKeyword, additionalKeywordsJson, content, title, metaTitle, metaDescription, updateMeta }) => {
    const additional = useMemo(() => {
        try { return JSON.parse(additionalKeywordsJson || '[]'); } catch (e) { return []; }
    }, [additionalKeywordsJson]);

    const addKeyword = () => {
        updateMeta('_quinrel_additional_keywords', JSON.stringify([...additional, '']));
    };

    const updateKeyword = (index, value) => {
        const next = [...additional];
        next[index] = value;
        updateMeta('_quinrel_additional_keywords', JSON.stringify(next));
    };

    const removeKeyword = (index) => {
        updateMeta('_quinrel_additional_keywords', JSON.stringify(additional.filter((_, i) => i !== index)));
    };

    // Mini-analysis for each additional keyword
    const analyzeKw = useCallback((kw) => {
        if (!kw) return null;
        const kwLower = kw.toLowerCase();
        const contentLower = stripHtml(content).toLowerCase();
        const titleLower = (metaTitle || title).toLowerCase();
        const descLower = metaDescription.toLowerCase();
        const inTitle = titleLower.includes(kwLower);
        const inDesc = descLower.includes(kwLower);
        const inContent = contentLower.includes(kwLower);
        const density = inContent
            ? Math.round(((contentLower.split(kwLower).length - 1) / Math.max(1, contentLower.split(/\s+/).length)) * 100 * 10) / 10
            : 0;
        const score = (inTitle ? 34 : 0) + (inDesc ? 33 : 0) + (inContent ? 33 : 0);
        return { inTitle, inDesc, inContent, density, score };
    }, [content, title, metaTitle, metaDescription]);

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '10px', marginTop: '12px' }}>
            <div style={{ fontSize: '11px', color: '#64748b', fontWeight: '700', textTransform: 'uppercase', letterSpacing: '0.05em' }}>
                {__('Additional Focus Keywords', 'quinrel')}
            </div>

            {additional.map((kw, idx) => {
                const analysis = analyzeKw(kw);
                return (
                    <div key={idx} style={{ background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: '8px', padding: '10px' }}>
                        <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                            <input
                                type="text"
                                value={kw}
                                placeholder={`Keyword ${idx + 2}…`}
                                onChange={(e) => updateKeyword(idx, e.target.value)}
                                style={{ flex: 1, padding: '6px 10px', border: '1px solid #cbd5e1', borderRadius: '6px', fontSize: '13px', outline: 'none' }}
                            />
                            <button
                                type="button"
                                onClick={() => removeKeyword(idx)}
                                style={{ background: 'none', border: 'none', color: '#ef4444', cursor: 'pointer', fontSize: '18px', lineHeight: 1, padding: '0 4px' }}
                                title={__('Remove', 'quinrel')}
                            >×</button>
                        </div>
                        {kw && analysis && (
                            <div style={{ display: 'flex', gap: '6px', marginTop: '8px', flexWrap: 'wrap' }}>
                                {[['Title', analysis.inTitle], ['Desc', analysis.inDesc], ['Content', analysis.inContent]].map(([label, pass]) => (
                                    <span key={label} style={{
                                        fontSize: '10px', fontWeight: '600', padding: '2px 6px', borderRadius: '99px',
                                        background: pass ? '#dcfce7' : '#fee2e2',
                                        color: pass ? '#166534' : '#991b1b'
                                    }}>{pass ? '✓' : '✗'} {label}</span>
                                ))}
                                <span style={{ fontSize: '10px', color: '#64748b', padding: '2px 6px', background: '#f1f5f9', borderRadius: '99px' }}>
                                    Density: {analysis.density}%
                                </span>
                                <span style={{
                                    fontSize: '10px', fontWeight: '600', padding: '2px 6px', borderRadius: '99px',
                                    background: analysis.score >= 66 ? '#dcfce7' : analysis.score >= 33 ? '#fef9c3' : '#fee2e2',
                                    color: analysis.score >= 66 ? '#166534' : analysis.score >= 33 ? '#854d0e' : '#991b1b'
                                }}>{analysis.score}%</span>
                            </div>
                        )}
                    </div>
                );
            })}

            <Button
                isSecondary
                onClick={addKeyword}
                style={{ alignSelf: 'flex-start', fontSize: '12px' }}
            >
                {__('+ Add Keyword', 'quinrel')}
            </Button>
        </div>
    );
};

// ── PageSpeed Badge ─────────────────────────────────────────────────────────
// Displays cached PageSpeed Insights scores (mobile + desktop) and lets
// the user refresh them on demand via the PSI API REST proxy.
const PageSpeedBadge = ({ postId, cachedJson, updateMeta }) => {
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState(null);

    const cached = useMemo(() => {
        try { return JSON.parse(cachedJson || 'null'); } catch (e) { return null; }
    }, [cachedJson]);

    const fetchPageSpeed = async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await fetch(`${quinrelEditorConfig.apiUrl}/pagespeed?post_id=${postId}`, {
                headers: { 'X-WP-Nonce': quinrelEditorConfig.nonce }
            });
            const data = await res.json();
            if (res.ok && data.success) {
                updateMeta('_quinrel_pagespeed_cache', JSON.stringify(data.scores));
            } else {
                setError(data.message || __('Failed to fetch PageSpeed data.', 'quinrel'));
            }
        } catch (e) {
            setError(__('Network error.', 'quinrel'));
        } finally {
            setIsLoading(false);
        }
    };

    const ScoreCircle = ({ label, score }) => {
        const color = score >= 90 ? '#22c55e' : score >= 50 ? '#f59e0b' : '#ef4444';
        return (
            <div style={{ textAlign: 'center' }}>
                <div style={{
                    width: '52px', height: '52px', borderRadius: '50%',
                    border: `4px solid ${color}`, display: 'flex',
                    alignItems: 'center', justifyContent: 'center',
                    fontSize: '14px', fontWeight: '700', color,
                    margin: '0 auto 4px'
                }}>{score}</div>
                <div style={{ fontSize: '10px', color: '#64748b', fontWeight: '600' }}>{label}</div>
            </div>
        );
    };

    return (
        <PanelBody title={__('PageSpeed Performance', 'quinrel')} initialOpen={false}>
            {quinrelEditorConfig.cloudConfigured !== '1' && (
                <Notice status="info" isDismissible={false}>
                    {__('This site is not connected to Quinrel, so PageSpeed through Quinrel is unavailable. You can keep using Quinrel on this site.', 'quinrel')}
                </Notice>
            )}
            {cached ? (
                <div>
                    <div style={{ display: 'flex', justifyContent: 'space-around', marginBottom: '12px' }}>
                        <ScoreCircle label={__('Mobile', 'quinrel')} score={cached.mobile ?? '—'} />
                        <ScoreCircle label={__('Desktop', 'quinrel')} score={cached.desktop ?? '—'} />
                    </div>
                    {cached.fetched_at && (
                        <div style={{ fontSize: '10px', color: '#94a3b8', textAlign: 'center', marginBottom: '10px' }}>
                            Last checked: {new Date(cached.fetched_at * 1000).toLocaleDateString()}
                        </div>
                    )}
                </div>
            ) : (
                <p style={{ fontSize: '12px', color: '#64748b', marginTop: 0 }}>
                    {__('No PageSpeed data cached yet. Click below to run an analysis.', 'quinrel')}
                </p>
            )}
            {error && <Notice status="error" isDismissible={false}>{error}</Notice>}
            <Button isSecondary isBusy={isLoading} onClick={fetchPageSpeed} style={{ width: '100%', justifyContent: 'center' }}>
                {isLoading ? 'Analysing…' : cached ? 'Refresh PageSpeed' : 'Run PageSpeed Check'}
            </Button>
        </PanelBody>
    );
};

// ── Ranking Keywords Panel ──────────────────────────────────────────────────
// Pulls the top-ranking GSC keywords for this specific post URL and displays
// them with clicks, impressions, and average position.
const RankingKeywordsPanel = ({ postId, cachedJson, cachedAt, updateMeta }) => {
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState(null);

    const keywords = useMemo(() => {
        try { return JSON.parse(cachedJson || '[]'); } catch (e) { return []; }
    }, [cachedJson]);

    const fetchKeywords = async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await fetch(`${quinrelEditorConfig.apiUrl}/gsc/keywords?post_id=${postId}`, {
                headers: { 'X-WP-Nonce': quinrelEditorConfig.nonce }
            });
            const data = await res.json();
            if (res.ok && data.success) {
                updateMeta('_quinrel_ranking_keywords', JSON.stringify(data.keywords));
                updateMeta('_quinrel_ranking_keywords_at', String(Math.floor(Date.now() / 1000)));
            } else {
                setError(data.message || __('Failed to fetch keyword data. Connect GSC under Quinrel Settings.', 'quinrel'));
            }
        } catch (e) {
            setError(__('Network error.', 'quinrel'));
        } finally {
            setIsLoading(false);
        }
    };

    const positionColor = (pos) => pos <= 3 ? '#22c55e' : pos <= 10 ? '#f59e0b' : '#ef4444';

    return (
        <PanelBody title={__('Ranking Keywords (GSC)', 'quinrel')} initialOpen={false}>
            {keywords.length > 0 ? (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '6px', marginBottom: '12px' }}>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 40px 60px 40px', gap: '4px', fontSize: '10px', fontWeight: '700', color: '#64748b', textTransform: 'uppercase', paddingBottom: '4px', borderBottom: '1px solid #e2e8f0' }}>
                        <span>{__('Query', 'quinrel')}</span><span style={{textAlign:'center'}}>{__('Pos', 'quinrel')}</span><span style={{textAlign:'center'}}>{__('Impr.', 'quinrel')}</span><span style={{textAlign:'center'}}>{__('Clicks', 'quinrel')}</span>
                    </div>
                    {keywords.slice(0, 10).map((kw, i) => (
                        <div key={i} style={{ display: 'grid', gridTemplateColumns: '1fr 40px 60px 40px', gap: '4px', alignItems: 'center', fontSize: '12px', padding: '4px 0', borderBottom: '1px solid #f1f5f9' }}>
                            <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', color: '#1e293b' }} title={kw.query}>{kw.query}</span>
                            <span style={{ textAlign: 'center', fontWeight: '700', color: positionColor(kw.position) }}>{Math.round(kw.position)}</span>
                            <span style={{ textAlign: 'center', color: '#475569' }}>{kw.impressions?.toLocaleString()}</span>
                            <span style={{ textAlign: 'center', color: '#475569' }}>{kw.clicks}</span>
                        </div>
                    ))}
                    {cachedAt && (
                        <div style={{ fontSize: '10px', color: '#94a3b8', marginTop: '4px' }}>
                            Updated: {new Date(parseInt(cachedAt) * 1000).toLocaleDateString()}
                        </div>
                    )}
                </div>
            ) : (
                <p style={{ fontSize: '12px', color: '#64748b', marginTop: 0 }}>
                    {__('No ranking data yet. Connect Google Search Console under Quinrel → Settings.', 'quinrel')}
                </p>
            )}
            {error && <Notice status="error" isDismissible={false} style={{ marginBottom: '10px' }}>{error}</Notice>}
            <Button isSecondary isBusy={isLoading} onClick={fetchKeywords} style={{ width: '100%', justifyContent: 'center' }}>
                {isLoading ? __('Fetching from GSC…', 'quinrel') : keywords.length > 0 ? __('Refresh Keywords', 'quinrel') : __('Fetch Ranking Keywords', 'quinrel')}
            </Button>
        </PanelBody>
    );
};

// ── Schema Stack Editor ─────────────────────────────────────────────────────
// Allows adding unlimited secondary schema types on top of the primary schema.
// Each entry in the stack is a { type, label } tuple stored as JSON.
const SchemaStackEditor = ({ stackJson, updateMeta }) => {
    const stack = useMemo(() => {
        try { return JSON.parse(stackJson || '[]'); } catch (e) { return []; }
    }, [stackJson]);

    const SCHEMA_OPTIONS = [
        { value: 'faq', storedLabel: 'FAQ', label: __('FAQ', 'quinrel') },
        { value: 'howto', storedLabel: 'HowTo', label: __('HowTo', 'quinrel') },
        { value: 'product', storedLabel: 'Product', label: __('Product', 'quinrel') },
        { value: 'localbusiness', storedLabel: 'Local Business', label: __('Local Business', 'quinrel') },
        { value: 'recipe', storedLabel: 'Recipe', label: __('Recipe', 'quinrel') },
        { value: 'event', storedLabel: 'Event', label: __('Event', 'quinrel') },
        { value: 'article', storedLabel: 'Article', label: __('Article', 'quinrel') },
        { value: 'newsarticle', storedLabel: 'NewsArticle', label: __('NewsArticle', 'quinrel') },
        { value: 'breadcrumb', storedLabel: 'BreadcrumbList', label: __('BreadcrumbList', 'quinrel') },
        { value: 'video', storedLabel: 'VideoObject', label: __('VideoObject', 'quinrel') },
        { value: 'person', storedLabel: 'Person', label: __('Person', 'quinrel') },
        { value: 'organization', storedLabel: 'Organization', label: __('Organization', 'quinrel') },
    ];

    const addSchema = () => {
        updateMeta('_quinrel_schema_stack', JSON.stringify([...stack, { type: 'faq', label: 'FAQ' }]));
    };

    const updateType = (index, type) => {
        const next = [...stack];
        const option = SCHEMA_OPTIONS.find(o => o.value === type);
        next[index] = { type, label: option?.storedLabel || type };
        updateMeta('_quinrel_schema_stack', JSON.stringify(next));
    };

    const removeSchema = (index) => {
        updateMeta('_quinrel_schema_stack', JSON.stringify(stack.filter((_, i) => i !== index)));
    };

    return (
        <div style={{ marginTop: '16px', borderTop: '1px solid #e2e8f0', paddingTop: '12px' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
                <div style={{ fontSize: '11px', color: '#64748b', fontWeight: '700', textTransform: 'uppercase' }}>
                    {__('Additional Schema Layers', 'quinrel')}
                </div>
                <span style={{ fontSize: '10px', background: '#4f46e5', color: '#fff', padding: '2px 8px', borderRadius: '99px' }}>
                    {sprintf(_n('%d schema', '%d schemas', stack.length + 1, 'quinrel'), stack.length + 1)}
                </span>
            </div>

            {stack.length === 0 && (
                <p style={{ fontSize: '12px', color: '#94a3b8', margin: '0 0 8px' }}>
                    {__('Stack additional schema types on top of the primary schema above.', 'quinrel')}
                </p>
            )}

            {stack.map((item, idx) => (
                <div key={idx} style={{ display: 'flex', gap: '8px', alignItems: 'center', marginBottom: '6px' }}>
                    <SelectControl
                        value={item.type}
                        options={SCHEMA_OPTIONS.map(o => ({ label: o.label, value: o.value }))}
                        onChange={(val) => updateType(idx, val)}
                        style={{ flex: 1, margin: 0 }}
                    />
                    <button
                        type="button"
                        onClick={() => removeSchema(idx)}
                        style={{ background: 'none', border: 'none', color: '#ef4444', cursor: 'pointer', fontSize: '18px', lineHeight: 1, padding: '0 4px', flexShrink: 0 }}
                    >×</button>
                </div>
            ))}

            <Button isSecondary onClick={addSchema} style={{ fontSize: '12px', marginTop: '4px' }}>
                {__('+ Add Schema Layer', 'quinrel')}
            </Button>
        </div>
    );
};

// ── Performance Badges ──────────────────────────────────────────────────────
// Visual badges shown at the top of the sidebar based on post quality signals.
const PerformanceBadges = ({ seoScore, readabilityScore, hasKeyword, isPillar, isCornerstone, rankingCount, pagespeedMobile }) => {
    const badges = [];

    if (seoScore >= 80) badges.push({ label: __('SEO Champion', 'quinrel'), color: '#166534', bg: '#dcfce7' });
    else if (seoScore >= 50) badges.push({ label: __('SEO Improving', 'quinrel'), color: '#854d0e', bg: '#fef9c3' });
    else if (seoScore > 0) badges.push({ label: __('Needs SEO Work', 'quinrel'), color: '#991b1b', bg: '#fee2e2' });

    if (readabilityScore >= 60) badges.push({ label: __('Easy to Read', 'quinrel'), color: '#1e40af', bg: '#dbeafe' });

    if (isPillar) badges.push({ label: __('Pillar Content', 'quinrel'), color: '#6b21a8', bg: '#f3e8ff' });
    else if (isCornerstone) badges.push({ label: __('Cornerstone', 'quinrel'), color: '#0e7490', bg: '#cffafe' });

    if (rankingCount > 0) badges.push({ label: sprintf(__('%d ranking keywords', 'quinrel'), rankingCount), color: '#064e3b', bg: '#d1fae5' });

    if (pagespeedMobile >= 90) badges.push({ label: __('Fast Page', 'quinrel'), color: '#14532d', bg: '#dcfce7' });
    else if (pagespeedMobile > 0 && pagespeedMobile < 50) badges.push({ label: __('Slow Page', 'quinrel'), color: '#7c2d12', bg: '#fee2e2' });

    if (badges.length === 0) return null;

    return (
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: '6px', marginTop: '10px', marginBottom: '4px' }}>
            {badges.map((b, i) => (
                <span key={i} style={{
                    fontSize: '10px', fontWeight: '600', padding: '3px 8px',
                    borderRadius: '99px', background: b.bg, color: b.color,
                    whiteSpace: 'nowrap'
                }}>{b.label}</span>
            ))}
        </div>
    );
};

const QuinrelSidebar = () => {
    const { meta, postType, title, content } = useSelect((select) => {
        const editor = select('core/editor');
        return {
            meta: editor.getEditedPostAttribute('meta') || {},
            postType: editor.getCurrentPostType(),
            title: editor.getEditedPostAttribute('title') || '',
            content: editor.getEditedPostAttribute('content') || ''
        };
    }, []);

    const { editPost } = useDispatch('core/editor');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [submitStatus, setSubmitStatus] = useState(null);

    // Scraper State
    const [competitorUrl, setCompetitorUrl] = useState('');
    const [isScraping, setIsScraping] = useState(false);
    const [scrapeNotice, setScrapeNotice] = useState(null);

    const postId = useSelect((select) => select('core/editor').getCurrentPostId());
    const permalink = useSelect((select) => select('core/editor').getPermalink());

    // ── Phase 1 additions: additional keywords, pillar, pagespeed, ranking ──

    const featuredMediaId = useSelect((select) => select('core/editor').getEditedPostAttribute('featured_media'), []);
    const featuredMedia = useSelect((select) => featuredMediaId ? select('core').getMedia(featuredMediaId) : null, [featuredMediaId]);
    const featuredMediaUrl = featuredMedia?.source_url || '';
    const defaultShareImage = (typeof quinrelEditorConfig !== 'undefined' && quinrelEditorConfig.defaultShareImage) || '';

    const domain = useMemo(() => {
        if (!permalink) return 'yourdomain.com';
        try {
            return new URL(permalink).hostname;
        } catch (e) {
            return 'yourdomain.com';
        }
    }, [permalink]);

    const updateMeta = (key, value) => {
        editPost({ meta: { ...meta, [key]: value } });
    };

    // ── Derived Analysis (memoized) ──────────────────────────
    const focusKeyword = meta._quinrel_focus_keyword || '';
    const metaTitle = meta._quinrel_meta_title || '';
    const metaDescription = meta._quinrel_meta_description || '';
    const effectiveTitle = metaTitle || title;
    const isCornerstone = meta._quinrel_is_cornerstone === 'yes';
    const isPillar = meta._quinrel_is_pillar === 'yes';

    const readability = useMemo(() => analyzeReadability(content), [content]);

    const seoResult = useMemo(() => calculateSeoScore({
        title,
        content,
        focusKeyword,
        metaTitle,
        metaDescription,
        readability,
        isCornerstone,
    }), [title, content, focusKeyword, metaTitle, metaDescription, readability, isCornerstone]);

    // Performance badge computed values
    const pagespeedCache = useMemo(() => {
        try { return JSON.parse(meta._quinrel_pagespeed_cache || 'null'); } catch (e) { return null; }
    }, [meta._quinrel_pagespeed_cache]);
    const rankingKeywords = useMemo(() => {
        try { return JSON.parse(meta._quinrel_ranking_keywords || '[]'); } catch (e) { return []; }
    }, [meta._quinrel_ranking_keywords]);
    const additionalKeywordsCount = useMemo(() => {
        try { return JSON.parse(meta._quinrel_additional_keywords || '[]').length; } catch (e) { return 0; }
    }, [meta._quinrel_additional_keywords]);
    const totalKeywords = (focusKeyword ? 1 : 0) + additionalKeywordsCount;

    // ── Actions ──────────────────────────────────────────────
    const scrapeCompetitorSchema = async () => {
        if (!competitorUrl) return;
        setIsScraping(true);
        setScrapeNotice(null);
        try {
            const response = await fetch(`${quinrelEditorConfig.apiUrl}/schema/scrape`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': quinrelEditorConfig.nonce },
                body: JSON.stringify({ url: competitorUrl })
            });
            const data = await response.json();
            if (response.ok && data.success && data.schema_type) {
                updateMeta('_quinrel_primary_schema', data.schema_type);
                if (data.mapped_fields) {
                    Object.keys(data.mapped_fields).forEach(key => {
                        updateMeta(key, data.mapped_fields[key]);
                    });
                }
                setScrapeNotice({ type: 'success', message: sprintf(__('Successfully imported %s schema.', 'quinrel'), data.schema_type) });
            } else {
                setScrapeNotice({ type: 'error', message: data.message || __('Scraping failed.', 'quinrel') });
            }
        } catch (e) {
            setScrapeNotice({ type: 'error', message: __('Network error during scraping.', 'quinrel') });
        } finally {
            setIsScraping(false);
        }
    };

    const submitToIndex = async () => {
        setIsSubmitting(true);
        setSubmitStatus(null);
        try {
            const response = await fetch(`${quinrelEditorConfig.apiUrl}/index-url`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': quinrelEditorConfig.nonce },
                body: JSON.stringify({ post_id: postId })
            });
            const data = await response.json();
            setSubmitStatus(response.ok
                ? { type: 'success', message: data.message || __('Submitted successfully!', 'quinrel') }
                : { type: 'error', message: data.message || __('Failed to submit.', 'quinrel') }
            );
        } catch (error) {
            setSubmitStatus({ type: 'error', message: __('An error occurred during submission.', 'quinrel') });
        } finally {
            setIsSubmitting(false);
        }
    };

    // FAQ items parsed for preview
    const parsedFaqs = useMemo(() => {
        try {
            const parsed = JSON.parse(meta._quinrel_schema_faq_questions || '[]');
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }, [meta._quinrel_schema_faq_questions]);

    // ── Render ──────────────────────────────────────────────
    return (
        <>
            <PluginSidebarMoreMenuItem target="quinrel-sidebar">
                {__('Quinrel SEO', 'quinrel')}
            </PluginSidebarMoreMenuItem>
            <PluginSidebar name="quinrel-sidebar" title={__('Quinrel AI SEO', 'quinrel')}>
                <div className="quinrel-editor-wrapper" style={{ padding: '12px 16px' }}>

                    {/* ── SEO Score Header ── */}
                    <div style={{ borderBottom: '1px solid #e2e8f0', paddingBottom: '12px', marginBottom: '8px' }}>
                        <ScoreRing score={seoResult.score} color={seoResult.color} label={seoResult.label} />
                        <PerformanceBadges
                            seoScore={seoResult.score}
                            readabilityScore={readability.flesch.score}
                            hasKeyword={!!focusKeyword}
                            isPillar={isPillar}
                            isCornerstone={isCornerstone}
                            rankingCount={rankingKeywords.length}
                            pagespeedMobile={pagespeedCache?.mobile ?? 0}
                        />
                        {totalKeywords > 1 && (
                            <div style={{ fontSize: '11px', color: '#6366f1', fontWeight: '600', marginTop: '6px' }}>
                                ✓ Optimizing {totalKeywords} keywords
                            </div>
                        )}
                    </div>

                    {/* ── On-Page SEO Analysis ── */}
                    <PanelBody title={__('SEO Analysis', 'quinrel')} initialOpen={true}>
                        <TextControl
                            label={__('Primary Focus Keyword', 'quinrel')}
                            value={focusKeyword}
                            onChange={(value) => updateMeta('_quinrel_focus_keyword', value)}
                        />
                        <ToggleControl
                            label={__('Pillar Content', 'quinrel')}
                            help={__('Marks this as a top-level pillar page in your topic cluster.', 'quinrel')}
                            checked={isPillar}
                            onChange={(checked) => updateMeta('_quinrel_is_pillar', checked ? 'yes' : 'no')}
                        />
                        <ToggleControl
                            label={__('Cornerstone Content', 'quinrel')}
                            help={__('Enforces stricter length (≥1000 words) and internal link audits.', 'quinrel')}
                            checked={isCornerstone}
                            onChange={(checked) => updateMeta('_quinrel_is_cornerstone', checked ? 'yes' : 'no')}
                        />
                        <ul style={{ listStyle: 'none', margin: '12px 0 0', padding: 0 }}>
                            {seoResult.checks.map((check, i) => (
                                <CheckItem key={i} label={check.label} pass={check.pass} />
                            ))}
                        </ul>
                        {seoResult.keywordGaps && seoResult.keywordGaps.length > 0 && focusKeyword && (
                            <div style={{ background: '#fffbeb', borderLeft: '4px solid #f59e0b', borderRadius: '4px', padding: '10px 12px', marginTop: '12px', marginBottom: '4px', boxShadow: '0 1px 2px 0 rgba(0,0,0,0.05)' }}>
                                <div style={{ fontWeight: '600', color: '#b45309', fontSize: '13px', display: 'flex', alignItems: 'center', gap: '6px', marginBottom: '6px' }}>
                                    <span>⚠️</span> {__('Keyword Optimization Gaps', 'quinrel')}
                                </div>
                                <div style={{ fontSize: '12px', color: '#78350f', lineHeight: '1.4' }}>
                                    {__('Your focus keyword is missing in these critical locations:', 'quinrel')}
                                </div>
                                <ul style={{ margin: '6px 0 0 16px', padding: 0, fontSize: '12px', color: '#78350f', listStyleType: 'disc' }}>
                                    {seoResult.keywordGaps.map((gap, i) => (
                                        <li key={i} style={{ marginBottom: '2px' }}>{gap}</li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </PanelBody>

                    <AiAssistantPanel 
                        title={title} 
                        content={content} 
                        focusKeyword={focusKeyword} 
                        updateMeta={updateMeta}
                        meta={meta}
                    />

                    {/* ── Unlimited Keywords ── */}
                    <PanelBody title={__('Additional Keywords', 'quinrel')} initialOpen={false}>
                        <p style={{ fontSize: '12px', color: '#64748b', margin: '0 0 8px' }}>
                            {__('Add unlimited secondary keywords. Each is independently analysed against your title, description, and content.', 'quinrel')}
                        </p>
                        <UnlimitedKeywordsEditor
                            primaryKeyword={focusKeyword}
                            additionalKeywordsJson={meta._quinrel_additional_keywords || '[]'}
                            content={content}
                            title={title}
                            metaTitle={metaTitle}
                            metaDescription={metaDescription}
                            updateMeta={updateMeta}
                        />
                    </PanelBody>

                    <InternalLinkPanel 
                        postId={postId}
                        content={content}
                    />

                    <CustomFieldsSeoPanel
                        postId={postId}
                    />

                    {/* ── Readability Analysis ── */}
                    <PanelBody title={__('Readability', 'quinrel')} initialOpen={false}>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '12px' }}>
                            <ScoreRing
                                score={readability.flesch.score}
                                color={readability.flesch.pass ? '#22c55e' : readability.flesch.score >= 40 ? '#f59e0b' : '#ef4444'}
                                label={readability.flesch.label}
                                size={56}
                            />
                        </div>
                        <ul style={{ listStyle: 'none', margin: 0, padding: 0 }}>
                            <CheckItem label={`Flesch Reading Ease: ${readability.flesch.score} (${readability.flesch.label})`} pass={readability.flesch.pass} />
                            <CheckItem label={`Long sentences: ${readability.longSentences.value}% (target ≤25%)`} pass={readability.longSentences.pass} />
                            <CheckItem label={readability.longParagraphs.pass ? __('No overly long paragraphs', 'quinrel') : `${readability.longParagraphs.value} paragraph(s) exceed 150 words`} pass={readability.longParagraphs.pass} />
                            <CheckItem label={`Passive voice: ${readability.passiveVoice.value}% (target ≤10%)`} pass={readability.passiveVoice.pass} />
                            <CheckItem label={`Transition words: ${readability.transitionWords.value}% (target ≥30%)`} pass={readability.transitionWords.pass} />
                            <CheckItem label={readability.subheadings.pass ? __('Good subheading distribution', 'quinrel') : __('Add more subheadings to break up content', 'quinrel')} pass={readability.subheadings.pass} />
                        </ul>
                    </PanelBody>

                    {/* ── Core Meta + SERP Preview ── */}
                    <PanelBody title={__('SERP Preview & Meta', 'quinrel')} initialOpen={false}>
                        <SerpPreview
                            title={effectiveTitle}
                            url={permalink}
                            description={metaDescription}
                        />

                        <div style={{ marginTop: '16px' }}>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline' }}>
                                <label style={{ fontSize: '13px', fontWeight: '600', color: '#334155' }}>
                                    {__('Meta Title', 'quinrel')}
                                </label>
                                <CharCount current={effectiveTitle.length} min={30} max={60} />
                            </div>
                            <TextControl
                                help={__('Leave blank to use the post title.', 'quinrel')}
                                value={metaTitle}
                                onChange={(value) => updateMeta('_quinrel_meta_title', value)}
                            />
                        </div>

                        <div>
                            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline' }}>
                                <label style={{ fontSize: '13px', fontWeight: '600', color: '#334155' }}>
                                    {__('Meta Description', 'quinrel')}
                                </label>
                                <CharCount current={metaDescription.length} min={120} max={160} />
                            </div>
                            <TextareaControl
                                value={metaDescription}
                                onChange={(value) => updateMeta('_quinrel_meta_description', value)}
                                rows={4}
                            />
                        </div>
                    </PanelBody>

                    {/* ── Structured Data Schema (SD-001) ── */}
                    <PanelBody title={__('Structured Data Schema', 'quinrel')} initialOpen={false}>
                        <SelectControl
                            label={__('Primary Schema Type', 'quinrel')}
                            value={meta._quinrel_primary_schema || 'none'}
                            options={[
                                { label: __('None / Use Default', 'quinrel'), value: 'none' },
                                { label: __('Product Schema', 'quinrel'), value: 'product' },
                                { label: __('FAQ Page Schema', 'quinrel'), value: 'faq' },
                                { label: __('HowTo Tutorial Schema', 'quinrel'), value: 'howto' },
                                { label: __('Local Business Schema', 'quinrel'), value: 'localbusiness' },
                                { label: __('Recipe Schema', 'quinrel'), value: 'recipe' },
                                { label: __('Event Schema', 'quinrel'), value: 'event' },
                                { label: __('Podcast Episode Schema', 'quinrel'), value: 'podcast' },
                                { label: __('Course Schema', 'quinrel'), value: 'course' },
                                { label: __('Job Posting Schema', 'quinrel'), value: 'jobposting' },
                                { label: __('Book Schema', 'quinrel'), value: 'book' },
                                { label: __('Software Application Schema', 'quinrel'), value: 'software' },
                                { label: __('Restaurant Schema', 'quinrel'), value: 'restaurant' },
                                { label: __('Service Schema', 'quinrel'), value: 'service' },
                                { label: __('Review Schema', 'quinrel'), value: 'review' },
                                { label: __('Movie Schema', 'quinrel'), value: 'movie' },
                                { label: __('Music Recording Schema', 'quinrel'), value: 'music' },
                                { label: __('Dataset Schema', 'quinrel'), value: 'dataset' },
                                { label: __('Person / Profile Schema', 'quinrel'), value: 'person' },
                                { label: __('Custom Schema Builder', 'quinrel'), value: 'custom' },
                            ]}
                            onChange={(val) => updateMeta('_quinrel_primary_schema', val)}
                        />

                        {/* Competitor Schema Scraper */}
                        <div style={{ marginTop: '16px', background: '#f8fafc', padding: '12px', borderRadius: '6px', border: '1px solid #e2e8f0' }}>
                            <div style={{ fontSize: '11px', color: '#64748b', fontWeight: 'bold', textTransform: 'uppercase', marginBottom: '8px' }}>{__('Competitor Schema Scraping', 'quinrel')}</div>
                            <div style={{ display: 'flex', gap: '8px', alignItems: 'end' }}>
                                <div style={{ flex: 1 }}>
                                    <TextControl
                                        label={__('URL to Scrape', 'quinrel')}
                                        value={competitorUrl}
                                        placeholder={__('https://example.com/competitor-post', 'quinrel')}
                                        onChange={(v) => setCompetitorUrl(v)}
                                        help={__('Imports schema fields from competitor URLs.', 'quinrel')}
                                    />
                                </div>
                                <Button 
                                    isSecondary 
                                    isBusy={isScraping} 
                                    onClick={scrapeCompetitorSchema} 
                                    style={{ marginBottom: '24px' }}
                                    disabled={!competitorUrl}
                                >
                                    {__('Scrape & Pre-fill', 'quinrel')}
                                </Button>
                            </div>
                            {scrapeNotice && (
                                <Notice status={scrapeNotice.type} isDismissible={false} className="mt-2">
                                    {scrapeNotice.message}
                                </Notice>
                            )}
                        </div>

                        {meta._quinrel_primary_schema === 'product' && (
                            <div style={{ background: '#f8fafc', padding: '12px', borderRadius: '6px', border: '1px solid #e2e8f0', marginTop: '12px', display: 'flex', flexDirection: 'column', gap: '10px' }}>
                                <strong>{__('Product Details', 'quinrel')}</strong>
                                <TextControl
                                    label={__('Product Name', 'quinrel')}
                                    value={meta._quinrel_schema_product_name || ''}
                                    placeholder={__('Leave blank to use post title', 'quinrel')}
                                    onChange={(v) => updateMeta('_quinrel_schema_product_name', v)}
                                />
                                <TextareaControl
                                    label={__('Product Description', 'quinrel')}
                                    value={meta._quinrel_schema_product_description || ''}
                                    onChange={(v) => updateMeta('_quinrel_schema_product_description', v)}
                                    rows={2}
                                />
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
                                    <TextControl
                                        label={__('Price', 'quinrel')}
                                        type="number"
                                        step="0.01"
                                        value={meta._quinrel_schema_product_price || ''}
                                        onChange={(v) => updateMeta('_quinrel_schema_product_price', v)}
                                    />
                                    <SelectControl
                                        label={__('Currency', 'quinrel')}
                                        value={meta._quinrel_schema_product_currency || 'USD'}
                                        options={[
                                            { label: __('USD ($)', 'quinrel'), value: 'USD' },
                                            { label: __('EUR (€)', 'quinrel'), value: 'EUR' },
                                            { label: __('GBP (£)', 'quinrel'), value: 'GBP' },
                                            { label: __('NGN (₦)', 'quinrel'), value: 'NGN' },
                                        ]}
                                        onChange={(v) => updateMeta('_quinrel_schema_product_currency', v)}
                                    />
                                </div>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
                                    <TextControl
                                        label={__('Rating (1-5)', 'quinrel')}
                                        type="number"
                                        step="0.1"
                                        min="1"
                                        max="5"
                                        value={meta._quinrel_schema_product_rating || ''}
                                        onChange={(v) => updateMeta('_quinrel_schema_product_rating', v)}
                                    />
                                    <SelectControl
                                        label={__('Availability', 'quinrel')}
                                        value={meta._quinrel_schema_product_availability || 'InStock'}
                                        options={[
                                            { label: __('In Stock', 'quinrel'), value: 'InStock' },
                                            { label: __('Out of Stock', 'quinrel'), value: 'OutOfStock' },
                                            { label: __('Pre Order', 'quinrel'), value: 'PreOrder' },
                                        ]}
                                        onChange={(v) => updateMeta('_quinrel_schema_product_availability', v)}
                                    />
                                </div>

                                {/* Product Rich Snippet Preview */}
                                <div style={{ marginTop: '8px', borderTop: '1px solid #cbd5e1', paddingTop: '8px' }}>
                                    <div style={{ fontSize: '11px', color: '#64748b', fontWeight: 'bold', textTransform: 'uppercase' }}>{__('Google Product SERP Preview', 'quinrel')}</div>
                                    <div style={{ border: '1px solid #e2e8f0', borderRadius: '6px', padding: '8px', background: '#fff', fontSize: '13px', marginTop: '6px' }}>
                                        <div style={{ color: '#1a0dab', fontWeight: '600' }}>{meta._quinrel_schema_product_name || effectiveTitle}</div>
                                        <div style={{ display: 'flex', alignItems: 'center', gap: '6px', color: '#f59e0b', fontSize: '12px', marginTop: '3px' }}>
                                            <span style={{ letterSpacing: '2px' }}>★★★★★</span>
                                            <span style={{ color: '#475569' }}>Rating: {meta._quinrel_schema_product_rating || '5.0'}</span>
                                            <span style={{ color: '#cbd5e1' }}>•</span>
                                            <span style={{ color: '#475569' }}>{meta._quinrel_schema_product_price ? `${meta._quinrel_schema_product_currency || 'USD'} ${meta._quinrel_schema_product_price}` : '$99.99'}</span>
                                            <span style={{ color: '#cbd5e1' }}>•</span>
                                            <span style={{ color: meta._quinrel_schema_product_availability === 'OutOfStock' ? '#ef4444' : '#22c55e' }}>{meta._quinrel_schema_product_availability || 'InStock'}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {meta._quinrel_primary_schema === 'faq' && (
                            <div style={{ background: '#f8fafc', padding: '12px', borderRadius: '6px', border: '1px solid #e2e8f0', marginTop: '12px', display: 'flex', flexDirection: 'column', gap: '10px' }}>
                                <strong>{__('FAQ Setup', 'quinrel')}</strong>
                                <FaqEditor
                                    value={meta._quinrel_schema_faq_questions || '[]'}
                                    onChange={(newJson) => updateMeta('_quinrel_schema_faq_questions', newJson)}
                                />

                                {/* FAQ Rich Snippet Preview */}
                                {parsedFaqs.length > 0 && (
                                    <div style={{ marginTop: '8px', borderTop: '1px solid #cbd5e1', paddingTop: '8px' }}>
                                        <div style={{ fontSize: '11px', color: '#64748b', fontWeight: 'bold', textTransform: 'uppercase' }}>{__('Google FAQ SERP Preview', 'quinrel')}</div>
                                        <div style={{ border: '1px solid #e2e8f0', borderRadius: '6px', padding: '8px', background: '#fff', fontSize: '13px', marginTop: '6px', display: 'flex', flexDirection: 'column', gap: '6px' }}>
                                            <div style={{ color: '#1a0dab', fontWeight: '600' }}>{effectiveTitle}</div>
                                            {parsedFaqs.map((faq, index) => (
                                                <div key={index} style={{ borderTop: index > 0 ? '1px solid #f1f5f9' : 'none', paddingTop: index > 0 ? '4px' : '0' }}>
                                                    <div style={{ fontWeight: '600', color: '#202124', display: 'flex', justifyContent: 'space-between', cursor: 'pointer' }}>
                                                        <span>{faq.question || `Question ${index + 1}`}</span>
                                                        <span style={{ color: '#70757a' }}>▾</span>
                                                    </div>
                                                    <div style={{ color: '#4d5156', fontSize: '12px', marginTop: '2px' }}>{faq.answer || __('Enter an answer above.', 'quinrel')}</div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}

                        {meta._quinrel_primary_schema === 'howto' && (
                            <div style={{ background: '#f8fafc', padding: '12px', borderRadius: '6px', border: '1px solid #e2e8f0', marginTop: '12px', display: 'flex', flexDirection: 'column', gap: '10px' }}>
                                <strong>{__('HowTo Tutorial details', 'quinrel')}</strong>
                                <TextControl
                                    label={__('HowTo Name', 'quinrel')}
                                    value={meta._quinrel_schema_howto_name || ''}
                                    placeholder={__('Leave blank to use post title', 'quinrel')}
                                    onChange={(v) => updateMeta('_quinrel_schema_howto_name', v)}
                                />
                                <TextareaControl
                                    label={__('HowTo Description', 'quinrel')}
                                    value={meta._quinrel_schema_howto_description || ''}
                                    onChange={(v) => updateMeta('_quinrel_schema_howto_description', v)}
                                    rows={2}
                                />
                                <TextControl
                                    label={__('Required Supplies (comma separated)', 'quinrel')}
                                    value={meta._quinrel_schema_howto_supplies || ''}
                                    placeholder={__('e.g. Water, Soil, Plant Pot', 'quinrel')}
                                    onChange={(v) => updateMeta('_quinrel_schema_howto_supplies', v)}
                                />
                                <TextControl
                                    label={__('Required Tools (comma separated)', 'quinrel')}
                                    value={meta._quinrel_schema_howto_tools || ''}
                                    placeholder={__('e.g. Trowel, Watering Can', 'quinrel')}
                                    onChange={(v) => updateMeta('_quinrel_schema_howto_tools', v)}
                                />
                                <HowToStepsEditor
                                    value={meta._quinrel_schema_howto_steps || '[]'}
                                    onChange={(newJson) => updateMeta('_quinrel_schema_howto_steps', newJson)}
                                />
                            </div>
                        )}

                        {meta._quinrel_primary_schema === 'localbusiness' && (
                            <div style={{ background: '#f8fafc', padding: '12px', borderRadius: '6px', border: '1px solid #e2e8f0', marginTop: '12px', display: 'flex', flexDirection: 'column', gap: '10px' }}>
                                <strong>{__('Local Business Info', 'quinrel')}</strong>
                                <TextControl
                                    label={__('Business Name', 'quinrel')}
                                    value={meta._quinrel_schema_localbusiness_name || ''}
                                    placeholder={__('Leave blank to use site name', 'quinrel')}
                                    onChange={(v) => updateMeta('_quinrel_schema_localbusiness_name', v)}
                                />
                                <TextControl
                                    label={__('Telephone', 'quinrel')}
                                    value={meta._quinrel_schema_localbusiness_telephone || ''}
                                    placeholder={__('e.g. +1-555-0199', 'quinrel')}
                                    onChange={(v) => updateMeta('_quinrel_schema_localbusiness_telephone', v)}
                                />
                                <TextControl
                                    label={__('Street Address', 'quinrel')}
                                    value={meta._quinrel_schema_localbusiness_street || ''}
                                    placeholder={__('e.g. 100 Main St', 'quinrel')}
                                    onChange={(v) => updateMeta('_quinrel_schema_localbusiness_street', v)}
                                />
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
                                    <TextControl
                                        label={__('City', 'quinrel')}
                                        value={meta._quinrel_schema_localbusiness_city || ''}
                                        placeholder={__('e.g. Seattle', 'quinrel')}
                                        onChange={(v) => updateMeta('_quinrel_schema_localbusiness_city', v)}
                                    />
                                    <TextControl
                                        label={__('Postal Code', 'quinrel')}
                                        value={meta._quinrel_schema_localbusiness_postal || ''}
                                        placeholder={__('e.g. 98101', 'quinrel')}
                                        onChange={(v) => updateMeta('_quinrel_schema_localbusiness_postal', v)}
                                    />
                                </div>
                                <TextControl
                                    label={__('Country Code', 'quinrel')}
                                    value={meta._quinrel_schema_localbusiness_country || 'US'}
                                    placeholder={__('e.g. US', 'quinrel')}
                                    onChange={(v) => updateMeta('_quinrel_schema_localbusiness_country', v)}
                                />
                            </div>
                        )}

                        {meta._quinrel_primary_schema === 'recipe' && (
                            <div style={{ background: '#f8fafc', padding: '12px', borderRadius: '6px', border: '1px solid #e2e8f0', marginTop: '12px', display: 'flex', flexDirection: 'column', gap: '10px' }}>
                                <strong>{__('Recipe Information', 'quinrel')}</strong>
                                <TextControl
                                    label={__('Recipe Name', 'quinrel')}
                                    value={meta._quinrel_schema_recipe_name || ''}
                                    placeholder={__('Leave blank to use post title', 'quinrel')}
                                    onChange={(v) => updateMeta('_quinrel_schema_recipe_name', v)}
                                />
                                <TextareaControl
                                    label={__('Recipe Description', 'quinrel')}
                                    value={meta._quinrel_schema_recipe_description || ''}
                                    onChange={(v) => updateMeta('_quinrel_schema_recipe_description', v)}
                                    rows={2}
                                />
                                <TextareaControl
                                    label={__('Ingredients (one per line)', 'quinrel')}
                                    value={meta._quinrel_schema_recipe_ingredients || ''}
                                    onChange={(v) => updateMeta('_quinrel_schema_recipe_ingredients', v)}
                                    rows={4}
                                />
                                
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
                                    <TextControl
                                        label={__('Prep Time (mins)', 'quinrel')}
                                        type="number"
                                        value={meta._quinrel_schema_recipe_prep_time || ''}
                                        onChange={(v) => updateMeta('_quinrel_schema_recipe_prep_time', v)}
                                    />
                                    <TextControl
                                        label={__('Cook Time (mins)', 'quinrel')}
                                        type="number"
                                        value={meta._quinrel_schema_recipe_cook_time || ''}
                                        onChange={(v) => updateMeta('_quinrel_schema_recipe_cook_time', v)}
                                    />
                                </div>
                                <TextControl
                                    label={__('Calories (kcal)', 'quinrel')}
                                    type="number"
                                    value={meta._quinrel_schema_recipe_calories || ''}
                                    onChange={(v) => updateMeta('_quinrel_schema_recipe_calories', v)}
                                />

                                <RecipeInstructionsEditor
                                    value={meta._quinrel_schema_recipe_instructions || '[]'}
                                    onChange={(newJson) => updateMeta('_quinrel_schema_recipe_instructions', newJson)}
                                />

                                <RecipeSerpPreview 
                                    name={meta._quinrel_schema_recipe_name || effectiveTitle}
                                    url={permalink}
                                    image={featuredMediaUrl}
                                    calories={meta._quinrel_schema_recipe_calories}
                                    prepTime={meta._quinrel_schema_recipe_prep_time}
                                    cookTime={meta._quinrel_schema_recipe_cook_time}
                                    ingredientsCount={meta._quinrel_schema_recipe_ingredients ? meta._quinrel_schema_recipe_ingredients.split('\n').filter(i => i.trim()).length : 0}
                                    stepsCount={(() => {
                                        try { return JSON.parse(meta._quinrel_schema_recipe_instructions || '[]').length; } catch(e) { return 0; }
                                    })()}
                                />
                            </div>
                        )}

                        {meta._quinrel_primary_schema === 'event' && (
                            <div style={{ background: '#f8fafc', padding: '12px', borderRadius: '6px', border: '1px solid #e2e8f0', marginTop: '12px', display: 'flex', flexDirection: 'column', gap: '10px' }}>
                                <strong>{__('Event Details', 'quinrel')}</strong>
                                <TextControl
                                    label={__('Event Name', 'quinrel')}
                                    value={meta._quinrel_schema_event_name || ''}
                                    placeholder={__('Leave blank to use post title', 'quinrel')}
                                    onChange={(v) => updateMeta('_quinrel_schema_event_name', v)}
                                />
                                
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
                                    <TextControl
                                        label={__('Start Date & Time', 'quinrel')}
                                        type="datetime-local"
                                        value={meta._quinrel_schema_event_start_date || ''}
                                        onChange={(v) => updateMeta('_quinrel_schema_event_start_date', v)}
                                    />
                                    <TextControl
                                        label={__('End Date & Time', 'quinrel')}
                                        type="datetime-local"
                                        value={meta._quinrel_schema_event_end_date || ''}
                                        onChange={(v) => updateMeta('_quinrel_schema_event_end_date', v)}
                                    />
                                </div>

                                <TextControl
                                    label={__('Venue / Location Name', 'quinrel')}
                                    value={meta._quinrel_schema_event_venue || ''}
                                    onChange={(v) => updateMeta('_quinrel_schema_event_venue', v)}
                                />
                                <TextControl
                                    label={__('Physical Address', 'quinrel')}
                                    value={meta._quinrel_schema_event_address || ''}
                                    onChange={(v) => updateMeta('_quinrel_schema_event_address', v)}
                                />
                                
                                <TextControl
                                    label={__('Main Performer/Speaker', 'quinrel')}
                                    value={meta._quinrel_schema_event_performer || ''}
                                    onChange={(v) => updateMeta('_quinrel_schema_event_performer', v)}
                                />
                                
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
                                    <TextControl
                                        label={__('Ticket Price', 'quinrel')}
                                        type="number"
                                        step="0.01"
                                        value={meta._quinrel_schema_event_price || ''}
                                        onChange={(v) => updateMeta('_quinrel_schema_event_price', v)}
                                    />
                                    <SelectControl
                                        label={__('Currency', 'quinrel')}
                                        value={meta._quinrel_schema_event_currency || 'USD'}
                                        options={[
                                            { label: __('USD ($)', 'quinrel'), value: 'USD' },
                                            { label: __('EUR (€)', 'quinrel'), value: 'EUR' },
                                            { label: __('GBP (£)', 'quinrel'), value: 'GBP' },
                                        ]}
                                        onChange={(v) => updateMeta('_quinrel_schema_event_currency', v)}
                                    />
                                </div>

                                <EventSerpPreview 
                                    name={meta._quinrel_schema_event_name || effectiveTitle}
                                    url={permalink}
                                    startDate={meta._quinrel_schema_event_start_date}
                                    venue={meta._quinrel_schema_event_venue}
                                    address={meta._quinrel_schema_event_address}
                                    performer={meta._quinrel_schema_event_performer}
                                    price={meta._quinrel_schema_event_price ? `${meta._quinrel_schema_event_currency || 'USD'} ${meta._quinrel_schema_event_price}` : ''}
                                />
                            </div>
                        )}

                        {meta._quinrel_primary_schema === 'podcast' && (
                            <div style={{ background: '#f8fafc', padding: '12px', borderRadius: '6px', border: '1px solid #e2e8f0', marginTop: '12px', display: 'flex', flexDirection: 'column', gap: '10px' }}>
                                <strong>{__('Podcast Episode Details', 'quinrel')}</strong>
                                <TextControl
                                    label={__('Audio File URL', 'quinrel')}
                                    value={meta._quinrel_podcast_audio_url || ''}
                                    placeholder={__('https://example.com/audio/episode.mp3', 'quinrel')}
                                    help={__('Optional. If left blank, Quinrel automatically scans for audio blocks or attached media.', 'quinrel')}
                                    onChange={(v) => updateMeta('_quinrel_podcast_audio_url', v)}
                                />
                                <TextControl
                                    label={__('Episode Title', 'quinrel')}
                                    value={meta._quinrel_podcast_title || ''}
                                    placeholder={__('Leave blank to use post title', 'quinrel')}
                                    onChange={(v) => updateMeta('_quinrel_podcast_title', v)}
                                />
                                <TextareaControl
                                    label={__('Episode Show Notes / Description', 'quinrel')}
                                    value={meta._quinrel_podcast_description || ''}
                                    placeholder={__('Leave blank to use excerpt', 'quinrel')}
                                    onChange={(v) => updateMeta('_quinrel_podcast_description', v)}
                                    rows={3}
                                />
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '8px' }}>
                                    <TextControl
                                        label={__('Episode #', 'quinrel')}
                                        value={meta._quinrel_podcast_episode_number || ''}
                                        placeholder="1"
                                        onChange={(v) => updateMeta('_quinrel_podcast_episode_number', v)}
                                    />
                                    <TextControl
                                        label={__('Season #', 'quinrel')}
                                        value={meta._quinrel_podcast_season_number || ''}
                                        placeholder="1"
                                        onChange={(v) => updateMeta('_quinrel_podcast_season_number', v)}
                                    />
                                    <SelectControl
                                        label={__('Type', 'quinrel')}
                                        value={meta._quinrel_podcast_episode_type || 'full'}
                                        options={[
                                            { label: __('Full Episode', 'quinrel'), value: 'full' },
                                            { label: __('Trailer', 'quinrel'), value: 'trailer' },
                                            { label: __('Bonus', 'quinrel'), value: 'bonus' },
                                        ]}
                                        onChange={(v) => updateMeta('_quinrel_podcast_episode_type', v)}
                                    />
                                </div>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
                                    <TextControl
                                        label={__('Series / Show Name', 'quinrel')}
                                        value={meta._quinrel_podcast_series_name || ''}
                                        placeholder={__('e.g. The Quinrel Show', 'quinrel')}
                                        onChange={(v) => updateMeta('_quinrel_podcast_series_name', v)}
                                    />
                                    <TextControl
                                        label={__('Series Website URL', 'quinrel')}
                                        value={meta._quinrel_podcast_series_url || ''}
                                        placeholder="https://example.com/show"
                                        onChange={(v) => updateMeta('_quinrel_podcast_series_url', v)}
                                    />
                                </div>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
                                    <TextControl
                                        label={__('Duration', 'quinrel')}
                                        value={meta._quinrel_podcast_duration || ''}
                                        placeholder={__('e.g. 45 min or PT45M', 'quinrel')}
                                        onChange={(v) => updateMeta('_quinrel_podcast_duration', v)}
                                    />
                                    <TextControl
                                        label={__('Transcript URL or Text', 'quinrel')}
                                        value={meta._quinrel_podcast_transcript || ''}
                                        placeholder="https://example.com/transcript.txt"
                                        onChange={(v) => updateMeta('_quinrel_podcast_transcript', v)}
                                    />
                                </div>
                            </div>
                        )}

                        {meta._quinrel_primary_schema === 'course' && (
                            <div style={{ background: '#f8fafc', padding: '12px', borderRadius: '6px', border: '1px solid #e2e8f0', marginTop: '12px', display: 'flex', flexDirection: 'column', gap: '10px' }}>
                                <strong>{__('Course Details', 'quinrel')}</strong>
                                <TextControl
                                    label={__('Course Title', 'quinrel')}
                                    value={meta._quinrel_schema_course_name || ''}
                                    placeholder={__('Leave blank to use post title', 'quinrel')}
                                    onChange={(v) => updateMeta('_quinrel_schema_course_name', v)}
                                />
                                <TextareaControl
                                    label={__('Course Description', 'quinrel')}
                                    value={meta._quinrel_schema_course_description || ''}
                                    placeholder={__('Leave blank to use excerpt', 'quinrel')}
                                    onChange={(v) => updateMeta('_quinrel_schema_course_description', v)}
                                    rows={3}
                                />
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
                                    <TextControl
                                        label={__('Course Code', 'quinrel')}
                                        value={meta._quinrel_schema_course_code || ''}
                                        placeholder="CS-101"
                                        onChange={(v) => updateMeta('_quinrel_schema_course_code', v)}
                                    />
                                    <SelectControl
                                        label={__('Level', 'quinrel')}
                                        value={meta._quinrel_schema_course_level || 'Beginner'}
                                        options={[
                                            { label: __('Beginner', 'quinrel'), value: 'Beginner' },
                                            { label: __('Intermediate', 'quinrel'), value: 'Intermediate' },
                                            { label: __('Advanced', 'quinrel'), value: 'Advanced' },
                                            { label: __('All Levels', 'quinrel'), value: 'All Levels' },
                                        ]}
                                        onChange={(v) => updateMeta('_quinrel_schema_course_level', v)}
                                    />
                                </div>
                                <TextControl
                                    label={__('Course Provider / Institution', 'quinrel')}
                                    value={meta._quinrel_schema_course_provider || ''}
                                    placeholder={__('Leave blank to use site name', 'quinrel')}
                                    onChange={(v) => updateMeta('_quinrel_schema_course_provider', v)}
                                />
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
                                    <TextControl
                                        label={__('Price', 'quinrel')}
                                        type="number"
                                        value={meta._quinrel_schema_course_price || ''}
                                        placeholder="0 for free"
                                        onChange={(v) => updateMeta('_quinrel_schema_course_price', v)}
                                    />
                                    <SelectControl
                                        label={__('Currency', 'quinrel')}
                                        value={meta._quinrel_schema_course_currency || 'USD'}
                                        options={[
                                            { label: 'USD ($)', value: 'USD' },
                                            { label: 'EUR (€)', value: 'EUR' },
                                            { label: 'GBP (£)', value: 'GBP' },
                                        ]}
                                        onChange={(v) => updateMeta('_quinrel_schema_course_currency', v)}
                                    />
                                </div>
                            </div>
                        )}

                        {meta._quinrel_primary_schema === 'jobposting' && (
                            <div style={{ background: '#f8fafc', padding: '12px', borderRadius: '6px', border: '1px solid #e2e8f0', marginTop: '12px', display: 'flex', flexDirection: 'column', gap: '10px' }}>
                                <strong>{__('Job Posting Details', 'quinrel')}</strong>
                                <TextControl
                                    label={__('Job Title', 'quinrel')}
                                    value={meta._quinrel_schema_job_title || ''}
                                    placeholder={__('Leave blank to use post title', 'quinrel')}
                                    onChange={(v) => updateMeta('_quinrel_schema_job_title', v)}
                                />
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
                                    <TextControl
                                        label={__('Hiring Organization', 'quinrel')}
                                        value={meta._quinrel_schema_job_company || ''}
                                        placeholder={__('Company name', 'quinrel')}
                                        onChange={(v) => updateMeta('_quinrel_schema_job_company', v)}
                                    />
                                    <TextControl
                                        label={__('Company Website', 'quinrel')}
                                        value={meta._quinrel_schema_job_company_url || ''}
                                        placeholder="https://example.com"
                                        onChange={(v) => updateMeta('_quinrel_schema_job_company_url', v)}
                                    />
                                </div>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
                                    <SelectControl
                                        label={__('Employment Type', 'quinrel')}
                                        value={meta._quinrel_schema_job_employment_type || 'FULL_TIME'}
                                        options={[
                                            { label: __('Full-time', 'quinrel'), value: 'FULL_TIME' },
                                            { label: __('Part-time', 'quinrel'), value: 'PART_TIME' },
                                            { label: __('Contractor', 'quinrel'), value: 'CONTRACTOR' },
                                            { label: __('Temporary', 'quinrel'), value: 'TEMPORARY' },
                                            { label: __('Intern', 'quinrel'), value: 'INTERN' },
                                        ]}
                                        onChange={(v) => updateMeta('_quinrel_schema_job_employment_type', v)}
                                    />
                                    <SelectControl
                                        label={__('Workplace Type', 'quinrel')}
                                        value={meta._quinrel_schema_job_location_type || 'ONSITE'}
                                        options={[
                                            { label: __('On-site / In-person', 'quinrel'), value: 'ONSITE' },
                                            { label: __('Fully Remote (Telecommute)', 'quinrel'), value: 'REMOTE' },
                                        ]}
                                        onChange={(v) => updateMeta('_quinrel_schema_job_location_type', v)}
                                    />
                                </div>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '8px' }}>
                                    <TextControl
                                        label={__('City', 'quinrel')}
                                        value={meta._quinrel_schema_job_city || ''}
                                        onChange={(v) => updateMeta('_quinrel_schema_job_city', v)}
                                    />
                                    <TextControl
                                        label={__('Country', 'quinrel')}
                                        value={meta._quinrel_schema_job_country || ''}
                                        placeholder="US"
                                        onChange={(v) => updateMeta('_quinrel_schema_job_country', v)}
                                    />
                                    <TextControl
                                        label={__('Valid Through', 'quinrel')}
                                        type="date"
                                        value={meta._quinrel_schema_job_valid_through || ''}
                                        onChange={(v) => updateMeta('_quinrel_schema_job_valid_through', v)}
                                    />
                                </div>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '8px' }}>
                                    <TextControl
                                        label={__('Salary Amount', 'quinrel')}
                                        type="number"
                                        value={meta._quinrel_schema_job_salary || ''}
                                        placeholder="120000"
                                        onChange={(v) => updateMeta('_quinrel_schema_job_salary', v)}
                                    />
                                    <SelectControl
                                        label={__('Currency', 'quinrel')}
                                        value={meta._quinrel_schema_job_salary_currency || 'USD'}
                                        options={[
                                            { label: 'USD ($)', value: 'USD' },
                                            { label: 'EUR (€)', value: 'EUR' },
                                            { label: 'GBP (£)', value: 'GBP' },
                                        ]}
                                        onChange={(v) => updateMeta('_quinrel_schema_job_salary_currency', v)}
                                    />
                                    <SelectControl
                                        label={__('Pay Frequency', 'quinrel')}
                                        value={meta._quinrel_schema_job_salary_unit || 'YEAR'}
                                        options={[
                                            { label: __('Per Year', 'quinrel'), value: 'YEAR' },
                                            { label: __('Per Month', 'quinrel'), value: 'MONTH' },
                                            { label: __('Per Hour', 'quinrel'), value: 'HOUR' },
                                        ]}
                                        onChange={(v) => updateMeta('_quinrel_schema_job_salary_unit', v)}
                                    />
                                </div>
                            </div>
                        )}

                        {meta._quinrel_primary_schema === 'book' && (
                            <div style={{ background: '#f8fafc', padding: '12px', borderRadius: '6px', border: '1px solid #e2e8f0', marginTop: '12px', display: 'flex', flexDirection: 'column', gap: '10px' }}>
                                <strong>{__('Book Details', 'quinrel')}</strong>
                                <TextControl
                                    label={__('Book Title', 'quinrel')}
                                    value={meta._quinrel_schema_book_name || ''}
                                    placeholder={__('Leave blank to use post title', 'quinrel')}
                                    onChange={(v) => updateMeta('_quinrel_schema_book_name', v)}
                                />
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
                                    <TextControl
                                        label={__('Author', 'quinrel')}
                                        value={meta._quinrel_schema_book_author || ''}
                                        placeholder={__('Leave blank for post author', 'quinrel')}
                                        onChange={(v) => updateMeta('_quinrel_schema_book_author', v)}
                                    />
                                    <TextControl
                                        label={__('ISBN', 'quinrel')}
                                        value={meta._quinrel_schema_book_isbn || ''}
                                        placeholder="978-0..."
                                        onChange={(v) => updateMeta('_quinrel_schema_book_isbn', v)}
                                    />
                                </div>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '8px' }}>
                                    <SelectControl
                                        label={__('Format', 'quinrel')}
                                        value={meta._quinrel_schema_book_format || 'Paperback'}
                                        options={[
                                            { label: __('Paperback', 'quinrel'), value: 'Paperback' },
                                            { label: __('Hardcover', 'quinrel'), value: 'Hardcover' },
                                            { label: __('EBook', 'quinrel'), value: 'EBook' },
                                            { label: __('Audiobook', 'quinrel'), value: 'AudiobookFormat' },
                                        ]}
                                        onChange={(v) => updateMeta('_quinrel_schema_book_format', v)}
                                    />
                                    <TextControl
                                        label={__('Pages', 'quinrel')}
                                        type="number"
                                        value={meta._quinrel_schema_book_pages || ''}
                                        onChange={(v) => updateMeta('_quinrel_schema_book_pages', v)}
                                    />
                                    <TextControl
                                        label={__('Publisher', 'quinrel')}
                                        value={meta._quinrel_schema_book_publisher || ''}
                                        onChange={(v) => updateMeta('_quinrel_schema_book_publisher', v)}
                                    />
                                </div>
                            </div>
                        )}

                        {meta._quinrel_primary_schema === 'software' && (
                            <div style={{ background: '#f8fafc', padding: '12px', borderRadius: '6px', border: '1px solid #e2e8f0', marginTop: '12px', display: 'flex', flexDirection: 'column', gap: '10px' }}>
                                <strong>{__('Software Application Details', 'quinrel')}</strong>
                                <TextControl
                                    label={__('Application Name', 'quinrel')}
                                    value={meta._quinrel_schema_software_name || ''}
                                    placeholder={__('Leave blank to use post title', 'quinrel')}
                                    onChange={(v) => updateMeta('_quinrel_schema_software_name', v)}
                                />
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
                                    <TextControl
                                        label={__('Operating System', 'quinrel')}
                                        value={meta._quinrel_schema_software_os || 'Web'}
                                        placeholder="Windows, macOS, Linux, Web"
                                        onChange={(v) => updateMeta('_quinrel_schema_software_os', v)}
                                    />
                                    <TextControl
                                        label={__('Category', 'quinrel')}
                                        value={meta._quinrel_schema_software_category || 'BusinessApplication'}
                                        placeholder="BusinessApplication, DeveloperApplication"
                                        onChange={(v) => updateMeta('_quinrel_schema_software_category', v)}
                                    />
                                </div>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '8px' }}>
                                    <TextControl
                                        label={__('Price', 'quinrel')}
                                        type="number"
                                        value={meta._quinrel_schema_software_price || ''}
                                        placeholder="0 for free"
                                        onChange={(v) => updateMeta('_quinrel_schema_software_price', v)}
                                    />
                                    <SelectControl
                                        label={__('Currency', 'quinrel')}
                                        value={meta._quinrel_schema_software_currency || 'USD'}
                                        options={[
                                            { label: 'USD ($)', value: 'USD' },
                                            { label: 'EUR (€)', value: 'EUR' },
                                            { label: 'GBP (£)', value: 'GBP' },
                                        ]}
                                        onChange={(v) => updateMeta('_quinrel_schema_software_currency', v)}
                                    />
                                    <TextControl
                                        label={__('Rating (1-5)', 'quinrel')}
                                        value={meta._quinrel_schema_software_rating || ''}
                                        placeholder="4.9"
                                        onChange={(v) => updateMeta('_quinrel_schema_software_rating', v)}
                                    />
                                </div>
                            </div>
                        )}

                        {meta._quinrel_primary_schema === 'restaurant' && (
                            <div style={{ background: '#f8fafc', padding: '12px', borderRadius: '6px', border: '1px solid #e2e8f0', marginTop: '12px', display: 'flex', flexDirection: 'column', gap: '10px' }}>
                                <strong>{__('Restaurant Details', 'quinrel')}</strong>
                                <TextControl
                                    label={__('Restaurant Name', 'quinrel')}
                                    value={meta._quinrel_schema_restaurant_name || ''}
                                    placeholder={__('Leave blank to use post title', 'quinrel')}
                                    onChange={(v) => updateMeta('_quinrel_schema_restaurant_name', v)}
                                />
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
                                    <TextControl
                                        label={__('Cuisine Type', 'quinrel')}
                                        value={meta._quinrel_schema_restaurant_cuisine || ''}
                                        placeholder="Italian, Japanese, Bistro"
                                        onChange={(v) => updateMeta('_quinrel_schema_restaurant_cuisine', v)}
                                    />
                                    <SelectControl
                                        label={__('Price Range', 'quinrel')}
                                        value={meta._quinrel_schema_restaurant_price_range || '$$'}
                                        options={[
                                            { label: '$ (Inexpensive)', value: '$' },
                                            { label: '$$ (Moderate)', value: '$$' },
                                            { label: '$$$ (Upscale)', value: '$$$' },
                                            { label: '$$$$ (Fine Dining)', value: '$$$$' },
                                        ]}
                                        onChange={(v) => updateMeta('_quinrel_schema_restaurant_price_range', v)}
                                    />
                                </div>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
                                    <TextControl
                                        label={__('Telephone', 'quinrel')}
                                        value={meta._quinrel_schema_restaurant_telephone || ''}
                                        onChange={(v) => updateMeta('_quinrel_schema_restaurant_telephone', v)}
                                    />
                                    <TextControl
                                        label={__('Street Address', 'quinrel')}
                                        value={meta._quinrel_schema_restaurant_street || ''}
                                        onChange={(v) => updateMeta('_quinrel_schema_restaurant_street', v)}
                                    />
                                </div>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
                                    <TextControl
                                        label={__('Menu URL', 'quinrel')}
                                        value={meta._quinrel_schema_restaurant_menu_url || ''}
                                        placeholder="https://example.com/menu"
                                        onChange={(v) => updateMeta('_quinrel_schema_restaurant_menu_url', v)}
                                    />
                                    <SelectControl
                                        label={__('Accepts Reservations', 'quinrel')}
                                        value={meta._quinrel_schema_restaurant_reservations || 'yes'}
                                        options={[
                                            { label: __('Yes', 'quinrel'), value: 'yes' },
                                            { label: __('No', 'quinrel'), value: 'no' },
                                        ]}
                                        onChange={(v) => updateMeta('_quinrel_schema_restaurant_reservations', v)}
                                    />
                                </div>
                            </div>
                        )}

                        {meta._quinrel_primary_schema === 'service' && (
                            <div style={{ background: '#f8fafc', padding: '12px', borderRadius: '6px', border: '1px solid #e2e8f0', marginTop: '12px', display: 'flex', flexDirection: 'column', gap: '10px' }}>
                                <strong>{__('Service Details', 'quinrel')}</strong>
                                <TextControl
                                    label={__('Service Name', 'quinrel')}
                                    value={meta._quinrel_schema_service_name || ''}
                                    placeholder={__('Leave blank to use post title', 'quinrel')}
                                    onChange={(v) => updateMeta('_quinrel_schema_service_name', v)}
                                />
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
                                    <TextControl
                                        label={__('Service Type', 'quinrel')}
                                        value={meta._quinrel_schema_service_type || ''}
                                        placeholder="Search Engine Optimization"
                                        onChange={(v) => updateMeta('_quinrel_schema_service_type', v)}
                                    />
                                    <TextControl
                                        label={__('Service Provider', 'quinrel')}
                                        value={meta._quinrel_schema_service_provider || ''}
                                        placeholder={__('Leave blank for site name', 'quinrel')}
                                        onChange={(v) => updateMeta('_quinrel_schema_service_provider', v)}
                                    />
                                </div>
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
                                    <TextControl
                                        label={__('Area Served', 'quinrel')}
                                        value={meta._quinrel_schema_service_area || ''}
                                        placeholder="Global, New York, UK"
                                        onChange={(v) => updateMeta('_quinrel_schema_service_area', v)}
                                    />
                                    <TextControl
                                        label={__('Estimated Price', 'quinrel')}
                                        type="number"
                                        value={meta._quinrel_schema_service_price || ''}
                                        onChange={(v) => updateMeta('_quinrel_schema_service_price', v)}
                                    />
                                </div>
                            </div>
                        )}

                        {meta._quinrel_primary_schema === 'review' && (
                            <div style={{ background: '#f8fafc', padding: '12px', borderRadius: '6px', border: '1px solid #e2e8f0', marginTop: '12px', display: 'flex', flexDirection: 'column', gap: '10px' }}>
                                <strong>{__('Review Details', 'quinrel')}</strong>
                                <TextControl
                                    label={__('Item Reviewed', 'quinrel')}
                                    value={meta._quinrel_schema_review_item_name || ''}
                                    placeholder={__('Leave blank to use post title', 'quinrel')}
                                    onChange={(v) => updateMeta('_quinrel_schema_review_item_name', v)}
                                />
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
                                    <SelectControl
                                        label={__('Item Type', 'quinrel')}
                                        value={meta._quinrel_schema_review_item_type || 'Product'}
                                        options={[
                                            { label: 'Product', value: 'Product' },
                                            { label: 'Book', value: 'Book' },
                                            { label: 'Movie', value: 'Movie' },
                                            { label: 'SoftwareApplication', value: 'SoftwareApplication' },
                                            { label: 'Restaurant', value: 'Restaurant' },
                                            { label: 'Course', value: 'Course' },
                                        ]}
                                        onChange={(v) => updateMeta('_quinrel_schema_review_item_type', v)}
                                    />
                                    <TextControl
                                        label={__('Rating (1 to 5)', 'quinrel')}
                                        value={meta._quinrel_schema_review_rating || '5'}
                                        onChange={(v) => updateMeta('_quinrel_schema_review_rating', v)}
                                    />
                                </div>
                                <TextareaControl
                                    label={__('Review Summary / Body', 'quinrel')}
                                    value={meta._quinrel_schema_review_body || ''}
                                    placeholder={__('Leave blank to use post excerpt', 'quinrel')}
                                    onChange={(v) => updateMeta('_quinrel_schema_review_body', v)}
                                    rows={3}
                                />
                            </div>
                        )}

                        {meta._quinrel_primary_schema === 'movie' && (
                            <div style={{ background: '#f8fafc', padding: '12px', borderRadius: '6px', border: '1px solid #e2e8f0', marginTop: '12px', display: 'flex', flexDirection: 'column', gap: '10px' }}>
                                <strong>{__('Movie Details', 'quinrel')}</strong>
                                <TextControl
                                    label={__('Movie Title', 'quinrel')}
                                    value={meta._quinrel_schema_movie_name || ''}
                                    placeholder={__('Leave blank to use post title', 'quinrel')}
                                    onChange={(v) => updateMeta('_quinrel_schema_movie_name', v)}
                                />
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
                                    <TextControl
                                        label={__('Director', 'quinrel')}
                                        value={meta._quinrel_schema_movie_director || ''}
                                        onChange={(v) => updateMeta('_quinrel_schema_movie_director', v)}
                                    />
                                    <TextControl
                                        label={__('Release Date', 'quinrel')}
                                        type="date"
                                        value={meta._quinrel_schema_movie_date || ''}
                                        onChange={(v) => updateMeta('_quinrel_schema_movie_date', v)}
                                    />
                                </div>
                                <TextControl
                                    label={__('Lead Actors (comma-separated)', 'quinrel')}
                                    value={meta._quinrel_schema_movie_actors || ''}
                                    placeholder="Actor 1, Actor 2"
                                    onChange={(v) => updateMeta('_quinrel_schema_movie_actors', v)}
                                />
                                <TextControl
                                    label={__('Duration', 'quinrel')}
                                    value={meta._quinrel_schema_movie_duration || ''}
                                    placeholder="e.g. 2 hours 15 min"
                                    onChange={(v) => updateMeta('_quinrel_schema_movie_duration', v)}
                                />
                            </div>
                        )}

                        {meta._quinrel_primary_schema === 'music' && (
                            <div style={{ background: '#f8fafc', padding: '12px', borderRadius: '6px', border: '1px solid #e2e8f0', marginTop: '12px', display: 'flex', flexDirection: 'column', gap: '10px' }}>
                                <strong>{__('Music Recording Details', 'quinrel')}</strong>
                                <TextControl
                                    label={__('Track Title', 'quinrel')}
                                    value={meta._quinrel_schema_music_name || ''}
                                    placeholder={__('Leave blank to use post title', 'quinrel')}
                                    onChange={(v) => updateMeta('_quinrel_schema_music_name', v)}
                                />
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
                                    <TextControl
                                        label={__('Artist / Band', 'quinrel')}
                                        value={meta._quinrel_schema_music_artist || ''}
                                        onChange={(v) => updateMeta('_quinrel_schema_music_artist', v)}
                                    />
                                    <TextControl
                                        label={__('Album Name', 'quinrel')}
                                        value={meta._quinrel_schema_music_album || ''}
                                        onChange={(v) => updateMeta('_quinrel_schema_music_album', v)}
                                    />
                                </div>
                                <TextControl
                                    label={__('Duration', 'quinrel')}
                                    value={meta._quinrel_schema_music_duration || ''}
                                    placeholder="e.g. 3 min 45 sec"
                                    onChange={(v) => updateMeta('_quinrel_schema_music_duration', v)}
                                />
                            </div>
                        )}

                        {meta._quinrel_primary_schema === 'dataset' && (
                            <div style={{ background: '#f8fafc', padding: '12px', borderRadius: '6px', border: '1px solid #e2e8f0', marginTop: '12px', display: 'flex', flexDirection: 'column', gap: '10px' }}>
                                <strong>{__('Dataset Details', 'quinrel')}</strong>
                                <TextControl
                                    label={__('Dataset Name', 'quinrel')}
                                    value={meta._quinrel_schema_dataset_name || ''}
                                    placeholder={__('Leave blank to use post title', 'quinrel')}
                                    onChange={(v) => updateMeta('_quinrel_schema_dataset_name', v)}
                                />
                                <TextareaControl
                                    label={__('Dataset Description', 'quinrel')}
                                    value={meta._quinrel_schema_dataset_description || ''}
                                    placeholder={__('Leave blank to use excerpt', 'quinrel')}
                                    onChange={(v) => updateMeta('_quinrel_schema_dataset_description', v)}
                                    rows={3}
                                />
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
                                    <TextControl
                                        label={__('License URL', 'quinrel')}
                                        value={meta._quinrel_schema_dataset_license || ''}
                                        placeholder="https://creativecommons.org/licenses/by/4.0/"
                                        onChange={(v) => updateMeta('_quinrel_schema_dataset_license', v)}
                                    />
                                    <TextControl
                                        label={__('Download URL', 'quinrel')}
                                        value={meta._quinrel_schema_dataset_download_url || ''}
                                        placeholder="https://example.org/data.csv"
                                        onChange={(v) => updateMeta('_quinrel_schema_dataset_download_url', v)}
                                    />
                                </div>
                            </div>
                        )}

                        {meta._quinrel_primary_schema === 'person' && (
                            <div style={{ background: '#f8fafc', padding: '12px', borderRadius: '6px', border: '1px solid #e2e8f0', marginTop: '12px', display: 'flex', flexDirection: 'column', gap: '10px' }}>
                                <strong>{__('Person / Profile Details', 'quinrel')}</strong>
                                <TextControl
                                    label={__('Full Name', 'quinrel')}
                                    value={meta._quinrel_schema_person_name || ''}
                                    placeholder={__('Leave blank to use post title', 'quinrel')}
                                    onChange={(v) => updateMeta('_quinrel_schema_person_name', v)}
                                />
                                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '8px' }}>
                                    <TextControl
                                        label={__('Job Title', 'quinrel')}
                                        value={meta._quinrel_schema_person_job_title || ''}
                                        placeholder="Chief Technology Officer"
                                        onChange={(v) => updateMeta('_quinrel_schema_person_job_title', v)}
                                    />
                                    <TextControl
                                        label={__('Organization', 'quinrel')}
                                        value={meta._quinrel_schema_person_works_for || ''}
                                        placeholder="Acme Corp"
                                        onChange={(v) => updateMeta('_quinrel_schema_person_works_for', v)}
                                    />
                                </div>
                                <TextControl
                                    label={__('Social / SameAs URLs (comma-separated)', 'quinrel')}
                                    value={meta._quinrel_schema_person_same_as || ''}
                                    placeholder="https://twitter.com/..., https://github.com/..."
                                    onChange={(v) => updateMeta('_quinrel_schema_person_same_as', v)}
                                />
                            </div>
                        )}

                        {meta._quinrel_primary_schema === 'custom' && (
                            <div style={{ background: '#f8fafc', padding: '12px', borderRadius: '6px', border: '1px solid #e2e8f0', marginTop: '12px', display: 'flex', flexDirection: 'column', gap: '10px' }}>
                                <strong>{__('Custom Schema Configuration', 'quinrel')}</strong>
                                <TextControl
                                    label={__('Root Schema @type', 'quinrel')}
                                    value={meta._quinrel_schema_custom_type || ''}
                                    placeholder={__('e.g. Course, SoftwareApplication', 'quinrel')}
                                    onChange={(v) => updateMeta('_quinrel_schema_custom_type', v)}
                                />
                                <CustomPropertyTreeBuilder 
                                    value={meta._quinrel_custom_schema_properties || '[]'}
                                    onChange={(newJson) => updateMeta('_quinrel_custom_schema_properties', newJson)}
                                />
                            </div>
                        )}
                        <SchemaValidator
                            schemaType={meta._quinrel_primary_schema}
                            meta={meta}
                            title={title}
                            content={content}
                            featuredMediaUrl={featuredMediaUrl}
                            permalink={permalink}
                            domain={domain}
                        />
                        <SchemaStackEditor
                            stackJson={meta._quinrel_schema_stack || '[]'}
                            updateMeta={updateMeta}
                        />
                    </PanelBody>

                    {/* ── Ranking Keywords (GSC) ── */}
                    <RankingKeywordsPanel
                        postId={postId}
                        cachedJson={meta._quinrel_ranking_keywords || '[]'}
                        cachedAt={meta._quinrel_ranking_keywords_at || ''}
                        updateMeta={updateMeta}
                    />

                    {/* ── PageSpeed Performance ── */}
                    <PageSpeedBadge
                        postId={postId}
                        cachedJson={meta._quinrel_pagespeed_cache || 'null'}
                        updateMeta={updateMeta}
                    />

                    {/* ── Social Metadata ── */}
                    <PanelBody title={__('Social Metadata', 'quinrel')} initialOpen={false}>
                        <TextControl
                            label={__('Open Graph Title (Facebook/LinkedIn)', 'quinrel')}
                            value={meta._quinrel_og_title || ''}
                            onChange={(value) => updateMeta('_quinrel_og_title', value)}
                        />
                        <TextareaControl
                            label={__('Open Graph Description', 'quinrel')}
                            value={meta._quinrel_og_description || ''}
                            onChange={(value) => updateMeta('_quinrel_og_description', value)}
                            rows={3}
                        />
                        <TextControl
                            label={__('Open Graph Image URL', 'quinrel')}
                            help={__('Provide an absolute URL to a custom social sharing image.', 'quinrel')}
                            value={meta._quinrel_og_image || ''}
                            onChange={(value) => updateMeta('_quinrel_og_image', value)}
                        />
                        <TextControl
                            label={__('Twitter Title', 'quinrel')}
                            value={meta._quinrel_twitter_title || ''}
                            onChange={(value) => updateMeta('_quinrel_twitter_title', value)}
                        />
                        
                        <SocialCardPreview
                            title={meta._quinrel_og_title || title}
                            description={meta._quinrel_og_description || (content ? stripHtml(content).substring(0, 150) + '...' : '')}
                            image={meta._quinrel_og_image || featuredMediaUrl || defaultShareImage}
                            domain={domain}
                        />
                    </PanelBody>

                    {/* ── Indexing & Technical ── */}
                    <PanelBody title={__('Indexing & Technical', 'quinrel')} initialOpen={false}>
                        <TextControl
                            label={__('Canonical URL', 'quinrel')}
                            help={__('Prevent duplicate content issues by overriding canonical output.', 'quinrel')}
                            value={meta._quinrel_canonical_url || ''}
                            onChange={(value) => updateMeta('_quinrel_canonical_url', value)}
                        />

                        <ToggleControl
                            label={__('No Index (Hide from Search Engines)', 'quinrel')}
                            checked={meta._quinrel_noindex === 'yes'}
                            onChange={(checked) => updateMeta('_quinrel_noindex', checked ? 'yes' : 'no')}
                        />
                        <ToggleControl
                            label={__('No Follow (Prevent following outbound links)', 'quinrel')}
                            checked={meta._quinrel_nofollow === 'yes'}
                            onChange={(checked) => updateMeta('_quinrel_nofollow', checked ? 'yes' : 'no')}
                        />
                        <ToggleControl
                            label={__('Optimize for Google News', 'quinrel')}
                            checked={meta._quinrel_is_news === 'yes'}
                            onChange={(checked) => updateMeta('_quinrel_is_news', checked ? 'yes' : 'no')}
                        />

                        <div style={{ marginTop: '16px', paddingTop: '16px', borderTop: '1px solid #e2e8f0' }}>
                            <strong>{__('Manual Indexing', 'quinrel')}</strong>
                            <p style={{ fontSize: '12px', color: '#64748b', margin: '4px 0 8px' }}>{__('Queue an IndexNow notification. Google Indexing API submission is only available for eligible JobPosting and livestream pages.', 'quinrel')}</p>

                            {submitStatus && (
                                <Notice status={submitStatus.type} isDismissible={true} onRemove={() => setSubmitStatus(null)} className="mb-2">
                                    {submitStatus.message}
                                </Notice>
                            )}

                            <Button isPrimary isBusy={isSubmitting} onClick={submitToIndex} className="w-full justify-center mb-4">
                                {isSubmitting ? __('Submitting...', 'quinrel') : __('Submit to Search Engines', 'quinrel')}
                            </Button>

                        </div>
                    </PanelBody>

                </div>
                
                {/* ── Social Media Sharing ── */}
                <PanelBody title={__('Social Media Sharing', 'quinrel')} initialOpen={false}>
                    <div style={{ marginBottom: '16px' }}>
                        <ToggleControl
                            label={__('Disable Auto-Share for this Post', 'quinrel')}
                            help={__('If enabled, this post will NOT be automatically shared to your connected social accounts when published.', 'quinrel')}
                            checked={meta._quinrel_disable_social_share === '1'}
                            onChange={(checked) => updateMeta('_quinrel_disable_social_share', checked ? '1' : '0')}
                        />
                    </div>

                    <div style={{ marginBottom: '16px' }}>
                        <strong>{__('Live Social Preview', 'quinrel')}</strong>
                        <p style={{ fontSize: '12px', color: '#64748b', margin: '4px 0 8px' }}>{__('Preview how your post will appear on Facebook, X (Twitter), and LinkedIn.', 'quinrel')}</p>
                        
                        <div style={{ border: '1px solid #e2e8f0', borderRadius: '8px', overflow: 'hidden', background: '#fff', boxShadow: '0 1px 3px rgba(0,0,0,0.1)' }}>
                            <div style={{ width: '100%', height: '150px', background: '#f1f5f9', display: 'flex', alignItems: 'center', justifyContent: 'center', borderBottom: '1px solid #e2e8f0', overflow: 'hidden' }}>
                                {meta._quinrel_og_image ? (
                                    <img src={meta._quinrel_og_image} style={{ width: '100%', height: '100%', objectFit: 'cover' }} alt="OG Preview" />
                                ) : (
                                    <div style={{ color: '#94a3b8', fontSize: '12px' }}>{__('[Default Share Image]', 'quinrel')}</div>
                                )}
                            </div>
                            <div style={{ padding: '12px' }}>
                                <div style={{ fontSize: '11px', color: '#64748b', textTransform: 'uppercase', marginBottom: '4px' }}>
                                    {window.location.hostname}
                                </div>
                                <div style={{ fontWeight: 'bold', fontSize: '14px', color: '#0f1419', lineHeight: '1.3', marginBottom: '4px', display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', overflow: 'hidden' }}>
                                    {meta._quinrel_og_title || meta._quinrel_meta_title || __('(Post Title)', 'quinrel')}
                                </div>
                                <div style={{ fontSize: '13px', color: '#536471', display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', overflow: 'hidden' }}>
                                    {meta._quinrel_og_description || meta._quinrel_meta_description || __('(Post Excerpt)', 'quinrel')}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div style={{ borderTop: '1px solid #e2e8f0', paddingTop: '16px' }}>
                        <strong>{__('Manual Share', 'quinrel')}</strong>
                        <p style={{ fontSize: '12px', color: '#64748b', margin: '4px 0 8px' }}>{__('Force push this post to your connected social channels now.', 'quinrel')}</p>
                        <Button 
                            isSecondary 
                            className="w-full justify-center"
                            onClick={() => {
                                const currentPostId = wp.data.select('core/editor').getCurrentPostId();
                                wp.apiFetch({
                                    path: '/quinrel/v1/social/share',
                                    method: 'POST',
                                    data: { post_id: currentPostId }
                                }).then((res) => {
                                    alert(res.message);
                                }).catch((err) => {
                                    alert(err.message || __('Error triggering share.', 'quinrel'));
                                });
                            }}
                        >
                            {__('Share Now', 'quinrel')}
                        </Button>
                    </div>
                </PanelBody>

            </PluginSidebar>
        </>
    );
};

// Register the Gutenberg plugin
registerPlugin('quinrel-editor-sidebar', {
    render: QuinrelSidebar,
    icon: 'chart-area',
});
