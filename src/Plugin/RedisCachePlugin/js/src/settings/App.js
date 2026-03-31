import { useState, useEffect } from '@wordpress/element';
import {
	Panel,
	PanelBody,
	TextControl,
	TextareaControl,
	SelectControl,
	ToggleControl,
	Button,
	Notice,
	Spinner,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import './style.css';

const MASKED = '********';

function SourceBadge( { source } ) {
	if ( ! source || source === 'default' ) {
		return null;
	}
	const labels = {
		constant: __( 'Constant', 'wppack-cache' ),
		option: __( 'Saved', 'wppack-cache' ),
	};
	return (
		<span className={ `wpp-cache-source-badge wpp-cache-source-${ source }` }>
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
	const [ awsRegion, setAwsRegion ] = useState( '' );
	const [ globalForm, setGlobalForm ] = useState( {
		prefix: 'wp:',
		maxTtl: '',
		hashAlloptions: false,
		asyncFlush: false,
		compression: 'none',
	} );
	const [ globalReadonly, setGlobalReadonly ] = useState( {} );
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
		if ( data.awsRegion ) {
			setAwsRegion( data.awsRegion );
		}
		if ( data.globalOptions ) {
			const go = data.globalOptions;
			setGlobalForm( {
				prefix: go.prefix ?? 'wp:',
				maxTtl: go.maxTtl !== null && go.maxTtl !== '' ? String( go.maxTtl ) : '',
				hashAlloptions: !! go.hashAlloptions,
				asyncFlush: !! go.asyncFlush,
				compression: go.compression ?? 'none',
			} );
			setGlobalReadonly( go.readonlyFields || {} );
		}
	};

	useEffect( () => {
		apiFetch( { path: '/wppack/v1/cache/settings' } )
			.then( ( data ) => {
				applyResponse( data );
				setLoading( false );
			} )
			.catch( () => {
				setNotice( { type: 'error', message: __( 'Failed to load settings.', 'wppack-cache' ) } );
				setLoading( false );
			} );
	}, [] );

	const handleSave = () => {
		setSaving( true );
		setNotice( null );
		apiFetch( {
			path: '/wppack/v1/cache/settings',
			method: 'POST',
			data: {
				provider,
				fields,
				globalOptions: {
					prefix: globalForm.prefix,
					maxTtl: globalForm.maxTtl !== '' ? Number( globalForm.maxTtl ) : null,
					hashAlloptions: globalForm.hashAlloptions,
					asyncFlush: globalForm.asyncFlush,
					compression: globalForm.compression,
				},
			},
		} )
			.then( ( data ) => {
				applyResponse( data );
				setNotice( { type: 'success', message: __( 'Settings saved.', 'wppack-cache' ) } );
			} )
			.catch( () => {
				setNotice( { type: 'error', message: __( 'Failed to save settings.', 'wppack-cache' ) } );
			} )
			.finally( () => setSaving( false ) );
	};

	const handleTest = () => {
		setTesting( true );
		setNotice( null );
		apiFetch( { path: '/wppack/v1/cache/test', method: 'POST' } )
			.then( ( data ) => {
				setNotice( {
					type: data.success ? 'success' : 'error',
					message: data.success
						? __( 'Connection test passed.', 'wppack-cache' )
						: __( 'Connection test failed.', 'wppack-cache' ),
				} );
			} )
			.catch( () => {
				setNotice( { type: 'error', message: __( 'Connection test failed.', 'wppack-cache' ) } );
			} )
			.finally( () => setTesting( false ) );
	};

	if ( loading ) {
		return <div className="wpp-cache-loading"><Spinner /></div>;
	}

	const def = definitions[ provider ] || null;
	const lastSchemes = [ 'apcu', 'dsn' ];
	const providerOptions = [
		{ label: __( '— Select —', 'wppack-cache' ), value: '' },
		...Object.values( definitions )
			.filter( ( d ) => ! lastSchemes.includes( d.scheme ) )
			.sort( ( a, b ) => a.label.localeCompare( b.label ) )
			.map( ( d ) => ( { label: d.label, value: d.scheme } ) ),
		...lastSchemes
			.filter( ( s ) => definitions[ s ] )
			.map( ( s ) => ( { label: definitions[ s ].label, value: s } ) ),
	];

	const compressionOptions = [
		{ label: 'none', value: 'none' },
		{ label: 'zstd', value: 'zstd' },
		{ label: 'lz4', value: 'lz4' },
		{ label: 'lzf', value: 'lzf' },
	];

	return (
		<div className="wpp-cache-settings">
			<h1>{ __( 'Cache Settings', 'wppack-cache' ) }</h1>

			{ notice && (
				<Notice status={ notice.type } isDismissible onDismiss={ () => setNotice( null ) }>
					{ notice.message }
				</Notice>
			) }

			<Panel>
				<PanelBody title={ __( 'Provider', 'wppack-cache' ) } initialOpen={ true }>
					<SelectControl
						label={ <><span>{ __( 'Provider', 'wppack-cache' ) }</span>{ isReadonly && <SourceBadge source="constant" /> }</> }
						value={ provider }
						onChange={ ( val ) => {
							setProvider( val );
							setFields( {} );
						} }
						options={ providerOptions }
						disabled={ isReadonly }
						className="wpp-cache-small-select"
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
						if ( f.type === 'textarea' ) {
							return (
								<div key={ f.name } style={ wrapStyle }>
									<TextareaControl
										label={ f.label + ( f.required ? ' *' : '' ) }
										help={ f.help || undefined }
										value={ fields[ f.name ] || effectiveDefault }
										onChange={ ( val ) => setFields( ( prev ) => ( { ...prev, [ f.name ]: val } ) ) }
										disabled={ isReadonly }
										rows={ 3 }
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

				<PanelBody title={ __( 'Options', 'wppack-cache' ) } initialOpen={ true }>
					<TextControl
						label={ <><span>{ __( 'Key Prefix', 'wppack-cache' ) }</span>{ globalReadonly.prefix && <SourceBadge source="constant" /> }</> }
						value={ globalForm.prefix }
						onChange={ ( val ) => setGlobalForm( ( prev ) => ( { ...prev, prefix: val } ) ) }
						disabled={ !! globalReadonly.prefix }
						__nextHasNoMarginBottom
					/>
					<div style={ { maxWidth: '120px' } }>
						<TextControl
							label={ <><span>{ __( 'Max TTL', 'wppack-cache' ) }</span>{ globalReadonly.maxTtl && <SourceBadge source="constant" /> }</> }
							type="number"
							value={ globalForm.maxTtl }
							onChange={ ( val ) => setGlobalForm( ( prev ) => ( { ...prev, maxTtl: val } ) ) }
							disabled={ !! globalReadonly.maxTtl }
							__nextHasNoMarginBottom
						/>
					</div>
					<ToggleControl
						label={ <><span>{ __( 'Hash Alloptions', 'wppack-cache' ) }</span>{ globalReadonly.hashAlloptions && <SourceBadge source="constant" /> }</> }
						checked={ globalForm.hashAlloptions }
						onChange={ ( val ) => setGlobalForm( ( prev ) => ( { ...prev, hashAlloptions: val } ) ) }
						disabled={ !! globalReadonly.hashAlloptions }
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ <><span>{ __( 'Async Flush', 'wppack-cache' ) }</span>{ globalReadonly.asyncFlush && <SourceBadge source="constant" /> }</> }
						checked={ globalForm.asyncFlush }
						onChange={ ( val ) => setGlobalForm( ( prev ) => ( { ...prev, asyncFlush: val } ) ) }
						disabled={ !! globalReadonly.asyncFlush }
						__nextHasNoMarginBottom
					/>
					<div style={ { maxWidth: '200px' } }>
						<SelectControl
							label={ <><span>{ __( 'Compression', 'wppack-cache' ) }</span>{ globalReadonly.compression && <SourceBadge source="constant" /> }</> }
							value={ globalForm.compression }
							onChange={ ( val ) => setGlobalForm( ( prev ) => ( { ...prev, compression: val } ) ) }
							options={ compressionOptions }
							disabled={ !! globalReadonly.compression }
							__nextHasNoMarginBottom
						/>
					</div>
				</PanelBody>
			</Panel>

			<div className="wpp-cache-actions">
				<Button
					variant="primary"
					onClick={ handleSave }
					isBusy={ saving }
					disabled={ saving || ! provider }
				>
					{ saving ? __( 'Saving…', 'wppack-cache' ) : __( 'Save Settings', 'wppack-cache' ) }
				</Button>
				<Button
					variant="secondary"
					onClick={ handleTest }
					isBusy={ testing }
					disabled={ testing || source === 'default' }
				>
					{ testing ? __( 'Testing…', 'wppack-cache' ) : __( 'Test Connection', 'wppack-cache' ) }
				</Button>
			</div>
		</div>
	);
}
