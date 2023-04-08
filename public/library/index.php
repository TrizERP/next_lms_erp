<?php

session_start();
error_reporting(0);
?>
<?
if(!isset($_SESSION['DUSER_NAME']))
{ 
   header("location:../../dashboard.php"); // Redirect to login.php page
}
if(isset($_REQUEST['logout']))
{
      session_destroy();
      header('Location:logout.php');
}
?>


<?php 
if($_SESSION['USER_GROUP_ID']==2)
{
    
}
if(empty($_POST['logMeIn']))
{
    
    $id = $_SERVER['REMOTE_ADDR']; 
    $id = $id.".php";
  
        if((file_exists($id) || $_GET['lms']=='set')  && $_REQUEST['admin_logged_in']==1)
        {

           header("Location:http://".$_SERVER["HTTP_HOST"]."/school_inte/Products/library/admin/index.php");

        }    
        elseif((file_exists($id) || $_GET['lms']=='set')  && $_REQUEST['admin_logged_in']!=1)
        {

          header("Location:http://".$_SERVER["HTTP_HOST"]."/school_inte/Products/library/admin/logout.php"); 
        }
}
else
{
    
}

if(!empty($_POST['logMeIn']) || isset($_GET['lms']))
{
    
    if($_POST['database']=="--select--")
    {

                header('Location: index.php');

    }
    else
    {
                $id = $_SERVER['REMOTE_ADDR']; 
                $id = $id.".php";            
                $fh = fopen($id,"w");
                $string = "<?php define('DB_HOST', 'trizapps.in');\n";
                $string.= "define('DB_PORT', '3306');\n";
                $string.= "define('DB_NAME', '$_POST[database]');\n";
                $set_repo = "repository/".$_POST['database'];
                $string.="define('REPO_DIR', '$set_repo');\n";
                $string.= "define('DB_NAME', 'trizino_slibrary');\n";
                $string.= "define('DB_USERNAME', 'dev_db');\n";
                $string.= "define('DB_PASSWORD', 'dev@sql'); ?>";
    }
}

require 'sysconfig.inc.php';
//require LIB_DIR.'member_session.inc.php';
//require LIB_DIR.'contents/member.inc.php';





if(isset($_SESSION["HOD"]))
{           
    $hod=explode(",",$_SESSION["HOD"]);
    if(in_array("113", $hod))       
        $_SESSION["USER_GROUP_ID"]=1;               
}

//include './setsession.php';


if($_REQUEST["mod"])
    $_SESSION["mod"]=$_REQUEST["mod"];  
else
    unset($_SESSION["mod"]);

if($_REQUEST["page"])
    $_SESSION["page"]=$_REQUEST["page"];
else
    unset($_SESSION["page"]);

//$_SESSION['material_id']=$_REQUEST['material_sub_type_select'];

//$_SESSION['advance_sub_value']=$_REQUEST['mst_sub'];


if ($sysconf['template']['base'] == 'html')
{
    require SIMBIO_BASE_DIR.'simbio_GUI/template_parser/simbio_template_parser.inc.php';

    
}
//print_r($_SESSION);
$page_title = $sysconf['library_name'].' | '.$sysconf['library_subname'].' :: OPAC';
$total_pages = 1;
$header_info = '';
$metadata = '';



ob_start();
require LIB_DIR.'contents/common.inc.php';

if(isset($_REQUEST['checkbox'])!='' && $_REQUEST['p']=='book_request')
{
   
    if(isset($_REQUEST['submit1']))
    { 
            
	$count=$_REQUEST['checkbox'];
	$v='';
	if($count!=0)
	{
		foreach($count as $value)
		{
			$v.=$value.',';
		}
        }
        $del=substr($v,0,-1);	 
        
        echo '<script>';
        echo 'var r=confirm("Press a button!")';
        echo 'alert(r)';
        echo '</script>';
        
        
        $sql = "DELETE FROM temp_request WHERE temp_id IN($del)";                                 	                
        $result =$dbs->query($sql);                
        if($result)
        {
            utility::jsAlert('Request has been Deleted!');            
        }                                    
        unset($del);
        }
        
		
    
 }
 else if(!isset($_REQUEST['checkbox']) && isset($_REQUEST['submit1']))
 {	
            utility::jsAlert('Please Select The Record First!');            
 }


     
