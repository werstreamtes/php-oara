<?php
namespace Oara\Network\Publisher;
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
/**
 * Api Class
 *
 * @author     Carlos Morillo Merino
 * @category   AffiliateWindow
 * @copyright  Fubra Limited
 * @version    Release: 01.00
 *
 */
class AffiliateWindow extends \Oara\Network
{
    /**
     * Soap client.
     */
    private $_apiClient = null;
    private $_exportClient = null;
    private $_pageSize = 100;
    private $_currency = null;
    private $_userId = null;
    public $_sitesAllowed = array();
    public $_credentials = array();
    /**
     * @param $credentials
     * @throws \Exception
     * @throws \Oara\Curl\Exception
     */
    public function login($credentials)
    {
        ini_set('default_socket_timeout', '120');

        $this->_credentials = $credentials;

        $this->_userId = $credentials['accountid'];
        $password = $credentials['apipassword'];
        /*
        $this->_currency = $credentials['currency'];

                //Login to the website
                if (filter_var($user, \FILTER_VALIDATE_EMAIL)) {

                    $this->_exportClient = new \Oara\Curl\Access($credentials);
                    //Log in
                    $valuesLogin = array(
                        new \Oara\Curl\Parameter('email', $user),
                        new \Oara\Curl\Parameter('password', $passwordExport),
                        new \Oara\Curl\Parameter('Login', '')
                    );
                    $urls = array();
                    $urls[] = new \Oara\Curl\Request('https://darwin.affiliatewindow.com/login?', $valuesLogin);
                    $this->_exportClient->post($urls);


                    $urls = array();
                    $urls[] = new \Oara\Curl\Request('https://darwin.affiliatewindow.com/user/', array());
                    $exportReport = $this->_exportClient->get($urls);
                    if (\preg_match_all('/href=\"\/awin\/affiliate\/.*\".*id=\"goDarwin(.*)\"/', $exportReport[0], $matches)) {

                        foreach ($matches[1] as $user) {
                            $urls = array();
                            $urls[] = new \Oara\Curl\Request('https://darwin.affiliatewindow.com/awin/affiliate/' . $user, array());
                            $exportReport = $this->_exportClient->get($urls);

                            $doc = new \DOMDocument();
                            @$doc->loadHTML($exportReport[0]);
                            $xpath = new \DOMXPath($doc);
                            $linkList = $xpath->query('//a');
                            $href = null;
                            foreach ($linkList as $link) {
                                $text = \trim($link->nodeValue);
                                if ($text == "Manage API Credentials") {
                                    $href = $link->attributes->getNamedItem("href")->nodeValue;
                                    break;
                                }
                            }
                            if ($href != null) {
                                $urls = array();
                                $urls[] = new \Oara\Curl\Request('https://darwin.affiliatewindow.com' . $href, array());
                                $exportReport = $this->_exportClient->get($urls);

                                $doc = new \DOMDocument();
                                @$doc->loadHTML($exportReport[0]);
                                $xpath = new \DOMXPath($doc);
                                $linkList = $xpath->query("//span[@id='aw_api_password_hash']");
                                foreach ($linkList as $link) {
                                    $text = \trim($link->nodeValue);
                                    if ($text == $password) {
                                        $this->_userId = $user;
                                        break;
                                    }
                                }

                            } else {
                                throw new \Exception("It couldn't connect to darwin");
                            }
                        }
                    }
                } else {
                    throw new \Exception("It's not an email");
                }


        */
        $nameSpace = 'http://api.affiliatewindow.com/';
        $wsdlUrl = 'http://api.affiliatewindow.com/v6/AffiliateService?wsdl';
        //Setting the client.
        $this->_apiClient = new \SoapClient($wsdlUrl, array('login' => $this->_userId, 'encoding' => 'UTF-8', 'password' => $password, 'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE, 'soap_version' => SOAP_1_1));
        $soapHeader1 = new \SoapHeader($nameSpace, 'UserAuthentication', array('iId' => $this->_userId, 'sPassword' => $password, 'sType' => 'affiliate'), true, $nameSpace);
        $soapHeader2 = new \SoapHeader($nameSpace, 'getQuota', true, true, $nameSpace);
        $this->_apiClient->__setSoapHeaders(array($soapHeader1, $soapHeader2));
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
        $parameter["description"] = "User Password";
        $parameter["required"] = true;
        $parameter["name"] = "Password";
        $credentials["password"] = $parameter;

        $parameter = array();
        $parameter["description"] = "PublisherService API password";
        $parameter["required"] = true;
        $parameter["name"] = "API password";
        $credentials["apipassword"] = $parameter;

        $parameter = array();
        $parameter["description"] = "Currency code for reporting";
        $parameter["required"] = false;
        $parameter["name"] = "Currency";
        $credentials["currency"] = $parameter;

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
     * @return array
     */
    public function getMerchantList()
    {
        $merchantList = array();
        $params = array();
        $params['sRelationship'] = 'joined';
        $merchants = $this->_apiClient->getMerchantList($params)->getMerchantListReturn;
        foreach ($merchants as $merchant) {
            if (count($this->_sitesAllowed) == 0  ||\in_array($merchant->oPrimaryRegion->sCountryCode, $this->_sitesAllowed)) {
                $merchantArray = array();
                $merchantArray["cid"] = $merchant->iId;
                $merchantArray["name"] = $merchant->sName;
                $merchantArray["url"] = $merchant->sDisplayUrl;
                $merchantList[] = $merchantArray;
            }
        }
        return $merchantList;
    }

    /**
     * @param null $merchantList
     * @param \DateTime|null $dStartDate
     * @param \DateTime|null $dEndDate
     * @return array
     */
    public function getTransactionList($merchantList = null, \DateTime $dStartDate = null, \DateTime $dEndDate = null)
    {
        $totalTransactions = array();

        try {
            $id = $this->_credentials["accountid"];
            $pwd = $this->_credentials["apipassword"];
            //echo "<br> id ".$id." pwd ".$pwd."<br>";

            $dStartDate_ = $dStartDate->format("Y-m-d");
            //echo "<br>s date ".$dStartDate_;
            $dStartTime_ = $dStartDate->format("H:s:i");
            $dEndDate_ = $dEndDate->format("Y-m-d");
            $dEndTime_ = $dEndDate->format("H:s:i");
            $dEndDate = urlencode($dEndDate_ . "T" . $dEndTime_);
            $dStartDate = urlencode($dStartDate_ . "T" . $dStartTime_);
            //echo "<br>start date " . $dStartDate;
            //$url = 'https://api.awin.com/publishers/'.$id.'/transactions/?accessToken='.$pwd.'&startDate=2017-02-20T00%3A00%3A00&endDate=2017-02-21T01%3A59%3A59&timezone=Europe/Berlin';
            $url = 'https://api.awin.com/publishers/' . $id . '/transactions/?accessToken=' . $pwd . '&startDate=' . $dStartDate . '&endDate=' . $dEndDate . '&timezone=Europe/Berlin';
            $result = \file_get_contents($url);
            if ($result === false)
            {
                throw new \Exception("php-oara AffiliateWindow - file_get_contents is false");
            } else {
                $content = \utf8_encode($result);
                $totalTransactions = \json_decode($content);
            }
        } catch (\Exception $e) {
            throw new \Exception($e);
        }
        return $totalTransactions;
    }

    /**
     * @param $rowAvailable
     * @param $rowsReturned
     * @return int
     */
    private function getIterationNumber($rowAvailable, $rowsReturned)
    {
        $iterationDouble = (double)($rowAvailable / $rowsReturned);
        $iterationInt = (int)($rowAvailable / $rowsReturned);
        if ($iterationDouble > $iterationInt) {
            $iterationInt++;
        }
        return $iterationInt;
    }

    /**
     * @return array
     */
    public function getPaymentHistory()
    {
        $paymentHistory = array();

        $urls = array();
        $urls[] = new \Oara\Curl\Request("https://darwin.affiliatewindow.com/awin/affiliate/" . $this->_userId . "/payments/history?", array());
        $exportReport = $this->_exportClient->get($urls);


        $doc = new \DOMDocument();
        @$doc->loadHTML($exportReport[0]);
        $xpath = new \DOMXPath($doc);
        $results = $xpath->query('//table/tbody/tr');

        $finished = false;
        while (!$finished) {
            foreach ($results as $result) {
                $linkList = $result->getElementsByTagName('a');
                if ($linkList->length > 0) {
                    $obj = array();
                    $date = \DateTime::createFromFormat('j M Y', $linkList->item(0)->nodeValue);
                    $date->setTime(0, 0);
                    $obj['date'] = $date->format("Y-m-d H:i:s");
                    $attrs = $linkList->item(0)->attributes;
                    foreach ($attrs as $attrName => $attrNode) {
                        if ($attrName = 'href') {
                            $parseUrl = \trim($attrNode->nodeValue);
                            if (\preg_match("/\/paymentId\/(.+)/", $parseUrl, $matches)) {
                                $obj['pid'] = $matches[1];
                            }
                        }
                    }

                    $obj['value'] = \Oara\Utilities::parseDouble($linkList->item(3)->nodeValue);
                    $obj['method'] = trim($linkList->item(2)->nodeValue);
                    $paymentHistory[] = $obj;
                }
            }

            $results = $xpath->query("//span[@id='nextPage']");
            if ($results->length > 0) {
                foreach ($results as $nextPageLink) {
                    $linkList = $nextPageLink->getElementsByTagName('a');
                    $attrs = $linkList->item(0)->attributes;
                    $nextPageUrl = null;
                    foreach ($attrs as $attrName => $attrNode) {
                        if ($attrName = 'href') {
                            $nextPageUrl = trim($attrNode->nodeValue);
                        }
                    }
                    $urls = array();
                    $urls[] = new \Oara\Curl\Request("https://darwin.affiliatewindow.com" . $nextPageUrl, array());
                    $exportReport = $this->_exportClient->get($urls);
                    $doc = new \DOMDocument();
                    @$doc->loadHTML($exportReport[0]);
                    $xpath = new \DOMXPath($doc);
                    $results = $xpath->query('//table/tbody/tr');
                }
            } else {
                $finished = true;
            }
        }
        return $paymentHistory;
    }

    /**
     * @param $paymentId
     * @return array
     */
    public function paymentTransactions($paymentId)
    {
        $transactionList = array();
        $urls = array();
        $urls[] = new \Oara\Curl\Request("https://darwin.affiliatewindow.com/awin/affiliate/" . $this->_userId . "/payments/download/paymentId/" . $paymentId, array());
        $exportReport = $this->_exportClient->get($urls);
        $exportData = \str_getcsv($exportReport[0], "\n");
        $num = \count($exportData);
        $header = \str_getcsv($exportData[0], ",");
        $index = \array_search("Transaction ID", $header);
        for ($j = 1; $j < $num; $j++) {
            $transactionArray = \str_getcsv($exportData[$j], ",");
            $transactionList[] = $transactionArray[$index];
        }
        return $transactionList;
    }
}
