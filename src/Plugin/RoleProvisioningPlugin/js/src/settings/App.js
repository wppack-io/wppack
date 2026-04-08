import { DataForm } from '@wordpress/dataviews/wp';
import { useState, useEffect, useMemo, useCallback } from '@wordpress/element';
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

function RuleCard( { rule, index, onChange, onRemove } ) {
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
							placeholder="subscriber"
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
	const [ formData, setFormData ] = useState( {
		enabled: true,
		addUserToBlog: false,
		syncOnLogin: false,
		rules: [],
	} );
	const [ roles, setRoles ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	const applyResponse = useCallback( ( data ) => {
		const s = data.settings || {};
		setFormData( {
			enabled: s.enabled?.value ?? true,
			addUserToBlog: s.addUserToBlog?.value ?? false,
			syncOnLogin: s.syncOnLogin?.value ?? false,
			rules: s.rules?.value ?? [],
		} );
		setRoles( data.roles ?? [] );
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

	const generalFields = useMemo( () => [
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
		{
			id: 'addUserToBlog',
			label: __( 'Add to Blog', 'wppack-role-provisioning' ),
			type: 'text',
			Edit: ( { data } ) => (
				<ToggleControl
					label={ __( 'Add to Blog', 'wppack-role-provisioning' ) }
					help={ __( 'Automatically add new users to the current site.', 'wppack-role-provisioning' ) }
					checked={ !! data.addUserToBlog }
					onChange={ ( v ) => setFormData( ( prev ) => ( { ...prev, addUserToBlog: v } ) ) }
					__nextHasNoMarginBottom
				/>
			),
		},
		{
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
		},
	], [] );

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
						<p style={ { color: '#757575', fontSize: '13px', marginBottom: '12px' } }>
							{ __( 'Rules are evaluated top-down. The first matching rule is applied.', 'wppack-role-provisioning' ) }
						</p>
						{ rules.map( ( rule, i ) => (
							<RuleCard
								key={ i }
								rule={ rule }
								index={ i }
								onChange={ ( r ) => updateRule( i, r ) }
								onRemove={ () => removeRule( i ) }
							/>
						) ) }
						<Button variant="secondary" onClick={ addRule }>
							+ { __( 'Add Rule', 'wppack-role-provisioning' ) }
						</Button>
					</div>
				);
			},
		},
	], [] );

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
			<div style={ { maxWidth: '900px', marginTop: '16px' } }>
				{ notice && (
					<Notice status={ notice.type } isDismissible onDismiss={ () => setNotice( null ) } style={ { marginBottom: '16px' } }>
						{ notice.message }
					</Notice>
				) }

				<DataForm
					data={ formData }
					fields={ allFields }
					form={ formLayout }
					onChange={ ( edits ) => setFormData( ( prev ) => ( { ...prev, ...edits } ) ) }
				/>

				<div style={ { marginTop: '16px' } }>
					<Button variant="primary" onClick={ handleSave } isBusy={ saving } disabled={ saving }>
						{ saving ? __( 'Saving…', 'wppack-role-provisioning' ) : __( 'Save Settings', 'wppack-role-provisioning' ) }
					</Button>
				</div>
			</div>
		</Page>
	);
}
