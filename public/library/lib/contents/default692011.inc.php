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

/* Showing list of catalogues and also for searching handling */

// include required class class
/*added by iresh on 22-1-2011 */
error_reporting(0);
require SIMBIO_BASE_DIR.'simbio_UTILS/simbio_tokenizecql.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require LIB_DIR.'biblio_list.inc.php';

// create biblio list object
$biblio_list = new biblio_list($dbs);
// no item data related search on UCS
if (defined('UCS_BASE_DIR')) { $biblio_list->disable_item_data = true; }

if (isset($sysconf['enable_xml_detail']) && !$sysconf['enable_xml_detail']) {
    $biblio_list->xml_detail = false;
}
//added by parth 30/06/2011
if($_GET['Search'] == "Keyword Search")
{
    $is_adv = false;
    $keywords = '';
    $criteria = '';
    if ($_GET['material_sub_type_select'] != "SELECT") {
        $_GET['material_sub_type_select1'] = $_GET['material_sub_type_select'];
    }
    //$_GET['subtype'] = str_replace(',',' ',$_GET['subtype']);
    //$keywords = trim(strip_tags(urldecode($_GET['subtype'])));
    //$qry = $dbs->query("select material_sub_id from mst_material_sub_type where material_sub_name ='".$_GET['subtype']."'");
    //$qry = $qry->fetch_row();
    //$result = mysqli_query($qry);
    //echo "hi";
    $criteria .= ' searchkeyword=' . $_GET['q'];
    $criteria = trim($criteria);
    $biblio_list->setSQLcriteria($criteria);
}
if(isset($_GET['subtype']) && !empty($_GET['subtype'])) {
    $is_adv = false;
    $keywords = '';
    $criteria = '';
    //$_GET['subtype'] = str_replace(',',' ',$_GET['subtype']);
    //$keywords = trim(strip_tags(urldecode($_GET['subtype'])));
    //$qry = $dbs->query("select material_sub_id from mst_material_sub_type where material_sub_name ='".$_GET['subtype']."'");
    //$qry = $qry->fetch_row();
    //$result = mysqli_query($qry);

    $criteria .= ' subtype=' . $_GET['subid'];
    $criteria = trim($criteria);
    $biblio_list->setSQLcriteria($criteria);
    // simple search
    /*if (isset($_GET['subtype'])) {
        $keywords = trim(strip_tags(urldecode($_GET['subtype'])));
    }
    if ($keywords && !preg_match('@[a-z0-9_.]+=[^=]+\s+@i', $keywords.' ')) {
        $criteria = "subtype LIKE $keywords%";
       $biblio_list->setSQLcriteria($criteria);

    } else {
        $biblio_list->setSQLcriteria($keywords);
    }*/

}
if (isset($_GET['search1']) && !empty($_GET['search1']) && empty($_GET['Search'])) {
    $is_adv = false;
    $keywords = '';
    $criteria = '';
    //$_GET['subtype'] = str_replace(',',' ',$_GET['subtype']);
    //$keywords = trim(strip_tags(urldecode($_GET['subtype'])));
    //$qry = $dbs->query("select material_sub_id from mst_material_sub_type where material_sub_name ='".$_GET['subtype']."'");
    //$qry = $qry->fetch_row();

    //$result = mysqli_query($qry);

    $_GET['subtype1'] = $_GET['subid'];
    if ($_GET['material_sub_type_select'] != "SELECT") {
        $_GET['material_sub_type_select1'] = $_GET['material_sub_type_select'];
    }
    $criteria .= ' searchsimple=' . $_GET['keywords'];
    $criteria = trim($criteria);
    $biblio_list->setSQLcriteria($criteria);
}
if (isset($_GET['letternew']) && !empty($_GET['letternew'])) {
    $is_adv = false;
    $keywords = '';
    $criteria = '';
    //$_GET['subtype'] = str_replace(',',' ',$_GET['subtype']);
    //$keywords = trim(strip_tags(urldecode($_GET['subtype'])));
    //$qry = $dbs->query("select material_sub_id from mst_material_sub_type where material_sub_name ='".$_GET['subtype']."'");
    //$qry = $qry->fetch_row();

    //$result = mysqli_query($qry);

    //$_GET['subtype1'] = $qry[0];
    $_GET['subtype1'] = $_GET['subid'];
    $criteria .= ' letternew=' . $_GET['letternew'];
    $criteria = trim($criteria);
    $biblio_list->setSQLcriteria($criteria);
}
//ended by parth 30/06/2011
if (isset($_GET['letter']) && !empty($_GET['letter']))//added by iresh on 17-3-2011 For alphabetical searching
{


    // default vars
    $is_adv = false;
    $keywords = '';
    $criteria = '';
    // simple search
    if (isset($_GET['letter'])) {
        $keywords = trim(strip_tags(urldecode($_GET['letter'])));
    }
    if ($keywords && !preg_match('@[a-z0-9_.]+=[^=]+\s+@i', $keywords.' ')) {
        $criteria = "title LIKE $keywords%";
       $biblio_list->setSQLcriteria($criteria);

    } else {
        $biblio_list->setSQLcriteria($keywords);
    }

}

