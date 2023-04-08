<?php
class biblio_list
{
   
    /* Private properties */
    private $obj_db = false;
    private $resultset = false;
    private $num2show = 10;
    private $subquery = array();
    private $biblio_ids = array();
    private $emulate_short_word_search = false;
    private $queries_word_num_allowed = 20;
    private $query_error;
    /* Public properties */
    public $num_rows = 0;
    public $xml_detail = true;
    public $xml_result = true;
    public $only_promoted = false;
    public $show_labels = true;
    public $stop_words = array('a', 'an', 'of', 'the', 'to', 'so', 'as', 'be');
    public $query_time = 0;
	public $disable_item_data = false;
    /* Protected properties */
    protected $criteria = array();
    protected $label_cache = array();
    protected $custom_fields = array();
    protected $enable_custom_frontpage = false;
    protected $orig_query;
    protected $searchable_fields = array('title', 'author', 'subject', 'isbn',
		'publisher', 'gmd', 'notes', 'colltype', 'publishyear',
		'location', 'itemcode', 'callnumber', 'itemcallnumber', 'notes','letter');
    
    protected $field_join_type = array('title' => 'OR', 'author' => 'OR', 'subject' => 'OR');
    protected $current_page = 1;


    /**
     * Class Constructor
     *
     * @param   object  $obj_db
     */
    public function __construct($obj_db)
    {
        $this->obj_db = $obj_db;
    }


    /**
     * Method to set search criteria
     *
     * @param   string  $str_criteria
     * @return  void
     */
    public function setSQLcriteria($str_criteria)
    {
        
	
        if (!$str_criteria)
            return null;
        // defaults
        $_sql_criteria = '';
        $_searched_fields = array();
        $_title_buffer = '';
        $_previous_field = '';
        $_boolean = '';
        // parse query
        $this->orig_query = $str_criteria;
	$_queries = simbio_tokenizeCQL($str_criteria, $this->searchable_fields, $this->stop_words, $this->queries_word_num_allowed);
	
       // var_dump($_queries);
       if (count($_queries) < 1) {
            return null;
       }
        // loop each query
        foreach ($_queries as $_num => $_query) 
        {
            // field
           $_field = $_query['f'];
	
            // for debugging purpose only
          //  echo "<p>$_num. $_field -> $_boolean -> $_sql_criteria</p><p>&nbsp;</p>";
            // boolean
            if ($_title_buffer == '' && $_field != 'boolean') 
            {
                $_sql_criteria .= " $_boolean ";
            }
            // $_sql_criteria .= " $_boolean ";
            // flush title string concatenation
            if ($_field != 'title' && $_title_buffer !='') 
            {
                $_title_buffer = trim($_title_buffer);
              //$_sql_criteria .= " biblio.biblio_id IN(SELECT DISTINCT biblio_id FROM biblio WHERE MATCH (title, series_title) AGAINST ('$_title_buffer' IN BOOLEAN MODE)) ";
              
                $_sql_criteria .= "and title like '$_GET[letter]%' ";
                // reset title buffer
                $_title_buffer = '';
            }


 	
            //  break the loop if we meet `cql_end` field
            if ($_field == 'cql_end') { break; }
            // boolean mode
            $_b = isset($_query['b'])?$_query['b']:$_query;
            if ($_b == '*') {
                $_boolean = 'OR';
            } else { $_boolean = 'AND'; }
            // search value
            $_q = @$this->obj_db->escape_string($_query['q']);
	
            // searched fields flag set
            $_searched_fields[$_field] = 1;
            $_previous_field = $_field;
            // check field

       

            if ($_field == 'title') {
		
		
 		if (strlen($_q)< 4)
		 {
		  
                    $_previous_field = 'title_short';
                 // $_sql_criteria .= " biblio.title LIKE '%$_q%' ";
 		 $_sql_criteria .= " biblio.title LIKE '$_q' ";//added by iresh on 17-3-2011
                    $_title_buffer = '';
                }
	      
 		
			 else {
                    if (isset($_query['is_phrase'])) {
                        $_title_buffer .= ' '.$_b.'"'.$_q.'"';
                    } else {
                        $_title_buffer .= ' '.$_b.$_q;
                    }
                }
            }
	 
	
		 else if ($_field == 'author') {
                if ($_b == '-') {
                    $_sql_criteria .= " biblio.biblio_id NOT IN(SELECT ba.biblio_id FROM biblio_author AS ba"
                        ." LEFT JOIN mst_author AS a ON ba.author_id=a.author_id"
                        ." WHERE author_name LIKE '%$_q%')";
                } else {
                    $_sql_criteria .= " biblio.biblio_id IN(SELECT ba.biblio_id FROM biblio_author AS ba"
                        ." LEFT JOIN mst_author AS a ON ba.author_id=a.author_id"
                        ." WHERE author_name LIKE '%$_q%')";
                }
            } else if ($_field == 'subject') {
                if ($_b == '-') {
                    $_sql_criteria .= " biblio.biblio_id NOT IN(SELECT bt.biblio_id FROM biblio_topic AS bt"
                        ." LEFT JOIN mst_topic AS t ON bt.topic_id=t.topic_id"
                        ." WHERE topic LIKE '%$_q%')";
                } 
		
		else {
                    $_sql_criteria .= " biblio.biblio_id IN(SELECT bt.biblio_id FROM biblio_topic AS bt"
                        ." LEFT JOIN mst_topic AS t ON bt.topic_id=t.topic_id"
                        ." WHERE topic LIKE '%$_q%')";
                }
                // reset title buffer
                $_title_buffer = '';
            }

              

		 else {
                switch ($_field) {
                    case 'location' :
						if (!$this->disable_item_data) {
							$_subquery = 'SELECT location_id FROM mst_location WHERE location_name=\''.$_q.'\'';
							if ($_b == '-') {
								$_sql_criteria .= " item.location_id NOT IN ($_subquery)";
							} else { $_sql_criteria .= " item.location_id IN ($_subquery)"; }
						} else {
							if ($_b == '-') {
								$_sql_criteria .= " biblio.node_id !='$_q'";
							} else { $_sql_criteria .= " biblio.node_id = '$_q'"; }
						}
                        break;
                    case 'colltype' :
						if (!$this->disable_item_data) {
							$_subquery = 'SELECT coll_type_id FROM mst_coll_type WHERE coll_type_name=\''.$_q.'\'';
							if ($_b == '-') {
								$_sql_criteria .= " item.coll_type_id NOT IN ($_subquery)";
							} else { $_sql_criteria .= " item.coll_type_id IN ($_subquery)"; }
						}
                        break;
                    case 'itemcode' :
						if (!$this->disable_item_data) {
							if ($_b == '-') {
								$_sql_criteria .= " item.item_code != '$_q'";
							} else { $_sql_criteria .= " item.item_code LIKE '$_q%'"; }
						}
                        break;
                    case 'callnumber' :
                        if ($_b == '-') {
                            $_sql_criteria .= ' AND biblio.call_number NOT LIKE \''.$_q.'%\'';
                        } else { $_sql_criteria .= ' biblio.call_number LIKE \''.$_q.'%\''; }

                        break;
                    case 'itemcallnumber' :
						if (!$this->disable_item_data) {
							if ($_b == '-') {
								$_sql_criteria .= ' AND item.call_number NOT LIKE \''.$_q.'%\'';
							} else { $_sql_criteria .= ' item.call_number LIKE \''.$_q.'%\''; }

						}
                        break;
                    case 'class' :
                        if ($_b == '-') {
                            $_sql_criteria .= ' AND biblio.classification NOT LIKE \''.$_q.'%\'';
                        } else { $_sql_criteria .= ' biblio.classification LIKE \''.$_q.'%\''; }
                        break;
                    case 'isbn' :
                        if ($_b == '-') {
                            $_sql_criteria .= ' AND biblio.isbn_issn!=\''.$_q.'\'';
                        } else { $_sql_criteria .= ' biblio.isbn_issn=\''.$_q.'\''; }
                        break;
                    case 'publisher' :
                        $_subquery = 'SELECT publisher_id FROM mst_publisher WHERE publisher_name LIKE \'%'.$_q.'%\'';
                        if ($_b == '-') {
                            $_sql_criteria .= " biblio.publisher_id NOT IN ($_subquery)";
                        } else { $_sql_criteria .= " biblio.publisher_id IN ($_subquery)"; }
                        break;
                    case 'publishyear' :
                        if ($_b == '-') {
                            $_sql_criteria .= ' AND biblio.publish_year!=\''.$_q.'\'';
                        } else { $_sql_criteria .= ' biblio.publish_year=\''.$_q.'\''; }
                        break;
                    case 'gmd' :
                        $_subquery = 'SELECT gmd_id FROM mst_gmd WHERE gmd_name=\''.$_q.'\'';
                        if ($_b == '-') {
                            $_sql_criteria .= " biblio.gmd_id NOT IN ($_subquery)";
                        } else { $_sql_criteria .= " biblio.gmd_id IN ($_subquery)"; }
                        break;
                    case 'notes' :
                        if ($_b == '-') {
                            $_sql_criteria .= " NOT (MATCH (biblio.notes) AGAINST ('".$_q."', IN BOOLEAN MODE))";
                        } else { $_sql_criteria .= " (MATCH (biblio.notes) AGAINST ('".$_q."', IN BOOLEAN MODE))"; }
                        break;
                }
            }
            
        }

        // remove boolean's logic symbol prefix and suffix
        $_sql_criteria = preg_replace('@^(AND|OR|NOT)\s*|\s+(AND|OR|NOT)$@i', '', trim($_sql_criteria));
        // below for debugging purpose only
        // echo "<div style=\"border: 1px solid #ff0000; padding: 5px; color: #ff0000; margin: 5px;\">$_sql_criteria</div>";

        $this->criteria = array('sql_criteria' => $_sql_criteria, 'searched_fields' => $_searched_fields);
        /*echo '<pre>';
        print_r($this->criteria);
        echo '<pre>';die;*/
        return $this->criteria;
    }


