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
 * @category   WebePartnersV2
 * @version    Release: 01.00
 *
 */
class WebePartnersV2 extends \Oara\Network
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
        $aMerchants = array();
        $user = $this->_credentials['user'];
        $password = $this->_credentials['password'];

        if (!empty($password)) {
            $url_endpoint = 'https://api2.webepartners.pl/Wydawca/';
            $version = '';
            $apiKey = base64_encode($user . ':' . $password);
            $url = $url_endpoint . $version . "GetPrograms";

            $ch = curl_init();
            // set curl options
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Basic " . $apiKey));
            curl_setopt($ch, CURLOPT_POST, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            // execute curl
            $response = curl_exec($ch);
            $error = curl_errno($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($http_code != 200){
                throw new \Exception("[WebePartnersV2][getMerchantList][Exception] http code: " . $http_code);
            }
            if (!empty($response)) {
                $merchantList = json_decode($response, true);
                if (is_array($merchantList) && count($merchantList) > 0) {
                    foreach ($merchantList as $merchant) {
                        $obj = Array();
                        $obj['cid'] = $merchant['programId'];
                        $obj['name'] = $merchant['programName'];
                        switch ($merchant['relationId']) {
                            case 0:
                                $obj['status'] = 'unknown';
                                break;
                            case 1:
                                $obj['status'] = 'waiting';
                                break;
                            case 2:
                                $obj['status'] = 'refused';
                                break;
                            case 4:
                                $obj['status'] = 'active';
                                break;
                            case 5:
                                $obj['status'] = 'terminated';
                                break;
                            case 6:
                                $obj['status'] = 'under-review';
                                break;
                            default:
                                $obj['status'] = 'Unknown';
                                echo '[php-oara][Oara][Network][Publisher][WebePartnersV2][getMerchantList] Merchant status unexpected ' . $merchant['statusId'];
                                break;
                        }
                        $obj['launch_date'] = $merchant['createDate'];
                        $obj['application_date'] = $merchant['updateDate'];
                        $aMerchants[] = $obj;
                    }
                }
            }
        }

        return $aMerchants;
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
        $user = $this->_credentials['user'];
        $password = $this->_credentials['password'];

        if (!empty($password)) {
            $url_endpoint = 'https://api2.webepartners.pl/Wydawca/';
            $version = '';
            $apiKey = base64_encode($user . ':' . $password);
            $url = $url_endpoint . $version . "GetOrders";
            $params = array(
                new \Oara\Curl\Parameter('dateFrom', $dStartDate->format("Y-m-d")), //yyyy-mm-dd
                new \Oara\Curl\Parameter('dateTo', $dEndDate->format("Y-m-d")), //yyyy-mm-dd
            );
            $p = array();
            foreach ($params as $parameter) {
                $p[] = $parameter->getKey() . '=' . \urlencode($parameter->getValue());
            }
            $get_params = implode('&', $p);
            $url = $url . '?' . $get_params;

            $ch = curl_init();
            // set curl options
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Basic " . $apiKey));
            curl_setopt($ch, CURLOPT_POST, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            // execute curl
            $response = curl_exec($ch);
            $error = curl_errno($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($http_code != 200){
                throw new \Exception("[WebePartnersV2][getTransactionList][Exception] http code: " . $http_code);
            }
            if (!empty($response)) {
                $transactionList = json_decode($response, true);
                if (is_array($transactionList) && count($transactionList) > 0) {
                    foreach ($transactionList as $transaction) {
                        $transactionArray = Array();
                        $transactionArray['unique_id'] = $transaction['orderId'];
                        if (isset($transaction['param1']) && !empty($transaction['param1'])){
                            $transactionArray['custom_id'] = $transaction['param1'];
                        }
                        elseif (isset($transaction['param2']) && !empty($transaction['param2'])){
                            $transactionArray['custom_id'] = $transaction['param2'];
                        }
                        else{
                            $transactionArray['custom_id'] = '';
                        }
                        $transactionArray['click_date'] = new \DateTime($transaction['clickDate'], new \DateTimeZone('UTC'));
                        $transactionArray['merchantId'] = $transaction['programId'];
                        $transactionArray['merchantName'] = $transaction['programName'];
                        $transactionArray['date'] = new \DateTime($transaction['orderDate'], new \DateTimeZone('UTC'));
                        if (isset($transaction['statusDate']) && !empty($transaction['statusDate'])){
                            $transactionArray['update_date'] = new \DateTime($transaction['statusDate'], new \DateTimeZone('UTC'));
                        }
                        if ($transaction['statusId'] == 1 || ($transaction['statusId'] == 3)) {
                            $transactionArray['status'] = \Oara\Utilities::STATUS_PENDING;
                        } elseif ($transaction['statusId'] == 4 || $transaction['statusId'] == 5 || $transaction['statusId'] == 6) {
                            $transactionArray['status'] = \Oara\Utilities::STATUS_CONFIRMED;
                        } elseif ($transaction['statusId'] == 2) {
                            $transactionArray['status'] = \Oara\Utilities::STATUS_DECLINED;
                        } else {
                            throw new \Exception("[WebePartnersV2][getTransactionList] - unexpected transaction status {$transaction['statusId']}");
                        }
                        $transactionArray['currency'] = 'PLN';
                        $transactionArray['amount'] = \Oara\Utilities::parseDouble($transaction['orderCost']);
                        $transactionArray['commission'] = \Oara\Utilities::parseDouble($transaction['commission']);
                        $totalTransactions[] = $transactionArray;
                    }
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
