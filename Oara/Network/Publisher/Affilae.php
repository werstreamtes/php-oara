<?php

namespace Oara\Network\Publisher;
/**
 * The goal of the Open Affiliate Report Aggregator (OARA) is to develop a set
 * of PHP classes that can download affiliate reports from a number of affiliate networks, and store the data in a common format.
 **/

/**
 * Export Class
 *
 * @author     Jessica Capuozzo
 * @category   Affilae
 * @version    Release: 01.00
 *
 */
class Affilae extends \Oara\Network
{

    private $_credentials = null;

    /**
     * @param $credentials
     */
    public function login($credentials)
    {
        $this->_credentials = $credentials;
    }

    /**
     * Check the connection
     */
    public function checkConnection()
    {
        return true;
    }

    /**
     * (non-PHPdoc)
     * @see library/Oara/Network/Interface#getMerchantList()
     */
    public function getMerchantList()
    {
        // NOT IMPLEMENTED YET
        $merchants = array();
        return $merchants;
    }

    /**
     * @param null $merchantList array of merchants id to retrieve transactions (empty array or null = all merchants)
     * @param \DateTime|null $dStartDate
     * @param \DateTime|null $dEndDate
     * @return array
     * @throws \Exception
     */
    public function getTransactionList($merchantList = null, \DateTime $dStartDate = null, \DateTime $dEndDate = null)
    {
        $totalTransactions = array();
        $affiliate_id = $this->_credentials['user'];
        $password = $this->_credentials['password'];

        if (!empty($password)) {
            $url_endpoint = 'https://rest.affilae.com/publisher/';
            $version = '';
            $limit = 1000;
            $offset = 0;
            $loop = true;
            $attempts = 0;

            while ($loop){
                $url = $url_endpoint . $version . "conversions.list";
                $params = array(
                    new \Oara\Curl\Parameter('affiliateProfile', $affiliate_id), //required
                    new \Oara\Curl\Parameter('from', $dStartDate->format("c")), //string <date>
                    new \Oara\Curl\Parameter('to', $dEndDate->format("c")), //string <date>
                    new \Oara\Curl\Parameter('limit', $limit), //default 20, max. 1000.
                    new \Oara\Curl\Parameter('offset', $offset)
                );
                $p = array();
                foreach ($params as $parameter) {
                    $p[] = $parameter->getKey() . '=' . \urlencode($parameter->getValue());
                }
                $get_params = implode('&', $p);
                $url = $url . '?' . $get_params;

                // set the request authentication headers -- set the content type to application/json
                $headers = array(
                    'Content-Type:application/json;charset=UTF-8',
                    'Authorization: Bearer ' . $password,
                );

                $ch = curl_init();
                // set curl options
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POST, false);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
                // execute curl
                $response = curl_exec($ch);
                $error = curl_errno($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                curl_close($ch);
                if (!empty($response)) {
                    $transactionList = json_decode($response, true);
                    if ($http_code == 429 && $attempts < 5){
                        //too many requests - try again in 30 seconds
                        sleep(30);
                        $attempts++;
                        continue;
                    }
                    elseif (5 == $attempts){
                        throw new \Exception("[Affilae][getTransactionList][Exception] 429  Too many requests " . $response);
                    }
                    if (is_array($transactionList) && count($transactionList) > 0) {
                        foreach ($transactionList['conversions']['data'] as $transaction) {
                            $transactionArray = Array();
                            $sub_id = '';
                            $transactionArray['unique_id'] = $transaction['id'] .'|'.$transaction['externalId'];
                            if (isset($transaction['subId']) && !empty($transaction['subId']) && ($transaction['subId'] != 'yes') && ($transaction['subId'] != 'no') && ($transaction['subId'] != 'N/A')){
                                $transactionArray['custom_id'] = $transaction['subId'];
                            }
                            elseif (isset($transaction['events'])){
                                foreach ($transaction['events'] as $event){
                                    if (isset($event['click']['subId']) && !empty($event['click']['subId']) && ($event['click']['subId'] != 'yes') && ($event['click']['subId'] != 'no') && ($event['click']['subId'] != 'N/A')){
                                        if (str_contains($event['click']['subId'], '?')) {
                                            preg_match('~subid=(.*)~', $event['click']['subId'], $a_matches);
                                            if (is_array($a_matches) && isset($a_matches[1])){
                                                $transactionArray['custom_id'] = $a_matches[1];
                                            }
                                        }
                                        else{
                                            $transactionArray['custom_id'] = $event['click']['subId'];
                                        }
                                        break;
                                    }
                                    elseif (isset($event['click']['landingPage']) && !empty($event['click']['landingPage'])){
                                        //extract subid from landingPage
                                        $landing_page = $event['click']['landingPage'];
                                        preg_match('~subid%3D(.*?)%26~', $landing_page, $a_matches);
                                        if (is_array($a_matches) && isset($a_matches[1])){
                                            $sub_id = $a_matches[1];
                                        }
                                        else{
                                            preg_match('~subid=(.*?)&~', $landing_page, $a_matches);
                                            if (is_array($a_matches) && isset($a_matches[1])){
                                                $sub_id = $a_matches[1];
                                            }
                                        }
                                        if (!empty($sub_id)){
                                            $transactionArray['custom_id'] = $sub_id;
                                            break;
                                        }
                                    }
                                }
                            }
                            else{
                                $transactionArray['custom_id'] = '';
                                echo '[php-oara][Oara][Network][Publisher][Affilae][getTransactionList] subid not found - transaction: ' . $transaction . PHP_EOL;
                            }
                            if (isset($transaction['firstEventAt'])){
                                $transactionArray['click_date'] = new \DateTime($transaction['firstEventAt']);
                            }
                            $transactionArray['merchantId'] = $transaction['program']['id'];
                            $transactionArray['merchantName'] = $transaction['program']['name'];
                            $transactionArray['date'] = new \DateTime($transaction['createdAt']);
                            if (strtolower($transaction['status']) == 'pending') {
                                $transactionArray['status'] = \Oara\Utilities::STATUS_PENDING;
                            } elseif (strtolower($transaction['status']) == 'accepted') {
                                $transactionArray['status'] = \Oara\Utilities::STATUS_CONFIRMED;
                            } elseif (strtolower($transaction['status']) == 'refused') {
                                $transactionArray['status'] = \Oara\Utilities::STATUS_DECLINED;
                            } else {
                                throw new \Exception("[Affilae][getTransactionList] - unexpected transaction status {$transaction['status']}");
                            }
                            $transactionArray['currency'] = $transaction['currency'];
                            if (isset($transaction['amount'])){
                                //All monetary amounts are sent/returned in cents, i.e. 100 equals to 1.00.
                                $transactionArray['amount'] = \Oara\Utilities::parseDouble($transaction['amount'] / 100);
                            }
                            //All monetary amounts are sent/returned in cents, i.e. 100 equals to 1.00.
                            $transactionArray['commission'] = \Oara\Utilities::parseDouble($transaction['commissionTotal'] / 100);
                            $totalTransactions[] = $transactionArray;
                        }
                    }
                }
                if ($transactionList['conversions']['total'] < $limit || count($totalTransactions) >= $transactionList['conversions']['total']){
                    $loop = false;
                }
                else{
                    $loop = true;
                    $offset = $offset + $limit;
                }
            }
        }

        return $totalTransactions;
    }

    public function getVouchers()
    {
        throw new \Exception("Not implemented yet");
    }

}
