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

function StoragePanel( { name, storage, definitions, awsRegion, onChange, onDelete, onRename, isReadonly } ) {
	const [ editName, setEditName ] = useState( name );
	const provider = storage.provider || '';
	const fields = storage.fields || {};
	const def = definitions[ provider ] || {};
	const defFields = def.fields || [];

	const updateField = ( key ) => ( val ) => {
		onChange( name, {
			...storage,
			fields: { ...fields, [ key ]: val },
		} );
	};

	const updateMeta = ( key ) => ( val ) => {
		onChange( name, { ...storage, [ key ]: val } );
	};

	const titleElement = (
		<span className="wpp-storage-panel-title">
			{ storage.fields?.bucket || storage.fields?.account || storage.fields?.rootDir || name }
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
			<BaseControl
				id={ `${ name }-name` }
				label={ __( 'Name', 'wppack-storage' ) }
				help={ __( 'Storage identifier (e.g. media, backup).', 'wppack-storage' ) }
				className="wpp-storage-narrow"
			>
				<TextControl
					id={ `${ name }-name` }
					value={ editName }
					onChange={ ( val ) => setEditName( val.toLowerCase().replace( /[^a-z0-9-]+/g, '-' ).replace( /^-|-$/g, '' ) ) }
					onBlur={ () => { if ( editName && editName !== name ) onRename( name, editName ); } }
					disabled={ isReadonly }
					__nextHasNoMarginBottom
				/>
			</BaseControl>
			<div className="wpp-storage-narrow">
				<TextControl
					id={ `${ name }-provider` }
					label={ __( 'Provider', 'wppack-storage' ) }
					value={ def.label || provider }
					disabled={ true }
					onChange={ () => {} }
					__nextHasNoMarginBottom
				/>
			</div>
			{ defFields.map( ( f ) => {
				const effectiveDefault = ( f.name === 'region' && awsRegion ) ? awsRegion : ( f.default || '' );
				return (
					<TextControl
						key={ f.name }
						label={ f.label + ( f.required ? ' *' : '' ) }
						help={ f.help || undefined }
						type={ f.type === 'password' ? 'password' : 'text' }
						value={ fields[ f.name ] || effectiveDefault }
						onChange={ updateField( f.name ) }
						disabled={ isReadonly }
						placeholder={ f.default || '' }
						__nextHasNoMarginBottom
					/>
				);
			} ) }
			<TextControl
				label={ __( 'Upload Prefix', 'wppack-storage' ) }
				help={ __( 'Path prefix for uploaded files (e.g. uploads).', 'wppack-storage' ) }
				value={ storage.prefix || '' }
				onChange={ updateMeta( 'prefix' ) }
				disabled={ isReadonly }
				__nextHasNoMarginBottom
			/>
			<TextControl
				label={ __( 'CDN URL', 'wppack-storage' ) }
				help={ __( 'CDN base URL for public file access.', 'wppack-storage' ) }
				value={ storage.cdnUrl || '' }
				onChange={ updateMeta( 'cdnUrl' ) }
				disabled={ isReadonly }
				__nextHasNoMarginBottom
			/>
			{ ! isReadonly && (
				<div className="wpp-storage-delete-storage">
					<Button
						variant="secondary"
						isDestructive
						onClick={ () => onDelete( name ) }
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
	const [ storageOrder, setStorageOrder ] = useState( [] );
	const [ primary, setPrimary ] = useState( '' );
	const [ source, setSource ] = useState( 'default' );
	const [ awsRegion, setAwsRegion ] = useState( '' );
	const [ newProviderType, setNewProviderType ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	const applyResponse = ( data ) => {
		setDefinitions( data.definitions || {} );
		setStorages( data.storages || {} );
		setStorageOrder( Object.keys( data.storages || {} ) );
		setPrimary( data.primary || '' );
		setSource( data.source || 'default' );
		if ( data.awsRegion ) {
			setAwsRegion( data.awsRegion );
		}
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

		// Build storages payload in order
		const orderedStorages = {};
		storageOrder.forEach( ( name ) => {
			if ( storages[ name ] ) {
				orderedStorages[ name ] = storages[ name ];
			}
		} );

		apiFetch( {
			path: '/wppack/v1/storage/settings',
			method: 'POST',
			data: {
				storages: orderedStorages,
				primary,
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

	const updateStorage = ( name, storage ) => {
		setStorages( ( prev ) => ( { ...prev, [ name ]: storage } ) );
	};

	const handleAddStorage = () => {
		const type = newProviderType;
		if ( ! type ) {
			return;
		}

		const baseName = type === 's3' ? 'media' : type;
		let name = baseName;
		let index = 1;
		while ( storages[ name ] ) {
			name = `${ baseName }-${ index }`;
			index++;
		}

		const newStorage = {
			provider: type,
			fields: {},
			prefix: 'uploads',
			cdnUrl: '',
			readonly: false,
		};

		setStorages( ( prev ) => ( { ...prev, [ name ]: newStorage } ) );
		setStorageOrder( ( prev ) => [ ...prev, name ] );
		setNewProviderType( '' );

		// Auto-select as primary if it's the first storage
		if ( storageOrder.length === 0 || ( storageOrder.length === 1 && storages[ storageOrder[ 0 ] ]?.readonly ) ) {
			setPrimary( name );
		}
	};

	const handleDeleteStorage = ( name ) => {
		setStorages( ( prev ) => {
			const next = { ...prev };
			delete next[ name ];
			return next;
		} );
		setStorageOrder( ( prev ) => prev.filter( ( n ) => n !== name ) );
		if ( primary === name ) {
			setPrimary( '' );
		}
	};

	const handleRenameStorage = ( oldName, newName ) => {
		if ( ! newName || newName === oldName || storages[ newName ] ) {
			return;
		}
		setStorages( ( prev ) => {
			const next = {};
			Object.entries( prev ).forEach( ( [ k, v ] ) => {
				next[ k === oldName ? newName : k ] = v;
			} );
			return next;
		} );
		setStorageOrder( ( prev ) => prev.map( ( n ) => n === oldName ? newName : n ) );
		if ( primary === oldName ) {
			setPrimary( newName );
		}
	};

	if ( loading ) {
		return <div className="wpp-storage-loading"><Spinner /></div>;
	}

	const providerOptions = [
		{ label: __( '— Select Provider —', 'wppack-storage' ), value: '' },
		...Object.values( definitions )
			.sort( ( a, b ) => a.label.localeCompare( b.label ) )
			.map( ( d ) => ( { label: d.label, value: d.provider } ) ),
	];

	const primaryOptions = [
		{ label: __( '— Select —', 'wppack-storage' ), value: '' },
		...storageOrder.map( ( name ) => ( { label: name, value: name } ) ),
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

				{ storageOrder.length > 0 && (
					<Panel>
						{ storageOrder.map( ( name ) => {
							const storage = storages[ name ];
							if ( ! storage ) return null;
							return (
								<StoragePanel
									key={ name }
									name={ name }
									storage={ storage }
									definitions={ definitions }
									awsRegion={ awsRegion }
									onChange={ updateStorage }
									onDelete={ handleDeleteStorage }
									onRename={ handleRenameStorage }
									isReadonly={ !! storage.readonly }
								/>
							);
						} ) }
					</Panel>
				) }

				<div className="wpp-storage-add-storage">
					<SelectControl
						value={ newProviderType }
						onChange={ setNewProviderType }
						options={ providerOptions }
						__nextHasNoMarginBottom
					/>
					<Button
						variant="secondary"
						onClick={ handleAddStorage }
						disabled={ ! newProviderType }
					>
						{ __( 'Add Storage', 'wppack-storage' ) }
					</Button>
				</div>

				{ storageOrder.length > 0 && (
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
