<?php
error_reporting(0);
session_start();
if (!defined('DIRECT_INCLUDE')) 
{    
    require '../../../sysconfig.inc.php';    
}
require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/simbio_dbop.inc.php';
require SIMBIO_BASE_DIR.'simbio_UTILS/simbio_date.inc.php';
require MODULES_BASE_DIR.'membership/member_base_lib.inc.php';
require MODULES_BASE_DIR.'circulation/circulation_base_lib.inc.php';
?>

<script type="text/javascript">
var show_confirm=function()
{
    var r=confirm("Member will Expire before Due Date, Are You Sure to Issue?");    
    
    if (r==true)
    {
          
          ////<?php $_SESSION['confrm']='1'; ?>
            //return true;
     }
     else
     {          
          
          ////<?php $_SESSION['confrm']='2'; ?>
            //return false;
            
     }
}
</script>
<?php
if(isset($_REQUEST['memberID']))
{
    $_SESSION['memberID1']=$_REQUEST['memberID'];
}
if (isset($_POST['finish'])) 
{
     
    // create circulation object
    $memberID = $_SESSION['memberID1'];

    $circulation = new circulation($dbs, $memberID);
    // finish loan transaction


    $flush = $circulation->finishLoanSession();
    if ($flush == TRANS_FLUSH_ERROR) {
        // write log
        utility::writeLogs($dbs, 'tblstudent', $memberID, 'circulation', 'ERROR : '.$_SESSION['realname'].' FAILED finish circulation transaction with member ('.$memberID.')');
        echo '<script type="text/javascript">';
        echo 'alert(\''.gettext('ERROR! Issue data can\'t be saved to database').'\');';
        echo '</script>';
    } else {

        // write log
        utility::writeLogs($dbs, 'tblstudent', $memberID, 'circulation', $_SESSION['realname'].' finish circulation transaction with member ('.$memberID.')');
        // send message
        echo '<script type="text/javascript">';
        if ($sysconf['transaction_finished_notification']) {
            echo 'alert(\''.gettext('Transaction finished').'\');';
	    
        }
	
        // print receipt only if enabled and $_SESSION['receipt_record'] not empty
        if ($sysconf['circulation_receipt'] && $_SESSION['receipt_record'] && (count($_SESSION['receipt_record']['loan'])>0)) {
	     //  print_r($_SESSION['receipt_record']);
            // open receipt windows
           echo 'parent.openWin(\''.MODULES_WEB_ROOT_DIR.'circulation/pop_loan_receipt.php\', \'popReceipt\', 350, 500, true);';
        }


        echo 'parent.setContent(\'mainContent\', \''.MODULES_WEB_ROOT_DIR.'circulation/index.php\', \'post\', \'finishID='.$memberID.'\');';
        echo '</script>';
    }

    exit();
}


