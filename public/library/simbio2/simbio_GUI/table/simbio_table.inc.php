<?php
/**
 * simbio_table class
 * Class for creating HTML table
 *
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


class simbio_table_field
{
    public $attr;
    public $value;

    /**
     * Class Constructor
     *
     * @param   string  $str_attr
     */
    public function __construct($str_attr = '')
    {
        $this->attr = $str_attr;
    }
}


class simbio_table_row
{
    public $attr;
    public $fields = array();
    public $all_cell_attr;

    /**
     * Class Constructor
     *
     * @param   string  $str_attr
     */
    public function __construct($array_field_content, $str_attr = '')
    {
        $this->attr = $str_attr;
        $this->addFields($array_field_content);
    }


    /**
     * Method to create simbio_table_field array from array
     *
     * @param   array   $array_field_content
     * @return  array
     */
    public function addFields($array_field_content)
    {
        foreach ($array_field_content as $idx => $fld_content) {
            $_field_obj = new simbio_table_field();
            $_field_obj->value = $fld_content;
            $this->fields[$idx] = $_field_obj;
        }
    }
}


class simbio_table
{
    public $table_attr = '';
    public $table_header_attr = '';
    public $table_content_attr = '';
    public $table_row = array();
    public $cell_attr = array();
    public $highlight_row = false;

    /**
     * Class Constructor
     *
     * @param   string  $str_table_attr
     */
    public function __construct($str_table_attr = '')
    {
        $this->table_attr = $str_table_attr;
    }


    /**
     * Method to set table headers
     *
     * @param   array   $array_column_value
     * @return  void
     */
    public function setHeader($array_column_content)
    {
        if (!is_array($array_column_content)) {
            // do nothing
            return;
        } else {
            $this->table_row[0] = new simbio_table_row($array_column_content);
        }
    }


    /**
     * Method to append row/record to table
     *
     * @param   array   $array_column_content
     * @return  void
     */
    public function appendTableRow($array_column_content)
    {
        // row content must be an array
        if (!is_array($array_column_content)) 
         {
            // do nothing
            return;
        } else {
            // records row must start with index 1 not 0
            // index 0 is reserved for table header row
            $_row_cnt = count($this->table_row);
            // create instance of simbio_table_row
            $_row_obj = new simbio_table_row($array_column_content);
            if ($_row_cnt < 1) {
                $this->table_row[1] = $_row_obj;
            } else {
                // if header row exists
                if (isset($this->table_row[0])) {
                    $this->table_row[$_row_cnt] = $_row_obj;
                } else {
                    $this->table_row[$_row_cnt+1] = $_row_obj;
                }
            }
        }
    }


    /**
     * Method to set content of specific column
     *
     * @param   integer $int_row
     * @param   integer $int_column
     * @param   string  $str_column_content
     * @return  void
     */
    public function setColumnContent($int_row, $int_column, $str_column_content)
    {
        if (!isset($this->table_row[$int_row]->fields[$int_column])) {
           // do nothing
           return;
        } else {
           $this->table_row[$int_row]->fields[$int_column]->value = $str_column_content;
        }
    }



    /**
     * Method to get content of specific column
     *
     * @param   integer $int_row
     * @param   integer $int_column
     * @param   string  $str_column_content
     * @return  mixed
     */
    public function getColumnContent($int_row, $int_column, $str_column_content)
    {
        if (isset($this->table_row[$int_row]->fields[$int_column])) {
            return $this->table_row[$int_row]->fields[$int_column]->value;
        } else {
            return null;
        }
    }


    /**
     * Method to set specific column attribute
     *
     * @param   integer $int_row
     * @param   integer $int_column
     * @param   string  $str_column_attr
     * @return  void
     */
    public function setCellAttr($int_row = 0, $int_column = null, $str_column_attr)
    {
        if (is_null($int_column)) {
            $this->table_row[$int_row]->all_cell_attr = $str_column_attr;
        } else {
            $this->cell_attr[$int_row][$int_column] = $str_column_attr;
        }
    }


