import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const CHART_COLORS = ['#0060F9', '#10b981', '#f59e0b', '#ef4444', '#9A37F4', '#64748b'];

const Ga4ChannelChart = ({ data = [] }) => {
    const [Charts, setCharts] = useState(null);
    const [loadError, setLoadError] = useState(false);

    useEffect(() => {
        let mounted = true;
        import('recharts')
            .then((module) => {
                if (mounted) {
                    setCharts(module);
                }
            })
            .catch(() => {
                if (mounted) {
                    setLoadError(true);
                }
            });
        return () => {
            mounted = false;
        };
    }, []);

    if (loadError) {
        return <div style={{ height: 200, display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#f8fafc', borderRadius: 8 }}>{__('Chart unavailable', 'quinrel')}</div>;
    }

    if (!Charts) {
        return <div style={{ height: 200, display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#f8fafc', borderRadius: 8 }}>{__('Loading chart...', 'quinrel')}</div>;
    }

    const { ResponsiveContainer, PieChart, Pie, Cell, Tooltip } = Charts;
    const safeData = Array.isArray(data) ? data : [];

    return (
        <ResponsiveContainer width="100%" height={200}>
            <PieChart>
                <Pie data={safeData} dataKey="sessions" nameKey="channel" cx="50%" cy="50%" outerRadius={80} label={({ channel, percent }) => `${channel} ${(percent * 100).toFixed(0)}%`} labelLine={false}>
                    {safeData.map((_, i) => <Cell key={i} fill={CHART_COLORS[i % CHART_COLORS.length]} />)}
                </Pie>
                <Tooltip />
            </PieChart>
        </ResponsiveContainer>
    );
};

export default Ga4ChannelChart;
