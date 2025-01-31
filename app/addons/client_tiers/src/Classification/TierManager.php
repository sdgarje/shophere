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

namespace Tygh\Addons\ClientTiers\Classification;

use Tygh\Addons\ClientTiers\Enum\Calculation;
use Tygh\Addons\ClientTiers\Enum\Logging;
use Tygh\Addons\ClientTiers\Enum\OperationResultCodes;
use Tygh\Addons\ClientTiers\Enum\UpdatePeriods;
use Tygh\Common\OperationResult;
use Tygh\Enum\UsergroupLinkStatuses;
use DateTime;
use Tygh\Enum\UserTypes;

/**
 * Class TierManager contains operation of managing clients movement through tiers
 *
 * @package Tygh\Addons\ClientTiers\Classification
 */
class TierManager
{
    /** @var int */
    protected $reporting_period;

    /** @var int */
    protected $upgrade_option;

    /** @var int */
    protected $auto_downgrade;

    /** @var \Tygh\Addons\ClientTiers\Classification\TierClassificationService */
    protected $classification_service;

    /**
     * TierManager constructor.
     *
     * @param int                                                               $reporting_period       Add-on settings option value
     * @param int                                                               $upgrade_option         Add-on settings option value
     * @param int                                                               $auto_downgrade         Add-on settings option value
     * @param \Tygh\Addons\ClientTiers\Classification\TierClassificationService $classification_service Service for work with current tiers classification
     */
    public function __construct($reporting_period, $upgrade_option, $auto_downgrade, $classification_service)
    {
        $this->reporting_period = $reporting_period;
        $this->upgrade_option = $upgrade_option;
        $this->auto_downgrade = $auto_downgrade;
        $this->classification_service = $classification_service;
    }

    /**
     * Sets timestamp boundaries for orders to be counted
     *
     * @return array
     * @throws \Exception
     */
    public function getCalculationPeriod()
    {
        $startTimeStamp = new DateTime();
        $finishTimeStamp = new DateTime();

        switch ($this->reporting_period) {
            case UpdatePeriods::PREVIOUS_30_DAYS:
                $startTimeStamp->modify('-30 days');
                break;
            case UpdatePeriods::PREVIOUS_MONTH:
                $startTimeStamp->modify('first day of last month');
                $finishTimeStamp->modify('last day of last month');
                break;
            case UpdatePeriods::PREVIOUS_12_MONTHS:
                $startTimeStamp->modify('-12 months');
                break;
            case UpdatePeriods::PREVIOUS_YEAR:
                $startTimeStamp->modify('first day of january last year');
                $finishTimeStamp->modify('last day of december last year');
                break;
            default:
                break;
        }

        return ['from' => $startTimeStamp->getTimestamp(), 'to' => $finishTimeStamp->getTimestamp()];
    }

    /**
     * Returns total amount of spent money for specified in add-on settings period
     *
     * @param $user_id
     *
     * @return double
     * @throws \Exception
     */
    protected function calculateTotalForPeriod($user_id)
    {
        $period = $this->getCalculationPeriod();
        list(, , $totals) = fn_get_orders(
            [
                'user_id' => $user_id,
                'period'  => 'C',
                'time_from' => $period['from'],
                'time_to' => $period['to'],
            ], 0, true);

        return $totals['totally_paid'] ? $totals['totally_paid'] : 0.0;
    }

    /**
     * Updates tier for specified user in tiers classification
     *
     * @param int       $user_id         Specified user, which position in the tier should be updated
     * @param double    $user_total      Total amount of spent money in specified period ot time
     * @param string    $type            Type of current updating position (AUTO or MANUAL)
     * @param bool      $allow_downgrade Flag that allows or disallows downgrade updating position
     *
     * @return array    Array contains status of completed operation (code) and data of this operation (data)
     * @throws \Exception
     */
    public function updateTier($user_id, $user_total = 0.0, $type = Calculation::AUTO, $allow_downgrade = false)
    {
        if (empty($user_total)) {
            $user_total = $this->calculateTotalForPeriod($user_id);
        }
        if ($type === Calculation::MANUAL) {
            $this->classification_service->getClassification(true);
        }
        $new_tier = $this->classification_service->findProperTierByTotalSpend($user_total);
        $old_tier = $this->classification_service->getCurrentTierByUserId($user_id);

        $data = [
            'user_id'       => $user_id,
            'user_total'    => $user_total,
        ];

        if ($new_tier === $old_tier) {
            $result = [
                'code' => OperationResultCodes::TIER_STAYS_THE_SAME,
                'data' => $data,
            ];
            return $result;
        }

        $status = false;
        if ($type === Calculation::MANUAL) {
            $status = $this->update($user_id, $new_tier, $old_tier);
        } elseif (($old_tier < $new_tier) || ($old_tier === null) || ($allow_downgrade || $this->auto_downgrade)) {
            $status = $this->update($user_id, $new_tier, $old_tier);
        }

        if (!$status) {
            $result = [
                'code' => OperationResultCodes::REQUIRED_OPERATION_REFUSED,
                'data' => $data,
            ];
            return $result;
        }

        if ($old_tier === null) {
            $data['new_tier'] = $new_tier;
            if (isset($status['activate_new_group'])) {
                $result = [
                    'code'  => OperationResultCodes::SUCCESSFULLY_SET_TIER,
                    'data'  => $data,
                ];
            } else {
                $result = [
                    'code'  => OperationResultCodes::FAIL_SET_NEW_TIER,
                    'data'  => $data,
                ];
            }

            return $result;
        }

        if ($new_tier === null) {
            $data['old_tier'] = $old_tier;
            if (isset($status['deactivate_old_group'])) {
                $result = [
                    'code'  => OperationResultCodes::SUCCESSFULLY_UNSET_TIER,
                    'data'  => $data,
                ];
            } else {
                $result = [
                    'code'  => OperationResultCodes::FAIL_UNSET_OLD_TIER,
                    'data'  => $data,
                ];
            }

            return $result;
        }

        $data['new_tier'] = $new_tier;
        $data['old_tier'] = $old_tier;

        if ($status['activate_new_group'] && $status['deactivate_old_group']) {
            $result = [
                'code'  => OperationResultCodes::SUCCESSFULLY_SET_TIER,
                'data'  => $data,
            ];
        } else {
            if (!$status['activate_new_group']) {
                $result = [
                    'code'  => OperationResultCodes::FAIL_SET_NEW_TIER,
                    'data'  => $data,
                ];
            } else {
                $result = [
                    'code'  => OperationResultCodes::FAIL_UNSET_OLD_TIER,
                    'data'  => $data,
                ];
            }
        }

        return $result;
    }

