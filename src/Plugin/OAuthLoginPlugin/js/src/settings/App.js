import { useState, useEffect } from '@wordpress/element';
import {
	Panel,
	PanelBody,
	TextControl,
	TextareaControl,
	ToggleControl,
	SelectControl,
	Button,
	Notice,
	Spinner,
	BaseControl,
	Tooltip,
} from '@wordpress/components';
import { Page } from '@wordpress/admin-ui';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const MASKED = '********';

function SourceBadge( { source } ) {
	if ( ! source || source === 'default' ) {
		return null;
	}
	const labels = {
		constant: __( 'Constant', 'wppack-oauth-login' ),
		option: __( 'Saved', 'wppack-oauth-login' ),
	};
	return (
		<span className={ `wpp-oauth-source-badge wpp-oauth-source-${ source }` }>
			{ labels[ source ] || source }
		</span>
	);
}

function Field( { id, label, field, value, onChange, help, disabled } ) {
	const isReadonly = disabled || field?.readonly;
	return (
		<TextControl
			id={ id }
			label={
				<>
					{ label }
					<SourceBadge source={ field?.source } />
				</>
			}
			value={ value || '' }
			onChange={ onChange }
			disabled={ isReadonly }
			help={ help }
			__nextHasNoMarginBottom
		/>
	);
}

function BoolField( { label, field, value, onChange, help, disabled } ) {
	const isReadonly = disabled || field?.readonly;
	return (
		<ToggleControl
			label={
				<>
					{ label }
					<SourceBadge source={ field?.source } />
				</>
			}
			help={ help }
			checked={ !! value }
			onChange={ onChange }
			disabled={ isReadonly }
			__nextHasNoMarginBottom
		/>
	);
}

function PathField( { id, label, field, value, onChange, prefix, disabled } ) {
	const isReadonly = disabled || field?.readonly;
	return (
		<BaseControl
			id={ id }
			label={
				<>
					{ label }
					<SourceBadge source={ field?.source } />
				</>
			}
		>
			<div className="wpp-oauth-path-field">
				<span className="wpp-oauth-path-prefix">{ prefix }</span>
				<input
					type="text"
					id={ id }
					className="components-text-control__input"
					value={ value || '' }
					onChange={ ( e ) => onChange( e.target.value ) }
					disabled={ isReadonly }
				/>
			</div>
		</BaseControl>
	);
}

