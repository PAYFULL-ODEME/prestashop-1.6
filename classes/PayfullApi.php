<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of PayfullApi
 *
 * @author houmam
 */
class PayfullApi
{
    protected $apikey;
	protected $secret;
	protected $endpoint;
	protected $force3D;
	protected $enable3D;
	protected $force3DForDebit;
	protected $enableInstallment;
	protected $merchantPayFees;
	protected $language;
    
    public function __construct($config=[]) {
        foreach($config as $key=>$value) {
            if(!property_exists($this, $key)) {
                throw new Exception('Getting unknown property: ' . get_class($this) . '::' . $key);
            }
            $this->$key = $value;
        }
    }
    
    public static function instance($module, $lang='tr')
    {        
        return new PayfullApi([
            'apikey' => $module->apikey,
            'secret' => $module->secret,
            'endpoint' => $module->endpoint,
            'force3D' => $module->force3D,
            'enable3D' => $module->enable3D,
            'force3DForDebit' => $module->force3DForDebit,
            'enableInstallment' => $module->enableInstallment,
            'merchantPayFees' => $module->merchantPayFees,
            'language' => $lang,
        ]);
    }
    
    public function bin($bin)
    {
        $response = $this->sendRequest('Get', [
            'bin' => $bin,
            'get_param' => 'Installments',
        ]);
        $response = json_decode($response,true);
        $issuer_data=$this->issuer($bin);
        $issuer = $issuer_data['data']['bank_id'];
        $network = $issuer_data['data']['network'];
        $type = $issuer_data['data']['type'];
        $installments=[];
        foreach ($response['data'] as $key => $value) {
            if($value['bank']==$issuer)
            {
                $value['brand']=$network;
                $value['type']=$type;
                $installments=$value;
            }
        }
        if(!$this->enableInstallment) {
            foreach($response['data'] as &$data) {
                foreach ($data['installments'] as $opt) {
                    if($opt['count']==1) {
                        $data['installments'] = [$opt];
                        break;
                    }
                }
            }
        }
        if($this->merchantPayFees) {
            foreach ($installments as &$opt) {
                $opt['commission'] = 0;
                $opt['percentage'] = "0%";
            }
        }
        return $installments;
    }
    
    public function issuer($bin)
    {
        $response = $this->sendRequest('Get', [
            'bin' => $bin,
            'get_param' => 'Issuer',
        ]);
        $response = json_decode($response,true);
        return $response;
    }
    
    public function pay($request)
    {
        $response = $this->sendRequest('Sale', $request);
        if(strpos($response, '<form') !== false || strpos($response, '</form>')) {
            $html=$response;
            $response=[];
            $response['status']=1;
            $response['html']=$html;
        }
        else {
            $response=json_decode($response,true);
        }
        return $response;
    }
    
    public function use3D($bin, $installment, $defaultValue=true)
    {
        if($this->force3D) {
            return true;
        }
        if($this->force3DForDebit && ($cardInfo = $this->issuer($bin))) {
            $type = isset($cardInfo['data']['type']) ? $cardInfo['data']['type'] : '';
            if(strtolower($type)==='debit') {
                return true;
            }
        }
        if($this->enable3D) {
            return $defaultValue;
        }
        return false;
    }
    
    public function getPaymentCommission($bin, $installment)
    {
        if($this->merchantPayFees) {
            return 0;
        }
        $result = $this->bin(substr($bin, 0, 6));
        if(is_array($result))
        {
            foreach ($result['installments'] as $key => $value) {
                if($value['count']==$installment)
                {
                    return floatval($value['commission']);
                }
            }
            return false;
        }
        return false;
    }
    
    protected function sendRequest($op, $data)
    {
        $data['type'] = $op;
        $data['merchant'] = $this->apikey;
        $data['language'] = $this->language;
        $data['client_ip'] = $this->getClinetIp();
        $data['hash'] = $this->hash($data);
        $response = self::post($this->endpoint, $data);
        if($this->enable3D)
        {
            return $response;
        }
        else {
            $json = json_decode($response, true);
            return $json;
        }
    }
    
