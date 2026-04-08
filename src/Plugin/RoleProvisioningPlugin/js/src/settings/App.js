import { useState, useEffect, useCallback } from '@wordpress/element';
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
} from '@wordpress/components';
import { Page } from '@wordpress/admin-ui';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const OPERATORS = [
	{ label: 'equals', value: 'equals' },
	{ label: 'not_equals', value: 'not_equals' },
	{ label: 'contains', value: 'contains' },
	{ label: 'starts_with', value: 'starts_with' },
	{ label: 'ends_with', value: 'ends_with' },
	{ label: 'matches', value: 'matches' },
	{ label: 'exists', value: 'exists' },
];

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
				<TextControl
					label={ __( 'Field', 'wppack-role-provisioning' ) }
					value={ condition.field }
					onChange={ ( v ) => onChange( { ...condition, field: v } ) }
					placeholder="user.email, meta._wppack_sso_source"
					__nextHasNoMarginBottom
				/>
			</FlexBlock>
			<FlexItem>
				<SelectControl
					label={ __( 'Operator', 'wppack-role-provisioning' ) }
					value={ condition.operator }
					onChange={ ( v ) => onChange( { ...condition, operator: v } ) }
					options={ OPERATORS }
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

function RuleCard( { rule, index, onChange, onRemove, roles } ) {
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

	const blogIdsStr = rule.blogIds === null ? '' : ( rule.blogIds || [] ).join( ', ' );

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
				{ rule.conditions.map( ( cond, i ) => (
					<ConditionRow
						key={ i }
						condition={ cond }
						onChange={ ( c ) => updateCondition( i, c ) }
						onRemove={ () => removeCondition( i ) }
						canRemove={ rule.conditions.length > 1 }
					/>
				) ) }
				<Button variant="tertiary" size="small" onClick={ addCondition } style={ { marginTop: '4px' } }>
					+ { __( 'Add Condition', 'wppack-role-provisioning' ) }
				</Button>

				<Flex gap={ 4 } style={ { marginTop: '12px' } } wrap>
					<FlexBlock>
						<TextControl
							label={ __( 'Role', 'wppack-role-provisioning' ) }
							value={ rule.role }
							onChange={ ( v ) => onChange( { ...rule, role: v } ) }
							help={ __( 'Role name or {{meta.<key>.<path>}} template', 'wppack-role-provisioning' ) }
							placeholder={ roles?.length ? roles[ 0 ] : 'subscriber' }
							__nextHasNoMarginBottom
						/>
					</FlexBlock>
					<FlexBlock>
						<TextControl
							label={ __( 'Blog IDs', 'wppack-role-provisioning' ) }
							value={ blogIdsStr }
							onChange={ ( v ) => {
								if ( v.trim() === '' ) {
									onChange( { ...rule, blogIds: null } );
								} else {
									const ids = v.split( ',' ).map( ( s ) => parseInt( s.trim(), 10 ) ).filter( ( n ) => ! isNaN( n ) );
									onChange( { ...rule, blogIds: ids } );
								}
							} }
							help={ __( 'Comma-separated blog IDs. Empty = all sites.', 'wppack-role-provisioning' ) }
							__nextHasNoMarginBottom
						/>
					</FlexBlock>
				</Flex>
			</CardBody>
		</Card>
	);
}

