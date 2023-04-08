<?php
require_once("connection.php");
require_once("fileIOFuncs.php");
$exclude = array("035","0359","010a","040a","040b","040c","040d","0970","097b", "097a",);
array_push($exclude, "906a","906b","906c","906d","906e","906f","906g","955a");

$recordterminator="\35";
$fieldterminator="\36";
$delimiter="\37";
$usmarc_str = fileGetContents($_FILES["usmarc_data"]["tmp_name"]);
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
	if(H($field->getTag())=="650" && H($field->getSubfieldCd())=="a")
		{
			$tag_new.= ",".H($field->getFieldData());			
			//echo "tags -->".$tag_new."</br>";
		}	
	if(H($field->getTag())=="250" && H($field->getSubfieldCd())=="a")
		{
			$edition = H($field->getFieldData());			
			//echo "Edition -->".$edition."</br>";
		}
	
	if(H($field->getTag())=="020" && H($field->getSubfieldCd())=="a")
		{
			if(!empty($isbn_issn))
			{
			}
			else
			{
			$isbn_issn.= H($field->getFieldData());			
			//echo "isbn_issn -->".$isbn_issn."</br>";
			}
		}
	if(H($field->getTag())=="260" && H($field->getSubfieldCd())=="b")
		{
			$publisher_name = H($field->getFieldData());			
			//echo "Publisher_Name -->".$publisher_name."</br>";
			
		}
	if(H($field->getTag())=="260" && H($field->getSubfieldCd())=="c")
		{
		$publisher_year = H($field->getFieldData());
		$bodytag = str_replace("[", "", $publisher_year);
		$publisher_year = str_replace("]", "", $bodytag);
		$publisher_year = str_replace(".", "", $publisher_year);	
		$publisher_year = str_replace(";", "", $publisher_year);	
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
	if(H($field->getTag())=="260" && H($field->getSubfieldCd())=="a")
		{
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
/*echo "Title->".$title."<br/>";
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
echo "Classification Number ->".$classification_number."<br/>";*/
if(!empty($author))
	{
$qry_author = "INSERT IGNORE into mst_author (author_name,authority_type,input_date,last_update) values ('$author','p',sysdate(),sysdate())";
$result_author = mysql_query($qry_author); 
$author_id = mysql_insert_id();
 	}
if(!empty($publisher_name))
	{
$qry_publisher = "INSERT IGNORE into mst_publisher (publisher_name,input_date,last_update) values ('$publisher_name',sysdate(),sysdate())";
$result_publisher = mysql_query($qry_publisher); 
$publisher_id = mysql_insert_id();
 	}
if(!empty($publisher_place))
	{
$qry_publisher_place = "INSERT IGNORE into mst_place (place_name,input_date,last_update) values ('$publisher_place',sysdate(),sysdate())";
$result_publisher_place = mysql_query($qry_publisher_place); 
$publisher_place_id = mysql_insert_id();
 	}
$qry_insert_biblio = "INSERT IGNORE into biblio (title,sub_title,tags,edition,isbn_issn,publisher_id,publish_year,language_id,source,publish_place_id,classification,notes,spec_detail_info,publication_date,input_date,last_update) values ('$title','$sub_title','$tags','$edition','$isbn_issn','$publisher_id','$publisher_year','$language','$source','$publisher_place_id','$classification_number','$note','$specific_detail_info','$publisher_date',sysdate(),sysdate())";
$result_insert_biblio = mysql_query($qry_insert_biblio);
$result_biblio_id = mysql_insert_id();
if(isset($result_biblio_id))
	{
	$qry_biblio_author = "INSERT IGNORE into biblio_author (biblio_id,author_id,level) values ('$result_biblio_id','$author_id','2')";
	$result_biblio_author = mysql_query($qry_biblio_author);
	}
    echo '</table>';
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
