<?php
/*
Plugin Name: WC Cart Shipping
Plugin URI: http://wooexperts.com
Description: Woocommerce Cart based Shipping.
Author: Vikram S.
Version: 1.0
Author URI: http://wooexperts.com
License: GPLv3
*/

if (!defined('ABSPATH')) {
    exit;
    // Exit if accessed directly
}




    function Wc_cart_shipping_method_init()
    {

        if (!class_exists('WC_Cart_Shipping')) {

            class WC_Cart_Shipping extends WC_Shipping_Method
            {
                /**
                 * Constructor for your shipping class
                 *
                 * @access public
                 * @return void
                 */
                public function __construct()
                {
                    $this->id = 'wc_cart_shipping';
                    // Id for your shipping method. Should be uunique.
                    $this->method_title = __('WC Cart Shipping');
                    // Title shown in admin
                    $this->method_description = __('Calculate Shipping based on Cart total');
                    // Description shown in admin
                    $this->wc_cart_rates_option = '_wc_cart_rates';
                    $this->init();
                }

                /**
                 * Init your settings
                 *
                 * @access public
                 * @return void
                 */
                function init()
                {
                    // Load the settings API
                    $this->init_form_fields();
                    // This is part of the settings API. Override the method to add your own settings
                    $this->init_settings();
                    // This is part of the settings API. Loads settings you previously init.
                    $this->title = $this->get_option('shipping_lable');
                    $this->cost = $this->get_option('cost');
                    $this->enabled = $this->settings['enabled'];
                    $this->get_wc_cart_rates();
                    // Save settings in admin if you have any defined
                    add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
                    add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_wc_cart_shipping_rates'));

                }


                function init_form_fields()
                {
                    $this->form_fields = array(
                        'enabled' => array( 'title' => __('Enable/Disable', 'woocommerce'),'type' => 'checkbox','label' => __('Enable this shipping method', 'woocommerce'),'default' => 'yes'),
                        'shipping_lable' => array( 'title' => __('Shipping Label', 'woocommerce'),'type' => 'text','description' => __('Display shipping label at cart and checkout page ', 'woocommerce'),'default' => __('Shipping Cost', 'woocommerce'),'desc_tip' => true),
                        'add_wc_cart_rates' => array('type' => 'add_wc_cart_rates'),
                    );
                }

                function get_wc_cart_total(){

                    $cart_subtotal = WC()->cart->cart_contents_total + WC()->cart->get_taxes_total(false,false);
                    return $cart_subtotal;
                }

                /**
                 * calculate_shipping function.
                 *
                 * @access public
                 * @param mixed $package
                 * @return void
                 */

                function wc_sort_by($data){

                    $price = array();
                    foreach ($data as $key => $row)
                    {
                        $price[$key] = $row['value'];
                    }
                    array_multisort($price,SORT_DESC,$data);
                    return $data;
                }

                public function calculate_shipping($package)
                {
                    $shipping_cost = 0;
                    if(isset($this->wc_cart_rates) && count($this->wc_cart_rates['condition'])>0){
                        $data = array();
                        foreach($this->wc_cart_rates['condition'] as $key=>$val){
                            $data[]=array('condition'=>$this->wc_cart_rates['condition'][$key],'value'=>$this->wc_cart_rates['value'][$key],'cart_total'=>$this->wc_cart_rates['cart_total'][$key]);
                        }
                        $data = $this->wc_sort_by($data);

                        $wc_cart_total = $this->get_wc_cart_total();
                        foreach($data as $d_key => $d_val){

                            if($d_val['condition']==1){
                                $condition = '<=';
                            }
                            elseif($d_val['condition']==2){
                                $condition = '>=';
                            }

                            switch($condition){

                                case "<=":
                                    if($wc_cart_total<=$d_val['value']){
                                        $shipping_cost = $d_val['cart_total'];
                                    }
                                break;
                                case ">=":
                                    if($wc_cart_total>=$d_val['value']){
                                        $shipping_cost = $d_val['cart_total'];
                                    }
                                break;

                            }

                        }

                    }

                    $rate = array('id' => $this->id, 'label' => $this->title, 'cost' => $shipping_cost, 'calc_tax' => 'per_item');
                    // Register the rate
                    $this->add_rate($rate);
                }


                function get_wc_cart_rates()
                {
                    $this->wc_cart_rates = array_filter((array)get_option($this->wc_cart_rates_option));
                }


                function process_wc_cart_shipping_rates()
                {
                    $wc_cart_rates = array();

                    if (!empty($_POST['default_wc_cart_condition'])) {
                        $counter = 0;
                        foreach ($_POST['default_wc_cart_condition'] as $key => $row) {
                            $wc_cart_rates['condition'][$key] = wc_clean(stripslashes($_POST['default_wc_cart_condition'][$key]));
                            $wc_cart_rates['value'][$key] = wc_format_decimal($_POST['default_wc_cart_condition_value'][$key]);
                            $wc_cart_rates['cart_total'][$key] = wc_format_decimal($_POST['default_wc_cart_cost'][$key]);
                            $counter++;
                        }

                    }

                    update_option($this->wc_cart_rates_option, $wc_cart_rates);
                    $this->get_wc_cart_rates();
                }


                function generate_add_wc_cart_rates_html()
                {
                    ob_start();
                    include('views/html-wc-cart-rates.php');
                    return ob_get_clean();
                }

            }

        }

    }

    add_action('woocommerce_shipping_init', 'Wc_cart_shipping_method_init');

    function wc_cart_shipping_method($methods)
    {
        $methods[] = 'WC_Cart_Shipping';
        return $methods;
    }

    add_filter('woocommerce_shipping_methods', 'wc_cart_shipping_method');


?>