if(isset($_REQUEST['myself']))
{ 


            $sql1="select * from biblio_myself where member_id='".$_SESSION['DUSER_ID']."' LIMIT 10";      
            $check1 = $dbs->query($sql1);	
            $check2 = $check1->fetch_assoc();

               if($check2['biblio_id'] == ($_GET['myself']))
                {
                   utility::jsAlert("You Have Already Added This!");
                }
                else
                {
                    //utility::jsAlert($_GET['myself']);
                $last_update = date('Y-m-d H:i:s');
                $sql="INSERT INTO biblio_myself(member_id,biblio_id,last_update) values('".$_SESSION['DUSER_ID']."','".$_GET['myself']."','".$last_update."')";
                        $check = $dbs->query($sql);	

                        if($check>0)
                                {
                                        utility::jsAlert('Added To My Self Successfully');
                                }
                        else
                                {
                                        utility::jsAlert('Failed To Add in My Self');
                                }
                }
}
    
     
if (isset($_GET['p'])  )
{                
          $path = trim($_GET['p']);
          
          $path = preg_replace('@^(http|https|ftp|sftp|file|smb):@i', '', $path);    
            //echo $path;die;
            if (file_exists(LIB_DIR.'contents/'.$path.'.inc.php')) 
            { 
                 //echo LIB_DIR.'contents/'.$path.'.inc.php';die;
                   //echo LIB_DIR.'contents/'.$path.'.inc.php';                
                    include LIB_DIR.'contents/'.$path.'.inc.php';                 

                    if ($path != 'show_detail') 
                    {                 
                            
                            $metadata = '<meta name="robots" content="noindex, follow">';
                    }
            }
            else
            {  
                      
                        $metadata = '<meta name="robots" content="noindex, follow">';                                
                        echo '<div class="contentDesc" align=center><iframe src=Studenthelp.pdf height=1000px width=900px align=center></iframe></div>';
            }
            
  }
else if(isset($_GET['letter']) )
{

    $metadata = '<meta name="robots" content="noindex, follow">';    
   if (!isset($_GET['p']) && !isset($_GET['letter'])) 
   {
       
        if ((!isset($_GET['keywords'])) AND (!isset($_GET['page'])) AND (!isset($_GET['title'])) AND (!isset($_GET['author'])) AND (!isset($_GET['subject'])) AND (!isset($_GET['location']))) 
            {                        
                include LIB_DIR.'content.inc.php';
                $content = new content();
                $content_data = $content->get($dbs, 'headerinfo');
            if ($content_data) 
            {              
                unset($content_data);
            }
        }
    }    
    include LIB_DIR.'contents/default_alpha.inc.php';
    //include LIB_DIR.'contents/default.inc.php';
}
else if(isset($_GET['subject_search'])) 
{
    
    $metadata = '<meta name="robots" content="noindex, follow">'; 
   if (!isset($_GET['p'])) {
        if ((!isset($_GET['keywords'])) AND (!isset($_GET['page'])) AND (!isset($_GET['title'])) AND (!isset($_GET['author'])) AND (!isset($_GET['subject'])) AND (!isset($_GET['location']))) 
            {            
                include LIB_DIR.'content.inc.php';
                $content = new content();
                $content_data = $content->get($dbs, 'headerinfo');
            if ($content_data) 
             {             
                unset($content_data);
              }
        }
    }
     
    include LIB_DIR.'contents/default_subject_search.inc.php';
}
else if(isset($_GET['gmd_search']))
{
    $metadata = '<meta name="robots" content="noindex, follow">';
    // homepage header info
   if (!isset($_GET['p'])) {
        if ((!isset($_GET['keywords'])) AND (!isset($_GET['page'])) AND (!isset($_GET['title'])) AND (!isset($_GET['author'])) AND (!isset($_GET['subject'])) AND (!isset($_GET['location']))) {
            // get content data from database
            include LIB_DIR.'content.inc.php';
            $content = new content();
            $content_data = $content->get($dbs, 'headerinfo');
            if ($content_data) {
               // $header_info .= '<div id="headerInfo">'.$content_data['Content'].'</div>';
                unset($content_data);
            }
        }
    }

     
    include LIB_DIR.'contents/default_gmd_search.inc.php';
}
else 
{
   
  $metadata = '<meta name="robots" content="noindex, follow">';
    // homepage header info
   if (!isset($_GET['p'])) 
   {       
     
        if ((!isset($_GET['keywords'])) AND (!isset($_GET['page'])) AND (!isset($_GET['title'])) AND (!isset($_GET['author'])) AND (!isset($_GET['subject'])) AND (!isset($_GET['location']))) 
            {            
                
                include LIB_DIR.'content.inc.php';
             
                $content = new content();
                $content_data = $content->get($dbs, 'headerinfo');
          
                    if ($content_data) 
                    {     
                        unset($content_data);
                    }
            }
               
    }
    if($_SESSION['DUSER_ID']!='')
    {
      
        
            if(isset($_REQUEST['myself']))
            { 
                
                
                $sql1="select * from biblio_myself where member_id='".$_SESSION['DUSER_ID']."' LIMIT 10";
                
                $check1 = $dbs->query($sql1);	
                $check2 = $check1->fetch_assoc();


                   if($check2>0)
                    {
                    }
                    else
                    {
                    $last_update = date('Y-m-d H:i:s');
                    $sql="INSERT INTO biblio_myself(member_id,biblio_id,last_update) values('".$_SESSION['DUSER_ID']."','".$_GET['myself']."','".$last_update."')";
                            $check = $dbs->query($sql);	
                           
                            if($check>0)
                                    {
                                            utility::jsAlert('Added To My Self Successfully');
                                    }
                            else
                                    {
                                            utility::jsAlert('Failed To Add in My Self');
                                    }
                    }
          }
    }    
      include LIB_DIR.'contents/default.inc.php';
      
      
    
}

