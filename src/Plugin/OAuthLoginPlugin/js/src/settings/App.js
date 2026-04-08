import { DataForm } from '@wordpress/dataviews/wp';
import { useState, useEffect, useMemo } from '@wordpress/element';
import {
	Panel,
	PanelBody,
	TextControl,
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

function badgeLabel( label, source ) {
	if ( ! source || source === 'default' ) {
		return label;
	}
	const labels = {
		constant: __( 'Constant', 'wppack-oauth-login' ),
		option: __( 'Saved', 'wppack-oauth-login' ),
	};
	return (
		<>
			<span>{ label }</span>
			<span className={ `wpp-oauth-source-badge wpp-oauth-source-${ source }` }>
				{ labels[ source ] || source }
			</span>
		</>
	);
}

function PathField( { id, label, field, value, onChange, prefix, disabled } ) {
	const isReadonly = disabled || field?.readonly;
	return (
		<BaseControl
			id={ id }
			label={
				badgeLabel( label, field?.source )
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

function ProviderPanel( { name, provider, providerFields, onChange, onDelete, onRename, onMoveUp, onMoveDown, isFirst, isLast, isReadonly, icons, allStyles, buttonDisplay, definitions, initialOpen } ) {
	const [ editName, setEditName ] = useState( name );
	const f = providerFields || {};
	const icon = icons[ f.type ] || icons[ name ] || provider.icon;
	const providerStyles = allStyles[ f.type ] || allStyles[ name ] || {};
	const styleKeys = Object.keys( providerStyles );
	const selectedStyle = f.button_style || styleKeys[ 0 ] || '';
	const currentStyle = providerStyles[ selectedStyle ] || providerStyles[ styleKeys[ 0 ] ] || { bg: '#f0f0f0', text: '#1d2327', border: '#ddd', icon: 'original' };
	const firstStyle = providerStyles[ styleKeys[ 0 ] ] || currentStyle;
	const def = definitions[ f.type ] || {};
	const reqFields = [ ...( def.requiredFields || [] ), ...( def.optionalFields || [] ) ];

	const panelData = useMemo( () => ( {
		type: f.type || '',
		client_id: f.client_id || '',
		client_secret: f.client_secret || '',
		label: f.label || '',
		scopes: f.scopes || '',
		tenant_id: f.tenant_id || '',
		domain: f.domain || '',
		discovery_url: f.discovery_url || '',
		hosted_domain: f.hosted_domain || '',
		auto_provision: !! f.auto_provision,
		button_style: selectedStyle,
	} ), [ f, selectedStyle ] );

	const handlePanelChange = ( edits ) => {
		const newFields = { ...f };
		for ( const [ key, value ] of Object.entries( edits ) ) {
			if ( key !== 'type' ) {
				newFields[ key ] = value;
			}
		}
		onChange( name, newFields );
	};

	const panelFormFields = useMemo( () => {
		const result = [
			{
				id: 'type',
				label: __( 'Type', 'wppack-oauth-login' ),
				type: 'text',
				Edit: ( { data, field } ) => (
					<TextControl
						id={ field.id }
						label={ field.label }
						value={ data.type }
						disabled
						onChange={ () => {} }
						__nextHasNoMarginBottom
					/>
				),
			},
			{
				id: 'client_id',
				label: badgeLabel( __( 'Client ID', 'wppack-oauth-login' ), isReadonly ? 'constant' : undefined ),
				type: 'text',
				Edit: isReadonly
					? ( { data, field } ) => (
						<TextControl
							id={ field.id }
							label={ field.label }
							value={ data.client_id }
							disabled
							onChange={ () => {} }
							__nextHasNoMarginBottom
						/>
					)
					: undefined,
			},
			{
				id: 'client_secret',
				label: badgeLabel( __( 'Client Secret', 'wppack-oauth-login' ), isReadonly ? 'constant' : undefined ),
				type: 'text',
				Edit: ( { data, field, onChange: onFieldChange } ) => (
					<TextControl
						id={ field.id }
						label={ field.label }
						value={ data.client_secret }
						onChange={ ( val ) => onFieldChange( { client_secret: val } ) }
						disabled={ isReadonly }
						help={
							data.client_secret === MASKED
								? __( 'Leave as masked to keep current value.', 'wppack-oauth-login' )
								: undefined
						}
						__nextHasNoMarginBottom
					/>
				),
			},
			{
				id: 'scopes',
				label: badgeLabel( __( 'Scopes', 'wppack-oauth-login' ), isReadonly ? 'constant' : undefined ),
				type: 'text',
				description: __( 'Space-separated. Leave empty for defaults.', 'wppack-oauth-login' ),
				Edit: isReadonly
					? ( { data, field } ) => (
						<TextControl
							id={ field.id }
							label={ field.label }
							help={ __( 'Space-separated. Leave empty for defaults.', 'wppack-oauth-login' ) }
							value={ data.scopes }
							disabled
							onChange={ () => {} }
							__nextHasNoMarginBottom
						/>
					)
					: undefined,
			},
		];

		if ( reqFields.includes( 'tenant_id' ) ) {
			result.push( {
				id: 'tenant_id',
				label: badgeLabel( __( 'Tenant ID', 'wppack-oauth-login' ), isReadonly ? 'constant' : undefined ),
				type: 'text',
				Edit: isReadonly
					? ( { data, field } ) => (
						<TextControl
							id={ field.id }
							label={ field.label }
							value={ data.tenant_id }
							disabled
							onChange={ () => {} }
							__nextHasNoMarginBottom
						/>
					)
					: undefined,
			} );
		}

		if ( reqFields.includes( 'domain' ) ) {
			result.push( {
				id: 'domain',
				label: badgeLabel( __( 'Domain', 'wppack-oauth-login' ), isReadonly ? 'constant' : undefined ),
				type: 'text',
				Edit: isReadonly
					? ( { data, field } ) => (
						<TextControl
							id={ field.id }
							label={ field.label }
							value={ data.domain }
							disabled
							onChange={ () => {} }
							__nextHasNoMarginBottom
						/>
					)
					: undefined,
			} );
		}

		if ( reqFields.includes( 'discovery_url' ) ) {
			result.push( {
				id: 'discovery_url',
				label: badgeLabel( __( 'Discovery URL', 'wppack-oauth-login' ), isReadonly ? 'constant' : undefined ),
				type: 'text',
				Edit: isReadonly
					? ( { data, field } ) => (
						<TextControl
							id={ field.id }
							label={ field.label }
							value={ data.discovery_url }
							disabled
							onChange={ () => {} }
							__nextHasNoMarginBottom
						/>
					)
					: undefined,
			} );
		}

		if ( reqFields.includes( 'hosted_domain' ) ) {
			result.push( {
				id: 'hosted_domain',
				label: badgeLabel( __( 'Hosted Domain', 'wppack-oauth-login' ), isReadonly ? 'constant' : undefined ),
				type: 'text',
				description: __( 'Restrict to Google Workspace domain.', 'wppack-oauth-login' ),
				Edit: isReadonly
					? ( { data, field } ) => (
						<TextControl
							id={ field.id }
							label={ field.label }
							help={ __( 'Restrict to Google Workspace domain.', 'wppack-oauth-login' ) }
							value={ data.hosted_domain }
							disabled
							onChange={ () => {} }
							__nextHasNoMarginBottom
						/>
					)
					: undefined,
			} );
		}

		result.push(
			{
				id: 'auto_provision',
				label: badgeLabel( __( 'Auto-create Users', 'wppack-oauth-login' ), isReadonly ? 'constant' : undefined ),
				type: 'text',
				Edit: ( { data, field, onChange: onFieldChange } ) => (
					<ToggleControl
						label={ field.label }
						checked={ !! data.auto_provision }
						onChange={ ( val ) => onFieldChange( { auto_provision: val } ) }
						disabled={ isReadonly }
						__nextHasNoMarginBottom
					/>
				),
			},
		);

		result.push( {
			id: 'label',
			label: badgeLabel( __( 'Label', 'wppack-oauth-login' ), isReadonly ? 'constant' : undefined ),
			type: 'text',
			Edit: isReadonly
				? ( { data, field } ) => (
					<TextControl
						id={ field.id }
						label={ field.label }
						value={ data.label }
						disabled
						onChange={ () => {} }
						__nextHasNoMarginBottom
					/>
				)
				: undefined,
		} );

		if ( styleKeys.length > 0 ) {
			result.push( {
				id: 'button_style',
				label: badgeLabel( __( 'Button Style', 'wppack-oauth-login' ), isReadonly ? 'constant' : undefined ),
				type: 'text',
				elements: styleKeys.map( ( key ) => ( {
					label: providerStyles[ key ].label,
					value: key,
				} ) ),
				Edit: isReadonly
					? ( { data, field } ) => {
						const el = styleKeys.find( ( k ) => k === data.button_style );
						return (
							<TextControl
								id={ field.id }
								label={ field.label }
								value={ el ? providerStyles[ el ].label : data.button_style }
								disabled
								onChange={ () => {} }
								__nextHasNoMarginBottom
							/>
						);
					}
					: undefined,
			} );
		}

		result.push( {
			id: '_buttonPreview',
			label: __( 'Login Button Preview', 'wppack-oauth-login' ),
			type: 'text',
			Edit: ( { data } ) => {
				const style = providerStyles[ data.button_style ] || providerStyles[ styleKeys[ 0 ] ] || { bg: '#f0f0f0', text: '#1d2327', border: '#ddd', icon: 'original' };
				const displayLabel = data.label || name;
				return (
					<BaseControl label={ __( 'Login Button Preview', 'wppack-oauth-login' ) }>
						<div className="wpp-oauth-button-preview">
							{ buttonDisplay === 'icon-only' ? (
								<Tooltip text={ sprintf( __( 'Login with %s', 'wppack-oauth-login' ), displayLabel ) }>
									<a
										className="wpp-oauth-login-button is-icon-only"
										style={ { background: style.bg, color: style.text, border: `1px solid ${ style.border }` } }
									>
										{ /* Safe: SVG icon HTML is generated server-side by PHP, not from user input */ }
									{ icon && <span className="wpp-oauth-login-icon" style={ style.icon !== 'original' ? { color: style.icon } : {} } dangerouslySetInnerHTML={ { __html: icon } } /> }
									</a>
								</Tooltip>
							) : (
								<a
									className={ `wpp-oauth-login-button${ buttonDisplay === 'icon-left' ? ' is-icon-left' : '' }` }
									style={ { background: style.bg, color: style.text, border: `1px solid ${ style.border }` } }
								>
									{ /* Safe: SVG icon HTML is generated server-side by PHP, not from user input */ }
								{ buttonDisplay !== 'text-only' && icon && <span className="wpp-oauth-login-icon" style={ style.icon !== 'original' ? { color: style.icon } : {} } dangerouslySetInnerHTML={ { __html: icon } } /> }
									<span className="wpp-oauth-login-text">{ sprintf( __( 'Login with %s', 'wppack-oauth-login' ), displayLabel ) }</span>
								</a>
							) }
						</div>
					</BaseControl>
				);
			},
		} );

		return result;
	}, [ reqFields, isReadonly, styleKeys, providerStyles, buttonDisplay, name, icon ] );

	const panelForm = useMemo( () => ( {
		fields: panelFormFields.map( ( pf ) => pf.id ),
	} ), [ panelFormFields ] );

	const titleElement = (
		<span className="wpp-oauth-panel-title">
			<span className="wpp-oauth-move-buttons">
				{ /* eslint-disable-next-line jsx-a11y/click-events-have-key-events */ }
				<span role="button" tabIndex={ 0 } className={ `wpp-oauth-move-btn${ isFirst ? ' is-disabled' : '' }` } onClick={ ( e ) => { e.stopPropagation(); if ( ! isFirst ) onMoveUp( name ); } }>&#9650;</span>
				{ /* eslint-disable-next-line jsx-a11y/click-events-have-key-events */ }
				<span role="button" tabIndex={ 0 } className={ `wpp-oauth-move-btn${ isLast ? ' is-disabled' : '' }` } onClick={ ( e ) => { e.stopPropagation(); if ( ! isLast ) onMoveDown( name ); } }>&#9660;</span>
			</span>
			{ /* Safe: SVG icon HTML is generated server-side by PHP, not from user input */ }
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
			<div className="wpp-oauth-dataform-wrap">
				<DataForm
					data={ panelData }
					fields={ panelFormFields }
					form={ panelForm }
					onChange={ handlePanelChange }
				/>
			</div>
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

	const updateProvider = ( name, fields ) => {
		setProviderForm( ( prev ) => ( { ...prev, [ name ]: fields } ) );
	};

	const handleAddProvider = () => {
		const type = newProviderType;
		if ( ! type ) {
			return;
		}

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

	// ── Global Settings DataForm ──

	const g = ( key ) => globalSettings?.[ key ] || {};

	const globalData = useMemo( () => ( {
		ssoOnly: !! globalForm.ssoOnly,
		autoProvision: !! globalForm.autoProvision,
		buttonDisplay: globalForm.buttonDisplay || 'icon-text',
		authorizePath: globalForm.authorizePath || '',
		callbackPath: globalForm.callbackPath || '',
		verifyPath: globalForm.verifyPath || '',
	} ), [ globalForm ] );

	const globalFields = useMemo( () => [
		{
			id: 'ssoOnly',
			label: badgeLabel( __( 'SSO Only Mode', 'wppack-oauth-login' ), g( 'ssoOnly' ).source ),
			type: 'text',
			Edit: ( { data, field, onChange: onFieldChange } ) => (
				<ToggleControl
					label={ field.label }
					help={ __( 'Disable WordPress login form.', 'wppack-oauth-login' ) }
					checked={ !! data.ssoOnly }
					onChange={ ( val ) => onFieldChange( { ssoOnly: val } ) }
					disabled={ g( 'ssoOnly' ).readonly }
					__nextHasNoMarginBottom
				/>
			),
		},
		{
			id: 'autoProvision',
			label: badgeLabel( __( 'Auto-create Users', 'wppack-oauth-login' ), g( 'autoProvision' ).source ),
			type: 'text',
			Edit: ( { data, field, onChange: onFieldChange } ) => (
				<ToggleControl
					label={ field.label }
					help={ __( 'Global default. Can be overridden per provider.', 'wppack-oauth-login' ) }
					checked={ !! data.autoProvision }
					onChange={ ( val ) => onFieldChange( { autoProvision: val } ) }
					disabled={ g( 'autoProvision' ).readonly }
					__nextHasNoMarginBottom
				/>
			),
		},
		{
			id: 'authorizePath',
			label: __( 'Authorize Path', 'wppack-oauth-login' ),
			type: 'text',
			Edit: ( { data, onChange: onFieldChange } ) => (
				<PathField
					id="authorizePath"
					label={ __( 'Authorize Path', 'wppack-oauth-login' ) }
					field={ g( 'authorizePath' ) }
					value={ data.authorizePath }
					onChange={ ( val ) => onFieldChange( { authorizePath: val } ) }
					prefix={ siteUrl }
				/>
			),
		},
		{
			id: 'callbackPath',
			label: __( 'Callback Path', 'wppack-oauth-login' ),
			type: 'text',
			Edit: ( { data, onChange: onFieldChange } ) => (
				<PathField
					id="callbackPath"
					label={ __( 'Callback Path', 'wppack-oauth-login' ) }
					field={ g( 'callbackPath' ) }
					value={ data.callbackPath }
					onChange={ ( val ) => onFieldChange( { callbackPath: val } ) }
					prefix={ siteUrl }
				/>
			),
		},
		{
			id: 'verifyPath',
			label: __( 'Verify Path', 'wppack-oauth-login' ),
			type: 'text',
			Edit: ( { data, onChange: onFieldChange } ) => (
				<PathField
					id="verifyPath"
					label={ __( 'Verify Path', 'wppack-oauth-login' ) }
					field={ g( 'verifyPath' ) }
					value={ data.verifyPath }
					onChange={ ( val ) => onFieldChange( { verifyPath: val } ) }
					prefix={ siteUrl }
				/>
			),
		},
		{
			id: 'buttonDisplay',
			label: badgeLabel( __( 'Button Display', 'wppack-oauth-login' ), g( 'buttonDisplay' ).source ),
			type: 'text',
			elements: [
				{ label: __( 'Icon + Text', 'wppack-oauth-login' ), value: 'icon-text' },
				{ label: __( 'Icon Left + Text', 'wppack-oauth-login' ), value: 'icon-left' },
				{ label: __( 'Icon Only', 'wppack-oauth-login' ), value: 'icon-only' },
				{ label: __( 'Text Only', 'wppack-oauth-login' ), value: 'text-only' },
			],
			Edit: g( 'buttonDisplay' ).readonly
				? ( { data, field } ) => {
					const displayLabels = { 'icon-text': __( 'Icon + Text', 'wppack-oauth-login' ), 'icon-left': __( 'Icon Left + Text', 'wppack-oauth-login' ), 'icon-only': __( 'Icon Only', 'wppack-oauth-login' ), 'text-only': __( 'Text Only', 'wppack-oauth-login' ) };
					return (
						<TextControl
							id={ field.id }
							label={ field.label }
							value={ displayLabels[ data.buttonDisplay ] || data.buttonDisplay }
							disabled
							onChange={ () => {} }
							__nextHasNoMarginBottom
						/>
					);
				}
				: undefined,
		},
	], [ globalSettings, siteUrl ] );

	const globalFormDef = useMemo( () => ( {
		fields: [ {
			id: 'global-section',
			label: __( 'Global Settings', 'wppack-oauth-login' ),
			children: globalFields.map( ( gf ) => gf.id ),
			layout: { type: 'regular' },
		} ],
	} ), [ globalFields ] );

	const handleGlobalChange = ( edits ) => {
		setGlobalForm( ( prev ) => ( { ...prev, ...edits } ) );
	};

	// ── Add Provider DataForm ──

	const addProviderOptions = useMemo( () => [
		{ label: __( '-- Select Provider --', 'wppack-oauth-login' ), value: '' },
		...Object.values( definitions )
			.filter( ( def ) => def.type !== 'oidc' )
			.sort( ( a, b ) => a.dropdownLabel.localeCompare( b.dropdownLabel ) )
			.map( ( def ) => ( { label: def.dropdownLabel, value: def.type } ) ),
		...( definitions.oidc ? [ { label: definitions.oidc.dropdownLabel, value: 'oidc' } ] : [] ),
	], [ definitions ] );

	const addData = useMemo( () => ( {
		providerType: newProviderType,
	} ), [ newProviderType ] );

	const addFields = useMemo( () => [
		{
			id: 'providerType',
			label: __( 'Provider Type', 'wppack-oauth-login' ),
			type: 'text',
			elements: addProviderOptions.filter( ( o ) => o.value !== '' ),
			Edit: ( { data, field, onChange: onFieldChange } ) => (
				<SelectControl
					id={ field.id }
					label={ field.label }
					value={ data.providerType }
					options={ addProviderOptions }
					onChange={ ( val ) => onFieldChange( { providerType: val } ) }
					__nextHasNoMarginBottom
				/>
			),
		},
	], [ addProviderOptions ] );

	const addForm = useMemo( () => ( {
		fields: [ {
			id: 'add-section',
			label: __( 'Add Provider', 'wppack-oauth-login' ),
			children: [ 'providerType' ],
			layout: { type: 'regular' },
		} ],
	} ), [] );

	const handleAddFormChange = ( edits ) => {
		if ( 'providerType' in edits ) {
			setNewProviderType( edits.providerType );
		}
	};

	if ( loading ) {
		return (
			<div className="wpp-oauth-loading">
				<Spinner />
			</div>
		);
	}

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

				<div className="wpp-oauth-dataform-wrap">
					<DataForm
						data={ globalData }
						fields={ globalFields }
						form={ globalFormDef }
						onChange={ handleGlobalChange }
					/>
				</div>

				<Panel>
					{ providerOrder.map(
						( name, idx ) => {
							const provider = providers[ name ];
							if ( ! provider ) return null;
							return (
								<ProviderPanel
									key={ name }
									name={ name }
									provider={ provider }
									providerFields={ providerForm[ name ] || provider.fields }
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
									initialOpen={ name === lastAdded }
								/>
							);
						}
					) }
				</Panel>

				<div className="wpp-oauth-add-section wpp-oauth-dataform-wrap">
					<DataForm
						data={ addData }
						fields={ addFields }
						form={ addForm }
						onChange={ handleAddFormChange }
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
							? __( 'Saving...', 'wppack-oauth-login' )
							: __( 'Save Settings', 'wppack-oauth-login' ) }
					</Button>
				</div>
			</div>
		</Page>
	);
}
