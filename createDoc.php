<?php
session_start();

function in_array_r($needle, $haystack){
    foreach($haystack as $array){
        if(in_array($needle,$array)){
            return true;
        }
    }
    return false;

}

/**********************************************************************************************************************
Author: Ryan Sloan
This process will read a sorted .csv F657 GL file from LCDN and analyze the credits and debits and whether or not
they balance and balance the General Ledger by making adjustments to the original file and output it's actions to
a web page.
ryan@paydayinc.com
 *********************************************************************************************************************/

/**
   Data Example:

Index: 0            1        2          3        4    5-Num   6   7   8-DB     9-CR      10-Name
PR061015 WK# 24, 99982473, ER WCA,   6/11/2015, 6508, 200,   20, 60,   2.3,       0,  AGNES L OLSEN
PR061015 WK# 24, 99982473, NM-SUI,   6/11/2015, 6510, 200,   20, 60,  5.47,       0,  AGNES L OLSEN
PR061015 WK# 24, 99982499, NETPAY,   6/11/2015, 1030, 100,    0,   0,    0,  483.07,  AMANDA  JARAMILLO
PR061015 WK# 24, 99982499, OASDI,    6/11/2015, 2210, 100,    0,   0,    0,   44.27,  AMANDA  JARAMILLO
PR061015 WK# 24, 99982499, ER OASDI, 6/11/2015, 2210, 100,    0,   0,    0,   44.27,  AMANDA  JARAMILLO
PR061015 WK# 24, 99982499, MEDICARE, 6/11/2015, 2220, 100,    0,   0,    0,   10.35,  AMANDA  JARAMILLO

**/
if(isset($_SESSION['fileData'])) {
    $fileData = $_SESSION['fileData'];
    $toBalance = $_SESSION['toBalance'];
    //var_dump($fileData);
    //var_dump($toBalance);
    $groups = array();
    foreach ($fileData as $k => $data) {
        $key = (int)$data[4];
        $data[11] = $k;
        $groups[$data[3]][$key][] = $data;
    }
    //var_dump($groups);

    $lines = array();
    foreach ($groups as $groupKey => $group) {

        if (array_key_exists($groupKey, $toBalance)) {

            foreach ($group as $numKey => $number) {

                if (array_key_exists($numKey, $toBalance[$groupKey])) {
                    foreach($number as $array) {
                        $lines[$groupKey][$numKey] = array($array[0],$array[1],$array[2],$array[3],$array[4],1020,$array[6],$array[7],$array[8],0.00,'0.00', $array[11]);
                        //var_dump($array);
                    }

                }
            }
        }
    }
    //var_dump($lines);
    $output = array();

    foreach ($lines as $lineKey => &$line) {

        foreach ($line as $numKey => &$number) {

            $output[$lineKey][$numKey][] = "<b><u>". $lineKey. " - " . $numKey."</u></b>";
            $output[$lineKey][$numKey][] = "Added Additional Line";
            $dt = $toBalance[$lineKey][$numKey][0];
            $ct = $toBalance[$lineKey][$numKey][1];
            $difference = 0.00;

            if ($dt > $ct) {

                $output[$lineKey][$numKey][] = "Previous Credit Value: $" . $number[10];
                $difference = round($dt - $ct, 2);
                $output[$lineKey][$numKey][] = " $".number_format($dt,2)." | $". number_format($ct,2);
                $output[$lineKey][$numKey][] = "Difference: $" . number_format($difference,2);
                $number[10] = (string)(((float)$number[10] + $difference));
                $output[$lineKey][$numKey][] = "Current Value: <span class='currentVal'>$" . (string) $number[10]. "</span>";


            } else if($ct > $dt){

                $output[$lineKey][$numKey][] = "Previous Debit Value: $" . $number[10];
                $difference = round($ct - $dt, 2);
                $output[$lineKey][$numKey][] = " $".number_format($dt,2)." | $". number_format($ct,2);
                $output[$lineKey][$numKey][] = "Difference: -$" . number_format($difference,2);
                $number[10] = (string)(((float)$number[10] - $difference));
                $output[$lineKey][$numKey][] = "Current Value: <span class='currentVal'>$" . (string) $number[10]. "</span>";

            }
            $output[$lineKey][$numKey][] = "<hr>";

            //var_dump($difference);
            //var_dump($number);

        }
        unset($number);

    }
    unset($line);
    //var_dump($lines);
    //var_dump($groups);


    foreach ($groups as $groupKey => &$group) {
        if (array_key_exists($groupKey, $lines)) {
            foreach ($group as $numKey => &$number) {
                //var_dump($groupKey. " " .$numKey);
                if (array_key_exists($numKey, $lines[$groupKey])) {

                    foreach ($number as $key => &$array) {
                        if ($array[11] === $lines[$groupKey][$numKey][11]) {
                            //var_dump($array, $lines[$groupKey][$numKey]);
                            array_push($groups[$groupKey][$numKey],$lines[$groupKey][$numKey]);
                        }

                    }
                    unset($array);
                }
            }
            unset($number);
        }
    }
    unset($group);
    //var_dump("_____________________________________________________________________________________________",$groups);
    $newData = array();
    //var_dump($groups);
    foreach($groups as $ee){
        foreach($ee as $number){

            foreach($number as $file){
                unset($file[11]);
                $newData[] = $file;
            }
        }

    }
    //var_dump($newData);
    //var_dump("OUTPUT___________________________________________________________________________________________",$output);




//Get todays date and time
    $today = new DateTime('now');
    $month = $today->format("m");
    $day = $today->format('d');
    $year = $today->format('y');
    $time = $today->format('H-i-s');


//Create a file name using todays date and current time
    $fileName = "Tesuque_Processed_Files/Tesuque_Processed_File_" .$month . "-" . $day . "-" . $year . "-" . $time . ".csv";
    $handle = fopen($fileName, 'wb');

    //create a .csv from updated original fileData
    for($i = 0; $i < count($newData); $i++){
        fputcsv($handle, $newData[$i]);
        fwrite($handle, "\r\n");
    }

    fclose($handle);

    //assign the filename to the session for download using download.php
    $_SESSION['fileName'] = $fileName;

}else{
    echo "No Results Available";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Tesuque GL Balancer</title>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>

    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">

    <!-- Optional theme -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap-theme.min.css">

    <!-- Latest compiled and minified JavaScript -->
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js"></script>
    <style>
        body{
            background-color: lightyellow;
        }
        table{
            margin-left: auto;
            margin-right: auto;
            text-align: center;
            font-size: 18px;
        }
        h1, h2, h3, h4{
            text-align: center;
        }
        h1 {
            font-weight: bold;
        }
        #analysisDiv{

        }

        .heading{
            font-weight: bold;
            font-size: 18px;
        }
        .green{
            color: green;
        }
        .lineCount{
            text-align: center;
        }
        .currentVal{
            color: green;
        }
        td{
            padding-top: .5em;
            padding-bottom: .5em;
        }

    </style>
