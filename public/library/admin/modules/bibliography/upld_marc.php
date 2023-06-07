<?php
session_start();
error_reporting(0);

/* Bibliography Management section */

if (!defined('SENAYAN_BASE_DIR')) {
    // main system configuration
    require '../../../sysconfig.inc.php';
    // start the session
    require SENAYAN_BASE_DIR.'admin/default/session.inc.php';
}


require SENAYAN_BASE_DIR.'admin/default/session_check.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/table/simbio_table.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/form_maker/simbio_form_table_AJAX.inc.php';
require SIMBIO_BASE_DIR.'simbio_GUI/paging/simbio_paging.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/datagrid/simbio_dbgrid.inc.php';
require SIMBIO_BASE_DIR.'simbio_DB/simbio_dbop.inc.php';
require SIMBIO_BASE_DIR.'simbio_FILE/simbio_file_upload.inc.php';
//require_once("connection.php");
//IO function
function fileGetContents($filename)
{
  $result = "";
  $handle = @fopen ($filename, "rb");
  if ($handle === FALSE) {
    return FALSE;
  }
  while (!feof ($handle)) {
    $buffer = fgets($handle, 4096);
    if ($buffer === FALSE && !feof($handle)) {
      return FALSE;
    }
    $result .= $buffer;
  }
  if (!fclose ($handle)) {
    /* We don't care because we've finished reading. */
    // return FALSE;
  }
  return $result;
}
//IO function
// privileges checking
$can_read = utility::havePrivilege('bibliography', 'r');
$can_write = utility::havePrivilege('bibliography', 'w');

if (!$can_read) {
    die('<div class="errorBox">' . gettext('You are not authorized to view this section') . '</div>');
}

$in_pop_up = false;
// check if we are inside pop-up window
if (isset($_GET['inPopUp'])) {
    $in_pop_up = true;
}
?>
<br>
<center>
    <?php
    $bradecum = '';
    $basedir = basename(dirname(__FILE__));
    $bradecum = "<a href=javascript:void(0); onclick=javascript:new_set_home(); >Home</a>-><a class='' href=javascript:void(0); onclick=javascript:new_set('reporting');>";
    $query = "select module_name from mst_module where module_path = 'reporting'";
    $set_query = $dbs->query($query);
    while ($row = $set_query->fetch_assoc()) {
        $_formated_module_name = ucwords(str_replace('_', ' ', $row['module_name']));
        $bradecum .= $_formated_module_name;
    }
    $bradecum .= '</a>->';
/*if(isset($_REQUEST['action']))
{
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'membership/index.php?action=detail class="headerText2">Add New Member</a>';
}
else
{
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'membership/index.php class="headerText2">View Member List</a>';
}*/
$bradecum .= '<a href='.MODULES_WEB_ROOT_DIR.'bibliography/upld_marc.php class="headerText2">Import MARC file</a>';
echo $bradecum;
        ?></center>
<table>
    <tr>
        <td class="tab_menu_top">
            <ul class="tabs">
                <li>
                    <!--<a href="<?php echo MODULES_WEB_ROOT_DIR; ?>bibliography/dl_print.php" class="headerText2"><?php // echo gettext('Label Print'); ?></a> </li><li>-->
<a href="<?php echo  MODULES_WEB_ROOT_DIR; ?>bibliography/upld_marc.php" class="headerText2"><?php echo gettext('Import Marc File'); ?></a> </li>
            </ul>
    </td>
</tr>
</table>
<fieldset class="menuBox">
<div class="menuBoxInner importIcon">
    <?php echo gettext('IMPORT MARC FILE'); ?>
    <p class="only_border">&nbsp;</p>
