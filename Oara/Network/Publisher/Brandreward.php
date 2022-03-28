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

class Brandreward extends \Oara\Network
{
	private $_user_id = null;
	private $_api_password = null;

	/**
	 * API URL
	 * @var string
	 */
	protected $_apiUrl = 'https://api.brandreward.com';


	/**
	 * @param $credentials
	 * @throws Exception
	 */
	public function login($credentials)
	{
        $this->_user_id = $credentials['user'];
        $this->_api_password = $credentials['password'];
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
         * https://www.brandreward.com/b_tools_apidocs.php?func=transaction
         */
		$totalTransactions = array();
		try{
            $start_date = $dStartDate->format('Y-m-d');
            $end_date = $dEndDate->format('Y-m-d');
			$page = 1;
			$limit = 1000;
			$loop = true;

			while ($loop){

				$transactionsList = $this->_request(
					'report',
					'transaction_data',
					array(
						'bdate' => $start_date, //format YYYY-mm-dd
						'edate' => $end_date, //format YYYY-mm-dd
                        'key' => $this->_api_password, //Your Site ID in Account tab
                        'user' => $this->_user_id, //Your Login User Name
                        'page' => $page, //Specifies the page of the results set that is currently being viewed.(default:1)
                        'pagesize' => $limit, //Specifies the number of records to be viewed per page.(default:100)
                        'datetype' => 'tradedate', //Parameters [bdate] and [edate] search data.(empty/clickdate: ClickTime, updatedate:UpdateTime,tradedate: TransactionTime)
                        'outformat' => 'json', //Output content format (default:txt , option:'txt','json','xml','csv')
                    )
				);
                if (isset($transactionsList['response']) && $transactionsList['response']['Num'] == 0) {
                    $loop = false;
                    break;
                }
				if (isset($transactionsList['data'])){
					foreach ($transactionsList['data'] as $transaction) {
						$a_transaction['unique_id'] = $transaction['TransactionID'];
						$a_transaction['date'] = new \DateTime($transaction['CreateTime']);
                        $a_transaction['update_date'] = new \DateTime($transaction['UpdateTime']);
                        $a_transaction['click_date'] = new \DateTime($transaction['ClickTime']);
                        $a_transaction['merchantName'] = $transaction['Advertiser'];
						$a_transaction['custom_id'] = $transaction['SID'];
                        $a_transaction['commission'] = Utilities::parseDouble($transaction['Earnings']);
						switch ($transaction['State']){
							case 'PENDING':
								$a_transaction['status'] = Utilities::STATUS_PENDING;
								break;
							case 'CANCELLED':
								$a_transaction['status'] = Utilities::STATUS_DECLINED;
								break;
                            case 'CONFIRMED':
							case 'PAID':
								$a_transaction['status'] = Utilities::STATUS_CONFIRMED;
								break;
						}
						$totalTransactions[] = $a_transaction;
					}
                    if ($transactionsList['response']['PageTotal'] == $transactionsList['response']['PageNow']) {
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
		return $this->_apiUrl;
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
		$url = $this->_getApiBaseUrl() . '?act=' . $service . '.' . $call;

		foreach ($a_options as $key => $value) {
			$url .= '&' . $key . '=' . $value;
		}

		$data = file_get_contents($url);
		if (strlen($data) == 0) {
			throw new \Exception('[Brandreward][_request] invalid result received');
		}

        $data = $this->_decode($data);
        if (isset($data['response'])){
            return $data;
        } else {
            throw new \Exception($data);
        }
	}




}