if (isset($_GET['submit']) && !empty($_GET['submit'])) {


$standard='';
  $standard = trim(strip_tags(urldecode($_GET['standard'])));
/*
if (!empty($_GET['standard'])) {

	$standard_title=$dbs->query('SELECT standard_name FROM mst_standard WHERE standard_id='.$_GET['standard'].'');
	while($row=$standard_title->fetch_assoc())
	{
		  $standard = trim(strip_tags(urldecode($row['standard_name'])));

	}


        }
*/
$subject_ajax='';
$subject_ajax = trim(strip_tags(urldecode($_GET['subjectajax'])));
/*
if (isset($_GET['subjectajax'])!='0') {

	$subject_ajax_title=$dbs->query('SELECT topic FROM mst_topic WHERE topic_id='.$_GET['subjectajax'].'');
	while($row=$subject_ajax_title->fetch_assoc())
	{
		  $subject_ajax = trim(strip_tags(urldecode($row['topic'])));

	}


        }
*/
$subject='';
 $subject = trim(strip_tags(urldecode($_GET['subject'])));
/*if (isset($_GET['subject'])!='0') {

	$subject_title=$dbs->query('SELECT topic FROM mst_topic WHERE topic_id='.$_GET['subject'].'');
	while($row=$subject_title->fetch_assoc())
	{
		  $subject = trim(strip_tags(urldecode($row['topic'])));

	}


        }
*/
$subjecttype='';
 $subjecttype = trim(strip_tags(urldecode($_GET['subjecttype'])));
/*
$subjecttype='';
if (isset($_GET['subjecttype'])) {

	$subjecttype_title=$dbs->query('SELECT subject_type_name FROM mst_subject_type WHERE subject_type_id='.$_GET['subjecttype'].'');
	while($row=$subjecttype_title->fetch_assoc())
	{
		  $subjecttype = trim(strip_tags(urldecode($row['subject_type_name'])));

	}


        }
*/

$materialtype='';
 $materialtype = trim(strip_tags(urldecode($_GET['material_sub_type'])));

/*
if (isset($_GET['material_sub_type'])) {

	$materialtype_title=$dbs->query('SELECT material_sub_name FROM mst_material_sub_type WHERE material_sub_id='.$_GET['material_sub_type'].'');
	while($row=$materialtype_title->fetch_assoc())
	{
		  $materialtype = trim(strip_tags(urldecode($row['material_sub_name'])));

	}


        }

*/
if($standard || $materialtype || $subject_ajax || $subject || $subjecttype)
{

 $criteria = '';


if ($subject) { $criteria .= ' subject='.$subject;}
if ($standard){ $criteria .= ' standard='.$standard; }
if ($subjecttype){ $criteria .= ' subjecttype='.$subjecttype; }
if ($materialtype){ $criteria .= ' material_sub_type='.$materialtype; }
if ($subject_ajax){ $criteria .= ' subjectajax='.$subject_ajax; }
 $criteria = trim($criteria);
 $biblio_list->setSQLcriteria($criteria);
}
//echo "<br>";
}

