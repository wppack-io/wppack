import { useState, useEffect } from '@wordpress/element';
import {
	Panel,
	PanelBody,
	TextControl,
	SelectControl,
	Button,
	Notice,
	Spinner,
	BaseControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import './style.css';

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

export default function App() {
	const [ definitions, setDefinitions ] = useState( {} );
	const [ provider, setProvider ] = useState( '' );
	const [ fields, setFields ] = useState( {} );
	const [ source, setSource ] = useState( 'default' );
	const [ isReadonly, setIsReadonly ] = useState( false );
	const [ suppression, setSuppression ] = useState( [] );
	const [ awsRegion, setAwsRegion ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const [ testing, setTesting ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	const applyResponse = ( data ) => {
		setDefinitions( data.definitions || {} );
		setProvider( data.provider || '' );
		setFields( data.fields || {} );
		setSource( data.source || 'default' );
		setIsReadonly( data.readonly || false );
		setSuppression( data.suppression || [] );
		if ( data.awsRegion ) {
			setAwsRegion( data.awsRegion );
		}
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
			data: { provider, fields },
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
			setSuppression( ( prev ) => prev.filter( ( e ) => e !== email ) );
		} );
	};

	if ( loading ) {
		return <div className="wpp-mailer-loading"><Spinner /></div>;
	}

	const def = definitions[ provider ] || null;
	const lastSchemes = [ 'smtp', 'native', 'dsn' ];
	const providerOptions = [
		{ label: __( '— Select —', 'wppack-mailer' ), value: '' },
		...Object.values( definitions )
			.filter( ( d ) => ! lastSchemes.includes( d.scheme ) )
			.sort( ( a, b ) => a.label.localeCompare( b.label ) )
			.map( ( d ) => ( { label: d.label, value: d.scheme } ) ),
		...lastSchemes
			.filter( ( s ) => definitions[ s ] )
			.map( ( s ) => ( { label: definitions[ s ].label, value: s } ) ),
	];

	const isSes = provider.startsWith( 'ses' );

	return (
		<div className="wpp-mailer-settings">
			<h1>
				{ __( 'Mail Settings', 'wppack-mailer' ) }
				<SourceBadge source={ source } />
			</h1>

			{ notice && (
				<Notice status={ notice.type } isDismissible onDismiss={ () => setNotice( null ) }>
					{ notice.message }
				</Notice>
			) }

			<Panel>
				<PanelBody title={ __( 'Transport', 'wppack-mailer' ) } initialOpen={ true }>
					<SelectControl
						label={ __( 'Provider', 'wppack-mailer' ) }
						value={ provider }
						onChange={ ( val ) => {
							setProvider( val );
							setFields( {} );
						} }
						options={ providerOptions }
						disabled={ isReadonly }
						className="wpp-mailer-small-select"
						__nextHasNoMarginBottom
					/>
					{ def && def.fields.map( ( f ) => {
						const wrapStyle = f.maxWidth ? { maxWidth: f.maxWidth } : {};
						const effectiveDefault = ( f.name === 'region' && awsRegion ) ? awsRegion : ( f.default || '' );
						if ( f.options ) {
							return (
								<div key={ f.name } style={ wrapStyle }>
									<SelectControl
										label={ f.label + ( f.required ? ' *' : '' ) }
										help={ f.help || undefined }
										value={ fields[ f.name ] || effectiveDefault }
										onChange={ ( val ) => setFields( ( prev ) => ( { ...prev, [ f.name ]: val } ) ) }
										options={ f.options }
										disabled={ isReadonly }
										__nextHasNoMarginBottom
									/>
								</div>
							);
						}
						return (
							<div key={ f.name } style={ wrapStyle }>
								<TextControl
									label={ f.label + ( f.required ? ' *' : '' ) }
									help={ f.help || undefined }
									type={ f.type === 'password' ? 'password' : f.type === 'number' ? 'number' : 'text' }
									value={ fields[ f.name ] || effectiveDefault }
									onChange={ ( val ) => setFields( ( prev ) => ( { ...prev, [ f.name ]: val } ) ) }
									disabled={ isReadonly }
									placeholder={ f.default || '' }
									__nextHasNoMarginBottom
								/>
							</div>
						);
					} ) }
				</PanelBody>

				{ isSes && suppression.length > 0 && (
					<PanelBody title={ __( 'Suppression List', 'wppack-mailer' ) } initialOpen={ false }>
						<div className="wpp-mailer-suppression-list">
							{ suppression.map( ( email ) => (
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
				) }
			</Panel>

			<div className="wpp-mailer-actions">
				<Button
					variant="primary"
					onClick={ handleSave }
					isBusy={ saving }
					disabled={ saving || ! provider }
				>
					{ saving ? __( 'Saving…', 'wppack-mailer' ) : __( 'Save Settings', 'wppack-mailer' ) }
				</Button>
				<Button
					variant="secondary"
					onClick={ handleTest }
					isBusy={ testing }
					disabled={ testing || source === 'default' }
				>
					{ testing ? __( 'Sending…', 'wppack-mailer' ) : __( 'Send Test Email', 'wppack-mailer' ) }
				</Button>
			</div>
		</div>
	);
}
