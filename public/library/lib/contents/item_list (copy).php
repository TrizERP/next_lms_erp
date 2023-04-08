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

if (($ajaxsec_user == $sysconf['ajaxsec_user']) AND ($ajaxsec_passwd == $sysconf['ajaxsec_passwd'])) {
    if ($sysconf['ajaxsec_ip_enabled'] == '1') {
        if ($_SERVER['SERVER_ADDR'] == $sysconf['ajaxsec_ip_allowed']) {
            die();
        }
    }
    if (isset($_POST['id'])) {
        $id = intval($_POST['id']);
	$_SESSION['id']=$_REQUEST['id'];//added by iresh on 18-2-2011
        $copy_q = $dbs->query('SELECT i.item_code, loc.location_name, stat.*, i.site FROM item AS i
            LEFT JOIN mst_item_status AS stat ON i.item_status_id=stat.item_status_id
            LEFT JOIN mst_location AS loc ON i.location_id=loc.location_id
            WHERE i.biblio_id='.$id);
        if ($copy_q->num_rows < 1) {
            echo '<strong style="color: red; font-weight: bold;">'.__('There is no item/copy for this title yet').'</strong>';
        } else {
	    echo '<form method="post">';
            echo '<table width="100%" class="itemList" cellpadding="3" cellspacing="0">';


		$item='select item_code from temp where status="Confirm"';
		$item1=$dbs->query($item);
		$item3='';
		while($item2=$item1->fetch_assoc())
		{
			$item3.=$item2['item_code'].',';
		}

		$item3=substr($item3,0,-1);
            while ($copy_d = $copy_q->fetch_assoc()) {
                // check if this collection is on loan
                $loan_stat_q = $dbs->query('SELECT due_date FROM loan AS l
                    LEFT JOIN item AS i ON l.item_code=i.item_code
                    WHERE l.item_code=\''.$copy_d['item_code'].'\' AND is_lent=1 AND is_return=0');



                $sql='select item_code from temp where status="Confirm" AND item_code=\''.$copy_d['item_code'].'\'';
		$sql1=$dbs->query($sql);


		

		//$confirm_date='select item_code from loan where loan_date IN (select ADDDATE(confirm_date,1) from temp where item_code=\''.$copy_d['item_code'].'\')';
		//$confirm=$dbs->query($confirm_date);
				

		$confirm_date='SELECT item_code from temp where status ="Confirm" AND confirm_date < CURDATE() AND time < CURTIME() AND item_code= \''.$copy_d['item_code'].'\'';
		$confirm=$dbs->query($confirm_date);



		echo $confirm->num_rows."gggggG";
                echo '<tr><td width="10%"><strong>'.$copy_d['item_code'].'</strong></td><td width="60%">'.$copy_d['location_name'];
                if (trim($copy_d['site']) != "") {
                    echo ' ('.$copy_d['site'].')';
                }
                echo '</td>';
                echo '<td width="30%">';
                /* DEPECRATED
                $_rules = @unserialize($copy_d['rules']);
                */
                if ($loan_stat_q->num_rows > 0) {
                    $loan_stat_d = $loan_stat_q->fetch_row();
                    echo '<strong width="50%" style="color: red;">'.__('Currently On Loan (Due on').date($sysconf['date_format'], strtotime($loan_stat_d[0])).')</strong>'; //mfc
                } else if ($copy_d['no_loan']) {
                    echo '<strong width="50%" style="color: red;">'.__('Available but not for loan').' - '.$copy_d['item_status_name'].'</strong>';
                } 

		else if($sql1->num_rows>0)//added by iresh on 22-2-2011
		{
			$sql2 =$sql1->fetch_row();
			
			echo '<strong width="50%" style="color: red;">'.__('Reserved').'  '.$copy_d['item_status_name'].'</strong>';


			
		
			/*else {
				$item=$copy_d['item_code'];
		            echo '<strong width="50%" style="color: navy;">'.__('Available').(trim($copy_d['item_status_name'])?' - '.$copy_d['item_status_name']:'').'</strong><input type=radio name="values[$item]" value='. $copy_d['item_code'].'>';
		
		        	}*/
		
		}

	         else if($confirm->num_rows>=1)
		 {
			
				$confirm2 =$confirm->fetch_row();
				$item=$copy_d['item_code'];
		            echo '<strong width="50%" style="color: navy;">'.__('Available').(trim($copy_d['item_status_name'])?' - '.$copy_d['item_status_name']:'').'</strong><input type=radio name="values[$item]" value='. $copy_d['item_code'].'>';
		
		  }

		else {
				$item=$copy_d['item_code'];
		            echo '<strong width="50%" style="color: navy;">'.__('Available').(trim($copy_d['item_status_name'])?' - '.$copy_d['item_status_name']:'').'</strong><input type=radio name="values[$item]" value='. $copy_d['item_code'].'>';
		
		     }
		
                $loan_stat_q->free_result();
                echo '</td>';

                echo '</tr>';
            }
            
            echo '<tr><td><td align=center><input type=submit name=submit value="Book Request"><center></td></tr>';
            echo '</table>';
	    echo '</form>';
	   
           
        }
    }
}
?>
