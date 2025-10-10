(function( wp, window ) {
    if ( ! wp || ! wp.plugins || ! wp.editPost || ! wp.element ) {
        return;
    }

    var registerPlugin = wp.plugins.registerPlugin;
    var PluginSidebar = wp.editPost.PluginSidebar;
    var PluginSidebarMoreMenuItem = wp.editPost.PluginSidebarMoreMenuItem;
    var PanelBody = wp.components.PanelBody;
    var Button = wp.components.Button;
    var Spinner = wp.components.Spinner;
    var Notice = wp.components.Notice;
    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var useState = wp.element.useState;
    var __ = wp.i18n.__;
    var useSelect = wp.data && wp.data.useSelect ? wp.data.useSelect : null;
    var apiFetch = wp.apiFetch;

    if ( ! registerPlugin || ! PluginSidebar || ! PluginSidebarMoreMenuItem || ! useSelect || ! apiFetch ) {
        return;
    }

    if ( window.geoaiEditor && window.geoaiEditor.nonce && apiFetch.createNonceMiddleware ) {
        if ( ! window.geoaiEditor.nonceMiddlewareApplied ) {
            apiFetch.use( apiFetch.createNonceMiddleware( window.geoaiEditor.nonce ) );
            window.geoaiEditor.nonceMiddlewareApplied = true;
        }
    }

    var getScoreColor = function( score ) {
        if ( score >= 80 ) {
            return '#46b450';
        }

        if ( score >= 60 ) {
            return '#ffb900';
        }

        return '#dc3232';
    };

    var GeoAISidebar = function() {
        var postId = useSelect( function( select ) {
            var editor = select( 'core/editor' );
            if ( editor && editor.getCurrentPostId ) {
                return editor.getCurrentPostId();
            }
            return 0;
        }, [] );

        var stateLoading = useState( false );
        var loading = stateLoading[0];
        var setLoading = stateLoading[1];

        var stateAudit = useState( null );
        var auditData = stateAudit[0];
        var setAuditData = stateAudit[1];

        var stateError = useState( null );
        var error = stateError[0];
        var setError = stateError[1];

        var runAudit = function() {
            setLoading( true );
            setError( null );

            apiFetch( {
                path: '/geoai/v1/audit',
                method: 'POST',
                data: { post_id: postId }
            } ).then( function( response ) {
                if ( response && response.success ) {
                    setAuditData( response.data );
                } else {
                    setAuditData( null );
                    setError( response && response.message ? response.message : __( 'Audit failed', 'geo-ai' ) );
                }
                setLoading( false );
            } ).catch( function( err ) {
                var message = err && err.message ? err.message : __( 'An error occurred', 'geo-ai' );
                setError( message );
                setAuditData( null );
                setLoading( false );
            } );
        };

        var applyQuickFix = function( fixId ) {
            apiFetch( {
                path: '/geoai/v1/quick-fix',
                method: 'POST',
                data: {
                    post_id: postId,
                    fix_id: fixId
                }
            } ).then( function() {
                window.location.reload();
            } ).catch( function( err ) {
                setError( err && err.message ? err.message : __( 'An error occurred', 'geo-ai' ) );
            } );
        };

        var renderIssues = function( issues ) {
            if ( ! issues || ! issues.length ) {
                return null;
            }

            var issueItems = issues.map( function( issue, index ) {
                var severityColor = '#46b450';
                if ( issue.severity === 'high' ) {
                    severityColor = '#dc3232';
                } else if ( issue.severity === 'med' ) {
                    severityColor = '#ffb900';
                }

                return el(
                    'li',
                    {
                        key: index,
                        style: {
                            marginBottom: '12px',
                            padding: '8px',
                            border: '1px solid #ddd',
                            borderRadius: '4px'
                        }
                    },
                    el(
                        'div',
                        {
                            style: {
                                fontSize: '12px',
                                color: '#666',
                                marginBottom: '4px'
                            }
                        },
                        el(
                            'span',
                            {
                                style: {
                                    color: severityColor,
                                    fontWeight: 'bold'
                                }
                            },
                            issue.severity ? issue.severity.toUpperCase() : ''
                        )
                    ),
                    el( 'div', null, issue.msg ),
                    issue.quickFix ? el( Button, {
                        isSmall: true,
                        isSecondary: true,
                        onClick: function() {
                            applyQuickFix( issue.quickFix );
                        },
                        style: {
                            marginTop: '8px'
                        }
                    }, __( 'Apply Quick Fix', 'geo-ai' ) ) : null
                );
            } );

            return el(
                'div',
                null,
                el( 'strong', null, __( 'Issues:', 'geo-ai' ) ),
                el( 'ul', { style: { listStyle: 'none', padding: 0 } }, issueItems )
            );
        };

        var scoreBreakdown = function( scores ) {
            if ( ! scores ) {
                return null;
            }

            return el(
                'div',
                { style: { marginBottom: '16px' } },
                el( 'strong', null, __( 'Score Breakdown:', 'geo-ai' ) ),
                el( 'ul', { style: { listStyle: 'none', padding: 0 } },
                    el( 'li', null, __( 'Answerability:', 'geo-ai' ) + ' ' + scores.answerability ),
                    el( 'li', null, __( 'Structure:', 'geo-ai' ) + ' ' + scores.structure ),
                    el( 'li', null, __( 'Trust:', 'geo-ai' ) + ' ' + scores.trust ),
                    el( 'li', null, __( 'Technical:', 'geo-ai' ) + ' ' + scores.technical )
                )
            );
        };

        var renderAudit = function() {
            if ( ! auditData ) {
                return null;
            }

            return el(
                'div',
                { className: 'geoai-audit-results' },
                el(
                    'div',
                    {
                        className: 'geoai-score-dial',
                        style: {
                            textAlign: 'center',
                            padding: '20px',
                            marginBottom: '16px'
                        }
                    },
                    el( 'div', {
                        style: {
                            fontSize: '48px',
                            fontWeight: 'bold',
                            color: getScoreColor( auditData.scores ? auditData.scores.total : 0 )
                        }
                    }, auditData.scores ? auditData.scores.total : 0 ),
                    el( 'div', { style: { fontSize: '14px', color: '#666' } }, __( 'Overall Score', 'geo-ai' ) )
                ),
                scoreBreakdown( auditData.scores ),
                renderIssues( auditData.issues )
            );
        };

        return el(
            Fragment,
            null,
            el( PluginSidebarMoreMenuItem, { target: 'geoai-sidebar' }, __( 'GEO AI', 'geo-ai' ) ),
            el(
                PluginSidebar,
                {
                    name: 'geoai-sidebar',
                    title: __( 'GEO AI Audit', 'geo-ai' )
                },
                el(
                    PanelBody,
                    null,
                    el(
                        Button,
                        {
                            isPrimary: true,
                            onClick: runAudit,
                            disabled: loading,
                            style: { width: '100%', marginBottom: '16px' }
                        },
                        loading ? el( Spinner, null ) : __( 'Run AI Audit', 'geo-ai' )
                    ),
                    error ? el( Notice, { status: 'error', isDismissible: false }, error ) : null,
                    renderAudit()
                )
            )
        );
    };

    registerPlugin( 'geoai-sidebar', {
        render: GeoAISidebar
    } );
})( window.wp, window );
