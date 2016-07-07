<?php
// Load database connection, helpers, etc.
require_once(__DIR__ . '/errors.php');
require_once(__DIR__ . '/include.php');


//timezone setting
ini_set('date.timezone', 'Europe/Rome');

//Function to split m-Y format date in a more readable format (Monthname [space] Year)
//2nd parameter $monthOrYear is useful to return the month part (0) or year part (1) of the date passed as first parameter
function splitDate($date, $monthOrYear) {
    $d = explode("-", $date);
    if ($monthOrYear == 0) {
        //return the month name of passed month number
        $dateObj = DateTime::createFromFormat('!m', $d[0]);
        $monthName = $dateObj->format('F'); // March
        return $monthName;
    } else {
        //return the year
        return $d[1];
    }
}

// Vars
$period = 12; // Life-Time of 12 months
$commission = 0.10; // 10% commission

$bookersOnPeriod = array();
$LTV = array();
$bookingsOnPeriod = array();

//checking get parameters...
if (filter_input(INPUT_GET, "period") && filter_input(INPUT_GET, "commission")) {
    $period = filter_input(INPUT_GET, "period"); // Life-Time of 12 months
    $commission = filter_input(INPUT_GET, "commission") / 100; // 10% commission
}



//Prepare query
//Calculate timestamp minus period directly in query (see CAST function in WHERE clause)
$query = "SELECT bookers.id AS bookerID, 
                strftime('%m-%Y', MIN(bookingitems.end_timestamp),'unixepoch') as min_date
                FROM bookingitems
                INNER JOIN bookers ON bookings.booker_id = bookers.id
                INNER JOIN bookings ON bookingitems.booking_id = bookings.id
                WHERE bookingitems.end_timestamp <= strftime('%s', datetime(CAST(strftime('%s', strftime('%Y-%m-%d','now') ) AS date_now), 'unixepoch', 'start of month', '-" . $period . " months')) 
                GROUP BY bookers.user_id";

$result = $db
        ->prepare($query)
        ->run();


foreach ($result->fetchAll() as $row) {
    $bookersOnPeriod[$row->bookerID] = $row->min_date;
}

$query = "SELECT bookings.booker_id, SUM(locked_total_price) AS tot_price, COUNT(bookingitems.id) AS num_bookings
		FROM bookingitems
		INNER JOIN bookings ON bookings.id = bookingitems.booking_id
		WHERE bookings.booker_id IN (" . implode(',', array_keys($bookersOnPeriod)) . ")
		GROUP BY bookings.booker_id";

$result = $db
        ->prepare($query)
        ->run();


foreach ($result->fetchAll() as $row) {
    //get the month
    $month = $bookersOnPeriod[$row->booker_id];

    if (!array_key_exists($month, $LTV)) {
        $LTV[$month] = 0;
        $bookingsOnPeriod[$month] = 0;
        $bookersOnPeriod[$month] = 0;
    }
    $LTV[$month] += $row->tot_price;
    $bookingsOnPeriod[$month] += $row->num_bookings;
    $bookersOnPeriod[$month] ++;
}

//Ordering array from older to newer date
uksort($LTV, function($varA, $varB) {
    $varA = DateTime::createFromFormat('m-Y', $varA);
    $varB = DateTime::createFromFormat('m-Y', $varB);
    return ($varA == $varB ? 0 : $varA < $varB ? -1 : 1);
});
?>
<!doctype html>
<html>
    <head>
        <title>Assignment 1: Create a Report (SQL)</title>
        <meta http-equiv="content-type" content="text/html; charset=utf-8" />
        <style type="text/css">
            .report-table .right
            {
                text-align: right;
            }
        </style>


        <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1q8mTJOASx8j1Au+a5WDVnPi2lkFfwwEAa8hDDdjZlpLegxhjVME1fgjWPGmkzs7" crossorigin="anonymous">      
        <link href="http://cdn.datatables.net/1.10.12/css/jquery.dataTables.min.css" rel="stylesheet">
        <link href="assets/css/style.css" rel="stylesheet">
    </head>
    <body>
        <div id="wrapper">

            <div id="page-wrapper">    

                <div class="row">
                    <div class="col-lg-6"><h1>Report:</h1></div>
                    <div class="col-lg-6">
                        
                      
                        <h4>Change Period and Commission</h4>
                        <form method="GET">
                            <input name="commission" type="digits" size="1" required minlength="1" maxlength="2" value="<?= $commission * 100 ?>">%
                            
                            <select name="period" required>
                                <option value="3"  <?php if ($period == 3) { ?>selected<?php } ?>>3 months</option>
                                <option value="6"  <?php if ($period == 6) { ?>selected<?php } ?>>6 months</option>
                                <option value="12" <?php if ($period == 12) { ?>selected<?php } ?>>12 months</option>
                                <option value="18" <?php if ($period == 18) { ?>selected<?php } ?>>18 months</option>
                            </select>
                            
                            <button type="submit" value="Go" class="btn btn-success btn-xs"> Go </button>
                        </form>
                        

                    </div>
                </div>
                <hr>

                <div class="row">

                    <div class="col-lg-12">

                        <table class="report-table" id="reportLTV">
                            <thead>
                                <tr>
                                    <th>Start</th>
                                    <th>Bookers</th>
                                    <!--<th># of bookings</th>-->
                                    <th># of bookings (avg)</th>
                                    <!--<th>Turnover</th>-->
                                    <th>Turnover (avg)</th>
                                    <th>LTV</th>
                                </tr>
                            </thead>
                            <tbody>

                                <?php foreach ($LTV as $month => $turnover): ?>
                                    <tr>
                                        <td><?= splitDate($month, 0) ?> <?= splitDate($month, 1) ?></td>
                                        <td><?= $bookersOnPeriod[$month]; ?></td>
                                        <!--<td><?= number_format($bookingsOnPeriod[$month], 0); ?></td>-->
                                        <td><?= number_format($bookingsOnPeriod[$month] / $bookersOnPeriod[$month], 2, ',', '.'); ?></td>
                                        <!--<td><?= number_format($turnover, 2, ',', '.'); ?></td>-->
                                        <td><?= number_format($turnover / $bookingsOnPeriod[$month], 2, ',', '.'); ?></td>
                                        <td><?= number_format($turnover * $commission, 2, ',', '.'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="right"><strong>Total rows:</strong></td>
                                    <td><?= count($LTV); ?></td>
                                </tr>
                            </tfoot>
                        </table>

                    </div>
                </div>
            </div>
        </div>
    </body>


    <script src="https://code.jquery.com/jquery-2.2.4.min.js" integrity="sha256-BbhdlvQf/xTY9gja0Dq3HiwQF8LaCRTXxZKRutelT44=" crossorigin="anonymous"></script>  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js" integrity="sha384-0mSbJDEHialfmuBBQP6A4Qrprq5OVfW37PRR3j5ELqxss1yVqOtnepnHVP9aJ7xS" crossorigin="anonymous"></script>    
    <script src="//cdn.datatables.net/1.10.12/js/jquery.dataTables.min.js"></script>
    <script src="assets/js/functions.js"></script>
</html>