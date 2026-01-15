<?php
/**
 * gateway.class.php
 *
 * Gateway Class for Payfast
 *
 * Copyright (c) 2026 Payfast (Pty) Ltd
 * @author App Inlet (Pty) Ltd
 * @link http://www.payfast.co.za/help/cube_cart
 */

require_once __DIR__ . '/vendor/autoload.php';

use Payfast\PayfastCommon\Aggregator\Request\PaymentRequest;

class Gateway
{
    private $config;
    private $module;
    private $basket;

    /**
     * __construct
     */
    public function __construct($module = false)
    {
        $this->config   = &$GLOBALS['config'];
        $this->_session = &$GLOBALS['user'];

        $this->module = $module;
        $this->basket = &$GLOBALS['cart']->basket;
    }

    /**
     * transfer
     * @usage Will redirect the user to either sandbox or live Payfast
     */
    public function transfer()
    {
        return array(
            'action' => ($this->module['testMode'] != 0) ?
                'https://sandbox.payfast.co.za/eng/process' : 'https://www.payfast.co.za/eng/process',
            'method' => 'post',
            'target' => '_self',
            'submit' => 'auto',
        );
    }

    /**
     * repeatVariables
     */
    public function repeatVariables()
    {
        return false;
    }

    /**
     * fixedVariables
     */
    public function fixedVariables()
    {
        // Use appropriate merchant identifiers
        $passPhrase  = $this->module['passphrase'];
        $merchantId  = $this->module['merchant_id'];
        $merchantKey = $this->module['merchant_key'];

        // Create description
        $description = '';
        foreach ($this->basket['contents'] as $item) {
            $description .= $item['quantity'] . ' x ' . $item['name'] . ' @ ' .
                            number_format($item['price'] / $item['quantity'], 2, '.', ',') . 'ea = ' .
                            number_format($item['price'], 2, '.', ',') . '; ';
        }

        $description .= 'Shipping = ' . $this->basket['shipping']['value'] . '; ';
        $description .= 'Tax = ' . $this->basket['total_tax'] . '; ';
        $description .= 'Total = ' . $this->basket['total'];

        $hidden   = array(
            // Merchant details
            'merchant_id'      => $merchantId,
            'merchant_key'     => $merchantKey,

            // Create URLs
            'return_url'       => $GLOBALS['storeURL'] . '/index.php?_a=complete',
            'cancel_url'       => $GLOBALS['storeURL'] . '/index.php?_a=confirm',
            'notify_url'       => $GLOBALS['storeURL'] . '/index.php?_g=rm&type=gateway&cmd=call&module=Payfast',

            // Customer details
            'name_first'       => substr(trim($this->basket['billing_address']['first_name']), 0, 100),
            'name_last'        => substr(trim($this->basket['billing_address']['last_name']), 0, 100),
            'email_address'    => substr(trim($this->basket['billing_address']['email']), 0, 255),

            // Item details
            'm_payment_id'     => $this->basket['cart_order_id'],
            'amount'           => number_format($this->basket['total'], 2, '.', ''),
            'item_name'        => $GLOBALS['config']->get(
                    'config',
                    'store_name'
                ) . ' Purchase, Order #' . $this->basket['cart_order_id'],
            'item_description' => substr(trim($description), 0, 255),
            'custom_str1'      => 'PF_CubeCart_6' . '_' . '1.1',
        );
        $pfOutput = '';
        foreach ($hidden as $key => $val) {
            $pfOutput .= $key . '=' . urlencode(trim($val)) . '&';
        }
        empty($passPhrase) ? $pfOutput = substr($pfOutput, 0, -1) : $pfOutput .= 'passphrase=' . urlencode($passPhrase);
        $pfSignature         = md5($pfOutput);
        $hidden['signature'] = $pfSignature;

        return $hidden;
    }

