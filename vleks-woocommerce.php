<?php
/**
 * Plugin Name: Vleks WooCommerce
 * Plugin URI:  https://www.vleks.com/plugins/woocommerce/
 * Description: Share your WooCommerce products with <strong>Vleks.com</strong>.
 * Version:     1.0.1
 * Author:      Vleks.com
 * Author URI:  https://www.vleks.com/
 *
 * Text Domain: woocommerce-vleks-integration
 * Domain path: /languages/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

if (!class_exists ('WC_Vleks')) {

    class WC_Vleks
    {
        /**
         * Construct the plugin.
         */
        public function __construct ()
        {
            add_action ('plugins_loaded', array ($this, 'load'));
            add_action ('init', array ($this, 'init'), 0);
        }

        /**
         * Initialize the plugin.
         */
        public function load ()
        {
            # Checks if WooCommerce is installed
            if (class_exists ('WC_Integration')) {

                # Include our integration class
                include_once 'includes/class-wc-vleks-integration.php';
                include_once 'includes/class-weight.php';
                include_once 'includes/class-dimension.php';

                # Register our integration
                add_filter ('woocommerce_integrations', array ($this, 'add_integration'));
            }
        }

        public function init ()
        {
            $locale = is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
            $locale = apply_filters( 'plugin_locale', $locale, 'woocommerce-vleks-integration' );

            unload_textdomain( 'woocommerce-vleks-integration' );
            load_textdomain( 'woocommerce-vleks-integration', realpath (dirname (__FILE__)).  '/languages/' . $locale . '.mo');
        }

        /**
         * Add a new integration to WooCommerce
         */
        public function add_integration ($integrations)
        {
            $integrations[] = 'WC_Vleks_Integration';
            return $integrations;
        }
    }

    $WC_Vleks = new WC_Vleks (__FILE__);
}
