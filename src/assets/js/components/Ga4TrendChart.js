import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const Ga4TrendChart = ({ data = [] }) => {
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
        return <div style={{ height: 180, display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#f8fafc', borderRadius: 8 }}>{__('Chart unavailable', 'quinrel')}</div>;
    }

    if (!Charts) {
        return <div style={{ height: 180, display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#f8fafc', borderRadius: 8 }}>{__('Loading chart...', 'quinrel')}</div>;
    }

    const { ResponsiveContainer, LineChart, XAxis, YAxis, Tooltip, Line } = Charts;

    return (
        <ResponsiveContainer width="100%" height={180}>
            <LineChart data={data} margin={{ top: 4, right: 8, left: -20, bottom: 0 }}>
                <XAxis
                    dataKey="date"
                    tick={{ fontSize: 10 }}
                    tickFormatter={(d) => (typeof d === 'string' && d.length > 5 ? d.slice(5) : d)}
                />
                <YAxis tick={{ fontSize: 10 }} />
                <Tooltip />
                <Line type="monotone" dataKey="sessions" stroke="#0060F9" strokeWidth={2} dot={false} name={__('Sessions', 'quinrel')} />
                <Line type="monotone" dataKey={(row) => row.pageviews ?? row.screenPageViews ?? 0} stroke="#22c55e" strokeWidth={1} dot={false} name={__('Pageviews', 'quinrel')} />
            </LineChart>
        </ResponsiveContainer>
    );
};

export default Ga4TrendChart;
