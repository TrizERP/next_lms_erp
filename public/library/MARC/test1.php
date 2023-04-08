<?php
require_once("connection.php"); 
?>
<html>
<form enctype="multipart/form-data" action="upload_usmarc.php" method="post">
<?php echo "Upload" ?>: <input type="file" name="usmarc_data"><br><br>

<hr />
<b>Defaults:</b>
  <input type="submit" value="Upload" class="button">
</form>
</html>
