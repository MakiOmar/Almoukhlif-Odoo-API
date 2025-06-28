<?php
/**
 * Odoo Stock Management Class
 * 
 * @package Odoo
 */

defined('ABSPATH') || die;

class Odoo_Stock {
    
    /**
     * Check stock from Odoo before allowing a product to be added to the WooCommerce cart.
     *
     * @param bool $passed Whether the add-to-cart action should proceed.
     * @param int $product_id The ID of the product being added to the cart.
     * @param int $quantity The quantity of the product.
     * @param int $variation_id Variation ID being added to the cart.
     * @param array $variation Variation data.
     * @return bool Whether the add-to-cart action should proceed.
     */
    public static function check_stock_before_add_to_cart($passed, $product_id, $quantity, $variation_id = 0, $variation = null) {
        // Check if WooCommerce is active.
        if (!class_exists('WooCommerce')) {
            return $passed;
        }

        // Get the product and its SKU.
        $product = wc_get_product($product_id);
        if (!$product) {
            wc_add_notice('Invalid product.', 'error');
            return false;
        }

        $sku = $product->get_sku();

        // Determine if this is a variation or simple product.
        $check_id = $variation_id ? $variation_id : $product_id;

        // Use the helper function to check stock with the multiplier.
        $stock_check = self::check_stock($sku, $quantity, $check_id);

        if (is_wp_error($stock_check)) {
            wc_add_notice($stock_check->get_error_message(), 'error');
            return false;
        }

        if (!$stock_check) {
            $message = 'مخزون المنتج محدود. يرجى التواصل مع الدعم للحصول على مزيد من المعلومات.';
            wc_add_notice($message, 'error');
            return false; // Prevent adding to cart.
        }

        return $passed; // Allow adding to cart if all checks pass.
    }
    
    /**
     * Check stock from Odoo for a given SKU and quantity
     * 
     * @param string $sku Product SKU
     * @param int $quantity Quantity to check
     * @param int $product_id Product ID
     * @return bool|WP_Error True if stock available, false if not, WP_Error on failure
     */
    public static function check_stock($sku, $quantity, $product_id) {
        $message = 'لا يمكن استرجاع معلومات المخزون. يرجى المحاولة لاحقًا.';

        // Fetch the authentication token.
        $token = Odoo_Auth::get_auth_token();
        if (!$token) {
            if (function_exists('teamlog')) {
                teamlog($message);
            }
            return new WP_Error('token_error', $message);
        }

        // Get stock data from Odoo
        $stock_response = Odoo_API::get_stock_data($sku, $token);

        if (is_wp_error($stock_response)) {
            Odoo_Auth::clear_auth_token();
            return new WP_Error('stock_api_error', $message);
        }

        $stock_body_response = wp_remote_retrieve_body($stock_response);
        $stock_data = json_decode($stock_body_response);

        if (!isset($stock_data->result->Data) || !is_array($stock_data->result->Data)) {
            return new WP_Error('stock_data_error', $message);
        }

        // Calculate total positive stock quantity.
        $total_stock = 0;
        foreach ($stock_data->result->Data as $stock_item) {
            $q = (float) $stock_item->available_quantity; // Changed from (int) to (float)
            if ($q > 0) {
                $total_stock += $q;
            }
        }

        // Check if the product has a stock multiplier.
        $product = wc_get_product($product_id);
        $multiplier = 1; // Default multiplier
        
        if ($product) {
            if ($product->is_type('variation')) {
                $multiplier = (float) $product->get_meta('_stock_multiplier', true);
            } else {
                $multiplier = (float) $product->get_meta('_stock_multiplier', true);
            }
            
            // If no multiplier is set, default to 1
            if (empty($multiplier) || $multiplier <= 0) {
                $multiplier = 1;
            }
        }

        $adjusted_quantity = $quantity * $multiplier;
        
        // Return stock availability.
        return $total_stock >= $adjusted_quantity;
    }
    
    /**
     * Update stock in WooCommerce based on Odoo stock data for a given SKU or product object.
     *
     * @param string $sku The SKU of the product.
     * @param WC_Product|null $product Optional. The product object. If not provided, it will be fetched using the SKU.
     * @return void
     */
    public static function update_stock($sku, $product = null) {
        $token = Odoo_Auth::get_auth_token();
        if (!$token) {
            if (function_exists('teamlog')) {
                teamlog('Failed to update stock in Odoo: Missing authentication token.');
            } else {
                error_log('Failed to update stock in Odoo: Missing authentication token.');
            }
            return;
        }

        $response = Odoo_API::get_stock_data($sku, $token);

        if (is_wp_error($response)) {
            if (function_exists('teamlog')) {
                teamlog('Failed to update stock in Odoo: ' . $response->get_error_message());
            } else {
                error_log('Failed to update stock in Odoo: ' . $response->get_error_message());
            }
            return;
        }

        $response_data = json_decode(wp_remote_retrieve_body($response));

        if (isset($response_data->result->Data) && is_array($response_data->result->Data)) {
            $stock_data = $response_data->result->Data;

            // We assume only one relevant entry in Data array for the stock.
            if (!empty($stock_data[0]) && isset($stock_data[0]->available_quantity)) {
                $forecasted_quantity = (float) $stock_data[0]->available_quantity;

                // Use the provided product object or fetch it by SKU.
                if (is_null($product)) {
                    $product_id = wc_get_product_id_by_sku($sku);
                    $product = $product_id ? wc_get_product($product_id) : null;
                }
                
                if ($product) {
                    wc_update_product_stock($product, max(0, (int) $forecasted_quantity)); // Set stock to 0 if negative.
                }
            } elseif (function_exists('teamlog')) {
                teamlog('Failed to update stock: Invalid data received from Odoo.');
            } else {
                error_log('Failed to update stock: Invalid data received from Odoo.');
            }
        } elseif (function_exists('teamlog')) {
            teamlog('Failed to update stock: Invalid response format from Odoo.');
        } else {
            error_log('Failed to update stock: Invalid response format from Odoo.');
        }
    }
} 