// return and extend process
if (isset($_POST['process']) AND isset($_POST['loanID']))
{

$loanID = $_POST['loanID'];
if((isset($_POST['submit'])=='Return Selected Data'))
{
    
    if (empty($_POST['loanID']))
    {       
       echo '<script type="text/javascript">';       
       echo "\n".'alert(\''.gettext('Please Select item code!').'\');'."\n";		    
       echo 'location.href = \'loan_list.php\';';      
       echo '</script>';
     exit;  
    }
    
    $_SESSION['retuen_date']='return';
    
    $countcheck=count($_POST['loanID']);
    
    for($i=0;$i<$countcheck;$i++)
    {
	$deleteid=$_POST['loanID'][$i];
	
	
    // get loan data
    $loan_q = $dbs->query('SELECT item_code FROM loan WHERE loan_id='.$deleteid.'');
    $loan_d = $loan_q->fetch_row();
    
  //  $reserve_loan_d=array_push($loan_d);
    //$r=serialize($reserve_loan_d);
   // $reserve_loan= $reserve_loan_d[];
    // create circulation object
    $circulation = new circulation($dbs, $_SESSION['memberID']);
    $return_status = $circulation->returnItem($deleteid);
	//exit();
   }
                $implode=implode(",",$_POST['loanID']);
                $loan_q = $dbs->query('SELECT item_code FROM loan WHERE loan_id IN ('.$implode.')');
                $loan_reserve_data='';
                while($row=$loan_q->fetch_assoc())
                {
                        $loan_reserve_data.=$row['item_code'].',';
                }
                $explode=explode(",",$loan_reserve_data);
                $reserve_loan_d=$explode;
                $r=serialize($reserve_loan_d);


                echo '<script type="text/javascript">';
                         if ($return_status === ITEM_RESERVED) 
                        {
                                    echo 'location.href = \'loan_list.php?reserveAlert='.urlencode($r).'\';';
                        }
                        else
                        {
                            echo "\n".'alert(\''.gettext('Issue Item With '.$loan_reserve_data.' has been Returned On '.date('d-m-Y').' Dated ').'\');';
                            echo 'location.href = \'loan_list.php\';'; 
                          
                         }
                        echo '</script>';
                        echo '<script type="text/javascript">';
                        if ($circulation->loan_have_overdue) 
                        {
                            echo "\n".'alert(\''.gettext('Overdue fines inserted to fines database').'\');'."\n";
                        }

                        echo '</script>';

                        
                        
    /*if ($_POST['process'] == 'return') 
    {
      //  $return_status = $circulation->returnItem($loanID);
        // write log
        utility::writeLogs($dbs, 'member', $_SESSION['memberID'], 'circulation', $_SESSION['realname'].' return item '.$loan_d[0].' for member ('.$_SESSION['memberID'].')');
            echo '<script type="text/javascript">';
        if ($circulation->loan_have_overdue) 
        {
            echo "\n".'alert(\''.__('Overdue fines inserted to fines database').'\');'."\n";
        }
        if ($return_status === ITEM_RESERVED) 
        {
            echo 'location.href = \'loan_list.php?reserveAlert='.urlencode($loan_d[0]).'\';';
        }
        else
        {
            echo 'location.href = \'loan_list.php\';'; 
         
        }
        echo '</script>';
      
    } */


}
//else {
else {

    if (empty($_POST['loanID']))
    {       
       echo '<script type="text/javascript">';       
       echo "\n".'alert(\''.gettext('Please Select item code!').'\');'."\n";		    
       echo 'location.href = \'loan_list.php\';';      
       echo '</script>';
     exit;  
    }
    
	 $countcheck=count($_POST['loanID']);
	 for($i=0;$i<$countcheck;$i++)
	    {
		 $deleteid=$_POST['loanID'][$i];	  	
                 $loan_q = $dbs->query('SELECT item_code FROM loan WHERE loan_id='.$deleteid.'');                 
                 $loan_d = $loan_q->fetch_row();
	    
	    
	   // $reserve_loan= $reserve_loan_d[];
	    // create circulation object
	    $circulation = new circulation($dbs, $_SESSION['memberID']);
	      
            
		// set holiday settings
		$circulation->holiday_dayname = $_SESSION['holiday_dayname'];
		$circulation->holiday_date = $_SESSION['holiday_date'];

                
                $extend_status = $circulation->extendItemLoan($deleteid);

                //echo $extend_status;die;
                
	/*	if ($extend_status == 'ITEM_RESERVED') 
                {                                      
                    
		    echo '<script type="text/javascript">';
		    echo 'alert(\''.__('Item CANNOT BE Extended! This Item is being reserved by other member').'\');';
		    echo 'location.href = \'loan_list.php\';';
		    echo '</script>';
		} */
                
                
                if(!$extend_status )
                {   
                    echo 'ReborrowLimitOver';
                    echo '<script type="text/javascript">';
		    echo 'alert(\''.gettext('Your Re-Issue Limit Is Over').'\');';
		    echo 'location.href = \'loan_list.php\';';
		    echo '</script>';
                                        
                }                   
                elseif($extend_status)
                {
                    
                     // write log
		    utility::writeLogs($dbs, 'tblstudent', $_SESSION['memberID'], 'circulation', $_SESSION['realname'].' extend issue for item '.$loan_d[0].' for member ('.$_SESSION['memberID'].')');
		    echo '<script type="text/javascript">';
		    echo 'alert(\''.gettext('Issue Extended').'\');';
		    if ($circulation->loan_have_overdue) 
                    {
		        echo "\n".'alert(\''.gettext('Overdue fines inserted to fines database').'\');'."\n";
		    }
		    echo 'location.href = \'loan_list.php\';';
		    echo '</script>';
                }   
                
	    }
    }
    exit();
}
if (isset($_POST['tempLoanID'])) 
{


               $circulation = new circulation($dbs, $_SESSION['memberID1']);
               
               $circulation->holiday_dayname = $_SESSION['holiday_dayname'];
               $circulation->holiday_date = $_SESSION['holiday_date'];               
               $data2=array();
               
                    
                $memberID = $_SESSION['memberID1'];
                $temp=$_POST['tempLoanID'];
	        
                $bib="SELECT biblio_id FROM item WHERE item_code='$temp'";  
				//echo    $bib;die();           
    		$bib1=$dbs->query($bib);
		$bib2=$bib1->fetch_row();
		$bib3=$bib2[0];

                
               
		$memberID = $_SESSION['memberID1'];	
                                   
               
                $temp_item="SELECT item_code AS item_code FROM temp_request where member_id='$memberID' and temp_id not in(select temp_id from loan where member_id='$memberID' and temp_id!=0)";                                              		                                                   
                //echo $temp_item;die;
                $temp_item1=$dbs->query($temp_item);                                                
		$temp_val_biblio_id='';		
		while($temp_item2=$temp_item1->fetch_assoc())
		{
                    $biblio_id_data="select biblio_id from item where item_code='$temp_item2[item_code]'";
                    $biblio_id_data1=$dbs->query($biblio_id_data);                                                
                    $biblio_id_data2=$biblio_id_data1->fetch_assoc();
                    $temp_val_biblio_id.=$biblio_id_data2['biblio_id'].',';
		}
                
                $explode1=explode(",",$temp_val_biblio_id);                                                                                                                                                                                               
		if(in_array($bib3,$explode1))
		{
			    echo '<script type="text/javascript">';
			    echo 'alert(\''.gettext('You have already Request for this book Please confirm it From There!').'\');';				   
			    echo '</script>';
			    die();
		}
                                         
//                $time_of_other_request="select * from temp_request where item_code='$temp' and temp_id in(select temp_id from loan where request_date is not null and temp_id>0)"; 
                $time_of_other_request1=$dbs->query($time_of_other_request);                                                
                
                if ($time_of_other_request1->num_rows>0)
                {
                            echo '<script type="text/javascript">';
			    echo 'alert(\''.gettext('This Item is issued oR Reserved By Other Please User Other Item Code!').'\');';				   
			    echo '</script>';
			    die();
                }
                
                
                
                $temp_item="SELECT count(item_code) AS item_code FROM temp_request where member_id='$memberID' ";
                $temp_item1=$dbs->query($temp_item);
                                                
		$temp_val='';		
		while($temp_item2=$temp_item1->fetch_assoc())
		{
                                    
			 $temp_val.=$temp_item2['item_code'].',';
		}
		                                        
		$temp_val=substr($temp_val,0,-1);
                //utility::jsAlert($temp_val);
                
          //echo "$temp_val=". $temp_val;die();     
		if(intval($temp_val) >= 1)
		{       
                      
                    
            $temp_item="select item_code from temp_request where member_id='$memberID'";
                                                
			$temp_item=$dbs->query($temp_item);
			$temp_i='';
			while($temp_i1=$temp_item->fetch_assoc())
			{
				$temp_i.="'".$temp_i1['item_code']."',";
			}
			$temp_i=substr($temp_i,0,-1);
                        //utility::jsAlert($temp_i);
			$session_bib="SELECT biblio_id FROM item WHERE item_code IN($temp_i) ";
                        
			$session_bib1=$dbs->query($session_bib);
			$session_val='';		
			while($session_bib2=$session_bib1->fetch_assoc())	
			{
				$session_val.=$session_bib2['biblio_id'].',';
	
			}
			
			$explode=explode(",",$session_val);
                        
			$count_loan="select count(loan_id) AS loan_id from loan";                        			                        
                        $count_loan=$dbs->query($count_loan);
			//$c='';
			while($row=$count_loan->fetch_assoc())
			{
				 $c=$row['loan_id'];
			}
                       // utility::jsAlert($c);
			if(intval($c)>0)
			{	
                            
                            //$loan_item="select count(item_code) as item_code from loan where member_id='$memberID' AND is_return=0";
				$loan_item="select count(item_code) as item_code from loan";                                
				$loan_item=$dbs->query($loan_item);
				
				while($row=$loan_item->fetch_assoc())
				{
					//$loan_i.=$row['item_code'];
                                        $loan_i=$row['item_code'];
				}				
				if(intval($loan_i)>0)
				{
					//$loan_item="select item_code from loan where member_id='$memberID' AND is_return=0";
                                        $loan_item="select item_code from loan where member_id='$memberID' AND return_date is null";
                                        
                                        
					$loan_item=$dbs->query($loan_item);
					$loan_i='';
					while($row=$loan_item->fetch_assoc())
					{
						$loan_i.="'".$row['item_code']."',";
					}
					$loan_i=substr($loan_i,0,-1);
					if($loan_i!='')
					{
						$session_bib2="SELECT biblio_id FROM item WHERE item_code IN($loan_i) ";                                                
						$session_bib3=$dbs->query($session_bib2);
						$session_val1='';		
						while($row=$session_bib3->fetch_assoc())	
						{
							$session_val1.=$row['biblio_id'].',';
	
						}
						 
						$explode1=explode(",",$session_val1);                                                                                                                                                                                               
						if(in_array($bib3,$explode1))
						{
						    echo '<script type="text/javascript">';
						    echo 'alert(\''.gettext('You Cannot Issue Same Copy Of Books').'\');';
						   echo '</script>';
						    die();
						} //-- End If
					} //-- End IF loop of $loan_i!=''
				} // -- End IF loop of intval($loan_i)>0
			} // --- End If loop of intval($c)>0  
                           
			                        
      
                        ///////////////////////////////////////Temp///////////////////////////////////////////////////////////////////////////////

    					
                        $add = $circulation->addLoanSession($_POST['tempLoanID']);
                        
                            if ($add == LOAN_LIMIT_REACHED) 
                            {        
                                 utility::jsAlert('Issue Limit has been Finished Please Increase It123!');
                                 exit();

                            }
                            else if ($add == ITEM_RESERVED) 
                            {
                                echo '<html>';
                                echo '<body>';
                                echo '<form method="post" name="overrideForm" action="'.MODULES_WEB_ROOT_DIR.'circulation/circulation_action.php">';
                                echo '<input type="hidden" name="overrideID" value="'.$_POST['tempLoanID'].'" /></form>';
                                echo '<script type="text/javascript">';
                                echo 'var confOverride = confirm(\''.gettext('WARNING! This Item is reserved by You Or another member').'\' + "\n" + \''.__('Do You Want To Overide This?').'\');';
                                echo 'if (confOverride) { ';
                                echo 'document.overrideForm.submit();';
                                echo '} else { self.location.href = \'loan.php\';}';
                                echo '</script>';
                                echo '</body>';
                                echo '</html>';
                                exit();      
                            }
                            else if ($add == ITEM_NOT_FOUND) 
                            {
                                echo '<script type="text/javascript">';
                                echo 'alert(\''.gettext('This Item is not registered in database').'\');';
                                echo 'location.href = \'loan.php\';';
                                echo '</script>';
                                exit();
                            }
                            else if ($add == ITEM_UNAVAILABLE) 
                            {
                                echo '<script type="text/javascript">';
                                echo 'alert(\''.gettext('This Item is currently not available').'\');';
                                echo 'location.href = \'loan.php\';';
                                echo '</script>';
                                exit();
                            }
                            else if ($add == LOAN_NOT_PERMITTED) 
                            {
                                echo '<script type="text/javascript">';
                                echo 'alert(\''.gettext('Issue NOT PERMITTED! Membership already EXPIRED! TEST').'\');';
                                echo 'location.href = \'loan.php\';';
                                echo '</script>';
                                exit();
                            }
                            else if ($add == ITEM_LOAN_FORBID) 
                            {
                                echo '<script type="text/javascript">';
                                echo 'alert(\''.gettext('The Item cannot be taken for loan!').'\');';
                                echo 'location.href = \'loan.php\';';
                                echo '</script>';
                                exit();
                            } 

                        
                        
                            
                        $sql_op = new simbio_dbop($dbs);
			$memberID = $_SESSION['memberID1'];
			$_loan_date = date('Y-m-d');
			$data['item_code']=$_POST['tempLoanID'];	
			$data['member_id']=$dbs->escape_string($memberID);
			$data['request_date']=$dbs->escape_string($_loan_date);
                        $data['time']=date('H:i:s');
                        $data['status']='Pending';

                        $material_id="select material_sub_id from biblio where biblio_id=$bib3";
                        $material_id1=$dbs->query($material_id);
                        $material_id2=$material_id1->fetch_row();
                        $material_id=$material_id2[0];
                        
                        $data['material_sub_id']=$material_id;
                            
                        
                        $onl_request="select temp_request.member_id,temp_request.item_code,temp_request.request_date,temp_request.req_time,biblio.material_sub_id from biblio 
                        left join item ON biblio.biblio_id=item.biblio_id 
                        left join temp_request ON temp_request.item_code=item.item_code                                       
                        where temp_request.item_code='$_POST[tempLoanID]'";
                        
                        $onl_request1=$dbs->query($onl_request);                                                                                                                             
                        if ($onl_request1->num_rows>0)
                        {  
                               $my_date1=array();
                               $my_date2=array();
                               $onl_request2=$onl_request1->fetch_row();
                               $my_date=date('Y-m-d');                                                                    
                               $my_date1=explode('-', $my_date);
                               $my_date2=explode('-', $onl_request2[2]);
                               
                                       
                                       
                                 
                               /*if($my_date1[2]<=$my_date1[2])                                                                                             
                               {
                                   if($my_date1[1]<=$my_date1[1])                                                                                             
                                   {*/
                               if(strtotime($my_date)<=strtotime($onl_request2[2]))
                               {                                  
                                     $data1=array();
                                     if (date('H:i:s')<$onl_request2[3])
                                     {
                                         $member = new member($dbs, $memberID);
                                         $member->getMemberTypeProp();
                                          $due_date="select c.cat_issue_period,m.expire_date from ".$inte_schema.".tblstudent as m 
										  left join category_mast as c ON c.cat_mast_user =m.user_profile_id 
										  where m.SUB_INSTITUTE_ID='$_SESSION[SUB_INSTITUTE_ID]' AND m.enrollment_no like '$member->member_id' and c.material_sub_id=$onl_request2[4]";                                                                                       
                                         // $approximate_due_date = strtotime(date("Y-m-d", strtotime($due_date2[1])) . " +$due_date2[0] day");
                                          //$approximate_due_date=date("Y-m-d", $approximate_due_date); 
                                                                
                                          $due_date1=$dbs->query($due_date);  
                                          $due_date2=$due_date1->fetch_row();
                                                                                                                                                                                   
                                          $data1['item_code']=$_POST['tempLoanID'];	
                                          $data1['member_id']=$dbs->escape_string($memberID);
                                          $data1['loan_date']=date('Y-m-d');                                                                                        
                                          $data1['due_date']=date('Y-m-d', strtotime('+'.$due_date2[0].' days'));  
                                          $data1['time']=date('H:i:s');     
//                                           utility::jsAlert($data1['due_date']);       
                                           $datediff =strtotime($due_date2[1])-strtotime($data1['due_date']) ;
                                           //utility::jsAlert($datediff);die;
                                           $days_diff=floor($datediff/(60*60*24));
    

                                               //   if ($days_diff<$due_date2[0])
                                              if ($days_diff<0)
                                                  {    
                                                       utility::jsAlert('Member will Expire before Due Date');
                                                       exit();
                                                       
                                                  }
                                                  else    
                                                  {
                                                        $insert = $sql_op->insert('loan',$data1);
                                                        if ($insert)
                                                        {
                                                              echo '<script type="text/javascript">';
                                                              echo 'alert(\''.gettext('Loan Data Has Been Issued!').'\');';
                                                              echo 'location.href = \'loan.php\';';
                                                              echo '</script>'; 
                                                              exit();
                                                        }
                                                     
                                                  }
                                                                                       


                                      }
                                      else
                                      {                                                                                         
                                            utility::jsAlert('Book cant be Issue Please Send Request Through Login!');
                                            exit();
                                       
                                      }

                               
                               }
                               //  }
                                   
//                               }
                               /*else
                               {                                                                           
                                    utility::jsAlert('Item Loan cant be Issue Please Send Request Through Login!');
                                    exit();
                               }*/
                           }
                           else
                           {                               //echo "hiiii";die;

                                  $member = new member($dbs, $memberID);
                                  $member->getMemberTypeProp();
                                         
                                 $mat_id="select biblio.material_sub_id from item left join biblio ON biblio.biblio_id=item.biblio_id  where item_code = $_POST[tempLoanID]";                                                                                                                                                                                                          
                                                           
                                 $mat_id1=$dbs->query($mat_id);  
                                 $mat_id2=$mat_id1->fetch_row();
                                   
                                 $due_date="select c.cat_issue_period,m.expire_date from ".$inte_schema.".tblstudent as m left join category_mast as c ON c.cat_mast_user =m.user_profile_id where m.SUB_INSTITUTE_ID='$_SESSION[SUB_INSTITUTE_ID]' AND m.enrollment_no like ''$member->member_id'' and c.material_sub_id=$mat_id2[0]";
                                                                  
                                 $due_date1=$dbs->query($due_date);  
                                 $due_date2=$due_date1->fetch_row();                                                                                                                       
                                 $data1['item_code']=$_POST['tempLoanID'];	
                                 $data1['member_id']=$dbs->escape_string($memberID);
                                 $data1['loan_date']=date('Y-m-d');                                                                            
                                 $data1['due_date']=date('Y-m-d', strtotime('+'.$due_date2[0].' days'));  
                                 $data1['time']=date('H:i:s');     
                                                  
                                 /*echo 'expire date'.$due_date2[1];echo '<br>';
                                 echo 'due date'.$data1['due_date'];echo '<br>';
                                 */
                                 $datediff =strtotime($due_date2[1])-strtotime($data1['due_date']) ;
                                 $days_diff=floor($datediff/(60*60*24));
                                 
                                 /*echo 'day diff'.$days_diff;echo '<br>';
                                 echo 'due date diff'.$due_date2[0];echo '<br>';
                                 die;*/
                                 //if ($days_diff<$due_date2[0])
                                 
                                 if ($days_diff<0)
                                 {
                                     
                                    /* echo '<script type="text/javascript">';
                                     echo 'show_confirm();';
                                     //$b=show_confirm();
                                     echo '</script>';
                                     
                                     echo $_SESSION['confrm'];echo '<br>';
                                     //echo $hhhh;echo '<br>';
                                     die;
                                     if ($_SESSION['confrm']==true)
                                     {
                                        utility::jsAlert('Ok');
                                        exit();    
                                     }
                                     else
                                     {
                                        utility::jsAlert('Else');
                                        exit();     
                                     }
                                     unset ($_SESSION['confrm']);*/
                                     //$a=return show_confirm();
                                    //utility::jsAlert($a);                                                                           
                                     
                                     utility::jsAlert('Member will Expire before Due Date');
                                     exit();
                                       
                                      
                                      
                                      

                                      
                                     
                                 }
                                 else    
                                 {       
                                         

                                         $insert = $sql_op->insert('loan',$data1);
                                         if ($insert)
                                         {
                                                 utility::jsAlert('3-Loan has been Issued!');
												 //exit();
                                                 
                                         }
                                     
                                          
                                 }                                                                                                                                                                                 
                         }

                        
  /////////////////////////////////////Temp//////////////////////////////////////////////////////////////////////////////////////
                                                                                                                                                                                                                        
		}
		else
		{                            
                   
                    
                        $explode=explode(",",$session_val);
                        $count_loan="select count(loan_id) AS loan_id from loan";
                        $count_loan=$dbs->query($count_loan);
                        $c='';
                        while($row=$count_loan->fetch_assoc())
                        {
                              $c.=$row['loan_id'];
                        }
                        if($c>0)
                        {

                              //$loan_item="select count(item_code) as item_code from loan where member_id='$memberID' AND is_return=0";
                              $loan_item="select count(item_code) as item_code from loan";
							  //echo $loan_item;die();
                              $loan_item=$dbs->query($loan_item);
                              $loan_i='';
                              while($row=$loan_item->fetch_assoc())
                              {
                                   $loan_i.=$row['item_code'];
                              }
                              if($loan_i>0)
                              {
                                  $loan_item="select item_code from loan where member_id='$memberID' AND return_date is null";
								  //echo $loan_item;die();
                                  $loan_item=$dbs->query($loan_item);
                                  $loan_i='';
                                  while($row=$loan_item->fetch_assoc())
                                  {
                                       $loan_i.="'".$row['item_code']."',";
                                  }
                                  $loan_i=substr($loan_i,0,-1);
                                  if($loan_i!='')
                                  {	
                                         $session_bib2="SELECT biblio_id FROM item WHERE item_code IN($loan_i) ";
										 //echo $session_bib2;die();
                                         $session_bib3=$dbs->query($session_bib2);
                                         $session_val1='';		
                                         while($row=$session_bib3->fetch_assoc())	
                                         {
                                              $session_val1.=$row['biblio_id'].',';
                                         }
                                         $explode1=explode(",",$session_val1);
                                         if(in_array($bib3,$explode1))
                                         {
                                             echo '<script type="text/javascript">';
                                             echo 'alert(\''.gettext('You Cannot Issue Same Copy Of Books').'\');';
                                             echo '</script>';
                                             die();
                                         }
                                    }
                                }
                        }
                                                                                                                                        
                                ///////////////////////////////////////Temp///////////////////////////////////////////////////////////////////////////////
                             
                                            $add = $circulation->addLoanSession($_POST['tempLoanID']); 
											//echo "Add :".$add; die();                                          
                                            if ($add == LOAN_LIMIT_REACHED) 
                                            {        
                                                 utility::jsAlert('Issue Limit has been Finished Please Increase It!');
                                                 exit();
                                            }
                                            else if ($add == ITEM_RESERVED) 
                                            {

                                                echo '<html>';
                                                echo '<body>';
                                                echo '<form method="post" name="overrideForm" action="'.MODULES_WEB_ROOT_DIR.'circulation/circulation_action.php">';
                                                echo '<input type="hidden" name="overrideID" value="'.$_POST['tempLoanID'].'" /></form>';
                                                echo '<script type="text/javascript">';
                                                echo 'var confOverride = confirm(\''.gettext('WARNING! This Item is reserved by You Or another member').'\' + "\n" + \''.gettext('Do You Want To Overide This?').'\');';
                                                echo 'if (confOverride) { ';
                                                echo 'document.overrideForm.submit();';
                                                echo '} else { self.location.href = \'loan.php\';}';
                                                echo '</script>';
												echo exit();
                                                echo '</body>';
                                                echo '</html>';
                                                exit();      
                                            }
                                            else if ($add == ITEM_NOT_FOUND) 
                                            {
                                                echo '<script type="text/javascript">';
                                                echo 'alert(\''.gettext('This Item is not registered in database').'\');';
                                                echo 'location.href = \'loan.php\';';
                                                echo '</script>';
                                                exit();
                                            }
                                            else if ($add == ITEM_UNAVAILABLE) 
                                            {
                                                echo '<script type="text/javascript">';
                                                echo 'alert(\''.gettext('This Item is currently not available').'\');';
                                                echo 'location.href = \'loan.php\';';
                                                echo '</script>';
                                                exit();
                                            }
                                            else if ($add == LOAN_NOT_PERMITTED) 
                                            {
                                                echo '<script type="text/javascript">';
                                                echo 'alert(\''.gettext('Issue NOT PERMITTED! Membership already EXPIRED!').'\');';
                                                echo 'location.href = \'loan.php\';';
                                                echo '</script>';
                                                exit();
                                            }
                                            else if ($add == LOAN_NOT_PERMITTED_PENDING) 
                                            {
                                                echo '<script type="text/javascript">';
                                                echo 'alert(\''.gettext('Loan NOT PERMITTED! Membership under PENDING State!').'\');';
                                                echo 'location.href = \'loan.php\';';
                                                echo '</script>';
                                                exit();
                                            }
                                            else if ($add == ITEM_LOAN_FORBID) 
                                            {
                                                echo '<script type="text/javascript">';
                                                echo 'alert(\''.gettext('The Item cannot be taken for loan!').'\');';
                                                echo 'location.href = \'loan.php\';';
                                                echo '</script>';
                                                exit();
                                            } 
                                            
                        
                        
                        
                        
                        ////////////////////////////Temp//////////////////////////////////////////////////////////////////////////////////////
                                                                        
                                             
                                            $sql_op = new simbio_dbop($dbs);
                                            $memberID = $_SESSION['memberID1'];
                                            $_loan_date = date('Y-m-d');
                                            $data['item_code']=$_POST['tempLoanID'];	
                                            $data['member_id']=$dbs->escape_string($memberID);
                                            $data['request_date']=$dbs->escape_string($_loan_date);
                                            $data['time']=date('H:i:s');
                                           // $data['status']='Pending';

                                            $material_id="select material_sub_id from biblio where biblio_id=$bib3";
											//echo $material_id;die();
                                            $material_id1=$dbs->query($material_id);
                                            $material_id2=$material_id1->fetch_row();
                                            $material_id=$material_id2[0];

                                           $data['material_sub_id']=$material_id;                                          
                                           $onl_request="select temp_request.member_id,temp_request.item_code,temp_request.request_date,temp_request.req_time,biblio.material_sub_id from biblio left join item ON biblio.biblio_id=item.biblio_id left join temp_request ON temp_request.item_code=item.item_code where temp_request.item_code='$_POST[tempLoanID]' and temp_request.req_status='Pending'";
										  // echo $onl_request;die();
                                          //  $onl_request="select member_id,item_code,request_date ,time,material_sub_id from temp_request where status='Pending' and item_code='$_POST[tempLoanID]'";                        
                                         
                                           $onl_request1=$dbs->query($onl_request);
                                            if ($onl_request1->num_rows>0)
                                            {
                                                $onl_request2=$onl_request1->fetch_row();
                                                $my_date=date('Y-m-d');                        
                                                if  ($my_date<=$onl_request2[2])
                                                {                         
                                                     $data1=array();
                                                     if (date('H:i:s')<$onl_request2[3])
                                                     {  
                                                         $member = new member($dbs, $memberID);
                                                         $member->getMemberTypeProp();
                                                           $due_date="select c.cat_issue_period,m.expire_date from ".$inte_schema.".tblstudent as m left join category_mast as c ON c.cat_mast_user =m.user_profile_id where m.SUB_INSTITUTE_ID='$_SESSION[SUB_INSTITUTE_ID]' AND m.enrollment_no like '$member->member_id' and c.material_sub_id=$onl_request2[4]";
														   echo $due_date;die();
                                                                                         
                                                          $due_date1=$dbs->query($due_date);  
                                                          $due_date2=$due_date1->fetch_row();
                                                         
                                                          $data1['item_code']=$_POST['tempLoanID'];	
                                                          $data1['member_id']=$dbs->escape_string($memberID);
                                                          $data1['loan_date']=date('Y-m-d');
                                                          $data1['loan_date']=date('Y-m-d');                                        
                                                          $data1['due_date']=date('Y-m-d', strtotime('+'.$due_date2[0].' days'));  
                                                          $data1['time']=date('H:i:s');     
                                                                          
                                                             $datediff =strtotime($due_date2[1])-strtotime($data1['due_date']) ;
                                                             $days_diff=floor($datediff/(60*60*24));
                                                             
                                                             
                                                             
                                                            // if ($days_diff<$due_date2[0])
                                                             if ($days_diff<0)
                                                             {

                                                                 utility::jsAlert('Member will Expire before Due Date');
                                                                 exit();


                                                             }
                                                             else 
                                                             {
                                                                 $insert = $sql_op->insert('loan',$data1);
                                                                 if ($insert)
                                                                 {
                                                                        utility::jsAlert('Loan has been Issued!');
																		//exit();
                                                                 }

                                                             
                                                          
                                                             }
                                     
                                
                                                        }
                                                        else
                                                        {
                                                                    utility::jsAlert('Book cant be Issue Please Send Request Through Login!');
                                                                    exit();
                                                               
                                                         }
                                                                                                                       
                                                     }
                                                     else
                                                     {
                                                            utility::jsAlert('Book cant be Issue Please Send Request Through Login!');
                                                            exit();
                                                            
                                                     }
                                                
                                           }
                                           else                                                                                              
                                           {
                                               $member = new member($dbs, $memberID);
                                               $member->getMemberTypeProp();
                                              
                                         $mat_id="select biblio.material_sub_id from item left join biblio ON biblio.biblio_id=item.biblio_id  							where item_code = '$_POST[tempLoanID]' ";
											  // echo $mat_id;die();
                                                            $mat_id1=$dbs->query($mat_id);  
                                                            $mat_id2=$mat_id1->fetch_row();
                                                                                          
                                                           $due_date="select c.cat_issue_period,m.expire_date from ".$inte_schema.".tblstudent as m left join category_mast as c ON c.cat_mast_user =m.user_profile_id where m.SUB_INSTITUTE_ID='$_SESSION[SUB_INSTITUTE_ID]' AND m.enrollment_no like '$member->member_id' and c.material_sub_id=$mat_id2[0]";                                                                                  
                                                           //echo $due_date;die;
                                                            $due_date1=$dbs->query($due_date);  
                                                            $due_date2=$due_date1->fetch_row();
															$data1['item_code']=$_POST['tempLoanID'];	
															$data1['member_id']=$dbs->escape_string($memberID);
															$data1['loan_date']=date('Y-m-d');                                                                            
															$data1['due_date']=date('Y-m-d', strtotime('+'.$due_date2[0].' days'));  
															$data1['time']=date('H:i:s');                                                                                                     
															$datediff =strtotime($due_date2[1])-strtotime($data1['due_date']) ;
															$days_diff=floor($datediff/(60*60*24));
															// echo 'days diff '.$days_diff.'<br>';
															// echo 'date diff '.$datediff.'<br>';
															// echo $data1['due_date'];die; 
															//  if ($days_diff<$due_date2[0])
															  if ($days_diff<0)
															 {
																	utility::jsAlert('Member will Expire before Due Date');
																	exit();
															  }
															  else
															  {
																  $insert = $sql_op->insert('loan',$data1);
																  if ($insert)
																  {
																		utility::jsAlert('Loan has been Issued!');
																		exit();
																   }
															  }                                                                                                                                                                                                                                                                                                                          

                                             }
                                            
                                            

                         

                        //////////////////////////temp//////////////////////////////////////////////////////////
                        
                        
                        
                        
                        
		}
		//echo $_SESSION['$t'].=$_POST['tempLoanID'];
	
		//$temp_it="select item_code from temp where member_id='$memberID'";
                $temp_it="select item_code from temp_request where member_id='$memberID'";
		$temp_it2=$dbs->query($temp_it);
		$temp_val2='';		
		while($row=$temp_it2->fetch_assoc())
		{
			 $temp_val2.=$row['item_code'].',';
		}
		
		$temp_val2=substr($temp_val2,0,-1);


		$confirm="select confirm_date from temp_request where item_code IN ( $temp) AND member_id='$memberID' AND req_status='Confirm' ";
                
		$confirm1=$dbs->query($confirm);
		$confirm_date_t_request='';
		while($con=$confirm1->fetch_assoc())
		{
			$confirm_date_t_request.="".str_replace("\'","''",$con['confirm_date']).",";
		}	
		$confirm_date_t_request=substr($confirm_date_t_request,0,-1);
		
		//echo $current_date=date("Y-m-d");

		$current_date="".str_replace("\'","''",date("Y-m-d"))."";
		// $diff=date_diff($confirm_date_t_request,$current_date);
		
		//$date1 = "2011-02-23";
		//$date2 = "2011-02-26";

		
		
		//$t= new simbio_date();
		$pp = simbio_date::getNextDateNotHoliday($current_date,$_SESSION['holiday_dayname'], $_SESSION['holiday_date']);
						
		$diff = abs(strtotime($yesterday) - strtotime($confirm_date_t_request));                
		$years = floor($diff/ (365*60*60*24));                
		$months = floor(($diff- $years * 365*60*60*24) / (30*60*60*24));
		$days = floor(($diff - $years * 365*60*60*24 - $months*30*60*60*24)/ (60*60*24));
		
		if($confirm_date_t_request!='')
		{		
			if($days>1)
			{
                            
					    echo '<script type="text/javascript">';
					    echo 'alert(\''.gettext('You Cannot Issue Book').'\');';
					   
				            echo '</script>';
					   // die();
			
			}

		}
}


