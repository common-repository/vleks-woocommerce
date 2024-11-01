<?php

/**
 * Vleks integration into WooCommerce
 *
 * @package  WC_Integration_Vleks
 * @category Integration
 * @author   Vleks.com
 */

if (!class_exists ('WC_Vleks_Integration')) {

    class WC_Vleks_Integration extends WC_Integration
    {
        const LANG_NAMESPACE = 'woocommerce-vleks-integration';
        const EXPORT_DIR     = 'vleks_export';
        const EXPORT_NAME    = 'products.xml';

        /**
         * Weight object
         *
         * @var Vleks_Weight
         */
        protected $weight;

        /**
         * Dimension object
         *
         * @var Vleks_Dimension
         */
        protected $dimension;

        /**
         * Init and hook in the integration.
         */
        public function __construct ()
        {
            global $woocommerce;

            $this->id                 = 'vleks-integration';
            $this->method_title       = __('Vleks.com', self::LANG_NAMESPACE);
            $this->method_description = __('The official Vleks.com WooCommerce integration.', self::LANG_NAMESPACE) . '<br /><br />' .
                                        __('File location', self::LANG_NAMESPACE) . ': <a href="' . content_url (self::EXPORT_DIR . '/' . self::EXPORT_NAME) . '" target="new">' . content_url (self::EXPORT_DIR . '/' . self::EXPORT_NAME) . '</a><br />' .
                                        __('Next export planned', self::LANG_NAMESPACE) . ': <u>' . date ('Y-m-d H:i:s', get_option ('vleks_export_scheduled')) . '</u>';

            $this->weight    = new Vleks_Weight;
            $this->dimension = new Vleks_Dimension;

            # Load the settings
            $this->init_form_fields ();
            $this->init_settings ();

            # Define user set variables
            $this->stock_marge       = $this->get_option ('stock_marge');
            $this->merchant_id       = $this->get_option ('merchant_id');
            $this->stock_location_id = $this->get_option ('stock_location_id');
            $this->export_rate       = $this->get_option ('export_rate');
            $this->do_export         = $this->get_option ('do_export');
            $this->min_delivery_time = $this->get_option ('min_delivery_time');
            $this->max_delivery_time = $this->get_option ('max_delivery_time');

            # Actions
            add_action ('woocommerce_update_options_integration_' . $this->id, array ($this, 'process_admin_options'));
            add_action ('woocommerce_product_options_inventory_product_data', array ($this, 'extend_product_settings'));
            add_action ('woocommerce_process_product_meta', array ($this, 'save_product_settings'));
            add_action ('woocommerce_vleks_integration_export', array ($this, 'export'));
            add_action ('init', array ($this, 'add_brand_taxonomy'));

            # Schedules
            $this->schedule_event ();
        }

        /**
         * Add the brand taxonomy.
         */
        public function add_brand_taxonomy ()
        {
            if (!taxonomy_exists ('product_brand')) {
                register_taxonomy (
                    'product_brand',
                    'product',
                    array (
                        'labels' => array (
                            'name'          => __('Product brands', self::LANG_NAMESPACE),
                            'singular_name' => __('Brand', self::LANG_NAMESPACE),
                            'menu_name'     => __('Brands', self::LANG_NAMESPACE),
                            'all_items'     => __('All brands', self::LANG_NAMESPACE),
                            'edit_item'     => __('Edit brand', self::LANG_NAMESPACE),
                            'view_item'     => __('View brand', self::LANG_NAMESPACE),
                            'update_item'   => __('Update brand', self::LANG_NAMESPACE),
                            'add_new_item'  => __('Add new brand', self::LANG_NAMESPACE),
                            'new_item_name' => __('New brand name', self::LANG_NAMESPACE)
                        ),
                        'rewrite'            => FALSE,
                        'public'             => FALSE,
                        'publicly_queryable' => FALSE,
                        'show_ui'            => TRUE,
                        'show_in_menu'       => TRUE,
                        'hierarchical'       => FALSE
                    )
                );
            }
        }

        /**
         * Extend product settings.
         */
        public function extend_product_settings ()
        {
            global $woocommerce, $post;

            echo '<div class="options_group">';

            woocommerce_wp_text_input (array (
                'id'          => '_ean',
                'label'       => __('EAN', self::LANG_NAMESPACE),
                'desc_tip'    => TRUE,
                'description' => __('EAN refers to a European Article Number, a semi-unique identifier for each distinct product that can be purchased.', self::LANG_NAMESPACE)
            ));

            $value = get_post_meta ($post->ID, '_condition', TRUE);

            if (empty ($value)) {
                $value = 'NEW';
            }

            woocommerce_wp_select (array (
                'id'      => '_condition',
                'label'   => __('Condition', self::LANG_NAMESPACE),
                'options' => array (
                    'NEW'        => __('New', self::LANG_NAMESPACE),
                    'AS_NEW'     => __('As new', self::LANG_NAMESPACE),
                    'GOOD'       => __('Good', self::LANG_NAMESPACE),
                    'REASONABLE' => __('Reasonable', self::LANG_NAMESPACE),
                    'MODERATE'   => __('Moderate', self::LANG_NAMESPACE)
                ),
                'value'  => $value
            ));

            echo '</div>';
        }

        /**
         * Save product settings.
         */
        public function save_product_settings ($post_id)
        {
            if (!empty ($_POST['_ean']))
                update_post_meta ($post_id, '_ean', esc_attr ($_POST['_ean']));

            $condition = (!empty ($_POST['_condition']) && in_array ($_POST['_condition'], array ('NEW', 'AS_NEW', 'GOOD', 'REASONABLE', 'MODERATE'))) ? $_POST['_condition'] : 'NEW';
            update_post_meta ($post_id, '_condition', esc_attr ($condition));
        }

        /**
         * Initialize integration settings form fields.
         */
        public function init_form_fields ()
        {
            $this->form_fields = array (
                'stock_marge' => array (
                    'title'       => __('Stock marge', self::LANG_NAMESPACE),
                    'description' => __('Allow some marge in the stock quantity on Vleks.com', self::LANG_NAMESPACE),
                    'type'        => 'text',
                    'default'     => '0'
                ),
                'merchant_id' => array (
                    'title'       => __('Merchant ID', self::LANG_NAMESPACE),
                    'description' => __('Enter your Vleks.com Merchant ID.', self::LANG_NAMESPACE),
                    'type'        => 'string',
                    'default'     => ''
                ),
                'stock_location_id' => array (
                    'title'       => __('Stock Location ID', self::LANG_NAMESPACE),
                    'description' => __('Enter your Vleks.com Stock Location ID.', self::LANG_NAMESPACE),
                    'type'        => 'string',
                    'default'     => ''
                ),
                'do_export' => array (
                    'type'        => 'checkbox',
                    'label'       => __('Enable feed generation', self::LANG_NAMESPACE),
                    'default'     => 'off'
                ),
                'export_rate' => array (
                    'title'       => __('Export rate', self::LANG_NAMESPACE),
                    'description' => __('How often is the feed generated.', self::LANG_NAMESPACE),
                    'type'        => 'select',
                    'default'     => 'day',
                    'options'     => array (
                        '10-minutes' => __('Every ten minutes', self::LANG_NAMESPACE),
                        '15-minutes' => __('Every fifteen minutes', self::LANG_NAMESPACE),
                        '30-minutes' => __('Every half an hour', self::LANG_NAMESPACE),
                        '45-minutes' => __('Every fourty-five minutes', self::LANG_NAMESPACE),
                        'hour'       => __('Every hour', self::LANG_NAMESPACE),
                        '6-hours'    => __('Every six hours', self::LANG_NAMESPACE),
                        '12-hours'   => __('Every twelve hours', self::LANG_NAMESPACE),
                        'day'        => __('Once a day', self::LANG_NAMESPACE),
                        'week'       => __('Once a week', self::LANG_NAMESPACE),
                        'month'      => __('Once a month', self::LANG_NAMESPACE),
                        'year'       => __('Once a year', self::LANG_NAMESPACE)
                    )
                ),
                'min_delivery_time' => array (
                    'title'       => __('Minimal delivery time', self::LANG_NAMESPACE),
                    'description' => __('In days', self::LANG_NAMESPACE),
                    'type'        => 'text',
                    'default'     => '1'
                ),
                'max_delivery_time' => array (
                    'title'       => __('Maximal delivery time', self::LANG_NAMESPACE),
                    'description' => __('In days', self::LANG_NAMESPACE),
                    'type'        => 'text',
                    'default'     => '2'
                )
            );
        }

        /**
         * Validate the stock marge option.
         */
        public function validate_stock_marge_field ($key, $value)
        {
            if (!is_numeric ($value)) {
                WC_Admin_Settings::add_error (esc_html__('Be sure to enter a numeric value in the stock marge field.', self::LANG_NAMESPACE));
            }

            return abs (intval ($value));
        }

        /**
         * Validate the Merchant ID.
         */
        public function validate_merchant_id_field ($key, $value)
        {
            if (empty ($value)) {
                WC_Admin_Settings::add_error (esc_html__('The Merchant ID really should not be empty.', self::LANG_NAMESPACE));
            }

            return $value;
        }

        /**
         * Validate the Stock Location ID.
         */
        public function validate_stock_location_id_field ($key, $value)
        {
            if (empty ($value)) {
                WC_Admin_Settings::add_error (esc_html__('The Stock Location ID really should not be empty.', self::LANG_NAMESPACE));
            }

            return $value;
        }

        /**
         * Validate the Minimal delivery time.
         */
        public function validate_min_delivery_time_field ($key, $value)
        {
            if (!is_numeric ($value)) {
                WC_Admin_Settings::add_error (esc_html__('Please, use a numeric value.', self::LANG_NAMESPACE));
                return 1;
            }

            if (empty ($value) || 1 > abs (intval ($value))) {
                WC_Admin_Settings::add_error (esc_html__('The Minimal delivery time should not be empty.', self::LANG_NAMESPACE));
                return 1;
            }

            return abs (intval ($value));
        }

        /**
         * Validate the Maximal delivery time.
         */
        public function validate_max_delivery_time_field ($key, $value)
        {
            if (!is_numeric ($value)) {
                WC_Admin_Settings::add_error (esc_html__('Please, use a numeric value.', self::LANG_NAMESPACE));
                return 1;
            }

            if (empty ($value) || 1 > abs (intval ($value))) {
                WC_Admin_Settings::add_error (esc_html__('The Maximal delivery time should not be empty.', self::LANG_NAMESPACE));
                return 1;
            }

            if ($value < $this->get_option ('min_delivery_time'))
            {
                WC_Admin_Settings::add_error (esc_html__('The Maximal delivery time should not be less than the Minimal delivery time.', self::LANG_NAMESPACE));
                return $this->get_option ('min_delivery_time');
            }

            return abs (intval ($value));
        }

        /**
         * Schedule event.
         */
        public function schedule_event ()
        {
            if ('yes' === $this->do_export) {

                $last_scheduled = get_option ('vleks_export_scheduled', FALSE);

                if (FALSE === $last_scheduled) {
                    $last_scheduled = time ();
                }

                if (time () >= $last_scheduled) {
                    $schedule = $this->export_rate;

                    if (!preg_match ('/^\d/', $schedule)) {
                        $schedule = '1 ' . $schedule;
                    }

                    $schedule = preg_replace ('/-+/', ' ', $schedule);
                    $schedule = strtotime ('+' . $schedule, $last_scheduled);

                    update_option ('vleks_export_scheduled', $schedule);

                    $this->export ();
                }
            }
        }

        /**
         * Export data.
         */
        public function export ()
        {
            add_action ('wp_loaded', function () {

                $path = ABSPATH . '/wp-content/' . self::EXPORT_DIR . '/';

                wp_mkdir_p ($path);

                if (is_dir ($path)) {
                    $loop = new WP_Query (array (
                        'post_type'      => array ('product', 'product_variation'),
                        'post_status'    => array ('publish', 'future', 'private'),
                        'posts_per_page' => -1
                    ));

                    if ($loop->have_posts ()) {

                        $calculate_vat  = 'yes' !== get_option ('woocommerce_prices_include_tax', 'no');
                        $dimension_unit = get_option ('woocommerce_dimension_unit');
                        $weight_unit    = get_option ('woocommerce_weight_unit');

                        $doc  = new DOMDocument ('1.0', 'UTF-8');

                        $root = $doc->createElement ('VleksRequest');
                        $root = $doc->appendChild ($root);

                        $header = $doc->createElement ('Header');
                        $header->appendChild ($doc->createElement ('MerchantID', $this->merchant_id));
                        $header->appendChild ($doc->createElement ('Entity', 'Product'));
                        $header->appendChild ($doc->createElement ('Action', 'Update'));
                        $header = $root->appendChild ($header);

                        while ($loop->have_posts ()) {
                            $loop->the_post ();

                            $ID         = get_the_ID ();
                            $SKU        = get_post_meta ($ID, '_sku', TRUE);
                            $EAN        = get_post_meta ($ID, '_ean', TRUE);
                            $Categories = get_the_terms ($ID, 'product_cat');
                            $Brands     = get_the_terms ($ID, 'product_brand');

                            $_WC_Tax       = new WC_tax ();
                            $TaxRates      = $_WC_Tax->get_rates (get_post_meta ($ID, '_tax_class', TRUE));
                            $TaxPercentage = 0;

                            if (!empty ($TaxRates)) {
                                $TaxRate       = current ($TaxRates);
                                $TaxPercentage = intval ($TaxRate['rate']);
                            }

                            if (!empty ($SKU) && !empty ($EAN) && !empty ($Categories) && !empty ($Brands)) {
                                $Condition  = get_post_meta ($ID, '_condition', TRUE);
                                $SalePrice  = get_post_meta ($ID, '_regular_price', TRUE);
                                $OfferPrice = get_post_meta ($ID, '_sale_price', TRUE);
                                $Weight     = get_post_meta ($ID, '_weight', TRUE);
                                $Length     = get_post_meta ($ID, '_length', TRUE);
                                $Width      = get_post_meta ($ID, '_width', TRUE);
                                $Height     = get_post_meta ($ID, '_height', TRUE);

                                if (empty ($Condition)) {
                                    $Condition = 'NEW';
                                }

                                if (!empty ($OfferPrice)) {
                                    $FromDate = get_post_meta ($ID, '_sale_price_dates_from', TRUE);
                                    $ToDate   = get_post_meta ($ID, '_sale_price_dates_to', TRUE);

                                    if (!empty ($FromDate) && time () < $FromDate) {
                                        $OfferPrice = '';
                                    }

                                    if (!empty ($ToDate) && time () > $ToDate) {
                                        $OfferPrice = '';
                                    }
                                }

                                $product = $doc->createElement ('Product');
                                $product->appendChild ($doc->createElement ('Active', 'publish' === get_post_status () ? 'true' : 'false'));
                                $product->appendChild ($doc->createElement ('TaxPercentage', $TaxPercentage));
                                $product->appendChild ($doc->createElement ('Condition', $Condition));
                                $product->appendChild ($doc->createElement ('MinDeliveryTime', $this->min_delivery_time * 24));
                                $product->appendChild ($doc->createElement ('MaxDeliveryTime', $this->max_delivery_time * 24));

                                if (!empty ($SKU)) {
                                    $sku = $product->appendChild ($doc->createElement ('SKU'));
                                    $sku = $sku->appendChild ($doc->createCDATASection ($SKU));
                                }

                                if (!empty ($EAN)) {
                                    $eanList = $product->appendChild ($doc->createElement('EANList'));
                                    $ean     = $eanList->appendChild ($doc->createElement ('EAN'));
                                    $ean     = $ean->appendChild ($doc->createCDATASection ($EAN));
                                }

                                # The product title
                                $title = $product->appendChild ($doc->createElement ('Title'));
                                $title = $title->appendChild ($doc->createCDATASection (get_the_title ()));

                                # The product description
                                $description = $product->appendChild ($doc->createElement ('Description'));
                                $description = $description->appendChild ($doc->createCDATASection (get_the_content ()));

                                # Brand
                                if (!empty ($Brands)) {
                                    $Brand = current ($Brands);
                                    $brand = $product->appendChild ($doc->createElement ('Brand'));
                                    $brand->appendChild ($doc->createCDATASection ($Brand->name));
                                }

                                # ItemType
                                if (!empty ($Categories)) {
                                    $ItemType = current ($Categories);
                                    $itemtype = $product->appendChild ($doc->createElement ('ItemType'));
                                    $itemtype->appendChild ($doc->createCDATASection ($ItemType->name));
                                }

                                # SalePrice
                                if (!empty ($SalePrice)) {
                                    if ($calculate_vat) {
                                        $SalePrice += (($SalePrice / 100) * $TaxPercentage);
                                    }

                                    $saleprice = $doc->createElement ('SalePrice');
                                    $saleprice->appendChild ($doc->createElement ('Currency', get_option ('woocommerce_currency', 'EUR')));
                                    $saleprice->appendChild ($doc->createElement ('Amount', $SalePrice));
                                    $saleprice = $product->appendChild ($saleprice);
                                }

                                # OfferPrice
                                if (!empty ($OfferPrice)) {
                                    if ($calculate_vat) {
                                        $OfferPrice += (($OfferPrice / 100) * $TaxPercentage);
                                    }

                                    $offerprice = $doc->createElement ('OfferPrice');
                                    $offerprice->appendChild ($doc->createElement ('Currency', get_option ('woocommerce_currency', 'EUR')));
                                    $offerprice->appendChild ($doc->createElement ('Amount', $OfferPrice));
                                    $offerprice = $product->appendChild ($offerprice);
                                }

                                # Stock
                                if ('yes' === get_post_meta ($ID, '_manage_stock', TRUE)) {
                                    $Stock = abs (intval (get_post_meta ($ID, '_stock', TRUE)));
                                    $Stock -= $this->stock_marge;

                                    if (0 >= $Stock) {
                                        $Stock = 0;
                                    }

                                    $stock = $doc->createElement ('Stock');
                                    $stocklocation = $doc->createElement ('StockLocation');
                                    $stocklocation->appendChild ($doc->createElement ('LocationID', $this->stock_location_id));
                                    $stocklocation->appendChild ($doc->createElement ('QuantityInStock', $Stock));
                                    $stock->appendChild ($stocklocation);
                                    $product->appendChild ($stock);
                                }

                                # Height
                                if (!empty ($Height)) {
                                    $this->dimension->set ($Height, $dimension_unit);
                                    $product->appendChild ($doc->createElement ('Height', $this->dimension->get ('mm', 0)));
                                }

                                # Width
                                if (!empty ($Width)) {
                                    $this->dimension->set ($Width, $dimension_unit);
                                    $product->appendChild ($doc->createElement ('Width', $this->dimension->get ('mm', 0)));
                                }

                                # Length
                                if (!empty ($Length)) {
                                    $this->dimension->set ($Length, $dimension_unit);
                                    $product->appendChild ($doc->createElement ('Length', $this->dimension->get ('mm', 0)));
                                }

                                # Weight
                                if (!empty ($Weight)) {
                                    $this->weight->set ($Weight, $weight_unit);
                                    $product->appendChild ($doc->createElement ('Weight', $this->weight->get ('g', 0)));
                                }

                                $product = $root->appendChild ($product);
                            }
                        }

                        $doc->formatOutput = TRUE;

                        file_put_contents ($path . self::EXPORT_NAME, $doc->saveXML ());
                        chmod ($path . self::EXPORT_NAME, 0775);
                    }
                }
            });

            $this->schedule_event ();
        }
    }
}
