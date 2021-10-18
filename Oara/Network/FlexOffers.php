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
 * @category   FlexOffers
 * @version    Release: 01.00
 *
 */
class FlexOffers extends \Oara\Network
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
            $url_endpoint = 'https://api.flexoffers.com/';
            $version = '';
            $page = 1;
            $limit = 12;
            $loop = true;

            while ($loop){
                $url = $url_endpoint . $version . "allsales";
                $params = array(
                    new \Oara\Curl\Parameter('startDate', $dStartDate->format("c")), //2021-09-01T07:35:07+0000
                    new \Oara\Curl\Parameter('endDate', $dEndDate->format("c")), //Specifies the upper bound of the date range, defaults to today if not specified.
                    new \Oara\Curl\Parameter('reportType', 'sales'),
                    new \Oara\Curl\Parameter('page', $page),
                    new \Oara\Curl\Parameter('pageSize', $limit), //Specify the maximum number of records to be retrieved per page. This value must be between 1 and 500.
                    new \Oara\Curl\Parameter('status', 'all'),
                    // Sales will be filtered based on Status. If 'all' is selected, overall sales appear in result.
                    //status all or other statuses individually: pending, approved, canceled, bonus or non-commissionable
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
                    'apiKey:' . $password,
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
                    if ($http_code == 429){
                        //too many requests - try again in 30 seconds
                        throw new \Exception("[FlexOffers][getTransactionList][Exception] 429  Too many requests " . $response);
                    }
                    if (is_array($transactionList) && count($transactionList) > 0) {
                        if ($http_code == 400 || $http_code == 405){
                            if (isset($transactionList['message'])){
                                throw new \Exception("[FlexOffers][getTransactionList][Exception] " . $transactionList['message']);
                            }
                        }
                        foreach ($transactionList['results'] as $transaction) {
                            $transactionArray = Array();
                            $transactionArray['unique_id'] = $transaction['legacyId'];
                            if (isset($transaction['subTracking'])){
                                $transactionArray['custom_id'] = $transaction['subTracking'];
                                $transactionArray['click_date'] = ($transaction['clickDate']);
                            }
                            $transactionArray['merchantId'] = $transaction['programId'];
                            $transactionArray['merchantName'] = $transaction['programName'];
                            $transactionArray['date'] = $transaction['eventDate'];
                            if (isset($transaction['modifiedDate'])){
                                $transactionArray['update_date'] = $transaction['modifiedDate'];
                            }
                            if ($transaction['orderStatus'] == 'Pending') {
                                $transactionArray['status'] = \Oara\Utilities::STATUS_PENDING;
                            } elseif ($transaction['orderStatus'] == 'Approved') {
                                $transactionArray['status'] = \Oara\Utilities::STATUS_CONFIRMED;
                            } elseif ($transaction['orderStatus'] == 'Canceled') {
                                $transactionArray['status'] = \Oara\Utilities::STATUS_DECLINED;
                            } else {
                                throw new \Exception("Unexpected transaction status {$transaction['orderStatus']}");
                            }
                            $transactionArray['currency'] = $transaction['currency'];
                            $transactionArray['amount'] = \Oara\Utilities::parseDouble($transaction['merchantValue']);
                            $transactionArray['commission'] = \Oara\Utilities::parseDouble($transaction['commission']);
                            $totalTransactions[] = $transactionArray;
                        }
                    }
                }
                if ($transactionList['totalCount'] < $transactionList['pageSize']){
                    $loop = false;
                }
                elseif ($transactionList['totalCount'] > count($totalTransactions)){
                    $loop = true;
                    $page++;
                }
                else {
                    //empty respons - status code 204
                    $loop = false;
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