// loan limit override
if (isset($_POST['overrideID']) AND !empty($_POST['overrideID'])) 
{
    
    // define constant
    define('IGNORE_LOAN_RULES', 1);
    // create circulation object
    $circulation = new circulation($dbs, $_SESSION['memberID']);
    // set holiday settings
    $circulation->holiday_dayname = $_SESSION['holiday_dayname'];
    $circulation->holiday_date = $_SESSION['holiday_date'];
    // add item to loan session
    $add = $circulation->addLoanSession($_POST['overrideID']);
    echo '<script type="text/javascript">';
    echo 'location.href = \'loan.php\';';
    echo '</script>';
    exit();
   //comment by iresh on 13/1/2011 exit();
}


// remove temporary item session
if (isset($_GET['removeID'])) 
{  
    // create circulation object
    $circulation = new circulation($dbs, $_SESSION['memberID']);
    // remove item from loan session
    $circulation->removeLoanSession($_GET['removeID']);
    echo '<script type="text/javascript">';
    $msg = str_replace('{removeID}', $_GET['removeID'], gettext('Item {removeID} removed from session')); //mfc
    echo 'alert(\''.$msg.'\');';
    echo 'location.href = \'loan.php\';';
    echo '</script>';
exit();
    //comment by iresh on 13/1/2011 exit();
}


