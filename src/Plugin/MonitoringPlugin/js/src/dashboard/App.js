import { TabPanel } from '@wordpress/components';
import DashboardPage from './pages/DashboardPage';
import SettingsPage from './pages/SettingsPage';

export default function App() {
	const tabs = [
		{ name: 'dashboard', title: 'Dashboard', Component: DashboardPage },
		{ name: 'settings', title: 'Settings', Component: SettingsPage },
	];

	return (
		<div className="wpp-monitoring">
			<TabPanel tabs={ tabs }>
				{ ( tab ) => {
					const Tab = tabs.find(
						( t ) => t.name === tab.name
					)?.Component;
					return Tab ? <Tab /> : null;
				} }
			</TabPanel>
		</div>
	);
}
