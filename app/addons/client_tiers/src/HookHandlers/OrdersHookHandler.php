<?php
/***************************************************************************
 *                                                                          *
 *   (c) 2004 Vladimir V. Kalynyak, Alexey V. Vinokurov, Ilya M. Shalnev    *
 *                                                                          *
 * This  is  commercial  software,  only  users  who have purchased a valid *
 * license  and  accept  to the terms of the  License Agreement can install *
 * and use this program.                                                    *
 *                                                                          *
 ****************************************************************************
 * PLEASE READ THE FULL TEXT  OF THE SOFTWARE  LICENSE   AGREEMENT  IN  THE *
 * "copyright.txt" FILE PROVIDED WITH THIS DISTRIBUTION PACKAGE.            *
 ****************************************************************************/

namespace Tygh\Addons\ClientTiers\HookHandlers;

use Tygh\Addons\ClientTiers\Enum\Logging;
use Tygh\Addons\ClientTiers\Enum\Calculation;
use Tygh\Addons\ClientTiers\Enum\UpgradeOptions;
use Tygh\Addons\ClientTiers\ServiceProvider;

class OrdersHookHandler
{
    /** @var int */
    protected $upgrade_option;

    /**
     * OrdersHookHandler constructor.
     *
     * @param int $upgrade_option_value
     */
    public function __construct($upgrade_option_value)
    {
        $this->upgrade_option = $upgrade_option_value;
    }

    /**
     * The "change_order_status_post" hook handler.
     *
     * Actions performed:
     *     - Checks user for upgrading in tiers after completed purchase or downgrading after returned purchase.
     *
     * @see \fn_change_order_status()
     */
    public function onCompletePurchase($order_id, $status_to, $status_from, $force_notification, $place_order, $order_info, $edp_data)
    {
        $paid_statuses = fn_get_settled_order_statuses();

        if ($this->upgrade_option == UpgradeOptions::AFTER_PURCHASE) {

            $service = ServiceProvider::getTierManager();

            if (in_array($status_to, $paid_statuses)) {
                $operation_status = $service->updateTier($order_info['user_id'], 0, Calculation::AUTO);
                $service->logTierUpdateResult($operation_status['code'], $operation_status['data']);
            } elseif (in_array($status_from, $paid_statuses)) {
                $period = $service->getCalculationPeriod();
                if ($period['from'] < $order_info['timestamp'] && $order_info['timestamp'] <= $period['to']) {
                    $operation_status = $service->updateTier($order_info['user_id'], 0, Calculation::AUTO, true);
                    $service->logTierUpdateResult($operation_status['code'], $operation_status['data']);
                }
            }
        }
    }
}