// quick return proccess
if (isset($_POST['quickReturnID']) ) 
{       
    if (empty($_POST['quickReturnID']) ) 
    {       
           echo '<script type="text/javascript">';       
           echo "\n".'alert(\''.gettext('Please Enter item code!').'\');'."\n";		    
           echo 'location.href = \'index.php?mod=circulation\';';
           echo '</script>';
           exit; 
    }
  
    $i=0;
	$sql = "SELECT l.*,m.enrollment_no,m.first_name,b.title FROM loan AS l
    INNER JOIN item AS i ON i.item_code=l.item_code
    INNER JOIN biblio AS b ON i.biblio_id=b.biblio_id
    INNER JOIN ".$inte_schema.".tblstudent AS m ON m.SUB_INSTITUTE_ID='$_SESSION[SUB_INSTITUTE_ID]' AND l.member_id collate utf8_general_ci = m.enrollment_no collate utf8_general_ci
    WHERE l.item_code='".$dbs->escape_string($_POST['quickReturnID'])."' AND loan_date is not null AND return_date is null";
//    echo $sql; die();
	$loan_info_q = $dbs->query($sql);
    if($loan_info_q->num_rows==0)
    {
		$sql = "SELECT * FROM loan AS l
        INNER JOIN item AS i ON i.item_code=l.item_code
        INNER JOIN biblio AS b ON i.biblio_id=b.biblio_id
        INNER JOIN ".$inte_schema.".tbluser AS m ON m.SUB_INSTITUTE_ID='$_SESSION[SUB_INSTITUTE_ID]' AND l.member_id collate utf8_general_ci = m.user_name collate utf8_general_ci
        WHERE l.item_code='".$dbs->escape_string($_POST['quickReturnID'])."' AND loan_date is not null AND return_date is null";
        $loan_info_q = $dbs->query($sql);
    }    
    if ($loan_info_q->num_rows < 1) 
    {
        $i++;
        echo '<div class="errorBox">'.gettext('This is item already returned or not exists in loan database').'</div>';
    } 
    else
    {
        
        $return_date = date('Y-m-d');        
        $loan_d = $loan_info_q->fetch_assoc();        
        $circulation = new circulation($dbs, $loan_d['enrollment_no']);        
        $overdue = $circulation->countOverdueValue($loan_d['loan_id'], $return_date);
        
        
        if ($overdue)
        {
            $msg = str_replace('{overdueDays}', $overdue['days'],gettext('OVERDUED for {overdueDays} days(s) with fines value of')); //mfc
            $loan_d['title'] .= '<div style="color: red; font-weight: bold;">'.$msg.$overdue['value'].'</div>';
        }
        
        
        $_SESSION['retuen_date']='return';        
        
        $return_status = $circulation->returnItem($loan_d['loan_id']);        
      
        
        if ($return_status === ITEM_RESERVED) 
        {            
            $reserve_q = $dbs->query('SELECT r.member_id, m.first_name
                                        FROM reserve AS r
                                        LEFT JOIN '.$inte_schema.'.tblstudent AS m ON m.SUB_INSTITUTE_ID='.$_SESSION['SUB_INSTITUTE_ID'].' AND r.member_id=m.enrollment_no
                                        WHERE item_code=\''.$loan_d['item_code'].'\' ORDER BY reserve_date DESC');
            $reserve_d = $reserve_q->fetch_row();
            $member = $reserve_d[1].' ('.$reserve_d[0].')';
            $reserve_msg = str_replace(array('{itemCode}', '{member}'), array($loan_d['item_code'], $member), __('Item {itemCode} is being reserved by member {member}')); //mfc
            $loan_d['title'] .= '<div>'.$reserve_msg.'</div>';
        }
        
        // write log
       // show loan information
        include SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
        // create table object
        $table = new simbio_table();
        
        $table->table_attr = 'class="border" style="width: 100%; margin-bottom: 5px;" cellpadding="5" cellspacing="0"';
        // append data to table row
        $table->appendTableRow(array('Item '.$_POST['quickReturnID'].'  '.('Successfully returned on').'  '.date('d-m-Y',strtotime($return_date)))); //mfc
        $table->appendTableRow(array(gettext('Title'), $loan_d['title']));
        $table->appendTableRow(array(gettext('Member Name'),$loan_d['first_name'], gettext('Member ID'), $loan_d['enrollment_no']));
        $table->appendTableRow(array(gettext('Issue Date'),date('d-m-Y',strtotime( $loan_d['loan_date'])), gettext('Due Date'),date('d-m-Y',strtotime($loan_d['due_date']))));
        // set the cell gettext
        $table->setCellAttr(1, null, 'class="dataListHeader" style="color: #FFFFFF; font-weight: bold;" colspan="4"');
        $table->setCellAttr(2, 0, 'class="alterCell"');
        $table->setCellAttr(2, 1, 'class="alterCell2" colspan="3"');
        $table->setCellAttr(3, 0, 'class="alterCell" width="15%"');
        $table->setCellAttr(3, 1, 'class="alterCell2" width="35%"');
        $table->setCellAttr(3, 2, 'class="alterCell" width="15%"');
        $table->setCellAttr(3, 3, 'class="alterCell2" width="35%"');
        $table->setCellAttr(4, 0, 'class="alterCell" width="15%"');
        $table->setCellAttr(4, 1, 'class="alterCell2" width="35%"');
        $table->setCellAttr(4, 2, 'class="alterCell" width="15%"');
        $table->setCellAttr(4, 3, 'class="alterCell2" width="35%"');
        // print out the table
        echo $table->printTable();
           
                 
           
    }
    if($i==0)
    { 
            echo '<script type="text/javascript">';       
           echo "\n".'alert(\''.gettext('Item has been Return '.$loan_d['item_code']).'\');'."\n";		               
           echo '</script>';           
    }
           
           exit();
   //comment by iresh on 13/1/2011 exit();
}
//else if(!isset($_POST['quickReturnID']))
//{
//    utility::jsAlert("Please Enter The Item Code.");
//}

