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
 * @category   Adtraction
 * @version    Release: 01.00
 *
 */
class Adtraction extends \Oara\Network
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
            $url_endpoint = 'https://api.adtraction.com/';
            $version = 'v2/';
            $url = $url_endpoint . $version . "affiliate/transactions?token=" . $password;
            $fields = [
                'fromDate'          => $dStartDate->format("Y-m-d H:i:s:u"), //2021-05-01T07:35:07+0000
                'toDate'            => $dEndDate->format("Y-m-d H:i:s:u"),
                'transactionStatus' => "0" // means all transactions status
            ];
            $fields_string = json_encode($fields);
            // set the request authentication headers -- set the content type to application/json
            $headers = array(
                'Content-Type:application/json;charset=UTF-8',
            );
            $ch = curl_init();
            // set curl options
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POST, true);
            // Attach encoded JSON string to the POST fields
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            // execute curl
            $response = curl_exec($ch);
            if (!empty($response)) {
                $transactionList = json_decode($response, true);
                if (is_array($transactionList) && count($transactionList) > 0) {
                    foreach ($transactionList as $transaction) {
                        if (isset($transactionList['status']) && $transactionList['status'] == 400){
                            /*
                             * https://adtractionapi.docs.apiary.io/#introduction/response-codes-and-statuses
                             * The request could not be understood by the server due to malformed syntax.
                             */
                            throwException($transactionList['message']);
                        }
                        else if (!isset($transaction['uniqueId'])){
                            continue;
                        }
                        $transactionArray = Array();
                        $transactionArray['unique_id'] = $transaction['uniqueId'];
                        if (isset($transaction['click'])){
                            $transactionArray['merchantId'] = $transaction['click']['programId'];
                            $transactionArray['merchantName'] = $transaction['click']['programName'];
                            $transactionArray['custom_id'] = $transaction['click']['epi'];
                            $transactionArray['click_date'] = $transaction['click']['clickDate'];
                        }
                        $transactionArray['date'] = $transaction['transactionDate'];
                        if (isset($transaction['lastUpdated'])){
                            $transactionArray['update_date'] = $transaction['lastUpdated'];
                        }
                        //The status of the transactions to be included in the dataset. Impressions and clicks are not affected by this input. 1 = approved, 2 = pending, 3 = approved + pending, 4 = open claims, 5 = rejected
                        if ($transaction['transactionStatus'] == '2' || $transaction['transactionStatus'] == '3' || $transaction['transactionStatus'] == '4') {
                            $transactionArray['status'] = \Oara\Utilities::STATUS_PENDING;
                        } elseif ($transaction['transactionStatus'] == '1') {
                            $transactionArray['status'] = \Oara\Utilities::STATUS_CONFIRMED;
                        } elseif ($transaction['transactionStatus'] == '5') {
                            $transactionArray['status'] = \Oara\Utilities::STATUS_DECLINED;
                        } else {
                            throw new \Exception("Unexpected transaction status {$transaction['transactionStatus']}");
                        }
                        $transactionArray['currency'] = $transaction['currency'];
                        $transactionArray['amount'] = \Oara\Utilities::parseDouble($transaction['orderValue']);
                        $transactionArray['commission'] = \Oara\Utilities::parseDouble($transaction['commission']);
                        $totalTransactions[] = $transactionArray;
                    }
                }
            }
        }

        return $totalTransactions;
    }

    /**
     * @return array
     */
    public function getVouchers()
    {
        throw new \Exception("Not implemented yet");
    }

}
