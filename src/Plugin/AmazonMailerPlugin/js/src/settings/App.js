import { DataForm } from '@wordpress/dataviews/wp';
import { useState, useEffect, useMemo } from '@wordpress/element';
import {
	Panel,
	PanelBody,
	TextControl,
	SelectControl,
	Button,
	Notice,
	Spinner,
} from '@wordpress/components';
import { Page } from '@wordpress/admin-ui';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const MASKED = '********';

function SourceBadge( { source } ) {
	if ( ! source || source === 'default' ) {
		return null;
	}
	const labels = { constant: 'Constant', option: 'Saved' };
	return (
		<span className={ `wpp-mailer-source-badge wpp-mailer-source-${ source }` }>
			{ labels[ source ] || source }
		</span>
	);
}

function badgeLabel( label, showBadge ) {
	if ( ! showBadge ) {
		return label;
	}
	return <><span>{ label }</span><SourceBadge source="constant" /></>;
}

export default function App() {
	const [ formData, setFormData ] = useState( {
		provider: '',
		fields: {},
	} );
	const [ meta, setMeta ] = useState( {
		definitions: {},
		source: 'default',
		isReadonly: false,
		suppression: [],
		awsRegion: '',
	} );
	const [ saving, setSaving ] = useState( false );
	const [ testing, setTesting ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	const applyResponse = ( data ) => {
		setFormData( {
			provider: data.provider || '',
			fields: data.fields || {},
		} );
		setMeta( {
			definitions: data.definitions || {},
			source: data.source || 'default',
			isReadonly: data.readonly || false,
			suppression: data.suppression || [],
			awsRegion: data.awsRegion || '',
		} );
	};

	useEffect( () => {
		apiFetch( { path: '/wppack/v1/mailer/settings' } )
			.then( ( data ) => {
				applyResponse( data );
				setLoading( false );
			} )
			.catch( () => {
				setNotice( { type: 'error', message: __( 'Failed to load settings.', 'wppack-mailer' ) } );
				setLoading( false );
			} );
	}, [] );

	const handleSave = () => {
		setSaving( true );
		setNotice( null );
		apiFetch( {
			path: '/wppack/v1/mailer/settings',
			method: 'POST',
			data: { provider: formData.provider, fields: formData.fields },
		} )
			.then( ( data ) => {
				applyResponse( data );
				setNotice( { type: 'success', message: __( 'Settings saved.', 'wppack-mailer' ) } );
			} )
			.catch( () => {
				setNotice( { type: 'error', message: __( 'Failed to save settings.', 'wppack-mailer' ) } );
			} )
			.finally( () => setSaving( false ) );
	};

	const handleTest = () => {
		setTesting( true );
		setNotice( null );
		apiFetch( { path: '/wppack/v1/mailer/test', method: 'POST' } )
			.then( ( data ) => {
				setNotice( {
					type: data.success ? 'success' : 'error',
					message: data.success
						? __( 'Test email sent to ', 'wppack-mailer' ) + data.to
						: __( 'Failed to send test email.', 'wppack-mailer' ),
				} );
			} )
			.catch( () => {
				setNotice( { type: 'error', message: __( 'Failed to send test email.', 'wppack-mailer' ) } );
			} )
			.finally( () => setTesting( false ) );
	};

	const handleRemoveSuppression = ( email ) => {
		apiFetch( {
			path: `/wppack/v1/mailer/suppression/${ encodeURIComponent( email ) }`,
			method: 'DELETE',
		} ).then( () => {
			setMeta( ( prev ) => ( {
				...prev,
				suppression: prev.suppression.filter( ( e ) => e !== email ),
			} ) );
		} );
	};

	const def = meta.definitions[ formData.provider ] || null;

	const lastSchemes = [ 'smtp', 'native', 'dsn' ];
	const providerOptions = useMemo( () => [
		...Object.values( meta.definitions )
			.filter( ( d ) => ! lastSchemes.includes( d.scheme ) )
			.sort( ( a, b ) => a.label.localeCompare( b.label ) )
			.map( ( d ) => ( { label: d.label, value: d.scheme } ) ),
		...lastSchemes
			.filter( ( s ) => meta.definitions[ s ] )
			.map( ( s ) => ( { label: meta.definitions[ s ].label, value: s } ) ),
	], [ meta.definitions ] );

	// ── DataForm fields ──

	const transportFields = useMemo( () => {
		const result = [
			{
				id: 'provider',
				label: badgeLabel( __( 'Provider', 'wppack-mailer' ), meta.isReadonly ),
				type: 'text',
				elements: providerOptions,
				getValue: ( { item } ) => item.provider,
				Edit: meta.isReadonly
					? ( { data, field } ) => (
						<SelectControl
							id={ field.id }
							label={ field.label }
							value={ data.provider }
							options={ [ { value: '', label: '—' }, ...providerOptions ] }
							disabled
							__nextHasNoMarginBottom
						/>
					)
					: undefined,
			},
		];

		if ( ! def ) {
			return result;
		}

		for ( const f of def.fields ) {
			const fieldId = `fields.${ f.name }`;
			const effectiveDefault = ( f.name === 'region' && meta.awsRegion ) ? meta.awsRegion : ( f.default || '' );

			if ( f.options ) {
				result.push( {
					id: fieldId,
					label: f.label + ( f.required ? ' *' : '' ),
					type: 'text',
					description: f.help || undefined,
					elements: f.options,
					getValue: ( { item } ) => item.fields[ f.name ] || effectiveDefault,
					setValue: ( value ) => ( { fields: { [ f.name ]: value } } ),
					Edit: meta.isReadonly
						? ( { data, field } ) => (
							<SelectControl
								id={ field.id }
								label={ field.label }
								value={ data.fields[ f.name ] || effectiveDefault }
								options={ f.options }
								disabled
								__nextHasNoMarginBottom
							/>
						)
						: undefined,
				} );
				continue;
			}

			result.push( {
				id: fieldId,
				label: f.label + ( f.required ? ' *' : '' ),
				type: f.type === 'password' ? 'password' : 'text',
				description: f.help || undefined,
				getValue: ( { item } ) => item.fields[ f.name ] || effectiveDefault,
				setValue: ( value ) => ( { fields: { [ f.name ]: value } } ),
				Edit: meta.isReadonly
					? ( { data, field } ) => (
						<TextControl
							id={ field.id }
							label={ field.label }
							value={ data.fields[ f.name ] || effectiveDefault }
							disabled
							__nextHasNoMarginBottom
						/>
					)
					: undefined,
			} );
		}

		return result;
	}, [ def, meta.isReadonly, meta.awsRegion, providerOptions ] );

	const transportForm = useMemo( () => ( {
		fields: [ {
			id: 'transport-section',
			label: __( 'Transport', 'wppack-mailer' ),
			children: transportFields.map( ( f ) => f.id ),
			layout: { type: 'regular' },
		} ],
	} ), [ transportFields ] );

	const handleFormChange = ( edits ) => {
		setFormData( ( prev ) => {
			const next = { ...prev };
			for ( const [ key, value ] of Object.entries( edits ) ) {
				if ( key === 'provider' ) {
					next.provider = value;
					next.fields = {};
				} else if ( key === 'fields' ) {
					next.fields = { ...next.fields, ...value };
				} else {
					next[ key ] = value;
				}
			}
			return next;
		} );
	};

	if ( loading ) {
		return <div className="wpp-mailer-loading"><Spinner /></div>;
	}

	const hasSuppression = def?.capabilities?.includes( 'suppression' );

	return (
		<Page title={ __( 'Mail Settings', 'wppack-mailer' ) } hasPadding>
			<div className="wpp-mailer-settings">
				{ notice && (
					<Notice status={ notice.type } isDismissible onDismiss={ () => setNotice( null ) }>
						{ notice.message }
					</Notice>
				) }

				<div className="wpp-mailer-dataform-wrap">
					<DataForm
						data={ formData }
						fields={ transportFields }
						form={ transportForm }
						onChange={ handleFormChange }
					/>
				</div>

				{ hasSuppression && meta.suppression.length > 0 && (
					<Panel>
						<PanelBody title={ __( 'Suppression List', 'wppack-mailer' ) } initialOpen={ false }>
							<div className="wpp-mailer-suppression-list">
								{ meta.suppression.map( ( email ) => (
									<div key={ email } className="wpp-mailer-suppression-item">
										<span>{ email }</span>
										<Button
											variant="tertiary"
											isDestructive
											size="small"
											onClick={ () => handleRemoveSuppression( email ) }
										>
											{ __( 'Remove', 'wppack-mailer' ) }
										</Button>
									</div>
								) ) }
							</div>
						</PanelBody>
					</Panel>
				) }

				<div className="wpp-mailer-actions">
					<Button
						variant="primary"
						onClick={ handleSave }
						isBusy={ saving }
						disabled={ saving || ! formData.provider }
					>
						{ saving ? __( 'Saving…', 'wppack-mailer' ) : __( 'Save Settings', 'wppack-mailer' ) }
					</Button>
					<Button
						variant="secondary"
						onClick={ handleTest }
						isBusy={ testing }
						disabled={ testing || meta.source === 'default' }
					>
						{ testing ? __( 'Sending…', 'wppack-mailer' ) : __( 'Send Test Email', 'wppack-mailer' ) }
					</Button>
				</div>
			</div>
		</Page>
	);
}