// main content grab
$main_content = ob_get_clean();

// template output
if ($sysconf['template']['base'] == 'html') 
{       

// create the template object
    $template = new simbio_template_parser($sysconf['template']['dir'].'/'.$sysconf['template']['theme'].'/index_template.html');
    // assign content to markers
    $template->assign('<!--PAGE_TITLE-->', $page_title);
    $template->assign('<!--CSS-->', $sysconf['template']['css']);
    $template->assign('<!--INFO-->', $info);
    $template->assign('<!--LIBRARY_NAME-->', $sysconf['library_name']);
    $template->assign('<!--LIBRARY_SUBNAME-->', $sysconf['library_subname']);
    $template->assign('<!--GMD_LIST-->', $gmd_list);
    $template->assign('<!--COLLTYPE_LIST-->', $colltype_list);
    $template->assign('<!--LOCATION_LIST-->', $location_list);
    $template->assign('<!--LANGUAGE_SELECT-->', $language_select);
    $template->assign('<!--ADVSEARCH_AUTHOR-->', $advsearch_author);
    $template->assign('<!--ADVSEARCH_TOPIC-->', $advsearch_topic);
    $template->assign('<!--HEADER_INFO-->', $header_info);
    $template->assign('<!--MAIN_CONTENT-->', $main_content);
    if ($metadata) { $template->assign('<!--METADATA-->', $metadata); }
    // print out the template
    $template->printOut();
} 
else if ($sysconf['template']['base'] == 'php') 
 {
    
    $_SESSION['material_id']=$_REQUEST['material_sub_type_select'];
  
    
    
           //$_SESSION['start'] = time();
        //$_SESSION['expire'] = $_SESSION['start']; //+ (2 * 60);
       // echo $sysconf['template']['dir'].'/'.$sysconf['template']['theme'].'/index_template.inc.php';
      require $sysconf['template']['dir'].'/'.$sysconf['template']['theme'].'/index_template.inc.php';
        
}
///////////////////////////    added by iresh on 16-2-2011 ////////////////////////////////////////////
/* if($_SESSION['DUSER_ID']!='')
{
 if(isset($_REQUEST['submit'])=='Book Request')
	    {

			//echo "<pre>";
			//print_r($_REQUEST);
		        $request_date1=date("Y-m-d");
                        $m=$_SESSION['DUSER_ID'];
			$book_request_value=$_REQUEST['values']['$item'];			 
			//$sql='SELECT biblio_id FROM item where item_code='.$book_request_value.'';                        
			//$sql1=$dbs->query($sql);
			//while($sql2=$sql1->fetch_assoc())
			//{
			//	$sql3.=$sql2['biblio_id'];
			//}
				
//////////////////////////////////////////// condition for checking book request is already made by member itself////////////////////////////////////////
			
	                $request="SELECT item_code from temp where member_id='$m' AND DATE_FORMAT(request_date,'%Y-%m-%d') >='$request_date1'";
			$request1=$dbs->query($request);
			$request_val='';

			while($req = $request1->fetch_assoc())
			{
			 $request_val.=$req['item_code'].',';
			}
			$request_val1=explode(",",$request_val);
			
              
			if(in_array($book_request_value,$request_val1))
			{
	
				echo '<script type="text/javascript">';
				echo 'alert(\''.__('The Request For This Book Is Already Made By You').'\');';
				//echo "return false;";
				//echo 'location.href = \'index.php?p=show_detail&id=477'\';';

				echo '</script>';
				die();
			}

//////////////////////////////////////// end of condition/////////////////////////////////////////////////////////////////////////////////////////////////

////////////////////////////// condition for checking book request is already made by member itself /////////////////////////////////////////////////////////
                    
		        $request_other_mamber="SELECT item_code from temp where member_id NOT IN($m) AND DATE_FORMAT(request_date,'%Y-%m-%d') >='$request_date1'";
			$request_other_mamber=$dbs->query($request_other_mamber);
			$request_other_member_val='';

			while($req_other_member = $request_other_mamber->fetch_assoc())
			{
			 $request_other_member_val.=$req_other_member['item_code'].',';
			}
			
		
		        $request_other_member_val=explode(",", $request_other_member_val);
			            
			if(in_array($book_request_value, $request_other_member_val))
			{
	
				echo '<script type="text/javascript">';
				echo 'alert(\''.__('The Request For This Book Is Already Made By Other Member').'\');';
				//echo 'location.href = \'item_list.php'\';';

				echo '</script>';
				die();
			}



/////////////////////////////////////////////////end of condition ///////////////////////////////////////////////////////////////////////////////////////////


		
			$request_date.= "'".str_replace("\'","''",$request_date1)."'";
			$fields =$values ='';
			foreach($_REQUEST['values'] as $column=>$value)
			{


			$fields .=$column.",";	

			$values .= "'".str_replace("\'","''",$value)."',";

			}
	
		       $values=substr($values,0,-1);


			if($values)
			{

		          $sql='INSERT INTO temp(item_code,member_id,request_date) values('.$values.','.$_SESSION['DUSER_ID'].','.$request_date.')';
			  $dbs->query($sql);
			}
			 echo '<script type="text/javascript">';
	      	         echo 'alert(\''.__('Your Book Request Sucessfully Submited').'\');';
		         echo 'location.href = \'index.php\';';
		         echo '</script>';

		
		  

		
	    }	
}*/
///////////end by iresh//////////////////////////////////////////////////////////////


