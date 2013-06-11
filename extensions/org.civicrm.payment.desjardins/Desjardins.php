<?php

/*
 +--------------------------------------------------------------------+
 | Desjardins Payment Gateway Processor (without redirection)         |
 +--------------------------------------------------------------------+
 | Copyright Mathieu Lutfy 2010-2012                                  |
 +--------------------------------------------------------------------+
 | This file is part of the Payment gateway extension for CiviCRM.    |
 |                                                                    |
 | IMPORTANT:                                                         |
 | This is a community contributed extension. It is not endorsed or   |
 | supported by neither Desjardins nor CiviCRM. Use at your own risk. |
 |                                                                    |
 | LICENSE:                                                           |
 | This extension is free software; you can copy, modify, and         |
 | distribute it under the terms of the GNU Affero General Public     |
 | License Version 3, 19 November 2007.                               |
 |                                                                    |
 | This extension is distributed in the hope that it will be useful,  |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of     |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License along with this program; if not, contact CiviCRM LLC       |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/*
 * TESTING: use 4530911100000990
 */

require_once 'CRM/Core/Payment.php';

class org_civicrm_payment_desjardins extends CRM_Core_Payment {
    const
        CHARSET  = 'UFT-8'; # (not used, implicit in the API, might need to convert?)

    const
        CIVICRM_DESJARDINS_LOG = TRUE; # Wheter to log all XML communication with the gateway

    /**
     * We only need one instance of this object. So we use the singleton
     * pattern and cache the instance in this variable
     *
     * @var object
     * @static
     */
    static private $_singleton = null;

    // IP of the visitor
    private $ip = 0;

    /**
     * Constructor
     *
     * @param string $mode the mode of operation: live or test
     *
     * @return void
     */
    function __construct( $mode, &$paymentProcessor ) {
        $this->_mode = $mode;
        $this->_paymentProcessor = $paymentProcessor;
        $this->_processorName = ts('Desjardins');

        $config = CRM_Core_Config::singleton( ); // get merchant data from config
        $this->_profile['mode'] = $mode; // live or test
        $this->_profile['storeid']  = $this->_paymentProcessor['user_name'];
        $this->_profile['apitoken'] = $this->_paymentProcessor['password'];
        $currencyID = $config->defaultCurrency;

        if ('CAD' != $currencyID) {
            // Configuration error: default currency must be CAD
            # [ML] FIXME $config->defaultCurrency returns USD...
            # return self::error('Invalid configuration: ' . $currencyID . ', you must use currency CAD with Desjardins');
        }
    }

    function purchase($tx_id, $tx_key, $amount, $cc_num, $cc_name, $cc_expyear, $cc_expmonth, $cc_email) {
    	$merchant_id = $this->_profile['storeid'];
    	$merchant_key = $this->_profile['apitoken'];
        $url_response = 'https://' . $_SERVER['SERVER_NAME'] . '/civicrmdesjardins/validate'; // XXX should use the correct variable for baseurl
   	$submit_url =  $this->_paymentProcessor['url_site'];

    	$amount = intval($amount * 100); // Ex: 15.24$ => 1524

        require_once 'CRM/Utils/System.php';
        $lcMessages = CRM_Utils_System::getUFLocale();

        if ($lcMessages) {
          $lcMessages = substr($lcMessages, 0, 2);
        }

        // don't take any risks, otherwise the transaction will fail
        if (! ($lcMessages == 'fr' || $lcMessages == 'en')) {
          $lcMessages = 'fr';
        }

        // Clean up CC number
        $cc_num = preg_replace('/[^0-9]/', '', $cc_num);

    	$xmlData = '';
    	$response = '';

    	$xmlData .= '<?xml version="1.0" encoding="UTF-8" ?>';
    	$xmlData .= '<request>';
    	$xmlData .=   '<merchant id="' .$merchant_id . '" key="' . $merchant_key . '">';
    	$xmlData .=     '<transactions>';
    	$xmlData .=       '<transaction id="' . $tx_id . '" key="' . $tx_key . '" type="purchase" currency="CAD" currencyText="$CAD">';
    	$xmlData .=         '<amount>' . $amount . '</amount>';
    	$xmlData .=         '<language>' . $lcMessages . '</language>';
    	$xmlData .=         '<card>';
    	$xmlData .=           '<number>' . $cc_num . '</number>';
    	$xmlData .=           '<holder_name>' . $cc_name . '</holder_name>';
    	$xmlData .=           '<expiry>';
    	$xmlData .=             '<year>' . $cc_expyear . '</year>';
    	$xmlData .=             '<month>' . $cc_expmonth . '</month>';
    	$xmlData .=           '</expiry>';
    	$xmlData .=         '</card>';
    	$xmlData .=         '<customer_email>' . $cc_email . '</customer_email>';
    	$xmlData .=         '<urls>';
    	$xmlData .=           '<url name="response">';
    	$xmlData .=             '<path>'.$url_response.'</path>';
    	$xmlData .=           '</url>';
    	$xmlData .=         '</urls>';
    	$xmlData .=       '</transaction>';
    	$xmlData .=     '</transactions>';
    	$xmlData .=   '</merchant>';
    	$xmlData .= '</request>';

        $this->djLog($tx_id, $xmlData, 'purchase send');

    	$header = array();
    	$header[] = "MIME-Version: 1.0";
    	$header[] = "Content-type: text/xml";
    	$header[] = "Accept: text/xml";
    	$header[] = "Content-length: " . strlen($xmlData);
    	$header[] = "Cache-Control: no-cache";
    	$header[] = "Connection: close";

    	$ch = curl_init();
    	curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    	curl_setopt($ch, CURLOPT_URL, $submit_url);
    	curl_setopt($ch, CURLOPT_VERBOSE, 0);
    	curl_setopt($ch, CURLOPT_POST, 1);
    	curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlData);
    	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
    	curl_setopt($ch, CURLOPT_NOPROGRESS, 1);
    	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
    	$response = curl_exec($ch);
    	curl_close($ch);

