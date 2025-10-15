import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import { PanelBody, Button, Spinner, Notice } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { useDispatch, useSelect } from '@wordpress/data';

const GeoAISidebar = () => {
	const [ loading, setLoading ] = useState( false );
	const [ auditData, setAuditData ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ successMessage, setSuccessMessage ] = useState( null );
	const [ lastAuditTimestamp, setLastAuditTimestamp ] = useState( null );
	const [ applyingFixId, setApplyingFixId ] = useState( null );

	const { postId, meta } = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		return {
			postId: editor.getCurrentPostId(),
			meta: editor.getEditedPostAttribute( 'meta' ) || {},
		};
	}, [] );

	const { editPost } = useDispatch( 'core/editor' );

	useEffect( () => {
		if ( meta?._geoai_audit_timestamp ) {
			setLastAuditTimestamp( meta._geoai_audit_timestamp );
		}
	}, [ meta?._geoai_audit_timestamp ] );

	const parseTimestamp = ( timestamp ) => {
		if ( ! timestamp ) {
			return null;
		}

		const normalized = timestamp.replace( ' ', 'T' );
		const parsed = new Date( normalized );

		return Number.isNaN( parsed.getTime() ) ? null : parsed;
	};

	const formatTimestamp = ( timestamp ) => {
		if ( ! timestamp ) {
			return __( 'No audit run yet', 'geo-ai' );
		}

		const parsed = parseTimestamp( timestamp );

		if ( ! parsed ) {
			return timestamp;
		}

		return parsed.toLocaleString();
	};

	const parsedAuditDate = parseTimestamp( lastAuditTimestamp );
	const staleThreshold = 7 * 24 * 60 * 60 * 1000;
	const isAuditStale = parsedAuditDate
		? Date.now() - parsedAuditDate.getTime() > staleThreshold
		: false;
	const showAuditReminder = ! parsedAuditDate || isAuditStale;
	const reminderMessage = ! parsedAuditDate
		? __(
				'No audit has been run yet. Run an audit to generate fresh insights.',
				'geo-ai'
		  )
		: __(
				'This audit is over 7 days old. Run a fresh audit before publishing.',
				'geo-ai'
		  );

	const runAudit = async () => {
		setLoading( true );
		setError( null );
		setSuccessMessage( null );

		try {
			const response = await apiFetch( {
				path: '/geoai/v1/audit',
				method: 'POST',
				data: { post_id: postId },
			} );

			if ( response.success ) {
				setAuditData( response.data );
				setLastAuditTimestamp( new Date().toISOString() );
			} else {
				setError( response.message || __( 'Audit failed', 'geo-ai' ) );
			}
		} catch ( err ) {
			setError( err.message || __( 'An error occurred', 'geo-ai' ) );
		} finally {
			setLoading( false );
		}
	};

	const applyQuickFix = async ( fixId ) => {
		setError( null );
		setSuccessMessage( null );
		setApplyingFixId( fixId );

		try {
			const response = await apiFetch( {
				path: '/geoai/v1/quick-fix',
				method: 'POST',
				data: {
					post_id: postId,
					fix_id: fixId,
				},
			} );

			if ( response.success ) {
				if ( response?.data?.content ) {
					editPost( { content: response.data.content } );
				}

				setSuccessMessage(
					response?.data?.notice ||
						response.message ||
						__( 'Quick fix applied successfully.', 'geo-ai' )
				);
			} else {
				setError(
					response.message ||
						__( 'Unable to apply quick fix.', 'geo-ai' )
				);
			}
		} catch ( err ) {
			setError( err.message );
		} finally {
			setApplyingFixId( null );
		}
	};

	const getScoreColor = ( score ) => {
		if ( score >= 80 ) {
			return '#46b450';
		}

		if ( score >= 60 ) {
			return '#ffb900';
		}

		return '#dc3232';
	};

	const getSeverityColor = ( severity ) => {
		switch ( severity ) {
			case 'high':
				return '#dc3232';
			case 'med':
				return '#ffb900';
			default:
				return '#46b450';
		}
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
				{ __( 'GEO AI', 'geo-ai' ) }
			</PluginSidebarMoreMenuItem>
			<PluginSidebar
				name="geoai-sidebar"
				title={ __( 'GEO AI Audit', 'geo-ai' ) }
			>
				<PanelBody>
					<div
						style={ {
							marginBottom: '12px',
							fontSize: '13px',
							color: '#555',
						} }
					>
						<strong>{ __( 'Last audit:', 'geo-ai' ) } </strong>
						{ formatTimestamp( lastAuditTimestamp ) }
					</div>

					{ showAuditReminder && (
						<Notice status="warning" isDismissible={ false }>
							{ reminderMessage }
						</Notice>
					) }

					<Button
						isPrimary
						onClick={ runAudit }
						disabled={ loading }
						style={ { width: '100%', marginBottom: '16px' } }
					>
						{ loading ? (
							<Spinner />
						) : (
							__( 'Run AI Audit', 'geo-ai' )
						) }
					</Button>

					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }

					{ successMessage && (
						<Notice status="success" isDismissible={ false }>
							{ successMessage }
						</Notice>
					) }

					{ auditData && (
						<div className="geoai-audit-results">
							<div
								className="geoai-score-dial"
								style={ {
									textAlign: 'center',
									padding: '20px',
									marginBottom: '16px',
								} }
							>
								<div
									style={ {
										fontSize: '48px',
										fontWeight: 'bold',
										color: getScoreColor(
											auditData.scores.total
										),
									} }
								>
									{ auditData.scores.total }
								</div>
								<div
									style={ {
										fontSize: '14px',
										color: '#666',
									} }
								>
									{ __( 'Overall Score', 'geo-ai' ) }
								</div>
							</div>

							<div style={ { marginBottom: '16px' } }>
								<strong>
									{ __( 'Score Breakdown:', 'geo-ai' ) }
								</strong>
								<ul style={ { listStyle: 'none', padding: 0 } }>
									<li>
										{ __( 'Answerability:', 'geo-ai' ) }{ ' ' }
										{ auditData.scores.answerability }
									</li>
									<li>
										{ __( 'Structure:', 'geo-ai' ) }{ ' ' }
										{ auditData.scores.structure }
									</li>
									<li>
										{ __( 'Trust:', 'geo-ai' ) }{ ' ' }
										{ auditData.scores.trust }
									</li>
									<li>
										{ __( 'Technical:', 'geo-ai' ) }{ ' ' }
										{ auditData.scores.technical }
									</li>
								</ul>
							</div>

							{ auditData.issues &&
								auditData.issues.length > 0 && (
									<div>
										<strong>
											{ __( 'Issues:', 'geo-ai' ) }
										</strong>
										<ul
											style={ {
												listStyle: 'none',
												padding: 0,
											} }
										>
											{ auditData.issues.map(
												( issue, index ) => (
													<li
														key={ index }
														style={ {
															marginBottom:
																'12px',
															padding: '8px',
															border: '1px solid #ddd',
															borderRadius: '4px',
														} }
													>
														<div
															style={ {
																fontSize:
																	'12px',
																color: '#666',
																marginBottom:
																	'4px',
															} }
														>
															<span
																style={ {
																	color: getSeverityColor(
																		issue.severity
																	),
																	fontWeight:
																		'bold',
																} }
															>
																{ issue.severity.toUpperCase() }
															</span>
														</div>
														<div>{ issue.msg }</div>
														{ issue.quickFix && (
															<Button
																isSmall
																isSecondary
																isBusy={
																	applyingFixId ===
																	issue.quickFix
																}
																disabled={
																	applyingFixId !==
																		null &&
																	applyingFixId !==
																		issue.quickFix
																}
																onClick={ () =>
																	applyQuickFix(
																		issue.quickFix
																	)
																}
																style={ {
																	marginTop:
																		'8px',
																} }
															>
																{ __(
																	'Apply Quick Fix',
																	'geo-ai'
																) }
															</Button>
														) }
													</li>
												)
											) }
										</ul>
									</div>
								) }

							{ ( hasTitleOptions ||
								hasEntities ||
								hasCitations ) && (
								<div style={ { marginTop: '16px' } }>
									<strong>
										{ __( 'AI Suggestions', 'geo-ai' ) }
									</strong>

									{ hasTitleOptions && (
										<div style={ { marginTop: '12px' } }>
											<div
												style={ {
													fontWeight: '600',
													marginBottom: '4px',
												} }
											>
												{ __(
													'Meta Title Ideas',
													'geo-ai'
												) }
											</div>
											<ul
												style={ {
													listStyle: 'disc',
													paddingLeft: '20px',
													margin: 0,
												} }
											>
												{ auditData.suggestions.titleOptions.map(
													( title, index ) => (
														<li
															key={ `title-${ index }` }
														>
															{ title }
														</li>
													)
												) }
											</ul>
										</div>
									) }

									{ hasEntities && (
										<div style={ { marginTop: '12px' } }>
											<div
												style={ {
													fontWeight: '600',
													marginBottom: '4px',
												} }
											>
												{ __(
													'Relevant Entities',
													'geo-ai'
												) }
											</div>
											<div
												style={ {
													display: 'flex',
													flexWrap: 'wrap',
													gap: '6px',
												} }
											>
												{ auditData.suggestions.entities.map(
													( entity, index ) => (
														<span
															key={ `entity-${ index }` }
															style={ {
																background:
																	'#f0f0f0',
																borderRadius:
																	'12px',
																padding:
																	'4px 10px',
																fontSize:
																	'12px',
															} }
														>
															{ entity }
														</span>
													)
												) }
											</div>
										</div>
									) }

									{ hasCitations && (
										<div style={ { marginTop: '12px' } }>
											<div
												style={ {
													fontWeight: '600',
													marginBottom: '4px',
												} }
											>
												{ __(
													'Suggested Citations',
													'geo-ai'
												) }
											</div>
											<ul
												style={ {
													listStyle: 'disc',
													paddingLeft: '20px',
													margin: 0,
												} }
											>
												{ auditData.suggestions.citations.map(
													( citation, index ) => (
														<li
															key={ `citation-${ index }` }
														>
															<a
																href={
																	citation
																}
																target="_blank"
																rel="noreferrer"
															>
																{ citation }
															</a>
														</li>
													)
												) }
											</ul>
										</div>
									) }
								</div>
							) }
						</div>
					) }
				</PanelBody>
			</PluginSidebar>
		</>
	);
};

registerPlugin( 'geoai-sidebar', {
	render: GeoAISidebar,
	icon: 'search',
} );