    /**
     * call
     * @usage ITN function, will updat ethe order appropriately
     */
    public function call()
    {
        // Include Payfast common file
        $debugMode     = $this->isDebugMode();
        $paymentRequest = new PaymentRequest($debugMode);

        // Variable Initialization
        $pfError       = false;
        $pfNotes       = array();
        $pfData        = array();
        $pfHost        = $this->getHost();
        $orderId       = '';
        $pfParamString = '';
        $paymentRequest->pflog('Payfast ITN call received');

        //// Notify Payfast that information has been received
        $this->notifyPayfast($pfError);

        //// Get data sent by Payfast
        if (!$pfError) {
            $paymentRequest->pflog('Get posted data');

            // Posted variables from ITN
            $pfData = $paymentRequest->pfGetData();

            $paymentRequest->pflog('Payfast Data: ' . print_r($pfData, true));

            if ($pfData === false) {
                $pfError   = true;
                $pfNotes[] = $paymentRequest::PF_ERR_BAD_ACCESS;
            }
        }

        //// Verify security signature
        if (!$pfError) {
            $paymentRequest->pflog('Verify security signature');
            // If signature different, log for debugging
            if (!$paymentRequest->pfValidSignature($pfData, $pfParamString, $this->module['passphrase'])) {
                $pfError   = true;
                $pfNotes[] = $paymentRequest::PF_ERR_INVALID_SIGNATURE;
            }
        }

        //// Retrieve order from CubeCart
        if (!$pfError) {
            $paymentRequest->pflog('Get order');

            $orderId       = $pfData['m_payment_id'];
            $order         = Order::getInstance();
            $order_summary = $order->getSummary($orderId);

            $paymentRequest->pflog('Order ID = ' . $orderId);
        }

        //// Verify data
        global $ini;
        $moduleInfo = [
            "pfSoftwareName"       => 'CubeCart',
            "pfSoftwareVer"        => $ini['ver'],
            "pfSoftwareModuleName" => 'PF_CubeCart_6',
            "pfModuleVer"          => '1.3',
        ];

        if (!$pfError) {
            $paymentRequest->pflog('Verify data received');

            $pfValid = $this->verifyDataReceived(
                $paymentRequest,
                $moduleInfo,
                $pfHost,
                $pfParamString
            );

            if (!$pfValid) {
                $pfError   = true;
                $pfNotes[] = $paymentRequest::PF_ERR_BAD_ACCESS;
            }
        }

        //// Check status and update order & transaction table
        if (!$pfError) {
            $paymentRequest->pflog('Check status and update order');

            $success = true;

            // Check the payment_status is Completed
            if ($pfData['payment_status'] !== 'COMPLETE') {
                $success = false;

                $pfNotes = match ($pfData['payment_status']) {
                    'FAILED' => $paymentRequest::PF_MSG_FAILED,
                    'PENDING' => $paymentRequest::PF_MSG_PENDING,
                    default => $paymentRequest::PF_ERR_UNKNOWN,
                };
            }

            // Check if the transaction has already been processed
            // This checks for a "transaction" in CubeCart of the same status (status)
            // for the same order (order_id) and same payfast payment id (trans_id)
            $trnId = $GLOBALS['db']->select(
                'CubeCart_transactions',
                array('id'),
                array('trans_id' => $pfData['pf_payment_id'])
            );

            list($success, $pfNotes) = $this->checkTransactionState($trnId, $success, $paymentRequest, $pfNotes);

            // Check Payfast amount matches order amount
            if (!$paymentRequest->pfAmountsEqual($pfData['amount_gross'], $order_summary['total'])) {
                $success   = false;
                $pfNotes[] = $paymentRequest::PF_ERR_AMOUNT_MISMATCH;
            }

            // If transaction is successful and correct, update order status
            $pfNotes = $this->upDateOrderStatus($success, $paymentRequest, $pfNotes, $order, $orderId);
        }

        //// Insert transaction entry
        // This gets done for every ITN call no matter whether successful or not.
        // The notes field is used to provide feedback to the user.
        $paymentRequest->pflog('Create transaction data and save');

        $pfNoteMsg = '';
        $pfNoteMsg = $this->getNotesMsg($pfNotes, $pfNoteMsg);

        $transData                = array();
        $transData['customer_id'] = $order_summary['customer_id'];
        $transData['gateway']     = "Payfast ITN";
        $transData['trans_id']    = $pfData['pf_payment_id'];
        $transData['order_id']    = $orderId;
        $transData['status']      = $pfData['payment_status'];
        $transData['amount']      = $pfData['amount_gross'];
        $transData['notes']       = $pfNoteMsg;

        $paymentRequest->pflog("Transaction log data: \n" . print_r($transData, true));

        $order->logTransaction($transData);

        // Close log
        $paymentRequest->pflog('', true);
    }

