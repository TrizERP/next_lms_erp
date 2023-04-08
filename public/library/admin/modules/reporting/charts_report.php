<?php
error_reporting(0);
/**
 * Charts
 * Copyright (C) 2010  Arie Nugraha (dicarve@yahoo.com)
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

/* Chart/Plot Report section */

if (!defined('SENAYAN_BASE_DIR')) {
    // main system configuration
    require '../../../sysconfig.inc.php';
    // start the session
//    require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
}

require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
// PHPLOT Library
if (file_exists(LIB_DIR.'phplot'.DIRECTORY_SEPARATOR.'phplot.php')) {
    require LIB_DIR.'phplot'.DIRECTORY_SEPARATOR.'phplot.php';
} else {
    die();
}

// privileges checking
$can_read = utility::havePrivilege('reporting', 'r');
$can_write = utility::havePrivilege('reporting', 'w');

if (!$can_read) { die(); }

/**
 * Function to generate random color
 * Taken from http://www.jonasjohn.de/snippets/php/random-color.htm
 * Licensed in Public Domain
 */
function generateRandomColors()
{
    @mt_srand((double)microtime()*1000000);
    $_c = '';
    while(strlen($_c)<6){ $_c .= sprintf("%02X", mt_rand(0, 255)); }
    return $_c;
}

// create PHPLot object
$plot = new PHPlot(770, 515);
$plot_data = array();
$data_colors = array();
// default chart
$chart = 'total_title_gmd';
$chart_title = __('Total Loan By Material Type');

if (isset($_GET['chart'])) {
    $chart = trim($_GET['chart']);
}


/**
 * Defines data here
 */
switch ($chart) {
    case 'total_title_colltype':
        $chart_title = __('Total Items By Collection Type');
        $stat_query = $dbs->query('SELECT coll_type_name, COUNT(item_id) AS total_items
            FROM `item` AS i
            INNER JOIN mst_coll_type AS ct ON i.coll_type_id = ct.coll_type_id
            GROUP BY i.coll_type_id
            HAVING total_items >0
            ORDER BY COUNT(item_id) DESC');
        // set plot data and colors
        while ($data = $stat_query->fetch_row()) {
            $plot_data[] = array($data[0], $data[1]);
            $data_colors[] = '#'.generateRandomColors();
        }
        break;
    case 'total_member_by_type':
        $chart_title = __('Total Members By Membership Type');
        // total number of active member by membership type
        $report_q = $dbs->query('SELECT mt.user_group_name, COUNT(enrollment_no) FROM "'.$inte_schema.'".tbluser_groups AS mt
            LEFT JOIN '.$inte_schema.'.tblstudent AS m ON mt.user_group_id=m.user_group_id
            WHERE TO_DAYS(expire_date)>TO_DAYS(\''.date('Y-m-d').'\')
            GROUP BY m.user_group_id ORDER BY COUNT(enrollment_no) DESC');
        while ($data = $report_q->fetch_row()) {
            $plot_data[] = array($data[0], $data[1]);
            $data_colors[] = '#'.generateRandomColors();
        }
        break;
    case 'total_loan_gmd':
        $chart_title = __('Total Loan By Material Type');
        $report_q = $dbs->query('SELECT gmd_name, COUNT(loan_id) FROM loan AS l
            INNER JOIN item AS i ON l.item_code=i.item_code
            INNER JOIN biblio AS b ON i.biblio_id=b.biblio_id
            INNER JOIN mst_gmd AS gmd ON b.gmd_id=gmd.gmd_id
            GROUP BY b.gmd_id ORDER BY COUNT(loan_id) DESC');
        $report_d = '';
        while ($data = $report_q->fetch_row()) {
            $plot_data[] = array($data[0], $data[1]);
            $data_colors[] = '#'.generateRandomColors();
        }
        break;
//added started by Parth 29/7/2011
    case 'total_loan_material_sub_type' :
	$chart_title = __('Total Loan By Material Sub Type');
        $report_q = $dbs->query('SELECT material_sub_name, COUNT(loan_id) FROM loan AS l
    INNER JOIN item AS i ON l.item_code=i.item_code
    INNER JOIN biblio AS b ON i.biblio_id=b.biblio_id
    INNER JOIN mst_material_sub_type AS gmd ON b.material_sub_id=gmd.material_sub_id
    GROUP BY b.material_sub_id ORDER BY COUNT(loan_id) DESC');
        $report_d = '';
        while ($data = $report_q->fetch_row()) {
            $plot_data[] = array($data[0], $data[1]);
            $data_colors[] = '#'.generateRandomColors();
        }
        break;
//added ended by Parth 29/7/2011
    case 'total_loan_colltype':
        $chart_title = __('Total Loan By Collection Type');
        $report_q = $dbs->query('SELECT coll_type_name, COUNT(loan_id) FROM loan AS l
            INNER JOIN item AS i ON l.item_code=i.item_code
            INNER JOIN mst_coll_type AS ct ON i.coll_type_id=ct.coll_type_id
            GROUP BY i.coll_type_id ORDER BY COUNT(loan_id) DESC');
        while ($data = $report_q->fetch_row()) {
            $plot_data[] = array($data[0], $data[1]);
            $data_colors[] = '#'.generateRandomColors();
        }
        break;
//added started by Parth 29/7/2011
     case 'total_title_material_sub_type':
	$chart_title = __('Total Material Sub type');	
	$report_q = $dbs->query('SELECT material_sub_name, COUNT(biblio_id) AS total_titles
    FROM `biblio` AS b
    INNER JOIN mst_material_sub_type AS gmd ON b.material_sub_id = gmd.material_sub_id
    GROUP BY b.material_sub_id HAVING total_titles>0 ORDER BY COUNT(biblio_id) DESC');
        while ($data = $report_q->fetch_row()) {
            $plot_data[] = array($data[0], $data[1]);
            $data_colors[] = '#'.generateRandomColors();
        }
        break;
//added ended by Parth 29/7/2011
    default:
        $stat_query = $dbs->query('SELECT gmd_name, COUNT(biblio_id) AS total_titles
            FROM `biblio` AS b
            INNER JOIN mst_gmd AS gmd ON b.gmd_id = gmd.gmd_id
            GROUP BY b.gmd_id HAVING total_titles>0 ORDER BY COUNT(biblio_id) DESC');
        // set plot data and colors
        while ($data = $stat_query->fetch_row()) {
            $plot_data[] = array($data[0], $data[1]);
            $data_colors[] = '#'.generateRandomColors();
        }
        break;
}
/**
 * Charts data definition end
 */

// Create plot
if ($plot_data && $chart) {
    // set plot titles
    $plot->SetTitle($chart_title);

    // set data
    $plot->SetDataValues($plot_data);

    // set plot colors
    $plot->SetDataColors($data_colors);

    // set plot shading
    $plot->SetShading(20);

    // set plot type to pie
    $plot->SetPlotType('pie');
    $plot->SetDataType('text-data-single');

    // set legend
    foreach ($plot_data as $row) {
      $plot->SetLegend(implode(': ', $row));
    }

    //Draw it
    $plot->DrawGraph();
}
exit();
?>
