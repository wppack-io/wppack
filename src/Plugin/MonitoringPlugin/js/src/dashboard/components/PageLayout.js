/**
 * Lightweight Page layout inspired by @wordpress/admin-ui Page component.
 * Self-contained to avoid dependency on wp-admin-ui script handle
 * which may not be registered in WordPress < 6.10.
 */
export default function PageLayout( { title, actions, children } ) {
	return (
		<div className="wpp-monitoring-page">
			<header className="wpp-monitoring-page__header">
				<div className="wpp-monitoring-page__header-left">
					{ title && (
						<h2 className="wpp-monitoring-page__title">
							{ title }
						</h2>
					) }
				</div>
				{ actions && (
					<div className="wpp-monitoring-page__header-actions">
						{ actions }
					</div>
				) }
			</header>
			<div className="wpp-monitoring-page__content">{ children }</div>
		</div>
	);
}
