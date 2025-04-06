<?php
//require('../CallCenterInvoice/fpdf/fpdf.php'); // Include the FPDF library
require('../CallCenterInvoice/fpdf/MultiCellTable.php'); // Include the FPDF library
$rootDir = __DIR__ . '/../../../';
include($rootDir . 'config/error_reporting_config.php');
include($rootDir . 'config/app_config.php');
include($rootDir . 'Classes/EmailService.php');

#################################################################################################### --- INCLUDES
date_default_timezone_set('Europe/Tirane');
require_once $rootDir . 'vendor/autoload.php';
include($rootDir . 'config/globals.php');

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
    $walletId = '23252'; // Paysera 1 wallet ID
    $iban = 'LT7535000'; // Paysera 1 wallet ID
} elseif ($selectedWallet === 'Paysera2') {
    $walletId = '25353'; // Paysera 2 wallet ID
    $iban = ' LT603500052792'; // Paysera 2 wallet ID

} elseif ($selectedWallet === 'Paysera3') {
    $walletId = '235233'; // Paysera 3 wallet ID
    $iban = 'LT93350000825'; // Paysera 3 wallet ID

}elseif ($selectedWallet === 'Paysera4') {
    $walletId = '23532'; // Paysera 4 wallet ID
    $iban = 'LT76350001492'; // Paysera 4 wallet ID

} elseif ($selectedWallet === 'ConnectiVoice') {
    $walletId = '23532'; // Paysera ConnectiVoice wallet ID
    $iban = 'LT29350545744'; // Paysera ConnectiVoice wallet ID
} elseif ($selectedWallet === 'visadebit') {
    $walletId = '23533'; // Paysera visadebit wallet ID
    $iban = 'LT54350008930'; // Paysera visadebit wallet ID
} else {
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

$clientId = 'clientid';
$secret = 'secretid';

#################################################################################################### --- WALLET OBJECT

$api = new Paysera_WalletApi($clientId, $secret);

#################################################################################################### --- OAUTH METHOD

$oauth = $api->oauthConsumer();

try {
    $fromDate = (new DateTime($start_date));
    $toDate0 = (new DateTime($end_date));
    $toDate1 = $end_date;

    $toDate = (new DateTime($end_date))->modify('+1 day');

    $account = "PROFESSIONAL TECHNOLOGIES, SH.P.K.: " . $selectedWallet . "  " . $iban;
    $period = "Period: " . $fromDate->format('Y-m-d') . " to " . $toDate0->format('Y-m-d');

    $all_statements=[];
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
            foreach ($batchStatements as $statement) {
                $date = $statement->getDate()->format('Y-m-d H:i:s');
                $description = $statement->getDetails();

                $amount_unformatted = $statement->getAmount()->getAmount();
                $amount_unformatted = round($amount_unformatted / 100, 2);
                $amount = number_format($amount_unformatted, 2, '.', '');
                $currency = $statement->getAmount()->getCurrency();
                $amount=$amount." ".$currency;


                $direction = $statement->getDirection();
                $transferId = $statement->getTransferId();
                $statementId= $statement->getId();
                $other_party = $statement->getOtherParty();
                $referenceNo = $statement->getReferenceNumber();

                if ($other_party !== null) {
                    $sender = $other_party->getName() ." ".$other_party->getBic() ." ". $other_party->getAccountNumber();
                    $code= $other_party->getCode();

                }else {
                    $sender="Commission fee";
                    $code="-";

                }
                if ($direction === 'in') {
                    $amountType = "C";

                } else {
                    $amountType = "D";
                    $amount = -$amount." ".$currency; // Apply negative sign for debit

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

        $pdf = new PDF_MC_Table();
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 12); 
        $pdf->Image('/var/www/html/billing-system/images/header_final.png', 0, 0);

        $imageHeight = 40; 
        $accountY = $imageHeight + 10; 
        $periodY = $accountY + 10; 


        $pdf->SetY($accountY);
        $pdf->Cell(0, 10, $account, 0, 1, 'C');

        $pdf->SetY($periodY);
        $pdf->Cell(0, 10, $period, 0, 1, 'C');

        $pdf->SetWidths(array(20, 30, 15, 30, 20, 20, 10, 15, 25));

        $pdf->SetTextColor(0);
        $pdf->SetDrawColor(100, 100, 100);
        $pdf->SetFont('Arial', 'B', 7); 
        $pdf->SetFillColor(255, 255, 255);

        $pdf->Row(array(
            'Date',
            'Description',
            'Amount Type',
            'Sender',
            'Transfer ID',
            'Statement ID',
            'Reference No',
            'Code',
            'Amount'
        ));
        $pdf->SetFont('Arial', '', 8);

        foreach ($all_statements as $row) {
            $pdf->Row(array(
                $row['date'],
                $row['description'],
                $row['amountType'],
                $row['sender'],
                $row['transferId'],
                $row['statementId'],
                $row['referenceNo'],
                $row['code'],
                $row['amount']
            ));
        }

        $currentDateTime = date("Y-m-d-H-i-s");

        $path=$rootDir."uploaded_exported_files/PayseraStatements/generatedPdf";
        $pdfFile = $path."/".$selectedWallet."_".$currentDateTime.".pdf"; // Specify the file name
        $path1="../billing-system/uploaded_exported_files/PayseraStatements/generatedPdf";
        $pdf1 = $path1."/".$selectedWallet."_".$currentDateTime.".pdf"; // Specify the file name


        $pdf->Output($pdfFile, 'F'); // Use the FPDF object to call Output()

        $response = array(
            'filename' => $pdf1
        );

        header('Content-Type: application/json');
        echo json_encode($response);

    } catch (Exception $e) {

        $jsonResponse = json_encode("Error!", JSON_PRETTY_PRINT);

    }
} catch (Exception $e) {
    $jsonResponse = json_encode("Something went wrong", JSON_PRETTY_PRINT);

}
