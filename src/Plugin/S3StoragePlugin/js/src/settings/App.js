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
	const labels = {
		constant: __( 'Constant', 'wppack-storage' ),
		option: __( 'Saved', 'wppack-storage' ),
	};
	return (
		<span className={ `wpp-storage-source-badge wpp-storage-source-${ source }` }>
			{ labels[ source ] || source }
		</span>
	);
}

/**
 * Parse a DSN string into individual fields.
 *
 * DSN format: scheme://accessKey:secretKey@bucket?region=value
 */
function parseDsn( dsn ) {
	if ( ! dsn ) {
		return {};
	}
	try {
		const match = dsn.match( /^(\w+):\/\/([^:]*):([^@]*)@([^?]+)\??(.*)$/ );
		if ( ! match ) {
			return {};
		}
		const params = new URLSearchParams( match[ 5 ] );
		return {
			accessKey: match[ 2 ] || '',
			secretKey: match[ 3 ] || '',
			region: params.get( 'region' ) || '',
		};
	} catch {
		return {};
	}
}

/**
 * Build a DSN string from individual fields.
 */
function buildDsn( scheme, bucket, fields ) {
	const accessKey = fields.accessKey || '';
	const secretKey = fields.secretKey || '';
	const params = new URLSearchParams();
	if ( fields.region ) {
		params.set( 'region', fields.region );
	}
	const query = params.toString();
	return `${ scheme }://${ accessKey }:${ secretKey }@${ bucket }${ query ? '?' + query : '' }`;
}

/**
 * Extract bucket name from a URI like "s3://my-bucket".
 */
function bucketFromUri( uri ) {
	const match = uri.match( /^\w+:\/\/(.+)$/ );
	return match ? match[ 1 ] : '';
}

/**
 * Extract scheme from a URI like "s3://my-bucket".
 */
