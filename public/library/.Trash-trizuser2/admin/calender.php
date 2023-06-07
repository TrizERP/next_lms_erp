<?php
//$link = mysqli_connect('localhost','triz','triz@sql');
//$con = mysqli_select_db('trizino_slibrary',$link);
include_once '../sysconfig.inc.php';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Untitled Document</title>
    <script>
        // Popup window code
        function newPopup(url) {
	popupWindow = window.open(
		url,'popUpWindow','height=200,width=200,left=0,top=0, 	resizable=no,scrollbars=yes,toolbar=no,menubar=no,location=no,directories=no,status=no')
}
function newPopup1(url) {
	popupWindow = window.open(
		url,'popUpWindow','height=600,width=600,left=0,top=0, 	resizable=no,scrollbars=yes,toolbar=no,menubar=no,location=no,directories=no,status=no')
}
function goLastMonth(month, year){
// If the month is January, decrement the year
if(month == 1){
--year;
month = 13;
}
document.location.href = '<?=$_SERVER['PHP_SELF'];?>?month='+(month-1)+'&year='+year;
}
//next function
function goNextMonth(month, year){
// If the month is December, increment the year
if(month == 12){
++year;
month = 0;
}
document.location.href = '<?=$_SERVER['PHP_SELF'];?>?month='+(month+1)+'&year='+year;
}

function remChars(txtControl, txtCount, intMaxLength)
{
if(txtControl.value.length > intMaxLength)
txtControl.value = txtControl.value.substring(0, (intMaxLength-1));
else
txtCount.value = intMaxLength - txtControl.value.length;
}

function checkFilled() {
var filled = 0
var x = document.form1.calName.value;
//x = x.replace(/^\s+/,""); // strip leading spaces
if (x.length > 0) {filled ++}

var y = document.form1.calDesc.value;
//y = y.replace(/^s+/,""); // strip leading spaces
if (y.length > 0) {filled ++}

if (filled == 2) {
document.getElementById("Submit").disabled = false;
}
else {document.getElementById("Submit").disabled = true} // in case a field is filled then erased

}

</script>
<style>
body{
font-family:Georgia, "Times New Roman", Times, serif;
font-size:14px;
pedding:0px;
margin:0px;
}
.title
{

font-size:13px;
font-weight:bold;
font-family:Georgia, "Times New Roman", Times, serif;
}
.button
{
background-color:#cccccc;
}
.today{
/*background-color:#00CCCC;*/
font-weight:bold;
background-image:url(calBg.jpg);
background-repeat:no-repeat;
background-position:center;
position:relative;
}
.today span{
position:absolute;
left:0;
top:0;
}