        $r = new CRM_Core_Payment_Desjardins_Response('Payment', $response);
        $this->djLog($tx_id, utf8_encode($response), 'purchase response', $r->isError());

        return $r;
    }

    /**
     * singleton function used to manage this object
     *
     * @param string $mode the mode of operation: live or test
     *
     * @return object
     * @static
     *
     */
    static function &singleton( $mode, &$paymentProcessor ) {
        $processorName = $paymentProcessor['name'];
        if (self::$_singleton[$processorName] === null ) {
            self::$_singleton[$processorName] = new org_civicrm_payment_desjardins( $mode, $paymentProcessor );
        }
        return self::$_singleton[$processorName];
    }


    function doDirectPayment( &$params ) {
      if (!function_exists('curl_init')) {
        return self::error('The Desjardins.com API service requires curl.  Please talk to your system administrator to get this configured.');
      }

      $this->ip = $params['ip_address'];

      # make sure i've been called correctly ...
      if ( ! $this->_profile ) {
          return self::error('Unexpected error, missing profile');
      }

      if ($params['currencyID'] != 'CAD') {
         # [ML] FIXME return self::error('Invalid currency selection, must be CAD');
      }

      // Fraud-protection: Validate the postal code
/* [ML] 2012-02-28 disabling due to client complaints. */
/*
      if (! $this->isValidPostalCode($params)) {
        watchdog('civicrmdesjardins', 'Invalid postcode for Canada: ' . print_r($params, 1));
        $this->djLog($params['invoiceID'], 'anti-fraud (CDJ002 invalid postcode): ' . print_r($params, 1), 'do_direct fraud', TRUE);
        return self::error(t("Error") . ": " . t('The transaction could not be processed, please contact us for more information.')
                      . ' (code: CDJ002) '
                      . '<div class="civicrm-dj-retrytx">' . t("The transaction was not approved. Please verify your credit card number and expiration date.") . '</div>');
      }
*/

      // Fraud-protection: Limit the number of transactions: 2 per 6 hours
      if ($this->isTooManyTransactions($params)) {
        watchdog('civicrmdesjardins', 'Too many transactions from: ' . $params['ip_address']);
        $this->djLog($params['invoiceID'], 'anti-fraud (CDJ003 too many transactions from IP): ' . print_r($params, 1), 'do_direct fraud', TRUE);
        return self::error(t("Error") . ": " . t('The transaction could not be processed, please contact us for more information.')
                      . ' (code: CDJ003) '
                      . '<div class="civicrm-dj-retrytx">' . t("The transaction was not approved. Please verify your credit card number and expiration date.") . '</div>');
      }

      if(!empty($params['amount'])){
	$amount = $params['amount'];
      }else{
        $amount = $params['amount_other'];
      }

      // Ex: if the donation amount is 0, membership only, and they are
      // both handled as separate transactions (although sounds like a civicrm bug)
      if ($amount <= 0) {
        return $params;
      }

      $cc_num   = $params['credit_card_number'];
      $cc_month = str_pad($params['month'], 2, '0', STR_PAD_LEFT);
      $cc_year  = substr($params['year'], -2);
      $cc_name  = $params['first_name'] . ' ' . $params['last_name'];
      $tx_email = $params['email'];

      // *************************** Request Variables ******************************
      $merchant_id   = $this->_profile['storeid'];
      $merchant_key  = $this->_profile['apitoken'];
      $invoice_id = $params['invoiceID'];

      // used to have a special CC number to make test transactions, but should be avoided..
      // if (! ($cc_num == '4111111111111111' && $cc_month == '03')) {
      // }

      $authresp = $this->doDesjardinsLogin($merchant_key, $invoice_id);
      $auth = $authresp->getData();

      if ($authresp->isError()) {
        // login failed, probably an error caused by the merchant site, not the user.
        return self::error(t("Error") . ": " . $authresp->getErrorMessage()
            . '<div class="civicrm-dj-retrytx">' . t("The transaction could not be processed. Please contact us.") . '</div>');
      }

      $purchaseresp = $this->purchase($invoice_id, $auth->merchant->login->trx['key'], $amount, $cc_num, $cc_name, $cc_year, $cc_month, $tx_email);
      $purchase = $purchaseresp->getData();

      if ($purchaseresp->isError() || (! $purchase->merchant->transaction->receipt)) {
        // this would be cleaner to just call self:error($purchaseresp)
        // and leave it to getErrorMessage() to generate the correct message
        // depending on the phase of the transaction (i.e. whether we need a receipt or not)
        return self::error($purchaseresp->getErrorMessage('short')
          . '<div class="civicrm-dj-retrytx">' . t("The transaction was not approved. Please verify your credit card number and expiration date.") . '</div>'
          . '<pre>' . $this->generateReceipt($invoice_id, $amount, $purchase, FALSE) . '</pre>', '9002-' . $purchaseresp->getResponseCode());
      }

      // Success
      $params['trxn_result_code'] = (string) $purchase->merchant->transaction->{'condition_code'}[0];
      $params['trxn_id']         = $invoice_id;
      $params['gross_amount']    = $amount;

      // Assigning the receipt to the $params doesn't really do anything
      // In previous versions, we would patch the core in order to show the receipt.
      // It would be nice to have something in CiviCRM core in order to handle this.
      $params['receipt_desjardins'] = $this->generateReceipt($invoice_id, $amount, $purchase);

      db_query("INSERT INTO {civicrmdesjardins_receipt} (trx_id, receipt, first_name, last_name, card_type, card_number, timestamp, ip)
                VALUES (:trx_id, :receipt, :first_name, :last_name, :card_type, :card_number, :timestamp, :ip)",
                array(
                  ':trx_id' => $invoice_id,
                  ':receipt' => $params['receipt_desjardins'],
                  ':first_name' => $params['first_name'],
                  ':last_name' => $params['last_name'],
                  ':card_type' => $params['credit_card_type'],
                  ':card_number' => preg_replace('/(\d{2})\d{10}(\d{4})/', '\1**********\2', $params['credit_card_number']),
                  ':timestamp' => time(),
                  ':ip' => $params['ip_address'],
               ));

      // Invoke hook_civicrmdesjardins_success($params, $purchase).
      module_invoke_all('civicrmdesjardins_success', $params, $purchase);

      return $params;
    }

    function doDesjardinsLogin($key, $id_trx) {
	$xmlData = '';
	$response = '';

	$isProduction = ($this->_profile['mode'] == 'live');
   	$submit_url =  $this->_paymentProcessor['url_site'];

	$xmlData .= '<?xml version="1.0" encoding="UTF-8" ?>'
                  . '<request>'
                  .   '<merchant key="'. $key .'">'
                  .     '<login><trx id="'. $id_trx .'" /></login>'
                  .   '</merchant>'
                  . '</request>';

        $this->djLog($id_trx, $xmlData, 'dj_login send');

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml"));
	curl_setopt($ch, CURLOPT_URL, $submit_url);
	curl_setopt($ch, CURLOPT_VERBOSE, 0);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlData);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
	curl_setopt($ch, CURLOPT_NOPROGRESS, 1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
	$response = curl_exec($ch);
	curl_close($ch);

        $r = new CRM_Core_Payment_Desjardins_Response('Login', $response);
        $this->djLog($id_trx, utf8_encode($response), 'dj_login response', $r->isError());

	return $r;
    }

    /**
     * Validate the postal code.
     * Returns TRUE if the postal code is valid.
     */
    function isValidPostalCode($params) {
      if ($params['country'] != 'CA') {
        return TRUE;
      }

      $province     = $params['state_province'];
      $postal_code  = $params['postal_code'];
      $postal_first = strtoupper(substr($postal_code, 0, 1));

      $provinces_codes = array(
        'AB' => array('T'),
        'BC' => array('V'),
        'MB' => array('R'),
        'NB' => array('E'),
        'NL' => array('A'),
        'NT' => array('X'),
        'NS' => array('B'),
        'NU' => array('X'),
        'ON' => array('K', 'L', 'M', 'N', 'P'),
        'PE' => array('C'),
        'QC' => array('H', 'J', 'G'),
        'SK' => array('S'),
        'YT' => array('Y'),
      );

      if (in_array($postal_first, $provinces_codes[$province])) {
        return TRUE;
      }

      return FALSE;
    }

    /**
     * Check whether the person (by IP address) has been doing too many transactions lately (2 tx in the past 6 hours)
     * Returns TRUE if there have been too many transactions
     */
    function isTooManyTransactions($params) {
      $ip = $params['ip_address'];

      $nb_tx_lately = db_query('SELECT count(*) from {civicrmdesjardins_receipt}
         WHERE ip = :ip and timestamp > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 1 HOUR))',
         array(':ip' => $ip))->fetchField();

      if ($nb_tx_lately >= 400) {
        return TRUE;
      }

      return FALSE;
    }

    /**
     * error : either an object that implements getResponseCode() and getErrorMessage, or a string.
     * errnum : if the error is a string, this should have the error number.
     */
    function &error( $error = null, $errnum = 9002 ) {
        $e =& CRM_Core_Error::singleton( );
        if ( is_object($error) ) {
            $e->push( $error->getResponseCode( ),
                      0, null,
                      $error->getErrorMessage( ) );
        } elseif ( is_string($error) ) {
            $e->push( $errnum,
                      0, null,
                      $error );
        } else {
            $e->push( 9001, 0, null, "Unknown System Error." );
        }
        return $e;
    }

    /**
     * This function checks to see if we have the right config values
     *
     * @return string the error message if any
     * @public
     */
    function checkConfig( ) {
        $error = array( );

        if ( empty( $this->_paymentProcessor['user_name'] ) ) {
            $error[] = ts( 'Merchant ID is not set in the Administer CiviCRM &raquo; Payment Processor.' );
        }

        if ( empty( $this->_paymentProcessor['password'] ) ) {
            $error[] = ts( 'Password is not set in the Administer CiviCRM &raquo; Payment Processor.' );
        }

        if ( ! empty( $error ) ) {
            return implode( '<p>', $error );
        } else {
            return null;
        }
    }

    // use only civicrmdesjardins_logxml() from civicrmdesjardins.module ?
    function djLog($trx_id, $message, $type, $fail = 0) {
      #if ($this->CIVICRM_DESJARDINS_LOG) {
        $time = time();
        $message = preg_replace('/<number>(\d{2})\d{10}(\d{4})<\/number>/', '<number>\1**********\2</number>', $message);

        // sometimes the field is empty, not 0
        if (! $fail) {
          $fail = 0;
        }

        db_query("INSERT INTO {civicrmdesjardins_log} (trx_id, timestamp, type, message, fail, ip)
                  VALUES (:trx_id, :timestamp, :type, :message, :fail, :ip)",
                  array(':trx_id' => $trx_id, ':timestamp' => $time, ':type' => $type, ':message' => $message, ':fail' => $fail, ':ip' => $this->ip));
      #}
    }

    /**
     * Generates a human-readable receipt using the purchase response from Desjardins.
     * trx_id : CiviCRM transaction ID
     * amount : numeric amount of the transcation
     * purchase : response from Desjardins (parsed in an array)
     * success : whether this is a receipt for a successful or failed transaction (not really used)
     */
    function generateReceipt($trx_id, $amount, $purchase, $success = TRUE) {
      $tx = $purchase->merchant->transaction;

      $receipt = $tx->receipt;
      $receipt = preg_replace("/^0/", "", $receipt);
      $receipt = preg_replace("/\n0/", "\n", $receipt);
      $receipt = preg_replace("/\n1/", "\n", $receipt); // in failed transaction receipts

      if (function_exists('variable_get')) {
        $tos_url  = variable_get('civicrmdesjardins_tos_url', FALSE);
        $tos_text = variable_get('civicrmdesjardins_tos_text', FALSE);

        if ($tos_url) {
          $receipt .= "\n\n";
          $receipt .= t("Terms and conditions:") . "\n";
          $receipt .= $tos_url . "\n\n";
        }

        if ($tos_text) {
          $receipt .= wordwrap($tos_text);
        }
      }

      // Add obligatory notes:
      $receipt .= "\n";
      $receipt .= t("Prices are in canadian dollars ($ CAD).") . "\n";
      $receipt .= t("This donation is non-taxable.") . "\n\n";

      // Fetch the domain name, but allow to override it (Desjardins requires that it
      // be the exact business name of the org, and sometimes we use shorter names.
      $org_name = variable_get('civicrmdesjardins_orgname', NULL);

      if (! $org_name) {
        $results = civicrm_api("Domain","get", array ('version' =>'3'));
        $org_name = $results['values'][1]['name'];
      }

      // Show the card owner next to the card number
      $cardholder = t('Card Holder Name: !name', array('!name' => $tx->{'card_holder_name'}));
      $receipt = preg_replace("/\nNo. /", "\n" . $cardholder . "\nNo. ", $receipt);

      $receipt = $org_name . "\n\n"
        . "Transaction: " . $trx_id . "\n"
    	. t("Authorization:") . " " . $tx->{'authorization_no'} . "\n"
        . t("Reference:") . " " . $tx->{'sequence_no'} . ' ' . $tx->{'terminal_id'} . "\n\n"
    	. $receipt;

      return $receipt;
    }
}