function schemeFromUri( uri ) {
	const match = uri.match( /^(\w+):\/\// );
	return match ? match[ 1 ] : '';
}

const AWS_REGIONS = [
	{ value: '', label: '\u2014' },
	...( window.wppStorage?.awsRegions ?? [] ),
];

/**
 * Build a lookup of sensitive field names from a definition's fields array.
 */
function getSensitiveFieldNames( fields ) {
	return ( fields || [] )
		.filter( ( f ) => f.sensitive )
		.map( ( f ) => f.name );
}

/**
 * Find a field object by name from a definition's fields array.
 */
function findField( fields, name ) {
	return ( fields || [] ).find( ( f ) => f.name === name );
}

function StoragePanel( { uri, storage, definitions, onChange, onDelete, isReadonly } ) {
	const scheme = schemeFromUri( uri );
	const def = Object.values( definitions ).find( ( d ) => d.scheme === scheme ) || {};
	const dsnFields = parseDsn( storage.dsn );

	const defFields = def.fields || [];
	const sensitiveFieldNames = useMemo( () => getSensitiveFieldNames( defFields ), [ defFields ] );

	const panelData = useMemo( () => ( {
		provider: def.label || scheme,
		bucket: bucketFromUri( uri ),
		...Object.fromEntries(
			defFields
				.filter( ( f ) => ! [ 'bucket', 'container' ].includes( f.name ) )
				.map( ( f ) => [ f.name, dsnFields[ f.name ] || '' ] )
		),
		cdnUrl: storage.cdnUrl || '',
	} ), [ def, scheme, uri, dsnFields, storage.cdnUrl, defFields ] );

	const handlePanelChange = ( edits ) => {
		const bucket = bucketFromUri( uri );
		const newDsnFields = { ...dsnFields };
		let newCdnUrl = storage.cdnUrl || '';

		for ( const [ key, value ] of Object.entries( edits ) ) {
			if ( key === 'cdnUrl' ) {
				newCdnUrl = value;
			} else if ( key !== 'provider' && key !== 'bucket' ) {
				newDsnFields[ key ] = value;
				// Preserve masked values for other sensitive fields
				sensitiveFieldNames.forEach( ( sf ) => {
					if ( sf !== key && dsnFields[ sf ] === MASKED ) {
						newDsnFields[ sf ] = MASKED;
					}
				} );
			}
		}

		onChange( uri, {
			...storage,
			dsn: buildDsn( scheme, bucket, newDsnFields ),
			cdnUrl: newCdnUrl,
		} );
	};

	const panelFields = useMemo( () => {
		const bucketField = findField( defFields, 'bucket' ) || findField( defFields, 'container' );

		const result = [
			{
				id: 'provider',
				label: __( 'Provider', 'wppack-storage' ),
				type: 'text',
				Edit: ( { data, field } ) => (
					<TextControl
						id={ field.id }
						label={ field.label }
						value={ data.provider }
						disabled
						onChange={ () => {} }
						__nextHasNoMarginBottom
					/>
				),
			},
			{
				id: 'bucket',
				label: bucketField?.label || 'Bucket',
				type: 'text',
				Edit: ( { data, field } ) => (
					<TextControl
						id={ field.id }
						label={ field.label }
						value={ data.bucket }
						disabled
						onChange={ () => {} }
						__nextHasNoMarginBottom
					/>
				),
			},
		];

		for ( const fieldDef of defFields ) {
			const fieldKey = fieldDef.name;

			if ( [ 'bucket', 'container' ].includes( fieldKey ) ) {
				continue;
			}

			const isSensitive = !! fieldDef.sensitive;

			if ( fieldKey === 'region' ) {
				result.push( {
					id: fieldKey,
					label: fieldDef.label || fieldKey,
					type: 'text',
					elements: AWS_REGIONS.filter( ( r ) => r.value !== '' ),
					Edit: isReadonly
						? ( { data, field } ) => {
							const el = AWS_REGIONS.find( ( r ) => r.value === ( data[ fieldKey ] || '' ) );
							return (
								<TextControl
									id={ field.id }
									label={ field.label }
									value={ el ? el.label : data[ fieldKey ] || '' }
									disabled
									__nextHasNoMarginBottom
								/>
							);
						}
						: undefined,
				} );
				continue;
			}

			result.push( {
				id: fieldKey,
				label: fieldDef.label || fieldKey,
				type: isSensitive ? 'password' : 'text',
				Edit: isReadonly
					? ( { data, field } ) => (
						<TextControl
							id={ field.id }
							label={ field.label }
							type={ isSensitive ? 'password' : 'text' }
							value={ data[ fieldKey ] || '' }
							disabled
							onChange={ () => {} }
							__nextHasNoMarginBottom
						/>
					)
					: undefined,
			} );
		}

		result.push( {
			id: 'cdnUrl',
			label: __( 'CDN URL', 'wppack-storage' ),
			type: 'text',
			description: __( 'CDN base URL for public file access.', 'wppack-storage' ),
			Edit: isReadonly
				? ( { data, field } ) => (
					<TextControl
						id={ field.id }
						label={ field.label }
						help={ __( 'CDN base URL for public file access.', 'wppack-storage' ) }
						value={ data.cdnUrl || '' }
						disabled
						onChange={ () => {} }
						__nextHasNoMarginBottom
					/>
				)
				: undefined,
		} );

		return result;
	}, [ defFields, isReadonly ] );

	const panelForm = useMemo( () => ( {
		fields: panelFields.map( ( f ) => f.id ),
	} ), [ panelFields ] );

	const titleElement = (
		<span className="wpp-storage-panel-title">
			{ uri }
			{ isReadonly && <SourceBadge source="constant" /> }
		</span>
	);

	return (
		<PanelBody
			title={ titleElement }
			initialOpen={ false }
			className="wpp-storage-provider-panel"
		>
			{ isReadonly && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'This storage is defined via constant and cannot be edited.',
						'wppack-storage'
					) }
				</Notice>
			) }
			<div className="wpp-storage-dataform-wrap">
				<DataForm
					data={ panelData }
					fields={ panelFields }
					form={ panelForm }
					onChange={ handlePanelChange }
				/>
			</div>
			{ ! isReadonly && (
				<div className="wpp-storage-delete-storage">
					<Button
						variant="secondary"
						isDestructive
						onClick={ () => onDelete( uri ) }
					>
						{ __( 'Delete Storage Settings', 'wppack-storage' ) }
					</Button>
				</div>
			) }
		</PanelBody>
	);
}

