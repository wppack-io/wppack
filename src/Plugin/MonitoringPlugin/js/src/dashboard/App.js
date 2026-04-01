import { useState } from '@wordpress/element';
import { TabPanel } from '@wordpress/components';
import { Page } from '@wordpress/admin-ui';
import { __ } from '@wordpress/i18n';
import DashboardPage from './pages/DashboardPage';
import SettingsPage from './pages/SettingsPage';

export default function App() {
	const [ activeTab, setActiveTab ] = useState( 'dashboard' );

	const tabs = [
		{ name: 'dashboard', title: __( 'Dashboard', 'wppack-monitoring' ) },
		{ name: 'settings', title: __( 'Settings', 'wppack-monitoring' ) },
	];

	return (
		<Page
			title={ __( 'Infrastructure Monitoring', 'wppack-monitoring' ) }
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