    /**
     * process
     */
    public function process()
    {
        ## We're being returned from Payfast - This function can do some pre-processing,
        #but must assume NO variables are being passed around
        ## The basket will be emptied when we get to _a=complete, and the status isn't Failed/Declined

        ## Redirect to _a=complete, and drop out unneeded variables
        httpredir(currentPage(array('_g', 'type', 'cmd', 'module'), array('_a' => 'complete')));
    }

    /**
     * form
     */
    public function form()
    {
        return false;
    }

    /**
     * @param PaymentRequest $paymentRequest
     * @param array $moduleInfo
     * @param string $pfHost
     * @param string $pfParamString
     *
     * @return bool
     */
    public function verifyDataReceived(
        PaymentRequest $paymentRequest,
        array $moduleInfo,
        string $pfHost,
        string $pfParamString
    ): bool {
        return $paymentRequest->pfValidData($moduleInfo, $pfHost, $pfParamString);
    }

    /**
     * @param bool $pfError
     *
     * @return void
     */
    public function notifyPayfast(bool $pfError): void
    {
        if (!$pfError) {
            header('HTTP/1.0 200 OK');
            flush();
        }
    }

    /**
     * @param bool $success
     * @param PaymentRequest $paymentRequest
     * @param array $pfNotes
     * @param $order
     * @param mixed $orderId
     *
     * @return array
     */
    public function upDateOrderStatus(
        bool $success,
        PaymentRequest $paymentRequest,
        array $pfNotes,
        $order,
        mixed $orderId
    ): array {
        if ($success) {
            $pfNotes[] = $paymentRequest::PF_MSG_OK;
            try {
                $order->paymentStatus(Order::PAYMENT_SUCCESS, $orderId);
                $order->orderStatus(Order::ORDER_COMPLETE, $orderId);
            } catch (Exception $e) {
                $paymentRequest->pflog('Error Exception message: ' . $e);
            }
        }

        return $pfNotes;
    }

    /**
     * @param array $pfNotes
     * @param string $pfNoteMsg
     *
     * @return string
     */
    public function getNotesMsg(array $pfNotes, string $pfNoteMsg): string
    {
        if (sizeof($pfNotes) > 1) {
            foreach ($pfNotes as $note) {
                $pfNoteMsg .= $note . "; ";
            }
        } else {
            $pfNoteMsg .= $pfNotes[0];
        }

        return $pfNoteMsg;
    }

    /**
     * @return bool
     */
    public function isDebugMode(): bool
    {
        return $this->module['debug_log'] ? true : false;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return (($this->module['testMode'] != 0) ? 'sandbox' : 'www') . '.payfast.co.za';
    }

    /**
     * @return mixed
     */
    public function getDebugEmail(): mixed
    {
        return (strlen($this->module['debug_email']) > 0) ?
            $this->module['debug_email'] : $this->config->get('config', 'masterEmail');
    }

    /**
     * @param $trnId
     * @param bool $success
     * @param PaymentRequest $paymentRequest
     * @param array $pfNotes
     *
     * @return array
     */
    public function checkTransactionState($trnId, bool $success, PaymentRequest $paymentRequest, array $pfNotes): array
    {
        if ($trnId) {
            $success   = false;
            $pfNotes[] = $paymentRequest::PF_ERR_ORDER_PROCESSED;
        }

        return array($success, $pfNotes);
    }
}
