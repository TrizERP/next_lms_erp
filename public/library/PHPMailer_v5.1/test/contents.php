<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
    <title>Email test</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  </head>
  <body>


      
	<p><?php foreach($_SESSION['receipt_record']['loan'] as $loan)
{
	        echo '<tr>';
                echo '<td>'.$loan['itemCode'].'</td>&nbsp;';
                echo '<td>'.$loan['title'].'</td>&nbsp;';
                echo '<td>'.$loan['loanDate'].'</td>&nbsp;';
                echo '<td>'.$loan['dueDate'].'</td>&nbsp;';
                echo '</tr>';

}?></p>



  </body>
</html>