    /**
     * Contains all logging options for operations with tiers
     *
     * @param int   $code Code of state of executed operation
     * @param array $data Data from executed operation
     *
     * @return \Tygh\Common\OperationResult
     */
    public function logTierUpdateResult($code, $data)
    {
        $username = fn_get_user_name($data['user_id']);

        $result = new OperationResult(false);
        $result->setData($data['user_id'], 'user_id');
        $result->setData($username, 'username');
        $result->setData(fn_format_price($data['user_total']), 'current_total');

        switch ($code) {
            case OperationResultCodes::TIER_STAYS_THE_SAME:
                $result->setSuccess(true);
                break;
            case OperationResultCodes::SUCCESSFULLY_SET_TIER:
                $result->setSuccess(true);
                $new_group = fn_get_usergroup_name($this->classification_service->getUsergroupByTier($data['new_tier']));
                $result->setData($new_group, 'new_group');
                $result->addMessage($code, __('client_tiers.client_success_set_tier', ['[username]' => $username, '[total]' => $data['user_total'], '[new_group]' => $new_group]));
                fn_log_event(Logging::LOG_TYPE_CLIENT_TIERS, Logging::ACTION_SUCCESS, ['result' => $result]);
                break;
            case OperationResultCodes::FAIL_SET_NEW_TIER:
                $new_group = fn_get_usergroup_name($this->classification_service->getUsergroupByTier($data['new_tier']));
                $result->setData($new_group, 'new_group');
                $result->addError($code, __('client_tiers.client_fail_set_new_tier', ['[user_id]' => $data['user_id'], '[total]' => $data['user_total'], '[new_group]' => $new_group]));
                fn_log_event(Logging::LOG_TYPE_CLIENT_TIERS, Logging::ACTION_FAILURE, ['result' => $result]);
                break;
            case OperationResultCodes::SUCCESSFULLY_UNSET_TIER:
                $result->setSuccess(true);
                $old_group = fn_get_usergroup_name($this->classification_service->getUsergroupByTier($data['old_tier']));
                $result->setData($old_group, 'old_group');
                $result->addMessage($code, __('client_tiers.client_success_unset_tier', ['[username]' => $username, '[total]' => $data['user_total'], '[old_group]' => $old_group]));
                fn_log_event(Logging::LOG_TYPE_CLIENT_TIERS, Logging::ACTION_SUCCESS, ['result' => $result]);
                break;
            case OperationResultCodes::FAIL_UNSET_OLD_TIER:
                $old_group = fn_get_usergroup_name($this->classification_service->getUsergroupByTier($data['old_tier']));
                $result->setData($old_group, 'old_group');
                $result->addError($code, __('client_tiers.client_fail_unset_old_tier', ['[user_id]' => $data['user_id'], '[total]' => $data['user_total'], '[old_group]' => $old_group]));
                fn_log_event(Logging::LOG_TYPE_CLIENT_TIERS, Logging::ACTION_FAILURE, ['result' => $result]);
                break;
            case OperationResultCodes::REQUIRED_OPERATION_REFUSED:
                $result->setSuccess(true);
                break;
            default:
                break;
        }

        return $result;
    }

    /**
     * Updates tier by activating and deactivating specified usergroups for selected user
     *
     * @param int      $user_id
     * @param int|null $new_tier Number of new tier for selected user | null if selected user should not be in any tier
     * @param int|null $old_tier Number of old tier for selected user | null if selected user was not in any tier
     *
     * @return array
     */
    protected function update($user_id, $new_tier, $old_tier)
    {
        $result_deactivate = false;
        $result_activate = false;

        if ($old_tier === null) {
            $result_activate = fn_change_usergroup_status(UsergroupLinkStatuses::ACTIVE, $user_id, $this->classification_service->getUsergroupByTier($new_tier), [UserTypes::CUSTOMER => true]);
        } else {
            if ($new_tier === null) {
                $result_deactivate = fn_change_usergroup_status(UsergroupLinkStatuses::AVAILABLE, $user_id, $this->classification_service->getUsergroupByTier($old_tier), [UserTypes::CUSTOMER => true]);
            } else {
                $result_activate = fn_change_usergroup_status(UsergroupLinkStatuses::ACTIVE, $user_id, $this->classification_service->getUsergroupByTier($new_tier), [UserTypes::CUSTOMER => true]);
                if ($result_activate) {
                    $result_deactivate = fn_change_usergroup_status(UsergroupLinkStatuses::AVAILABLE, $user_id, $this->classification_service->getUsergroupByTier($old_tier), [UserTypes::CUSTOMER => true]);
                }
            }
        }
        return ['activate_new_group' => $result_activate, 'deactivate_old_group' => $result_deactivate];
    }

}