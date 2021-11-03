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
 * @category   TargetCircle
 * @version    Release: 01.00
 *
 */
class TargetCircle extends \Oara\Network
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
        $password = $this->_credentials['password'];

        if (!empty($password)) {
            $url_endpoint = 'https://api.targetcircle.com/api/';
            $version = 'v1/';
            $offset = 0;
            $limit = 50; //max: 50
            $loop = true;
            // savedTo (+ 1day)
            $dEndDate->add(new \DateInterval('P1D'));
            while ($loop){
                $url = $url_endpoint . $version . "transactions";
                $params = array(
                    new \Oara\Curl\Parameter('savedFrom', $dStartDate->format("Y-m-d")), //yyyy-mm-dd
                    new \Oara\Curl\Parameter('savedTo', $dEndDate->format("Y-m-d")), //yyyy-mm-dd
                    new \Oara\Curl\Parameter('currency', 'EUR'),
                    new \Oara\Curl\Parameter('offset', $offset),
                    new \Oara\Curl\Parameter('limit', $limit), //Specify the maximum number of records to be retrieved per page. This value must be between 1 and 50.
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
                    'X-Api-Token:' . $password,
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
                if ($http_code != 200){
                    throw new \Exception("[TargetCircle][getTransactionList][Exception] http code: " . $http_code);
                }
                if (!empty($response)) {
                    $transactionList = json_decode($response, true);
                    if (is_array($transactionList) && count($transactionList) > 0) {
                        foreach ($transactionList['data'] as $transaction) {
                            $transactionArray = Array();
                            $transactionArray['unique_id'] = $transaction['transactionId'];
                            if (isset($transaction['clickId']) && !empty($transaction['clickId'])){
                                $transactionArray['custom_id'] = $transaction['clickId'];
                            }
                            elseif (isset($transaction['reference']['ref1']) && !empty($transaction['reference']['ref1'])){
                                $transactionArray['custom_id'] = $transaction['reference']['ref1'];
                            }
                            else{
                                $transactionArray['custom_id'] = '';
                            }
                            $transactionArray['click_date'] = new \DateTime($transaction['clickSaved'], new \DateTimeZone('UTC'));
                            $transactionArray['merchantId'] = $transaction['offerSid'];
                            $transactionArray['merchantName'] = $transaction['offer'];
                            $transactionArray['date'] = new \DateTime($transaction['saved'], new \DateTimeZone('UTC'));
                            if (isset($transaction['validationDate']) && !empty($transaction['validationDate'])){
                                $transactionArray['update_date'] = new \DateTime($transaction['validationDate'], new \DateTimeZone('UTC'));
                            }
                            if (strtolower($transaction['status']) == 'pending' || (strtolower($transaction['status']) == 'approved')) {
                                $transactionArray['status'] = \Oara\Utilities::STATUS_PENDING;
                            } elseif (str_contains(strtolower($transaction['status']), 'paid') || str_contains(strtolower($transaction['status']), 'invoiced')) {
                                $transactionArray['status'] = \Oara\Utilities::STATUS_CONFIRMED;
                            } elseif (strtolower($transaction['status']) == 'declined' || strtolower($transaction['status']) == 'failed') {
                                $transactionArray['status'] = \Oara\Utilities::STATUS_DECLINED;
                            } else {
                                throw new \Exception("[TargetCircle][getTransactionList] - unexpected transaction status {$transaction['status']}");
                            }
                            $transactionArray['currency'] = $transaction['currency'];
                            $transactionArray['amount'] = \Oara\Utilities::parseDouble($transaction['transactionAmount']);
                            $transactionArray['commission'] = \Oara\Utilities::parseDouble($transaction['payout']);
                            $totalTransactions[] = $transactionArray;
                        }
                    }
                }
                if (count($transactionList['data']) < $limit){
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