export default function App() {
	const [ definitions, setDefinitions ] = useState( {} );
	const [ storages, setStorages ] = useState( {} );
	const [ storageUris, setStorageUris ] = useState( [] );
	const [ primary, setPrimary ] = useState( '' );
	const [ uploadsPath, setUploadsPath ] = useState( '' );
	const [ source, setSource ] = useState( 'default' );
	const [ newStorage, setNewStorage ] = useState( {
		providerType: '',
		bucket: '',
		region: '',
		accessKey: '',
		secretKey: '',
	} );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	const applyResponse = ( data ) => {
		setDefinitions( data.definitions || {} );
		setStorages( data.storages || {} );
		setStorageUris( Object.keys( data.storages || {} ) );
		setPrimary( data.primary || '' );
		setUploadsPath( data.uploadsPath || '' );
		setSource( data.source || 'default' );
	};

	useEffect( () => {
		apiFetch( { path: '/wppack/v1/storage/settings' } )
			.then( ( data ) => {
				applyResponse( data );
				setLoading( false );
			} )
			.catch( () => {
				setNotice( { type: 'error', message: __( 'Failed to load settings.', 'wppack-storage' ) } );
				setLoading( false );
			} );
	}, [] );

	const handleSave = () => {
		setSaving( true );
		setNotice( null );

		const orderedStorages = {};
		storageUris.forEach( ( uri ) => {
			if ( storages[ uri ] ) {
				orderedStorages[ uri ] = {
					dsn: storages[ uri ].dsn,
					cdnUrl: storages[ uri ].cdnUrl,
				};
			}
		} );

		apiFetch( {
			path: '/wppack/v1/storage/settings',
			method: 'POST',
			data: {
				storages: orderedStorages,
				primary,
				uploadsPath,
			},
		} )
			.then( ( data ) => {
				applyResponse( data );
				setNotice( { type: 'success', message: __( 'Settings saved.', 'wppack-storage' ) } );
			} )
			.catch( () => {
				setNotice( { type: 'error', message: __( 'Failed to save settings.', 'wppack-storage' ) } );
			} )
			.finally( () => setSaving( false ) );
	};

	const updateStorage = ( uri, storage ) => {
		setStorages( ( prev ) => ( { ...prev, [ uri ]: storage } ) );
	};

	/**
	 * Resolve the scheme for a given provider key from definitions.
	 */
	const schemeForProvider = ( providerKey ) => {
		const def = definitions[ providerKey ];
		return def?.scheme || providerKey;
	};

	const newUri = newStorage.providerType && newStorage.bucket
		? `${ schemeForProvider( newStorage.providerType ) }://${ newStorage.bucket }`
		: '';

	const handleAddStorage = () => {
		if ( ! newStorage.providerType || ! newStorage.bucket ) {
			return;
		}

		const scheme = schemeForProvider( newStorage.providerType );
		const uri = `${ scheme }://${ newStorage.bucket }`;

		if ( storages[ uri ] ) {
			setNotice( { type: 'error', message: __( 'A storage with this URI already exists.', 'wppack-storage' ) } );
			return;
		}

		const dsn = buildDsn( scheme, newStorage.bucket, {
			accessKey: newStorage.accessKey,
			secretKey: newStorage.secretKey,
			region: newStorage.region,
		} );

		const entry = {
			dsn,
			cdnUrl: '',
			readonly: false,
			uri,
		};

		setStorages( ( prev ) => ( { ...prev, [ uri ]: entry } ) );
		setStorageUris( ( prev ) => [ ...prev, uri ] );

		// Auto-select as primary if it's the first editable storage
		if ( storageUris.length === 0 || ( storageUris.length === 1 && storages[ storageUris[ 0 ] ]?.readonly ) ) {
			setPrimary( uri );
		}

		// Reset form
		setNewStorage( { providerType: '', bucket: '', region: '', accessKey: '', secretKey: '' } );
	};

	const handleDeleteStorage = ( uri ) => {
		setStorages( ( prev ) => {
			const next = { ...prev };
			delete next[ uri ];
			return next;
		} );
		setStorageUris( ( prev ) => prev.filter( ( u ) => u !== uri ) );
		if ( primary === uri ) {
			setPrimary( '' );
		}
	};

	const providerOptions = useMemo( () => [
		{ label: __( '— Select Provider —', 'wppack-storage' ), value: '' },
		...Object.entries( definitions )
			.sort( ( [ , a ], [ , b ] ) => a.label.localeCompare( b.label ) )
			.map( ( [ key, d ] ) => ( { label: d.label, value: key } ) ),
	], [ definitions ] );

	const primaryOptions = useMemo( () => [
		{ label: __( '— Select —', 'wppack-storage' ), value: '' },
		...storageUris.map( ( uri ) => ( { label: uri, value: uri } ) ),
	], [ storageUris ] );

	// ── Add Storage DataForm fields ──

	const addDef = definitions[ newStorage.providerType ] || null;
	const isLocal = addDef?.scheme === 'local';

	const addFields = useMemo( () => {
		const result = [
			{
				id: 'providerType',
				label: __( 'Provider', 'wppack-storage' ),
				type: 'text',
				elements: providerOptions,
				Edit: ( { data, field, onChange: onFieldChange } ) => (
					<SelectControl
						id={ field.id }
						label={ field.label }
						value={ data.providerType }
						options={ providerOptions }
						onChange={ ( val ) => onFieldChange( {
							providerType: val,
							bucket: '',
							region: '',
							accessKey: '',
							secretKey: '',
						} ) }
						__nextHasNoMarginBottom
					/>
				),
			},
		];

		if ( ! newStorage.providerType ) {
			return result;
		}

		const addDefFields = addDef?.fields || [];
		const bucketFieldDef = findField( addDefFields, 'bucket' )
			|| findField( addDefFields, 'container' )
			|| findField( addDefFields, 'rootDir' );
		const idLabel = isLocal
			? __( 'Root Path', 'wppack-storage' )
			: ( bucketFieldDef?.label || 'Bucket' );

		result.push( {
			id: 'bucket',
			label: idLabel,
			type: 'text',
			Edit: ( { data, field, onChange: onFieldChange } ) => (
				<TextControl
					id={ field.id }
					label={ field.label }
					value={ data.bucket }
					placeholder={ isLocal ? '/var/www' : '' }
					onChange={ ( val ) => onFieldChange( {
						bucket: isLocal ? val : val.toLowerCase().replace( /[^a-z0-9._-]+/g, '' ),
					} ) }
					__nextHasNoMarginBottom
				/>
			),
		} );

		const dynamicFields = addDefFields.filter(
			( f ) => ! [ 'bucket', 'container', 'rootDir' ].includes( f.name )
		);

		for ( const fieldDef of dynamicFields ) {
			const fieldKey = fieldDef.name;
			const isSensitive = !! fieldDef.sensitive;

			if ( fieldKey === 'region' ) {
				result.push( {
					id: 'region',
					label: fieldDef.label || 'Region',
					type: 'text',
					elements: AWS_REGIONS.filter( ( r ) => r.value !== '' ),
				} );
				continue;
			}

			result.push( {
				id: fieldKey,
				label: fieldDef.label || fieldKey,
				type: isSensitive ? 'password' : 'text',
			} );
		}

		result.push( {
			id: '_uriPreview',
			label: __( 'URI Preview', 'wppack-storage' ),
			type: 'text',
			Edit: ( { data } ) => {
				const scheme = schemeForProvider( data.providerType );
				const uri = data.bucket ? `${ scheme }://${ data.bucket }` : `${ scheme }://`;
				return (
					<TextControl
						label={ __( 'URI Preview', 'wppack-storage' ) }
						value={ uri }
						readOnly
						__nextHasNoMarginBottom
					/>
				);
			},
		} );

		return result;
	}, [ newStorage.providerType, providerOptions, addDef, isLocal ] );

	const addForm = useMemo( () => ( {
		fields: [ {
			id: 'add-section',
			label: __( 'Add Storage', 'wppack-storage' ),
			children: addFields.map( ( f ) => f.id ),
			layout: { type: 'regular' },
		} ],
	} ), [ addFields ] );

	const handleAddFormChange = ( edits ) => {
		setNewStorage( ( prev ) => ( { ...prev, ...edits } ) );
	};

	// ── Global Settings DataForm fields ──

	const globalFields = useMemo( () => [
		{
			id: 'primary',
			label: __( 'Primary Storage', 'wppack-storage' ),
			type: 'text',
			description: __( 'The storage used for WordPress media uploads.', 'wppack-storage' ),
			Edit: ( { data, field, onChange: onFieldChange } ) => (
				<SelectControl
					id={ field.id }
					label={ field.label }
					help={ __( 'The storage used for WordPress media uploads.', 'wppack-storage' ) }
					value={ data.primary }
					options={ primaryOptions }
					onChange={ ( val ) => onFieldChange( { primary: val } ) }
					__nextHasNoMarginBottom
				/>
			),
		},
		{
			id: 'uploadsPath',
			label: __( 'Uploads Path', 'wppack-storage' ),
			type: 'text',
			description: __( 'Path prefix for uploaded files (e.g. wp-content/uploads).', 'wppack-storage' ),
		},
	], [ primaryOptions ] );

	const globalForm = useMemo( () => ( {
		fields: [ {
			id: 'settings-section',
			label: __( 'Settings', 'wppack-storage' ),
			children: globalFields.map( ( f ) => f.id ),
			layout: { type: 'regular' },
		} ],
	} ), [ globalFields ] );

	const globalData = useMemo( () => ( {
		primary,
		uploadsPath,
	} ), [ primary, uploadsPath ] );

	const handleGlobalChange = ( edits ) => {
		if ( 'primary' in edits ) {
			setPrimary( edits.primary );
		}
		if ( 'uploadsPath' in edits ) {
			setUploadsPath( edits.uploadsPath );
		}
	};

	if ( loading ) {
		return <div className="wpp-storage-loading"><Spinner /></div>;
	}

	return (
		<Page
			title={ __( 'Storage Settings', 'wppack-storage' ) }
			hasPadding
		>
			<div className="wpp-storage-settings">
				{ notice && (
					<Notice status={ notice.type } isDismissible onDismiss={ () => setNotice( null ) }>
						{ notice.message }
					</Notice>
				) }

				{ storageUris.length > 0 && (
					<Panel>
						{ storageUris.map( ( uri ) => {
							const storage = storages[ uri ];
							if ( ! storage ) return null;
							return (
								<StoragePanel
									key={ uri }
									uri={ uri }
									storage={ storage }
									definitions={ definitions }
									onChange={ updateStorage }
									onDelete={ handleDeleteStorage }
									isReadonly={ !! storage.readonly }
								/>
							);
						} ) }
					</Panel>
				) }

				<div className="wpp-storage-add-section wpp-storage-dataform-wrap">
					<DataForm
						data={ newStorage }
						fields={ addFields }
						form={ addForm }
						onChange={ handleAddFormChange }
					/>
					<Button
						variant="secondary"
						onClick={ handleAddStorage }
						disabled={ ! newStorage.providerType || ! newStorage.bucket }
					>
						{ __( 'Add Storage', 'wppack-storage' ) }
					</Button>
				</div>

				{ storageUris.length > 0 && (
					<div className="wpp-storage-global-settings wpp-storage-dataform-wrap">
						<DataForm
							data={ globalData }
							fields={ globalFields }
							form={ globalForm }
							onChange={ handleGlobalChange }
						/>
					</div>
				) }

				<div className="wpp-storage-actions">
					<Button
						variant="primary"
						onClick={ handleSave }
						isBusy={ saving }
						disabled={ saving }
					>
						{ saving ? __( 'Saving…', 'wppack-storage' ) : __( 'Save Settings', 'wppack-storage' ) }
					</Button>
				</div>
			</div>
		</Page>
	);
}