/**
 * Class for parsing XML requests
 */
class CRM_Core_Payment_Desjardins_Response {
  private $data;
  private $currentTag;
  private $parser;
  private $stage; // for debug only (description of the response we are parsing)
  private $error; // boolean
  private $errno; // error code
  private $errstr; // error message

  function CRM_Core_Payment_Desjardins_Response($stage, $xmlString) {
    $this->stage = $stage; // Login, Payment or Confirm
    $this->error = false;
    $this->errno = 0;
    $this->errstr = 'n/a';

    $data = new SimpleXMLElement($xmlString, LIBXML_NOCDATA);

    if ($data->error->count()) {
      $this->error  = TRUE;
      $this->errno  = $data->error->code;
      $this->errstr = $data->error->message;
    }
    elseif ($data->merchant->transaction['approved'] == 'no') {
      $this->error  = TRUE;
      $this->errno  = $data->merchant->transaction->{'condition_code'};
      $this->errstr = $data->merchant->transaction->{'receipt_text'};
    }

    $this->data = $data;
  }

  function getData() {
    return $this->data;
  }

  function isError() {
    return $this->error;
  }

  function getErrorMessage($format = 'full') {
    if ($format == 'full') {
      return $this->errno . ': ' . $this->errstr;
    }
    else {
      return $this->errstr;
    }
  }

