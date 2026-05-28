<?php
/**
 * SMS history listing page.
 *
 * @package Sendsmsro\ForWooCommerce
 */

namespace Sendsmsro\ForWooCommerce\Admin\Pages;

use Sendsmsro\ForWooCommerce\Admin\HistoryTable;
use Sendsmsro\ForWooCommerce\Admin\Menu;
use Sendsmsro\ForWooCommerce\Storage\HistoryRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the WP_List_Table wrapping {prefix}sendsmsro_history.
 */
final class HistoryPage {

	/**
	 * @var HistoryRepository
	 */
	private $history;

	/**
	 * @param HistoryRepository $history Shared history repository.
	 */
	public function __construct( HistoryRepository $history ) {
		$this->history = $history;
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render(): void {
		// WP_List_Table is loaded lazily.
		if ( ! class_exists( '\WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}

		$table = new HistoryTable( $this->history );
		$table->prepare_items();
		?>
		<div class="wrap sendsmsro-page">
			<h1><?php esc_html_e( 'SendSMS — History', 'sendsms-for-woocommerce' ); ?></h1>
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( Menu::HISTORY_SLUG ); ?>" />
				<?php
				$table->search_box( __( 'Search', 'sendsms-for-woocommerce' ), 'sendsmsro-history-search' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}
}
