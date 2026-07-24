<?php
/**
 * Plugin Name:       123-SMS pour WooCommerce
 * Plugin URI:        https://www.123-sms.net/developpeurs-api-123-sms-pro-woocommerce.php
 * Description:       Notifications SMS de commande via 123-SMS.net — service français depuis 2002, crédits prépayés sans abonnement. SMS au marchand à chaque commande, SMS au client à la confirmation et à l'expédition.
 * Version:           1.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.2
 * Requires Plugins:  woocommerce
 * Author:            123-SMS.net (DRANER.com)
 * Author URI:        https://www.123-sms.net/
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       123-sms-for-woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Sms123_WooCommerce {

	const OPT = 'sms123_wc_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'settings' ) );
		// Compatibilite HPOS (stockage des commandes haute performance)
		add_action( 'before_woocommerce_init', function () {
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			}
		} );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'commande_confirmee' ), 10, 1 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'commande_expediee' ), 10, 1 );
	}

	/* ------------------------------------------------ reglages */

	public static function defauts() {
		return array(
			'email'          => '',
			'cleapi'         => '',
			'sender'         => '',
			'numero_admin'   => '',
			'sms_admin'      => '1',
			'sms_confirme'   => '0',
			'sms_expedie'    => '0',
			'tpl_admin'      => 'Nouvelle commande {numero} de {prenom} {nom} : {total} EUR',
			'tpl_confirme'   => '{boutique} : votre commande {numero} est confirmee. Merci !',
			'tpl_expedie'    => '{boutique} : votre commande {numero} a ete expediee.',
		);
	}

	public static function opt( $cle ) {
		$o = wp_parse_args( get_option( self::OPT, array() ), self::defauts() );
		return isset( $o[ $cle ] ) ? $o[ $cle ] : '';
	}

	public static function menu() {
		add_submenu_page(
			'woocommerce',
			__( 'SMS 123-SMS.net', '123-sms-for-woocommerce' ),
			__( 'SMS 123-SMS.net', '123-sms-for-woocommerce' ),
			'manage_woocommerce',
			'sms123-wc',
			array( __CLASS__, 'page_reglages' )
		);
	}

	public static function settings() {
		register_setting( 'sms123_wc', self::OPT, array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) );
	}

	public static function sanitize( $in ) {
		$out = array();
		foreach ( self::defauts() as $cle => $def ) {
			$val = isset( $in[ $cle ] ) ? $in[ $cle ] : '';
			$out[ $cle ] = in_array( $cle, array( 'sms_admin', 'sms_confirme', 'sms_expedie' ), true )
				? ( $val ? '1' : '0' )
				: sanitize_text_field( $val );
		}
		return $out;
	}

	public static function page_reglages() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
		$o = wp_parse_args( get_option( self::OPT, array() ), self::defauts() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'SMS 123-SMS.net pour WooCommerce', '123-sms-for-woocommerce' ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: %s: lien vers l'espace client 123-SMS.net */
					esc_html__( 'Identifiant et clé API : transmis à l\'inscription, rubrique API de %s.', '123-sms-for-woocommerce' ),
					'<a href="https://www.123-sms.net/" target="_blank" rel="noopener">123-SMS.net</a>'
				);
				?>
				<?php esc_html_e( 'Variables des modèles :', '123-sms-for-woocommerce' ); ?>
				<code>{numero}</code> <code>{prenom}</code> <code>{nom}</code> <code>{total}</code> <code>{boutique}</code>
			</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'sms123_wc' ); ?>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><?php esc_html_e( 'Identifiant 123-SMS', '123-sms-for-woocommerce' ); ?></th>
						<td><input type="text" name="<?php echo esc_attr( self::OPT ); ?>[email]" value="<?php echo esc_attr( $o['email'] ); ?>" class="regular-text" required></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Clé API', '123-sms-for-woocommerce' ); ?></th>
						<td><input type="password" name="<?php echo esc_attr( self::OPT ); ?>[cleapi]" value="<?php echo esc_attr( $o['cleapi'] ); ?>" class="regular-text" autocomplete="new-password" required></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Sender-ID (optionnel)', '123-sms-for-woocommerce' ); ?></th>
						<td><input type="text" name="<?php echo esc_attr( self::OPT ); ?>[sender]" value="<?php echo esc_attr( $o['sender'] ); ?>" class="regular-text" maxlength="11">
						<p class="description"><?php esc_html_e( 'Nom d\'expéditeur personnalisé, à déclarer auprès de 123-SMS.', '123-sms-for-woocommerce' ); ?></p></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Numéro du marchand', '123-sms-for-woocommerce' ); ?></th>
						<td><input type="text" name="<?php echo esc_attr( self::OPT ); ?>[numero_admin]" value="<?php echo esc_attr( $o['numero_admin'] ); ?>" class="regular-text" placeholder="0601020304"></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'SMS au marchand', '123-sms-for-woocommerce' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPT ); ?>[sms_admin]" value="1" <?php checked( $o['sms_admin'], '1' ); ?>> <?php esc_html_e( 'à chaque commande confirmée', '123-sms-for-woocommerce' ); ?></label><br>
						<input type="text" name="<?php echo esc_attr( self::OPT ); ?>[tpl_admin]" value="<?php echo esc_attr( $o['tpl_admin'] ); ?>" class="large-text"></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'SMS client — confirmation', '123-sms-for-woocommerce' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPT ); ?>[sms_confirme]" value="1" <?php checked( $o['sms_confirme'], '1' ); ?>> <?php esc_html_e( 'quand la commande passe « en cours »', '123-sms-for-woocommerce' ); ?></label><br>
						<input type="text" name="<?php echo esc_attr( self::OPT ); ?>[tpl_confirme]" value="<?php echo esc_attr( $o['tpl_confirme'] ); ?>" class="large-text"></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'SMS client — expédition', '123-sms-for-woocommerce' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( self::OPT ); ?>[sms_expedie]" value="1" <?php checked( $o['sms_expedie'], '1' ); ?>> <?php esc_html_e( 'quand la commande passe « terminée »', '123-sms-for-woocommerce' ); ?></label><br>
						<input type="text" name="<?php echo esc_attr( self::OPT ); ?>[tpl_expedie]" value="<?php echo esc_attr( $o['tpl_expedie'] ); ?>" class="large-text"></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/* ------------------------------------------------ envois */

	public static function commande_confirmee( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) { return; }
		if ( '1' === self::opt( 'sms_admin' ) && self::opt( 'numero_admin' ) ) {
			self::envoyer( self::opt( 'numero_admin' ), self::modele( 'tpl_admin', $order ), $order, __( 'marchand', '123-sms-for-woocommerce' ) );
		}
		if ( '1' === self::opt( 'sms_confirme' ) ) {
			self::sms_client( $order, 'tpl_confirme', __( 'confirmation', '123-sms-for-woocommerce' ) );
		}
	}

	public static function commande_expediee( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order && '1' === self::opt( 'sms_expedie' ) ) {
			self::sms_client( $order, 'tpl_expedie', __( 'expédition', '123-sms-for-woocommerce' ) );
		}
	}

	protected static function sms_client( $order, $tpl, $etape ) {
		$tel = $order->get_billing_phone();
		if ( $tel ) {
			/* translators: %s: etape de la commande (confirmation, expedition) */
			self::envoyer( $tel, self::modele( $tpl, $order ), $order, sprintf( __( 'client (%s)', '123-sms-for-woocommerce' ), $etape ) );
		}
	}

	protected static function modele( $cle, $order ) {
		return strtr( self::opt( $cle ), array(
			'{numero}'   => $order->get_order_number(),
			'{prenom}'   => $order->get_billing_first_name(),
			'{nom}'      => $order->get_billing_last_name(),
			'{total}'    => $order->get_total(),
			'{boutique}' => get_bloginfo( 'name' ),
		) );
	}

	/** Normalise un numero francais : 06/07 -> 336/337, sans espaces. */
	public static function normaliser( $tel ) {
		$tel = preg_replace( '/[^0-9+]/', '', (string) $tel );
		if ( 0 === strpos( $tel, '+' ) )  { $tel = substr( $tel, 1 ); }
		if ( 0 === strpos( $tel, '00' ) ) { $tel = substr( $tel, 2 ); }
		if ( 10 === strlen( $tel ) && '0' === $tel[0] ) { $tel = '33' . substr( $tel, 1 ); }
		return $tel;
	}

	/** Appel HTTPS de l'API 123-SMS.net et trace du code retour dans la commande. */
	public static function envoyer( $numero, $message, $order = null, $contexte = '' ) {
		$reponse = wp_remote_post( 'https://www.123-sms.net/http.php', array(
			'timeout' => 15,
			'body'    => array(
				'email'   => self::opt( 'email' ),
				'pass'    => self::opt( 'cleapi' ),
				'numero'  => self::normaliser( $numero ),
				'message' => $message,
				'sender'  => self::opt( 'sender' ),
			),
		) );
		$code = is_wp_error( $reponse ) ? $reponse->get_error_message()
			: trim( wp_remote_retrieve_body( $reponse ) );
		if ( $order ) {
			$order->add_order_note( sprintf(
				/* translators: 1: contexte (marchand, client), 2: code retour API, 3: mention (envoye) */
				__( '123-SMS %1$s : code retour %2$s %3$s', '123-sms-for-woocommerce' ),
				$contexte, $code, '80' === $code ? __( '(envoyé)', '123-sms-for-woocommerce' ) : ''
			) );
		}
		return $code;
	}
}

Sms123_WooCommerce::init();