    public function hash($data)
    {
        ksort($data);
        $hashString = "";
        foreach ($data as $key=>$val) {
            $l = mb_strlen($val);
            $hashString .= $l . $val;
        }
        return hash_hmac("sha256", $hashString,$this->secret);
    }

    protected static function post($url, $data=array())
    {
        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_ENCODING       => "",
            CURLOPT_USERAGENT      => "curl",
            CURLOPT_AUTOREFERER    => true,
            CURLOPT_CONNECTTIMEOUT => 120,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_CUSTOMREQUEST  => "POST",
        );

        // $url = "https://dev.payfull.com/integration/api/v1";
        $curl = curl_init($url);
        curl_setopt_array($curl, $options);
        if (_PS_MODE_DEV_ === false) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        }
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        $content    = curl_exec($curl);
        $error      = curl_error($curl);
        curl_close($curl);

        if($content === false) {
            throw new Exception(strtr('Error occured in sending data to Payfull/Portal: {error}', array(
                '{error}' => $error,
            )));
        }
        return $content;
    }
    
    protected  function getClinetIp()
    {
        $ip = '192.168.0.1';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        return $ip;
    }
    
    public static function getCardMask($pan)
    {
        $bin = substr($pan, 0, 6);
        $card = substr($pan, -4);
        $asterisk = str_repeat('*', strlen($pan) - 10);
        return $bin.$asterisk.$card;
    }
    
    public static function processPayResponse($module, $response=[], $use3D=false)
    {
        $status = isset($response['status']) ? intval($response['status']) : 0;
        $code = isset($response['ErrorCode']) ? $response['ErrorCode'] : 0;
        $message = isset($response['ErrorMSG']) ? $response['ErrorMSG'] : $module->l('Unexpected error occurred while processing payment transaction');
        $trix = isset($response['transaction_id']) ? $response['transaction_id'] : null;
        $total = isset($response['total']) ? floatval($response['total']) : 0;
        $currency = isset($response['currency']) ? $response['currency'] : 0;
        $installments = isset($response['installments']) ? $response['installments'] : 0;
        $originalTotal = isset($response['original_total']) ? $response['original_total'] : 0;
        $originalCurrency = isset($response['original_currency']) ? $response['original_currency'] : 0;
        $exchangeRate = isset($response['conversion_rate']) ? $response['conversion_rate'] : 1;
        $paymentInfo = isset($response['passive_data']) ? json_decode($response['passive_data'], true) : [];
        $hash = isset($response['hash']) ? $response['hash'] : null;
        
        unset($response['hash'], $response['_csrf']);
        
        $log_id = isset($paymentInfo['logId']) ? $paymentInfo['logId'] : null;
        $fee = isset($paymentInfo['fee']) ? floatval($paymentInfo['fee']) : 0;
        $cart_id = isset($paymentInfo['cartId']) ? $paymentInfo['cartId'] : 0;
        $order_id = isset($paymentInfo['orderId']) ? $paymentInfo['orderId'] : 0;
        $orderTotal = isset($paymentInfo['orderTotal']) ? $paymentInfo['orderTotal'] : 0;
        $currency_id = isset($paymentInfo['currencyId']) ? $paymentInfo['currencyId'] : 0;
        
        $exception = null;
        $customer = Context::getContext()->customer;
        
        try {
            $paidAmount = $total/$exchangeRate;
            if($paidAmount - ($orderTotal+$fee) > 0.01) {
                throw new Exception($module->l("Invalid paid amount. Please contact us to review your order."));
            }
            
            if($status && !$use3D) {
                // place the order for none 3D secure.
                $orderStatus = Configuration::get('PS_OS_PAYMENT');
                $module->validateOrder($cart_id, $orderStatus, $orderTotal, $module->displayName, $message, ['transaction_id'=>$trix], $currency_id, false, $customer->secure_key);
                //$order = Order::getOrderByCartId($cart_id);
                $order_id = $module->currentOrder;
            }
            
            //if(!$order) {
                $order = new Order($order_id);
            //}
            if(!Validate::isLoadedObject($order)) {
                throw new Exception($module->l("Invalid response. No matching order found"));
            }
            
            $paidStatus = (int)Configuration::get('PS_OS_PAYMENT');
            $pendingStatus = (int)Configuration::get('PS_OS_PAYFULL_PENDING');
            $currentState = $order->getCurrentOrderState();
            
            if($use3D && $currentState->id === $paidStatus) {
                throw new Exception($module->l("The order is already paid."));
            }
            
            self::addNewMessage($cart_id, $order_id, $customer->id, $message. " (code: $code, transaction: $trix)", true);
            
            if($status !==1) {
                $order->setCurrentState(Configuration::get('PS_OS_ERROR'));
                throw new Exception($message . " (code: $code)");
                //$order->addOrderPayment(0, null, $trix);
            } else {
                if($use3D) {
                    $order->addOrderPayment($orderTotal, null, $trix);
                    $history = new OrderHistory();
                    $history->id_order = $order->id;
                    $history->changeIdOrderState((int)Configuration::get('PS_OS_PAYMENT'), $order, TRUE /*use existing invoice*/);
                    $history->addWithemail(true, []);
                }
                self::addNewMessage($cart_id, $order_id, $customer->id, $module->l("Payment Information"). " (Installment: $installments, Fee: $fee)", false);
            }

            
        } catch (Exception $ex) {
            $status = 0;
            $message .= "  >>> ERROR: ".$ex->getMessage();
            $exception = $ex;
        } finally {
            if($log_id) {
                Db::getInstance()->update('payfull_transaction', [
                    'status' => (int)$status,
                    'order_id' => $order_id,
                    'transaction_id' => $trix,
                    'response_code' => $code,
                    'response_message' => $message,
                    'installment' => $installments,
                    'payment_currency' => $currency,
                    'exchange_rate' => $exchangeRate,
                    'updated' => date('Y-m-d H:i:s'),
                ], 'id = ' . $log_id);
            }
        }   
        
        if($exception) {
            throw $exception;
        }
        
        return true;
    }

    protected function editOrderPayment($amount_paid, $payment_method = null, $orderId , $payment_transaction_id = null, $currency = null, $date = null, $order_invoice = null){
        $order_payment = new OrderPayment();
        $order_payment->order_reference = $this->reference;
        $order_payment->id_currency = ($currency ? $currency->id : $this->id_currency);
        // we kept the currency rate for historization reasons
        $order_payment->conversion_rate = ($currency ? $currency->conversion_rate : 1);
        // if payment_method is define, we used this
        $order_payment->payment_method = ($payment_method ? $payment_method : $this->payment);
        $order_payment->transaction_id = $payment_transaction_id;
        $order_payment->amount = $amount_paid;
        $order_payment->date_add = ($date ? $date : null);

        // Add time to the date if needed
        if ($order_payment->date_add != null && preg_match('/^[0-9]+-[0-9]+-[0-9]+$/', $order_payment->date_add)) {
            $order_payment->date_add .= ' '.date('H:i:s');
        }

        // Update total_paid_real value for backward compatibility reasons
        if ($order_payment->id_currency == $this->id_currency) {
            $this->total_paid_real += $order_payment->amount;
        } else {
            $this->total_paid_real += Tools::ps_round(Tools::convertPrice($order_payment->amount, $order_payment->id_currency, false), 2);
        }

        // We put autodate parameter of add method to true if date_add field is null
        $res = $order_payment->add(is_null($order_payment->date_add)) && $this->update();

        if (!$res) {
            return false;
        }

        if (!is_null($order_invoice)) {
            $res = Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'order_invoice_payment SET id_order_invoice = `'.(int)$order_invoice->id.', id_order_payment = `'.(int)$order_payment->id.'` WHERE id_order = `'.(int)$this->id.'');
            // Clear cache
            Cache::clean('order_invoice_paid_*');
        }
    }

    protected static function addNewMessage($cartId, $orderId, $customerId, $message, $private=true)
    {
        $msg = new Message();
        $msg->message = $message;
        $msg->id_cart = $cartId;
        $msg->id_customer = $customerId;
        $msg->id_order = $orderId;
        $msg->private = 1;
        $msg->add();
    }
}