</div>
</fieldset>
<?php
 $form = new simbio_form_table_AJAX('mainForms', $_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'], 'post');
    $form->submit_button_attr = 'name="saveData" value="'.gettext('Save').'" class="button"';
    // form table attributes
    $form->table_attr = 'align="center" id="dataList" border=0 cellpadding="5" cellspacing="0"';
   $form->table_header_attr = 'class="alterCell" style="font-weight: bold;"';
    $form->table_content_attr = 'class="alterCell2"';

    $visibility = 'makeVisible';
 if (file_exists(MODULES_BASE_DIR.'bibliography/custom_fields.inc.php')) {

        include MODULES_BASE_DIR.'bibliography/custom_fields.inc.php';
    }

    /* Form Element(s) */
    // biblio title
$str_input = simbio_form_element::textField('file', 'importFile');
$str_input .= ' Maximum '.$sysconf['max_upload'].' KB';
$form->addAnything(gettext('File'), $str_input);

/*$gmd_q = $dbs->query('SELECT gmd_id, gmd_name FROM mst_gmd');
    //$gmd_options='';
       $gmd_options = array('N/A');
        while ($gmd_d = $gmd_q->fetch_row())
    //while ($gmd_d = $gmd_q->fetch_assoc())
    {
            $gmd_options[] = array($gmd_d[0], $gmd_d[1]);
        //$gmd_options.=$gmd_d['gmd_id'].',';
        }*/
$material_q = $dbs->query('SELECT material_resource_id, material_resource_name FROM mst_material_resource_type where active_inactive="1"');
//$gmd_options='';
$material_options = array('--Resource Type--');
while ($material_d = $material_q->fetch_row()) //while ($gmd_d = $gmd_q->fetch_assoc())
{
    $material_options[] = array($material_d[0], $material_d[1]);
    //$gmd_options.=$gmd_d['gmd_id'].',';
}

$ajax = "ajaxFillSelect('" . SENAYAN_WEB_ROOT_DIR . "admin/AJAX_material_sub_type_handler.php', 'mst_gmd', 'gmd_id:gmd_name:material_resource_id', 'gmdID', $('materialresourceid').getValue())";

if ($rec_d['gmd_name']) {
    $mst_options[] = array($rec_d['gmd_id'], $rec_d['gmd_name']);
}
$mst_options[] = array('0', gettext('--Material Type--'));


$ajax_exp = "ajaxFillSelect('" . SENAYAN_WEB_ROOT_DIR . "admin/AJAX_material_sub_type_handler.php', 'mst_material_sub_type', 'material_sub_id:material_sub_name:gmd_id', 'materialsubid', $('gmdID').getValue())";

if ($rec_d['material_sub_name']) {
    $mst_material_sub_type_options[] = array($rec_d['material_sub_id'], $rec_d['material_sub_name']);
}
$mst_material_sub_type_options[] = array('0', gettext('--Material Sub Type--'));
// string element

//$str_input='';
$str_input = simbio_form_element::selectList('materialresourceid', $material_options, $rec_d['material_resource_id'], 'onchange="' . $ajax . '"');
$str_input .= simbio_form_element::selectList('gmdID', $mst_options, $rec_d['gmd_id'], 'onchange="' . $ajax_exp . '"');
//$str_input .= simbio_form_element::selectList('gmdID', $mst_options, $rec_d['gmd_id'],'onchange="ccc(this.value);'.$ajax_exp.'"');
$str_input .= '&nbsp;';
$str_input .= simbio_form_element::selectList('materialsubid', $mst_material_sub_type_options, $rec_d['material_sub_id'], 'style="width: 30%;"');
$str_input .= '<tr><td id="txtHint" class="alterCell2" colspan="3"></td></tr>';
// $str_input .= '<tr><td id="txtsubmaterial" class="alterCell2" colspan="3"></td></tr>';
// $str_input .= '&nbsp;';
//$str_input .= simbio_form_element::textField('text', 'plc_search_str', $rec_d['place_name'], 'style="width: 45%;" onkeyup="'.$ajax_exp.'"');
$form->addAnything(gettext('Material Type'), $str_input);
//$form->addAnything(gettext('Upload Mrc File'), $str_input);

 echo $form->printOut();
?>
<?php

if(isset($_POST['saveData']) AND $can_read AND $can_write)
{


$max_chars = 1024*1000;

    // check for form validity
    if (!$_FILES['importFile']['name']) {
        utility::jsAlert(gettext('Please select the file to import!'));
        exit();
    }  else {

        $start_time = time();
        // set PHP time limit
        set_time_limit(0);
        // set ob implicit flush
        ob_implicit_flush();
        // create upload object
        $upload = new simbio_file_upload();
        // get system temporary directory location
        //$temp_dir = sys_get_temp_dir();
    //$uploaded_file = $temp_dir.DIRECTORY_SEPARATOR.$_FILES['importFile']['name'];
        //unlink($uploaded_file);
        // set max size
        //$max_size = $sysconf['max_upload']*1024;
        $upload->setAllowableFormat(array('.mrc'));
        //$upload->setMaxSize($max_size);
        //$upload->setUploadDir($temp_dir);
        $upload_status = $upload->doUpload_mrc('importFile');
        if ($upload_status != UPLOAD_SUCCESS) {
            utility::jsAlert(gettext('Upload failed! File type not allowed or the size is more than').($sysconf['max_upload']/1024).' MB'); //mfc
            exit();
        }
    }
    utility::jsAlert(gettext('File Uploaded Successfully!'));
$exclude = array("035","0359","010a","040a","040b","040c","040d","0970","097b", "097a",);
array_push($exclude, "906a","906b","906c","906d","906e","906f","906g","955a");
//$ext = end(explode('.', $_FILES["usmarc_data"]["name"]));
$recordterminator="\35";
$fieldterminator="\36";
$delimiter="\37";
$usmarc_str = fileGetContents($_FILES["importFile"]["tmp_name"]);
$records = explode($recordterminator,$usmarc_str);
//echo "1-->".$records[0];
//echo "2-->".$records[1];
//echo "3-->".$records[2];
$j=0;
array_pop($records);

$biblios = array();
foreach($records as $record) {
$biblio = new Biblio();
  //$biblio = new Biblio();
  //$biblio->setLastChangeUserid($_POST["userid"]);
  //$biblio->setMaterialCd($_POST["materialCd"]);
  //$biblio->setCollectionCd($_POST["collectionCd"]);
  //$biblio->setOpacFlg($_POST["opac"] == 'Y');

  $start=substr($record,12,5);
  $header=substr($record,24,$start-25);
  $codes = array();
  for ($l=0; $l<strlen($header); $l += 12) {
    $code=substr($header,$l,12);
    $codes[]=substr($code,0,3);

  }
 $j=0;
 foreach(split($fieldterminator,substr($record,$start)) as $field) {
    if ($codes[$j]{0} == '0' and $codes[$j]{1} == '0') {
      $j++;
      continue;  // We don't support control fields yet
    }
    // Skip three characters to drop indicators and the first delimiter.
    foreach(split($delimiter,substr($field, 3)) as $subfield) {
      $ident = $subfield{0};
      $data=substr($subfield,1);
      if (in_array($codes[$j].$ident, $exclude)) {
        continue;
      }


$f = new BiblioField();

     // $f->setTag($codes[$j]);
     //$f->setSubfieldCd($ident);
     //$f->setFieldData($data);
//$biblio->addBiblioField($codes[$j].$ident, $f);
      //echo H("$codes[$j]--$ident--$data")."<br />\n";

      if (trim($data)!="" and trim($codes[$j])!=="") {
        $f = new BiblioField();
        $f->setTag($codes[$j]);
        $f->setSubfieldCd($ident);
        $f->setFieldData($data);
        $biblio->addBiblioField($codes[$j].$ident, $f);
      }


//$f = new BiblioField();
  //      $f->setTag($codes[$j]);
    //    $f->setSubfieldCd($ident);
      //  $f->setFieldData($data);
//$biblio->addBiblioField($codes[$j].$ident, $f);
    }
    $j++;
  }

array_push($biblios, $biblio);

}
 foreach ($biblios as $biblio) {
//echo "hi";
    echo '<h3>Record</h3>';
    echo '<table><tr>';
    echo '<th>Tag</th>';
    echo '<th>Subfield</th>';
    echo '<th>UploadedData</th>';
    echo '</tr>';
//define veriables start
$title = '';
$sub_title = '';
$tag_new = '';
$edition = '';
$isbn_issn = '';
$publisher_name  = '';
$publisher_year  = '';
$language = '';
$source = '';
$publisher_place = '';
$specific_detail_info = '';
$publisher_date = '';
$note = '';
$author = '';
$classification_number = '';
//define veriables end
    foreach ($biblio->getBiblioFields() as $field) {
      echo '<tr><td>'.H($field->getTag()).'</td>';
      echo '<td>'.H($field->getSubfieldCd()).'</td>';
      echo '<td>'.H($field->getFieldData()).'</td></tr>';
    if(H($field->getTag())=="245" && H($field->getSubfieldCd())=="a")
        {
            $title = H($field->getFieldData());
            //echo "Title -->".$title."</br>";

        }
    if(H($field->getTag())=="245" && H($field->getSubfieldCd())=="b")
        {
            $sub_title = H($field->getFieldData());
            //echo "Sub Title -->".$sub_title."</br>";
        }
    if(H($field->getTag())=="650" && H($field->getSubfieldCd())=="a") {
        $tag_new .= "," . H($field->getFieldData());
        //echo "tags -->".$tag_new."</br>";
    }
    if(H($field->getTag())=="250" && H($field->getSubfieldCd())=="a") {
        $edition = H($field->getFieldData());
        //echo "Edition -->".$edition."</br>";
    }

    if(H($field->getTag())=="020" && H($field->getSubfieldCd())=="a")
        {
            if(!empty($isbn_issn))
            {
            }
            else {
                $isbn_issn .= H($field->getFieldData());
                //echo "isbn_issn -->".$isbn_issn."</br>";
            }
        }
    if(H($field->getTag())=="260" && H($field->getSubfieldCd())=="b") {
        $publisher_name = H($field->getFieldData());
        //echo "Publisher_Name -->".$publisher_name."</br>";

    }
    if(H($field->getTag())=="260" && H($field->getSubfieldCd())=="c") {
        $publisher_year = H($field->getFieldData());
        $bodytag = str_replace("[", "", $publisher_year);
        $publisher_year = str_replace("]", "", $bodytag);
        $publisher_year = str_replace(".", "", $publisher_year);
        $publisher_year = str_replace(";", "", $publisher_year);
        $publisher_year = str_replace("(", "", $publisher_year);
        $publisher_year = str_replace(")", "", $publisher_year);
        //echo "Publisher_Year -->".$publisher_year."</br>";

    }
    if(H($field->getTag())=="041" && H($field->getSubfieldCd())=="g")
        {
            $language  = H($field->getFieldData());
            //echo "Language -->".$language."</br>";

        }
    if(H($field->getTag())=="041" && H($field->getSubfieldCd())=="2")
        {
            $source = H($field->getFieldData());
            //echo "Source -->".$source."</br>";

        }
    if(H($field->getTag())=="260" && H($field->getSubfieldCd())=="a") {
        $publisher_place = H($field->getFieldData());
        $bodytag = str_replace(":", "", $publisher_place);
        $publisher_place = str_replace(";", "", $bodytag);
        $publisher_place = str_replace(",", "", $publisher_place);
        $publisher_place = str_replace("[", "", $publisher_place);
        $publisher_place = str_replace("]", "", $publisher_place);
        //echo "Publisher_Place -->".$publisher_place."</br>";

    }
    if(H($field->getTag())=="520" && H($field->getSubfieldCd())=="a")
        {
            $specific_detail_info = H($field->getFieldData());
            //echo "Specific Detail Info -->".$specific_detail_info."</br>";

        }
    if(H($field->getTag())=="260" && H($field->getSubfieldCd())=="c")
        {
            $publisher_date = H($field->getFieldData());
            //echo "Publisher_Date -->".$publisher_date."</br>";

        }
    if(H($field->getTag())=="050" && H($field->getSubfieldCd())=="a")
        {
            $classification_number = H($field->getFieldData());

            //echo "Classification Number -->".$classification_number."</br>";

        }
    if(H($field->getTag())=="050" && H($field->getSubfieldCd())=="b")
        {
            $classification_number.= H($field->getFieldData());

            //echo "Classification Number -->".$classification_number."</br>";

        }
    if(H($field->getTag())=="504" && H($field->getSubfieldCd())=="a")
        {

            $note = H($field->getFieldData());
            //echo "Note -->".$note."</br>";

        }
    if(H($field->getTag())=="100" && H($field->getSubfieldCd())=="a")
        {

            $author = H($field->getFieldData());
            //echo "Author -->".$author."</br>";

        }
    }
echo "Title->".$title."<br/>";
echo "Sub Title->".$sub_title."<br/>";
echo "tags->".$tag_new."<br/>";
echo "Edition ->".$edition."<br/>";
echo "ISBN_ISSN ->".$isbn_issn."<br/>";
echo "Publisher Name ->".$publisher_name."<br/>";
echo "Publisher_Year ->".$publisher_year."<br/>";
echo "Language ->".$language."<br/>";
echo "Source ->".$source."<br/>";
echo "Publisher_place ->".$publisher_place."<br/>";
echo "Specific_detail_info ->".$specific_detail_info."<br/>";
echo "Publisher_Date ->".$publisher_date."<br/>";
echo "Note ->".$note."<br/>";
echo "Author ->".$author."<br/>";
echo "Classification Number ->".$classification_number."<br/>";
if(!empty($author))
{
    $qry_author = "INSERT IGNORE into mst_author (author_name,authority_type,input_date,last_update) values ('$author','p',sysdate(),sysdate())";
    $dbs->query($qry_author);
    $author_id = $dbs->insert_id;
    //$result_author = mysqli_query($qry_author);
    //$author_id = mysql_insert_id();
    }
if(!empty($publisher_name))
    {
$qry_publisher = "INSERT IGNORE into mst_publisher (publisher_name,input_date,last_update) values ('$publisher_name',sysdate(),sysdate())";
//$result_publisher = mysqli_query($qry_publisher);
$dbs->query($qry_publisher);
$publisher_id = $dbs->insert_id;
    }
if(!empty($publisher_place))
    {
$qry_publisher_place = "INSERT IGNORE into mst_place (place_name,input_date,last_update) values ('$publisher_place',sysdate(),sysdate())";
$dbs->query($qry_publisher_place);
//$result_publisher_place = mysqli_query($qry_publisher_place);
$publisher_place_id = $dbs->insert_id;
    }
$qry_insert_biblio = "INSERT IGNORE into biblio (   material_resource_id,gmd_id,material_sub_id,title,sub_title,tags,edition,isbn_issn,publisher_id,publish_year,language_id,source,publish_place_id,classification,notes,spec_detail_info,publication_date,input_date,last_update) values ('$_POST[materialresourceid]','$_POST[gmdID]','$_POST[materialsubid]','$title','$sub_title','$tags','$edition','$isbn_issn','$publisher_id','$publisher_year','$language','$source','$publisher_place_id','$classification_number','$note','$specific_detail_info','$publisher_date',sysdate(),sysdate())";
 //utility::jsAlert($qry_insert_biblio); //mfc
$dbs->query($qry_insert_biblio);
//$result_insert_biblio = mysqli_query($qry_insert_biblio);
$result_biblio_id = $dbs->insert_id;
if(isset($result_biblio_id))
    {
    $qry_biblio_author = "INSERT IGNORE into biblio_author (biblio_id,author_id,level) values ('$result_biblio_id','$author_id','2')";
    $dbs->query($qry_biblio_author);
    }
    echo '</table>';
  }
}
function H($s) {
    return htmlspecialchars($s, ENT_QUOTES);
  }
class Biblio
{

 var $_biblioFields = array();
 function addBiblioField($index, $value) {
    $keySuffix = "";
    while (array_key_exists($index.$keySuffix, $this->_biblioFields)) {
      if ($keySuffix == "") {
        $keySuffix = 1;
      } else {
        $keySuffix = $keySuffix + 1;
      }
    }
    $this->_biblioFields[$index.$keySuffix] = $value;
  }
 function getBiblioFields() {
    return $this->_biblioFields;
  }
}
class BiblioField
{

  var $_tag = "";
  var $_subfieldCd = "";
  var $_fieldData = "";
 function setTag($value) {
  $this->_tag = trim($value);
  }
  function setSubfieldCd($value) {
    $this->_subfieldCd = substr(trim($value),0,1);
  }
function setFieldData($value) {
    $this->_fieldData = trim($value);
  }
   function getTag() {
    return $this->_tag;
  }
   function getSubfieldCd() {
    return $this->_subfieldCd;
  }
   function getFieldData() {
    return $this->_fieldData;
  }
 }


?>