// if we are in searching mode
if (isset($_GET['search']) && !empty($_GET['search'])) {
    // default vars
    $is_adv = false;
    $keywords = '';
    $criteria = '';
    // simple search
    if (isset($_GET['keywords'])) {
        $keywords = trim(strip_tags(urldecode($_GET['keywords'])));
    }
    if ($keywords && !preg_match('@[a-z0-9_.]+=[^=]+\s+@i', $keywords.' ')) {

        $criteria = 'title='.$keywords.' OR author='.$keywords.' OR subject='.$keywords.' OR barcode='.$keywords;

        $biblio_list->setSQLcriteria($criteria);
    } else {
        $biblio_list->setSQLcriteria($keywords);
    }
    // advanced search
    $is_adv = isset($_GET['search']) || isset($_GET['title']) || isset($_GET['author']) || isset($_GET['isbn'])
        || isset($_GET['subject']) || isset($_GET['location'])
        || isset($_GET['gmd']) || isset($_GET['mst_sub']) || isset($_GET['colltype']) || isset($_GET['classification']) || isset($_GET['publish_year']) || isset($_GET['publish_name']) || isset($_GET['keywords_tag']);
    if ($is_adv) {

        $title = '';
        if (isset($_GET['title'])) {
           $title = trim(strip_tags(urldecode($_GET['title'])));

        }
        $author = '';
        if (isset($_GET['author'])) {
            $author = trim(strip_tags(urldecode($_GET['author'])));
        }
        $subject = '';
        if (isset($_GET['subject'])) {
            $subject = trim(strip_tags(urldecode($_GET['subject'])));
        }
        $isbn = '';
        if (isset($_GET['isbn'])) {
            $isbn = trim(strip_tags(urldecode($_GET['isbn'])));
        }
        $gmd = '';
        if (isset($_GET['gmd'])) {
            $gmd = trim(strip_tags(urldecode($_GET['gmd'])));
        }
	$mst_sub = '';
        if (isset($_GET['mst_sub'])) {
           $mst_sub = trim(strip_tags(urldecode($_GET['mst_sub'])));
        }
        $colltype = '';
        if (isset($_GET['colltype'])) {
            $colltype = trim(strip_tags(urldecode($_GET['colltype'])));
        }
        $location = '';
        if (isset($_GET['location'])) {
            $location = trim(strip_tags(urldecode($_GET['location'])));
        }
        $class = '';
        if (isset($_GET['classification'])) {
            $class = trim(strip_tags(urldecode($_GET['classification'])));
        }
        $publish_year = '';
        if (isset($_GET['publish_year'])) {
            $publish_year = trim(strip_tags(urldecode($_GET['publish_year'])));
        }
        $publish_name = '';
        if (isset($_GET['publish_name'])) {
            $publish_name = trim(strip_tags(urldecode($_GET['publish_name'])));
        }
        $keywords_tag = '';
        if(isset($_GET['keywords_tag'])) {
            $keywords_tag = trim(strip_tags(urldecode($_GET['keywords_tag'])));
        }
        // UCS only
        $node = '';
        if (isset($_GET['node'])) {
            $node = trim(strip_tags(urldecode($_GET['node'])));
        }
        // don't do search if all search field is empty
        if ($title || $author || $subject || $isbn || $gmd || $mst_sub || $colltype || $location || $node || $class || $publish_year || $publish_name || $keywords_tag) {
              $criteria = '';
            if ($title) { $criteria .= ' title='.$title; }
            if ($author) { $criteria .= ' author='.$author; }
            if ($subject) { $criteria .= ' subject='.$subject;  }
            if ($isbn) { $criteria .= ' isbn='.$isbn; }
            if ($gmd) { $criteria .= ' gmd="'.$gmd.'"'; }
            if ($mst_sub) {
                $criteria .= ' mst_sub="' . $mst_sub . '"';
            }
            if ($colltype) {
                $criteria .= ' colltype="' . $colltype . '"';
            }
            if ($class) {
                $criteria .= ' class="' . $class . '"';
            }
            if ($publish_year) {
                $criteria .= ' publishyear="' . $publish_year . '"';
            }
            if ($publish_name) {
                $criteria .= ' publisher="' . $publish_name . '"';
            }
            if ($keywords_tag) {
                $criteria .= ' keywords_tag="' . $keywords_tag . '"';
            }
            if ($location) {
                $criteria .= ' location="' . $location . '"';
            }
            if ($node && defined('UCS_BASE_DIR')) { $criteria .= ' location="'.$node.'"'; }
             $criteria = trim($criteria);

            $biblio_list->setSQLcriteria($criteria);

        }
    }

    // search result info construction
    if ($is_adv) {
     // comment by iresh on 22-1-20011   $info .= '<div style="clear: both;">'.__('Found  <strong>{biblio_list->num_rows}</strong> from your keywords').' : </div>  '; //mfc
        if ($title) { $info .= 'Title : <strong><cite>'.$title.'</cite></strong>, '; }
        if ($author) { $info .= 'Author : <strong><cite>'.$author.'</cite></strong>, '; }
        if ($subject) { $info .= 'Subject : <strong><cite>'.$subject.'</cite></strong>, '; }
        if ($isbn) { $info .= 'ISBN/ISSN : <strong><cite>'.$isbn.'</cite></strong>, '; }
        if ($gmd) { $info .= 'GMD : <strong><cite>'.$gmd.'</cite></strong>, '; }
        if ($colltype) { $info .= 'Collection Type : <strong><cite>'.$colltype.'</cite></strong>, '; }
        if ($location) { $info .= 'Location : <strong><cite>'.$location.'</cite></strong>, '; }
        // strip last comma
        $info = substr_replace($info, '', -2);
    } else {
       $info .= '<div style="clear: both;">'.__('Found  <strong>{biblio_list->num_rows}</strong> from your keywords').': <strong><cite>'.$keywords.'</cite></strong></div>'; //mfc
    }
} else {
    // show promoted titles
    if (isset($sysconf['enable_promote_titles']) && $sysconf['enable_promote_titles']) {
        $biblio_list->only_promoted = true;

    }
}