</head>
<body>
<header>
    <nav class="navbar navbar-default">
    <div class="container-fluid">
        <!-- Brand and toggle get grouped for better mobile display -->
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1" aria-expanded="false">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="index.php">Home</a>
        </div>

        <!-- Collect the nav links, forms, and other content for toggling -->
        <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
            <ul class="nav navbar-nav">
                <li><a href="download.php">Download File</a></li>
                <li><a href="clear.php">Clear File</a></li>


            </ul>

            <ul class="nav navbar-nav navbar-right">

            </ul>
        </div><!-- /.navbar-collapse -->
    </div><!-- /.container-fluid -->
    </nav>
</header>
<main>
    <h1> TESUQUE GL BALANCER </h1>
    <h4> Below reflects the adjustments made to the original file</h4>
    <p class="lineCount"><?php echo "Line Count: " . count($newData); ?></p>
    <br>
    <hr>
    <div class="container-fluid">
        <div class="row">
            <div id="analysisDiv" class="col-md-12">
                <br>
                <?php
                //display the contents of output
                if(isset($output)){ //if session var is set
                    echo "<table>";
                   foreach($output as $ee){
                        foreach($ee as $array) {
                            foreach ($array as $line) {
                                echo "<tr><td>" . $line . "</td></tr>";
                            }
                        }
                    }
                    echo "</table>";

                }
                ?>
            </div>
        </div>
    </div>
</main>
</body>
</html>