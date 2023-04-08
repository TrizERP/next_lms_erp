<?php


/* Circulation AJAX Process */

require '../../../sysconfig.inc.php';

// quick return
if (isset($_POST['quickReturnID'])) 
{    
    echo '<script type="text/javascript">'."\n";
    echo 'parent.setContent(\'circulationLayer\', \''.MODULES_WEB_ROOT_DIR.'circulation/circulation_action.php\', \'post\', \'quickReturnID='.trim($_POST['quickReturnID']).'\');'."\n";
    echo 'parent.$(\'quickReturnID\').value = \'\';'."\n";
    echo 'parent.$(\'quickReturnID\').focus();'."\n";
    echo '</script>';
    exit();
}
?>
