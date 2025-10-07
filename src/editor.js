import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import { PanelBody, Button, Spinner, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { useSelect } from '@wordpress/data';

const GeoAISidebar = () => {
    const [loading, setLoading] = useState(false);
    const [auditData, setAuditData] = useState(null);
    const [error, setError] = useState(null);

    const postId = useSelect((select) => {
        return select('core/editor').getCurrentPostId();
    }, []);

    const runAudit = async () => {
        setLoading(true);
        setError(null);

        try {
            const response = await apiFetch({
                path: '/geoai/v1/audit',
                method: 'POST',
                data: { post_id: postId },
            });

            if (response.success) {
                setAuditData(response.data);
            } else {
                setError(response.message || __('Audit failed', 'geo-ai'));
            }
        } catch (err) {
            setError(err.message || __('An error occurred', 'geo-ai'));
        } finally {
            setLoading(false);
        }
    };

    const applyQuickFix = async (fixId) => {
        try {
            await apiFetch({
                path: '/geoai/v1/quick-fix',
                method: 'POST',
                data: {
                    post_id: postId,
                    fix_id: fixId,
                },
            });

            window.location.reload();
        } catch (err) {
            setError(err.message);
        }
    };

    const getScoreColor = (score) => {
        if (score >= 80) return '#46b450';
        if (score >= 60) return '#ffb900';
        return '#dc3232';
    };

    return (
        <>
            <PluginSidebarMoreMenuItem target="geoai-sidebar">
                {__('GEO AI', 'geo-ai')}
            </PluginSidebarMoreMenuItem>
            <PluginSidebar
                name="geoai-sidebar"
                title={__('GEO AI Audit', 'geo-ai')}
            >
                <PanelBody>
                    <Button
                        isPrimary
                        onClick={runAudit}
                        disabled={loading}
                        style={{ width: '100%', marginBottom: '16px' }}
                    >
                        {loading ? <Spinner /> : __('Run AI Audit', 'geo-ai')}
                    </Button>

                    {error && (
                        <Notice status="error" isDismissible={false}>
                            {error}
                        </Notice>
                    )}

                    {auditData && (
                        <div className="geoai-audit-results">
                            <div
                                className="geoai-score-dial"
                                style={{
                                    textAlign: 'center',
                                    padding: '20px',
                                    marginBottom: '16px',
                                }}
                            >
                                <div
                                    style={{
                                        fontSize: '48px',
                                        fontWeight: 'bold',
                                        color: getScoreColor(
                                            auditData.scores.total
                                        ),
                                    }}
                                >
                                    {auditData.scores.total}
                                </div>
                                <div style={{ fontSize: '14px', color: '#666' }}>
                                    {__('Overall Score', 'geo-ai')}
                                </div>
                            </div>

                            <div style={{ marginBottom: '16px' }}>
                                <strong>{__('Score Breakdown:', 'geo-ai')}</strong>
                                <ul style={{ listStyle: 'none', padding: 0 }}>
                                    <li>
                                        {__('Answerability:', 'geo-ai')}{' '}
                                        {auditData.scores.answerability}
                                    </li>
                                    <li>
                                        {__('Structure:', 'geo-ai')}{' '}
                                        {auditData.scores.structure}
                                    </li>
                                    <li>
                                        {__('Trust:', 'geo-ai')}{' '}
                                        {auditData.scores.trust}
                                    </li>
                                    <li>
                                        {__('Technical:', 'geo-ai')}{' '}
                                        {auditData.scores.technical}
                                    </li>
                                </ul>
                            </div>

                            {auditData.issues && auditData.issues.length > 0 && (
                                <div>
                                    <strong>{__('Issues:', 'geo-ai')}</strong>
                                    <ul style={{ listStyle: 'none', padding: 0 }}>
                                        {auditData.issues.map((issue, index) => (
                                            <li
                                                key={index}
                                                style={{
                                                    marginBottom: '12px',
                                                    padding: '8px',
                                                    border: '1px solid #ddd',
                                                    borderRadius: '4px',
                                                }}
                                            >
                                                <div
                                                    style={{
                                                        fontSize: '12px',
                                                        color: '#666',
                                                        marginBottom: '4px',
                                                    }}
                                                >
                                                    <span
                                                        style={{
                                                            color:
                                                                issue.severity ===
                                                                'high'
                                                                    ? '#dc3232'
                                                                    : issue.severity ===
                                                                      'med'
                                                                    ? '#ffb900'
                                                                    : '#46b450',
                                                            fontWeight: 'bold',
                                                        }}
                                                    >
                                                        {issue.severity.toUpperCase()}
                                                    </span>
                                                </div>
                                                <div>{issue.msg}</div>
                                                {issue.quickFix && (
                                                    <Button
                                                        isSmall
                                                        isSecondary
                                                        onClick={() =>
                                                            applyQuickFix(
                                                                issue.quickFix
                                                            )
                                                        }
                                                        style={{
                                                            marginTop: '8px',
                                                        }}
                                                    >
                                                        {__(
                                                            'Apply Quick Fix',
                                                            'geo-ai'
                                                        )}
                                                    </Button>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </div>
                    )}
                </PanelBody>
            </PluginSidebar>
        </>
    );
};

registerPlugin('geoai-sidebar', {
    render: GeoAISidebar,
    icon: 'search',
});