function ProviderPanel( { name, provider, onChange, onDelete, onRename, onMoveUp, onMoveDown, isFirst, isLast, isReadonly, icons, allStyles, buttonDisplay, definitions, roleOptions, initialOpen } ) {
	const [ editName, setEditName ] = useState( name );
	const f = provider.fields || {};
	const icon = icons[ f.type ] || icons[ name ] || provider.icon;
	const providerStyles = allStyles[ f.type ] || allStyles[ name ] || {};
	const styleKeys = Object.keys( providerStyles );
	const selectedStyle = f.button_style || styleKeys[ 0 ] || '';
	const currentStyle = providerStyles[ selectedStyle ] || providerStyles[ styleKeys[ 0 ] ] || { bg: '#f0f0f0', text: '#1d2327', border: '#ddd', icon: 'original' };
	const firstStyle = providerStyles[ styleKeys[ 0 ] ] || currentStyle;
	const def = definitions[ f.type ] || {};
	const reqFields = [ ...( def.requiredFields || [] ), ...( def.optionalFields || [] ) ];
	const update = ( key ) => ( val ) => {
		onChange( name, { ...f, [ key ]: val } );
	};

	const titleElement = (
		<span className="wpp-oauth-panel-title">
			<span className="wpp-oauth-move-buttons">
				{ /* eslint-disable-next-line jsx-a11y/click-events-have-key-events */ }
				<span role="button" tabIndex={ 0 } className={ `wpp-oauth-move-btn${ isFirst ? ' is-disabled' : '' }` } onClick={ ( e ) => { e.stopPropagation(); if ( ! isFirst ) onMoveUp( name ); } }>▲</span>
				{ /* eslint-disable-next-line jsx-a11y/click-events-have-key-events */ }
				<span role="button" tabIndex={ 0 } className={ `wpp-oauth-move-btn${ isLast ? ' is-disabled' : '' }` } onClick={ ( e ) => { e.stopPropagation(); if ( ! isLast ) onMoveDown( name ); } }>▼</span>
			</span>
			{ icon && (
				<span
					className="wpp-oauth-panel-icon"
					style={ firstStyle.icon !== 'original' ? { color: firstStyle.icon } : {} }
					dangerouslySetInnerHTML={ { __html: icon } }
				/>
			) }
			{ f.label || name }
		</span>
	);

	return (
		<PanelBody
			title={ titleElement }
			initialOpen={ initialOpen }
			className="wpp-oauth-provider-panel"
		>
			{ isReadonly && (
				<Notice status="info" isDismissible={ false }>
					{ __(
						'This provider is defined via constant and cannot be edited.',
						'wppack-oauth-login'
					) }
				</Notice>
			) }
			<BaseControl
				id={ `${ name }-name` }
				label={ __( 'Name', 'wppack-oauth-login' ) }
				help={ __( 'Provider identifier used in URLs.', 'wppack-oauth-login' ) }
				className="wpp-oauth-narrow"
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
			<div className="wpp-oauth-narrow">
				<Field
					id={ `${ name }-type` }
					label={ __( 'Type', 'wppack-oauth-login' ) }
					value={ f.type || '' }
					disabled={ true }
					onChange={ () => {} }
				/>
			</div>
			{ styleKeys.length > 0 && (
				<SelectControl
					className="wpp-oauth-narrow"
					label={ __( 'ボタンスタイル', 'wppack-oauth-login' ) }
					value={ selectedStyle }
					onChange={ update( 'button_style' ) }
					disabled={ isReadonly }
					options={ styleKeys.map( ( key ) => ( {
						label: providerStyles[ key ].label,
						value: key,
					} ) ) }
					__nextHasNoMarginBottom
				/>
			) }
			<BaseControl label={ __( 'ログインボタンプレビュー', 'wppack-oauth-login' ) }>
				<div className="wpp-oauth-button-preview">
					{ buttonDisplay === 'icon-only' ? (
						<Tooltip text={ sprintf( __( 'Login with %s', 'wppack-oauth-login' ), f.label || name ) }>
							<a
								className="wpp-oauth-login-button is-icon-only"
								style={ {
									background: currentStyle.bg,
									color: currentStyle.text,
									border: `1px solid ${ currentStyle.border }`,
								} }
							>
								{ icon && (
									<span
										className="wpp-oauth-login-icon"
										style={ currentStyle.icon !== 'original' ? { color: currentStyle.icon } : {} }
										dangerouslySetInnerHTML={ { __html: icon } }
									/>
								) }
							</a>
						</Tooltip>
					) : (
						<a
							className={ `wpp-oauth-login-button${ buttonDisplay === 'icon-left' ? ' is-icon-left' : '' }` }
							style={ {
								background: currentStyle.bg,
								color: currentStyle.text,
								border: `1px solid ${ currentStyle.border }`,
							} }
						>
							{ buttonDisplay !== 'text-only' && icon && (
								<span
									className="wpp-oauth-login-icon"
									style={ currentStyle.icon !== 'original' ? { color: currentStyle.icon } : {} }
									dangerouslySetInnerHTML={ { __html: icon } }
								/>
							) }
							<span className="wpp-oauth-login-text">{ sprintf( __( 'Login with %s', 'wppack-oauth-login' ), f.label || name ) }</span>
						</a>
					) }
				</div>
			</BaseControl>
			<Field
				id={ `${ name }-client-id` }
				label={ __( 'Client ID', 'wppack-oauth-login' ) }
				value={ f.client_id }
				onChange={ update( 'client_id' ) }
				disabled={ isReadonly }
			/>
			<Field
				id={ `${ name }-client-secret` }
				label={ __( 'Client Secret', 'wppack-oauth-login' ) }
				value={ f.client_secret }
				onChange={ update( 'client_secret' ) }
				disabled={ isReadonly }
				help={
					f.client_secret === MASKED
						? __(
								'Leave as masked to keep current value.',
								'wppack-oauth-login'
						  )
						: undefined
				}
			/>
			<div className="wpp-oauth-narrow">
				<Field
					id={ `${ name }-label` }
					label={ __( 'Label', 'wppack-oauth-login' ) }
					value={ f.label }
					onChange={ update( 'label' ) }
					disabled={ isReadonly }
				/>
			</div>
			{ reqFields.includes( 'tenant_id' ) && (
				<Field
					id={ `${ name }-tenant-id` }
					label={ __( 'Tenant ID', 'wppack-oauth-login' ) }
					value={ f.tenant_id }
					onChange={ update( 'tenant_id' ) }
					disabled={ isReadonly }
				/>
			) }
			{ reqFields.includes( 'domain' ) && (
				<Field
					id={ `${ name }-domain` }
					label={ __( 'Domain', 'wppack-oauth-login' ) }
					value={ f.domain }
					onChange={ update( 'domain' ) }
					disabled={ isReadonly }
				/>
			) }
			{ reqFields.includes( 'discovery_url' ) && (
				<Field
					id={ `${ name }-discovery-url` }
					label={ __( 'Discovery URL', 'wppack-oauth-login' ) }
					value={ f.discovery_url }
					onChange={ update( 'discovery_url' ) }
					disabled={ isReadonly }
				/>
			) }
			{ reqFields.includes( 'hosted_domain' ) && (
				<Field
					id={ `${ name }-hosted-domain` }
					label={ __( 'Hosted Domain', 'wppack-oauth-login' ) }
					value={ f.hosted_domain }
					onChange={ update( 'hosted_domain' ) }
					disabled={ isReadonly }
					help={ __(
						'Restrict to Google Workspace domain.',
						'wppack-oauth-login'
					) }
				/>
			) }
			<Field
				id={ `${ name }-scopes` }
				label={ __( 'Scopes', 'wppack-oauth-login' ) }
				value={ f.scopes }
				onChange={ update( 'scopes' ) }
				disabled={ isReadonly }
				help={ __(
					'Space-separated. Leave empty for defaults.',
					'wppack-oauth-login'
				) }
			/>
			<BoolField
				label={ __( 'Auto-create Users', 'wppack-oauth-login' ) }
				value={ f.auto_provision }
				onChange={ update( 'auto_provision' ) }
				disabled={ isReadonly }
			/>
			<SelectControl
				className="wpp-oauth-narrow"
				label={ __( 'Default Role', 'wppack-oauth-login' ) }
				value={ f.default_role || 'subscriber' }
				onChange={ update( 'default_role' ) }
				disabled={ isReadonly }
				options={ roleOptions }
				__nextHasNoMarginBottom
			/>
			<Field
				id={ `${ name }-role-claim` }
				label={ __( 'Role Claim', 'wppack-oauth-login' ) }
				value={ f.role_claim }
				onChange={ update( 'role_claim' ) }
				disabled={ isReadonly }
				help={ __(
					'JWT claim name containing role.',
					'wppack-oauth-login'
				) }
			/>
			<TextareaControl
				id={ `${ name }-role-mapping` }
				label={ __( 'Role Mapping (JSON)', 'wppack-oauth-login' ) }
				help={ __(
					'e.g. {"Admin":"administrator","Member":"subscriber"}',
					'wppack-oauth-login'
				) }
				value={ f.role_mapping || '' }
				onChange={ update( 'role_mapping' ) }
				disabled={ isReadonly }
				rows={ 3 }
				__nextHasNoMarginBottom
			/>
			{ ! isReadonly && (
				<div className="wpp-oauth-delete-provider">
					<Button
						variant="secondary"
						isDestructive
						onClick={ () => onDelete( name ) }
					>
						{ __( 'Delete Provider', 'wppack-oauth-login' ) }
					</Button>
				</div>
			) }
		</PanelBody>
	);
}

