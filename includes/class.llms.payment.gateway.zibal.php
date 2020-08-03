<?php
/**
 * Zibal Payment Gateway for LifterLMS
 * @since    1.0.0
 * @version  1.0.0
 * @author   Zibal
 */

if (! defined('ABSPATH')) {
    exit;
}

class LLMS_Payment_Gateway_Zibal extends LLMS_Payment_Gateway
{
    const REDIRECT_URL = 'https://gateway.zibal.ir/start/';

    const MIN_AMOUNT = 10000;

    public $MerchantID = '';



    /**
     * Constructor
     * @since    1.0.0
     * @version  1.0.0
     */
    public function __construct()
    {
        $this->id = 'Zibal';
        $this->icon = '<a href="https://www.zibal.ir"  onclick="javascript:window.open(\'https://www.zibal.ir\',\'zibal\',\'toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=1060, height=700\'); return false;"><img src="https://github.com/zibalco/zibal-prestashop-v1.7/raw/master/logo.png" border="0" alt="zibal logo"></a>';
        $this->admin_description = __('Allow customers to purchase courses and memberships using Zibal.', 'lifterlms-Zibal');
        $this->admin_title = 'Zibal';
        $this->title = 'Zibal';
        $this->description = __('Pay via Zibal', 'lifterlms-Zibal');

        $this->supports = array(
            'single_payments' => true,
        );


        // add Zibal specific fields
        add_filter('llms_get_gateway_settings_fields', array( $this, 'settings_fields' ), 10, 2);

        // output Zibal account details on confirm screen
        add_action('lifterlms_checkout_confirm_after_payment_method', array( $this, 'after_payment_method_details' ));
    }


    public function after_payment_method_details()
    {
        $key = isset($_GET['order']) ? $_GET['order'] : '';

        $order = llms_get_order_by_key($key);
        if (! $order || 'Zibal' !== $order->get('payment_gateway')) {
            return;
        }
        
        echo '<input name="llms_zibal_token" type="hidden" value="' . $_GET['trackId'] . '">';
    }

    /**
     * Output some information we need on the confirmation screen
     * @return   void
     * @since    1.0.0
     * @version  1.0.0
     */
    public function confirm_pending_order($order)
    {
        if (! $order || 'Zibal' !== $order->get('payment_gateway')) {
            return;
        }

        $this->log('Zibal `after_payment_method_callback()` started', $order, $_POST);

        $currency = $this->get_lifterlms_currency();

        if($currency != "IRR") {
            $amount = $order->get_price('total', array(), 'float') * 10;
        } else {
            $amount = $order->get_price('total', array(), 'float');
        }

        if(isset($_GET['status']) && $_GET['status'] == 2) {
            $trackId = $_GET['trackId'];
            $data = array(
                'merchant' => self::get_MerchantID(),
                'trackId' => $trackId,
            );
    
            $result = $this->postToZibal('verify', $data);
            $result = (array)$result;
    
            // check amounts and compare them
            if ($result['result'] == 100) {
                if (isset($result['amount']) && $result['amount'] == $amount) {
    
                    $txn_data = array();
                    $txn_data['amount'] = $order->get_price('total', array(), 'float');
                    $txn_data['transaction_id'] = $result['refNumber'];
                    $txn_data['result'] = 'llms-txn-succeeded';
                    $txn_data['payment_type'] = 'single';
                    $txn_data['source_description'] = isset($_POST['card_holder']) ? $_POST['card_holder'] : '';
                    
                    $order->record_transaction($txn_data);
                    
                    $this->log($order, 'Zibal `confirm_pending_order()` finished');
                    
                    
                    $order->add_note('شماره تراکنش : ' . $result['refNumber']);
                    
                    $this->complete_transaction($order);
                } else {
                    $this->log($order, 'Zibal `confirm_pending_order()` finished with error : Amounts do not match' . $result['result']);
                    $order->add_note('Faild Transaction : Amounts do not match' . $result['result']);
    
                    wp_safe_redirect(llms_cancel_payment_url());
                    exit();
                }
            } else {
                $this->log($order, 'Zibal `confirm_pending_order()` finished with error : ' . $this->resultCodes($result['result']) . $result['result']);
                $order->add_note('Faild Transaction : ' . $this->resultCodes($result['result']) . $result['result']);
    
                wp_safe_redirect(llms_cancel_payment_url());
                exit();
            }
        } else {
            $this->log($order, 'Zibal `confirm_pending_order()` finished with error : ' . $this->statusCodes($_GET['status']) . $_GET['status']);
            $order->add_note('Faild Transaction : ' . $this->statusCodes($_GET['status']) . $_GET['status']);

            wp_safe_redirect(llms_cancel_payment_url());
            exit();
        }
        
    }

    /**
     * Get $MerchantID option
     * @return   string
     * @since    1.0.0
     * @version  1.0.0
     */
    public function get_MerchantID()
    {
        return $this->get_option('MerchantID');
    }

    public function get_lifterlms_currency() {
        return apply_filters( 'lifterlms_currency', get_option( 'lifterlms_currency', 'USD' ) );
    }

