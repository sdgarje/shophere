<!--Stripe payment buttons-->
{$stripe_button_group_id = uniqid()}
{if $stripe_payment_buttons}

    {* If Stripe buttons are displayed, then hide the Buy now button *}
    {$show_buy_now = false scope = parent}

    {foreach $stripe_payment_buttons as $button}
        {if $button.is_setup}
            {$button_label = __("stripe.online_payment")}
            {if $button.is_test}
                {$button_label = __("stripe.test_payment")}
            {/if}
            <a class="stripe-payment-button stripe-payment-button--{$button.payment_type} hidden"
            data-ca-stripe-element="instantPaymentButton"
            data-ca-stripe-payment-type="{$button.payment_type}"
            data-ca-stripe-publishable-key="{$button.publishable_key}"
            data-ca-stripe-currency="{$button.currency}"
            data-ca-stripe-total-raw="{$button.total_raw}"
            data-ca-stripe-total="{$button.total}"
            data-ca-stripe-country="{$button.country}"
            data-ca-stripe-display-items="{$button.display_items|json_encode}"
            data-ca-stripe-shipping-options="{$button.shipping_options|json_encode}"
            data-ca-stripe-payment-id="{$button.payment_id}"
            data-ca-stripe-product-id="{$button.product_id}"
            data-ca-stripe-product-options="{$button.product_options|json_encode}"
            data-ca-stripe-payment-label="{$button_label}"
            data-ca-stripe-confirmation-url="{fn_url("stripe.check_confirmation.instant_payment")}"
            data-ca-stripe-button-group-id="{$stripe_button_group_id}"
            lang="{$smarty.const.CART_LANGUAGE}"
            ></a>
            {if $button.is_test}
                {capture name="stripe_test_mode_notification"}
                    <div class="stripe-test-mode-notification hidden"
                        data-ca-stripe-test-mode-notification-group-id="{$stripe_button_group_id}"
                    >
                        {__("stripe.test_payment.description")}
                    </div>
                {/capture}
            {/if}
        {/if}
    {/foreach}
{/if}
<!--/Stripe payment buttons-->