  // inconsistant: errno vs response code. we should probably just have
  // a response code, and rely on isError() to know if it's an error.
  function getResponseCode() {
    return $this->errno;
  }

  // TODO: place this elsewhere.. was here before because
  // we were using xml_parser functions before moving to SimpleXML

  /**
   * Login response handler.
   *
   * A typical success response is as follows:
   * <response>
   *   <merchant id="123456" key="12312312312312312312312312312312">
   *     <login>
   *       <trx id="123456789" key="34534534535434534534534534534534"/>
   *     </login>
   *   </merchant>
   * </response>
   *
   * Typical error responses:
   * Some fields were incorrectly formatted:
   * <response>
   *   <error>
   *     <code>WX03</code>
   *     <message><![CDATA[ Validation Error ]]></message>
   *   </error>
   * </response>
   *
   */

  /**
   * Payment response handler.
   *
   * A typical success response is as follows:
   * <response input="xml">
   *   <merchant id="123456">
   *     <transaction id="34534534534534534534534534534534" currency="CAD" currencyText="CAD $ " approved="yes">
   *       <terminal_id>05123456</terminal_id>
   *       <urls>
   *         <url name="success">
   *           <path>NotUse</path>
   *           <parameters>
   *             <parameter name="TrxId">1234567890</parameter>
   *           </parameters>
   *         </url>
   *       </urls>
   *       <ecr_number>05987651</ecr_number>
   *       <amount>1000</amount>
   *       <language>FR</language>
   *       <card_holder_name>John Doe</card_holder_name>
   *       <date>12/01/06 10:14:24</date>
   *       <effective_date>120107</effective_date>
   *       <transaction_code>0</transaction_code>
   *       <condition_code>1</condition_code>
   *       <iso_code>00</iso_code>
   *       <host_code>000</host_code>
   *       <action_code>1</action_code>
   *       <card_type>VISA</card_type>
   *       <batch_no>001</batch_no>
   *       <sequence_no>0010010111</sequence_no>
   *       <process_info>T@1</process_info>
   *       <authorization_no>101123</authorization_no>
   *       <receipt_text>APPROUVEE - MERCI</receipt_text>
   *       <receipt><![CDATA[0RELEVE DE TRANSACTION/TRANSACTION RECORD
   *         0
   *         0TPVEV000001  MARCH05123450
   *         0          ORG NAME
   *         0          1234 ADDRESS
   *         0          MONTREAL, QC
   *         0
   *         0Carte/Card:VISA
   *         0No.    45** **** **** 0990 14/10
   *         0
   *         0Seq.: 0011  Lot/Batch: 001
   *         02012/01/06  10:14  T@1
   *         0
   *         0ACHAT/PURCHASE         $10.00
   *         0AUTOR./AUTHOR.: 101234
   *         0
   *         0
   *         0             00 APPROUVEE - MERCI
   *
   *         ]]>
   *       </receipt>
   *     </transaction>
   *   </merchant>
   * </response>
   *
   * ERROR:
   * <response input="xml">
   *   <merchant id="123456">
   *     <transaction id="99c6f1790c608033c5193dcf6f5b08a8" currency="CAD" currencyText="CAD $ " approved="no">
   *       <terminal_id>05123450</terminal_id>
   *       <ecr_number>05123460</ecr_number>
   *       <language>FR</language>
   *       <card_holder_name>John Doe</card_holder_name>
   *       <date>12/01/06 11:38:50</date>
   *       <transaction_code>0</transaction_code>
   *       <condition_code>117</condition_code>
   *       [...]
   *       <receipt_text>TRANSACTION NON COMPLETEE</receipt_text>
   *       <receipt><![CDATA[0RELEVE DE TRANSACTION/TRANSACTION RECORD
   *         0
   *         0TPVEV000001  MARCH05028430
   *         0          DEVELOPPEMENTETPAIX
   *         0          1425 BOUL RENE-LEVES
   *         0          MONTREAL, QC
   *         0
   *         0Carte/Card:
   *         0No.    45** **** **** 0990 01/12
   *         0
   *         0Seq.:       Lot/Batch:
   *          12012/01/06  11:38  T@1
   *         1
   *         1ACHAT/PURCHASE          $0.00
   *         1AUTOR./AUTHOR.:
   *         1
   *         1           XX TRANSACTION NON COMPLETEE
   *        ]]>
   *       </receipt>
   *     </transaction>
   *   </merchant>
   * </response>
   *
   * <?xml version="1.0" encoding="ISO-8859-15"?>
   * <response>
   *   <error>
   *     <code>WE15</code>
   *     <message><![CDATA[ Identificateur de transaction deja utilise ]]></message>
   *   </error>
   * </response>
   */

  /**
   * Confirm request handler.
   * This is a request done by Desjardins to our server, in order to confirm
   * that the transaction is legitimate.
   *
   * A typical confirm request is as follows:
   * <?xml version="1.0" encoding="ISO-8859-15"?>
   * <request input="xml">
   *   <merchant id="123456">
   *     <confirm>
   *       <transaction id="0c7e99559a190e105b4866efa2f21075" />
   *     </confirm>
   *   </merchant>
   * </request>
   */
}

