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
 * @category   Booking
 * @version    Release: 01.00
 *
 */
class Booking extends \Oara\Network
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
        $user = $this->_credentials['user'];
        $password = $this->_credentials['password'];
        $id_site = $this->_credentials['idSite'];

        if (!empty($password)) {
            $url_endpoint = 'https://secure-distribution-xml.booking.com/';
            $version = '2.8/';
            $offset = 0;
            $limit = 1000; //max value: 1000
            $currency = 'EUR';
            $loop = true;
            while ($loop){
                $url = $url_endpoint . $version . "json/bookingDetails";

                $apiKey = base64_encode($user . ':' . $password);
                $p = array();
                $params = array(
                    new \Oara\Curl\Parameter('created_from', $dStartDate->format("Y-m-d")), //yyyy-mm-dd
                    new \Oara\Curl\Parameter('created_until', $dEndDate->format("Y-m-d")), //yyyy-mm-dd
                    new \Oara\Curl\Parameter('local_fee_currency', $currency),
                    new \Oara\Curl\Parameter('offset', $offset),
                    new \Oara\Curl\Parameter('rows', $limit),
                );

                foreach ($params as $parameter) {
                    $p[] = $parameter->getKey() . '=' . \urlencode($parameter->getValue());
                }
                $post_params = implode('&', $p);

                $ch = curl_init();
                // set curl options
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, TRUE);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_params);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Basic " . $apiKey));

                $response = curl_exec($ch);
                $error = curl_errno($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($http_code != 200){
                    throw new \Exception("[Booking][getTransactionList][Exception] http code: " . $http_code);
                }
                if (!empty($response)) {
                    $transactionList = json_decode($response, true);
                    if (is_array($transactionList) && count($transactionList) > 0) {
                        foreach ($transactionList['result'] as $transaction) {
                            $transactionArray = Array();
                            if ($transaction['affiliate_id'] !=  $id_site){
                                continue;
                            }
                            $transactionArray['unique_id'] = $transaction['reservation_id'];
                            if (isset($transaction['affiliate_label']) && !empty($transaction['affiliate_label'])){
                                $transactionArray['custom_id'] = $transaction['affiliate_label'];
                            }
                            else{
                                $transactionArray['custom_id'] = '';
                            }
                            $transactionArray['click_date'] = new \DateTime($transaction['created'], new \DateTimeZone('UTC'));
                            $transactionArray['merchantId'] = '';
                            $transactionArray['merchantName'] = 'Booking';
                            $transactionArray['date'] = new \DateTime($transaction['created'], new \DateTimeZone('UTC'));
                            if (isset($transaction['created']) && !empty($transaction['created'])){
                                $transactionArray['update_date'] = new \DateTime($transaction['created'], new \DateTimeZone('UTC'));
                            }
                            //The status of the booking.
                            // A 'stayed' booking is one that was booked, stayed and is not modifiable anymore.
                            // A 'booked' booking is currently marked as booked, might be stayed, but still may be marked as 'cancelled' or 'no_show'.
                            // A 'no_show' booking means that hotel marked this booking as guest not showing.
                            // Displaying of the 'no_show' values is conditioned by use of extras=no_show input param, or else they will be showed as 'cancelled'.
                            // A 'cancelled' booking means that the booking was cancelled. If extras=status_details is passed,
                            // bookings cancelled by the hotel will have status 'cancelled_by_hotel' and bookings cancelled by the guest will have status 'cancelled_by_guest'
                            if (strtolower($transaction['status']) == 'booked') {
                                $transactionArray['status'] = \Oara\Utilities::STATUS_PENDING;
                            } elseif (str_contains(strtolower($transaction['status']), 'stayed')) {
                                $transactionArray['status'] = \Oara\Utilities::STATUS_CONFIRMED;
                            } elseif (strtolower($transaction['status']) == 'cancelled' || strtolower($transaction['status']) == 'canceled') {
                                $transactionArray['status'] = \Oara\Utilities::STATUS_DECLINED;
                            } else {
                                throw new \Exception("[Booking][getTransactionList] - unexpected transaction status {$transaction['status']}");
                            }
                            $transactionArray['currency'] = $currency;
                            $commission = $transaction['euro_fee'];
                            $percentage_commission = $transaction['fee_percentage'];
                            if (!empty($commission)){
                                $estimate_amount = ((100 * $commission) / $percentage_commission);
                            }
                            else{
                                $estimate_amount = $percentage_commission;
                            }
                            $transactionArray['amount'] = \Oara\Utilities::parseDouble($estimate_amount);
                            $transactionArray['commission'] = \Oara\Utilities::parseDouble($commission);
                            $totalTransactions[] = $transactionArray;
                        }
                    }
                }
                if ($transactionList['meta']['has_more'] == 0){
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
