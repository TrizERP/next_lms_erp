<?php
/**
 * Copyright (C) 2007,2008  Arie Nugraha (dicarve@yahoo.com)
 * Some ajax security patches by Hendro Wicaksono (hendrowicaksono@yahoo.com)
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

/* Bibliographic items listing */

// required file
require '../../sysconfig.inc.php';
if (isset($_POST['ajaxsec_user'])) {
  $ajaxsec_user = $_POST['ajaxsec_user'];
}

if (isset($_POST['ajaxsec_passwd'])) {
    $ajaxsec_passwd = $_POST['ajaxsec_passwd'];
}

if (($ajaxsec_user == $sysconf['ajaxsec_user']) AND ($ajaxsec_passwd == $sysconf['ajaxsec_passwd']))
 {
	
    if ($sysconf['ajaxsec_ip_enabled'] == '1') {
        if ($_SERVER['SERVER_ADDR'] == $sysconf['ajaxsec_ip_allowed']) {
            die();
        }
 }
  if (isset($_POST['id']))
 {	

        $id = intval($_POST['id']);
	$_SESSION['id']=$_REQUEST['id'];//added by iresh on 18-2-2011
	$t="select count(i.item_code) AS item_code,b.title AS title from item i

		     left join biblio b on b.biblio_id=i.biblio_id
                     where i.biblio_id='$id'";

	$t=$dbs->query($t);
	$tt='';
	while($row=$t->fetch_assoc())
	{
		 $tt.=$row['item_code'];
		
	}

if($tt>0)
{
	$total_item="select count(i.item_code) AS item_code,b.title AS title from item i
		     left join biblio b on b.biblio_id=i.biblio_id
                     where i.biblio_id='$id'";
	$total_item=$dbs->query($total_item);
	$total1='';
	/*while($total=$total_item->fetch_assoc())
	{
		//$total1.=$total['item_code'];
		//$total1.=$total['title'];
	}*/
	
	$available_item="select count(i.item_code) AS item_code from item i
			 left join loan l on l.item_code=i.item_code
                         where i.biblio_id='$id' AND l.is_return=0";
	
	$available_item=$dbs->query($available_item);
	$available_item1='';
	while($available=$available_item->fetch_assoc())
	{

		$available_item1.=$available['item_code'];

	}

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

                	/*$select="select t.item_code AS item_code,t.member_id,t.confirm_date from temp t
				 left join item i on i.item_code=t.item_code
				 left join biblio b on b.biblio_id=i.biblio_id
			 	where status = 'Confirm' AND b.biblio_id='$id'";
			$select=$dbs->query($select);
			$select_item='';
			$select_member='';
			$select_confirm_date='';
			while($select1=$select->fetch_assoc())
			{
				 $select_item.=$select1['item_code'].',';
				 $select_member.=$select1['member_id'].',';
				 $select_confirm_date.="'".str_replace("\'","''",$select1['confirm_date'])."',";
			}
			$select_item=substr($select_item,0,-1);
			$select_member=substr($select_member,0,-1);
			$select_confirm_date1=substr($select_confirm_date,0,-1);
			
		       $select_loan="select count(item_code) AS item_code from loan where item_code IN($select_item) AND member_id IN ($select_member) AND loan_date IN 				                          ($select_confirm_date1)";
			$select_loan1=$dbs->query($select_loan);
			$select_item_loan_rows='';
		        while($select_loan_rows=$select_loan1->fetch_assoc())
			{
				$select_item_loan_rows.=$select_loan_rows['item_code'];
			}

			*/







/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


	 /*   $availabel_item_request="select count(t.item_code) AS item_code from temp t
				 left join item i on i.item_code=t.item_code
			         where i.biblio_id='$id' AND t.status='confirm'";
	     $availabel_item_request=$dbs->query($availabel_item_request);
	     $available_request1='';
	while($available_request= $availabel_item_request->fetch_assoc())
	{
		$available_request1.=$available_request['item_code'];
	}*/
	
	echo "<form  method=post>";
	echo "<table cellspacing=10 width=100%>";
	echo "<tr>";
	//echo "<td align=center>Book Title </td><td align=center>Total Copies</td><td align=center>Available Copies</td><td align=center>Book Status</td><td align=center>Book Request</td>";
echo "<td align=center>Book Title </td><td align=center>Total Copies</td><td align=center>Available Copies</td><td align=center>Book Status</td>";
          
	echo "</tr>";

	while($total=$total_item->fetch_assoc())
	{
		
		$available['item_code']=($total['item_code']-$available_item1);
		
		//echo $available['item_code']=$available['item_code']-$select_item_loan_rows;
		
		
		echo "<tr>";

		//echo "<td><input type=checkbox name=values[$loan_list_data[temp_id]] id=checkbox[] value=". $loan_list_data['temp_id']." ></td>"; 
		echo "<td align=center>" . $total['title'] ."</td>";
		echo "<td align=center>" .$total['item_code'] ."</td>";
		/*if($select_item_loan_rows>=1)
		{*/               
			 echo "<td align=center>" .$available['item_code'] ."</td>";
		//}
		/*else if($select_item_loan_rows==0)
		{	
			$select_temp="select item_code from temp where item_code IN ($select_item) AND member_id IN ($select_member) AND Confirm_date IN 				                          ($select_confirm_date1)";
			$select_temp_item=$dbs->query($select_temp);
			$select_item_temp_rows='';		
			while($temp_rows=$select_temp_item->fetch_assoc())
			{
				$select_item_temp_rows.=$temp_rows['item_code'];
			}
			//$available['item_code']=$available['item_code']+$select_item_temp_rows;
			echo "<td align=center>" .$available['item_code'] ."</td>";	

		}*/
		if($available['item_code']>0)
		{
		      
			
			echo '<td style="color: navy;" align=center>Available</td>';

		}
		
		else 
		{
			  $due_item="select min(l.due_date) AS due_date from loan l
					  left join item i on i.item_code=l.item_code
					  left join biblio b on b.biblio_id=i.biblio_id
					  where i.biblio_id='$id'";
				$due_item=$dbs->query($due_item);
			$due1='';
			while($due=$due_item->fetch_assoc())
			{
				 $due1.=$due['due_date'];
			}
			  // echo "<td>".gettext('Currently On Loan (Due on').$due1."</td>";
			echo '<td><strong width="50%" style="color: red;">'.gettext('All Copy Currently On Loan (One Of The Copy Due on').date($sysconf['date_format'], strtotime( $due1)).')</strong></td>'; 

		}
		
	
	$sql="select item_code from item where biblio_id='$id' AND item_code NOT IN (select item_code from loan where biblio_id='$id' AND is_return=0) limit 0,1";
	$sql=$dbs->query($sql);
	$s1='';
	while($s=$sql->fetch_assoc())
	{
	        $s1.=$s['item_code'];
	}
	if($available['item_code']>0)
	{      
		//echo '<td align=center><input type=checkbox name="item" value='.$s1.'></td>'; 
	
	}
	else
	{	
	         $item_loan="select l.item_code AS item_code from loan l
			    left join item i on i.item_code=l.item_code
			    left join biblio b on b.biblio_id=i.biblio_id
		 	    where l.due_date ='$due1' AND i.biblio_id='$id' ";
		$item_loan=$dbs->query($item_loan);
		$item_l='';
		while($item_loan1=$item_loan->fetch_assoc())
		{
			$item_l.=$item_loan1['item_code'];
			
		}
		
		 
		//echo '<td><input type=checkbox name="item" value='.$item_l.'></td>'; 
		
	}
	
        echo "</tr>";

	}
 
     //  echo '<tr><td><td align=center><input type=submit name=submit value="Book Request"><center></td></tr>';
	
echo "</table>";
	echo "</form>";	
}
else
{
	// echo '<strong style="color: red; font-weight: bold;">'.gettext('No Copy Available For This Book').'</strong>';
}


    }
}

?>
