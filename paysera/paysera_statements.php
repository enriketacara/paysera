<?php


#################################################################################################### --- CEHCK LOG IN
session_start();

#################################################################################################### --- INCLUDES
date_default_timezone_set('Europe/Tirane');
$rootDir = __DIR__ . '/../../../';
require_once $rootDir . 'vendor/autoload.php';
include($rootDir . 'config/app_config.php');
include($rootDir . 'config/globals.php');
include($rootDir . 'Classes/EmailService.php');
include($rootDir . 'api/v1/Bank/model/BankReport.php');
$dbh = include(__DIR__ . '/../../../config/connection.php');

require_once $rootDir . 'paysera/src/Paysera/WalletApi/Autoloader.php';
Paysera_WalletApi_Autoloader::register();
#################################################################################################### --- TIME CONFIG
if (isset($_POST['fromDate']) && !empty($_POST['fromDate']) && isset($_POST['toDate']) && !empty($_POST['toDate']) && isset($_POST['wallet']) && !empty($_POST['wallet'])) {
    $selectedWallet = $_POST['wallet'];
    $start_date = $_POST['fromDate'];
    $end_date = $_POST['toDate'];

} else {
    $response = array(
        'error' => [
            'code' => 500,
            'message' => 'Invalid request',
        ]
    );

    header('HTTP/1.1 500');
    echo json_encode($response);
    exit();
}

// Modify the $walletId based on the selected wallet
if ($selectedWallet === 'Paysera1') {
    $walletId = '235235'; // Paysera 1 wallet ID
} elseif ($selectedWallet === 'Paysera2') {
    $walletId = '25235'; // Paysera 2 wallet ID
} elseif ($selectedWallet === 'Paysera3') {
    $walletId = '243532'; // Paysera 3 wallet ID
} elseif ($selectedWallet === 'Paysera4') {
    $walletId = '23235'; // Paysera 4 wallet ID
}elseif ($selectedWallet === 'ConnectiVoice') {
    $walletId = '2523'; // Paysera 5
}elseif ($selectedWallet === 'visadebit') {
    $walletId = '252354'; // Paysera 6
}else {
    header('HTTP/1.1 500');
    $response = array(
        'error' => [
            'code' => 500,
            'message' => 'Select a wallet',
        ]
    );

    echo json_encode($response);
    exit();
}

#################################################################################################### --- PAYSERA CREDENTIALS

// credentials for API
$clientId = 'clientId';
$secret = 'secretid';

#################################################################################################### --- WALLET OBJECT
$api = new Paysera_WalletApi($clientId, $secret);
#################################################################################################### --- OAUTH METHOD
$oauth = $api->oauthConsumer();
try {
//    $data = [];
    $all_statements = [];
    try {
        do {
            $data = [];
            $filters = new Paysera_WalletApi_Entity_Statement_SearchFilter();
            // No need to set a limit
            $fromDate = (new DateTime($start_date));
            $toDate = (new DateTime($end_date))->modify('+1 day');
            $filters->setLimit(200);
            $filters->setFromDate($fromDate);
            $filters->setToDate($toDate);

            $batchStatements = $api->walletClient()->getWalletStatements($walletId, $filters);
            // Merge the batch of statements into the accumulated array
            foreach ($batchStatements as $statement) {
                $date = $statement->getDate()->format('Y-m-d H:i:s');
                $description = $statement->getDetails();

                $amount_unformatted = $statement->getAmount()->getAmount();
                $amount_unformatted = round($amount_unformatted / 100, 2);
                $amount = number_format($amount_unformatted, 2, '.', '');
                $currency = $statement->getAmount()->getCurrency();
                $amount = $amount . " " . $currency;

                // Determine if it's a credit or debit transaction
                $direction = $statement->getDirection();
                $transferId = $statement->getTransferId();
                $statementId = $statement->getId();
                $other_party = $statement->getOtherParty();
                $referenceNo = $statement->getReferenceNumber();

                if ($other_party !== null) {
                    $sender = $other_party->getName() . " " . $other_party->getBic() . " " . $other_party->getAccountNumber();
                    $code = $other_party->getCode();

                } else {
                    $sender = "Commission fee";
                    $code = "-";

                }
                if ($direction === 'in') {
                    $amountType = "C";

                } else {
                    $amountType = "D";
                    $amount = -$amount . " " . $currency; // Apply negative sign for debit

                }

                $data[] = [
                    'date' => $date,
                    'description' => $description,
                    'amountType' => $amountType,
                    'sender' => $sender,
                    'transferId' => $transferId,
                    'statementId' => $statementId,
                    'referenceNo' => $referenceNo,
                    'code' => $code,
                    'amount' => $amount
                ];
            }
            $lastElement = end($data);
            $end_date = $lastElement['date'];
            $all_statements = array_merge($all_statements, $data);

        } while (count($data) >= 200);

        // Prepare JSON response
        $jsonResponse = json_encode($all_statements, JSON_PRETTY_PRINT);

        // Set appropriate headers for JSON
        header('Content-Type: application/json');

        // Output JSON response
        echo $jsonResponse;

    } catch (Exception $e) {
        // Handle exceptions
        $jsonResponse = json_encode("Error!", JSON_PRETTY_PRINT);

    }
} catch (Exception $e) {
    // Handle exceptions
    $jsonResponse = json_encode("Something went wrong", JSON_PRETTY_PRINT);

}


