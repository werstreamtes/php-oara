<?php

namespace Oara\Network\Publisher;
/**
 * The goal of the Open Affiliate Report Aggregator (OARA) is to develop a set
 * of PHP classes that can download affiliate reports from a number of affiliate networks, and store the data in a common format.
 **/

/**
 * Export Class
 *
 * @author     Paolo Nardini
 * @category   LeadAlliance
 * @version    Release: 01.00
 *
 */
class LeadAlliance extends \Oara\Network
{

	private $_credentials = null;

	private $_publisherId = array();

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
		//If not login properly the construct launch an exception
		$connection = true;

		try {
			$user = $this->_credentials['user'];
			$password = $this->_credentials['password'];

			/*
			$url = "https://services.daisycon.com:443/publishers?page=1&per_page=100";
			// initialize curl resource
			$ch = curl_init();
			// set the http request authentication headers
			$headers = array('Authorization: Basic ' . base64_encode($user . ':' . $password));
			// set curl options
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			// execute curl
			$response = curl_exec($ch);
			$publisherList = json_decode($response, true);
			foreach ($publisherList as $publisher) {
				$this->_publisherId[] = $publisher["id"];
			}
			if (count($this->_publisherId) == 0) {
				throw new \Exception("No publisher found");
			}
			*/

		} catch (\Exception $e) {
			$connection = false;
		}
		return $connection;
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

