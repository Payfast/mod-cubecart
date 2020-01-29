<?php
/**
 * gateway.class.php
 *
 * Gateway Class for PayFast
 * 
 * Copyright (c) 2008 PayFast (Pty) Ltd
 * You (being anyone who is not PayFast (Pty) Ltd) may download and use this plugin / code in your own website in conjunction with a registered and active PayFast account. If your PayFast account is terminated for any reason, you may not use this plugin / code or part thereof.
 * Except as expressly indicated in this licence, you may not use, copy, modify or distribute this plugin / code or part thereof in any way.
 * 
 * @author     Jonathan Smit
 * @link       http://www.payfast.co.za/help/cube_cart
 */

class Gateway {
	private $_config;
	private $_module;
	private $_basket;

	/**
	 * __construct
     */
	public function __construct( $module = false, $basket = false )
	{
		$this->_config	=& $GLOBALS['config'];
		$this->_session	=& $GLOBALS['user'];

		$this->_module	= $module;
		$this->_basket =& $GLOBALS['cart']->basket;
	}

	/**
	 * transfer
     * @usage Will redirect the user to either sandbox or live PayFast
     */
	public function transfer()
	{
        // Include PayFast common file
        define( 'PF_DEBUG', ( $this->_module['debug_log'] ? true : false ) );
        include_once( 'payfast_common.inc' );
		$transfer	= array(
			'action'	=> ( $this->_module['testMode'] != 1) ? 'https://sandbox.payfast.co.za/eng/process' : 'https://www.payfast.co.za/eng/process',
			'method'	=> 'post',
			'target'	=> '_self',
			'submit'	=> 'auto',
			);
		
		return $transfer;
	}

	/**
	 * repeatVariables
     */
	public function repeatVariables() {
		return false;
	}