// add reservation
if (isset($_POST['reserveItemID'])) 
 {
    
    $item_id = trim($_POST['reserveItemID']);
    if (!$item_id) {
        echo '<script type="text/javascript">';
        echo 'alert(\''.gettext('NO DATA Selected to reserve!').'\');';
        echo 'location.href = \'reserve_list.php\';';
        echo '</script>';
        die();
    }
    // get reservation limit from member type
    $reserve_limit_q = $dbs->query('SELECT reserve_limit FROM mst_member_type WHERE member_type_id='.(integer)$_SESSION['memberTypeID']);
    $reserve_limit_d = $reserve_limit_q->fetch_row();
    // get current reservation data for this member
    $current_reserve_q = $dbs->query('SELECT COUNT(reserve_id) FROM reserve WHERE member_id=\''.trim($_SESSION['memberID']).'\'');
    $current_reserve_d = $current_reserve_q->fetch_row();
    if ($current_reserve_d[0] >= $reserve_limit_d[0]) {
        echo '<script type="text/javascript">';
        echo 'alert(\''.gettext('Can not add more reservation. Maximum limit reached').'\');';
        echo 'location.href = \'reserve_list.php\';';
        echo '</script>';
        die();
    }
/*
//added by iresh on 12/1/2011 for reservation checking 
$res_d=$_POST['reserveItemID'];

$m=$_SESSION['memberID'];

$date=date("Y-m-d");

$q="select item_code from reserve where member_id NOT IN($m) AND DATE_FORMAT(reserve_date,'%Y-%m-%d') >='$date'";
$r=$dbs->query($q);
$val_s='';

while($s = $r->fetch_assoc())
{
   $val_s=$s['item_code'].',';
}
$val_s=explode(",",$val_s);

if(in_array($res_d,$val_s))
{
	
	echo '<script type="text/javascript">';
        echo 'alert(\''.__('This Book Already reserve By Another Person').'\');';
        echo 'location.href = \'reserve_list.php\';';

        echo '</script>';
        die();
}


$current1="SELECT item_code FROM reserve WHERE member_id='$m' AND DATE_FORMAT(reserve_date,'%Y-%m-%d') >='$date'";
$current=$dbs->query($current1);
//$current = $dbs->query("SELECT item_code FROM reserve WHERE member_id='$m'");
$current_reserve_val='';
while($current_reserve = $current->fetch_array())
{
    $current_reserve_val=$current_reserve['item_code'].',';
}
$current_reserve_val=explode(",",$current_reserve_val);
if (in_array($res_d,$current_reserve_val))
 {
        echo '<script type="text/javascript">';
        echo 'alert(\''.__('This Book Already reserve by You').'\');';
        echo 'location.href = \'reserve_list.php\';';
        echo '</script>';
        die();
 }
*/



///end added by iresh on 12/1/2011

    // get biblio data for this item
    $biblio_q = $dbs->query('SELECT i.biblio_id, ist.rules FROM biblio AS b
        LEFT JOIN item AS i ON b.biblio_id=i.biblio_id
        LEFT JOIN mst_item_status AS ist ON i.item_status_id=ist.item_status_id
        WHERE i.item_code=\''.$dbs->escape_string($item_id).'\'');
    $biblio_d = $biblio_q->fetch_row();
    // check if this item is forbidden
    if (!empty($biblio_d[1])) {
        $arr_rules = @unserialize($biblio_d[1]);
        if ($arr_rules) {
            if (in_array(NO_LOAN_TRANSACTION, $arr_rules)) {
                echo '<script type="text/javascript">';
                echo 'alert(\''.gettext('Can\'t reserve this Item. Issue Forbidden!').'\');';
                echo 'location.href = \'reserve_list.php\';';
                echo '</script>';
                die();
            }
        }
    }
    // get the availability status
    $avail_q = $dbs->query('SELECT COUNT(l.loan_id) FROM loan AS l
        WHERE l.item_code=\''.$item_id.'\' AND l.loan_date is not null  AND l.return_date is null AND l.member_id!=\''.$_SESSION['memberID'].'\'');
    $avail_d = $avail_q->fetch_row();
    if ($avail_d[0] >= 0) {
        // write log
        utility::writeLogs($dbs, 'tblstudent', $_SESSION['memberID'], 'circulation', $_SESSION['realname'].' reserve item '.$item_id.' for member ('.$_SESSION['memberID'].')');
        // add reservation to database
        $reserve_date = date('Y-m-d H:i:s');
        $dbs->query('INSERT INTO reserve(member_id, biblio_id, item_code, reserve_date) VALUES (\''.$_SESSION['memberID'].'\', \''.$biblio_d[0].'\', \''.$item_id.'\', \''.$reserve_date.'\')');
        echo '<script type="text/javascript">';
        echo 'alert(\''.gettext('Reservation added').'\');';
        echo 'location.href = \'reserve_list.php\';';
        echo '</script>';
    } else {
        echo '<script type="text/javascript">';
        echo 'alert(\''.gettext('Item for this title is already available or already on hold by this member!').'\');';
        echo 'location.href = \'reserve_list.php\';';
        echo '</script>';
    }
exit();
  //comment by iresh on 13/1/2011  exit();
}