    /**
     * Handle a Pending Order
     * Called by LLMS_Controller_Orders->create_pending_order() on checkout form submission
     * All data will be validated before it's passed to this function
     *
     * @param   obj       $order   Instance LLMS_Order for the order being processed
     * @param   obj       $plan    Instance LLMS_Access_Plan for the order being processed
     * @param   obj       $person  Instance of LLMS_Student for the purchasing customer
     * @param   obj|false $coupon  Instance of LLMS_Coupon applied to the order being processed, or false when none is being used
     * @return  void
     * @since   1.0.0
     * @version 1.0.0
     */
    public function handle_pending_order($order, $plan, $person, $coupon = false)
    {
        $this->log('Zibal `handle_pending_order()` started', $order, $plan, $person, $coupon);
        $currency = $this->get_lifterlms_currency();

        if($currency != "IRR") {
            $amount = $order->get_price('total', array(), 'float') * 10;
        } else {
            $amount = $order->get_price('total', array(), 'float');
        }

        // do some gateway specific validation before proceeding
        // $total = $order->get_price('total', array(), 'float');
        
        if ($amount < self::MIN_AMOUNT) {
            return llms_add_notice(sprintf(__('با توجه به محدوديت هاي پرداختی امكان پرداخت با رقم درخواست شده ميسر نمي باشد حداقل مبلغ پرداختی  %s تومان است', 'lifterlms-Zibal'), self::MIN_AMOUNT), 'error');
        }

        $data = array(
            'merchant' => self::get_MerchantID(),
            'amount' => $amount,
            'callbackUrl' => llms_confirm_payment_url($order->get('order_key')),
            'description' => $order->get('order_key')
        );

        $result = $this->postToZibal('request', $data);
        $result = (array)$result;

        if ($result["result"] == 100) {
            $this->log($r, 'Zibal `handle_pending_order()` finished');
            do_action('lifterlms_handle_pending_order_complete', $order);
            $order->add_note('transaction ID : ' . $result["trackId"]);
            wp_redirect(self::REDIRECT_URL . $result["trackId"]);
            exit();
        } else {
            $this->log($r, 'Zibal `handle_pending_order()` finished with error code : ');
            return llms_add_notice('خطا در اتصال به درگاه : ' . $result["result"], 'error');
        }
    }




    /**
     * Output custom settings fields on the LifterLMS Gateways Screen
     * @param    array     $fields      array of existing fields
     * @param    string    $gateway_id  id of the gateway
     * @return   array
     * @since    1.0.0
     * @version  1.0.0
     */
    public function settings_fields($fields, $gateway_id)
    {

        // don't add fields to other gateways!
        if ($this->id !== $gateway_id) {
            return $fields;
        }

        $fields[] = array(
            'type'  => 'custom-html',
            'value' => '
				<h4>' . __('Zibal Settings', 'lifterlms-Zibal') . '</h4>
				<p>' . __(' شناسه درگاه (مرچنت) زیبال را وارد نمایید. ', 'lifterlms-Zibal') . '</p>
			',
        );

        $settings = array(
            'MerchantID' => __('مرچنت کد', 'lifterlms-Zibal'),
        );
        foreach ($settings as $k => $v) {
            $fields[] = array(
                'id'            => $this->get_option_name($k),
                'default'       => $this->{'get_' . $k}(),
                'title'         => $v,
                'type'          => 'text',
            );
        }


        return $fields;
    }

    public function postToZibal($path, $parameters)
    {
        $url = 'https://gateway.zibal.ir/v1/'.$path;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($parameters));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response  = curl_exec($ch);
        curl_close($ch);
        return json_decode($response);
    }

    /**
     * returns a string message based on result parameter from curl response
     * @param $code
     * @return String
     */
    public function resultCodes($code)
    {
        switch ($code) {
        case 100:
            return "با موفقیت تایید شد";
        
        case 102:
            return "merchant یافت نشد";

        case 103:
            return "merchant غیرفعال";

        case 104:
            return "merchant نامعتبر";

        case 201:
            return "قبلا تایید شده";
        
        case 105:
            return "amount بایستی بزرگتر از 1,000 ریال باشد";

        case 106:
            return "callbackUrl نامعتبر می‌باشد. (شروع با http و یا https)";

        case 113:
            return "amount مبلغ تراکنش از سقف میزان تراکنش بیشتر است.";

        case 201:
            return "قبلا تایید شده";
        
        case 202:
            return "سفارش پرداخت نشده یا ناموفق بوده است";

        case 203:
            return "trackId نامعتبر می‌باشد";

        default:
            return "وضعیت مشخص شده معتبر نیست";
    }
    }

    /**
     * returns a string message based on status parameter from $_GET
     * @param $code
     * @return String
     */
    public function statusCodes($code)
    {
        switch ($code) {
        case -1:
            return "در انتظار پردخت";
        
        case -2:
            return "خطای داخلی";

        case 1:
            return "پرداخت شده - تاییدشده";

        case 2:
            return "پرداخت شده - تاییدنشده";

        case 3:
            return "لغوشده توسط کاربر";
        
        case 4:
            return "‌شماره کارت نامعتبر می‌باشد";

        case 5:
            return "‌موجودی حساب کافی نمی‌باشد";

        case 6:
            return "رمز واردشده اشتباه می‌باشد";

        case 7:
            return "‌تعداد درخواست‌ها بیش از حد مجاز می‌باشد";
        
        case 8:
            return "‌تعداد پرداخت اینترنتی روزانه بیش از حد مجاز می‌باشد";

        case 9:
            return "مبلغ پرداخت اینترنتی روزانه بیش از حد مجاز می‌باشد";

        case 10:
            return "‌صادرکننده‌ی کارت نامعتبر می‌باشد";
        
        case 11:
            return "خطای سوییچ";

        case 12:
            return "کارت قابل دسترسی نمی‌باشد";

        default:
            return "وضعیت مشخص شده معتبر نیست";
    }
    }
}
