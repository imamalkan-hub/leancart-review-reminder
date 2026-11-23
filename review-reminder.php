<?php
/**
 * Plugin Name: LeanCart Review Reminder
 * Plugin URI:  https://epoxygaragefloorlab.com/
 * Description: Sends a single reminder email a set number of days after an order is completed. Generates more reviews and social proof for your product.
 * Version:     1.0.0
 * Author:      LeanCart
 * Author URI:  https://leancart.dev
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: review-reminder
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'LEANCART_REVIEW_REMINDER_DEMO_SITE' ) ) {
	define( 'LEANCART_REVIEW_REMINDER_DEMO_SITE', 'https://epoxygaragefloorlab.com' );
}

require_once __DIR__ . '/includes/class-leancart-framework.php';

final class LeanCart_Review_Reminder {

	const SLUG = 'review-reminder';

	public static function init() {
		LeanCart_Framework::boot( array(
			'slug'    => self::SLUG,
			'name'    => 'LeanCart Review Reminder',
			'version' => '1.0.0',
			'file'    => __FILE__,
			'site'    => LEANCART_REVIEW_REMINDER_DEMO_SITE,
		) );

		// WooCommerce may load after these plugins; defer the check to runtime.
		add_action( 'admin_notices', array( __CLASS__, 'wc_notice' ) );

		load_plugin_textdomain( 'review-reminder', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		add_action( 'leancart_' . self::SLUG . '_settings_fields', array( __CLASS__, 'fields' ) );

		// WooCommerce may load after this plugin; hooks are registered once WC is ready.
		if ( LeanCart_Framework::is_wc() ) {
			self::hooks();
		} else {
			add_action( 'woocommerce_loaded', array( __CLASS__, 'hooks' ) );
		}
	}

	public static function wc_notice() {
		if ( LeanCart_Framework::is_wc() ) { return; }
		echo '<div class="notice notice-warning"><p><strong>' . esc_html( 'LeanCart Review Reminder' ) . '</strong>: ';
		esc_html_e( 'This plugin requires WooCommerce to be active.', 'review-reminder' );
		echo '</p></div>';
	}

	public static function fields() {
		LeanCart_Framework::field_text( 'days', __('Reminder day', 'review-reminder'), __('How many days after the order is completed?', 'review-reminder'), 'number' );
		LeanCart_Framework::field_text( 'subject', __('Email subject', 'review-reminder'), __('E.g.: Would you like to review our product?', 'review-reminder'), 'text' );
	}


	public static function hooks() {
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'schedule' ) );
		add_action( 'lc_review_reminder', array( __CLASS__, 'send' ) );
	}

	public static function schedule( $order_id ) {
		$days = max( 1, (int) LeanCart_Framework::setting( 'days', 7 ) );
		wp_schedule_single_event( time() + $days * DAY_IN_SECONDS, 'lc_review_reminder', array( $order_id ) );
	}

	public static function send( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->get_billing_email() ) { return; }
		$links = array();
		foreach ( $order->get_items() as $item ) {
			$p = $item->get_product();
			if ( $p ) { $links[] = $p->get_name() . ' — ' . get_permalink( $p->get_id() ) . '#review'; }
		}
		if ( ! $links ) { return; }
		$subject = LeanCart_Framework::setting( 'subject', __( 'Would you like to review our product?', 'review-reminder' ) );
		$message = sprintf( __( "Hello,\n\nBy reviewing the products you purchased, you can help our other customers:\n\n%s\n\nThank you.", 'review-reminder' ), implode( "\n", $links ) );
		wp_mail( $order->get_billing_email(), $subject, $message );
		LeanCart_Framework::log( 'Review reminder sent for order ' . $order_id );
	}


}

LeanCart_Review_Reminder::init();

