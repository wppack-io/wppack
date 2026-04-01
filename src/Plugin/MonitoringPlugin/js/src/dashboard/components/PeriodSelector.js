import { ButtonGroup, Button } from '@wordpress/components';

const PERIODS = [
	{ value: 1, label: '1h' },
	{ value: 3, label: '3h' },
	{ value: 6, label: '6h' },
	{ value: 12, label: '12h' },
	{ value: 24, label: '24h' },
];

export default function PeriodSelector( { value, onChange } ) {
	return (
		<ButtonGroup>
			{ PERIODS.map( ( p ) => (
				<Button
					key={ p.value }
					variant={ value === p.value ? 'primary' : 'secondary' }
					onClick={ () => onChange( p.value ) }
					size="compact"
				>
					{ p.label }
				</Button>
			) ) }
		</ButtonGroup>
	);
}
