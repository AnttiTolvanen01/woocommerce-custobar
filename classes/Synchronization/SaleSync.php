<?php

namespace WooCommerceCustobar\Synchronization;

defined('ABSPATH') or exit;

use WooCommerceCustobar\DataType\CustobarSale;
use WooCommerceCustobar\DataType\Utilities;
use WooCommerceCustobar\AsyncTasks\CustobarAsyncTask;

/**
 * Class SaleSync
 *
 * @package WooCommerceCustobar\Synchronization
 */
class SaleSync extends AbstractDataSync
{
    protected static $endpoint = '/sales/upload/';

    public static function addHooks()
    {

        add_action('wp_async_woocommerce_new_order', [__CLASS__, 'singleUpdate']);
        add_action('wp_async_woocommerce_update_order', [__CLASS__, 'singleUpdate']);
        add_action('wp_async_woocommerce_subscription_renewal_payment_complete', [__CLASS__, 'updateOnSubscriptionRenewal']);
        add_action('wp_async_woocommerce_subscription_status_updated', [__CLASS__, 'updateOnSubscriptionStatusChange']);
        add_action('plugins_loaded', function () {
            new CustobarAsyncTask('woocommerce_new_order');
            new CustobarAsyncTask('woocommerce_update_order');
            new CustobarAsyncTask('woocommerce_subscription_renewal_payment_complete');
            new CustobarAsyncTask('woocommerce_subscription_status_updated');
        });
        add_filter('woocommerce_custobar_sale_properties', [__CLASS__, 'addSubscriptionFields'], 10, 3);

        // WooCommerce Subscriptions
        add_action('woocommerce_subscription_renewal_payment_complete', [__CLASS__, 'updateOnSubscriptionRenewal'], 10, 2);
        add_action('woocommerce_subscription_status_updated', [__CLASS__, 'updateOnSubscriptionStatusChange'], 10, 3);
    }

    public static function updateOnSubscriptionRenewal($args)
    {
        // args: 0 => subscription, 1 => last_order
        self::singleUpdate($args[1]->get_id());
    }

    public static function updateOnSubscriptionStatusChange($args)
    {
        // args: subscription
        self::singleUpdate($args->get_parent_id());
    }

    public static function singleUpdate($args)
    {
        $order = wc_get_order($args[0]);

        // Would sometimes be triggered twice without the class check, because
        // Subscriptions plugin also creates additional order instance.
        if ($order && get_class($order) === 'WC_Order') {
            $data = [];
            foreach ($order->get_items() as $order_item) {
                $data[] = self::formatSingleItem(array(
                    'order'      => $order,
                    'order_item' => $order_item,
                ));
            }
            self::uploadDataTypeData($data);
        }
    }

    public static function batchUpdate() {

      $orders = \wc_get_orders(array(
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'ASC',
      ));

      $tracker = self::trackerFetch();

      $data = [];
      $orderIds = [];
      $limit = 500;
      foreach ($orders as $order) {

        // skip already processed orders
        if( in_array( $order->get_id(), $tracker)) {
          continue;
        }

        foreach ($order->get_items() as $order_item) {
          $data[] = self::formatSingleItem(array(
            'order'      => $order,
            'order_item' => $order_item
          ));
        }

        $orderIds[] = $order->get_id();
        if( count( $data ) >= $limit ) {
          break;
        }

      }

      if( empty( $data )) {
        return;
      }

      self::trackerSave( $orderIds );
      return self::uploadDataTypeData($data);

    }

    public static function trackerFetch() {
      $trackerKey = 'custobar_export_sale';
      return get_option($trackerKey, []);
    }

    public static function trackerSave( $objectIds ) {
      $trackerKey = 'custobar_export_sale';
      $trackerData = get_option($trackerKey, []);
      $trackerData = array_merge($trackerData, $objectIds);
      $trackerData = array_unique($trackerData);
      update_option($trackerKey, $trackerData);
    }

    protected static function formatSingleItem($args)
    {
        extract($args);  // A hackish way to circumvent the number of parameters defined for inherited abstact method
        $custobar_sale = new CustobarSale($order, $order_item);
        $properties = $custobar_sale->getAssignedProperties();
        return apply_filters('woocommerce_custobar_sale_properties', $properties, $order, $order_item);
    }

    protected static function uploadDataTypeData($data) {
      $formatted_data = array(
        'sales' => $data
      );
      return self::uploadCustobarData($formatted_data);
    }

    /**
     * Modify basic properties by adding WooCommerce Subscriptions related
     * fields.
     */
    public static function addSubscriptionFields($properties, $order, $order_item)
    {
        if (function_exists('wcs_order_contains_subscription') && wcs_order_contains_subscription($order)) {
            $product_id = $order_item->get_product_id();
            $prefix = WOOCOMMERCE_CUSTOBAR_API_PREFIX;
            foreach (wcs_get_subscriptions_for_order($order) as $subscription) {
                foreach ($subscription->get_items() as $line_item) {
                    if ($line_item->get_product_id() === $product_id) {
                        $properties[$prefix . '__subscription_status'] = $subscription->get_status();
                        if ($subscription->get_date('date_created')) {
                            $properties[$prefix . '__subscription_date_created'] = Utilities::formatDateTime(new \DateTime($subscription->get_date('date_created')));
                        }
                        if ($subscription->get_date('trial_end')) {
                            $properties[$prefix . '__subscription_trial_end'] = Utilities::formatDateTime($subscription->get_date('trial_end'));
                        }
                        if ($subscription->get_date('next_payment')) {
                            $properties[$prefix . '__subscription_next_payment'] = Utilities::formatDateTime($subscription->get_date('next_payment'));
                        }
                        if ($subscription->get_date('last_order_date_paid')) {
                            $properties[$prefix . '__subscription_last_order_date_paid'] = Utilities::formatDateTime(new \DateTime($subscription->get_date('last_order_date_paid')));
                        }
                        if ($subscription->get_date('cancelled')) {
                            $properties[$prefix . '__subscription_cancelled'] = Utilities::formatDateTime($subscription->get_date('cancelled'));
                        }
                        if ($subscription->get_date('end')) {
                            $properties[$prefix . '__subscription_end'] = Utilities::formatDateTime(new \DateTime($subscription->get_date('end')));
                        }
                    }
                }
            }
        }
        return $properties;
    }
}
