<?php
session_start();
// main system configuration
require '../../../sysconfig.inc.php';
// start the session
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/simbio_dbop.inc.php';

// privileges checking
$can_read = utility::havePrivilege('bibliography', 'r');
$can_write = utility::havePrivilege('bibliography', 'w');
error_reporting(0);
if (!$can_read) {
    die('<div class="errorBox">'.__('You are not authorized to view this section').'</div>');
}

$in_pop_up = false;
// check if we are inside pop-up window
if (isset($_GET['inPopUp'])) 
{
    $in_pop_up = true;
}
if($_REQUEST['biblioID'])
    $_SESSION['biblioID']=$_REQUEST['biblioID'];


if (isset($_POST['saveData'])=='Save' AND $can_read AND $can_write)  // 
 {  
         
    $copies='';    
    $itemCode = trim(strip_tags($_POST['itemCode']));
    $copies=trim(strip_tags($_REQUEST['copies']));
     
    if (empty($itemCode))
    {
        utility::jsAlert(__('Item Code can\'t be empty!'));
        exit();
    }
                     
    /*if($form->edit_mode == false)
    {        
        if(empty($copies))
          {
			utility::jsAlert(__('Copies can\'t be empty!'));
		        exit();
		
          }
    }*/
 //   else
//    {                
        $title = trim($_POST['biblioTitle']);
        $data['biblio_id']=$_SESSION['biblioID'];
        //$data['biblio_id'] = $_POST['biblioID'];
         //$data['biblio_id'] = $_REQUEST['biblioID'];
         
  	$data['item_code'] = $dbs->escape_string($itemCode);
       //$data['copies'] = $dbs->escape_string($copies);
        $data['call_number'] = trim($dbs->escape_string($_POST['callNumber']));
        // check inventory code
        $inventoryCode = trim($_POST['inventoryCode']);
        if ($inventoryCode) {
            $data['inventory_code'] = $inventoryCode;
        } else {
            $data['inventory_code'] = 'literal{NULL}';
        }
        
        $data['location_id'] = $_POST['locationID'];
        $data['site'] = trim($dbs->escape_string(strip_tags($_POST['itemSite'])));
        $data['coll_type_id'] = intval($_POST['collTypeID']);
        $data['item_status_id'] = $dbs->escape_string($_POST['itemStatusID']);
        $data['source'] = $_POST['source'];
        $data['order_no'] = trim($dbs->escape_string(strip_tags($_POST['orderNo'])));
        $data['order_date'] = date('Y-m-d',strtotime($_POST['ordDate']));                           
        $data['received_date'] = date('Y-m-d',strtotime($_POST['recvDate']));                           
        
        $data['supplier_id'] = $_POST['supplierID'];
        $data['invoice'] = $_POST['invoice'];
        $data['invoice_date'] = date('Y-m-d',strtotime($_POST['invcDate'])); 
        
        $data['price_currency'] = trim($dbs->escape_string(strip_tags($_POST['priceCurrency'])));
        if (!$data['price_currency'])
        {
            $data['price_currency'] = 'literal{NULL}';
        }
        $data['price'] = preg_replace('@[.,\-a-z ]@i', '', strip_tags($_POST['price']));
        $data['input_date'] = date('Y-m-d H:i:s');        
        $data['last_update'] = date('Y-m-d H:i:s');
        $data['user_name']=$_SESSION['uname'];

        // create sql op object
        $sql_op = new simbio_dbop($dbs);                                          
        if (isset($_POST['updateRecordID'])) 
        {  
            unset($data['input_date']);
            // filter update record ID
            $updateRecordID = (integer)$_POST['updateRecordID'];
                        
            $loan_q = $dbs->query("select item_code from item where item_id=$updateRecordID");
            $check_item_code = $loan_q->fetch_assoc();
            $check_item_code1=$check_item_code['item_code']; 
                                                                                   
            $loan_q = $dbs->query("SELECT  count(*)  as total
                                    FROM loan l 
                                    left join temp_request t  on l.temp_id = t.temp_id
                                    WHERE l.item_code='$check_item_code1' and l.return_date is null
                                    union 
                                    SELECT  count(*) as total
                                     FROM temp_request t 
                                    left join loan l on l.temp_id = t.temp_id
                                    where  t.item_code='$check_item_code1' and l.return_date is null");
                                                
                                    $_still_loan='';
                                    
                                    while($loan_d = $loan_q->fetch_assoc())
                                    {                      
                                        $counter=$counter+(integer)$loan_d['total'];
                                                                                
                                    }
                                    
                                    if ($counter>0)
                                    {
                                        utility::jsAlert('Item is still on loan or it has pending request');
                                        exit();
                                    }
                                                                                                                                  
//// check if the item still on loan
                            /*$loan_q = $dbs->query('SELECT i.item_code, b.title, COUNT(l.loan_id) FROM item AS i
                                LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
                                LEFT JOIN loan AS l ON (i.item_code=l.item_code AND l.is_lent=1 AND l.is_return=0)
                                WHERE i.item_id='.$itemCode.' GROUP BY i.item_code');*/
                            
                         
                    //        utility::jsAlert(__($loan_q[2]));
                      //      exit();
                            
                            
                    /*       $query_loan='SELECT i.item_code, b.title, COUNT(l.loan_id) FROM item AS i LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id LEFT JOIN loan AS l ON (i.item_code=l.item_code AND l.is_lent=1 AND l.is_return=0) WHERE i.item_id='.$itemCode.' GROUP BY i.item_code';
                            $loan_q = $dbs->query($query_loan);
                            $loan_d = $loan_q->fetch_row();   
                           
                            // if there is no loan
                            if ($loan_d[2] < 1) 
                            {
                                if (!$sql_op->delete('item', 'item_id='.$itemID)) 
                                {
                                    $error_num++;
                                }
                                else
                                {
                                    // write log
                                    utility::writeLogs($dbs, 'staff', $_SESSION['uid'], 'bibliography', $_SESSION['realname'].' DELETE item data ('.$loan_d[0].') with title ('.$loan_d[1].')');
                                }
                            } 
                            else
                            {
                                    utility::jsAlert('d');
                                    exit();
                                $still_on_loan[] = $loan_d[0].' - '.$loan_d[1];
                                $error_num++;
                            }
                        */
                        
                       /*utility::jsAlert(__('Item data can not be Edited because still on hold by members')." : \n".$items);
                       exit();*/
          /*  if ($still_on_loan) 
             {
                
                $items = '';
                foreach ($still_on_loan as $item) 
                {
                    $items .= $item."\n";
                }
                utility::jsAlert(__('Item data can not be Edited because still on hold by members')." : \n".$items);
                echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\', \'post\');</script>';
                exit();
            }*/
            /* UPDATE RECORD MODE */
            // remove input date
           
            // update the data
            $update = $sql_op->update('item', $data, "item_id=".$updateRecordID);
            if ($update) 
            {
                // write log
                utility::writeLogs($dbs, 'tblstudent', $_SESSION['uid'], 'bibliography', $_SESSION['realname'].' update item data ('.$data['item_code'].') with title ('.$title.')');
                if ($sysconf['bibliography_item_update_notification']) 
                {
                    utility::jsAlert(__('Item Data Successfully Updated'));
		}
                if ($in_pop_up) {
                    echo '<script type="text/javascript">top.setIframeContent(\'itemIframe\', \''.MODULES_WEB_ROOT_DIR.'bibliography/iframe_item_list.php?biblioID='.$data['biblio_id'].'\');</script>';
                    echo '<script type="text/javascript">top.closeHTMLpop();</script>';
                } else {
                    echo '<script type="text/javascript">parent.setContent(\'mainContent\', parent.getPreviousAJAXurl(), \'get\');</script>';
                }
            }
            else
            {
                utility::jsAlert(__('Item Data FAILED to Save. Please Contact System Administrator')."\nDEBUG : ".$sql_op->error); 
            
            }
            exit();
        }
        else 
        {

      for($i=0;$i<$copies;$i++)
       {		         
		$data['item_code']=$data['item_code'];//str_pad($data['item_code'],13,"0",STR_PAD_LEFT);
		$insert = $sql_op->insert('item', $data);	    
		$data['item_code']++;
       }
       
          if ($insert) 
          {                     		
            utility::writeLogs($dbs, 'tblstudent', $_SESSION['uid'], 'bibliography', $_SESSION['realname'].' insert item data ('.$data['item_code'].') with title ('.$title.')');
              
                utility::jsAlert(__('New Item Data Successfully Saved'));
                if ($in_pop_up) {
                    echo '<script type="text/javascript">top.setIframeContent(\'itemIframe\', \''.MODULES_WEB_ROOT_DIR.'bibliography/iframe_item_list.php?biblioID='.$data['biblio_id'].'\');</script>';
                    echo '<script type="text/javascript">top.closeHTMLpop();</script>';
                } else {
                    echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'\', \'post\');</script>';
                }
            }


	 else {// utility::jsAlert(__('Item Data FAILED to Save. Please Contact System Administrator')."\nDEBUG : ".$sql_op->error);
		utility::jsAlert(__('Item Data FAILED to Save. Please Enter Number Of Copies')."\nDEBUG : ".$sql_op->error);	
		 }
            exit();
        }
 //   }
    exit();

} 
else if (isset($_POST['itemID']) AND !empty($_POST['itemID']) AND isset($_POST['itemAction'])) 
{
    
 
    if (!($can_read AND $can_write)) 
     {
        die();
    }
    
    $sql_op = new simbio_dbop($dbs);
    $failed_array = array();
    $error_num = 0;
    $still_on_loan = array();
    if (!is_array($_POST['itemID'])) 
    {
        // make an array
        $_POST['itemID'] = array((integer)$_POST['itemID']);
    }
    // loop array
    foreach ($_POST['itemID'] as $itemID) 
    {
        
        $itemID = (integer)$itemID;
                       
        $loan_q = $dbs->query('SELECT i.item_code, b.title, COUNT(l.loan_id) FROM item AS i
            LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
            LEFT JOIN loan AS l ON i.item_code=l.item_code
            WHERE i.item_id='.$itemID.' GROUP BY i.item_code');
        
        $loan_d = $loan_q->fetch_row();
        // if there is no loan
        if ($loan_d[2] < 1) 
        {
            if (!$sql_op->delete('item', 'item_id='.$itemID)) 
            {
                $error_num++;
            }
            else
            {
                // write log
                utility::writeLogs($dbs, 'tblstudent', $_SESSION['uid'], 'bibliography', $_SESSION['realname'].' DELETE item data ('.$loan_d[0].') with title ('.$loan_d[1].')');
            }
        } 
        else
        {
            $still_on_loan[] = $loan_d[0].' - '.$loan_d[1];
            $error_num++;
        }
    }

            if ($still_on_loan) 
             {
                $items = '';
                foreach ($still_on_loan as $item) 
                {
                    $items .= $item."\n";
                }
                utility::jsAlert(__('Item data can not be deleted because still on hold by members')." : \n".$items);
                echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\', \'post\');</script>';
                exit();
            }
    // error alerting
    if ($error_num == 0) {
        utility::jsAlert(__('Item succesfully removed!'));
        echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\', \'post\');</script>';
    } else {
        utility::jsAlert(__('Item FAILED to removed!'));
        echo '<script type="text/javascript">parent.setContent(\'mainContent\', \''.$_SERVER['PHP_SELF'].'?'.$_POST['lastQueryStr'].'\', \'post\');</script>';
    }
    exit();
}
/* RECORD OPERATION END */
if (!$in_pop_up) 
{    
?>
<table  align=center>
<tr>
	<td valign=top>
	<?php
	$bradecum = '';       
        $basedir = basename(dirname(__FILE__));
        $bradecum = "<a href=javascript:void(0); onclick=javascript:new_set_home(); >Home</a>-><a class='' href=javascript:void(0); onclick=javascript:new_set('".$basedir."');>"; 
	$query = "select module_name from mst_module where module_path = '".$basedir."'";
	$set_query = $dbs->query($query);
	while($row=$set_query->fetch_assoc())
	{
                $_formated_module_name = ucwords(str_replace('_', ' ', $row['module_name']));
		$bradecum .= $_formated_module_name;
	}
$bradecum .= '</a>->';
/*if(isset($_REQUEST['action']))
{
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'bibliography/index.php?action=detail&physical=physical class="headerText2">Add Hardcopy Book</a>';
}
else
{
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'bibliography/index.php class="headerText2">Book List</a>';
}*/
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'bibliography/item.php class="headerText2">Book List</a>';
echo $bradecum;
        ?>	
	</td>
</tr>
</table>
<table>
<tr>
	<td class="tab_menu_top">
                            <ul class="tabs"> 
				<li>
<a href="<?php echo MODULES_WEB_ROOT_DIR; ?>bibliography/item.php" class="headerText2"><?php echo __('Books List'); ?></a> </li><li>
<a href="<?php echo  MODULES_WEB_ROOT_DIR; ?>bibliography/checkout_item.php" class="headerText2"><?php echo __('Checkout books'); ?></a> </li></ul></td></tr></table>
<fieldset class="menuBox">
<div class="menuBoxInner itemIcon">
    <?php echo strtoupper(__('Books')); ?>
    <p class="only_border">&nbsp;</p>
    
    <form name="search" action="<?php echo MODULES_WEB_ROOT_DIR; ?>bibliography/item.php" id="search" method="get" style="display: inline;"><?php echo __('Search'); ?> :
  <!--commnet by iresh on 25-1-2011  <input type="text" name="keywords" id="keywords" size="30" />-->
   <!-- added by iresh on 25-1-2011 --> <input type="text" name="keywords" id="keywords" width=140px/>
    <input type="submit" id="doSearch" value="<?php echo __('Search'); ?>" class="button" />
    </form>
</div>
</fieldset>
<?php

}

if (isset($_POST['detail']) OR (isset($_GET['action']) AND $_GET['action'] == 'detail'))
{
 

    if (!($can_read AND $can_write))
    {
        die('<div class="errorBox">'.__('You are not authorized to view this section').'</div>');
    }
    
    $itemID = (integer)isset($_POST['itemID'])?$_POST['itemID']:0;
    $rec_q = $dbs->query('SELECT item.*, b.biblio_id, b.title, s.supplier_name
        FROM item
        LEFT JOIN biblio AS b ON item.biblio_id=b.biblio_id
        LEFT JOIN mst_supplier AS s ON item.supplier_id=s.supplier_id
        WHERE item_id='.$itemID);
    $rec_d = $rec_q->fetch_assoc();

    // create new instance
    $form = new simbio_form_table_AJAX('mainForm', $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'], 'post');
    $form->submit_button_attr = 'name="saveData" value="'.__('Save').'" class="button"';
    // form table attributes
    $form->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
    $form->table_header_attr = 'class="alterCell" style="font-weight: bold;"';
    $form->table_content_attr = 'class="alterCell2"';

    // edit mode flag set
    if ($rec_q->num_rows > 0)
    {
        $form->edit_mode = true;
        // record ID for delete process
        if (!$in_pop_up) 
        {
            $form->record_id = $itemID;
        }
        else
        {
            $form->addHidden('updateRecordID', $itemID);
            $form->back_button = false;
        }
        
        // form record title
        $form->record_title = $rec_d['title'];
        // submit button attribute
        $form->submit_button_attr = 'name="saveData" value="'.__('Update').'" class="button"';
        // default biblio title and biblio ID
        $b_title = $rec_d['title'];
        $b_id = $rec_d['biblio_id'];
      /*  if (trim($rec_d['call_number']) == '') 
        {
            $biblio_q = $dbs->query('SELECT call_number FROM biblio WHERE biblio_id='.$rec_d['biblio_id']);
            $biblio_d = $biblio_q->fetch_assoc();
            $rec_d['call_number'] = $biblio_d['call_number'];
        }*/
    } 
    else 
        {
        
        // get biblio title and biblio ID from database if we are not on edit mode
        $biblioID = 0;
        if (isset($_GET['biblioID'])) {
            $biblioID = (integer)$_GET['biblioID'];
        }
       /* $biblio_q = $dbs->query('SELECT biblio_id, title, call_number FROM biblio WHERE biblio_id='.$biblioID);
        $biblio_d = $biblio_q->fetch_assoc();
        $b_title = $biblio_d['title'];
        $b_id = $biblio_d['biblio_id'];
        if($b_id=='')
		{
 			$b_id = (integer)$_GET['biblioID'];
		}
        $def_call_number = $biblio_d['call_number'];*/
    }

    /* Form Element(s) */
    // title
    if (!$in_pop_up)
    {
        $str_input = $b_title;
        //$str_input .= '<div class="makeHidden"><a title="Edit Bibliographic Data" class="notAJAX" style="font-weight: bold; color: #ff9900;" href="javascript: openHTMLpop(\''.MODULES_WEB_ROOT_DIR.'bibliography/pop_biblio.php?inPopUp=true&action=detail&itemID='.$rec_d['biblio_id'].'&itemCollID='.$rec_d['item_id'].'\', 650, 500, \''.__('Edit Biblographic data').'\')">'.__('Edit Biblographic data').'</a></div>';
    } 
    else
    {
        $str_input = $b_title; 
       
    }
    if ($str_input!='')
    {
        $form->addAnything(__('Title'), $str_input);
    }
    $form->addHidden('biblioTitle', $b_title);
    $form->addHidden('biblioID', $b_id);
    
    if (!empty($rec_d['item_code']))
    {
            $items =$rec_d['item_code'];
    }
    else
    {
        $qry = $dbs->query('select max(item_code) as max_item  from item');
        $result = $qry->fetch_assoc();
        $items = $result['max_item']+1; 
    }
    
    // item code
    //$str_input = simbio_form_element::textField('text', 'itemCode', $rec_d['item_code'], 'onblur="ajaxCheckID(\''.SENAYAN_WEB_ROOT_DIR.'admin/AJAX_check_id.php\', \'item\', \'item_code\', \'msgBox\', \'itemCode\')" style="width: 40%;"');
    //$form->addTextField('text', 'biblioID', __('biblioID'), $_REQUEST['biblioID'], 'style="width: 40%;" onchange="return numericcheck(this.name);"');    
	// $str_input = simbio_form_element::textField('text', 'itemCode',$items, 'onchange="return numericcheck(this.name);"onkeyup="return checkspecialcharacterdynamic(this.name);"onblur="ajaxCheckID(\''.SENAYAN_WEB_ROOT_DIR.'admin/AJAX_check_id.php\', \'item\', \'item_code\', \'msgBox\', \'itemCode\')"style="width: 40%;"','disabled=""'  );
    
    $str_input = simbio_form_element::textField('text', 'itemCode',$items, 'onblur="ajaxCheckID(\''.SENAYAN_WEB_ROOT_DIR.'admin/AJAX_check_id.php\', \'item\', \'item_code\', \'msgBox\', \'itemCode\')"style="width: 40%;"','disabled=""'  );
    
    $str_input .= ' &nbsp; <span id="msgBox">&nbsp;</span>';

   $form->addAnything(__('Item Code'),$str_input);
   if(!$form->edit_mode)
   {
    $form->addTextField('text', 'copies', __('Copies'), $rec_d['copies'], 'style="width: 40%;" onkeyup="return checkspecialcharacterdynamic(this.name);"onchange="return numericcheck(this.name);"');
   }

    // call number
    $form->addTextField('text', 'callNumber', __('Call Number'), isset($rec_d['call_number'])?$rec_d['call_number']:$def_call_number, 'style="width: 40%;"onkeyup="return checkspecialcharacterdynamic(this.name);"onchange="return numericcheck(this.name);"');
    // inventory code
    $form->addTextField('text', 'inventoryCode', __('Inventory Code'), $rec_d['inventory_code'], 'style="width: 40%;"');
    // item location
        // get location data related to this record from database
        $location_q = $dbs->query("SELECT location_id, location_name FROM mst_location order by location_name");
        $location_options = array();
        while ($location_d = $location_q->fetch_row()) {
            $location_options[] = array($location_d[0], $location_d[1]);
        }
    $form->addSelectList('locationID', __('Location'), $location_options, $rec_d['location_id']);
    // item site
    $form->addTextField('text', 'itemSite', __('Shelf Location'), $rec_d['site'], 'style="width: 40%;"');
    // collection type
        // get collection type data related to this record from database
        $coll_type_q = $dbs->query("SELECT coll_type_id, coll_type_name FROM mst_coll_type order by coll_type_name");
        $coll_type_options = array();
      
        while ($coll_type_d = $coll_type_q->fetch_row()) {
            $coll_type_options[] = array($coll_type_d[0], $coll_type_d[1]);
        }

    $form->addSelectList('collTypeID', __('Collection Type'), $coll_type_options, $rec_d['coll_type_id']);
    // item status
        // get item status data from database
        $item_status_q = $dbs->query("SELECT item_status_id, item_status_name FROM mst_item_status order by item_status_name ");
        $item_status_options[] = array('0', __('Available'));
        while ($item_status_d = $item_status_q->fetch_row()) {
            $item_status_options[] = array($item_status_d[0], $item_status_d[1]);
        }
    $form->addSelectList('itemStatusID', __('Item Status'), $item_status_options, $rec_d['item_status_id']);
    // order number
    $form->addTextField('text', 'orderNo', __('Order Number'), $rec_d['order_no'], 'style="width: 40%;"');
    // order date
     //$form->addDateField('ordDate', __('Order Date'), $rec_d['order_date']?$rec_d['order_date']:date('d-m-Y'));
    
    //$order_date=$rec_d['order_date']?$rec_d['order_date']:date('d-m-Y');
    $order_date=date('d-m-Y');    
    $order_date=$rec_d['order_date']?date('d-m-Y',strtotime($rec_d['order_date'])):date('d-m-Y');
    $form->addDateField('ordDate', __('Order Date'),$order_date);
    
    //$form->addDateField('ordDate', __('Order Date'),$order_date=$rec_d['order_date']?$rec_d['order_date']:date('d-m-Y'));
    
//    echo '<input value="24/01/2012" class="inputDate"/>';
    // received date
    $recieve_date=date('d-m-Y');    
    $recieve_date=$rec_d['received_date']?date('d-m-Y',  strtotime($rec_d['received_date'])):$recieve_date;
    $form->addDateField('recvDate', __('Receiving Date'),$recieve_date);
    // item supplier
        // get item status data from database
        $supplier_q = $dbs->query("SELECT supplier_id, supplier_name FROM mst_supplier");
        $supplier_options[] = array('0', __('Not Applicable'));
        while ($supplier_d = $supplier_q->fetch_row()) {
            $supplier_options[] = array($supplier_d[0], $supplier_d[1]);
        }
    $form->addSelectList('supplierID', __('Supplier'), $supplier_options, $rec_d['supplier_id']);
    // item source
        $source_options[] = array('1', __('Buy'));
        $source_options[] = array('2', __('Prize/Grant'));
    $form->addRadio('source', __('Source'), $source_options, !empty($rec_d['source'])?$rec_d['source']:'1');
    // item invoice
    $form->addTextField('text', 'invoice', __('Invoice'), $rec_d['invoice'], 'style="width: 40%;"');
    // invoice date
    $invoice_date=date('d-m-Y'); 
    $invoice_date=$rec_d['invoice_date']?date('d-m-Y',strtotime($rec_d['invoice_date'])):$invoice_date;
    
    $form->addDateField('invcDate', __('Invoice Date'),$invoice_date);
    // price


    $str_input = simbio_form_element::textField('text', 'price', !empty($rec_d['price'])?$rec_d['price']:'0', 'style="width: 40%;" onchange="return numericcheck(this.name);"');
    $str_input .= simbio_form_element::selectList('priceCurrency', $sysconf['currencies'], $rec_d['price_currency']);;
    $form->addAnything(__('Price'), $str_input);

    // edit mode messagge
    if ($form->edit_mode) {
        echo '<div class="infoBox">'.__('You are going to edit data').': <b>'.$rec_d['title'].'</b> ' //mfc
            .'<br />'.__('Last Updated').' '.$rec_d['last_update'];
        echo '</div>'."\n";
    }
    // print out the form object
    echo $form->printOut();
} 
else 
    {
    
    require SIMBIO_BASE_DIR.'simbio_UTILS/simbio_tokenizecql.inc.php';
    require LIB_DIR.'biblio_list.inc.php';
    $title_field_idx = 1;
    // callback function to show title and authors in datagrid
    function showTitleAuthors($obj_db, $array_data)
    {
        global $title_field_idx;
        // biblio author detail
        $_biblio_q = $obj_db->query('SELECT b.title, a.author_name FROM biblio AS b
            LEFT JOIN biblio_author AS ba ON b.biblio_id=ba.biblio_id
            LEFT JOIN mst_author AS a ON ba.author_id=a.author_id
            WHERE b.biblio_id='.$array_data[$title_field_idx]);
        echo $obj_db->error;
        $_authors = '';
	 $_title='';
        while ($_biblio_d = $_biblio_q->fetch_row()) {
            $_title = $_biblio_d[0];
            $_authors .= $_biblio_d[1].' - ';
        }
        $_authors = substr_replace($_authors, '', -3);
        $_output = '<div style="float: left;"><b>'.$_title.'</b><br /><i>'.$_authors.'</i></div>';
        return $_output;
    }

    /* ITEM LIST */
    // table spec
    $table_spec = 'item
        LEFT JOIN biblio ON item.biblio_id=biblio.biblio_id
        LEFT JOIN mst_location AS loc ON item.location_id=loc.location_id
        LEFT JOIN mst_coll_type AS ct ON item.coll_type_id=ct.coll_type_id';

    // create datagrid
    $datagrid = new simbio_datagrid();
    if ($can_write) {
        $datagrid->setSQLColumn('item.item_id',
            'item.item_code AS \''.__('Item Code').'\'',
            'item.biblio_id AS \''.__('Title').'\'',
            'ct.coll_type_name AS \''.__('Collection Type').'\'',
            'loc.location_name AS \''.__('Location').'\'',
            'biblio.classification AS \''.__('Classification').'\'',
            'item.last_update AS \''.__('Last Updated').'\'');
        $datagrid->modifyColumnContent(2, 'callback{showTitleAuthors}');
        $title_field_idx = 2;
    } else {
        $datagrid->setSQLColumn('item.item_code AS \''.__('Item Code').'\'',
            'item.biblio_id AS \''.__('Title').'\'',
            'ct.coll_type_name AS \''.__('Collection Type').'\'',
            'loc.location_name AS \''.__('Location').'\'',
            'biblio.classification AS \''.__('Classification').'\'',
            'item.last_update AS \''.__('Last Updated').'\'');
        $datagrid->modifyColumnContent(1, 'callback{showTitleAuthors}');
    }
    $datagrid->setSQLorder('CAST(item.item_code AS SIGNED)');//item.last_update DESC

    // is there any search
    if (isset($_GET['keywords']) AND $_GET['keywords'])
    {
        
        $keywords = $dbs->escape_string(trim($_GET['keywords']));
        $searchable_fields = array('title', 'author', 'subject', 'itemcode');
        $search_str = '';
        // if no qualifier in fields
        if (!preg_match('@[a-z]+\s*=\s*@i', $keywords)) 
        {
            
            foreach ($searchable_fields as $search_field) 
            {
                $search_str .= $search_field.'='.$keywords.' OR ';
                 //$search_str .= $search_field.' Like '."%$keywords%".' OR ';
            }
        } 
        else
        {
            
            $search_str = $keywords;
        }
       
        $biblio_list = new biblio_list($dbs);
        
        $criteria = $biblio_list->setSQLcriteria($search_str);
        
    }
    if (isset($criteria)) 
    {
        
        $datagrid->setSQLcriteria('('.$criteria['sql_criteria'].')');
    }

    // set table and table header attributes
    $datagrid->table_attr = 'align="center" id="dataList" cellpadding="5" cellspacing="0"';
    $datagrid->table_header_attr = 'class="dataListHeader" style="font-weight: bold;"';
    // set delete proccess URL
    $datagrid->chbox_form_URL = $_SERVER['PHP_SELF'];

    // put the result into variables
    $datagrid_result = $datagrid->createDataGrid($dbs, $table_spec, 20, ($can_read AND $can_write));
    if (isset($_GET['keywords']) AND $_GET['keywords']) 
    {
        $msg = str_replace('{result->num_rows}', $datagrid->num_rows, __('Found <strong>{result->num_rows}</strong> from your keywords')); //mfc
        echo '<div class="infoBox">'.$msg.' : '.$_GET['keywords'].'<div>'.__('Query took').' <b>'.$datagrid->query_time.'</b> '.__('second(s) to complete').'</div></div>'; //mfc
    }

    echo $datagrid_result;
}

?>
