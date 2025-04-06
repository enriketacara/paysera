<?php

#################################################################################################### --- ERRORS
error_reporting(0);
session_start();
#######################################################################################################
include_once("config/app_config.php");
if (!isset($_SESSION['login_user'])) {
    header('Location: index.php'); // Redirecting To Home Page
}
if (!isset($_SESSION['access']) || $_SESSION['access'] === 'client') {
    http_response_code(404);
    die();
}
##################################################################ALLOW ACCESS ONLY TO CERTAIN USERS#############################################
$allowed_users=$GLOBALS['SETTINGS']['ACCESS_PAYSERA_STATEMENTS'];
if (
    !isset($_SESSION['login_user']) ||                         // Check if session user is not set
    strpos($allowed_users, $_SESSION['login_user']) === false // Check if session user is not in the allowed users string

) {
    http_response_code(404);
    echo '<script>alert("Access denied.You do not have permission to access this site");</script>';
    exit;
}
?>
<!-- #################################################################################################### --- HTML HEADER -->

<!DOCTYPE html>
<html>
<!--<head>-->

<?php include("head.php"); ?>
<?php echo "<script>window.onload=function(){\$('[data-toggle=\"tooltip\"]').tooltip({'html': true});}</script>"; ?>


<!--</head>-->

<body>
<?php include("navigation.php"); ?>

<script type="text/javascript" charset="utf8" src="/billing-system/resources/js/popper.min.js"></script>
<link rel="stylesheet" type="text/css" href="/billing-system/resources/css/jquery.dataTables.min.css">
<link rel="stylesheet" type="text/css" href="/billing-system/resources/css/responsive.dataTable.min.css">
<link rel="stylesheet" type="text/css" href="/billing-system/resources/css/jquery-confirm.min.css">
<link rel="stylesheet" type="text/css" media="screen" href="/billing-system/resources/css/jquery-ui.min.css">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@500&display=swap" rel="stylesheet">
<script src="https://kit.fontawesome.com/e2f8401cee.js" crossorigin="anonymous"></script>

<style>
    /* Define the custom class for a bigger Swal prompt */
    .bigger-swal-prompt {
        font-size: 15px; /* Adjust the font size as needed */
    }
    .container-custom-border {
        border: 2px inset #b5abab;
        /*background: #eaeaea;*/
        background: rgb(34,193,195);
        background: linear-gradient(0deg, rgba(34,193,195,1) 0%, rgba(200,199,199,1) 0%, rgba(232,232,232,1) 29%, rgba(255,255,255,1) 73%);
        padding: 10px;
    }
    .container-custom-border-table {
        border: 2px inset #b5abab;
        background: rgb(34,193,195);
        background: linear-gradient(0deg, rgba(34,193,195,1) 0%, rgba(223,223,223,1) 0%, rgba(255,255,255,1) 45%, rgba(243,243,243,1) 100%);
        padding: 10px;
    }
    .padding_left_right {
        padding-left: 0px !important;
        padding-right: 0px !important;
    }
</style>
<!-- #################################################################################################### --- DATATABLE -->
<div class="container mt-5 panel panel-default container-custom-border">
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <div class="col-md-12 text-center" style="margin-bottom: 15px;">
                    <img src="PHP/paysera_logo.png" alt="Paysera Logo" height="60"><br>
                </div>
                <label for="walletSelect">Select Wallet:</label>
                <select id="walletSelect" class="form-control">
                    <option value="" disabled selected>Select Wallet</option>
                    <option value="Paysera1">Paysera 1</option>
                    <option value="Paysera2">Paysera 2</option>
                    <option value="Paysera3">Paysera 3</option>
                    <option value="Paysera4">Paysera 4</option>
                    <option value="ConnectiVoice">Paysera ConnectiVoice</option>
                    <option value="visadebit">VISADEBIT</option>

                </select>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label for="dateRange">Select Date Range:</label>
                <select id="dateRange" class="form-control">
                    <option value="today" disabled selected> Select time period</option>
                    <option value="today">Today</option>
                    <option value="yesterday">Yesterday</option>
                    <option value="thisWeek">This Week</option>
                    <option value="lastWeek">Last Week</option>
                    <option value="thisMonth">This Month</option>
                    <option value="lastMonth">Last Month</option>
                    <option value="last30Days">Last 30 Days</option>
                    <option value="lastAndCurrentMonth">Last and Current Month</option>
                    <option value="thisYear">This Year</option>
                    <option value="lastYear">Last Year</option>
                    <!-- Add more options as needed -->
                </select>

            </div>
            <div class="form-group">
                <label for="fromDate">From Date:</label>
                <input type="date" id="fromDate" class="form-control">
            </div>
            <div class="form-group">
                <label for="toDate">To Date:</label>
                <input type="date" id="toDate" class="form-control">
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6 d-flex justify-content-start">
            <!--            <div class="form-group text-center" style="margin-bottom: 15px;" >-->
            <div class="form-group">
                <button id="filter_statements" class="btn btn-primary"><span class=" glyphicon glyphicon-filter"> </span> Filter Statements</button>
                <button id="export_statements" class="btn btn-success"><span class=" glyphicon glyphicon-export"> </span> Export Statements</button>
                <button id="pdf_statements" class="btn btn-danger"><span class="glyphicon glyphicon-save-file"> </span> PDF </button>
            </div>
        </div>
    </div>
