import { DataForm } from '@wordpress/dataviews/wp';
import { useState, useEffect, useMemo, useCallback, useRef } from '@wordpress/element';
import {
	Button,
	TextControl,
	SelectControl,
	ToggleControl,
	Notice,
	Spinner,
	Card,
	CardBody,
	CardHeader,
	Flex,
	FlexItem,
	FlexBlock,
	FormTokenField,
} from '@wordpress/components';
import { Page } from '@wordpress/admin-ui';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

function getOperators() {
	return [
		{ label: __( 'Equals', 'wppack-role-provisioning' ), value: 'equals' },
		{ label: __( 'Not equals', 'wppack-role-provisioning' ), value: 'not_equals' },
		{ label: __( 'Contains', 'wppack-role-provisioning' ), value: 'contains' },
		{ label: __( 'Starts with', 'wppack-role-provisioning' ), value: 'starts_with' },
		{ label: __( 'Ends with', 'wppack-role-provisioning' ), value: 'ends_with' },
		{ label: __( 'Matches (regex)', 'wppack-role-provisioning' ), value: 'matches' },
		{ label: __( 'Exists', 'wppack-role-provisioning' ), value: 'exists' },
	];
}

const FIELD_SUGGESTIONS = [
	'user.email',
	'user.login',
	'meta._wppack_sso_source',
	'meta._wppack_sso_provider',
	'meta._wppack_saml_attributes',
	'meta._wppack_saml_attributes.groups',
	'meta._wppack_oauth_claims_google',
	'meta._wppack_oauth_claims_azure',
	'meta._wppack_oauth_claims_github',
];

/**
 * Text input with dropdown suggestions (ComboboxControl-like).
 * Allows free-text input and does NOT clear value on focus.
 */
function SuggestInput( { label, value, onChange, suggestions, help, placeholder, __nextHasNoMarginBottom } ) {
	const [ open, setOpen ] = useState( false );
	const [ filter, setFilter ] = useState( '' );
	const [ listTop, setListTop ] = useState( 0 );
	const wrapRef = useRef( null );

	const filtered = useMemo( () => {
		const q = ( filter || value || '' ).toLowerCase();
		if ( ! q ) {
			return suggestions;
		}
		return suggestions.filter( ( s ) => s.toLowerCase().includes( q ) );
	}, [ filter, value, suggestions ] );

	useEffect( () => {
		function onClickOutside( e ) {
			if ( wrapRef.current && ! wrapRef.current.contains( e.target ) ) {
				setOpen( false );
			}
		}
		document.addEventListener( 'mousedown', onClickOutside );
		return () => document.removeEventListener( 'mousedown', onClickOutside );
	}, [] );

	const updateListPosition = useCallback( () => {
		if ( wrapRef.current ) {
			const input = wrapRef.current.querySelector( 'input' );
			if ( input ) {
				setListTop( input.offsetTop + input.offsetHeight + 0.5 );
			}
		}
	}, [] );

	return (
		<div ref={ wrapRef } className="wpp-suggest-input">
			<TextControl
				label={ label }
				value={ value }
				onChange={ ( v ) => {
					onChange( v );
					setFilter( v );
					updateListPosition();
					setOpen( true );
				} }
				onFocus={ () => {
					updateListPosition();
					setOpen( true );
				} }
				help={ help }
				placeholder={ placeholder }
				__nextHasNoMarginBottom={ __nextHasNoMarginBottom }
				autoComplete="off"
			/>
			{ open && filtered.length > 0 && (
				<ul className="wpp-suggest-input__list" style={ { top: listTop } }>
					{ filtered.map( ( item ) => (
						<li key={ item }>
							<button
								type="button"
								className={ `wpp-suggest-input__item${ item === value ? ' is-selected' : '' }` }
								onMouseDown={ ( e ) => {
									e.preventDefault();
									onChange( item );
									setOpen( false );
									setFilter( '' );
								} }
							>
								{ item }
							</button>
						</li>
					) ) }
				</ul>
			) }
		</div>
	);
}

function emptyCondition() {
	return { field: '', operator: 'equals', value: '' };
}

function emptyRule() {
	return { conditions: [ emptyCondition() ], role: '', blogIds: null };
}