    /**
     * Method to print out document records
     *
     * @param   object  $obj_db
     * @param   integer $int_num2show
     * @param   boolean $bool_return_output
     * @return  string
     */
    public function getDocumentList($int_num2show = 10, $bool_return_output = true)
    {
       
        global $sysconf;
        $this->num2show = $int_num2show;
        // get page number from http get var
        if (!isset($_GET['page']) OR $_GET['page'] < 1){
            $_page = 1;
        } else{
            $_page = (integer)$_GET['page'];
        }
        $this->current_page = $_page;

        // count the row offset
        if ($_page <= 1) {
            $_offset = 0;
        } else {
            $_offset = ($_page*$this->num2show) - $this->num2show;
        }

        // init sql string
        $_sql_str = 'SELECT SQL_CALC_FOUND_ROWS biblio.file_att,biblio.biblio_id, biblio.title, biblio.image, biblio.isbn_issn, biblio.labels';

        // checking custom frontpage fields file
        $custom_frontpage_record_file = (defined('UCS_BASE_DIR')?UCS_BASE_DIR:SENAYAN_BASE_DIR).$sysconf['template']['dir'].'/'.$sysconf['template']['theme'].'/custom_frontpage_record.inc.php';
        if (file_exists($custom_frontpage_record_file)) {
            include $custom_frontpage_record_file;
            $this->enable_custom_frontpage = true;
            $this->custom_fields = $custom_fields;
            foreach ($this->custom_fields as $_field => $_field_opts) {
                if ($_field_opts[0] == 1 && !in_array($_field, array('availability', 'isbn_issn'))) {
                    $_sql_str .= ", biblio.$_field";
                }
            }
        }

        // additional SQL string
        $_add_sql_str = '';

        // location
        if ($this->criteria) 
                {
            if (isset($this->criteria['searched_fields']['location']) || isset($this->criteria['searched_fields']['colltype'])) {
                if (!$this->disable_item_data) {
					$_add_sql_str .= ' LEFT JOIN item ON biblio.biblio_id=item.biblio_id ';
				}
            }
        }

        $_add_sql_str .= ' WHERE opac_hide=0 ';
        // promoted flag
        if ($this->only_promoted) { $_add_sql_str .= ' AND promoted=1'; }
        // main search criteria
        if ($this->criteria) {
            $_add_sql_str .= ' AND ('.$this->criteria['sql_criteria'].') ';
        }

        $_sql_str .= ' FROM biblio '.$_add_sql_str.' ORDER BY biblio.last_update DESC LIMIT '.$_offset.', '.$this->num2show;
        
// for debugging purpose only
        // echo "<div style=\"border: 1px solid navy; padding: 5px; color: navy; margin: 5px;\">$_sql_str</div>";
        // start time
        
        $_start = function_exists('microtime')?microtime(true):time();
        
        // execute query
        $this->resultset = $this->obj_db->query($_sql_str);
        if ($this->obj_db->error) 
        {
            $this->query_error = $this->obj_db->error;
        }
        // get total number of rows from query
        $_total_q = $this->obj_db->query('SELECT FOUND_ROWS()');
        $_total_d = $_total_q->fetch_row();
        $this->num_rows = $_total_d[0];
        // end time
        $_end = function_exists('microtime')?microtime(true):time();
        $this->query_time = round($_end-$_start, 5);
        if ($bool_return_output) 
        {
         //    return the html result         
	 return $this->makeOutput();
	
	}
    }