// remove reservation item
if (isset($_POST['reserveID']) AND !empty($_POST['reserveID'])) 
{
    
    $reserveID = intval($_POST['reserveID']);
    // get reserve data
    $reserve_q = $dbs->query('SELECT item_code FROM reserve WHERE reserve_id='.$reserveID);
    $reserve_d = $reserve_q->fetch_row();
    // delete reservation record from database
    $dbs->query('DELETE FROM reserve WHERE reserve_id='.$reserveID);
    // write log
    utility::writeLogs($dbs, 'tblstudent', $_SESSION['memberID'], 'circulation', $_SESSION['realname'].' remove reservation for item '.$reserve_d[0].' for member ('.$_SESSION['memberID'].')');
    echo '<script type="text/javascript">';
    echo 'alert(\''.gettext('Reservation removed').'\');';
    echo 'location.href = \'reserve_list.php\';';
    echo '</script>';
exit();
   //comment by iresh on 13/1/2011 exit();
}


// removing fines
if (isset($_POST['removeFines'])) 
{
    
    foreach ($_POST['removeFines'] as $fines_id) {
        $fines_id = intval($fines_id);
        // change loan data
        $dbs->query("DELETE FROM fines WHERE fines_id=$fines_id");
    }
    echo '<script type="text/javascript">';
    echo 'alert(\''.__('Fines data removed').'\');';
    echo 'location.href = \'fines_list.php\';';
    echo '</script>';
    exit();
   
} 
if(isset($_POST['memberID']) OR isset($_SESSION['memberID1'])) 
{ 
     
    if(isset($_SESSION['memberID1'])) 
    {
        $memberID = trim($_SESSION['memberID1']);
    } 
    else
    {             
        $_SESSION['temp_loan'] = array();
        $memberID = trim(preg_replace('@\s*(<.+)$@i', '', $_POST['memberID']));
        
    }
    
  // echo  "Rajesh-". $memberID;
    $member = new member($dbs, $memberID);
   //  echo '<pre>';
   // print_r($member);
   // die;
    if(!$member->valid())
    {        
            echo '<script type="text/javascript">';
            echo 'alert(\''.gettext('Please Enter valid enrollment no!').'\');';
            echo 'location.href = \'index.php?mod=circulation\';';
            echo '</script>';
            exit();
    } 
    else
    {        
     
        // get member information
        $member_type_d = $member->getMemberTypeProp();                
        $_SESSION['memberTypeID'] = $member->member_type_id;                
        $_SESSION['memberID'] = $member->member_id;        
        $_SESSION['reborrowed'] = array();       
        $_SESSION['m_is_expire'] = $member->isExpired();
        $_SESSION['receipt_record'] = array();
                
        $disabled = '';
        $add_style = '';        
        if ($_SESSION['is_expire']) 
        {
            $disabled = ' disabled ';
            $add_style = ' color: #999999; border-color: #CCCCCC;';
        }
        
        echo '<table width="100%" class="border" style="margin-bottom: 5px;" cellpadding="5" cellspacing="0">'."\n";
        echo '<tr>'."\n";
        echo '<td class="dataListHeader" colspan="5">';        
        echo '<form id="finishForm" method="post" target="blindSubmit" action="'.MODULES_WEB_ROOT_DIR.'circulation/circulation_action.php" style="display: inline;"><input type="button" value="'.gettext('Finish Transaction').'" onclick="confSubmit(\'finishForm\', \''.gettext('Are you sure want to finish current transaction?').'\')" /><input type="hidden" name="finish" value="true" /></form>';

        echo '</td>';
        echo '</tr>'."\n";
        echo '<tr>'."\n";
        echo '<td class="alterCell" width="15%"><strong>'.gettext('Member Name').'</strong></td><td class="alterCell2" width="30%">'.$member->member_name.'</td>';
        echo '<td class="alterCell" width="15%"><strong>'.gettext('GR No/Staff ID').'</strong></td><td class="alterCell2" width="30%">'.$member->member_id.'</td>';        
/*        {
            //  if (file_exists(IMAGES_BASE_DIR.'persons/'.$member->member_image)) 
            {
                echo '<td class="alterCell2" valign="top" rowspan="3">';
                //echo '<img src="'.SENAYAN_WEB_ROOT_DIR.'lib/phpthumb/phpThumb.php?src=../../images/persons/'.urlencode($member->member_image).'&w=90" style="border: 1px solid #999999" />';
            //$student_img = $library_student_img . $member->member_image . '.png';
                $student_img = $library_student_img . $member->member_image;// . '.jpg'

                echo '<img src=' . $student_img . ' size=90 height=80>';
                echo '</td>';
            }
        }
*/       {
            //  if (file_exists(IMAGES_BASE_DIR.'persons/'.$member->member_image)) 
            {
                echo '<td class="alterCell2" valign="top" rowspan="3">';
                //echo '<img src="'.SENAYAN_WEB_ROOT_DIR.'lib/phpthumb/phpThumb.php?src=../../images/persons/'.urlencode($member->member_image).'&w=90" style="border: 1px solid #999999" />';
            //$student_img = $library_student_img . $member->member_image . '.png';
                $student_img = $library_student_img . $member->member_image;// . '.jpg'

                echo '<img src=' . $student_img . ' size=90 height=100>';
                echo '</td>';
            }
        }   
        echo '</tr>'."\n";
        echo '<tr>'."\n";
        echo '<td class="alterCell" width="15%"><strong>'.gettext('Std-Div').'</strong></td><td class="alterCell2" width="30%">'.$member->member_standard.'</td>';
		echo '<td class="alterCell" width="15%"><strong>'.gettext('Member Mobile').'</strong></td><td class="alterCell2" width="30%">'.$member->member_mobile.'</td>';
        echo '</tr>'."\n";
/*        echo '<tr>'."\n";
//        echo '<td class="alterCell" width="15%"><strong>'.__('Register Date').'</strong></td><td class="alterCell2" width="30%">'.date("d-m-Y", strtotime($member->register_date)).'</td>';

        // give notification about expired membership and pending
        $expire_msg = '';
        if ($_SESSION['is_expire']) 
        {
            $expire_msg .= '<font style="color: #FF0000;">('.__('Membership Already Expired').')</font>';
        }
        echo '<td class="alterCell" width="15%"><strong>'.__('Expiry Date').'</strong></td><td class="alterCell2" width="30%">'.date("d-m-Y", strtotime($member->expire_date)).' '.$expire_msg.'</td>';
        echo '</tr>'."\n";
*/		
        echo '</table>'."\n";
        echo '<input  type="button" style="width: 19%;'.$add_style.'" class="tab tabSelected" value="'.gettext('Issue').'" onclick="setIframeContent(\'listsFrame\', \''.MODULES_WEB_ROOT_DIR.'circulation/loan.php\'); setTabClass(this);"/>';
        echo '<input type="button" style="width: 19%;" class="tab" value="'.gettext('Current Issue').'" onclick="setIframeContent(\'listsFrame\', \''.MODULES_WEB_ROOT_DIR.'circulation/loan_list.php\'); setTabClass(this);" '.$disabled.'  />';
        if ($member_type_d['enable_reserve'])
        {
           // echo '<input type="button" style="width: 19%;'.$add_style.'" class="tab" value="'.__('Reserve').'" onclick="setIframeContent(\'listsFrame\', \''.MODULES_WEB_ROOT_DIR.'circulation/reserve_list.php\'); setTabClass(this);" '.$disabled.' />';
        }
        echo '<input type="button" style="width: 19%;" class="tab" value="'.gettext('Fines').'" onclick="setIframeContent(\'listsFrame\', \''.MODULES_WEB_ROOT_DIR.'circulation/fines_list.php\'); setTabClass(this);" />';
        echo '<input type="button" style="width: 19%;" class="tab" value="'.gettext('Member History').'" onclick="setIframeContent(\'listsFrame\', \''.MODULES_WEB_ROOT_DIR.'circulation/member_loan_hist.php\'); setTabClass(this);" /><br />'."\n";
  // echo '<input type="button" style="width: 19%;" class="tab" value="'.__('Member Book Request').'" onclick="setIframeContent(\'listsFrame\', \''.MODULES_WEB_ROOT_DIR.'circulation/member_book_request_list.php\'); setTabClass(this);" /><br />'."\n";
      
        echo '<iframe src="modules/circulation/loan.php" id="listsFrame" class="border" style="width: 100%; height: 300px;"></iframe>'."\n";
        
        echo '<div class="objectDragger" id="iframeDragger">&nbsp;</div>'."\n";
        echo '<script type="text/javascript">registerDraggerEvent(\'iframeDragger\', \'listsFrame\');</script>'."\n";

//        echo '</form>';
        
    }
exit();
    //comment by iresh on 13/1/2011 exit();
}


?>
