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
		$id_site = $this->_credentials['idSite'];      // publisher id to retrieve (empty = all publishers)

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
		$id_site = $this->_credentials['idSite'];      // publisher id to retrieve (empty = all publishers)

		foreach ($this->_publisherId as $publisherId) {
			if (!empty($id_site) && $publisherId != $id_site) {
				// Skip unwanted publisher accounts
				continue;
			}

			$page = 1;
			$pageSize = 100;
			$finish = false;

			while (!$finish) {
                $url = "https://partner.qvc.de/api/v1/index.php/partner/subidstats?date=" . $dStartDate->format("Y-m-d") . "&dateend=" . $dEndDate->format("Y-m-d") . "&prid=" . $id_site;
				// initialize curl resource
				$ch = curl_init();
				// set the http request authentication headers
				$headers = array(
				    'Authorization: Basic ' . base64_encode($user . ':' . $password),
                    'Content-Type:application/json',
                    'lea-Public:R2ksq56xHfKvIPgGeICgFug1x26eFDjUn1IgL58VOc1qMteWSIEgyac8btKfc8sAx',
                    'lea-hash:6fbf7b260a6707d1c523e5f2b9c6a000b54eb3edca9947135f5427a132d27595x'
                );
				// set curl options
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				// execute curl
				$response = curl_exec($ch);
				$transactionList = json_decode($response, true);

				// TODO
                /*
				foreach ($transactionList as $transaction) {
					$merchantId = $transaction['program_id'];
					if (is_array($merchantList) && count($merchantList) > 0 && !isset($merchantIdList[$merchantId])) {
						// Skip unwanted merchants (empty merchant array means "all" merchants)
						continue;
					}
					if (!isset($transaction['parts']) || count($transaction['parts']) == 0) {
						continue;
					}
					foreach ($transaction['parts'] as $a_part) {
						$transactionArray = Array();
						$transactionArray['unique_id'] = $transaction['affiliatemarketing_id'] . '-' . $a_part['id'];
						$transactionArray['merchantId'] = $transaction['program_id'];
						$transactionArray['merchantName'] = $transaction['program_name'];
						$transactionDate = new \DateTime($a_part['date']);
						$transactionArray['date'] = $transactionDate->format("Y-m-d H:i:s");
						$transactionDateClick = new \DateTime($a_part['date_click']);
						$transactionArray['click_date'] = $transactionDateClick->format("Y-m-d H:i:s");
						$transactionDateUpdate = new \DateTime($a_part['last_modified']);
						$transactionArray['update_date'] = $transactionDateUpdate->format("Y-m-d H:i:s");

						$transactionArray['custom_id'] = $a_part['subid'];
						if ($a_part['status'] == 'approved') {
							$transactionArray['status'] = \Oara\Utilities::STATUS_CONFIRMED;
						} elseif ($a_part['status'] == 'pending' || $a_part['status'] == 'potential' || $a_part['status'] == 'open') {
							$transactionArray['status'] = \Oara\Utilities::STATUS_PENDING;
						} elseif ($a_part['status'] == 'disapproved' || $a_part['status'] == 'incasso') {
							$transactionArray['status'] = \Oara\Utilities::STATUS_DECLINED;
						} else {
							throw new \Exception("Unexpected transaction status {$a_part['status']}");
						}
						$transactionArray['currency'] = 'EUR';  // Default value
						$transactionArray['amount'] = \Oara\Utilities::parseDouble($a_part['revenue']);
						$transactionArray['commission'] = \Oara\Utilities::parseDouble($a_part['commission']);
						$transactionArray['IP'] = $transaction['anonymous_ip'];

						$totalTransactions[] = $transactionArray;
					}
				}
                */
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