    /**
     * Method to make an output of document records
     *
     * @return  string
     */
  /*  protected function makeOutput()
    {
	  
        global $sysconf;
        // init the result buffer
	$_buffer = '';
        //$redirect_string=$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'];
        // loop data
        $i = 1;
        if (!$this->resultset) 
        {
            return '<div style="border: 1px dotted #FF0000; color: #FF0000; padding: 5px; margin: 5px;">Query error : '.$this->query_error.'</div>';
        }
	echo"<br>";
	echo "<form method=post>";
 	echo '<table border=0 cellpadding=5 cellspacing=5 BORDERCOLOR=RED align=center>';
        //echo '<table border=0 cellpadding=5 cellspacing=5 BORDERCOLOR=RED align=center  class="new_tbl">';
        
        while ($_biblio_d = $this->resultset->fetch_assoc())
        {
	
		
	
	      $_biblio_d['title'] = '<a href="'.$sysconf['baseurl'].'index.php?p=show_detail&id='.$_biblio_d['biblio_id'].'" title="'.__('Record Detail').'">'.$_biblio_d['title'].'</a>';
            // label
          if ($this->show_labels AND !empty($_biblio_d['labels'])) 
          {
                $arr_labels = @unserialize($_biblio_d['labels']);
                if ($arr_labels !== false)
                {
	                foreach ($arr_labels as $label) 
                        {
	                    if (!isset($this->label_cache[$label[0]]['name'])) 
                            {
	                        $_label_q = $this->obj_db->query('SELECT label_name, label_desc, label_image FROM mst_label AS lb
	                            WHERE lb.label_name=\''.$label[0].'\'');
	                        $_label_d = $_label_q->fetch_row();
                                
                                
	                        $this->label_cache[$label[0]] = array('name' => $_label_d[0], 'desc' => $_label_d[1], 'image' => $_label_d[2]);
	                    }
	                    if (isset($label[1]) && $label[1]) 
                            {
	                       /* $_biblio_d['title'] .= ' <a href="'.$label[1].'" target="_blank"><img src="'.SENAYAN_WEB_ROOT_DIR.IMAGES_DIR.'/labels/'.$this->label_cache[$label[0]]['image'].'" title="'.$this->label_cache[$label[0]]['desc'].'" align="middle" class="labels" border="0" /></a>';*/
 /*                                   $_biblio_d['title'] .= ' <a href="'.$label[1].'" target="_blank" class="detailLink"> Connect</a>';
	                    } 
                            else 
                            {
	                    /*    $_biblio_d['title'] .= ' <img src="'.SENAYAN_WEB_ROOT_DIR.IMAGES_DIR.'/labels/'.$this->label_cache[$label[0]]['image'].'" title="'.$this->label_cache[$label[0]]['desc'].'" align="middle" class="labels" />'; */

/*                                $_biblio_d['title'] .= 'Connect';
	                    }
	                }
				}
            }
            
            // button
                echo   $_biblio_d['detail_button'] = '<a href="'.$sysconf['baseurl'].'index.php?p=show_detail&id='.$_biblio_d['biblio_id'].'" class="detailLink" title="'.__('Record Detail').'">'.__('Record Detail').'</a>';
	  
            
            if ($this->xml_detail) 
            {
               echo  $_biblio_d['xml_button'] = '<a href="'.$sysconf['baseurl'].'index.php?p=show_detail&inXML=true&id='.$_biblio_d['biblio_id'].'" class="xmlDetailLink" title="View Detail in XML Format" target="_blank">XML Detail</a>';
            } 
            else 
            {
              echo   $_biblio_d['xml_button'] = '';
            }

            // cover images var
            $_image_cover = '';
            if (!empty($_biblio_d['image']) && !defined('LIGHTWEIGHT_MODE')) 
            {
                $_biblio_d['image'] = urlencode($_biblio_d['image']);
                $images_loc = 'images/docs/'.$_biblio_d['image'];
                if (file_exists($images_loc)) {
                 //   $_image_cover = 'style="background-image: url(./lib/phpthumb/phpThumb.php?src=../../'.$images_loc.'&w=80); background-repeat: no-repeat;" onmouseout="javascript:zxcZoom(this);"  onmouseover="zxcZoom(this,300,300,1,C);" ' ;
       $_image_cover = 'style="background-image: url(./lib/phpthumb/phpThumb.php?src=../../'.$images_loc.'&w=80); background-repeat: no-repeat;" ' ;
		
  }
           }
		

	   /* $_alt_list = ($_i%3 == 0)?'alterList':'alterList2';
 	   
	   // $_buffer .='<tr>';
	    $_buffer .= '<td class="item '.$_alt_list.'" '.$_image_cover.'>'.$_biblio_d['title'].'</td>';
		if($i%3 == 0){
		echo '</tr><tr>';
		}
	*/
/*		 $_alt_list = ($_i%3 == 0)?'alterList':'alterList2';	
	 // echo '<tr>';
			
	   echo  '<td  align="left" valign="top" class="item '.$_alt_list.'" '.$_image_cover.' >'.$_biblio_d['title'].'<br />';
	   $total_item="select count(i.item_code) AS item_code,b.title AS title from item i
		     left join biblio b on b.biblio_id=i.biblio_id
                     where i.biblio_id=".$_biblio_d['biblio_id']."";
	    
           $total_item= $this->obj_db->query($total_item);
	    $total='';
	    while($row=$total_item->fetch_assoc())
	    {
	           $total.=$row['item_code'];

	    }
	   
	  $available_item="select count(i.item_code) AS item_code from item i
			 left join loan l on l.item_code=i.item_code
                         where i.biblio_id=".$_biblio_d['biblio_id']." AND l.is_return='0'";
	
	    $available_item=$this->obj_db->query($available_item);
	    $available_item1='';
	    while($available=$available_item->fetch_assoc())
	    {

	        $available_item1.=$available['item_code'];

	    }
            $available['item_code']=($total-$available_item1);
	    $available= $available['item_code'];
	
	    $t="select count(i.item_code) AS item_code,b.title AS title from item i

		     left join biblio b on b.biblio_id=i.biblio_id
                     where i.biblio_id=".$_biblio_d['biblio_id']."";

	    $t=$this->obj_db->query($t);
	    $tt='';
	    while($row=$t->fetch_assoc())
	    {
		 $tt.=$row['item_code'];
		
	    }

            //$_buffer .= '<div class="item '.$_alt_list.'" '.$_image_cover.'>'.$_biblio_d['title'].'<br />';
            // query the author
            $_author_q = $this->obj_db->query('SELECT a.author_name FROM biblio_author AS ba
                LEFT JOIN biblio AS b ON ba.biblio_id=b.biblio_id
                LEFT JOIN mst_author AS a ON ba.author_id=a.author_id WHERE ba.biblio_id='.$_biblio_d['biblio_id']);
            // concat author data
            $_authors = '';
            while ($_author_d = $_author_q->fetch_row()) 
            {
                $_authors .= $_author_d[0].' - ';
            }

	

          if ($_authors) 
          {
                // replace the last strip
                $_authors = substr_replace($_authors, '', -3);
                echo '<div class="subItem authorField"><b>'.__('Author(s)').'</b> : '.$_authors.'</div>';
		}
                $_subject_q = $this->obj_db->query('SELECT a.topic FROM biblio_topic AS ba
                LEFT JOIN biblio AS b ON ba.biblio_id=b.biblio_id
                LEFT JOIN mst_topic AS a ON ba.topic_id=a.topic_id WHERE ba.biblio_id='.$_biblio_d['biblio_id']);
            // concat author data
            $_subjects = '';
            while ($_subject_d = $_subject_q->fetch_row())
	    {
                $_subjects .= $_subject_d[0].' - ';
            }
	   $_subjects=substr($_subjects,0,-2);
	  echo '<div class="subItem authorField"><b>'.__('Subject(s)').'</b> : '.$_subjects.'</div>';
	 if($tt>0)
{
	   if($available>0)
	   {
	   	echo '<div class="subItem authorField"><b>'.__('Total Book').'</b> : '.$total.'</div>';
	        echo '<div class="subItem authorField"><b>'.__('Available Book').'</b> : '.$available.'</div>';
	
	       $sql="select item_code from item where biblio_id=".$_biblio_d['biblio_id']." AND item_code NOT IN 
		    (select item_code from loan where biblio_id=".$_biblio_d['biblio_id']." AND is_return=0) limit 0,1";
	        $sql=$this->obj_db->query($sql);
	        $s1='';
	        while($s=$sql->fetch_assoc())
	        {
	          $s1.=$s['item_code'];
	        }
		//echo "<div><input type=checkbox name='item' value='$s1'><a href=index.php?sub=".$s1.">Add To Cart</a></div>";
		
		if (utility::isMemberLogin())
		 {	

			

			echo "<div align=left class='subItem'><a class='detailLink' href=index.php?sub=".$s1.">Add To Cart</a></div>";
		 }


	   }
	   else
	   {

			echo '<div class="subItem authorField"><b>'.__('Total Book').'</b> : '.$total.'</div>';
	                echo '<div class="subItem authorField"><b>'.__('Available Book').'</b> : '.$available.'</div>';


			$due_item="select min(l.due_date) AS due_date from loan l
					  left join item i on i.item_code=l.item_code
					  left join biblio b on b.biblio_id=i.biblio_id
					  where i.biblio_id=".$_biblio_d['biblio_id']."";
				$due_item= $this->obj_db->query($due_item);
			$due1='';
			while($due=$due_item->fetch_assoc())
			{
				 $due1.=$due['due_date'];
			}
			  // echo "<td>".__('Currently On Loan (Due on').$due1."</td>";
			echo '<div align=left><strong width="50%" style="color: red;">'.__('All Copy Currently On Loan').'<br/>'.__('One Of The Copy Due On ').'('.date($sysconf['date_format'], strtotime($due1)).')</strong></div>'; 
			    

               $item_loan="select l.item_code AS item_code from loan l
			    left join item i on i.item_code=l.item_code
			    left join biblio b on b.biblio_id=i.biblio_id
		 	    where l.due_date ='$due1' AND i.biblio_id=".$_biblio_d['biblio_id']." ";
		$item_loan=$this->obj_db->query($item_loan);
		$item_l='';
		while($item_loan1=$item_loan->fetch_assoc())
		{
			$item_l.=$item_loan1['item_code'];
			
		}
		
		 
		//echo '<div><input type=checkbox name="item" value='.$item_l.'><input type="submit" name="sub" value="Add To Cart" ></div>'; 
		if (utility::isMemberLogin())
		 {		

		
		echo '<div class="subItem"><a class="detailLink" href=index.php?sub='.$item_l.'>Add To Cart</a></div>'; 

		}
		
	   }
           //echo "<div><input type='submit' name='sub' value='Add To Cart' ></div>";
}




else
{
	 echo '<div><strong style="font-weight: bold;">'.__('No Copy Available For This Book').'</strong></div>';
	  
	  
}

	
	
	

	    
	  
		
	

//}
	if($i%2 == 0)
         {
		echo '<tr>';
                
          }	

          /*  # checking custom file
            if ($this->enable_custom_frontpage AND $this->custom_fields) {
                foreach ($this->custom_fields as $_field => $_field_opts) {
                    if ($_field_opts[0] == 1) {
                        if ($_field == 'edition') {
                            $_buffer .= '<div class="customField editionField"><b>'.$_field_opts[1].'</b> : '.$_biblio_d['edition'].'</div>';
                        } else if ($_field == 'isbn_issn') {
                            $_buffer .= '<div class="customField isbnField"><b>'.$_field_opts[1].'</b> : '.$_biblio_d['isbn_issn'].'</div>';
                        } else if ($_field == 'collation') {
                            $_buffer .= '<div class="customField collationField"><b>'.$_field_opts[1].'</b> : '.$_biblio_d['collation'].'</div>';
                        } else if ($_field == 'series_title') {
                            $_buffer .= '<div class="customField seriesTitleField"><b>'.$_field_opts[1].'</b> : '.$_biblio_d['series_title'].'</div>';
                        } else if ($_field == 'call_number') {
                            $_buffer .= '<div class="customField callNumberField"><b>'.$_field_opts[1].'</b> : '.$_biblio_d['call_number'].'</div>';
                        } else if ($_field == 'availability' && !$this->disable_item_data) {
                            // get total number of this biblio items/copies
                            $_item_q = $this->obj_db->query('SELECT COUNT(*) FROM item WHERE biblio_id='.$_biblio_d['biblio_id']);
                            $_item_c = $_item_q->fetch_row();
                            // get total number of currently borrowed copies
                            $_borrowed_q = $this->obj_db->query('SELECT COUNT(*) FROM loan AS l INNER JOIN item AS i'
                                .' ON l.item_code=i.item_code WHERE l.is_lent=1 AND l.is_return=0 AND i.biblio_id='.$_biblio_d['biblio_id']);
                            $_borrowed_c = $_borrowed_q->fetch_row();
                            // total available
                            $_total_avail = $_item_c[0]-$_borrowed_c[0];
                            if ($_total_avail < 1) {
                                $_buffer .= '<div class="customField availabilityField"><b>'.$_field_opts[1].'</b> : <strong style="color: #FF0000;">none copy available</strong></div>';
                            } else {
                                $_buffer .= '<div class="customField availabilityField"><b>'.$_field_opts[1].'</b> : '.$_total_avail.' copies available for loan</div>';
                            }
                        } else if ($_field == 'node_id' && $this->disable_item_data) {
							$_buffer .= '<div class="customField locationField"><b>'.$_field_opts[1].'</b> : '.$sysconf['node'][$_biblio_d['node_id']]['name'].'</div>';
						}
                    }
                }
            }

            $_buffer .= '<td class="subItem">'.$_biblio_d['detail_button'].' '.$_biblio_d['xml_button'].'</td>';
            $_buffer .= "</td>";
  	   $_buffer .= "</tr>";*/

    
/*     $i++;  
        }
	if($i%2 == 0)
        {
	echo '</tr>';
	}
echo "</table>";  
echo "</form>";

     // free resultset memory
        $this->resultset->free_result();
 
        // paging
        if (($this->num_rows > $this->num2show)) {
            $_paging = '<hr width="97%" size="1" />'."\n";
            $_paging .= '<div style="text-align: center;">'.simbio_paging::paging($this->num_rows, $this->num2show, 5).'</div>';
        } else {
            $_paging = '';
        }
        
        return $_buffer.$_paging;

 }
*/


    
    protected function makeOutput()
    {
        
        global $sysconf;
        // init the result buffer
	$_buffer = '';
        //echo "<form action=\"index.php\" method=\"post\">";
        // loop data
        $redirect_string=$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'];
        echo"<form action='".$redirect_string."' method=\"post\">";
        $i = 1;
        
        if (!$this->resultset) 
        {
            
            return '<div style="border: 1px dotted #FF0000; color: #FF0000; padding: 5px; margin: 5px;">Query error : '.$this->query_error.'</div>';
        }
	echo"<br>";
        

        
	if($_GET['p']!='rescentview' && $_GET['p']!='myeself')
	{
            
         	echo '<table cellpadding=5 cellspacing=5 align=center width=80% class="content1">';
	}
	else
	{
           
            echo '<table cellpadding=5 cellspacing=5 align=center width=80% class="content1">';
	}

        if ($this->resultset->num_rows==0) 
        {
            
            echo "<th align='center'>No Data Found.</th>";
        }
        
        while ($_biblio_d = $this->resultset->fetch_assoc()) 
         {
            
            
		/* $sql='select g.gmd_code as gmd_code,b.gmd_id from biblio b
			   Left Join mst_gmd g on g.gmd_id = b.gmd_id	

		           where biblio_id='.$_biblio_d['biblio_id'].'';
		     $sql=$this->obj_db->query($sql);
		     $gm_code='';
		   while($row=$sql->fetch_assoc())
		 {
		  	 $gm_code.=$row['gmd_code'].',';

	    	 }
			echo $gm_code=substr($gm_code,0,-1);*/
                $myurl = "https://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];                
                $raw_url = parse_url($myurl);
                
                $domain_only =str_replace ('www.','', $raw_url);
                $domain_only['host']; 

                //start commented and coded by Parth 11/7/2011 
                //$_biblio_d['title'] = '<a href="'.$sysconf['baseurl'].'index.php?p=show_detail&id='.$_biblio_d['biblio_id'].'&gmd_code='.$_biblio_d['gmd_code'].'" title="'.__('Record Detail').'">'.$_biblio_d['title'].'</a>';
              /*  $set_title = '<a href="'.$sysconf['baseurl'].'index.php?p=show_detail&id='.$_biblio_d['biblio_id'].'&gmd_code='.$_biblio_d['gmd_code'].'" title="'.__('Record Detail').'">...more</a>';
                
                //end commented and coded by Parth 11/7/2011 
                // label
                  if ($this->show_labels AND !empty($_biblio_d['labels']))
                  {

                      /*$a=@unserialize($_biblio_d['labels']);
                      echo "<pre>";
                      print_r($a);
                      echo "<pre>";die;*/
       /*                   $arr_labels = @unserialize($_biblio_d['labels']);
                           if ($arr_labels !== false) 
                           {

                                foreach ($arr_labels as $label) 
                                {
                                    if (!isset($this->label_cache[$label[0]]['name']))
                                    {

                                        $_label_q = $this->obj_db->query('SELECT label_name, label_desc, label_image FROM mst_label AS lb WHERE lb.label_name=\''.$label[0].'\'');
                                        $_label_d = $_label_q->fetch_row();
                                        $this->label_cache[$label[0]] = array('name' => $_label_d[0], 'desc' => $_label_d[1], 'image' => $_label_d[2]);
                                    }
                                    if (isset($label[1]) && $label[1] && $label[1]!="NULL") 
                                    {
                                       /* $_biblio_d['title'] .= ' <a href="'.$label[1].'" target="_blank"><img src="'.SENAYAN_WEB_ROOT_DIR.IMAGES_DIR.'/labels/'.$this->label_cache[$label[0]]['image'].'" title="'.$this->label_cache[$label[0]]['desc'].'" align="middle" class="labels" border="0" /></a>';*/

        // Start commented and code by Parth 11/7/2011	
           //$_biblio_d['title'] .= ' <a href="'.$label[1].'" target="_blank" class="detailLink">Connect</a>';
        //added and commented start by Parth 5/8/2011
        //$_biblio_d['title'] = ' <a href="'.$label[1].'" target="_blank">'.$_biblio_d['title'].'</a>';
        /*if($_biblio_d['material_sub_id']=='115' || $_biblio_d['material_sub_id']=='97')
        {
        $_biblio_d['title'] = ' <a href="javascript:void(0)" onclick=javascript:window.open("lib/vediodisplay.php?url='.$label[1].'","Window1","menubar=no,width=1200,height=800,toolbar=no,scrollbars=yes,location=no,left=200px");>'.$_biblio_d['title'].'</a>';
        }
        else
        {*/
/*       $_biblio_d['title'] = ' <a href="javascript:void(0)" onclick=javascript:window.open("'.$label[1].'","Window1","menubar=no,width=1200,height=800,toolbar=no,scrollbars=yes,location=no,left=200px");>'.$_biblio_d['title'].'</a>';

        /*}*/
        // Ended commented and code by Parth 11/7/2011	
        //added and commented end by Parth 5/8/2011


  /*                                  } 
                                    else 
                                    {

                                       /* $_biblio_d['title'] .= ' <img src="'.SENAYAN_WEB_ROOT_DIR.IMAGES_DIR.'/labels/'.$this->label_cache[$label[0]]['image'].'" title="'.$this->label_cache[$label[0]]['desc'].'" align="middle" class="labels" />';*/
        // Start commented and code by Parth 11/7/2011	
              //$_biblio_d['title'] .= 'Connect';
 /*       $_biblio_d['title'] = $_biblio_d['title'];
        // Ended commented and code by Parth 11/7/2011	

                                    }


                                }


                                        }
                    }
                    
                    
                    
                    */
                
           //$_biblio_d['title'] = '<a href="'.$sysconf['baseurl'].'index.php?p=show_detail&id='.$_biblio_d['biblio_id'].'" title="'.__('Record Detail').'">'.$_biblio_d['title'].'</a>';
            
                 IF($_biblio_d['material_resource_id']==14){
                    
                   $_biblio_d['title'] = '<a href="'.$sysconf['baseurl'].'index.php?p=show_detail&id='.$_biblio_d['biblio_id'].'" title="'.__('Record Detail').'">'.$_biblio_d['title'].'</a><br>';
                }
                ELSE
                {
                   // echo 'hiii';
                    $a=array();
                    $a=explode('"',$_biblio_d[labels]);
                    //$b=$_biblio_d['file_att'];
                   // $i=0;
                    //$_biblio_d['title1']='';
                   // $var=array();
                    $_biblio_d['title'] = '<b><u>'.$_biblio_d['title'].'</u></b><br>';
                    foreach($a as $record)
                    {
                        $b=substr($record,0,3);
                        
                        //$_biblio_d['title'] ='';
                        if ($b=='htt')
                        {
                            //$_biblio_d['title']='';
                            
                            $_biblio_d['title'] .= '<a href='.$record.' title="'.__('Record Detail').'">'.$record.'</a><br>';
                                 
                             //echo '<br>';
                            //echo $_biblio_d['title'];
                        }    
                        
                       //if($a[$record[$i]][] == ){
                        //$_biblio_d['title'] = '<a href='.$_biblio_d[labels].' title="'.__('Record Detail').'">'.$_biblio_d['title'].'</a>'; 
                     //}
                     //  $i=$i+1;
                    }  
                    $_biblio_d['title'] .= '<b>Attachment File: </b> '.$_biblio_d['file_att'];
                   }
                
            // label
          if ($this->show_labels AND !empty($_biblio_d['labels'])) 
          {
                $arr_labels = @unserialize($_biblio_d['labels']);
                if ($arr_labels !== false)
                {
	                foreach ($arr_labels as $label) 
                        {
	                    if (!isset($this->label_cache[$label[0]]['name'])) 
                            {
	                        $_label_q = $this->obj_db->query('SELECT label_name, label_desc, label_image FROM mst_label AS lb
	                            WHERE lb.label_name=\''.$label[0].'\'');
	                        $_label_d = $_label_q->fetch_row();
                                
                                
	                        $this->label_cache[$label[0]] = array('name' => $_label_d[0], 'desc' => $_label_d[1], 'image' => $_label_d[2]);
	                    }
	                    if (isset($label[1]) && $label[1]) 
                            {
	                       /* $_biblio_d['title'] .= ' <a href="'.$label[1].'" target="_blank"><img src="'.SENAYAN_WEB_ROOT_DIR.IMAGES_DIR.'/labels/'.$this->label_cache[$label[0]]['image'].'" title="'.$this->label_cache[$label[0]]['desc'].'" align="middle" class="labels" border="0" /></a>';*/
                           //         $_biblio_d['title'] .= ' <a href="'.$label[1].'" target="_blank" class="detailLink"> Connect</a>';
	                    } 
                            else 
                            {
	                     //   $_biblio_d['title'] .= ' <img src="'.SENAYAN_WEB_ROOT_DIR.IMAGES_DIR.'/labels/'.$this->label_cache[$label[0]]['image'].'" title="'.$this->label_cache[$label[0]]['desc'].'" align="middle" class="labels" />'; */

                               // $_biblio_d['title'] .= 'Connect';
	                    }
	                }
				}
            }
                
                    
                    // button
                    $_biblio_d['detail_button'] = '<a href="'.$sysconf['baseurl'].'index.php?p=show_detail&id='.$_biblio_d['biblio_id'].'" class="detailLink" title="'.__('Record Detail').'">'.__('Record Detail').'</a>';
           
                    if ($this->xml_detail)
                     {
                        $_biblio_d['xml_button'] = '<a href="'.$sysconf['baseurl'].'index.php?p=show_detail&inXML=true&id='.$_biblio_d['biblio_id'].'" class="xmlDetailLink" title="View Detail in XML Format" target="_blank">XML Detail</a>';

                    }
                    else 
                    {

                        $_biblio_d['xml_button'] = '';

                    }

            // cover images var
                    $_image_cover = '';
                    if (!empty($_biblio_d['image']) && !defined('LIGHTWEIGHT_MODE'))
                    {
                        $_biblio_d['image'] = urlencode($_biblio_d['image']);
                        $images_loc = "images/docs/".urldecode($_biblio_d['image']);                
                        if (file_exists($images_loc)) 
                        {
                         //   $_image_cover = 'style="background-image: url(./lib/phpthumb/phpThumb.php?src=../../'.$images_loc.'&w=80); background-repeat: no-repeat;" onmouseout="javascript:zxcZoom(this);"  onmouseover="zxcZoom(this,300,300,1,C);" ' ;
              // $_image_cover = 'style="background-image: url(./lib/phpthumb/phpThumb.php?src=../../'.$images_loc.'&w=80); background-repeat: no-repeat;" ' ;
                 //$_image_cover = '<img src='.$images_loc.' width="80px" height="120px" style="float:left; margin-left:-85px;" >' ;
        $_image_cover = "<img id='".$_biblio_d['biblio_id']."' src='".$images_loc."' width='80px' height='120px' style='' onmouseover='mover(this.id)' onmouseout='mout(this.id)'>" ;


                         }


                    }
		

	   /* $_alt_list = ($_i%3 == 0)?'alterList':'alterList2';
 	   
	   // $_buffer .='<tr>';
	    $_buffer .= '<td class="item '.$_alt_list.'" '.$_image_cover.'>'.$_biblio_d['title'].'</td>';
		if($i%3 == 0){
		echo '</tr><tr>';
		}
	*/
		 $_alt_list = ($_i%3 == 0)?'alterList':'alterList2';	
	 // echo '<tr>';
//added by Parth 13/7/2011 & 6/8/2011	
	/*if(!empty($_image_cover))
	{*/
                 if($_GET['p']!='rescentview' && $_GET['p']!='myeself')
                {		
                // echo  '<td  align="left" valign="top" width=30%> '.$_image_cover.'<br/>'.$_biblio_d['title'];
                 echo  '<tr class="alterCell3"><td  align="left" valign="top">'.$_biblio_d['title'];  
                 }
                 else
                {
                echo  '<tr class="alterCell3"><td  align="left" valign="top"> '.$_biblio_d['title'];   
                 //echo  '<td  align="left" valign="top"> '.$_image_cover.'<br/>'.$_biblio_d['title'];   
                 }  
	  // echo  '<td  align="left" valign="top" class="item '.$_alt_list.'"> '.$_image_cover.''.$_biblio_d['title'].'<br />';
	/*}
	else
	{
$img_set = $_biblio_d['isbn_issn'];
          if($_GET['p']!='rescentview' && $_GET['p']!='myeself')
	{
	echo '<td  align="left" valign="top"> '; 
        }
        else
        {
        echo '<td  align="left" valign="top"> '; 
        }  
	//include('test1.php');
	//echo '<br/>'.$_biblio_d['title'].'';
        echo $_biblio_d['title'].'';
	}*/
            if($_GET['p']=="myeself")
            {
            echo '<div align="left"><a href=index.php?set_delete='.$_biblio_d['biblio_id'].'&p=myeself>Delete</a></div><br/>';
            }
//ended by Parth 13/7/2011 & 6/8/2011
	   $total_item="select count(i.item_code) AS item_code,b.title AS title from item i
		     left join biblio b on b.biblio_id=i.biblio_id
                     where i.biblio_id=".$_biblio_d['biblio_id']."";
	    $total_item= $this->obj_db->query($total_item);
	    $total='';
	    while($row=$total_item->fetch_assoc())
	    {
	           $total.=$row['item_code'];

	    }
	   /////$available_item Means NOT AVAILABLE ITEM
	  $available_item="select count(i.item_code) AS item_code from item i
			 left join loan l on l.item_code=i.item_code
                         where i.biblio_id=".$_biblio_d['biblio_id']." AND l.loan_date is not null AND l.return_date is null";
	
          
	    $available_item=$this->obj_db->query($available_item);
	    $available_item1='';
	    while($available=$available_item->fetch_assoc())
	    {

	        $available_item1.=$available['item_code'];

	    }
            $available['item_code']=($total-$available_item1);
	    $available= $available['item_code'];
	
	  $t="select count(i.item_code) AS item_code,b.title AS title from item i

		     left join biblio b on b.biblio_id=i.biblio_id
                     where i.biblio_id=".$_biblio_d['biblio_id']."";

	    $t=$this->obj_db->query($t);
	    $tt='';
	    while($row=$t->fetch_assoc())
	    {
		 $tt.=$row['item_code'];
		
	    }

            //$_buffer .= '<div class="item '.$_alt_list.'" '.$_image_cover.'>'.$_biblio_d['title'].'<br />';
            // query the author
            //echo 'SELECT a.author_name FROM biblio_author AS ba
              //  LEFT JOIN biblio AS b ON ba.biblio_id=b.biblio_id
                //LEFT JOIN mst_author AS a ON ba.author_id=a.author_id WHERE ba.biblio_id='.$_biblio_d['biblio_id'];die;
            
            $_author_q = $this->obj_db->query('SELECT a.author_name FROM biblio_author AS ba
                LEFT JOIN biblio AS b ON ba.biblio_id=b.biblio_id
                LEFT JOIN mst_author AS a ON ba.author_id=a.author_id WHERE ba.biblio_id='.$_biblio_d['biblio_id']);
            // concat author data
            
            $_authors = '';
            while ($_author_d = $_author_q->fetch_row())
            {
                $_authors .= $_author_d[0].' - ';
            }

	
	
          if ($_authors!='')
          {
                $_authors = substr_replace($_authors, '', -3);                
                echo $_authors;
                echo '<div class="subItem authorField"><b>'.__('By').'</b> : '.$_authors.'</div>';
	
          }
                $_subject_q = $this->obj_db->query('SELECT a.topic FROM biblio_topic AS ba
                LEFT JOIN biblio AS b ON ba.biblio_id=b.biblio_id
                LEFT JOIN mst_topic AS a ON ba.topic_id=a.topic_id WHERE ba.biblio_id='.$_biblio_d['biblio_id']);
            // concat author data
            $_subjects = '';
            while ($_subject_d = $_subject_q->fetch_row())
	    {
                $_subjects .= $_subject_d[0].' - ';
            }
	   $_subjects=substr($_subjects,0,-2);
//Commented And Added By Start Parth 14/7/2011 & 6/8/2011
	  //echo '<div class="subItem authorField"><b>'.__('Subject(s)').'</b> : '.$_subjects.'</div>';
//Commented And Added By End Parth 14/7/2011 & 6/8/2011
	 if($tt>0)
         {
	   if($available>0)
	   {
               
	   	echo '<div class="subItem authorField"><b>'.__('Total Book').'</b> : '.$total.'</div>';
	        echo '<div class="subItem authorField"><b>'.__('Available Book').'</b> : '.$available.'</div>';
	
	       $sql="select item_code from item where biblio_id=".$_biblio_d['biblio_id']." AND item_code NOT IN 
		    (select item_code from loan where biblio_id=".$_biblio_d['biblio_id']." AND return_date is null) limit 0,1";
               
	        $sql=$this->obj_db->query($sql);
	        $s1='';
	        while($s=$sql->fetch_assoc())
	        {
	          $s1.=$s['item_code'];
                  $_session['item_code']=$s['item_code'];
	        }
		//echo "<div><input type=checkbox name='item' value='$s1'><a href=index.php?sub=".$s1.">Add To Cart</a></div>";
		
		if (utility::isMemberLogin())
		 {	

			

			//echo "<div align=left class='subItem'><a class='detailLink' href=index.php?sub=".$s1.">Reserve</a></div>";
		 }


	   }
	   else
	   {

			echo '<div class="subItem authorField"><b>'.__('Total Book').'</b> : '.$total.'</div>';
	                echo '<div class="subItem authorField"><b>'.__('Available Book').'</b> : '.$available.'</div>';


			$due_item="select min(l.due_date) AS due_date from loan l
					  left join item i on i.item_code=l.item_code
					  left join biblio b on b.biblio_id=i.biblio_id
					  where i.biblio_id=".$_biblio_d['biblio_id']."";
                        
			$due_item= $this->obj_db->query($due_item);
			$due1='';
                        
                        /*echo '<pre>';
                        print_r($due_item);
                        echo '<pre>';
                        die;*/
                        
			while($due=$due_item->fetch_assoc())
			{
				 $due1.=$due['due_date'];
			}
			  // echo "<td>".__('Currently On Loan (Due on').$due1."</td>";
			echo '<div align=left><strong width="50%" style="color: red;">'.__('All Copy Currently On Loan').'<br/>'.__('One Of The Copy Due On ').'('.date($sysconf['date_format'], strtotime($due1)).')</strong></div>'; 
			    

               $item_loan="select l.item_code AS item_code from loan l
			    left join item i on i.item_code=l.item_code
			    left join biblio b on b.biblio_id=i.biblio_id
		 	    where l.due_date ='$due1' AND i.biblio_id=".$_biblio_d['biblio_id']." ";
		$item_loan=$this->obj_db->query($item_loan);
		$item_l='';
		while($item_loan1=$item_loan->fetch_assoc())
		{
			$item_l.=$item_loan1['item_code'];
			
		}
		
                    	 
		//echo '<div><input type=checkbox name="item" value='.$item_l.'><input type="submit" name="sub" value="Add To Cart" ></div>'; 
		if (utility::isMemberLogin())
		 {	
                    
                  //  echo '<div class="subItem"><a class="detailLink" href=index.php?sub='.$item_l.'>Reserve</a></div>'; 
                  }
		
	   }          
           //echo"<form action=\"index.php\" method=\"post\">";
           $redirect_string=$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'];
           echo"<form action='".$redirect_string."' method=\"post\">";
           
           echo "<div class='subItem'><input type='submit' name='sub' value='Add To Cart' ><input type='hidden' name='item_code' value=".$_session['item_code']." ></div>";
           echo "</form>";
            }




            else
            {	
                      $biblio_material_type=$this->obj_db->query('select material_resource_id from biblio where biblio_id='.$_biblio_d['biblio_id'].'');
                      $biblio_material_type_id=$biblio_material_type->fetch_assoc();
                      if($biblio_material_type_id['material_resource_id']==5)
                      {
                         //echo '<div class="subItem"><strong style="color: red; font-weight: bold;">'.__('No Copy Available For This Book').'</strong></div>';
                      }

            }

            //added by Parth 8/7/2011
                   $biblio_material_type_new=$this->obj_db->query('select material_sub_id from biblio where biblio_id='.$_biblio_d['biblio_id'].'');
                    $biblio_material_type_id_new=$biblio_material_type_new->fetch_assoc();
                    $biblio_material_type_name = $this->obj_db->query('select material_sub_name from mst_material_sub_type where material_sub_id ='.$biblio_material_type_id_new['material_sub_id'].'');
                    $biblio_material_type_name_id = $biblio_material_type_name->fetch_assoc();
                    echo '<div><b>Material Type :- </b><strong style="color: red; font-weight: bold;">'.$biblio_material_type_name_id['material_sub_name'].'</strong></div>';	

//ended by Parth 8/7/2011

//added by Parth 11/7/2011 & 14/7/2011 & 3/8/2011 & 4/8/2011
                if(empty($_biblio_d['count']) && $_GET['p']!="myeself")
                {
                    $flag_sumamry=0;
                    if(!empty($_biblio_d['abstract']))
                    {

                     echo '<div class="subItem authorField"><b>'.__('Summary').'</b> : '.$_biblio_d['abstract'].'</div>';
                    }
                    else
                    {
                    $flag_summary=1;
                    }
                }
                if(empty($_biblio_d['count']) && $_GET['p']!="myeself")
                {
                if(!empty($_SESSION['m_member_type']))
                {
                echo '<iframe src=lib/like.php?subid='.$_biblio_d['biblio_id'].'&memberid="'.$_SESSION['mid'].'" width=200px height=60px frameBorder=0></iframe>';
                }
                }
//ended by Parth 11/7/2011 & 14/7/2011 & 3/8/2011 & 6/8/2011 
//added by Parth 11/7/2011 & 3/8/2011	
                if(isset($_biblio_d['count']))
                {
                echo '<div align="left">View :- '.$_biblio_d['count'].'</div>';
                }

/*echo "<div align=right class='subItem'>";
if (utility::isMemberLogin())
		 {	

		/* $myself_item='select * from biblio_myself where biblio_id='.$_biblio_d['biblio_id'].' AND membre_id='.$_SESSION['mid'].'';
$myself_item=$dbs->query($myself_item);
	    //$myself_display = $this->obj_db->fetch_assoc($myself_item);
       $myself_display = '';
	$row=$myself_item->fetch_assoc();
	    
		 $myself_display=$row['biblio_id'];
		
	    

	    
	    echo $myself_display;*/

/*$biblio_myself=$this->obj_db->query('select biblio_id from biblio_myself where biblio_id='.$_biblio_d['biblio_id'].' AND member_id='.$_SESSION['mid'].'');
	$biblio_id_myself=$biblio_myself->fetch_assoc();
			if($biblio_id_myself['biblio_id']!='')
			{
			}
			else
			{
			$url="http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
                        $records = explode("?",$url); 
			$records[1] = str_replace("myself", "lastself", $records[1]);
			//echo $records[1];	
			echo "<a href=index.php?".$records[1]."&myself=".$_biblio_d['biblio_id']."><img src=lib/images/shelf.jpeg height=30px width=30px border=0 title=Add-To-MyShelf /></a>";
			}
		 }
if($flag_summary==1)
{
echo '<a href="javascript:void(0)" onclick=javascript:window.open("lib/test3.php?img_set='.$_biblio_d['isbn_issn'].'","Window1","menubar=no,width=600,height=300,toolbar=no,scrollbars=yes,location=no,left=200px"); ><img src="lib/images/summary.jpeg" height=30px width=30px border=0 title="summary"/></a>';
$flag_summary=0;
} 
//echo '<div align="right">'.$set_title.'</div>'; 
echo '<a href="javascript:void(0)" onclick=javascript:window.open("lib/toc.php?img_set='.$_biblio_d['isbn_issn'].'","Window1","menubar=no,width=1200,height=600,toolbar=no,scrollbars=yes,location=no,left=200px"); ><img src="lib/images/toc.jpeg" height=30px width=30px border=0 title="Table-Of-Contents"/></a>';
echo '<a href="javascript:void(0)" onclick=javascript:window.open("lib/abtautor.php?img_set='.$_biblio_d['isbn_issn'].'","Window1","menubar=no,width=600,height=600,toolbar=no,scrollbars=yes,location=no,left=200px"); ><img src="lib/images/author.jpeg" height=30px width=30px border=0 title="Author-Biography"/></a>';
echo $set_title.'</div><br/>'; */

                    echo '</td>';
                    if(!empty($_image_cover))
                    {
                         if($_GET['p']!='rescentview' && $_GET['p']!='myeself')
                            {		
                                    echo  '<td  align="center" valign="top" width=30%> '.$_image_cover; 
                            }
                         else
                            {
                                 echo  '<td  align="center" valign="top"> '.$_image_cover;   
                            }  

                    }
                    else
                    {
                            $img_set = $_biblio_d['isbn_issn'];
                            if($_GET['p']!='rescentview' && $_GET['p']!='myeself')
                            {
                                echo '<td  align="center" valign="top" width=30%> '; 
                            }
                            else
                            {
                                echo '<td  align="center" valign="top"> '; 
                            }
                            if($_biblio_d['material_sub_id']=='85' || $_biblio_d['material_sub_id']=='84' || $_biblio_d['material_sub_id']=='113' || $_biblio_d['material_sub_id']=='112' || $_biblio_d['material_sub_id']=='130' || $_biblio_d['material_sub_id']=='132')
                            {   
                                    echo '<img src="lib/images/imagesnotavailable.jpg" ID=$img_set  height=120px width=100px />';
                                    //include('test1.php'); hp
                             }	
                                    //echo '<br/>'.$_biblio_d['title'].'';
                      }
                     echo "<div align=center class='subItem'>";
                    if (utility::isMemberLogin())
                    {	


                                     /*$myself_item='select * from biblio_myself where biblio_id='.$_biblio_d['biblio_id'].' AND membre_id='.$_SESSION['mid'].'';
                                       $myself_item=$dbs->query($myself_item);
                                       //$myself_display = $this->obj_db->fetch_assoc($myself_item);
                                       $myself_display = '';
                                       $row=$myself_item->fetch_assoc();	    
                                       $myself_display=$row['biblio_id'];
                                       echo $myself_display;*/

                                $biblio_myself=$this->obj_db->query('select biblio_id from biblio_myself where biblio_id='.$_biblio_d['biblio_id'].' AND member_id="'.$_SESSION['mid'].'"');
                                $biblio_id_myself=$biblio_myself->fetch_assoc();

                                            if($biblio_id_myself['biblio_id']!='')
                                            {
                                            }
                                            else
                                            {


                                                $url="http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
                                                $records = explode("?",$url);                             
                                                $records[1] = str_replace("myself", "lastself", $records[1]);
                                                   //echo "index.php?".$records[1]."&myself=".$_biblio_d['biblio_id'];die;
                                                echo "<a href=index.php?".$records[1]."&myself=".$_biblio_d['biblio_id']."><img src=lib/images/shelf.png height=30px width=30px border=0 title=Add-To-MyShelf /></a>";
                                            }
                    }
                    if($flag_summary==1)
                    {
                    echo '<a href="javascript:void(0)" onclick=javascript:window.open("lib/test3.php?img_set='.$_biblio_d['isbn_issn'].'","Window1","menubar=no,width=600,height=300,toolbar=no,scrollbars=yes,location=no,left=200px"); ><img src="lib/images/summary.png" height=30px width=30px border=0 title="summary"/></a>';
                    $flag_summary=0;
                    } 
//echo '<div align="right">'.$set_title.'</div>'; 
                    echo '<a href="javascript:void(0)" onclick=javascript:window.open("lib/toc.php?img_set='.$_biblio_d['isbn_issn'].'","Window1","menubar=no,width=1200,height=600,toolbar=no,scrollbars=yes,location=no,left=200px"); ><img src="lib/images/toc.png" height=30px width=30px border=0 title="Table-Of-Contents"/></a>';
                    echo '<a href="javascript:void(0)" onclick=javascript:window.open("lib/abtautor.php?img_set='.$_biblio_d['isbn_issn'].'","Window1","menubar=no,width=600,height=600,toolbar=no,scrollbars=yes,location=no,left=200px"); ><img src="lib/images/author.png" height=30px width=30px border=0 title="Author-Biography"/></a>';
                    echo $set_title.'</div><br/>'; 
                    echo '</td>';
//ended by Parth 11/7/2011 & 3/8/2011 & 6/8/2011 
//added start by Parth 5/8/2011

//added ended by Parth 5/8/2011		
	

//}
//added started and commented by parth 3/8/2011
            if(isset($_biblio_d['count']) || $_GET['p']=='rescentview')
            {
               if($i%1 == 0){
                            echo '<tr class="alterCell3">';
                            }	
            }
            else if($_GET['p']=='myeself')
            {
            if($i%1 == 0){
                            echo '<tr class="alterCell3">';
                            }
            }
            else
            {
               if($i%1 == 0){
                            echo '<tr class="alterCell3">';
                            }	
            }
                    /*if($i%2 == 0){
                            echo '<tr>';
                            }	*/
            //added ended and commented by parth 3/8/2011
            /*
                        # checking custom file
                        if ($this->enable_custom_frontpage AND $this->custom_fields) {
                            foreach ($this->custom_fields as $_field => $_field_opts) {
                                if ($_field_opts[0] == 1) {
                                    if ($_field == 'edition') {
                                        $_buffer .= '<div class="customField editionField"><b>'.$_field_opts[1].'</b> : '.$_biblio_d['edition'].'</div>';
                                    } else if ($_field == 'isbn_issn') {
                                        $_buffer .= '<div class="customField isbnField"><b>'.$_field_opts[1].'</b> : '.$_biblio_d['isbn_issn'].'</div>';
                                    } else if ($_field == 'collation') {
                                        $_buffer .= '<div class="customField collationField"><b>'.$_field_opts[1].'</b> : '.$_biblio_d['collation'].'</div>';
                                    } else if ($_field == 'series_title') {
                                        $_buffer .= '<div class="customField seriesTitleField"><b>'.$_field_opts[1].'</b> : '.$_biblio_d['series_title'].'</div>';
                                    } else if ($_field == 'call_number') {
                                        $_buffer .= '<div class="customField callNumberField"><b>'.$_field_opts[1].'</b> : '.$_biblio_d['call_number'].'</div>';
                                    } else if ($_field == 'availability' && !$this->disable_item_data) {
                                        // get total number of this biblio items/copies
                                        $_item_q = $this->obj_db->query('SELECT COUNT(*) FROM item WHERE biblio_id='.$_biblio_d['biblio_id']);
                                        $_item_c = $_item_q->fetch_row();
                                        // get total number of currently borrowed copies
                                        $_borrowed_q = $this->obj_db->query('SELECT COUNT(*) FROM loan AS l INNER JOIN item AS i'
                                            .' ON l.item_code=i.item_code WHERE l.is_lent=1 AND l.is_return=0 AND i.biblio_id='.$_biblio_d['biblio_id']);
                                        $_borrowed_c = $_borrowed_q->fetch_row();
                                        // total available
                                        $_total_avail = $_item_c[0]-$_borrowed_c[0];
                                        if ($_total_avail < 1) {
                                            $_buffer .= '<div class="customField availabilityField"><b>'.$_field_opts[1].'</b> : <strong style="color: #FF0000;">none copy available</strong></div>';
                                        } else {
                                            $_buffer .= '<div class="customField availabilityField"><b>'.$_field_opts[1].'</b> : '.$_total_avail.' copies available for loan</div>';
                                        }
                                    } else if ($_field == 'node_id' && $this->disable_item_data) {
                                                                    $_buffer .= '<div class="customField locationField"><b>'.$_field_opts[1].'</b> : '.$sysconf['node'][$_biblio_d['node_id']]['name'].'</div>';
                                                            }
                                }
                            }
                        }

                        $_buffer .= '<td class="subItem">'.$_biblio_d['detail_button'].' '.$_biblio_d['xml_button'].'</td>';*/
                       // $_buffer .= "</td>";
                        //$_buffer .= "</tr>";

    
             $i++;  
        }
//added started and commented by parth 3/8/2011
                if(isset($_biblio_d['count']) || $_GET['p']!='myeself')
                {
                   if($i%1 == 0){
                                echo '</tr>';
                                }	
                }
                else
                {
                   if($i%1 == 0)
                   {
                        echo '</tr>';
                   }	
                }
	
echo "</table>";  
echo "</form>";

     // free resultset memory
        $this->resultset->free_result();
 
        // paging
        if (($this->num_rows > $this->num2show)) {
            $_paging = '<hr width="97%" size="1" />'."\n";
            $_paging .= '<div style="text-align: center;">'.simbio_paging::paging($this->num_rows, $this->num2show, 5).'</div>';
        } else {
            $_paging = '';
        }

        return $_buffer.$_paging;

 }
    
    
    /**
     * Method to make an output of document records in simple XML format
     *
     * @return  string
     */
    public function XMLresult()
    {
        global $sysconf;
        // loop data
        $_buffer = '<modsCollection xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.loc.gov/mods/v3" xmlns:slims="http://senayan.diknas.go.id" xsi:schemaLocation="http://www.loc.gov/mods/v3 http://www.loc.gov/standards/mods/v3/mods-3-3.xsd">'."\n";
        $_buffer .= '<slims:resultInfo>'."\n";
        $_buffer .= '<slims:modsResultNum>'.$this->num_rows.'</slims:modsResultNum>'."\n";
        $_buffer .= '<slims:modsResultPage>'.$this->current_page.'</slims:modsResultPage>'."\n";
        $_buffer .= '<slims:modsResultShowed>'.$this->num2show.'</slims:modsResultShowed>'."\n";
        $_buffer .= '</slims:resultInfo>'."\n";
        while ($_biblio_d = $this->resultset->fetch_assoc()) {
            $_buffer .= '<mods ID="'.$_biblio_d['biblio_id'].'">'."\n";
            // parse title
            $_title_sub = '';
            if (stripos($_biblio_d['title'], ':') !== false) {
                $_title_main = trim(substr_replace($_biblio_d['title'], '', stripos($_biblio_d['title'], ':')+1));
                $_title_sub = trim(substr_replace($_biblio_d['title'], '', 0, stripos($_biblio_d['title'], ':')+1));
            } else {
                $_title_main = trim($_biblio_d['title']);
            }

            $_buffer .= '<titleInfo>'."\n".'<title>'.$_title_main.'</title>'."\n";
            if ($_title_sub) {
                $_buffer .= '<subTitle>'.$_title_sub.'</subTitle>'."\n";
            }
            $_buffer .= '</titleInfo>'."\n";

            // get the authors data
            $_biblio_authors_q = $this->obj_db->query('SELECT a.*,ba.level FROM mst_author AS a'
                .' LEFT JOIN biblio_author AS ba ON a.author_id=ba.author_id WHERE ba.biblio_id='.$_biblio_d['biblio_id']);
            while ($_auth_d = $_biblio_authors_q->fetch_assoc()) {
                $_buffer .= '<name type="'.$sysconf['authority_type'][$_auth_d['authority_type']].'" authority="'.$_auth_d['auth_list'].'">'."\n"
                  .'<namePart>'.$_auth_d['author_name'].'</namePart>'."\n"
                  .'<role><roleTerm type="text">'.$sysconf['authority_level'][$_auth_d['level']].'</roleTerm></role>'."\n"
                .'</name>'."\n";
            }
            $_buffer .= '<typeOfResource manuscript="yes" collection="yes">mixed material</typeOfResource>'."\n";
            $_biblio_authors_q->free_result();

			// ISBN
			$_buffer .= '<identifier type="isbn">'.str_replace(array('-', ' '), '', $_biblio_d['isbn_issn']).'</identifier>'."\n";
            $_buffer .= '</mods>'."\n";
        }
        $_buffer .= '</modsCollection>';

        // free resultset memory
        $this->resultset->free_result();

        return $_buffer;
    }


    /**
     * Method to get list of document IDs of result
     *
     * @return  mixed
     */
    public function getDocumentIds()
    {
        $_temp_resultset = $this->resultset;
        while ($_biblio_d = $_temp_resultset->fetch_assoc()) {
	            $this->biblio_ids[] = $_biblio_d['biblio_id'];
        }
        unset($_temp_resultset);
        return $this->biblio_ids;
    }
}


//echo '<a href="index.php?p=member"><center><font-size=24>Back To Previous</font></center></a>';
?>