.today a{
color:#000000;
padding-top:10px;
}
.selected {
color: #FFFFFF;
background-color: #C00000;
}
.event {
background-color: #C6D1DC;
border:1px solid #ffffff;
}
.event1	{
background-color: red;
border:1px solid #ffffff;

}
.normal {

}
table{
border:1px solid #cccccc;
padding:0px;
font-size:12px;
}
th{
width:10px;
background-color:#cccccc;
text-align:center;
color:#ffffff;
border-left:1px solid #ffffff;
}
td{
text-align:center;
padding:2px;
margin:0;
}
table.tableClass{
width:100px;
border:none;
border-collapse: collapse;
font-size:85%;
border:1px dotted #cccccc;
}
table.tableClass input,textarea{
font-size:90%;
}
#form1{
margin:5px 0 0 0;
}
#greyBox{
height:5px;
width:5px;
background-color:#C6D1DC;
border:1px solid #666666;
margin:5px;
}
#legend{
margin:5 0 10px 0px;
width:100px;
}
#hr{border-bottom:1px solid #cccccc;width:100px;}
.output{width:100px;border-bottom:1px dotted #ccc;}
h5{margin:0;}
</style>
</head>

<body>
<!--<div id="legend">
<img src="sq.jpg" /> Scheduled Events<br/><img src="calBg.jpg" height="10"/> Todays Date</div>-->
<?php
//$todaysDate = date("n/j/Y");
//echo $todaysDate;
// Get values from query string
$day = (isset($_GET["day"])) ? $_GET['day'] : "";
$month = (isset($_GET["month"])) ? $_GET['month'] : "";
$year = (isset($_GET["year"])) ? $_GET['year'] : "";
//comparaters for today's date
//$todaysDate = date("n/j/Y");
//$sel = (isset($_GET["sel"])) ? $_GET['sel'] : "";
//$what = (isset($_GET["what"])) ? $_GET['what'] : "";

//$day = (!isset($day)) ? $day = date("j") : $day = "";
if(empty($day)){ $day = date("j"); }

if(empty($month)){ $month = date("n"); }

if(empty($year)){ $year = date("Y"); }
//set up vars for calendar etc
$currentTimeStamp = strtotime("$year-$month-$day");
$monthName = date("F", $currentTimeStamp);
$numDays = date("t", $currentTimeStamp);
$counter = 0;

//$numEventsThisMonth = 0;
//$hasEvent = false;
//$todaysEvents = "";
//run a selec statement to hi-light the days
function hiLightEvt($eMonth,$eDay,$eYear){
global $dbs;
//$tDayName = date("l");
$todaysDate = date("n/j/Y");
$dateToCompare = $eMonth . '/' . $eDay . '/' . $eYear;
if($todaysDate == $dateToCompare){
//$aClass = '<span>' . $tDayName . '</span>';
$aClass='class="today"';
}else {
//$dateToCompare = $eMonth . '/' . $eDay . '/' . $eYear;
//echo $todaysDate;
//return;
    $sql = $dbs->query("select count(calDate) as eCount from calTbl where calDate = '" . $eMonth . '/' . $eDay . '/' . $eYear . "'");
    $sql1 = $dbs->query("select count(holiday_date) as eCount1 from holiday where holiday_date = '" . $eYear . '/' . $eMonth . '/' . $eDay . "'");
//$result1 = mysqli_query($sql1);
//echo $sql;
//return;
//$result = mysqli_query($sql);
//while($row= mysqli_fetch_array($result)){
    while ($row = $sql->fetch_assoc()) {
        if ($row['eCount'] >= 1) {
            $aClass = 'class="event"';
        } elseif ($row['eCount'] == 0) {
            $aClass = 'class="normal"';
        }
    }
//while($row1= mysqli_fetch_array($result1)){
    while ($row1 = $sql1->fetch_assoc()) {
        if ($row1['eCount1'] >= 1) {
$aClass = 'class="event1"';
}
}
}
return $aClass;
}
?>
<table width="100px" height="180px" cellpadding="0" cellspacing="0">
<tr>
<td width="10px" colspan="1">
<input type="button" class="button" value=" < " onClick="goLastMonth(<?php echo $month . ", " . $year; ?>);">
</td>
<td width="10px" colspan="5">
<span class="title"><?php echo $monthName . " " . $year; ?></span><br>
</td>
<td width="10px" colspan="1" align="right">
<input type="button" class="button" value=" > " onClick="goNextMonth(<?php echo $month . ", " . $year; ?>);">
</td>
</tr>
<tr>
<th>S</td>
<th>M</td>
<th>T</td>
<th>W</td>
<th>T</td>
<th>F</td>
<th>S</td>
</tr>
<tr>
<?php
for($i = 1; $i < $numDays+1; $i++, $counter++){
$dateToCompare = $month . '/' . $i . '/' . $year;
$timeStamp = strtotime("$year-$month-$i");
//echo $timeStamp . '<br/>';
if($i == 1){
// Workout when the first day of the month is
$firstDay = date("w", $timeStamp);
for($j = 0; $j < $firstDay; $j++, $counter++){
echo "<td>&nbsp;</td>";
}
}
if($counter % 7 == 0){
?>
</tr><tr>
<?php
}
?>
<!--right here--><td width="8px" <?=hiLightEvt($month,$i,$year);?>><!--<a href="<?=$_SERVER['PHP_SELF'] . '?month='. $month . '&day=' . $i . '&year=' . $year;?>&v=1">--><a href="JavaScript:newPopup('calForm.php?month=<?=$month . '&day=' . $i . '&year=' . $year;?>&v=1');" style="text-decoration:none;"><?=$i;?></a></td>
<?php
}
?>
</table>
<br/>
<a href="JavaScript:newPopup1('calForm.php?v=2')" style="text-decoration:none;">New Event</a>
<?php
if(isset($_GET['v'])){
if(isset($_POST['Submit'])){
    $sql = "insert into calTbl(calName,calDesc,calDate,calStamp) values('" . $_POST['calName'] . "','" . $_POST['calDesc'] . "','" . $_POST['calDate'] . "',now())";
    mysqli_query($sql);
}
$sql="select calName,calDesc, DATE_FORMAT(calStamp, '%a %b %e %Y') as calStamp from calTbl where calDate = '" . $month . '/' . $day . '/' . $year . "'";
//echo $sql;
//return;
    $result = mysqli_query($sql);
    $numRows = mysql_num_rows($result);
?>

<a href="<?=$_SERVER['PHP_SELF'];?>?month=<?=$_GET['month'] . '&day=' . $_GET['day'] . '&year=' . $_GET['year'];?>&v=1&f=true">New Event</a><br/>
<?php
if(isset($_GET['f'])){
//include 'calForm.php';
if(isset($_GET['v'])){
if(isset($_POST['Submit'])){
switch($_GET['e']){
case 1:
$sql="insert into calTbl(calName,calDesc,calDate,calStamp) values('" . $_POST['calName'] ."','";
$sql.= $_POST['calDesc'] . "','" . $_POST['calDate'] . "',now())";
break;

case 2:
$sql="update calTbl set calName='" . $_POST['calName'] . "', calDesc='" . $_POST['calDesc'] . "'";
$sql.=" where calID = '" . $_POST['id'] . "'";
break;
}//end switch
    mysqli_query($sql);
//echo $sql;
//return;
}
}
}
    /*if($numRows == 0 ){
    echo '<h3>No Events</h3>';
    }else{
    //echo '<ul>';
    echo '<h3>Events Listed</h3>';
    while($row = mysqli_fetch_array($result)){
    ?>
    <div class="output">
    <h5><?=$row['calName'];?></h5>
    <?=$row['calDesc'];?><br/>
    Listed On: <?=$row['calStamp'];?>
    </div>
    <?php
    }
    }*/
}
?>
</body>
</body>
</html>