export default function App() {
	const [ globalSettings, setGlobalSettings ] = useState( null );
	const [ providers, setProviders ] = useState( {} );
	const [ icons, setIcons ] = useState( {} );
	const [ styles, setStyles ] = useState( {} );
	const [ definitions, setDefinitions ] = useState( {} );
	const [ roles, setRoles ] = useState( {} );
	const [ siteUrl, setSiteUrl ] = useState( '' );
	const [ globalForm, setGlobalForm ] = useState( {} );
	const [ providerForm, setProviderForm ] = useState( {} );
	const [ deletedProviders, setDeletedProviders ] = useState( [] );
	const [ providerOrder, setProviderOrder ] = useState( [] );
	const [ newProviderType, setNewProviderType ] = useState( '' );
	const [ lastAdded, setLastAdded ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	const applyResponse = ( data ) => {
		setSiteUrl( data.siteUrl || '' );
		setIcons( data.icons || {} );
		setStyles( data.styles || {} );
		setDefinitions( data.definitions || {} );
		setRoles( data.roles || {} );
		setGlobalSettings( data.global );
		setProviders( data.providers || {} );
		setProviderOrder( Object.keys( data.providers || {} ) );

		const gf = {};
		Object.entries( data.global || {} ).forEach( ( [ k, m ] ) => {
			gf[ k ] = m.value;
		} );
		setGlobalForm( gf );

		const pf = {};
		Object.entries( data.providers || {} ).forEach(
			( [ name, provider ] ) => {
				pf[ name ] = provider.fields || {};
			}
		);
		setProviderForm( pf );
	};

	useEffect( () => {
		apiFetch( { path: '/wppack/v1/oauth-login/settings' } )
			.then( ( data ) => {
				applyResponse( data );
				setLoading( false );
			} )
			.catch( () => {
				setNotice( {
					type: 'error',
					message: __(
						'Failed to load settings.',
						'wppack-oauth-login'
					),
				} );
				setLoading( false );
			} );
	}, [] );

	const handleSave = () => {
		setSaving( true );
		setNotice( null );

		apiFetch( {
			path: '/wppack/v1/oauth-login/settings',
			method: 'POST',
			data: {
				global: globalForm,
				providers: providerForm,
				providerOrder,
				deletedProviders,
			},
		} )
			.then( ( data ) => {
				applyResponse( data );
				setDeletedProviders( [] );
				setNotice( {
					type: 'success',
					message: __(
						'Settings saved.',
						'wppack-oauth-login'
					),
				} );
			} )
			.catch( () => {
				setNotice( {
					type: 'error',
					message: __(
						'Failed to save settings.',
						'wppack-oauth-login'
					),
				} );
			} )
			.finally( () => setSaving( false ) );
	};

	const updateGlobal = ( key ) => ( value ) => {
		setGlobalForm( ( prev ) => ( { ...prev, [ key ]: value } ) );
	};

	const updateProvider = ( name, fields ) => {
		setProviderForm( ( prev ) => ( { ...prev, [ name ]: fields } ) );
	};

	const handleAddProvider = () => {
		const type = newProviderType;
		if ( ! type ) {
			return;
		}

		// Use custom name or generate from type
		const baseName = type;
		let name = baseName;
		let index = 1;
		while ( providerForm[ name ] || providers[ name ] ) {
			name = `${ baseName }-${ index }`;
			index++;
		}

		const def = definitions[ type ];

		const defaultFields = {
			type,
			client_id: '',
			client_secret: '',
			label: def?.label || name,
			auto_provision: false,
			default_role: 'subscriber',
		};

		setProviders( ( prev ) => ( {
			...prev,
			[ name ]: { source: 'option', readonly: false, fields: defaultFields },
		} ) );
		setProviderForm( ( prev ) => ( {
			...prev,
			[ name ]: defaultFields,
		} ) );
		setProviderOrder( ( prev ) => [ ...prev, name ] );
		setLastAdded( name );
		setNewProviderType( '' );
		setNewProviderName( '' );
	};

	const handleDeleteProvider = ( name ) => {
		setDeletedProviders( ( prev ) => [ ...prev, name ] );
		setProviders( ( prev ) => {
			const next = { ...prev };
			delete next[ name ];
			return next;
		} );
		setProviderForm( ( prev ) => {
			const next = { ...prev };
			delete next[ name ];
			return next;
		} );
		setProviderOrder( ( prev ) => prev.filter( ( n ) => n !== name ) );
	};

	const handleRenameProvider = ( oldName, newName ) => {
		if ( ! newName || newName === oldName || providerForm[ newName ] || providers[ newName ] ) {
			return;
		}
		setProviders( ( prev ) => {
			const next = {};
			Object.entries( prev ).forEach( ( [ k, v ] ) => {
				next[ k === oldName ? newName : k ] = v;
			} );
			return next;
		} );
		setProviderForm( ( prev ) => {
			const next = {};
			Object.entries( prev ).forEach( ( [ k, v ] ) => {
				next[ k === oldName ? newName : k ] = v;
			} );
			return next;
		} );
		setProviderOrder( ( prev ) => prev.map( ( n ) => n === oldName ? newName : n ) );
		setDeletedProviders( ( prev ) => [ ...prev, oldName ] );
	};

	const handleMoveProvider = ( name, direction ) => {
		setProviderOrder( ( prev ) => {
			const idx = prev.indexOf( name );
			if ( idx < 0 ) return prev;
			const target = idx + direction;
			if ( target < 0 || target >= prev.length ) return prev;
			const next = [ ...prev ];
			[ next[ idx ], next[ target ] ] = [ next[ target ], next[ idx ] ];
			return next;
		} );
	};

	if ( loading ) {
		return (
			<div className="wpp-oauth-loading">
				<Spinner />
			</div>
		);
	}

	const g = ( key ) => globalSettings?.[ key ] || {};
	const roleOptions = Object.entries( roles ).map( ( [ value, label ] ) => ( {
		label,
		value,
	} ) );

	return (
		<Page title={ __( 'OAuth Login Settings', 'wppack-oauth-login' ) } hasPadding>
			<div className="wpp-oauth-settings">
				{ notice && (
				<Notice
					status={ notice.type }
					isDismissible
					onDismiss={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			<Panel>
				<PanelBody
					title={ __( 'Global Settings', 'wppack-oauth-login' ) }
					initialOpen={ true }
				>
					<BoolField
						label={ __( 'SSO Only Mode', 'wppack-oauth-login' ) }
						field={ g( 'ssoOnly' ) }
						value={ globalForm.ssoOnly }
						onChange={ updateGlobal( 'ssoOnly' ) }
						help={ __(
							'Disable WordPress login form.',
							'wppack-oauth-login'
						) }
					/>
					<BoolField
						label={ __(
							'Auto-create Users',
							'wppack-oauth-login'
						) }
						field={ g( 'autoProvision' ) }
						value={ globalForm.autoProvision }
						onChange={ updateGlobal( 'autoProvision' ) }
						help={ __(
							'Global default. Can be overridden per provider.',
							'wppack-oauth-login'
						) }
					/>
					<SelectControl
						className="wpp-oauth-narrow"
						label={
							<>
								{ __(
									'Default Role',
									'wppack-oauth-login'
								) }
								<SourceBadge
									source={ g( 'defaultRole' ).source }
								/>
							</>
						}
						value={ globalForm.defaultRole || 'subscriber' }
						onChange={ updateGlobal( 'defaultRole' ) }
						disabled={ g( 'defaultRole' ).readonly }
						options={ roleOptions }
						__nextHasNoMarginBottom
					/>
					<PathField
						id="authorizePath"
						label={ __(
							'Authorize Path',
							'wppack-oauth-login'
						) }
						field={ g( 'authorizePath' ) }
						value={ globalForm.authorizePath }
						onChange={ updateGlobal( 'authorizePath' ) }
						prefix={ siteUrl }
					/>
					<PathField
						id="callbackPath"
						label={ __(
							'Callback Path',
							'wppack-oauth-login'
						) }
						field={ g( 'callbackPath' ) }
						value={ globalForm.callbackPath }
						onChange={ updateGlobal( 'callbackPath' ) }
						prefix={ siteUrl }
					/>
					<PathField
						id="verifyPath"
						label={ __(
							'Verify Path',
							'wppack-oauth-login'
						) }
						field={ g( 'verifyPath' ) }
						value={ globalForm.verifyPath }
						onChange={ updateGlobal( 'verifyPath' ) }
						prefix={ siteUrl }
					/>
					<SelectControl
						className="wpp-oauth-narrow"
						label={
							<>
								{ __( 'ボタン表示', 'wppack-oauth-login' ) }
								<SourceBadge source={ g( 'buttonDisplay' ).source } />
							</>
						}
						value={ globalForm.buttonDisplay || 'icon-text' }
						onChange={ updateGlobal( 'buttonDisplay' ) }
						disabled={ g( 'buttonDisplay' ).readonly }
						options={ [
							{ label: 'アイコン + テキスト', value: 'icon-text' },
							{ label: 'アイコン左寄せ + テキスト', value: 'icon-left' },
							{ label: 'アイコンのみ', value: 'icon-only' },
							{ label: 'テキストのみ', value: 'text-only' },
						] }
						__nextHasNoMarginBottom
					/>
				</PanelBody>

				{ providerOrder.map(
					( name, idx ) => {
						const provider = providers[ name ];
						if ( ! provider ) return null;
						return (
							<ProviderPanel
								key={ name }
								name={ name }
								provider={ {
									...provider,
									fields:
										providerForm[ name ] || provider.fields,
								} }
								onChange={ updateProvider }
								onDelete={ handleDeleteProvider }
								onRename={ handleRenameProvider }
								onMoveUp={ ( n ) => handleMoveProvider( n, -1 ) }
								onMoveDown={ ( n ) => handleMoveProvider( n, 1 ) }
								isFirst={ idx === 0 }
								isLast={ idx === providerOrder.length - 1 }
								isReadonly={ provider.readonly }
								icons={ icons }
								allStyles={ styles }
								buttonDisplay={ globalForm.buttonDisplay || 'icon-text' }
								definitions={ definitions }
								roleOptions={ roleOptions }
								initialOpen={ name === lastAdded }
							/>
						);
					}
				) }
			</Panel>

			<div className="wpp-oauth-add-provider">
				<SelectControl
					value={ newProviderType }
					onChange={ setNewProviderType }
					options={ [
						{ label: __( '— Select Provider —', 'wppack-oauth-login' ), value: '' },
						...Object.values( definitions )
							.filter( ( def ) => def.type !== 'oidc' )
							.sort( ( a, b ) => a.dropdownLabel.localeCompare( b.dropdownLabel ) )
							.map( ( def ) => ( { label: def.dropdownLabel, value: def.type } ) ),
						...( definitions.oidc ? [ { label: definitions.oidc.dropdownLabel, value: 'oidc' } ] : [] ),
					] }
					__nextHasNoMarginBottom
				/>
				<Button
					variant="secondary"
					onClick={ handleAddProvider }
					disabled={ ! newProviderType }
				>
					{ __( 'Add Provider', 'wppack-oauth-login' ) }
				</Button>
			</div>

			<div className="wpp-oauth-actions">
				<Button
					variant="primary"
					onClick={ handleSave }
					isBusy={ saving }
					disabled={ saving }
				>
					{ saving
						? __( 'Saving…', 'wppack-oauth-login' )
						: __( 'Save Settings', 'wppack-oauth-login' ) }
				</Button>
			</div>
			</div>
		</Page>
	);
}