</div>

<div id="response"></div>
<div class="container padding_left_right" >
    <p id="global_errors" class="input-error"></p>
    <div id="statements" class="panel panel-default container-custom-border-table">
        <table id="statements_table" class="table table-bordered">
            <thead>
            <tr>
                <th>Statement number</th>
                <th>Transfer number</th>
                <th>Date</th>
                <th>Description</th>
                <th>Recipient</th>
                <th>Type</th>
                <th>Code</th>
                <th>Reference number</th>
                <th>Amount</th>
            </tr>
            </thead>
            <thead class="filters">
            <tr>
                <td><input type="text" class='form-control' placeholder="Search" /></td>
                <td><input type="text" class='form-control' placeholder="Search" /></td>
                <td><input type="text" class='form-control' placeholder="Search" /></td>
                <td><input type="text" class='form-control' placeholder="Search" /></td>
                <td><input type="text" class='form-control' placeholder="Search" /></td>
                <td><input type="text" class='form-control' placeholder="Search" /></td>
                <td><input type="text" class='form-control' placeholder="Search" /></td>
                <td><input type="text" class='form-control' placeholder="Search" /></td>
                <td><input type="text" class='form-control' placeholder="Search" /></td>

            </tr>
            </thead>
        </table>
    </div>
</div>