export default function App() {
	const [ settings, setSettings ] = useState( {
		enabled: true,
		addUserToBlog: false,
		syncOnLogin: false,
		rules: [],
	} );
	const [ roles, setRoles ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	const loadSettings = useCallback( () => {
		setLoading( true );
		apiFetch( { path: '/wppack/v1/role-provisioning/settings' } )
			.then( ( data ) => {
				const s = data.settings || {};
				setSettings( {
					enabled: s.enabled?.value ?? true,
					addUserToBlog: s.addUserToBlog?.value ?? false,
					syncOnLogin: s.syncOnLogin?.value ?? false,
					rules: s.rules?.value ?? [],
				} );
				setRoles( data.roles ?? [] );
				setLoading( false );
			} )
			.catch( () => {
				setNotice( { type: 'error', message: __( 'Failed to load settings.', 'wppack-role-provisioning' ) } );
				setLoading( false );
			} );
	}, [] );

	useEffect( () => {
		loadSettings();
	}, [ loadSettings ] );

	const handleSave = () => {
		setSaving( true );
		setNotice( null );
		apiFetch( {
			path: '/wppack/v1/role-provisioning/settings',
			method: 'POST',
			data: settings,
		} )
			.then( ( data ) => {
				const s = data.settings || {};
				setSettings( {
					enabled: s.enabled?.value ?? true,
					addUserToBlog: s.addUserToBlog?.value ?? false,
					syncOnLogin: s.syncOnLogin?.value ?? false,
					rules: s.rules?.value ?? [],
				} );
				setNotice( { type: 'success', message: __( 'Settings saved.', 'wppack-role-provisioning' ) } );
			} )
			.catch( () => {
				setNotice( { type: 'error', message: __( 'Failed to save settings.', 'wppack-role-provisioning' ) } );
			} )
			.finally( () => setSaving( false ) );
	};

	const updateRule = ( i, rule ) => {
		const next = [ ...settings.rules ];
		next[ i ] = rule;
		setSettings( { ...settings, rules: next } );
	};

	const removeRule = ( i ) => {
		setSettings( { ...settings, rules: settings.rules.filter( ( _, j ) => j !== i ) } );
	};

	const addRule = () => {
		setSettings( { ...settings, rules: [ ...settings.rules, emptyRule() ] } );
	};

	if ( loading ) {
		return (
			<Page title={ __( 'Role Provisioning', 'wppack-role-provisioning' ) } hasPadding>
				<div style={ { display: 'flex', justifyContent: 'center', padding: '48px' } }>
					<Spinner />
				</div>
			</Page>
		);
	}

	return (
		<Page title={ __( 'Role Provisioning', 'wppack-role-provisioning' ) } hasPadding>
			<div style={ { maxWidth: '900px', marginTop: '16px' } }>
				{ notice && (
					<Notice status={ notice.type } isDismissible onDismiss={ () => setNotice( null ) } style={ { marginBottom: '16px' } }>
						{ notice.message }
					</Notice>
				) }

				<ToggleControl
					label={ __( 'Enabled', 'wppack-role-provisioning' ) }
					help={ __( 'Enable role provisioning rules.', 'wppack-role-provisioning' ) }
					checked={ settings.enabled }
					onChange={ ( v ) => setSettings( { ...settings, enabled: v } ) }
					__nextHasNoMarginBottom
				/>

				<ToggleControl
					label={ __( 'Add to Blog', 'wppack-role-provisioning' ) }
					help={ __( 'Automatically add new users to the current site.', 'wppack-role-provisioning' ) }
					checked={ settings.addUserToBlog }
					onChange={ ( v ) => setSettings( { ...settings, addUserToBlog: v } ) }
					__nextHasNoMarginBottom
					style={ { marginTop: '12px' } }
				/>

				<ToggleControl
					label={ __( 'Sync on Login', 'wppack-role-provisioning' ) }
					help={ __( 'Re-evaluate rules on every SSO login (not just on registration).', 'wppack-role-provisioning' ) }
					checked={ settings.syncOnLogin }
					onChange={ ( v ) => setSettings( { ...settings, syncOnLogin: v } ) }
					__nextHasNoMarginBottom
					style={ { marginTop: '12px' } }
				/>

				<h3 style={ { marginTop: '24px' } }>{ __( 'Rules', 'wppack-role-provisioning' ) }</h3>
				<p style={ { color: '#757575', fontSize: '13px', marginBottom: '12px' } }>
					{ __( 'Rules are evaluated top-down. The first matching rule is applied.', 'wppack-role-provisioning' ) }
				</p>

				{ settings.rules.map( ( rule, i ) => (
					<RuleCard
						key={ i }
						rule={ rule }
						index={ i }
						onChange={ ( r ) => updateRule( i, r ) }
						onRemove={ () => removeRule( i ) }
						roles={ roles }
					/>
				) ) }

				<Button variant="secondary" onClick={ addRule } style={ { marginBottom: '24px' } }>
					+ { __( 'Add Rule', 'wppack-role-provisioning' ) }
				</Button>

				<div style={ { marginTop: '16px' } }>
					<Button variant="primary" onClick={ handleSave } isBusy={ saving } disabled={ saving }>
						{ saving ? __( 'Saving…', 'wppack-role-provisioning' ) : __( 'Save Settings', 'wppack-role-provisioning' ) }
					</Button>
				</div>
			</div>
		</Page>
	);
}
