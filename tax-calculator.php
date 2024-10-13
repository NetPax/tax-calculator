<?php
/*
Plugin Name: Tax calculator
Description: Object-Oriented Plugin for calculating gross amount and tax on a product.
Version: 1.0
Author: Wojciech Stawarz, w.stawarz@wp.pl
*/

if ( !defined( 'ABSPATH' ) ) exit;

class TaxCalculatorPlugin {

	public function __construct() {
		add_action( 'init', array( $this, 'register_cpt' ) );
		add_shortcode( 'tax_calculator', array( $this, 'display_form' ) );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		add_filter( 'manage_tax_calculations_posts_columns', [ $this, 'register_custom_columns' ] );
		add_action( 'manage_tax_calculations_posts_custom_column', [ $this, 'display_custom_columns' ], 10, 2 );
	}

	public function enqueue_scripts() {
		wp_enqueue_style( 'tax-calculator-style', plugins_url( 'index.css', __FILE__ ) );
		wp_enqueue_script( 'tax-calculator-validation', plugins_url( 'index.js', __FILE__ ), array( 'jquery' ), null, true );
	}

	public function register_cpt() {
		$labels = array(
			'name'           => 'Tax Calculations',
			'singular_name'  => 'Tax Calculation',
			'menu_name'      => 'Tax Calculations',
			'name_admin_bar' => 'Tax Calculation',
		);
		$args = array(
			'labels'   => $labels,
			'public'   => false,
			'show_ui'  => true,
			'supports' => array( 'title', 'custom-fields' ),
		);
		register_post_type( 'tax_calculations', $args );
	}

	public function display_form() {
		ob_start();
		?>

		<form class="tax-calculator-form" id="tax-calculator-form" method="post" novalidate>
			<?php wp_nonce_field( 'tax_calculator_nonce', 'tax_calculator_nonce_field' ); ?>

			<label class="tax-calculator-form__label">Nazwa produktu:</label>
			<input class="tax-calculator-form__input tax-calculator-form__input--text" type="text" name="product_name" required>

			<label class="tax-calculator-form__label">Kwota netto:</label>
			<input class="tax-calculator-form__input tax-calculator-form__input--number" type="number" name="net_amount" step="0.01" required>

			<label class="tax-calculator-form__label">Waluta:</label>
			<input class="tax-calculator-form__input tax-calculator-form__input--text" type="text" value="PLN" disabled>

			<label class="tax-calculator-form__label">Stawka VAT:</label>
			<select class="tax-calculator-form__input tax-calculator-form__input--select" name="vat_rate" required>
				<option value="">--- wybierz ---</option>
				<option value="23">23%</option>
				<option value="22">22%</option>
				<option value="8">8%</option>
				<option value="7">7%</option>
				<option value="5">5%</option>
				<option value="3">3%</option>
				<option value="0">0%</option>
				<option value="zw">zw.</option>
				<option value="np">np.</option>
				<option value="oo">o.o.</option>
			</select>

			<input class="tax-calculator-form__submit" type="submit" name="calculate" value="Oblicz">
		</form>

		<?php
		if ( isset( $_POST['calculate'] ) ) {
			echo "<div class='tax-calculator-form__result'>";
				if ( !isset( $_POST['tax_calculator_nonce_field'] ) || !wp_verify_nonce( $_POST['tax_calculator_nonce_field'], 'tax_calculator_nonce' ) ) {
					wp_die( 'Błąd: Nieautoryzowany dostęp!' );
				}

				$product_name = sanitize_text_field( $_POST['product_name'] );
				$net_amount = filter_var( $_POST['net_amount'], FILTER_VALIDATE_FLOAT );
				$vat_rate = sanitize_text_field( $_POST['vat_rate'] );

				if ( $product_name === '' ) {
					echo "<p>Błąd: Wprowadź nazwę produktu.</p>";

					return ob_get_clean();
				}

				if ( $net_amount === false ) {
					echo "<p>Błąd: Wprowadź poprawną kwotę netto.</p>";

					return ob_get_clean();
				}

				if ( !is_numeric( $vat_rate ) && !in_array( $vat_rate, [ 'zw', 'np', 'oo' ] ) ) {
					echo "<p>Błąd: Wybierz poprawną stawkę VAT.</p>";

					return ob_get_clean();
				}

				$tax_amount = is_numeric( $vat_rate ) ? $net_amount * ( $vat_rate / 100 ) : 0;
				$gross_amount = $net_amount + $tax_amount;
				$product_name_safe = esc_html( $product_name );

				echo "<p>Cena produktu <strong>{$product_name_safe}</strong>, wynosi: <strong>{$gross_amount}</strong> zł brutto, kwota podatku to <strong>{$tax_amount}</strong> zł.</p>";
				$this->save_to_cpt( $product_name, $net_amount, $vat_rate, $gross_amount, $tax_amount );
			echo "</div>";
			echo'<script>
			    if ( window.history.replaceState ) {
				    window.history.replaceState( null, null, window.location.href );
			    }
			</script>';
		}

		return ob_get_clean();
	}

	private function save_to_cpt(
		$product_name,
		$net_amount,
		$vat_rate,
		$gross_amount,
		$tax_amount
	) {
		$post_id = wp_insert_post( array(
			'post_type'   => 'tax_calculations',
			'post_title'  => $product_name,
			'post_status' => 'publish',
			'meta_input'  => array(
				'product_name'   => $product_name,
				'net_amount'     => $net_amount,
				'vat_rate'       => $vat_rate,
				'gross_amount'   => $gross_amount,
				'tax_amount'     => $tax_amount,
				'ip_address'     => $_SERVER['REMOTE_ADDR'],
				'date_submitted' => current_time( 'mysql' )
			)
		) );
	}

	public function register_custom_columns( $columns ) {
		$columns['product_name'] = 'Nazwa produktu';
		$columns['net_amount'] = 'Kwota netto';
		$columns['vat_rate'] = 'Stawka VAT';
		$columns['tax_amount'] = 'Kwota podatku';
		$columns['gross_amount'] = 'Kwota brutto';
		$columns['ip_address'] = 'IP użytkownika';
		$columns['date_submitted'] = 'Data wypełnienia';

		return $columns;
	}

	public function display_custom_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'product_name':
				echo esc_html( get_post_meta( $post_id, 'product_name', true ) );
				break;
			case 'net_amount':
				echo esc_html( number_format( (float)get_post_meta( $post_id, 'net_amount', true ), 2 ) ) . ' zł';
				break;
			case 'vat_rate':
				echo esc_html( get_post_meta( $post_id, 'vat_rate', true ) ) . '%';
				break;
			case 'tax_amount':
				echo esc_html( number_format( (float)get_post_meta( $post_id, 'tax_amount', true ), 2 ) ) . ' zł';
				break;
			case 'gross_amount':
				echo esc_html( number_format( (float)get_post_meta( $post_id, 'gross_amount', true ), 2 ) ) . ' zł';
				break;
			case 'ip_address':
				echo esc_html( get_post_meta( $post_id, 'ip_address', true ) );
				break;
			case 'date_submitted':
				echo esc_html( get_post_meta( $post_id, 'date_submitted', true ) );
				break;
		}
	}
}

new TaxCalculatorPlugin();