function ConditionRow( { condition, onChange, onRemove, canRemove } ) {
	return (
		<Flex align="flex-end" gap={ 2 } wrap>
			<FlexBlock>
				<SuggestInput
					label={ __( 'Field', 'wppack-role-provisioning' ) }
					value={ condition.field }
					onChange={ ( v ) => onChange( { ...condition, field: v } ) }
					suggestions={ FIELD_SUGGESTIONS }
					__nextHasNoMarginBottom
				/>
			</FlexBlock>
			<FlexItem>
				<SelectControl
					label={ __( 'Operator', 'wppack-role-provisioning' ) }
					value={ condition.operator }
					onChange={ ( v ) => onChange( { ...condition, operator: v } ) }
					options={ getOperators() }
					__nextHasNoMarginBottom
				/>
			</FlexItem>
			{ condition.operator !== 'exists' && (
				<FlexBlock>
					<TextControl
						label={ __( 'Value', 'wppack-role-provisioning' ) }
						value={ condition.value }
						onChange={ ( v ) => onChange( { ...condition, value: v } ) }
						__nextHasNoMarginBottom
					/>
				</FlexBlock>
			) }
			{ canRemove && (
				<FlexItem>
					<Button
						isDestructive
						variant="tertiary"
						size="small"
						onClick={ onRemove }
						style={ { marginBottom: '8px' } }
					>
						&times;
					</Button>
				</FlexItem>
			) }
		</Flex>
	);
}

function RuleCard( { rule, index, onChange, onRemove, roleSuggestions, siteMap } ) {
	const updateCondition = ( i, cond ) => {
		const next = [ ...rule.conditions ];
		next[ i ] = cond;
		onChange( { ...rule, conditions: next } );
	};

	const removeCondition = ( i ) => {
		onChange( { ...rule, conditions: rule.conditions.filter( ( _, j ) => j !== i ) } );
	};

	const addCondition = () => {
		onChange( { ...rule, conditions: [ ...rule.conditions, emptyCondition() ] } );
	};

	return (
		<Card size="small" style={ { marginBottom: '12px' } }>
			<CardHeader size="small">
				<Flex justify="space-between" align="center">
					<FlexItem>
						<strong>{ __( 'Rule', 'wppack-role-provisioning' ) } #{ index + 1 }</strong>
					</FlexItem>
					<FlexItem>
						<Button isDestructive variant="tertiary" size="small" onClick={ onRemove }>
							{ __( 'Remove', 'wppack-role-provisioning' ) }
						</Button>
					</FlexItem>
				</Flex>
			</CardHeader>
			<CardBody>
				<p style={ { margin: '0 0 8px', fontWeight: 500, fontSize: '12px', textTransform: 'uppercase', color: '#1e1e1e' } }>
					{ __( 'Conditions (AND)', 'wppack-role-provisioning' ) }
				</p>
				<div style={ { display: 'flex', flexDirection: 'column', gap: '8px' } }>
					{ rule.conditions.map( ( cond, i ) => (
						<ConditionRow
							key={ i }
							condition={ cond }
							onChange={ ( c ) => updateCondition( i, c ) }
							onRemove={ () => removeCondition( i ) }
							canRemove={ rule.conditions.length > 1 }
						/>
					) ) }
				</div>
				<Button variant="tertiary" size="small" onClick={ addCondition } style={ { marginTop: '4px' } }>
					+ { __( 'Add Condition', 'wppack-role-provisioning' ) }
				</Button>

				<Flex gap={ 4 } style={ { marginTop: '12px' } } wrap>
					<FlexBlock>
						<SuggestInput
							label={ __( 'Role', 'wppack-role-provisioning' ) }
							value={ rule.role }
							onChange={ ( v ) => onChange( { ...rule, role: v } ) }
							suggestions={ roleSuggestions }
							help={ __( 'Role name or {{meta.<key>.<path>}} template', 'wppack-role-provisioning' ) }
							placeholder="subscriber"
							__nextHasNoMarginBottom
						/>
					</FlexBlock>
					{ Object.keys( siteMap ).length > 0 && (
						<FlexBlock>
							<FormTokenField
								label={ __( 'Sites', 'wppack-role-provisioning' ) }
								value={ ( rule.blogIds || [] ).map( ( id ) => siteMap[ id ] || `#${ id }` ) }
								suggestions={ Object.values( siteMap ) }
								onChange={ ( tokens ) => {
									if ( tokens.length === 0 ) {
										onChange( { ...rule, blogIds: null } );
									} else {
										const ids = tokens.map( ( t ) => {
											const entry = Object.entries( siteMap ).find( ( [ , name ] ) => name === t );
											return entry ? parseInt( entry[ 0 ], 10 ) : null;
										} ).filter( ( id ) => id !== null );
										onChange( { ...rule, blogIds: ids.length > 0 ? ids : null } );
									}
								} }
								__experimentalShowHowTo={ false }
								__nextHasNoMarginBottom
							/>
							<p className="components-base-control__help" style={ { marginTop: '4px' } }>
								{ __( 'Empty = all sites.', 'wppack-role-provisioning' ) }
							</p>
						</FlexBlock>
					) }
				</Flex>
			</CardBody>
		</Card>
	);
}