	/**
	 * fixedVariables
     */
	public function fixedVariables() {
        // Include PayFast common file
        define( 'PF_DEBUG', ( $this->_module['debug_log'] ? true : false ) );
        include_once( 'payfast_common.inc' );

        // Use appropriate merchant identifiers
        $passPhrase = $this->_module['passphrase'];
        $merchantId = $this->_module['merchant_id'];
        $merchantKey = $this->_module['merchant_key'];

        if( empty($merchantId) || empty($merchantKey) )
        {
            $merchantId = '10000100';
            $merchantKey = '46f0cd694581a';
            $passPhrase = '';
        }

        // Create description
        $description = '';
        foreach( $this->_basket['contents'] as $item )
            $description .= $item['quantity'] .' x '. $item['name'] .' @ '.
                number_format( $item['price']/$item['quantity'], 2, '.', ',' ) .'ea = '.
                number_format( $item['price'], 2, '.', ',' ) .'; ';  
        $description .= 'Shipping = '. $this->_basket['shipping']['value'] .'; ';
        $description .= 'Tax = '. $this->_basket['total_tax'] .'; ';
        $description .= 'Total = '. $this->_basket['total'];

		$hidden = array(
            // Merchant details
            'merchant_id' => $merchantId,
            'merchant_key' => $merchantKey,

            // Create URLs
            'return_url' => $GLOBALS['storeURL'].'/index.php?_a=complete',
            'cancel_url' => $GLOBALS['storeURL'].'/index.php?_a=confirm',
            'notify_url' => $GLOBALS['storeURL'] .'/index.php?_g=rm&type=gateway&cmd=call&module=PayFast',

            // Customer details
        	'name_first' => substr( trim( $this->_basket['billing_address']['first_name'] ), 0, 100 ),
        	'name_last' => substr( trim( $this->_basket['billing_address']['last_name'] ), 0, 100 ),
            'email_address' => substr( trim( $this->_basket['billing_address']['email'] ), 0, 255 ),

            // Item details
            'm_payment_id' => $this->_basket['cart_order_id'],
            'amount' => number_format($this->_basket['total'], 2, '.', '' ),
            'item_name' => $GLOBALS['config']->get('config', 'store_name') .' Purchase, Order #'. $this->_basket['cart_order_id'],
            'item_description' => substr( trim( $description ), 0, 255 ),
            'custom_str1' =>  PF_MODULE_NAME . '_' . PF_MODULE_VER
        );
        $pfOutput = '';
        foreach ( $hidden as $key => $val )
        {
            $pfOutput .= $key . '=' . urlencode(trim($val)) . '&';
        }
        empty( $passPhrase ) ? $pfOutput = substr( $pfOutput, 0, -1 ) : $pfOutput .= 'passphrase='. urlencode( $passPhrase );
        $pfSignature = md5( $pfOutput );         
        $hidden['signature'] = $pfSignature;
        $hidden['user_agent'] = PF_USER_AGENT;
		return ( $hidden );
	}
	/**
	 * call
     * @usage ITN function, will updat ethe order appropriately
     */
	public function call() {
        // Include PayFast common file
        define( 'PF_DEBUG', ( $this->_module['debug_log'] ? true : false ) );
        include_once( 'payfast_common.inc' );
        
        // Variable Initialization
        $pfError = false;
        $pfNotes = array();
        $pfData = array();
        $pfHost = ( ( $this->_module['testMode'] != 1 ) ? 'sandbox' : 'www' ) .'.payfast.co.za';
        $orderId = '';
        $pfParamString = '';
        $pfErrors = array();
        pflog( 'PayFast ITN call received' );
        
        //// Set debug email address
        $pfDebugEmail = ( strlen( $this->_module['debug_email'] ) > 0 ) ?
            $this->_module['debug_email'] : $this->_config->get('config', 'masterEmail');
        
        //// Notify PayFast that information has been received
        if( !$pfError )
        {
            header( 'HTTP/1.0 200 OK' );
            flush();
        }
        
        //// Get data sent by PayFast
        if( !$pfError )
        {
            pflog( 'Get posted data' );
        
            // Posted variables from ITN
            $pfData = pfGetData();
        
            pflog( 'PayFast Data: '. print_r( $pfData, true ) );
        
            if( $pfData === false )
            {
                $pfError = true;
                $pfNotes[] = PF_ERR_BAD_ACCESS;
            }
        }
        
        //// Verify security signature
        if( !$pfError )
        {
            pflog( 'Verify security signature' );
            // If signature different, log for debugging
            if( !pfValidSignature( $pfData, $pfParamString, $this->_module['passphrase']) )
            {
                $pfError = true;
                $pfNotes[] = PF_ERR_INVALID_SIGNATURE;
            }
        }
        
        //// Retrieve order from CubeCart
        if( !$pfError )
        {
            pflog( 'Get order' );
        
            $orderId = $pfData['m_payment_id'];
			$order				= Order::getInstance();
			$order_summary		= $order->getSummary($orderId);

            pflog( 'Order ID = '. $orderId );
        }
        
        //// Verify data
        if( !$pfError )
        {
            pflog( 'Verify data received' );
        
            if( $config['proxy'] == 1 )
                $pfValid = pfValidData( $pfHost, $pfParamString, $config['proxyHost'] .":". $config['proxyPort'] );
            else
                $pfValid = pfValidData( $pfHost, $pfParamString );
        
            if( !$pfValid )
            {
                $pfError = true;
                $pfNotes[] = PF_ERR_BAD_ACCESS;
            }
        }
        
        //// Check status and update order & transaction table
        if( !$pfError )
        {
            pflog( 'Check status and update order' );
        
            $success = true;
        
        	// Check the payment_status is Completed
        	if( $pfData['payment_status'] !== 'COMPLETE' )
            {
        		$success = false;
        
        		switch( $pfData['payment_status'] )
                {
            		case 'FAILED':
                        $pfNotes = PF_MSG_FAILED;
            			break;
        
        			case 'PENDING':
                        $pfNotes = PF_MSG_PENDING;
            			break;
        
        			default:
                        $pfNotes = PF_ERR_UNKNOWN;
            			break;
        		}
        	}
        
        	// Check if the transaction has already been processed
        	// This checks for a "transaction" in CubeCart of the same status (status)
            // for the same order (order_id) and same payfast payment id (trans_id)
			$trnId	= $GLOBALS['db']->select('CubeCart_transactions', array('id'), array('trans_id' => $pfData['pf_payment_id']));
        
        	if( $trnId == true )
            {
        		$success = false;
        		$pfNotes[] = PF_ERR_ORDER_PROCESSED;
        	}
        
        	// Check PayFast amount matches order amount
            if( !pfAmountsEqual( $pfData['amount_gross'], $order_summary['total'] ) )
            {
        		$success = false;
        		$pfNotes[] = PF_ERR_AMOUNT_MISMATCH;
        	}
        
            // If transaction is successful and correct, update order status
        	if( $success == true )
            {
        		$pfNotes[] = PF_MSG_OK;
				$order->paymentStatus(Order::PAYMENT_SUCCESS, $orderId);
				$order->orderStatus(Order::ORDER_PROCESS, $orderId);
        	}
        }
        
        //// Insert transaction entry
        // This gets done for every ITN call no matter whether successful or not.
        // The notes field is used to provide feedback to the user.
        pflog( 'Create transaction data and save' );
        
        $pfNoteMsg = '';
        if( sizeof( $pfNotes ) > 1 )
            foreach( $pfNotes as $note )
                $pfNoteMsg .= $note ."; ";
        else
            $pfNoteMsg .= $pfNotes[0];
        
        $transData = array();
        $transData['customer_id'] = $order_summary['customer_id'];
        $transData['gateway']     = "PayFast ITN";
        $transData['trans_id']    = $pfData['pf_payment_id'];
        $transData['order_id']    = $orderId;
        $transData['status']      = $pfData['payment_status'];
        $transData['amount']      = $pfData['amount_gross'];
        $transData['notes']       = $pfNoteMsg;
        
        pflog( "Transaction log data: \n". print_r( $transData, true ) );
        
        $order->logTransaction($transData);
        
        // Close log
        pflog( '', true );
	}

	/**
	 * process
     */
	public function process()
	{
		## We're being returned from PayFast - This function can do some pre-processing, but must assume NO variables are being passed around
		## The basket will be emptied when we get to _a=complete, and the status isn't Failed/Declined

		## Redirect to _a=complete, and drop out unneeded variables
		httpredir( currentPage( array( '_g', 'type', 'cmd', 'module' ), array( '_a' => 'complete' ) ) );
	}

	/**
	 * form
     */
	public function form()
	{
		return false;
	}
	// }}}
}