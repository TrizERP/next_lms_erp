<?php

require '../../../../sysconfig.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
// privileges checking
$can_read = utility::havePrivilege('reporting', 'r');
$can_write = utility::havePrivilege('reporting', 'w');



require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_element.inc.php';

$page_title = 'Library Visitor Report';
$reportView = false;
if (isset($_GET['reportView'])) 
{
  $reportView = true;
}

 /*$material_q = $dbs->query('SELECT material_resource_id, material_resource_name FROM mst_material_resource_type where active_inactive="1"');
	//$gmd_options='';
       $material_options = array('--Material Resource Type--');
        while ($material_d = $material_q->fetch_row()) 	
	{
            $material_options[] = array($material_d[0], $material_d[1]);		
        }
$ajax_exp1 = "ajaxFillSelectnew('".SENAYAN_WEB_ROOT_DIR."admin/modules/reporting/customs/catalog_static_report.php','materialnewid1', $('materialsubid').getValue())"; 
        
$ajax = "ajaxFillSelect('".SENAYAN_WEB_ROOT_DIR."admin/AJAX_material_sub_type_handler.php', 'mst_gmd', 'gmd_id:gmd_name:material_resource_id', 'gmdID', $('materialresourceid').getValue())";

       if ($rec_d['gmd_name'])
       {
            $mst_options[] = array($rec_d['gmd_id'],$rec_d['gmd_name']);
       }
	$mst_options[] = array('0', __('--Material Type--'));


 $ajax_exp = "ajaxFillSelect('".SENAYAN_WEB_ROOT_DIR."admin/AJAX_material_sub_type_handler.php', 'mst_material_sub_type', 'material_sub_id:material_sub_name:gmd_id', 'materialsubid', $('gmdID').getValue())";

       if ($rec_d['material_sub_name']) 
       {
            $mst_material_sub_type_options[] = array($rec_d['material_sub_id'],$rec_d['material_sub_name']);
       }
	$mst_material_sub_type_options[] = array('0', __('--Material Sub Type--'));
    
$str_input = simbio_form_element::selectList('materialresourceid',$material_options, $rec_d['material_resource_id'],'onchange="'.$ajax.'"');
$str_input .= simbio_form_element::selectList('gmdID', $mst_options, $rec_d['gmd_id'],'onchange="'.$ajax_exp.'"');
$str_input .= '&nbsp;';

$str_input .= simbio_form_element::selectList('materialsubid', $lang_options,'' , 'onchange="'.$ajax_exp1.'"');
$str_input .= simbio_form_element::selectList('materialsubid', $lang_options,'' , 'onchange="generatetable_report(this.value)"');
$str_input .= '<br><tr><td id="materialnewid1" class="alterCell2" colspan="3"></td></tr>';
$form->addAnything(__('Material Type'), $str_input);   
echo $form->printOut();*/



