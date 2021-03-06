<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Free Shipping Method.
 *
 * A simple shipping method for free shipping.
 *
 * @class   WC_Shipping_Free_Shipping
 * @version 2.6.0
 * @package WooCommerce/Classes/Shipping
 * @author  WooThemes
 */
class WC_Shipping_Free_Shipping extends WC_Shipping_Method {

	/** @var float Min amount to be valid */
	public $min_amount = 0;

	/** @var string Requires option */
	public $requires   = '';

	/**
	 * Constructor.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id 			         = 'free_shipping';
		$this->instance_id 			 = absint( $instance_id );
		$this->method_title          = __( 'Free Shipping', 'woocommerce' );
		$this->method_description    = __( 'Free Shipping is a special method which can be triggered with coupons and minimum spends.', 'woocommerce' );
		$this->supports              = array(
			'shipping-zones',
			'instance-settings'
		);
		$this->title 		         = $this->get_option( 'title' );
		$this->min_amount 	         = $this->get_option( 'min_amount', 0 );
		$this->requires		         = $this->get_option( 'requires' );

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Get setting form fields for instances of this shipping method within zones.
	 * @return array
	 */
	public function get_instance_form_fields() {
		return array(
			'title' => array(
				'title' 		=> __( 'Title', 'woocommerce' ),
				'type' 			=> 'text',
				'description' 	=> __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
				'default'		=> $this->method_title,
				'desc_tip'		=> true,
			),
			'requires' => array(
				'title' 		=> __( 'Free Shipping Requires...', 'woocommerce' ),
				'type' 			=> 'select',
				'class'         => 'wc-enhanced-select',
				'default' 		=> '',
				'options'		=> array(
					'' 				=> __( 'N/A', 'woocommerce' ),
					'coupon'		=> __( 'A valid free shipping coupon', 'woocommerce' ),
					'min_amount' 	=> __( 'A minimum order amount', 'woocommerce' ),
					'either' 		=> __( 'A minimum order amount OR a coupon', 'woocommerce' ),
					'both' 			=> __( 'A minimum order amount AND a coupon', 'woocommerce' ),
				)
			),
			'min_amount' => array(
				'title' 		=> __( 'Minimum Order Amount', 'woocommerce' ),
				'type' 			=> 'price',
				'placeholder'	=> wc_format_localized_price( 0 ),
				'description' 	=> __( 'Users will need to spend this amount to get free shipping (if enabled above).', 'woocommerce' ),
				'default' 		=> '0',
				'desc_tip'		=> true
			)
		);

		wc_enqueue_js( "
			jQuery( function( $ ) {
				$('#woocommerce_free_shipping_requires').change(function(){
					if ( $(this).val() === 'coupon' || $(this).val() === '' ) {
						$('#woocommerce_free_shipping_min_amount').closest('tr').hide();
					} else {
						$('#woocommerce_free_shipping_min_amount').closest('tr').show();
					}
				}).change();
			});
		" );
	}

	/**
	 * See if free shipping is available based on the package and cart.
	 * @param array $package
	 * @return bool
	 */
	public function is_available( $package ) {
		$is_available       = false;
		$has_coupon         = false;
		$has_met_min_amount = false;

		if ( in_array( $this->requires, array( 'coupon', 'either', 'both' ) ) ) {
			if ( $coupons = WC()->cart->get_coupons() ) {
				foreach ( $coupons as $code => $coupon ) {
					if ( $coupon->is_valid() && $coupon->enable_free_shipping() ) {
						$has_coupon = true;
						break;
					}
				}
			}
		}

		if ( in_array( $this->requires, array( 'min_amount', 'either', 'both' ) ) && isset( WC()->cart->cart_contents_total ) ) {
			if ( WC()->cart->prices_include_tax ) {
				$total = WC()->cart->cart_contents_total + array_sum( WC()->cart->taxes );
			} else {
				$total = WC()->cart->cart_contents_total;
			}

			if ( $total >= $this->min_amount ) {
				$has_met_min_amount = true;
			}
		}

		switch ( $this->requires ) {
			case 'min_amount' :
				$is_available = $has_met_min_amount;
			break;
			case 'coupon' :
				$is_available = $has_coupon;
			break;
			case 'both' :
				$is_available = $has_met_min_amount && $has_coupon;
			break;
			case 'either' :
				$is_available = $has_met_min_amount || $has_coupon;
			break;
			default :
				$is_available = true;
			break;
		}

		return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', $is_available, $package );
	}

	/**
	 * Called to calculate shipping rates for this method. Rates can be added using the add_rate() method.
	 * @uses WC_Shipping_Method::add_rate()
	 */
	public function calculate_shipping( $package = array() ) {
		$this->add_rate( array(
			'id' 	 => $this->id . $this->instance_id,
			'label'  => $this->title,
			'cost' 	 => 0,
			'taxes'  => false
		) );
	}
}
