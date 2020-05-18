<?php
namespace Oara\Network\Publisher;

class PepperJamApi extends \Oara\Network
{
    private $_client = null;

    private $_api_key;

    /**
     * @param $credentials
     */
    public function login($credentials)
    {
        $this->_api_key = $credentials['api_key'];
    }

    /**
     * @return bool
     */
    public function checkConnection()
    {
        return true;
    }

    /**
     * @return array
     */
    public function getNeededCredentials()
    {
        $credentials = array();

        $parameter = array();
        $parameter["description"] = "User Log in";
        $parameter["required"] = true;
        $parameter["name"] = "User";
        $credentials["user"] = $parameter;

        $parameter = array();
        $parameter["description"] = "Password to Log in";
        $parameter["required"] = true;
        $parameter["name"] = "Password";
        $credentials["password"] = $parameter;

        return $credentials;
    }

    private function buildClient($basePath, $params = []) 
    {
        $client = curl_init();

        $params["apiKey"] = $this->_api_key;
        $params["format"] = "json";
        $url = $basePath . "?" . http_build_query($params);

        curl_setopt($client, CURLOPT_URL, $url);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, TRUE);
        return $client;
    }

    private function execClientCall($client) 
    {
        $response = curl_exec($client);
        curl_close($client);
        return $response;
    }

    private function getNextPage($url)
    {
        $client = curl_init();
        curl_setopt($client, CURLOPT_URL, $url);
        curl_setopt($client, CURLOPT_RETURNTRANSFER, TRUE);
        $response = curl_exec($client);
        curl_close($client);
        return $response;
    }

    /**
     * @return array
     */
    public function getMerchantList()
    {
        $MERCHANT_LIST_PATH = "https://api.pepperjamnetwork.com/20120402/publisher/advertiser";

        $client = $this->buildClient($MERCHANT_LIST_PATH);

        $response = json_decode($this->execClientCall($client));

        return $response;
    }

    private function parseTransactions($rawTransactions)
    {
        return array_map(function($rawTransaction) {
            $transaction = [];

            $transaction["unique_id"] = $data["transaction_id"];
            $transaction["order_id"] = $data["order_id"];
            $transaction["creative_type"] = $data["creative_type"];
            $transaction["commission"] = $data["commission"];
            $transaction["amount"] = $data["sale_amount"];
            $transaction["type"] = $data["type"];
            $transaction["date"] = $data["date"];
            // status
            switch ($data["status"]) {
            case "pending":
                $transaction["status"] = \Oara\Utilities::STATUS_PENDING;
                break;
            case "":
                $transaction["status"] = \Oara\Utilities::STATUS_CONFIRMED;
                break;
            case "":
                $transaction["status"] = \Oara\Utilities::STATUS_DECLINED;
                break;
            case "":
                $transaction["status"] = \Oara\Utilities::STATUS_PAID;
                break;
            default:
                $transaction["status"] = "CHECK OARA STATUS PARSER";
                break;
            }
            
            $transaction["new_to_file"] = $data["new_to_file"];
            $transaction["sub_type"] = $data["sub_type"];
            $transaction["custom_id"] = $data["sid"];
            $transaction["program_name"] = $data["program_name"];
            $transaction["program_id"] = $data["program_id"];

            return $transaction;

        }, $rawTransactions);
    }

    /**
     * @param null $merchantList
     * @param \DateTime|null $dStartDate
     * @param \DateTime|null $dEndDate
     * @return array
     * @throws Exception
     */
    public function getTransactionList($merchantList = null, \DateTime $dStartDate = null, \DateTime $dEndDate = null)
    {
        $TRANSACTIONS_LIST_PATH = "https://api.pepperjamnetwork.com/20120402/publisher/report/transaction-details";

        $params = [
            "startDate" => "2020-05-01",
            "endDate" => "2020-05-18"
        ];

        $client = $this->buildClient($TRANSACTIONS_LIST_PATH, $params);

        $response = json_decode($this->execClientCall($client));

        if($response) {
            $transactions = $this->parseTransactions($response->data);

            $nextPage = null;
            if(isset($metadata->meta->pagination->next)) {
                $nextPage = $metadata->meta->pagination->next;
            }
            
            // iterate through pages
            if ($nextPage) {
                $hasNextPage = true;
                while($hasNextPage) {
                    $response = $this->getNextPage($nextPage);
                    $nextPage = $metadata["meta"]["pagination"]["next"];
                    array_merge($transactions, $this->parseTransactions($response->data));

                    if($nextPage) {
                        $hasNextPage = false;
                    }
                }
            }

            return $transactions;
        }

        return [];
    }

    /**
     * @return array
     */
    public function getPaymentHistory()
    {
        return [];
    }

    /**
     * @param $paymentId
     * @return array
     */
    public function paymentTransactions($paymentId)
    {
       return [];
    }

}