if ($_REQUEST['id']=='1')
    {


    //ob_start();
    
    
                $row_class = 'alterCellPrinted';
                $output = '<table align="center" class="border" style="width: 100%;" cellpadding="3" cellspacing="0">';
                $output .= '<tr>';
                $output .= '<td class="dataListHeaderPrinted">'.__('Resource Type').'</td>';    
                $output .= '<td class="dataListHeaderPrinted">Total Titles</td>';
                $output .= '<td class="dataListHeaderPrinted">Total Items</td>';
                $output .= '<td class="dataListHeaderPrinted">Total issued Item</td>';
                $output .= '<td class="dataListHeaderPrinted">Total Available Item</td>';
                $output .= '<td class="dataListHeaderPrinted">Not Available for Loan</td>';
                $output .= '</tr>';
    
 
	
                $material_q = $dbs->query('select m.material_resource_name,count(*) as ttltitle,(select count(*) as ttlitems from item) as ttleitems,
                (select count(*) from loan where loan_date is not null and return_date is null) as ttlissueditem,
                (select count(*) - (select count(*) as issued_copy from loan where  loan_date is not null and return_date is null ) - (select count(*) from item where item_status_id IN (select item_status_id from mst_item_status)) from item) as ttlavailbleitem,
                (select count(*) from item where item_status_id IN (select item_status_id from mst_item_status)) as notavailableforloan 
                from  biblio as b
                left join mst_material_resource_type as m on b.material_resource_id=m.material_resource_id where m.material_resource_id=14
                union

                select m.material_resource_name,
                count(*) as ttltitle,
                (select count(*) as ttlitems from item 
                    left join biblio on item.biblio_id=biblio.biblio_id where material_resource_id=5) as ttleitems,
                (select count(*) from loan as l
                left join item on l.item_code=item.item_code
                left join biblio on item.biblio_id=biblio.biblio_id 
                where  l.loan_date is not null and l.return_date is null and biblio.material_resource_id=5) as ttlissueditem,
                (select count(*) - 
                    (ttlissueditem) - 
                    (select count(*) from item left join biblio on item.biblio_id=biblio.biblio_id where material_resource_id=5 and item_status_id IN (select item_status_id from mst_item_status)) from item left join biblio on item.biblio_id=biblio.biblio_id where material_resource_id=5) as ttlavailbleitem,
                (select count(*) from item left join biblio on item.biblio_id=biblio.biblio_id where material_resource_id=5 and  item_status_id IN (select item_status_id from mst_item_status)) as notavailableforloan 
                from  biblio as b
                left join mst_material_resource_type as m on b.material_resource_id=m.material_resource_id where m.material_resource_id=5'
                );          
                
                $total_title=0;
                $total_item=0;
                $Total_issued_Item=0;
                $Total_Available_Item=0;
                $notavailableforloan=0;

                while ($material_d = $material_q->fetch_row()) 	
                {   
                    $total_title=$total_title+$material_d[1];
                    $total_item=$total_item+$material_d[2];
                    $Total_issued_Item=$Total_issued_Item+$material_d[3];
                    $Total_Available_Item=$Total_Available_Item+$material_d[4];
                    $notavailableforloan=$notavailableforloan+$material_d[5];
                    $output .= '<tr>';
                    $output .= "<td class='dataListHeaderPrinted'>". $material_d[0]."</td>";
                    $output .= "<td class='dataListHeaderPrinted'>". $material_d[1]."</td>";
                    $output .= "<td class='dataListHeaderPrinted'>". $material_d[2]."</td>";
                    $output .= "<td class='dataListHeaderPrinted'>". $material_d[3]."</td>";
                    $output .= "<td class='dataListHeaderPrinted'>". $material_d[4]."</td>";
                    $output .= "<td class='dataListHeaderPrinted'>". $material_d[5]."</td>";
                    $output .= '</tr>';
                }
    
    
    
                $output .= '<tr>';
                $output .= '<td class="dataListHeaderPrinted">'.__('Total').'</td>';
                $output .= '<td class="dataListHeaderPrinted">'.__($total_title).'</td>';
                $output .= '<td class="dataListHeaderPrinted">'.__($total_item).'</td>';
                $output .= '<td class="dataListHeaderPrinted">'.__($Total_issued_Item).'</td>';
                $output .= '<td class="dataListHeaderPrinted">'.__($Total_Available_Item).'</td>';
                $output .= '<td class="dataListHeaderPrinted">'.__($notavailableforloan).'</td>';        
                $output .= '</tr>';

                $output .= '</table>';
    
                echo '<div class="printPageInfo">Catalog Static Report <strong>'.$selected_year.'</strong> <a class="printReport" onclick="window.print()" href="#">['.__('Print Current Page').']</a></div>'."\n";
                echo $output;

                //$content = ob_get_clean();
    // include the page template
    //require SENAYAN_BASE_DIR.'/admin/'.$sysconf['admin_template']['dir'].'/printed_page_tpl.php';
}
elseif($_REQUEST['id']=='2')
{
     $row_class = 'alterCellPrinted';
                $output = '<table align="center" class="border" style="width: 100%;" cellpadding="3" cellspacing="0">';
                $output .= '<tr>';
                $output .= '<td class="dataListHeaderPrinted">'.__('Material Type').'</td>';    
                $output .= '<td class="dataListHeaderPrinted">Total Titles</td>';
                $output .= '<td class="dataListHeaderPrinted">Total Items</td>';
                $output .= '<td class="dataListHeaderPrinted">Total issued Item</td>';
                $output .= '<td class="dataListHeaderPrinted">Total Available Item</td>';
                $output .= '<td class="dataListHeaderPrinted">Not Available for Loan</td>';
                $output .= '</tr>';
 
	
                $material_q = $dbs->query('select m.gmd_name,count(*) as ttltitle,(select count(*) as ttlitems from item) as ttleitems,
(select count(*) from loan where loan_date is not null and return_date is null) as ttlissueditem,
(select count(*) - (select count(*) as issued_copy from loan where  loan_date is not null and return_date is null ) - (select count(*) from item where item_status_id IN (select item_status_id from mst_item_status)) from item) as ttlavailbleitem,
(select count(*) from item where item_status_id IN (select item_status_id from mst_item_status)) as notavailableforloan 
from  biblio as b
left join mst_gmd as m on b.gmd_id=m.gmd_id where b.material_resource_id=14 
union

select m.gmd_name,
count(*) as ttltitle,
    (select count(*) as ttlitems from item 
             left join biblio on item.biblio_id=biblio.biblio_id where gmd_id=18) as ttleitems,
                (select count(*) from loan as l
                left join item on l.item_code=item.item_code
                left join biblio on item.biblio_id=biblio.biblio_id 
                where  l.loan_date is not null and l.return_date is null and biblio.gmd_id=18) as ttlissueditem,
                (select count(*) - 
                    (ttlissueditem) - 
                    (select count(*) from item left join biblio on item.biblio_id=biblio.biblio_id where gmd_id=18 and item_status_id IN (select item_status_id from mst_item_status)) from item left join biblio on item.biblio_id=biblio.biblio_id where gmd_id=18) as ttlavailbleitem,
                (select count(*) from item left join biblio on item.biblio_id=biblio.biblio_id where gmd_id=18 and  item_status_id IN (select item_status_id from mst_item_status)) as notavailableforloan 
                from  biblio as b
                left join mst_gmd as m on b.gmd_id=m.gmd_id where m.gmd_id=18
union
select m.gmd_name,
count(*) as ttltitle,
    (select count(*) as ttlitems from item 
             left join biblio on item.biblio_id=biblio.biblio_id where gmd_id=14) as ttleitems,
                (select count(*) from loan as l
                left join item on l.item_code=item.item_code
                left join biblio on item.biblio_id=biblio.biblio_id 
                where  l.loan_date is not null and l.return_date is null and biblio.gmd_id=14) as ttlissueditem,
                (select count(*) - 
                    (ttlissueditem) - 
                    (select count(*) from item left join biblio on item.biblio_id=biblio.biblio_id where gmd_id=14 and item_status_id IN (select item_status_id from mst_item_status)) from item left join biblio on item.biblio_id=biblio.biblio_id where gmd_id=14) as ttlavailbleitem,
                (select count(*) from item left join biblio on item.biblio_id=biblio.biblio_id where gmd_id=14 and  item_status_id IN (select item_status_id from mst_item_status)) as notavailableforloan 
                from  biblio as b
                left join mst_gmd as m on b.gmd_id=m.gmd_id where m.gmd_id=14
union
select m.gmd_name,
count(*) as ttltitle,
    (select count(*) as ttlitems from item 
             left join biblio on item.biblio_id=biblio.biblio_id where gmd_id=16) as ttleitems,
                (select count(*) from loan as l
                left join item on l.item_code=item.item_code
                left join biblio on item.biblio_id=biblio.biblio_id 
                where  l.loan_date is not null and l.return_date is null and biblio.gmd_id=16) as ttlissueditem,
                (select count(*) - 
                    (ttlissueditem) - 
                    (select count(*) from item left join biblio on item.biblio_id=biblio.biblio_id where gmd_id=16 and item_status_id IN (select item_status_id from mst_item_status)) from item left join biblio on item.biblio_id=biblio.biblio_id where gmd_id=16) as ttlavailbleitem,
                (select count(*) from item left join biblio on item.biblio_id=biblio.biblio_id where gmd_id=16 and  item_status_id IN (select item_status_id from mst_item_status)) as notavailableforloan 
                from  biblio as b
                left join mst_gmd as m on b.gmd_id=m.gmd_id where m.gmd_id=16
union

select m.gmd_name,
count(*) as ttltitle,
    (select count(*) as ttlitems from item 
             left join biblio on item.biblio_id=biblio.biblio_id where gmd_id=13) as ttleitems,
                (select count(*) from loan as l
                left join item on l.item_code=item.item_code
                left join biblio on item.biblio_id=biblio.biblio_id 
                where  l.loan_date is not null and l.return_date is null and biblio.gmd_id=13) as ttlissueditem,
                (select count(*) - 
                    (ttlissueditem) - 
                    (select count(*) from item left join biblio on item.biblio_id=biblio.biblio_id where gmd_id=13 and item_status_id IN (select item_status_id from mst_item_status)) from item left join biblio on item.biblio_id=biblio.biblio_id where gmd_id=13) as ttlavailbleitem,
                (select count(*) from item left join biblio on item.biblio_id=biblio.biblio_id where gmd_id=13 and  item_status_id IN (select item_status_id from mst_item_status)) as notavailableforloan 
                from  biblio as b
                left join mst_gmd as m on b.gmd_id=m.gmd_id where m.gmd_id=13


union
select m.gmd_name,
count(*) as ttltitle,
    (select count(*) as ttlitems from item 
             left join biblio on item.biblio_id=biblio.biblio_id where gmd_id=15) as ttleitems,
                (select count(*) from loan as l
                left join item on l.item_code=item.item_code
                left join biblio on item.biblio_id=biblio.biblio_id 
                where  l.loan_date is not null and l.return_date is null and biblio.gmd_id=15) as ttlissueditem,
                (select count(*) - 
                    (ttlissueditem) - 
                    (select count(*) from item left join biblio on item.biblio_id=biblio.biblio_id where gmd_id=15 and item_status_id IN (select item_status_id from mst_item_status)) from item left join biblio on item.biblio_id=biblio.biblio_id where gmd_id=15) as ttlavailbleitem,
                (select count(*) from item left join biblio on item.biblio_id=biblio.biblio_id where gmd_id=15 and  item_status_id IN (select item_status_id from mst_item_status)) as notavailableforloan 
                from  biblio as b
                left join mst_gmd as m on b.gmd_id=m.gmd_id where m.gmd_id=15');          
                
               $total_title=0;
                $total_item=0;
                $Total_issued_Item=0;
                $Total_Available_Item=0;
                $notavailableforloan=0;

                while ($material_d = $material_q->fetch_row()) 	
                {   
                    $total_title=$total_title+$material_d[1];
                    $total_item=$total_item+$material_d[2];
                    $Total_issued_Item=$Total_issued_Item+$material_d[3];
                    $Total_Available_Item=$Total_Available_Item+$material_d[4];
                    $notavailableforloan=$notavailableforloan+$material_d[5];
                    $output .= '<tr>';
                    $output .= "<td class='dataListHeaderPrinted'>". $material_d[0]."</td>";
                    $output .= "<td class='dataListHeaderPrinted'>". $material_d[1]."</td>";
                    $output .= "<td class='dataListHeaderPrinted'>". $material_d[2]."</td>";
                    $output .= "<td class='dataListHeaderPrinted'>". $material_d[3]."</td>";
                    $output .= "<td class='dataListHeaderPrinted'>". $material_d[4]."</td>";
                    $output .= "<td class='dataListHeaderPrinted'>". $material_d[5]."</td>";
                    $output .= '</tr>';
                }
    
    
    
                $output .= '<tr>';
                $output .= '<td class="dataListHeaderPrinted">'.__('Total').'</td>';
                $output .= '<td class="dataListHeaderPrinted">'.__($total_title).'</td>';
                $output .= '<td class="dataListHeaderPrinted">'.__($total_item).'</td>';
                $output .= '<td class="dataListHeaderPrinted">'.__($Total_issued_Item).'</td>';
                $output .= '<td class="dataListHeaderPrinted">'.__($Total_Available_Item).'</td>';
                $output .= '<td class="dataListHeaderPrinted">'.__($notavailableforloan).'</td>';        
                $output .= '</tr>';

                $output .= '</table>';
    
                echo '<div class="printPageInfo">Catalog Static Report <strong>'.$selected_year.'</strong> <a class="printReport" onclick="window.print()" href="#">['.__('Print Current Page').']</a></div>'."\n";
                echo $output;
}
elseif($_REQUEST['id']=='3')
{
    
    $row_class = 'alterCellPrinted';
                $output = '<table align="center" class="border" style="width: 100%;" cellpadding="3" cellspacing="0">';
                $output .= '<tr>';
                $output .= '<td class="dataListHeaderPrinted">'.__('Materrial Type').'</td>';    
                //$output .= '<td class="dataListHeaderPrinted">Materrial Type</td>';
                $output .= '<td class="dataListHeaderPrinted">Item</td>';                
                $output .= '</tr>';
    
 
	
                $material_q = $dbs->query('select m.material_sub_name,count(*) from biblio b
                                           left join mst_material_sub_type  m on b.material_sub_id=m.material_sub_id
                                           group by b.material_sub_id');          
                
                $total_title=0;
                $total_item=0;
                $Total_issued_Item=0;
                $Total_Available_Item=0;
                $notavailableforloan=0;

                while ($material_d = $material_q->fetch_row()) 	
                {   
                    $total_title=$total_title+$material_d[1];
                   /* $total_item=$total_item+$material_d[2];
                    $Total_issued_Item=$Total_issued_Item+$material_d[3];
                    $Total_Available_Item=$Total_Available_Item+$material_d[4];
                    $notavailableforloan=$notavailableforloan+$material_d[5];*/
                    $output .= '<tr>';
                    $output .= "<td class='dataListHeaderPrinted'>". $material_d[0]."</td>";
                    $output .= "<td class='dataListHeaderPrinted'>". $material_d[1]."</td>";
                   /* $output .= "<td class='dataListHeaderPrinted'>". $material_d[2]."</td>";
                    $output .= "<td class='dataListHeaderPrinted'>". $material_d[3]."</td>";
                    $output .= "<td class='dataListHeaderPrinted'>". $material_d[4]."</td>";
                    $output .= "<td class='dataListHeaderPrinted'>". $material_d[5]."</td>";*/
                    $output .= '</tr>';
                }
    
    
    
                $output .= '<tr>';
                $output .= '<td class="dataListHeaderPrinted">'.__('Total').'</td>';
                $output .= '<td class="dataListHeaderPrinted">'.__($total_title).'</td>';
               /* $output .= '<td class="dataListHeaderPrinted">'.__($total_item).'</td>';
                $output .= '<td class="dataListHeaderPrinted">'.__($Total_issued_Item).'</td>';
                $output .= '<td class="dataListHeaderPrinted">'.__($Total_Available_Item).'</td>';
                $output .= '<td class="dataListHeaderPrinted">'.__($notavailableforloan).'</td>';  */      
                $output .= '</tr>';

                $output .= '</table>';
    
                echo '<div class="printPageInfo">Catalog Static Report <strong>'.$selected_year.'</strong> <a class="printReport" onclick="window.print()" href="#">['.__('Print Current Page').']</a></div>'."\n";
                echo $output;   
    
}

?>
