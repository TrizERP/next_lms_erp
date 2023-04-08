<?php
error_reporting(1);

$path = "http://117.247.81.235/sattvavikasmdl/apps/";
if($_REQUEST["FOR_DEBUG"] == "YES"){
    $path = "http://appdev.triz.co.in/dev_mdl/apps/";
}

//475 student

echo '{"webhost" : "'.$path.'"}';

?>
