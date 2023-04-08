<?php
			if($_POST["name"] == "")
	echo "name is empty";
else
	echo "you typed ".$_POST["name"];

echo '<script type="text/javascript">';
		      	echo 'alert(\''.__('Your Book Request Sucessfully Submited').'\');';
			
			echo '</script>';
?>
