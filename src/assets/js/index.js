import { createRoot, render, Component } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import App from './App';
import '../css/admin.css';

class AdminErrorBoundary extends Component {
    constructor(props) {
        super(props);
        this.state = { hasError: false, message: '' };
    }

    static getDerivedStateFromError(error) {
        return {
            hasError: true,
            message: error && error.message ? String(error.message) : '',
        };
    }

    render() {
        if (this.state.hasError) {
            return (
                <div className="p-6 bg-white border border-red-200 rounded-lg max-w-2xl my-6 mx-auto">
                    <h2 className="text-lg font-semibold text-red-700 mt-0 mb-2">
                        {__('Quinrel encountered a display error', 'quinrel')}
                    </h2>
                    <p className="text-sm text-slate-600 mb-4">
                        {this.state.message || __('An unexpected error occurred while rendering this view.', 'quinrel')}
                    </p>
                    <button
                        type="button"
                        onClick={() => this.setState({ hasError: false, message: '' })}
                        className="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded hover:bg-indigo-700 cursor-pointer border-none"
                    >
                        {__('Reload View', 'quinrel')}
                    </button>
                </div>
            );
        }
        return this.props.children;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const rootElement = document.getElementById('quinrel-admin-app');
    if (rootElement) {
        const tree = (
            <AdminErrorBoundary>
                <App />
            </AdminErrorBoundary>
        );
        if (createRoot) {
            createRoot(rootElement).render(tree);
        } else {
            render(tree, rootElement);
        }
    }
});
