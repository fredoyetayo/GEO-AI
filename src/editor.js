import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import { PanelBody, Button, Spinner, Notice } from '@wordpress/components';
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { useSelect } from '@wordpress/data';

const GeoAISidebar = () => {
    const [loading, setLoading] = useState(false);
    const [auditData, setAuditData] = useState(null);
    const [error, setError] = useState(null);
    const [statusNotice, setStatusNotice] = useState(null);
    const [isFetchingSaved, setIsFetchingSaved] = useState(false);

    const postId = useSelect((select) => {
        return select('core/editor').getCurrentPostId();
    }, []);

    const fetchAudit = useCallback(async () => {
        if (!postId) {
            return;
        }

        setIsFetchingSaved(true);

        try {
            const response = await apiFetch({
                path: `/geoai/v1/audit/${postId}`,
                method: 'GET',
            });

            if (response.success) {
                setAuditData(response.data);
            } else {
                setAuditData(null);
            }
        } catch (err) {
            if (err?.data?.status === 404) {
                setAuditData(null);
            } else {
                setError(err.message || __('An error occurred', 'geo-ai'));
            }
        } finally {
            setIsFetchingSaved(false);
        }
    }, [postId]);

    useEffect(() => {
        fetchAudit();
    }, [fetchAudit]);

    const runAudit = async () => {
        setLoading(true);
        setError(null);
        setStatusNotice(null);

        try {
            const response = await apiFetch({
                path: '/geoai/v1/audit',
                method: 'POST',
                data: { post_id: postId },
            });

            if (response.success) {
                setAuditData(response.data);
                setStatusNotice({
                    status: 'success',
                    message: __('Audit completed successfully.', 'geo-ai'),
                });
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
        setStatusNotice(null);
        setError(null);
        try {
            const response = await apiFetch({
                path: '/geoai/v1/quick-fix',
                method: 'POST',
                data: {
                    post_id: postId,
                    fix_id: fixId,
                },
            });

            if (response.success) {
                setStatusNotice({
                    status: 'success',
                    message:
                        response.message ||
                        __('Quick fix applied successfully.', 'geo-ai'),
                });
                await fetchAudit();
            } else {
                setError(response.message || __('Quick fix failed', 'geo-ai'));
            }
        } catch (err) {
            setError(err.message || __('An error occurred', 'geo-ai'));
        }
    };

    const getScoreColor = (score) => {
        if (score >= 80) return '#46b450';
        if (score >= 60) return '#ffb900';
        return '#dc3232';
    };

    const hasTitleOptions =
        auditData?.suggestions?.titleOptions &&
        auditData.suggestions.titleOptions.length > 0;
    const hasEntities =
        auditData?.suggestions?.entities &&
        auditData.suggestions.entities.length > 0;
    const hasCitations =
        auditData?.suggestions?.citations &&
        auditData.suggestions.citations.length > 0;

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

                    {statusNotice && (
                        <Notice
                            status={statusNotice.status}
                            isDismissible={false}
                            style={{ marginBottom: '12px' }}
                        >
                            {statusNotice.message}
                        </Notice>
                    )}

                    {error && (
                        <Notice status="error" isDismissible={false}>
                            {error}
                        </Notice>
                    )}

                    {auditData?.runAt && (
                        <div
                            style={{
                                marginBottom: '12px',
                                fontSize: '12px',
                                color: '#555',
                                display: 'flex',
                                justifyContent: 'space-between',
                                gap: '8px',
                                alignItems: 'center',
                            }}
                        >
                            <span>
                                {__('Last audited:', 'geo-ai')}{' '}
                                {new Date(auditData.runAt).toLocaleString()}
                            </span>
                            <Button
                                isSecondary
                                isSmall
                                onClick={fetchAudit}
                                disabled={isFetchingSaved}
                            >
                                {isFetchingSaved
                                    ? __('Refreshing…', 'geo-ai')
                                    : __('Refresh', 'geo-ai')}
                            </Button>
                        </div>
                    )}

                    {!auditData && !isFetchingSaved && (
                        <div
                            style={{
                                fontSize: '13px',
                                color: '#666',
                                marginBottom: '16px',
                            }}
                        >
                            {__(
                                'Run your first audit to see AI-driven suggestions tailored to answer engine visibility.',
                                'geo-ai'
                            )}
                        </div>
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

                            {(hasTitleOptions || hasEntities || hasCitations) && (
                                <div style={{ marginTop: '16px' }}>
                                    <strong>{__('AI Suggestions', 'geo-ai')}</strong>

                                    {hasTitleOptions && (
                                        <div style={{ marginTop: '12px' }}>
                                            <div style={{ fontWeight: '600', marginBottom: '4px' }}>
                                                {__('Meta Title Ideas', 'geo-ai')}
                                            </div>
                                            <ul style={{ listStyle: 'disc', paddingLeft: '20px', margin: 0 }}>
                                                {auditData.suggestions.titleOptions.map((title, index) => (
                                                    <li key={`title-${index}`}>{title}</li>
                                                ))}
                                            </ul>
                                        </div>
                                    )}

                                    {hasEntities && (
                                        <div style={{ marginTop: '12px' }}>
                                            <div style={{ fontWeight: '600', marginBottom: '4px' }}>
                                                {__('Relevant Entities', 'geo-ai')}
                                            </div>
                                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '6px' }}>
                                                {auditData.suggestions.entities.map((entity, index) => (
                                                    <span
                                                        key={`entity-${index}`}
                                                        style={{
                                                            background: '#f0f0f0',
                                                            borderRadius: '12px',
                                                            padding: '4px 10px',
                                                            fontSize: '12px',
                                                        }}
                                                    >
                                                        {entity}
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                    )}

                                    {hasCitations && (
                                        <div style={{ marginTop: '12px' }}>
                                            <div style={{ fontWeight: '600', marginBottom: '4px' }}>
                                                {__('Suggested Citations', 'geo-ai')}
                                            </div>
                                            <ul style={{ listStyle: 'disc', paddingLeft: '20px', margin: 0 }}>
                                                {auditData.suggestions.citations.map((citation, index) => (
                                                    <li key={`citation-${index}`}>
                                                        <a href={citation} target="_blank" rel="noreferrer">
                                                            {citation}
                                                        </a>
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    )}
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
