<?php
/**
 * Copyright (C) 2007,2008  Arie Nugraha (dicarve@yahoo.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */


/* Biblio Author Adding Pop Windows */

// main system configuration
require '../../../sysconfig.inc.php';
// start the session
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/simbio_dbop.inc.php';

// page title
$page_title = 'Authority List';
// check for biblioID in url
$biblioID = 0;
if (isset($_GET['biblioID']) AND $_GET['biblioID']) {
    $biblioID = (integer)$_GET['biblioID'];
}

// utility function to check editor name
function checkAuthor($str_editor_name)
{
    global $dbs;
    $_q = $dbs->query('SELECT editor_id FROM mst_editor WHERE editor_name=\''.$str_editor_name.'\'');
    if ($_q->num_rows > 0) {
        $_d = $_q->fetch_row();
        // return the editor ID
        return $_d[0];
    }
    return false;
}

// start the output buffer
ob_start();
/* main content */
// biblio author save proccess
if (isset($_POST['save']) AND (isset($_POST['editorID']) OR trim($_POST['search_str']))) {
    $author_name = trim($dbs->escape_string(strip_tags($_POST['search_str'])));
    // create new sql op object
    $sql_op = new simbio_dbop($dbs);
    // check if biblioID POST var exists
    if (isset($_POST['biblioID']) AND !empty($_POST['biblioID'])) {
        $data['biblio_id'] = intval($_POST['biblioID']);
        // check if the editor select list is empty or not
        if (isset($_POST['editorID']) AND !empty($_POST['editorID'])) {
            $data['editor_id'] = $_POST['editorID'];
        } else if ($editor_name AND empty($_POST['editorID'])) {
            // check editor
            $editor_id = checkAuthor($editor_name);
            if ($editor_id !== false) {
                $data['editor_id'] = $editor_id;
            } else {
                // adding new editor
                $editor_data['editor_name'] = $editor_name;
                $editor_data['authority_type'] = $_POST['type'];
                $editor_data['input_date'] = date('Y-m-d');
                $editor_data['last_update'] = date('Y-m-d');
                // insert new editor to editor master table
                @$sql_op->insert('mst_editor', $editor_data);
                $data['editor_id'] = $sql_op->insert_id;
            }
        }
        $data['level'] = intval($_POST['level']);

        if ($sql_op->insert('biblio_editor', $data)) {
            echo '<script type="text/javascript">';
            echo 'alert(\''.gettext('Editor succesfully updated!').'\');';
            echo 'parent.setIframeContent(\'authorIframe\', \''.MODULES_WEB_ROOT_DIR.'bibliography/iframe_editor.php?biblioID='.$data['biblio_id'].'\');';
            echo '</script>';
        } else {
            utility::jsAlert(gettext('Editor FAILED to Add. Please Contact System Administrator')."\n".$sql_op->error);
        }
    } else {
        if (isset($_POST['editorID']) AND !empty($_POST['editorID'])) {
            // add to current session
            $_SESSION['biblioeditor'][$_POST['editorID']] = array($_POST['editorID'], intval($_POST['level']));
        } else if ($edito_name AND empty($_POST['editorID'])) {
            // check author
            $editor_id = checkAuthor($editor_name);
            if ($editor_id !== false) {
                $last_id = $editor_id;
            } else {
                // adding new editor
                $data['editor_name'] = $editor_name;
                $data['authority_type'] = $_POST['type'];
                $data['input_date'] = date('Y-m-d');
                $data['last_update'] = date('Y-m-d');
                // insert new editor to editor master table
                $sql_op->insert('mst_editor', $data);
                $last_id = $sql_op->insert_id;
            }
            $_SESSION['biblioeditor'][$last_id] = array($last_id, intval($_POST['level']));
        }

        echo '<script type="text/javascript">';
        echo 'alert(\''.gettext('Editor added!').'\');';
        echo 'parent.setIframeContent(\'authorIframe\', \''.MODULES_WEB_ROOT_DIR.'bibliography/iframe_editor.php\');';
        echo '</script>';
    }
}

?>

<div style="padding: 5px; background: #CCCCCC;">
<form name="mainForm" action="pop_editor.php?biblioID=<?php echo $biblioID; ?>" method="post">
<div>
    <strong><?php echo gettext('Add Editor'); ?> </strong>
    <hr />
    <form name="searchAuthor" method="post" style="display: inline;">
    <?php
    $ajax_exp = "ajaxFillSelect('../../AJAX_lookup_handler.php', 'mst_editor', 'editor_id:editor_name', 'editorID', $('search_str').getValue())";
    echo gettext('Author Name'); ?> : <input type="text" name="search_str" id="search_str" style="width: 30%;" onkeyup="<?php echo $ajax_exp; ?>" onchange="<?php echo $ajax_exp; ?>" />
    <select name="type" style="width: 20%;"><?php
    foreach ($sysconf['authority_type'] as $type_id => $type) {
        echo '<option value="'.$type_id.'">'.$type.'</option>';
    }
    ?></select>
    <select name="level" style="width: 20%;"><?php
    foreach ($sysconf['authority_level'] as $level_id => $level) {
        echo '<option value="'.$level_id.'">'.$level.'</option>';
    }
    ?></select>
</div>
<div style="margin-top: 5px;">
<select name="editorID" id="editorID" size="5" style="width: 100%;"><option value="0"><?php echo gettext('Type to search for existing editor or to add a new one'); ?></option></select>
<?php if ($biblioID) { echo '<input type="hidden" name="biblioID" value="'.$biblioID.'" />'; } ?>
<input type="submit" name="save" value="<?php echo gettext('Insert To Bibliography'); ?>" style="margin-top: 5px;" />
</div>
</form>
</div>

<?php
/* main content end */
$content = ob_get_clean();
// include the page template
require SENAYAN_BASE_DIR.'/admin/admin_template/notemplate_page_tpl.php';
?>