	/**
	 * (non-PHPdoc)
	 * @see library/Oara/Network/Interface#getMerchantList()
	 */
	public function getMerchantList()
	{
		$merchants = array();
		// NOT IMPLEMENTED YET
        /*
		$user = $this->_credentials['user'];
		$password = $this->_credentials['password'];
		$id_site = $this->_credentials['id_site'];      // publisher id to retrieve (empty = all publishers)

		foreach ($this->_publisherId as $publisherId) {
			if (!empty($id_site) && $publisherId != $id_site) {
				// Skip unwanted publisher accounts
				continue;
			}
			$page = 1;
			$pageSize = 100;
			$finish = false;

			while (!$finish) {
				$url = "https://services.daisycon.com:443/publishers/$publisherId/programs?page=$page&per_page=$pageSize";
				// initialize curl resource
				$ch = curl_init();
				// set the http request authentication headers
				$headers = array('Authorization: Basic ' . base64_encode($user . ':' . $password));
				// set curl options
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				// execute curl
				$response = curl_exec($ch);
				$merchantList = json_decode($response, true);

				foreach ($merchantList as $merchant) {

					$obj = Array();
					$obj['cid'] = $merchant['id'];
					$obj['name'] = $merchant['name'];
					// Added more info - 2018-06-01 <PN>
					$obj['status'] = $merchant['status'];
					$obj["display_url"] = $merchant['display_url'];
					$obj["start_date"] = $merchant['startdate'];
					$obj["end_date"] = $merchant['enddate'];
					$merchants[] = $obj;
				}

				if (count($merchantList) != $pageSize) {
					$finish = true;
				}
				$page++;
			}
		}
        */
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

		$merchantIdList = \Oara\Utilities::getMerchantIdMapFromMerchantList($merchantList);

		$user = $this->_credentials['user'];
		$password = $this->_credentials['password'];
		$id_site = $this->_credentials['id_site'];      // publisher id to retrieve (empty = all publishers)
        $this->_publisherId[] = $id_site;   // DUMMY TEST

        $public = '';
        $hash = '';

        if (isset($_ENV['LEAD_ALLIANCE_PUBLIC'])) {
            $public = $_ENV['LEAD_ALLIANCE_PUBLIC'];
        }
        if (isset($_ENV['LEAD_ALLIANCE_PRIVATE'])) {
            $private = $_ENV['LEAD_ALLIANCE_PRIVATE'];
            $hash = hash_hmac('sha256', '', $private);
        }         // '6fbf7b260a6707d1c523e5f2b9c6a000b54eb3edca9947135f5427a132d27595';

		foreach ($this->_publisherId as $publisherId) {
			if (!empty($id_site) && $publisherId != $id_site) {
				// Skip unwanted publisher accounts
				continue;
			}

			$page = 1;
			$pageSize = 9999;
			$finish = false;

			while (!$finish) {
                $url = "https://partner.qvc.de/api/v1/index.php/partner/transactions?date=" . $dStartDate->format("Y-m-d") . "&dateend=" . $dEndDate->format("Y-m-d") . "&prid=" . $id_site;
				// initialize curl resource
				$ch = curl_init();
				// set the http request authentication headers
				$headers = array(
				    'Authorization: Basic ' . base64_encode($user . ':' . $password),
                    'Content-Type:application/json',
                    'lea-Public:' . $public,
                    'lea-hash:' . $hash,
0                );
				// set curl options
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				// execute curl
				$response = curl_exec($ch);
				if (!empty($response)) {
				    if (stripos($response,'error') !== false) {
                        echo "[LeadAlliance][getTransactionList] Error: " . $response ."\n ";
                        return $totalTransactions;
                    }
                }
				$transactionList = json_decode($response, true);
				if (is_array($transactionList) && count($transactionList) > 0) {

                    // TODO
                    foreach ($transactionList as $transaction) {
                        $transactionArray = Array();
                        $merchantId = $transaction['programid'];
                        if (is_array($merchantList) && count($merchantList) > 0 && !isset($merchantIdList[$merchantId])) {
                            // Skip unwanted merchants (empty merchant array means "all" merchants)
                            continue;
                        }
                        $transactionArray['unique_id'] = $transaction['transactionid'];
                        $transactionArray['merchantId'] = $transaction['programid'];
                        $transactionArray['merchantName'] = $transaction['program'];
                        $date_origin = $transaction['dateorigin'];
                        $time_origin = $transaction['timeorigin'];
                        $transactionArray['date'] = $date_origin . ' ' . $time_origin;
                        $transactionArray['click_date'] = $transaction['timeclick'];
                        $transactionArray['update_date'] = $transaction['dateedit'];
                        $transactionArray['custom_id'] = $transaction['subid'];
                        if ($transaction['status'] == '2') {
                            $transactionArray['status'] = \Oara\Utilities::STATUS_CONFIRMED;
                        } elseif ($transaction['status'] == '1') {
                            $transactionArray['status'] = \Oara\Utilities::STATUS_PENDING;
                        } elseif ($transaction['status'] == '0') {
                            $transactionArray['status'] = \Oara\Utilities::STATUS_DECLINED;
                        } else {
                            throw new \Exception("Unexpected transaction status {$transaction['status']}");
                        }
                        $transactionArray['currency'] = 'EUR';  // Default value
                        $transactionArray['amount'] = \Oara\Utilities::parseDouble($transaction['value']);
                        $transactionArray['commission'] = \Oara\Utilities::parseDouble($transaction['commission']);
                        $transactionArray['info'] = $transaction['info'];
                        $transactionArray['statuscomment'] = $transaction['statuscomment'];
                        $transactionArray['datepayment'] = $transaction['datepayment'];
                        $transactionArray['category'] = $transaction['category'];
                        $transactionArray['leadtype'] = $transaction['leadtype'];
                        $transactionArray['adspaceid'] = $transaction['adspaceid'];
                        $transactionArray['autookdate'] = $transaction['autookdate'];
                        $totalTransactions[] = $transactionArray;
                    }
                }
				if (count($transactionList) != $pageSize) {
					$finish = true;
				}
				$page++;
			}
		}

		return $totalTransactions;
	}

	/**
	 * See: https://developers.daisycon.com/api/resources/publisher-resources/
	 * @return array
	 */
	public function getVouchers()
	{
        throw new \Exception("Not implemented yet");
	}

}