// check if we are on xml resultset mode
if (isset($_GET['resultXML']) && $sysconf['enable_xml_result']) {
    // get document list but don't output the result
    $biblio_list->getDocumentList($sysconf['opac_result_num'], false);
    if ($biblio_list->num_rows > 0) {
        // send http header
        header('Content-Type: text/xml');
        echo '<?xml version="1.0" encoding="UTF-8" ?>'."\n";
        echo $biblio_list->XMLresult();
    }
    exit();
} else {
    // show the list
    echo $biblio_list->getDocumentList($sysconf['opac_result_num']);
    echo '<br />'."\n";
    // set result number info
    $info = str_replace('{biblio_list->num_rows}', $biblio_list->num_rows, $info);
}
// count total pages
$total_pages = ceil($biblio_list->num_rows/$sysconf['opac_result_num']);

// page number info
if (isset($_GET['page']) AND $_GET['page'] > 1) {
    $page = intval($_GET['page']);
    $msg = str_replace('{page}', $page, __('You currently on page <strong>{page}</strong> of <strong>{total_pages}</strong> page(s)')); //mfc
    $msg = str_replace('{total_pages}', $total_pages, $msg);
    $info .= '<div style="clear: both;">'.$msg.'</div>';
} else {
    $page = 1;
}
// query time
// comment by iresh on 22-1-20011 $info .= '<div>'.__('Query took').' <b>'.$biblio_list->query_time.'</b> '.__('second(s) to complete').'</div>'; //mfc
if (isset($biblio_list) && isset($sysconf['enable_xml_result']) && $sysconf['enable_xml_result']) {
    $info .= '<div><a href="index.php?resultXML=true&'.$_SERVER['QUERY_STRING'].'" class="xmlResultLink" target="_blank" title="View Result in XML Format" style="clear: both;">XML Result</a></div>';
}
?>
