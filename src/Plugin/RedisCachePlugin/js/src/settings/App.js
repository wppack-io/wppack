import { DataForm } from '@wordpress/dataviews/wp';
import { useState, useEffect, useMemo } from '@wordpress/element';
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
import { Page } from '@wordpress/admin-ui';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

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
		globalOptions: {
			prefix: 'wp:',
			maxTtl: '',
			hashAlloptions: false,
			asyncFlush: false,
			compression: 'none',
			serializer: 'none',
			clientLibrary: '',
		},
	} );
	const [ meta, setMeta ] = useState( {
		definitions: {},
		source: 'default',
		isReadonly: false,
		awsRegion: '',
		extensions: {},
		globalReadonly: {},
	} );
	const [ saving, setSaving ] = useState( false );
	const [ testing, setTesting ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	const applyResponse = ( data ) => {
		const go = data.globalOptions || {};
		setFormData( {
			provider: data.provider || '',
			fields: data.fields || {},
			globalOptions: {
				prefix: go.prefix ?? 'wp:',
				maxTtl: go.maxTtl !== null && go.maxTtl !== '' ? String( go.maxTtl ) : '',
				hashAlloptions: !! go.hashAlloptions,
				asyncFlush: !! go.asyncFlush,
				compression: go.compression ?? 'none',
				serializer: go.serializer ?? 'none',
				clientLibrary: go.clientLibrary ?? '',
			},
		} );
		setMeta( {
			definitions: data.definitions || {},
			source: data.source || 'default',
			isReadonly: data.readonly || false,
			awsRegion: data.awsRegion || '',
			extensions: data.extensions || {},
			globalReadonly: ( data.globalOptions || {} ).readonlyFields || {},
		} );
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
				provider: formData.provider,
				fields: formData.fields,
				globalOptions: {
					prefix: formData.globalOptions.prefix,
					maxTtl: formData.globalOptions.maxTtl !== '' ? Number( formData.globalOptions.maxTtl ) : null,
					hashAlloptions: formData.globalOptions.hashAlloptions,
					asyncFlush: formData.globalOptions.asyncFlush,
					compression: formData.globalOptions.compression,
					serializer: formData.globalOptions.serializer,
					clientLibrary: formData.globalOptions.clientLibrary,
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

	const def = meta.definitions[ formData.provider ] || null;
	const cap = ( name ) => def?.capabilities?.includes( name );

	// ── Provider DataForm fields ──

	const lastSchemes = [ 'apcu', 'dsn' ];
	const providerOptions = useMemo( () => [
		...Object.values( meta.definitions )
			.filter( ( d ) => ! lastSchemes.includes( d.scheme ) )
			.sort( ( a, b ) => a.label.localeCompare( b.label ) )
			.map( ( d ) => ( { label: d.label, value: d.scheme } ) ),
		...lastSchemes
			.filter( ( s ) => meta.definitions[ s ] )
			.map( ( s ) => ( { label: meta.definitions[ s ].label, value: s } ) ),
	], [ meta.definitions ] );

	const providerFields = useMemo( () => {
		const result = [
			{
				id: 'provider',
				label: badgeLabel( __( 'Provider', 'wppack-cache' ), meta.isReadonly ),
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

			// Boolean fields
			if ( f.type === 'boolean' ) {
				const awsPattern = /\.cache\.amazonaws\.com|\.memorydb\.amazonaws\.com/;
				result.push( {
					id: fieldId,
					label: f.label,
					type: 'text',
					getValue: ( { item } ) => item.fields[ f.name ] ?? false,
					Edit: ( { data } ) => {
						const isAwsHost = awsPattern.test( data.fields.host || '' ) || awsPattern.test( data.fields.nodes || '' );
						const iamDisabled = meta.isReadonly || ( f.name === 'iamAuth' && ! isAwsHost );
						return (
							<ToggleControl
								label={ f.label }
								help={ iamDisabled && f.name === 'iamAuth' && ! meta.isReadonly
									? __( 'Enter an AWS endpoint to enable IAM authentication', 'wppack-cache' )
									: ( f.help || undefined ) }
								checked={ !! data.fields[ f.name ] }
								onChange={ ( val ) => setFormData( ( prev ) => ( {
									...prev,
									fields: { ...prev.fields, [ f.name ]: val },
								} ) ) }
								disabled={ iamDisabled }
								__nextHasNoMarginBottom
							/>
						);
					},
					_conditional: f.conditional,
				} );
				continue;
			}

			// Select fields
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
					_conditional: f.conditional,
				} );
				continue;
			}

			// Textarea fields
			if ( f.type === 'textarea' ) {
				result.push( {
					id: fieldId,
					label: f.label + ( f.required ? ' *' : '' ),
					type: 'text',
					description: f.help || undefined,
					getValue: ( { item } ) => item.fields[ f.name ] || effectiveDefault,
					Edit: ( { data, field } ) => (
						<TextareaControl
							label={ field.label }
							help={ f.help || undefined }
							value={ data.fields[ f.name ] || effectiveDefault }
							onChange={ ( val ) => setFormData( ( prev ) => ( {
								...prev,
								fields: { ...prev.fields, [ f.name ]: val },
							} ) ) }
							disabled={ meta.isReadonly }
							rows={ 3 }
							__nextHasNoMarginBottom
						/>
					),
					_conditional: f.conditional,
				} );
				continue;
			}

			// Text/password/number fields
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
				_conditional: f.conditional,
			} );
		}

		// Filter by conditional visibility
		return result.filter( ( field ) => {
			if ( ! field._conditional ) {
				return true;
			}
			const isNeg = field._conditional.startsWith( '!' );
			const refName = isNeg ? field._conditional.slice( 1 ) : field._conditional;
			const refVal = !! formData.fields[ refName ];
			return isNeg ? ! refVal : refVal;
		} );
	}, [ def, meta.isReadonly, meta.awsRegion, providerOptions, formData.fields ] );

	const providerForm = useMemo( () => ( {
		fields: [ {
			id: 'provider-section',
			label: __( 'Provider', 'wppack-cache' ),
			children: providerFields.map( ( f ) => f.id ),
			layout: { type: 'regular' },
		} ],
	} ), [ providerFields ] );

	// ── Options DataForm fields ──

	const optionsFields = useMemo( () => {
		const result = [
			{
				id: 'globalOptions.prefix',
				label: badgeLabel( __( 'Key Prefix', 'wppack-cache' ), meta.globalReadonly.prefix ),
				type: 'text',
				getValue: ( { item } ) => item.globalOptions.prefix,
				setValue: ( value ) => ( { globalOptions: { prefix: value } } ),
				Edit: ( { data, field } ) => (
					<TextControl
						className="wpp-cache-narrow-input"
						id={ field.id }
						label={ field.label }
						value={ data.globalOptions.prefix }
						onChange={ ( val ) => setFormData( ( prev ) => ( {
							...prev,
							globalOptions: { ...prev.globalOptions, prefix: val },
						} ) ) }
						disabled={ !! meta.globalReadonly.prefix }
						__nextHasNoMarginBottom
					/>
				),
			},
			{
				id: 'globalOptions.maxTtl',
				label: badgeLabel( __( 'Max TTL', 'wppack-cache' ), meta.globalReadonly.maxTtl ),
				type: 'text',
				getValue: ( { item } ) => item.globalOptions.maxTtl,
				setValue: ( value ) => ( { globalOptions: { maxTtl: value } } ),
				Edit: ( { data, field } ) => (
					<TextControl
						className="wpp-cache-narrow-input"
						id={ field.id }
						label={ field.label }
						type="number"
						value={ data.globalOptions.maxTtl }
						onChange={ ( val ) => setFormData( ( prev ) => ( {
							...prev,
							globalOptions: { ...prev.globalOptions, maxTtl: val },
						} ) ) }
						disabled={ !! meta.globalReadonly.maxTtl }
						__nextHasNoMarginBottom
					/>
				),
			},
		];

		if ( cap( 'hashAlloptions' ) ) {
			result.push( {
				id: 'globalOptions.hashAlloptions',
				label: badgeLabel( __( 'Hash Alloptions', 'wppack-cache' ), meta.globalReadonly.hashAlloptions ),
				type: 'text',
				getValue: ( { item } ) => item.globalOptions.hashAlloptions,
				Edit: ( { data, field } ) => (
					<ToggleControl
						label={ field.label }
						checked={ data.globalOptions.hashAlloptions }
						onChange={ ( val ) => setFormData( ( prev ) => ( {
							...prev,
							globalOptions: { ...prev.globalOptions, hashAlloptions: val },
						} ) ) }
						disabled={ !! meta.globalReadonly.hashAlloptions }
						__nextHasNoMarginBottom
					/>
				),
			} );
		}

		if ( cap( 'asyncFlush' ) ) {
			result.push( {
				id: 'globalOptions.asyncFlush',
				label: badgeLabel( __( 'Async Flush', 'wppack-cache' ), meta.globalReadonly.asyncFlush ),
				type: 'text',
				getValue: ( { item } ) => item.globalOptions.asyncFlush,
				Edit: ( { data, field } ) => (
					<ToggleControl
						label={ field.label }
						checked={ data.globalOptions.asyncFlush }
						onChange={ ( val ) => setFormData( ( prev ) => ( {
							...prev,
							globalOptions: { ...prev.globalOptions, asyncFlush: val },
						} ) ) }
						disabled={ !! meta.globalReadonly.asyncFlush }
						__nextHasNoMarginBottom
					/>
				),
			} );
		}

		if ( cap( 'compression' ) ) {
			result.push( {
				id: 'globalOptions.compression',
				label: badgeLabel( __( 'Compression', 'wppack-cache' ), meta.globalReadonly.compression ),
				type: 'text',
				getValue: ( { item } ) => item.globalOptions.compression,
				Edit: ( { data, field } ) => (
					<SelectControl
						className="wpp-cache-narrow-select"
						label={ field.label }
						value={ data.globalOptions.compression }
						onChange={ ( val ) => setFormData( ( prev ) => ( {
							...prev,
							globalOptions: { ...prev.globalOptions, compression: val },
						} ) ) }
						options={ [
							{ label: 'none', value: 'none' },
							{ label: meta.extensions.zstd ? 'zstd' : 'zstd (not installed)', value: 'zstd' },
							{ label: meta.extensions.lz4 ? 'lz4' : 'lz4 (not installed)', value: 'lz4' },
							{ label: meta.extensions.lzf ? 'lzf' : 'lzf (not installed)', value: 'lzf' },
						] }
						disabled={ !! meta.globalReadonly.compression }
						__nextHasNoMarginBottom
					/>
				),
			} );
		}

		if ( cap( 'serializer' ) ) {
			result.push( {
				id: 'globalOptions.serializer',
				label: badgeLabel( __( 'Serializer', 'wppack-cache' ), meta.globalReadonly.serializer ),
				type: 'text',
				getValue: ( { item } ) => item.globalOptions.serializer,
				Edit: ( { data, field } ) => (
					<SelectControl
						className="wpp-cache-narrow-select"
						label={ field.label }
						value={ data.globalOptions.serializer || 'none' }
						onChange={ ( val ) => setFormData( ( prev ) => ( {
							...prev,
							globalOptions: { ...prev.globalOptions, serializer: val },
						} ) ) }
						options={ [
							{ label: 'PHP (default)', value: 'none' },
							{ label: meta.extensions.igbinary ? 'igbinary' : 'igbinary (not installed)', value: 'igbinary' },
							{ label: meta.extensions.msgpack ? 'msgpack' : 'msgpack (not installed)', value: 'msgpack' },
						] }
						disabled={ !! meta.globalReadonly.serializer }
						help={ __( 'Redis-side serializer. Requires ext-igbinary or ext-msgpack.', 'wppack-cache' ) }
						__nextHasNoMarginBottom
					/>
				),
			} );
		}

		if ( cap( 'clientLibrary' ) ) {
			result.push( {
				id: 'globalOptions.clientLibrary',
				label: badgeLabel( __( 'Client Library', 'wppack-cache' ), meta.globalReadonly.clientLibrary ),
				type: 'text',
				getValue: ( { item } ) => item.globalOptions.clientLibrary,
				Edit: ( { data, field } ) => (
					<SelectControl
						className="wpp-cache-narrow-select"
						label={ field.label }
						value={ data.globalOptions.clientLibrary || '' }
						onChange={ ( val ) => setFormData( ( prev ) => ( {
							...prev,
							globalOptions: { ...prev.globalOptions, clientLibrary: val },
						} ) ) }
						options={ [
							{ label: 'Auto-detect', value: '' },
							{ label: meta.extensions.redis ? 'PhpRedis (ext-redis)' : 'PhpRedis (not installed)', value: 'Redis' },
							{ label: meta.extensions.relay ? 'Relay (ext-relay)' : 'Relay (not installed)', value: 'Relay\\Relay' },
							{ label: meta.extensions.predis ? 'Predis' : 'Predis (not installed)', value: 'Predis\\Client' },
						] }
						disabled={ !! meta.globalReadonly.clientLibrary }
						__nextHasNoMarginBottom
					/>
				),
			} );
		}

		return result;
	}, [ def, meta.extensions, meta.globalReadonly ] );

	const optionsForm = useMemo( () => ( {
		fields: [ {
			id: 'options-section',
			label: __( 'Options', 'wppack-cache' ),
			children: optionsFields.map( ( f ) => f.id ),
			layout: { type: 'regular' },
		} ],
	} ), [ optionsFields ] );

	const handleFormChange = ( edits ) => {
		setFormData( ( prev ) => {
			const next = { ...prev };
			for ( const [ key, value ] of Object.entries( edits ) ) {
				if ( key === 'provider' ) {
					next.provider = value;
					next.fields = {};
				} else if ( key === 'fields' ) {
					next.fields = { ...next.fields, ...value };
				} else if ( key === 'globalOptions' ) {
					next.globalOptions = { ...next.globalOptions, ...value };
				} else {
					next[ key ] = value;
				}
			}
			return next;
		} );
	};

	if ( loading ) {
		return <div className="wpp-cache-loading"><Spinner /></div>;
	}

	return (
		<Page title={ __( 'Cache Settings', 'wppack-cache' ) } hasPadding>
			<div className="wpp-cache-settings">
				{ notice && (
					<Notice status={ notice.type } isDismissible onDismiss={ () => setNotice( null ) }>
						{ notice.message }
					</Notice>
				) }

				<Panel>
					<PanelBody title={ __( 'Provider', 'wppack-cache' ) } initialOpen={ true }>
						<div className="wpp-cache-dataform-wrap">
							<DataForm
								data={ formData }
								fields={ providerFields }
								form={ providerForm }
								onChange={ handleFormChange }
							/>
						</div>
					</PanelBody>

					<PanelBody title={ __( 'Options', 'wppack-cache' ) } initialOpen={ true }>
						<div className="wpp-cache-dataform-wrap">
							<DataForm
								data={ formData }
								fields={ optionsFields }
								form={ optionsForm }
								onChange={ handleFormChange }
							/>
						</div>
					</PanelBody>
				</Panel>

				<div className="wpp-cache-actions">
					<Button
						variant="primary"
						onClick={ handleSave }
						isBusy={ saving }
						disabled={ saving || ! formData.provider }
					>
						{ saving ? __( 'Saving…', 'wppack-cache' ) : __( 'Save Settings', 'wppack-cache' ) }
					</Button>
					<Button
						variant="secondary"
						onClick={ handleTest }
						isBusy={ testing }
						disabled={ testing || meta.source === 'default' }
					>
						{ testing ? __( 'Testing…', 'wppack-cache' ) : __( 'Test Connection', 'wppack-cache' ) }
					</Button>
				</div>
			</div>
		</Page>
	);
}
