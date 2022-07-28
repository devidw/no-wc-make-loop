<?php

declare(strict_types=1);

/**
 * Plugin Name: Prevent inifinite webhook loop between WooCommerce and make.com
 * Plugin Description: Don't deliver WooCommerce webhooks, when the source of the event comes from the make.com infrastructure to prevent an infinite loop between both systems.
 * Plugin URI: https://github.com/devidw/no-wc-make-loop
 * Author: David Wolf
 * Author URI: https://github.com/devidw
 * Version: 1.0.0
 * License: GPLv2 or later
 */

/**
 * Let's say you use make.com to push data to WooCommerce, but at the same time you use WooCommerce to push data to make.com.
 * This would cause an infinite loop.
 * This plugin will prevent that.
 */
add_filter(
    hook_name: 'woocommerce_webhook_should_deliver',
    accepted_args: 3,
    callback: function (
        bool $shouldDeliver,
        \WC_Webhook $webhook,
        mixed $arg
    ): bool {

        // Only analyze when we are handling a rest api request
        if (!defined('REST_REQUEST') or !REST_REQUEST) {
            return $shouldDeliver;
        }

        // Only process on webhooks that actually should theoretically delivered to make.com
        if (
            !str_ends_with(
                needle: '.make.com', // Match something like "hook.us1.make.com"
                haystack: parse_url(
                    url: $webhook->get_delivery_url(),
                    component: PHP_URL_HOST,
                ),
            )
        ) {
            return $shouldDeliver;
        }

        /**
         * List of outbound IP addresses that are valid servers from the make.com infrastructure.
         * 
         * @see https://web.archive.org/web/20220728115038/https://www.make.com/en/help/connections/allowing-connections-to-and-from-make-ip-addresses
         */
        $allowedIps = [
            // us1.make.com
            '54.209.79.175',
            '54.80.47.193',
            '54.161.178.114',
            // eu1.make.com
            '54.75.157.176',
            '54.78.149.203',
            '52.18.144.195',
            // us1.make.celonis.com
            '44.196.246.20',
            '3.94.51.90',
            '52.4.48.212',
            // eu1.make.celonis.com
            '3.125.27.86',
            '3.68.125.41',
            '18.193.24.45',
        ];

        // Check if the current request is a make.com request
        if (
            in_array(
                needle: filter_input(
                    type: INPUT_SERVER,
                    var_name: 'REMOTE_ADDR',
                ),
                haystack: $allowedIps,
                strict: true,
            )
        ) {
            return false; // Don't deliver the webhook
        }

        return $shouldDeliver;
    }
);
