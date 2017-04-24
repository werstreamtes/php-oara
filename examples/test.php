<?php
include_once (dirname(__FILE__) . '/../settings.php');

$network = new \Oara\Network\Publisher\AffiliateWindow();
$credentialsNeeded = $network->getNeededCredentials();
$credentials = array();
$credentials["user"] = "";
$credentials["password"] = "";
$credentials['accountid'] = "307791";
$credentials['apipassword'] = "9ae553d5-8ac0-49e3-9924-0ed77f35d671";
$credentials['currency'] = null;

$network->login($credentials);
if ($network->checkConnection()){
    //$network->getPaymentHistory();
    $merchantList = array();//$network->getMerchantList();
    $startDate = new \DateTime('2017-04-22');
    $endDate = new \DateTime('2017-04-24');
    $transactionList = $network->getTransactionList($merchantList, $startDate, $endDate);
    var_dump($transactionList);
} else {
    echo "Network credentials not valid \n";
}