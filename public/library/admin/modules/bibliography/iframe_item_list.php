<?php
require '../../../sysconfig.inc.php';
// start the session
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/simbio_dbop.inc.php';

// page title
$page_title = 'Item List';
// get id from url
$biblioID = 0;
if (isset($_GET['biblioID']) AND !empty($_GET['biblioID'])) {
    $biblioID = (integer)$_GET['biblioID'];
}

// start the output buffer
ob_start();
?>
<script type="text/javascript">
function confirmProcess(int_biblio_id, int_item_id)
{
    
    var confirmBox = confirm('Are you sure to remove selected item?' + "\n" + 'Once deleted, it can\'t be restored!');
    if (confirmBox) 
    {
        // set hidden element value
        document.hiddenActionForm.bid.value = int_biblio_id;
        document.hiddenActionForm.remove.value = int_item_id;
        // submit form
        document.hiddenActionForm.submit();
    }
}
function confirmedit(biblio_id,item_id)
{  
    

    var confirmBox = confirm('Are you sure Do You want to Edit this item?');
    if (confirmBox) 
    {        
        document.hiddenActionForm.bid.value =  biblio_id;
        document.hiddenActionForm.edit.value = item_id;        
        document.hiddenActionForm.submit();
    }
}
</script>
<?php


if (isset($_POST['edit']) && $_POST['edit']!=0) 
{
    
    
    
    $id = (integer)$_POST['edit'];
    $bid = (integer)$_POST['bid'];
    
    
    
    $sql_op = new simbio_dbop($dbs);    

    $loan_q = $dbs->query('SELECT DISTINCT l.item_code, b.title FROM loan AS l
        LEFT JOIN item AS i ON l.item_code=i.item_code
        LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
        WHERE i.item_id='.$id.' AND l.loan_date is not null AND l.return_date is null');
    $loan_d = $loan_q->fetch_row();   
    if ($loan_q->num_rows > 0) 
    {
        echo '<script type="text/javascript">';
        echo 'alert(\''.gettext('Item data can not be edited because still on hold by members').'\');';  
        echo 'self.location.href = \'iframe_item_list.php?biblioID='.$bid.'\';';
        echo '</script>';
    }
    else
    {

    ?>

        <script type="text/javascript">
        
        top.openHTMLpop('<?php echo  MODULES_WEB_ROOT_DIR;?>bibliography/pop_item.php?inPopUp=true&action=detail&biblioID=<?php echo $bid; ?>&itemID=<?php echo $id; ?>', 650, 400, 'Items/Copies');                                
    <?php    
        
        echo 'self.location.href = \'iframe_item_list.php?biblioID='.$bid.'\';';
        echo "</script>";

    //      $edit_link = '<a href="#" onclick="top.openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_item.php?inPopUp=true&action=detail&biblioID='.$biblioID.'&itemID='.$item_d['item_id'].'\', 650, 400, \''.gettext('Items/Copies').'\')" >'
      //      style="text-decoration: underline;">Edit</a>';

    }
}
elseif (isset($_POST['remove'])) 
{
    
    $id = (integer)$_POST['remove'];
    $bid = (integer)$_POST['bid'];
    $sql_op = new simbio_dbop($dbs);
    // check if the item still on loan
    $loan_q = $dbs->query('SELECT DISTINCT l.item_code, b.title FROM loan AS l
        LEFT JOIN item AS i ON l.item_code=i.item_code
        LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
        WHERE i.item_id='.$id.' AND l.loan_date is not null AND l.return_date is null');
    $loan_d = $loan_q->fetch_row();
    // send an alert if the member cant be deleted
    if ($loan_q->num_rows > 0) 
    {
        echo '<script type="text/javascript">';
        echo 'alert(\''.gettext('Item data can not be deleted because still on hold by members').'\');';
        echo 'self.location.href = \'iframe_item_list.php?biblioID='.$bid.'\';';
        echo '</script>';
    }
    else
    {
        if ($sql_op->delete('item', 'item_id='.$id)) 
        {
            echo '<script type="text/javascript">';
            echo 'alert(\''.gettext('Item succesfully removed!').'\');';
            echo 'self.location.href = \'iframe_item_list.php?biblioID='.$bid.'\';';
            echo '</script>';
        } 
        else
        {
            echo '<script type="text/javascript">';
            echo 'alert(\''.gettext('Item Cant be Removed Due to its on hold!').'\');';
            echo 'self.location.href = \'iframe_item_list.php?biblioID='.$bid.'\';';
            echo '</script>';
        }
    }
}

// if biblio ID is set
if ($biblioID) 
 {


    $table = new simbio_table();
    $table->table_attr = 'align="center" class="detailTable" style="width: 100%;" cellpadding="2" cellspacing="0"';

    /*echo 'SELECT i.item_id, i.item_code, b.title, i.site, loc.location_name, ct.coll_type_name, st.item_status_name FROM item AS i
        LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
        LEFT JOIN mst_location AS loc ON i.location_id=loc.location_id
        LEFT JOIN mst_coll_type AS ct ON i.coll_type_id=ct.coll_type_id
        LEFT JOIN mst_item_status AS st ON i.item_status_id=st.item_status_id
        WHERE i.biblio_id='.$biblioID.'';die;*/
            
   
// database list
    $item_q = $dbs->query('SELECT i.item_id, i.item_code, b.title, i.site, loc.location_name, ct.coll_type_name, st.item_status_name FROM item AS i
        LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
        LEFT JOIN mst_location AS loc ON i.location_id=loc.location_id
        LEFT JOIN mst_coll_type AS ct ON i.coll_type_id=ct.coll_type_id
        LEFT JOIN mst_item_status AS st ON i.item_status_id=st.item_status_id
        WHERE i.biblio_id='.$biblioID);

    /*echo 'SELECT i.item_id, i.item_code, b.title, i.site, loc.location_name, ct.coll_type_name, st.item_status_name FROM item AS i
        LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
        LEFT JOIN mst_location AS loc ON i.location_id=loc.location_id
        LEFT JOIN mst_coll_type AS ct ON i.coll_type_id=ct.coll_type_id
        LEFT JOIN mst_item_status AS st ON i.item_status_id=st.item_status_id
        WHERE i.biblio_id='.$biblioID;*/
    $row = 1;
    
    while ($item_d = $item_q->fetch_assoc()) 
    {
        // alternate the row color
        $row_class = ($row%2 == 0)?'alterCell':'alterCell2';
//added started by Parth 2/9/2011
$qry = $dbs->query('select biblio_id from biblio where biblio_id = '.$biblioID);
$result = $qry->fetch_assoc();
$b_id = $result['biblio_id'];
//added ended by Parth 2/9/2011
        // links

//exit;
if($b_id!='')
{
    //utility::jsAlert($item_d['item_id']);die;
   // $edit_link = '<a href="#" onclick="top.openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_item.php?inPopUp=true&action=detail&biblioID='.$biblioID.'&itemID='.$item_d['item_id'].'\', 650, 400, \''.gettext('Items/Copies').'\')"
     //       style="text-decoration: underline;">Edit</a>';
    
    $edit_link = '<a href="#" onclick="confirmedit('.$biblioID.', '.$item_d['item_id'].')" style="text-decoration: underline;">Edit</a>';
    
        $remove_link = '<a href="#" onclick="confirmProcess('.$biblioID.', '.$item_d['item_id'].')"
            style="color: #FF0000; text-decoration: underline;">Delete</a>';

        $title = $item_d['item_code'];

        $table->appendTableRow(array($edit_link, $remove_link, $title, $item_d['location_name'], $item_d['site'], $item_d['coll_type_name'], $item_d['item_status_name']));
        $table->setCellAttr($row, null, 'valign="top" class="'.$row_class.'" style="font-weight: bold; width: auto;"');
        $table->setCellAttr($row, 0, 'valign="top" class="'.$row_class.'" style="font-weight: bold; width: 5%;"');
        $table->setCellAttr($row, 1, 'valign="top" class="'.$row_class.'" style="font-weight: bold; width: 10%;"');
        $table->setCellAttr($row, 2, 'valign="top" class="'.$row_class.'" style="font-weight: bold; width: 40%;"');
}
else
{
	$remove_link = '<a href="#" onclick="confirmProcess('.$biblioID.', '.$item_d['item_id'].')"
            style="color: #FF0000; text-decoration: underline;">Delete</a>';
	$title = $item_d['item_code'];

        $table->appendTableRow(array($remove_link, $title, $item_d['location_name'], $item_d['site'], $item_d['coll_type_name'], $item_d['item_status_name']));
        $table->setCellAttr($row, null, 'valign="top" class="'.$row_class.'" style="font-weight: bold; width: auto;"');
        $table->setCellAttr($row, 0, 'valign="top" class="'.$row_class.'" style="font-weight: bold; width: 5%;"');
        $table->setCellAttr($row, 1, 'valign="top" class="'.$row_class.'" style="font-weight: bold; width: 10%;"');
        $table->setCellAttr($row, 2, 'valign="top" class="'.$row_class.'" style="font-weight: bold; width: 40%;"');
}
        $row++;
  }
    echo $table->printTable();
    // hidden form
    
    echo '<form name="hiddenActionForm" method="post" action="'.$_SERVER['PHP_SELF'].'"><input type="hidden" name="bid" value="0" /><input type="hidden" name="remove" value="0" /><input type="hidden" name="edit" value="0" /></form>';
}
/* main content end */
$content = ob_get_clean();
// include the page template
require SENAYAN_BASE_DIR.'/admin/admin_template/notemplate_page_tpl.php';
?>
