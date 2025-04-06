<?php

session_start();
$rootDir = __DIR__ . '/../../../';
include_once($rootDir . 'Classes/PHPExcel.php');
include_once($rootDir . 'Classes/PHPExcel/IOFactory.php');
include_once($rootDir . 'Classes/PHPExcel/Writer/Excel2007.php');
#################################################################################################### --- INCLUDES
date_default_timezone_set('Europe/Tirane');
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
    $walletId = '1346'; // Paysera 1 wallet ID
    $iban = 'LT7535000100'; // Paysera 1 wallet ID
} elseif ($selectedWallet === 'Paysera2') {
    $walletId = '45757'; // Paysera 2 wallet ID
    $iban = 'LT60350001'; // Paysera 2 wallet ID

} elseif ($selectedWallet === 'Paysera3') {
    $walletId = '457457'; // Paysera 3 wallet ID
    $iban = ' LT93350001001'; // Paysera 3 wallet ID

} elseif ($selectedWallet === 'Paysera4') {
    $walletId = '4745'; // Paysera 4 wallet ID
    $iban = 'LT7635000'; // Paysera 4 wallet ID
} elseif ($selectedWallet === 'ConnectiVoice') {
    $walletId = '456456'; // Paysera ConnectiVoice wallet ID
    $iban = 'LT2935000144'; // Paysera ConnectiVoice wallet ID
} elseif ($selectedWallet === 'visadebit') {
    $walletId = '5775'; // Paysera visadebit wallet ID
    $iban = 'LT543500010'; // Paysera visadebit wallet ID
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

// credentials for API
$clientId = 'clientid';
$secret = 'secretid';

#################################################################################################### --- WALLET OBJECT

$api = new Paysera_WalletApi($clientId, $secret);

#################################################################################################### --- OAUTH METHOD

$oauth = $api->oauthConsumer();

try {
    $toDate0 = (new DateTime($end_date));
    //add +1 day because  toDate date is not included while filtering ,fromDate=27 and toDate=29,it gets only the 27 and 28,but after adding 1 day it gets also 29
    try {
        $all_statements = [];
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
//                $amount = $amount . " " . $currency;

                // Determine if it's a credit or debit transaction
                $direction = $statement->getDirection();
                $transferId = $statement->getTransferId();
                $statementId = $statement->getId();
                $other_party = $statement->getOtherParty();
                $referenceNo = $statement->getReferenceNumber();
                $type = $statement->getType();
                if ($other_party !== null) {
                    $sender = $other_party->getName();
                    $code = $other_party->getCode();
                    // $iban = $other_party->getIban();
                    $iban = $other_party->getAccountNumber(); // or getAccount()
                } else {
                    $sender = "Commission fee";
                    $code = "-";

                }
                if ($direction === 'in') {
                    $amountType = "C";

                } else {
                    $amountType = "D";
                    $amount = -$amount ; // Apply negative sign for debit

                }

                $data[] = [
                    'date' => $date,
                    'description' => $description,
                    'amountType' => $amountType,
                    'sender' => $sender,
                    'transferId' => $transferId,
                    'statementId' => $statementId,
                    'referenceNo' => $referenceNo,
                    'iban' => $iban,
                    'amount' => $amount,
                    'type' => $type,
                    'currency'=>$currency,
                    'code'=>$code,
                    'reference'=>$referenceNo,
                    'amountType_'=>$amountType
                ];
            }
            $lastElement = end($data);
            $end_date = $lastElement['date'];
            $all_statements = array_merge($all_statements, $data);

        } while (count($data) >= 200);


        function generateExcelFromValues($values, $savePath, $fromDate, $toDate0, $iban, $selectedWallet)
        {
            $phpExcel = new PHPExcel();
            $sheet = $phpExcel->getActiveSheet();
            $sheet->setTitle('Paysera');
            // Add title cell for the period time
            $period = "Period: " . $fromDate->format('Y-m-d') . " to " . $toDate0->format('Y-m-d');
            $account = "PROFESSIONAL TECHNOLOGIES, SH.P.K.: " . $selectedWallet . "  " . $iban;
            $sheet->mergeCells('A1:L1'); // Merge cells for the title
            $sheet->mergeCells('A2:L2'); // Merge cells for the title
            $sheet->setCellValue('A1', $period);
            $sheet->setCellValue('A2', $account);
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16); // Bold and font size 16
            $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(13); // Bold and font size 16
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
            $styleArray = array(
                'fill' => array(
                    'type' => PHPExcel_Style_Fill::FILL_SOLID,
                    'startcolor' => array('rgb' => 'ADD8E6'), // RGB color for very light blue
                ),
                'font' => array(
                    'bold' => true, // Set font to bold
                ),
            );

            // Apply the style to the header row
            $sheet->getStyle('A3:L3')->applyFromArray($styleArray);
            $row = 3;
            $sheet->setCellValue('A' . $row, 'Type');
            $sheet->setCellValue('B' . $row, 'Statement No.');
            $sheet->setCellValue('C' . $row, 'Transfer No.');
            $sheet->setCellValue('D' . $row, 'Date and time');
            $sheet->setCellValue('E' . $row, 'Recipient / Payer');
            $sheet->setCellValue('F' . $row, 'EVP / IBAN');
            $sheet->setCellValue('G' . $row, 'Code');
            $sheet->setCellValue('H' . $row, 'Amount and currency');
            $sheet->setCellValue('I' . $row, 'Currencies');
            $sheet->setCellValue('J' . $row, 'Purpose of payment');
            $sheet->setCellValue('K' . $row, 'Reference number');
            $sheet->setCellValue('L' . $row, 'Credit/Debit');

            $row++;

            // Add data
            foreach ($values as $value) {
                $sheet->setCellValue('A' . $row, $value['type']);
                $sheet->setCellValue('B' . $row, $value['statementId']);
                $sheet->setCellValue('C' . $row, $value['transferId']);
                $sheet->setCellValue('D' . $row, $value['date']);
                $sheet->setCellValue('E' . $row, $value['sender']);
                $sheet->setCellValue('F' . $row, $value['iban']);
                $sheet->setCellValue('G' . $row, $value['code']);
                $sheet->setCellValue('H' . $row, $value['amount']);
                $sheet->setCellValue('I' . $row, $value['currency']);
                $sheet->setCellValue('J' . $row, $value['description']);
                $sheet->setCellValue('K' . $row, $value['reference']);
                $sheet->setCellValue('L' . $row, $value['amountType_']);
                $row++;
            }
            // Adjust the width of specific columns for headers and data
            $columnWidths = [
                'A' => 15,  // Adjust the width for column A (Statement Id)
                'B' => 15,  // Adjust the width for column B (Transfer Id)
                'C' => 20,  // Adjust the width for column C (Date)
                'D' => 50,  // Adjust the width for column D (Description)
                'E' => 50,  // Adjust the width for column E (Sender)
                'F' => 15,  // Adjust the width for column F (Amount Type)
                'G' => 15,  // Adjust the width for column G (Code)
                'H' => 10,  // Adjust the width for column H (Reference No)
                'I' => 15,
                'J' => 20,// Adjust the width for column I (Amount)
                'K' => 15,
                'L' => 20,// Adjust the width for column I (Amount)
            ];

            foreach ($columnWidths as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }
            // Make headers bold
            $headerRow = 3; // Assuming headers start on row 3
            $lastColumn = 'L'; // Adjust if you add more columns

            for ($col = 'A'; $col <= $lastColumn; $col++) {
                $sheet->getStyle($col . $headerRow)->getFont()->setBold(true);
            }
//            ###################################################################################################
            $currentDateTime = date("Y-m-d-H-i-s");

            // Create Excel writer
            $writer = PHPExcel_IOFactory::createWriter($phpExcel, 'Excel2007');
            $filename = 'PayseraStatements_' . $currentDateTime . '.xlsx'; // Remove the save path from the filename
            $fullFilePath = $savePath . $filename; // Complete file path
            $writer->save($fullFilePath);

            return $filename;
        }

        // Convert JSON response to an array
        $savePath = $rootDir . 'uploaded_exported_files/PayseraStatements/exportedExcels/'; // Replace with your desired directory path
        $filename = generateExcelFromValues($all_statements, $savePath, $fromDate, $toDate0, $iban, $selectedWallet);
        $Directory = '../billing-system/uploaded_exported_files/PayseraStatements/exportedExcels/';
        $filename = $Directory . $filename;

        $response = array(
            'filename' => $filename
        );

        header('Content-Type: application/json');
        echo json_encode($response);

    } catch (Exception $e) {
        // Handle exceptions
        $jsonResponse = json_encode("Error!", JSON_PRETTY_PRINT);

    }
} catch (Exception $e) {
    // Handle exceptions
    $jsonResponse = json_encode("Something went wrong", JSON_PRETTY_PRINT);

}