    /**
     * Method to print out table
     *
     * @return string
     */
    public function printTable()
    {
	
        
	$_buffer = '<table '.$this->table_attr.'>'."\n";

        // check if the array have a records
        if (count($this->table_row) < 1) {
//added and Commented by Start Parth 23/8/2011
	    //$_buffer .= '<tr><td align="center" style="color: red; background-color: #CCCCCC;">'.__('No Data').'</td></tr>';	
            $_buffer .= '<tr><td align="center" style="color: red; background-color: #CCCCCC;">'.__('Not Available').'</td></tr>';
//added and Commented by End Parth 23/8/2011
        } else {
            // set header style if exist
//            if($_GET['member_type']=='0')
//                $type='Admin';
//            if($_GET['member_type']=='1')
//                $type='Staff';
//            if($_GET['member_type']=='2')
//                $type='Student';
//            if($_GET['member_type']=='3')
//                $type='Parent';
//            if($_GET['member_type']=='4')
//                $type='Employee';
            $this->setCellAttr(0, null, $this->table_header_attr);
//            $_buffer .= '<tr><td>Report By:</td><td>'.$type.'</td></tr>';
            // records
            $_record_row = 0;
            foreach ($this->table_row as $_row_idx => $_row) {
                if (!$_row instanceof simbio_table_row) {
                    continue;
                }
                // check for row highlights
                if ($this->highlight_row AND $_row_idx > 0) {
                    // higlight row special attributes
                    //$_row->attr .= ' id="row'.$_record_row.'" onmouseover="highlightRow(\'row'.$_record_row.'\')" onmouseout="unHighlightRow(\'row'.$_record_row.'\')"';
                }
                // print out the row objects
                $_buffer .= '<tr '.$_row->attr.'>';
                foreach ($_row->fields as $_field_idx => $_field) {
                    if ($_row->all_cell_attr) {
                        $_field->attr = $_row->all_cell_attr;
                    }
                    if (isset($this->cell_attr[$_row_idx][$_field_idx])) {
                        $_field->attr = $this->cell_attr[$_row_idx][$_field_idx];
                    }                     
                    $_buffer .= '<td '.$_field->attr.'>'.$_field->value.'</td>';
                }
                $_buffer .= '</tr>'."\n";
                $_record_row++;
            }
        }

        $_buffer .= '</table>'."\n";
           
        return $_buffer;
    }
    
    
    public function printTable_custom($gmd_main)
    {
	
        
	//$_buffer = '<table '.$this->table_attr.'>'."\n";
        
        $_buffer = '<table align="center" class="dataListPrinted" cellpadding="0" cellspacing="0">'."\n";
        

        // check if the array have a records
        if (count($this->table_row) < 1) 
        {
//added and Commented by Start Parth 23/8/2011
	    //$_buffer .= '<tr><td align="center" style="color: red; background-color: #CCCCCC;">'.__('No Data').'</td></tr>';	
            $_buffer .= '<tr><td align="center" style="color: red; background-color: #CCCCCC;">'.__('Not Available').'</td></tr>';
//added and Commented by End Parth 23/8/2011
        }
        else
        {
            if($_GET['gmd_main']=='0')
                $type='Resource Type';
            if($_GET['gmd_main']=='1')
                $type='Material Type';
            if($_GET['gmd_main']=='2')
                $type='Material Sub Type';
            if($_GET['member_type']=='0')
                $type='Admin';
            if($_GET['member_type']=='1')
                $type='Staff';
            if($_GET['member_type']=='2')
                $type='Student';
            if($_GET['member_type']=='3')
                $type='Parent';
            if($_GET['member_type']=='4')
                $type='Employee';
            // set header style if exist
            $this->setCellAttr(0, null, $this->table_header_attr);
$_buffer .= '<tr><td>Report By:</td><td>'.$type.'</td></tr>';
            // records
            $_record_row = 0;
            foreach ($this->table_row as $_row_idx => $_row) 
            {
              if ($_record_row !=0) 
              {
               
               $id=$_row->fields[6]->value;
               
              }
               
                if (!$_row instanceof simbio_table_row) 
                {
                    continue;
                }
                // check for row highlights
                if ($this->highlight_row AND $_row_idx > 0) 
                {
                    
                    // higlight row special attributes
                    $_row->attr .= ' id="row'.$_record_row.'" onmouseover="highlightRow(\'row'.$_record_row.'\')" onmouseout="unHighlightRow(\'row'.$_record_row.'\')"';
                }
                // print out the row objects
                $_buffer .= '<tr '.$_row->attr.'>';
                $i=0;
                foreach ($_row->fields as $_field_idx => $_field)
                {
                                      
                                                         
                    if ($_row->all_cell_attr) 
                    {
                        $_field->attr = $_row->all_cell_attr;
                    }
                    if (isset($this->cell_attr[$_row_idx][$_field_idx])) 
                    {
                        $_field->attr = $this->cell_attr[$_row_idx][$_field_idx];
                    }                     
                    
                  /*  if ($_field->value == "material")
                        $_buffer .= '<td '.$_field->attr.'>'.$_field->value.'</td>';
                    else
                        $_buffer .= '<td '.$_field->attr.'>hello</td>';*/
                      
                    //$_buffer .= '<td '.$_field->attr.'>'.$i.'</td>';
                    
                    //echo '<td>';
                  // echo "<a href='#' onclick='b($gmd_d[8],$_GET[gmd_main],1);return false;'>";echo $gmd_d['3'];echo '</a>';
                   //echo $gmd_d['3'];
                   //echo '</td>';
                    
               //echo "<a href='#' onclick='b($gmd_d[8],$_GET[gmd_main],1);return false;'>";echo $gmd_d['3'];echo '</a>';
                   //onclick='b($gmd_d[8],$_GET[gmd_main],1);return false;'
                    //.','.$gmd_main.
                    
                    
//                        $gmd_d=$_field->value;
                   if(($_field->value)<0){
                       $_field->value=0;
                   }
	         if($id=='5' || $id=='13' || $id=='112' || $id=='113' || $id=='115' || $id=='117' || $id=='118'){
                   if($i=='2'||$i=='3' || $i=='4' || $i=='5')
			$_field->value='N/A';
                  }
                    
                                        
                    if($i==3 && $_record_row !=0)
                        $_buffer .= '<td '. $_field->attr .' align="center"><a href="#" onclick=b('.$id.','.$gmd_main.",1);>".$_field->value .'</a></td>';
                    elseif($i==4 && $_record_row !=0)
                          $_buffer .= '<td '. $_field->attr .' align="center"><a href="#" onclick=c('.$id.','.$gmd_main.',1);>'.$_field->value .'</a></td>';
	   
                    elseif($i==5 && $_record_row !=0)
                          $_buffer .= '<td '. $_field->attr .' align="center"><a href="#" onclick=d('.$id.','.$gmd_main.',1);>'.$_field->value .'</a></td>';                    
                    elseif($i==7 )
                          $_buffer .= '<td '.$_field->attr.' style="display:none" >'.$_field->value.'</td>';
                    elseif($i==8 )
                          $_buffer .= '<td '.$_field->attr.' style="display:none" >'.$_field->value.'</td>';
                    elseif($i==0)                                       
                        $_buffer .= '<td '.$_field->attr.'>'.$_field->value.'</td>';
                    else                                        
                        $_buffer .= '<td '.$_field->attr.' align="center">'.$_field->value.'</td>';
                        
                    
                    
                $i=$i+1;                    

                }
                $_buffer .= '</tr>'."\n";                
                $_record_row++;
            }
            
        }

        $_buffer .= '</table>'."\n";
        return $_buffer;
    }
    
}
?>