if($_SESSION['DUSER_ID']!='')
{	
    
	if(isset($_REQUEST['submit'])=='book request')
	{
           

		$currentdate="'".str_replace("\'","''",date("Y-m-d"))."'";
		$item=$_REQUEST['item'];
		$bib='select biblio_id from item where item_code='.$item.'';
		$bib1=$dbs->query($bib);
		$b='';
		while($row=$bib1->fetch_assoc())
		{
			 $b.=$row['biblio_id'];

		}
		$sql_item="select item_code from temp_request where member_id='".$_SESSION['DUSER_ID']."' AND  request_date='".$currentdate."'";
		$sql1=$dbs->query($sql_item);
		$s='';
		while($row=$sql1->fetch_assoc())
		{
			$s.=$row['item_code'].',';
		}
		$s=substr($s,0,-1);
		if($s!='')
		{

		
	
		        $sql_bib="select biblio_id from item where item_code IN ($s)";
			$sql_bib1=$dbs->query($sql_bib);
			$s_b='';
			while($row=$sql_bib1->fetch_assoc())
			{
				$s_b.=$row['biblio_id'].',';
			}
			$s_b=substr($s_b,0,-1);
	
			$explode=explode(",",$s_b);
			//print_r($explode);
		
			if(in_array($b,$explode))
			{
				if($item!='')
				{

					echo '<script type="text/javascript">';
			      		echo 'alert(\''.__('You Cannot Make More Than One Request For Same Book').'\');';
					echo 'location.href = \'index.php?material_sub_type_select=SELECT&q=&Search=Keyword+Search&search1=Search&p=book_request\';';
                                        echo '</script>';
					die();
			       }
			}
		}
                        $request_date1=date("Y-m-d");
                        $request_date.= "'".str_replace("\'","''",$request_date1)."'";
                        $m=$_SESSION['DUSER_ID'];	
                        $book_request_value=$_REQUEST['item'];
                        $t=date('H:i:s');

                        
                        

                        $sql="INSERT INTO temp_request(item_code,member_id,request_date,time) values('".$book_request_value."','".$_SESSION['DUSER_ID']."',".$request_date.",'".$t."')";
                        
                        $dbs->query($sql);
		if($item!='')
		{
			echo '<script type="text/javascript">';
		      	echo 'alert(\''.__('Your Book Request Sucessfully Submited').'\');';
			echo 'location.href = \'index.php?material_sub_type_select=SELECT&q=&Search=Keyword+Search&search1=Search&p=book_request\';';

                        //echo 'location.href = \'index.php\';';
                        echo '</script>';
		}
			
        }


        
if(isset($_REQUEST['sub']) || $_REQUEST['sub']=='Add To Cart')
{        
    
    
   $temp = $_REQUEST['item_code'];
   $bi=$_REQUEST['biblio_id'];
   $user_type=$_SESSION['USER_GROUP_ID'];
   
 
   
   if ($temp==0)
   {
       utility::jsAlert('Book is not Available!');
       exit();
   }      
   
   $bib="SELECT biblio_id FROM item WHERE item_code=$temp";                                  
   
   $bib1=$dbs->query($bib);
   $bib2=$bib1->fetch_row();
   $bib3=$bib2[0];
   
   
   $data_material_sub_id="SELECT material_sub_id FROM biblio WHERE biblio_id=$bib3";   
   
   $data_material_sub_id1=$dbs->query($data_material_sub_id);
   $data_material_sub_id2=$data_material_sub_id1->fetch_row();
   $material_sub_id=intval($data_material_sub_id2[0]);
   

               // $loan_item="select item_code from loan where member_id='".$_SESSION['DUSER_ID']."' AND is_return=0";                		
               $loan_item="select item_code from loan where member_id='".$_SESSION['DUSER_ID']."' AND return_date is null";                		
                $loan_item=$dbs->query($loan_item);
		$l_item='';
		while($row=$loan_item->fetch_assoc())
		{

			$l_item.=$row['item_code'].',';

		}
	
		$l_item=substr($l_item,0,-1);
            
       

		if($l_item!='')
		{                                        
                    $loan_item_bib_id="select biblio_id from item where item_code IN ($l_item)";			
                    
                    
                        $loan_item_bib_id=$dbs->query($loan_item_bib_id);
			$loan_item_b_id='';
			while($row=$loan_item_bib_id->fetch_assoc())
			{

				$loan_item_b_id.=$row['biblio_id'].',';
			}

			 $explode_loan_item_b_id=explode(",",$loan_item_b_id);
                          
			if(in_array($bib3,$explode_loan_item_b_id))
			{


				echo '<script type="text/javascript">';
			      	echo 'alert(\''.__('Your Cannot Make Book Request Because You Currently Having Same Book On Loan ').'\');';
				echo 'location.href = \'index.php?material_sub_type_select=SELECT&q=&Search=Keyword+Search&search1=Search\';';
                                echo '</script>';
				echo die();

			}
			else
			{
                            
                            
				$currentdate="'".str_replace("\'","''",date("Y-m-d"))."'";
				//$item=$_REQUEST['sub'];
                                $item=$_REQUEST['item_code'];                               
				$bib='select biblio_id from item where item_code='.$item.'';
                                
				$bib1=$dbs->query($bib);
				$b='';
				while($row=$bib1->fetch_assoc())
				{
					 $b.=$row['biblio_id'];

				}
				//$sql_item="select item_code from temp_request where member_id='".$_SESSION['DUSER_ID']."' AND status='Pending' AND request_date=$currentdate";
                                $sql_item="select item_code from temp_request where member_id='".$_SESSION['DUSER_ID']."' AND request_date=".$currentdate." AND temp_id NOT IN (select temp_id from loan where temp_id !=0 )";
                                
				$sql1=$dbs->query($sql_item);
				$s='';
				while($row=$sql1->fetch_assoc())
				{
					$s.=$row['item_code'].',';
				}
				$s=substr($s,0,-1);
				if($s!='')
				{

		
	
					$sql_bib="select biblio_id from item where item_code IN ($s)";
                                        
					$sql_bib1=$dbs->query($sql_bib);
					$s_b='';
					while($row=$sql_bib1->fetch_assoc())
					{
						$s_b.=$row['biblio_id'].',';
					}
					$s_b=substr($s_b,0,-1);
	
					$explode=explode(",",$s_b);
					//print_r($explode);
		                                        
					if(in_array($b,$explode))
					{
						if($item!='')
						{

							echo '<script type="text/javascript">';
					      		echo 'alert(\''.__('You Cannot Make More Than One Request For Same Book').'\');';
							echo 'location.href = \'index.php?material_sub_type_select=SELECT&q=&Search=Keyword+Search&search1=Search\';';
                                                        //echo 'location.href = \'index.php\';';
							echo '</script>';
							die();
					       }
					}
				}
				$request_date1=date("Y-m-d");
				$request_date.= "'".str_replace("\'","''",$request_date1)."'";
				
                                $book_request_value=$_REQUEST['item_code'];
                                $t=date('H:i:s');
                                
                                $sql="INSERT INTO temp_request(item_code,member_id,request_date,req_time,user_type) values('".$temp."','".$_SESSION['DUSER_ID']."',".$request_date.",'".$t."','".$user_type."')";                	                                        
				$dbs->query($sql);
				if($item!='')
                                {
                                        echo '<script type="text/javascript">';
                                        echo 'alert(\''.__('Your Book Request Sucessfully Submited').'\');';			
                                        echo 'location.href = \'index.php?material_sub_type_select=SELECT&q=&Search=Keyword+Search&search1=Search\';';
                                        echo '</script>';
                                }

			}


		}

		else
		{                                                             
		$currentdate="'".str_replace("\'","''",date("Y-m-d"))."'";
		//$item=$_REQUEST['sub'];
                $item=$_REQUEST['item_code'];
		$bib='select biblio_id from item where item_code='.$item.'';
		$bib1=$dbs->query($bib);
		$b='';
		while($row=$bib1->fetch_assoc())
		{
			 $b.=$row['biblio_id'];

		}
		$sql_item="select item_code from temp_request where member_id='".$_SESSION['DUSER_ID']."' AND request_date=".$currentdate." AND temp_id NOT IN (select temp_id from loan where temp_id !=0 )";                
                
		$sql1=$dbs->query($sql_item);
		$s='';
		while($row=$sql1->fetch_assoc())
		{
			$s.=$row['item_code'].',';
		}
		$s=substr($s,0,-1);
		if($s!='')
		{	
	
		        $sql_bib="select biblio_id from item where item_code IN ($s)";
			$sql_bib1=$dbs->query($sql_bib);
			$s_b='';
			while($row=$sql_bib1->fetch_assoc())
			{
				$s_b.=$row['biblio_id'].',';
			}
			$s_b=substr($s_b,0,-1);
	
			$explode=explode(",",$s_b);
		//	print_r($explode);die;
		
			if(in_array($b,$explode))
			{
				if($item!='')
				{

					echo '<script type="text/javascript">';
			      		echo 'alert(\''.__('You Cannot Make More Than One Request For Same Book').'\');';
					//echo 'location.href = \'index.php?title=&author=&subject=&isbn=&gmd=0&colltype=0&location=0&search=Search\';';
                                        echo 'location.href = \'index.php?material_sub_type_select=SELECT&q=&Search=Keyword+Search&search1=Search\';';
					echo '</script>';
					die();
			       }
			}
		}
		$request_date1=date("Y-m-d");
		$request_date.= "'".str_replace("\'","''",$request_date1)."'";
               // $m=$_SESSION['DUSER_ID'];	
	        //$book_request_value=$_REQUEST['sub'];               
                $book_request_value=$_REQUEST['item_code'];               
                $t=date('H:i:s');
                
                
                $sql="INSERT INTO temp_request(item_code,member_id,request_date,req_time,user_type) values('".$temp."','".$_SESSION['DUSER_ID']."',".$request_date.",'".$t."','".$user_type."')";                	        
                $dbs->query($sql);
		
                if($item!='')
		{
			echo '<script type="text/javascript">';
		      	echo 'alert(\''.__('Your Book Request Sucessfully Submited').'\');';			
                        echo 'location.href = \'index.php?material_sub_type_select=SELECT&q=&Search=Keyword+Search&search1=Search\';';
			echo '</script>';
		}
			
        }
	
}


}


?>