<!-- #################################################################################################### --- FOOTER -->
<script type="text/javascript" charset="utf8" src="/billing-system/resources/js/alertify.min.js"></script>
<script type="text/javascript" charset="utf8" src="/billing-system/resources/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" charset="utf8" src="/billing-system/resources/js/jquery-confirm.min.js"></script>
<script type="text/javascript" charset="utf8" src="/billing-system/resources/js/jquery-ui.min.js"></script>
<script type="text/javascript" charset="utf8" src="/billing-system/resources/js/decimal.min.js"></script>
<script type="text/javascript" charset="utf8" src="/billing-system/resources/js/loadingoverlay.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/buttons/1.3.1/js/dataTables.buttons.min.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/buttons/1.3.1/js/buttons.html5.min.js"></script>
<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script type="text/javascript" src="/billing-system/resources/js/services/PayseraStatementsServices.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // #################################################################################################### - MODULE INIT
    $(function() {
        Module.init();
    });

    var Module = (function() {
        let datatable = null;

        var module = {
            init: function() {
                showTable();
                $("#filter_statements").on("click", function() {
                    const fromDate = $("#fromDate").val();
                    const toDate = $("#toDate").val();
                    const selectedWallet = $('#walletSelect').val();
                    populateTableWithData(fromDate, toDate,selectedWallet); // Pass the selected dates
                });
                $("#export_statements").on("click", function() {
                    const fromDate = $("#fromDate").val();
                    const toDate = $("#toDate").val();
                    const selectedWallet = $('#walletSelect').val();
                    exportStatements(fromDate, toDate,selectedWallet); // Pass the selected dates
                });
                $("#pdf_statements").on("click", function() {
                    const fromDate = $("#fromDate").val();
                    const toDate = $("#toDate").val();
                    const selectedWallet = $('#walletSelect').val();
                    generatePdf_(fromDate, toDate,selectedWallet); // Pass the selected dates
                });
            },
            refresh: function() {
                if (datatable) {
                    datatable.ajax.reload(null, false);
                }
            }
        };

        function showTable() {
            datatable = $("#statements_table").DataTable({
                'responsive': true,
                'bPaginate': true,
                'processing': false,
                'serverSide': false,
                'ordering': true,
                'columns': [
                    // Use 'date' property
                    { 'data': 'statementId' },
                    { 'data': 'transferId' },
                    { 'data': 'date' },
                    { 'data': 'description' },
                    { 'data': 'sender' },
                    { 'data': 'amountType' },
                    { 'data': 'code' },
                    { 'data': 'referenceNo' },
                    { 'data': 'amount' }
                    // { 'data': 'currency' }
                ],
                'orderCellsTop': true,
                'order': [0, 'desc'],
                'fixedHeader': true
            });

            $("#statements_table .filters td").each(function(i) {
                $("input", this).on("keyup change", function() {
                    if (datatable.column(i).search() !== this.value) {
                        datatable.column(i).search(this.value).draw();
                    }
                });
            });
        }
        function populateTableWithData(fromDate,toDate,selectedWallet) {
            console.log("From Date:", fromDate);
            console.log("To Date:", toDate);
            $("#export_statements").prop("disabled", false);
            $.LoadingOverlay("show", {
                fade: [0, 0]
            });
            $.ajax({
                'type': 'POST',
                'url': PayseraStatementsServices.get_paysera_statements,
                'data': {
                    'fromDate': fromDate,
                    'toDate': toDate,
                    'wallet':selectedWallet
                },
                'dataType': 'json', // Ensure that you're expecting JSON data
                success: function(data) {
                    populateStatementTable(data);
                    $.LoadingOverlay("hide");
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error("AJAX Error:", textStatus, errorThrown);
                    alertify.error("Failed to fetch data.");
                    $.LoadingOverlay("hide");
                }
            });

        }
        function exportStatements(fromDate,toDate,selectedWallet) {

            $.LoadingOverlay("show", {
                fade: [0, 0]
            });
            $.ajax({
                'type': 'POST',
                'url': PayseraStatementsServices.exportExcel,
                'data': {
                    'fromDate': fromDate,
                    'toDate': toDate,
                    'wallet':selectedWallet
                },
                'dataType': 'json', // Ensure that you're expecting JSON data
                success: function(data) {
                    if (data.filename) {
                        console.log("test");
                        var basename = data.filename;
                        console.log(basename);
                        basename = btoa(basename);
                        console.log(basename);

                        // Display a link to download the generated Excel file
                        var downloadLink = '<a class="btn btn-success" href="/billing-system/api/v1/downloadFile.php?q=' + basename + '" download><span class="glyphicon glyphicon-save"></span>  Paysera Statements</a>'
                        Swal.fire({
                            icon: 'info',
                            title: 'Download Statements',
                            html: downloadLink,
                            showConfirmButton: false,
                            showCloseButton: true,
                            // confirmButtonText: "",
                            customClass: {
                                popup: 'bigger-swal-prompt' // Apply a custom class for styling
                            }

                        });
                    } else {
                        alert("Excel file generation failed.");
                    }
                    $.LoadingOverlay("hide");
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error("AJAX Error:", textStatus, errorThrown);
                    alertify.error("Failed to fetch data.");
                    $.LoadingOverlay("hide");
                }
            });

        }
        function generatePdf_(fromDate,toDate,selectedWallet) {

            $.LoadingOverlay("show", {
                fade: [0, 0]
            });
            $.ajax({
                'type': 'POST',
                'url': PayseraStatementsServices.generatePdf,
                'data': {
                    'fromDate': fromDate,
                    'toDate': toDate,
                    'wallet':selectedWallet
                },
                'dataType': 'json', // Ensure that you're expecting JSON data
                success: function(data) {
                    console.log("test");
                    if (data.filename) {
                        // Display a link to download the generated Excel file
                        var basename = data.filename;
                        basename = btoa(basename);
                        var downloadLink = '<a class="btn btn-success" href="/billing-system/api/v1/downloadFile.php?q=' + basename+ '" download><span class="glyphicon glyphicon-save"></span>  Paysera Statements</a>'
                        Swal.fire({
                            icon: 'info',
                            title: 'Download Statements',
                            html: downloadLink,
                            showConfirmButton: false,
                            showCloseButton: true,
                            // confirmButtonText: "",
                            customClass: {
                                popup: 'bigger-swal-prompt' // Apply a custom class for styling
                            }
                        });
                    } else {
                        alert("Pdf file generation failed.");
                    }
                    $.LoadingOverlay("hide");
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error("AJAX Error:", textStatus, errorThrown);
                    alertify.error("Failed to fetch data.");
                    $.LoadingOverlay("hide");
                }
            });

        }

        function populateStatementTable(data) {
            datatable.clear().draw();

            data.forEach(function(statement) {
                datatable.row.add({
                    'date': statement.date,
                    'description': statement.description,
                    'transferId': statement.transferId,
                    'statementId': statement.statementId,
                    'sender': statement.sender,
                    'amountType': statement.amountType,
                    'referenceNo': statement.referenceNo,
                    'code': statement.code,
                    'amount': statement.amount
                    // 'currency': statement.currency
                });
            });

            datatable.draw();
            // Module.refresh();
            $.LoadingOverlay("hide");
        }

        // ...
        return module;
    })();

    // ###############################################Date Range JS#############################################################
    document.getElementById('dateRange').addEventListener('change', function () {
        const selectedOption = this.value;
        const fromDateInput = document.getElementById('fromDate');
        const toDateInput = document.getElementById('toDate');

        const today = new Date();
        const thisYear = today.getFullYear();
        const thisMonth = today.getMonth() + 1; // Month is 0-based
        const todayDate = today.getDate();
        const dayOfWeek = today.getDay(); // 0 = Sunday, 1 = Monday, ...

        const formatDate = (date) => {
            const yyyy = date.getFullYear();
            const mm = String(date.getMonth() + 1).padStart(2, '0'); // Month is 0-based
            const dd = String(date.getDate()).padStart(2, '0');
            return `${yyyy}-${mm}-${dd}`;
        };

        switch (selectedOption) {
            case 'today':
                fromDateInput.value = toDateInput.value = formatDate(today);
                break;
            case 'yesterday':
                const yesterday = new Date(today);
                yesterday.setDate(today.getDate() - 1);
                fromDateInput.value = toDateInput.value = formatDate(yesterday);
                break;
            case 'thisWeek':
                const startOfWeek = new Date(today);
                startOfWeek.setDate(today.getDate() - dayOfWeek + (dayOfWeek === 0 ? -6 : 1));
                fromDateInput.value = formatDate(startOfWeek);
                toDateInput.value = formatDate(today);
                break;
            case 'lastWeek':
                const startOfLastWeek = new Date(today);
                startOfLastWeek.setDate(today.getDate() - dayOfWeek - 6);
                const endOfLastWeek = new Date(today);
                endOfLastWeek.setDate(today.getDate() - dayOfWeek);
                fromDateInput.value = formatDate(startOfLastWeek);
                toDateInput.value = formatDate(endOfLastWeek);
                break;
            case 'thisMonth':
                fromDateInput.value = `${thisYear}-${String(thisMonth).padStart(2, '0')}-01`;
                toDateInput.value = formatDate(today);
                break;
            // case 'lastMonth':
            //     const lastMonth = thisMonth === 1 ? 12 : thisMonth - 1;
            //     fromDateInput.value = `${thisYear}-${String(lastMonth).padStart(2, '0')}-01`;
            //     toDateInput.value = formatDate(today);
            //     break;
            case 'lastMonth':
                const lastMonth = thisMonth === 1 ? 12 : thisMonth - 1;
                const lastDayOfLastMonth = new Date(thisYear, lastMonth, 0); // 0 day of a month is the last day of the previous month
                fromDateInput.value = `${thisYear}-${String(lastMonth).padStart(2, '0')}-01`;
                toDateInput.value = formatDate(lastDayOfLastMonth);
                break;
            case 'last30Days':
                const last30DaysAgo = new Date(today);
                last30DaysAgo.setDate(today.getDate() - 29);
                fromDateInput.value = formatDate(last30DaysAgo);
                toDateInput.value = formatDate(today);
                break;
            case 'lastAndCurrentMonth':
                fromDateInput.value = `${thisYear}-${(thisMonth === 1 ? 12 : thisMonth - 1).toString().padStart(2, '0')}-01`;
                toDateInput.value = formatDate(today);
                break;
            case 'thisYear':
                fromDateInput.value = `${thisYear}-01-01`;
                toDateInput.value = formatDate(today);
                break;
            case 'lastYear':
                fromDateInput.value = `${(thisYear - 1).toString()}-01-01`;
                toDateInput.value = `${(thisYear - 1).toString()}-12-31`;
                break;
            default:
                fromDateInput.value = toDateInput.value = '';
                break;
        }
    });
</script>
</body>

</html>