export default function App() {
	const [ formData, setFormData ] = useState( {
		enabled: true,
		addUserToBlog: false,
		syncOnLogin: false,
		rules: [],
	} );
	const [ roles, setRoles ] = useState( {} );
	const [ sites, setSites ] = useState( [] );
	const [ isMultisite, setIsMultisite ] = useState( false );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	const roleSuggestions = useMemo( () => Object.keys( roles ), [ roles ] );
	const siteMap = useMemo( () => {
		const m = {};
		sites.forEach( ( s ) => { m[ s.id ] = s.name || `#${ s.id }`; } );
		return m;
	}, [ sites ] );

	const applyResponse = useCallback( ( data ) => {
		const s = data.settings || {};
		setFormData( {
			enabled: s.enabled?.value ?? true,
			addUserToBlog: s.addUserToBlog?.value ?? false,
			syncOnLogin: s.syncOnLogin?.value ?? false,
			rules: s.rules?.value ?? [],
		} );
		setRoles( data.roles ?? {} );
		setSites( data.sites ?? [] );
		if ( data.isMultisite !== undefined ) {
			setIsMultisite( data.isMultisite );
		}
	}, [] );

	useEffect( () => {
		apiFetch( { path: '/wppack/v1/role-provisioning/settings' } )
			.then( ( data ) => {
				applyResponse( data );
				setLoading( false );
			} )
			.catch( () => {
				setNotice( { type: 'error', message: __( 'Failed to load settings.', 'wppack-role-provisioning' ) } );
				setLoading( false );
			} );
	}, [ applyResponse ] );

	const handleSave = () => {
		setSaving( true );
		setNotice( null );
		apiFetch( {
			path: '/wppack/v1/role-provisioning/settings',
			method: 'POST',
			data: formData,
		} )
			.then( ( data ) => {
				applyResponse( data );
				setNotice( { type: 'success', message: __( 'Settings saved.', 'wppack-role-provisioning' ) } );
			} )
			.catch( () => {
				setNotice( { type: 'error', message: __( 'Failed to save settings.', 'wppack-role-provisioning' ) } );
			} )
			.finally( () => setSaving( false ) );
	};

	// ── DataForm fields ──

	const generalFields = useMemo( () => {
		const fields = [
			{
				id: 'enabled',
				label: __( 'Enabled', 'wppack-role-provisioning' ),
				type: 'text',
				Edit: ( { data } ) => (
					<ToggleControl
						label={ __( 'Enabled', 'wppack-role-provisioning' ) }
						help={ __( 'Enable role provisioning rules.', 'wppack-role-provisioning' ) }
						checked={ !! data.enabled }
						onChange={ ( v ) => setFormData( ( prev ) => ( { ...prev, enabled: v } ) ) }
						__nextHasNoMarginBottom
					/>
				),
			},
		];

		if ( isMultisite ) {
			fields.push( {
				id: 'addUserToBlog',
				label: __( 'Add to Main Site', 'wppack-role-provisioning' ),
				type: 'text',
				Edit: ( { data } ) => (
					<ToggleControl
						label={ __( 'Add to Main Site', 'wppack-role-provisioning' ) }
						help={ __( 'Add new users to the main site with the default role.', 'wppack-role-provisioning' ) }
						checked={ !! data.addUserToBlog }
						onChange={ ( v ) => setFormData( ( prev ) => ( { ...prev, addUserToBlog: v } ) ) }
						__nextHasNoMarginBottom
					/>
				),
			} );
		}

		fields.push( {
			id: 'syncOnLogin',
			label: __( 'Sync on Login', 'wppack-role-provisioning' ),
			type: 'text',
			Edit: ( { data } ) => (
				<ToggleControl
					label={ __( 'Sync on Login', 'wppack-role-provisioning' ) }
					help={ __( 'Re-evaluate rules on every SSO login (not just on registration).', 'wppack-role-provisioning' ) }
					checked={ !! data.syncOnLogin }
					onChange={ ( v ) => setFormData( ( prev ) => ( { ...prev, syncOnLogin: v } ) ) }
					__nextHasNoMarginBottom
				/>
			),
		} );

		return fields;
	}, [ isMultisite ] );

	const rulesField = useMemo( () => [
		{
			id: 'rules',
			label: __( 'Rules', 'wppack-role-provisioning' ),
			type: 'text',
			Edit: ( { data } ) => {
				const rules = data.rules || [];
				const updateRule = ( i, rule ) => {
					const next = [ ...rules ];
					next[ i ] = rule;
					setFormData( ( prev ) => ( { ...prev, rules: next } ) );
				};
				const removeRule = ( i ) => {
					setFormData( ( prev ) => ( { ...prev, rules: prev.rules.filter( ( _, j ) => j !== i ) } ) );
				};
				const addRule = () => {
					setFormData( ( prev ) => ( { ...prev, rules: [ ...prev.rules, emptyRule() ] } ) );
				};
				return (
					<div>
						<p style={ { color: '#757575', fontSize: '13px', marginTop: 0, marginBottom: '12px' } }>
							{ __( 'Rules are evaluated top-down. The first matching rule is applied.', 'wppack-role-provisioning' ) }
						</p>
						{ rules.map( ( rule, i ) => (
							<RuleCard
								key={ i }
								rule={ rule }
								index={ i }
								onChange={ ( r ) => updateRule( i, r ) }
								onRemove={ () => removeRule( i ) }
								roleSuggestions={ roleSuggestions }
								siteMap={ siteMap }
							/>
						) ) }
						<Button variant="secondary" onClick={ addRule }>
							+ { __( 'Add Rule', 'wppack-role-provisioning' ) }
						</Button>
					</div>
				);
			},
		},
	], [ roleSuggestions ] );

	const allFields = useMemo(
		() => [ ...generalFields, ...rulesField ],
		[ generalFields, rulesField ]
	);

	const formLayout = useMemo( () => ( {
		fields: [
			{
				id: 'general-section',
				label: __( 'General', 'wppack-role-provisioning' ),
				children: generalFields.map( ( f ) => f.id ),
				layout: { type: 'regular' },
			},
			{
				id: 'rules-section',
				label: __( 'Rules', 'wppack-role-provisioning' ),
				children: [ 'rules' ],
				layout: { type: 'regular' },
			},
		],
	} ), [ generalFields ] );

	if ( loading ) {
		return (
			<Page title={ __( 'Role Provisioning', 'wppack-role-provisioning' ) } hasPadding>
				<div style={ { display: 'flex', justifyContent: 'center', padding: '48px' } }><Spinner /></div>
			</Page>
		);
	}

	return (
		<Page title={ __( 'Role Provisioning', 'wppack-role-provisioning' ) } hasPadding>
			<div className="wpp-role-provisioning-settings">
				{ notice && (
					<Notice status={ notice.type } isDismissible onDismiss={ () => setNotice( null ) } style={ { marginBottom: '16px' } }>
						{ notice.message }
					</Notice>
				) }

				<div className="wpp-role-provisioning-dataform-wrap">
					<DataForm
						data={ formData }
						fields={ allFields }
						form={ formLayout }
						onChange={ ( edits ) => setFormData( ( prev ) => ( { ...prev, ...edits } ) ) }
					/>
				</div>

				<div className="wpp-role-provisioning-actions">
					<Button variant="primary" onClick={ handleSave } isBusy={ saving } disabled={ saving }>
						{ saving ? __( 'Saving…', 'wppack-role-provisioning' ) : __( 'Save Settings', 'wppack-role-provisioning' ) }
					</Button>
				</div>
			</div>
		</Page>
	);
}
