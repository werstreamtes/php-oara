<?php
namespace Oara\Network\Publisher;
use Oara\Utilities;

/**
 * The goal of the Open Affiliate Report Aggregator (OARA) is to develop a set
 * of PHP classes that can download affiliate reports from a number of affiliate networks, and store the data in a common format.
 *
 * Copyright (C) 2016  Fubra Limited
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Contact
 * ------------
 * Fubra Limited <support@fubra.com> , +44 (0)1252 367 200
 **/

class MyLead extends \Oara\Network
{
	private $_user_id = null;
	private $_api_password = null;
	private $_token = null;
	/**
	 * API URL
	 * @var string
	 */
	protected $_apiUrl = 'https://mylead.global/api/external/';
	/**
	 * API version
	 * @var string
	 */
	protected $_apiVersion = 'v1';



	/**
	 * @param $credentials
	 * @throws Exception
	 */
	public function login($credentials)
	{
		$this->getToken();
	}


	public function getToken() {
		if (isset($_ENV['MYLEAD_TOKEN'])) {
            $this->_token = $_ENV['MYLEAD_TOKEN'];
        }
        else {
            $this->_token =  '';
        }

		return $this->_token;
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
		$credentials[] = $parameter;

		return $credentials;
	}

	/**
	 * @return bool
	 */
	public function checkConnection()
	{
		$connection = true;
		return $connection;
	}



	/**
	 * @param null $merchantList
	 * @param \DateTime|null $dStartDate
	 * @param \DateTime|null $dEndDate
	 * @return array
	 * @throws \Exception
	 */
	public function getTransactionList($merchantList = null, \DateTime $dStartDate = null, \DateTime $dEndDate = null)
	{
		/**
         * https://mylead.global/documentation/api#!/Statistics/api_external_statistics_conversions
         */
		$totalTransactions = array();
		try{
            $start_date = $dStartDate->format('Y-m-d');
            $end_date = $dEndDate->format('Y-m-d');
			$page = 1;
			$limit = 500;
			$loop = true;

			while ($loop){

				$transactionsList = $this->_request(
					'statistic',
					'conversions',
					array(
						'token' => $this->getToken(),
						'date_from' => $start_date, //format YYYY-mm-dd
						'date_to' => $end_date, //format YYYY-mm-dd
						'page' => $page,
						'limit' => $limit,
					)
				);

				if (isset($transactionsList['data'][0]['conversions'])){

					if (count($transactionsList['data'][0]['conversions']) == 0) {
						$loop = false;
						break;
					}
					foreach ($transactionsList['data'][0]['conversions'] as $transaction) {
						$a_transaction['unique_id'] = $transaction['id'];
						$a_transaction['date'] = new \DateTime($transaction['created_at']['date'], new \DateTimeZone($transaction['created_at']['timezone']));
                        $a_transaction['date']->setTimezone(new \DateTimeZone('Europe/Rome'));
                        $a_transaction['IP'] = $transaction['ip'];
						$a_transaction['merchantId'] = $transaction['campaign_id'];
						$a_transaction['merchantName'] = $transaction['campaign_name'];
						$a_transaction['custom_id'] = $transaction['ml_sub1'];
                        $a_transaction['commission'] = Utilities::parseDouble($transaction['payout']);
                        $a_transaction['currency'] = $transaction['currency'];
						switch ($transaction['status']){
							case 'pending':
								$a_transaction['status'] = Utilities::STATUS_PENDING;
								break;
							case 'rejected':
								$a_transaction['status'] = Utilities::STATUS_DECLINED;
								break;
                            case 'pre_approved':
							case 'approved':
								$a_transaction['status'] = Utilities::STATUS_CONFIRMED;
								break;
						}
						$totalTransactions[] = $a_transaction;
					}
                    if (empty($transactionsList['pagination']['next_page'])) {
                        $loop = false;
                        break;
                    }
                    else{
                        $page = (int)(1 + $page);
                    }
				}
			}
		}
		catch (\Exception $e){
			throw new \Exception($e);
		}

		return $totalTransactions;

	}


	/**
	 * @return string
	 */
	protected function _getApiBaseUrl() {
		return $this->_apiUrl . $this->_apiVersion;
	}

	/**
	 * @param  string $data
	 * @param  string $format (Optional) Format
	 * @return \stdClass
	 */
	protected function _decode($data, $format = 'json'){
		if ($format == 'json') {
			return json_decode($data, true);
		}
	}

	/**
	 * @param $service
	 * @param $call
	 * @param array $a_options
	 * @return false|\stdClass|string
	 * @throws \Exception
	 */
	protected function _request($service, $call, $a_options) {
		$url = $this->_getApiBaseUrl() . '/' . $service . '/' . $call . '?';

		foreach ($a_options as $key => $value) {
			$url .= '&' . $key . '=' . $value;
		}

		$data = file_get_contents($url);
		if (strlen($data) == 0) {
			throw new \Exception('[MyLead][_request] invalid result received');
		}

		$data = $this->_decode($data);
		if (isset($data['status_code']) ){
		    if ($data['status_code'] == 200) {
                return $data;
            }
		    else {
                throw new \Exception($data['status_code']);
            }
		} else {
            if (isset($data['error_description'])) {
                throw new \Exception($data['error_description']);
            }
		}
	}




}
