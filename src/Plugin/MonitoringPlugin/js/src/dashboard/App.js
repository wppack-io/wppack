import { useState } from '@wordpress/element';
import { TabPanel } from '@wordpress/components';
import { Page } from '@wordpress/admin-ui';
import DashboardPage from './pages/DashboardPage';
import SettingsPage from './pages/SettingsPage';

export default function App() {
	const [ activeTab, setActiveTab ] = useState( 'dashboard' );

	const tabs = [
		{ name: 'dashboard', title: 'Dashboard' },
		{ name: 'settings', title: 'Settings' },
	];

	return (
		<Page
			title="Infrastructure Monitoring"
			hasPadding
			actions={
				<TabPanel
					tabs={ tabs }
					onSelect={ setActiveTab }
					initialTabName={ activeTab }
				>
					{ () => null }
				</TabPanel>
			}
		>
			{ activeTab === 'dashboard' && <DashboardPage /> }
			{ activeTab === 'settings' && <SettingsPage /> }
		</Page>
	);
}
