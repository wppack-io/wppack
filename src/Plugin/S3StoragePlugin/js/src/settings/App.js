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

const FIELD_LABELS = {
	bucket: __( 'Bucket', 'wppack-storage' ),
	region: __( 'Region', 'wppack-storage' ),
	accessKey: __( 'Access Key', 'wppack-storage' ),
	secretKey: __( 'Secret Key', 'wppack-storage' ),
	account: __( 'Account', 'wppack-storage' ),
	container: __( 'Container', 'wppack-storage' ),
	accountKey: __( 'Account Key', 'wppack-storage' ),
	connectionString: __( 'Connection String', 'wppack-storage' ),
	project: __( 'Project', 'wppack-storage' ),
	keyFile: __( 'Key File', 'wppack-storage' ),
	rootDir: __( 'Root Directory', 'wppack-storage' ),
	publicUrl: __( 'Public URL', 'wppack-storage' ),
};

function StoragePanel( { uri, storage, definitions, onChange, onDelete, isReadonly } ) {
	const scheme = schemeFromUri( uri );
	const def = Object.values( definitions ).find( ( d ) => d.scheme === scheme ) || {};
	const dsnFields = parseDsn( storage.dsn );

	const sensitiveFields = [ 'secretKey', 'accountKey', 'keyFile', 'connectionString', 'accessKey' ];

	const updateField = ( key ) => ( val ) => {
		const bucket = bucketFromUri( uri );
		const newFields = { ...dsnFields, [ key ]: val };
		// Preserve masked values — don't overwrite server-side secrets
		sensitiveFields.forEach( ( sf ) => {
			if ( sf !== key && dsnFields[ sf ] === MASKED ) {
				newFields[ sf ] = MASKED;
			}
		} );
		onChange( uri, {
			...storage,
			dsn: buildDsn( scheme, bucket, newFields ),
		} );
	};

	const updateCdnUrl = ( val ) => {
		onChange( uri, { ...storage, cdnUrl: val } );
	};

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
			<div className="wpp-storage-narrow">
				<TextControl
					label={ __( 'Provider', 'wppack-storage' ) }
					value={ def.label || scheme }
					disabled={ true }
					onChange={ () => {} }
					__nextHasNoMarginBottom
				/>
			</div>
			{ ( def.fields || [] ).map( ( fieldKey ) => {
				const isBucket = [ 'bucket', 'container' ].includes( fieldKey );
				if ( isBucket ) {
					return (
						<TextControl
							key={ fieldKey }
							label={ FIELD_LABELS[ fieldKey ] || fieldKey }
							value={ bucketFromUri( uri ) }
							disabled={ true }
							onChange={ () => {} }
							__nextHasNoMarginBottom
						/>
					);
				}
				const isSensitive = [ 'secretKey', 'accountKey', 'keyFile', 'connectionString' ].includes( fieldKey );
				return (
					<TextControl
						key={ fieldKey }
						label={ FIELD_LABELS[ fieldKey ] || fieldKey }
						type={ isSensitive ? 'password' : 'text' }
						value={ dsnFields[ fieldKey ] || '' }
						onChange={ updateField( fieldKey ) }
						disabled={ isReadonly }
						__nextHasNoMarginBottom
					/>
				);
			} ) }
			<TextControl
				label={ __( 'CDN URL', 'wppack-storage' ) }
				help={ __( 'CDN base URL for public file access.', 'wppack-storage' ) }
				value={ storage.cdnUrl || '' }
				onChange={ updateCdnUrl }
				disabled={ isReadonly }
				__nextHasNoMarginBottom
			/>
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
	const [ newProviderType, setNewProviderType ] = useState( '' );
	const [ newBucket, setNewBucket ] = useState( '' );
	const [ newRegion, setNewRegion ] = useState( '' );
	const [ newAccessKey, setNewAccessKey ] = useState( '' );
	const [ newSecretKey, setNewSecretKey ] = useState( '' );
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

	const newUri = newProviderType && newBucket
		? `${ schemeForProvider( newProviderType ) }://${ newBucket }`
		: '';

	const handleAddStorage = () => {
		if ( ! newProviderType || ! newBucket ) {
			return;
		}

		const scheme = schemeForProvider( newProviderType );
		const uri = `${ scheme }://${ newBucket }`;

		if ( storages[ uri ] ) {
			setNotice( { type: 'error', message: __( 'A storage with this URI already exists.', 'wppack-storage' ) } );
			return;
		}

		const dsn = buildDsn( scheme, newBucket, {
			accessKey: newAccessKey,
			secretKey: newSecretKey,
			region: newRegion,
		} );

		const newStorage = {
			dsn,
			cdnUrl: '',
			readonly: false,
			uri,
		};

		setStorages( ( prev ) => ( { ...prev, [ uri ]: newStorage } ) );
		setStorageUris( ( prev ) => [ ...prev, uri ] );

		// Auto-select as primary if it's the first editable storage
		if ( storageUris.length === 0 || ( storageUris.length === 1 && storages[ storageUris[ 0 ] ]?.readonly ) ) {
			setPrimary( uri );
		}

		// Reset form
		setNewProviderType( '' );
		setNewBucket( '' );
		setNewRegion( '' );
		setNewAccessKey( '' );
		setNewSecretKey( '' );
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

	if ( loading ) {
		return <div className="wpp-storage-loading"><Spinner /></div>;
	}

	const providerOptions = [
		{ label: __( '— Select Provider —', 'wppack-storage' ), value: '' },
		...Object.entries( definitions )
			.sort( ( [ , a ], [ , b ] ) => a.label.localeCompare( b.label ) )
			.map( ( [ key, d ] ) => ( { label: d.label, value: key } ) ),
	];

	const primaryOptions = [
		{ label: __( '— Select —', 'wppack-storage' ), value: '' },
		...storageUris.map( ( uri ) => ( { label: uri, value: uri } ) ),
	];

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

				<div className="wpp-storage-add-section">
					<div className="wpp-storage-add-row">
						<SelectControl
							label={ __( 'Provider', 'wppack-storage' ) }
							value={ newProviderType }
							onChange={ setNewProviderType }
							options={ providerOptions }
							__nextHasNoMarginBottom
						/>
						<TextControl
							label={ __( 'Bucket', 'wppack-storage' ) }
							value={ newBucket }
							onChange={ ( val ) => setNewBucket( val.toLowerCase().replace( /[^a-z0-9._-]+/g, '' ) ) }
							__nextHasNoMarginBottom
						/>
					</div>
					<div className="wpp-storage-add-row">
						<TextControl
							label={ __( 'Region', 'wppack-storage' ) }
							value={ newRegion }
							onChange={ setNewRegion }
							placeholder="ap-northeast-1"
							__nextHasNoMarginBottom
						/>
						<TextControl
							label={ __( 'Access Key', 'wppack-storage' ) }
							type="password"
							value={ newAccessKey }
							onChange={ setNewAccessKey }
							__nextHasNoMarginBottom
						/>
						<TextControl
							label={ __( 'Secret Key', 'wppack-storage' ) }
							type="password"
							value={ newSecretKey }
							onChange={ setNewSecretKey }
							__nextHasNoMarginBottom
						/>
					</div>
					{ newUri && (
						<div className="wpp-storage-uri-preview">
							{ __( 'URI Preview:', 'wppack-storage' ) }
							{ ' ' }
							<code>{ newUri }</code>
						</div>
					) }
					<Button
						variant="secondary"
						onClick={ handleAddStorage }
						disabled={ ! newProviderType || ! newBucket }
					>
						{ __( 'Add Storage', 'wppack-storage' ) }
					</Button>
				</div>

				{ storageUris.length > 0 && (
					<div className="wpp-storage-global-settings">
						<div className="wpp-storage-primary-select">
							<SelectControl
								label={ __( 'Primary Storage', 'wppack-storage' ) }
								help={ __( 'The storage used for WordPress media uploads.', 'wppack-storage' ) }
								value={ primary }
								onChange={ setPrimary }
								options={ primaryOptions }
								__nextHasNoMarginBottom
							/>
						</div>
						<TextControl
							label={ __( 'Uploads Path', 'wppack-storage' ) }
							help={ __( 'Path prefix for uploaded files (e.g. wp-content/uploads).', 'wppack-storage' ) }
							value={ uploadsPath }
							onChange={ setUploadsPath }
							__nextHasNoMarginBottom
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
