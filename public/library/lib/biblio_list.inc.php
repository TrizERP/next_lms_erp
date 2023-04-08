<?php

class biblio_list
{
   
    /*Private properties*/
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
		'publisher', 'gmd', 'mst_sub', 'notes', 'colltype', 'publishyear',
		'location', 'itemcode', 'callnumber', 'itemcallnumber', 'notes','standard','subjecttype','material_sub_type','subjectajax','sub','barcode','subtype','searchsimple','letternew','searchkeyword', 'class', 'keywords_tag', 'key_sub');
    
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
       if (count($_queries) < 1) 
       {
            return null;
       }
        // loop each query
        foreach ($_queries as $_num => $_query) 
        {
            
           $_field = $_query['f'];
			
            // for debugging purpose only
          //  echo "<p>$_num. $_field -> $_boolean -> $_sql_criteria</p><p>&nbsp;</p>";
            // boolean
            if ($_title_buffer == '' && $_field != 'boolean') {
                $_sql_criteria .= " $_boolean ";
            }
            // $_sql_criteria .= " $_boolean ";
            // flush title string concatenation
            if ($_field != 'title' && $_title_buffer != '' && !isset($_GET['letter'])) 
             {
                $_title_buffer = trim($_title_buffer);
             $_sql_criteria .= " biblio.biblio_id IN(SELECT DISTINCT biblio_id FROM biblio WHERE MATCH (title, series_title,tags) AGAINST ('$_title_buffer' IN BOOLEAN MODE)) ";
                // reset title buffer
                $_title_buffer = '';
                
            }
            if (isset($_GET['letter']))
            {
                   //$_title_buffer = trim($_title_buffer);
                   $_sql_criteria = "title like '$_GET[letter]%' ";
                
            }


 	
            //  break the loop if we meet `cql_end` field
            if ($_field == 'cql_end')
            { 
                break;
            }
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

           if ($_field == 'title') 
           {
		
 		if (strlen($_q)< 4)
		 {
		  
                    $_previous_field = 'title_short';
                 // $_sql_criteria .= " biblio.title LIKE '%$_q%' ";
 		    $_sql_criteria .= " biblio.title LIKE '%$_q%'";
                    $_title_buffer = '';
                    
                 }	       		
		 else
                 {
                    if (isset($_query['is_phrase'])) 
                    {
                        $_title_buffer .= ' '.$_b.'"'.$_q.'"';
                    }
                    else 
                    {
                        $_title_buffer .= ' '.$_b.$_q;
                    }
                    
                  }
            
                
            }	 	

	elseif ($_field == 'searchkeyword') 
        {
            
            
               if(!empty($_GET['material_sub_type_select1']))
		{
                   
			$sub_id_new = $_GET['material_sub_type_select1'];
                        
                        if ($_b == '-') 
                        {
                            
                             $_sql_criteria .= " biblio.biblio_id NOT IN(SELECT bt.biblio_id FROM biblio AS bt"
                                  ." WHERE (title LIKE '%".$_q."%' OR tags LIKE '%".$_q."%') AND bt.material_sub_id = '".$sub_id_new."')";
                        } 
		
                        else
                        {
                            if ($sub_id_new==13 || $sub_id_new==14 || $sub_id_new==15 || $sub_id_new==16 || $sub_id_new==18 || $sub_id_new==136)
                            {
                               $_sql_criteria .= " biblio.biblio_id IN(SELECT bt.biblio_id FROM biblio AS bt"
                                  ." WHERE (title LIKE '%".$_q."%' OR tags LIKE '%".$_q."%') AND bt.gmd_id = '".$sub_id_new."')"; 
                            }
                            else
                            {
                             $_sql_criteria .= " biblio.biblio_id IN(SELECT bt.biblio_id FROM biblio AS bt"
                                  ." WHERE (title LIKE '%".$_q."%' OR tags LIKE '%".$_q."%') AND bt.material_sub_id = '".$sub_id_new."')";
                            }
                         }
 
		}
		else
		{
			if ($_b == '-') 
                        {
                            $_sql_criteria .= " biblio.title LIKE '%$_q%' OR biblio.tags LIKE '%$_q%'";
                        } 
		
        		else 
                        {
                            $_sql_criteria .= " biblio.title LIKE '%$_q%' OR biblio.tags LIKE '%$_q%'";
                        }
		}                
		    		                
                $_title_buffer = '';
                
        }	
        else if ($_field == 'letter') 
        {
		//echo 'hi';die;
              if ($_b == '-') {
                    $_sql_criteria .= " biblio.biblio_id NOT IN(SELECT bt.biblio_id FROM biblio AS bt"
                                  ." WHERE bt.material_sub_id = '".$_q."')";
                } 
		
		else {
                    $_sql_criteria .= " biblio.biblio_id IN(SELECT bt.biblio_id FROM biblio AS bt"
                                  ." WHERE bt.material_sub_id = '".$_q."')";
             }
		
                // reset title buffer
                $_title_buffer = '';
        }
        else if ($_field == 'subtype') 
        {
		
              if ($_b == '-') {
                    $_sql_criteria .= " biblio.biblio_id NOT IN(SELECT bt.biblio_id FROM biblio AS bt"
                                  ." WHERE bt.material_sub_id = '".$_q."')";
                } 
		
		else {
                    $_sql_criteria .= " biblio.biblio_id IN(SELECT bt.biblio_id FROM biblio AS bt"
                                  ." WHERE bt.material_sub_id = '".$_q."')";
             }
		
                // reset title buffer
                $_title_buffer = '';
        }
	else if ($_field == 'searchsimple') 
        {
           
		if(!empty($_GET['material_sub_type_select1']))
		{
			$sub_id_new = $_GET['material_sub_type_select1'];
		}
		else
		{
			$sub_id_new = $_GET['subtype1'];
		}
		
              if ($_b == '-') {
                    $_sql_criteria .= " biblio.biblio_id NOT IN(SELECT bt.biblio_id FROM biblio AS bt"
                                  ." WHERE title LIKE '%".$_q."%' AND bt.material_sub_id = '".$sub_id_new."')";
                } 
		
		else {
                    $_sql_criteria .= " biblio.biblio_id IN(SELECT bt.biblio_id FROM biblio AS bt"
                                  ." WHERE title LIKE '%".$_q."%' AND bt.material_sub_id = '".$sub_id_new."')";
             }
                $_title_buffer = '';
          }
	  else if ($_field == 'letternew') 
          {
		
              if ($_b == '-') {
                    $_sql_criteria .= " biblio.biblio_id NOT IN(SELECT bt.biblio_id FROM biblio AS bt"
                                  ." WHERE title LIKE '".$_q."%' AND bt.material_sub_id = '".$_GET['subtype1']."')";
                } 
		
		else {
                    $_sql_criteria .= " biblio.biblio_id IN(SELECT bt.biblio_id FROM biblio AS bt"
                                  ." WHERE title LIKE '".$_q."%' AND bt.material_sub_id = '".$_GET['subtype1']."')";
             }
		
                // reset title buffer
                $_title_buffer = '';
                
            }
	//ended by Parth 30/06/2011
	
		 else if ($_field == 'author')
           {
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
                        ." WHERE t.topic_id = '$_q' || t.topic = '$_q')";
                } 
		
		else {
                    
                    
                    $_sql_criteria .= " biblio.biblio_id IN(SELECT bt.biblio_id FROM biblio_topic AS bt"
                        ." LEFT JOIN mst_topic AS t ON bt.topic_id=t.topic_id"
                        ." WHERE t.topic_id = '$_q' || t.topic = '$_q')";
                }
		
                // reset title buffer
                $_title_buffer = '';
            }
 
	else if ($_field == 'subjectajax') {
                if ($_b == '-') {
                    $_sql_criteria .= " biblio.biblio_id NOT IN(SELECT bt.biblio_id FROM biblio_topic AS bt"
                        ." LEFT JOIN mst_topic AS t ON bt.topic_id=t.topic_id"
                        ." WHERE t.topic_id ='$_q')";
                } 
		
		else {
                    $_sql_criteria .= " biblio.biblio_id IN(SELECT bt.biblio_id FROM biblio_topic AS bt"
                        ." LEFT JOIN mst_topic AS t ON bt.topic_id=t.topic_id"
                        ." WHERE t.topic_id ='$_q')";
                }
                $_title_buffer = '';
            }
		else if ($_field == 'standard') {
                if ($_b == '-') {
                    $_sql_criteria .= " biblio.biblio_id NOT IN(SELECT bs.biblio_id FROM biblio_standard AS bs"
                        ." LEFT JOIN mst_standard AS ms ON bs.standard_id=ms.standard_id"
                        ." WHERE ms.standard_id ='$_q')";
                } 
		
		else {
                    $_sql_criteria .= " biblio.biblio_id IN(SELECT bs.biblio_id FROM biblio_standard AS bs"
                        ." LEFT JOIN mst_standard AS ms ON bs.standard_id=ms.standard_id"
                        ." WHERE ms.standard_id ='$_q')";
                }
		$_title_buffer = '';
		}
              
		else if ($_field == 'subjecttype')
                {
                    if ($_b == '-') 
                    {
                    $_sql_criteria .= " biblio.biblio_id NOT IN(SELECT bt.biblio_id FROM biblio_topic AS bt"
                        ." LEFT JOIN mst_subject_type AS mst ON bt.subject_type_id=mst.subject_type_id"
                        ." WHERE mst.subject_type_id ='$_q')";
                    } 
		
                    else 
                    {
                    $_sql_criteria .= " biblio.biblio_id IN(SELECT bt.biblio_id FROM biblio_topic AS bt"
                        ." LEFT JOIN mst_subject_type AS mst ON bt.subject_type_id=mst.subject_type_id"
                        ." WHERE mst.subject_type_id ='$_q')";
                    }
                    $_title_buffer = '';
		}
                /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
else if ($_field == 'material_sub_type') {
                if ($_b == '-') {
                    $_sql_criteria .= " biblio.biblio_id NOT IN(SELECT b.biblio_id FROM biblio AS b"
                        ." LEFT JOIN mst_material_sub_type AS mmt ON b.material_sub_id=mmt.material_sub_id"
                        ." WHERE mmt.material_sub_id='$_q')";
                } 
		
		else {
                    $_sql_criteria .= " biblio.biblio_id IN(SELECT b.biblio_id FROM biblio AS b"
                        ." LEFT JOIN mst_material_sub_type AS mmt ON b.material_sub_id=mmt.material_sub_id"
                        ." WHERE mmt.material_sub_id='$_q')";
                }
		$_title_buffer = '';
		}

else if ($_field == 'barcode') {
                if ($_b == '-') {
                    $_sql_criteria .= " biblio.biblio_id NOT IN(SELECT b.biblio_id FROM biblio AS b"
                        ."LEFT JOIN item AS i ON b.biblio_id=i.biblio_id"
                        ." WHERE i.item_code='$_q')";
                } 
		
		else {
                    $_sql_criteria .= " biblio.biblio_id IN(SELECT b.biblio_id FROM biblio AS b"
                        ." LEFT JOIN item AS i ON b.biblio_id=i.biblio_id"
                        ." WHERE i.item_code='$_q')";
                }
		$_title_buffer = '';
		}
		 else {
                switch ($_field) 
                     {
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
							} else { $_sql_criteria .= " item.item_code LIKE '%$_q%'"; }
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
                        $_subquery = 'SELECT publisher_id FROM mst_publisher WHERE publisher_name LIKE \''.$_q.'%\'';
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
	           case 'mst_sub' :
                        $_q = str_replace("+++++","'",$_q); 
			$_q = str_replace("-y----","'",$_q); 
                        $_q = str_replace("por"," OR",$_q);
    	
                        $_subquery = 'SELECT material_sub_id FROM mst_material_sub_type WHERE '.$_q;
                        if ($_b == '-') 
                        {
                            
                            $_sql_criteria .= " biblio.material_sub_id NOT IN ($_subquery)";
                        }
                        else
                        {
                        
                            $_sql_criteria .= " biblio.material_sub_id IN ($_subquery)"; 
                          
                        }
                        
                        break;
		   case 'key_sub' :
                        $_q = str_replace("+++++","'",$_q); 
			$_q = str_replace("-y----","'",$_q); 
                        $_q = str_replace("por"," OR",$_q);
                        $_q = str_replace("nor"," AND",$_q); 
                        $_subquery = $_q;
                        break;
                   case 'keywords_tag' :
                         if ($_b == '-') {
                            $_sql_criteria .= ' AND biblio.tags NOT LIKE \'%'.$_q.'%\' OR biblio.abstract NOT LIKE \'%'.$_q.'%\'';
                        } else { $_sql_criteria .= ' biblio.tags LIKE \'%'.$_q.'%\' OR biblio.abstract LIKE \'%'.$_q.'%\''; }
                        break; 
	           case 'notes' :
                        if ($_b == '-') {
                            $_sql_criteria .= " NOT (MATCH (biblio.notes) AGAINST ('".$_q."', IN BOOLEAN MODE))";
                        } else { $_sql_criteria .= " (MATCH (biblio.notes) AGAINST ('".$_q."', IN BOOLEAN MODE))"; }
                        break;
                }
            }
        }
        
        $_sql_criteria = preg_replace('@^(AND|OR|NOT)\s*|\s+(AND|OR|NOT)$@i', '', trim($_sql_criteria));
        $this->criteria = array('sql_criteria' => $_sql_criteria, 'searched_fields' => $_searched_fields);
        return $this->criteria;
    }
    public function getDocumentList($int_num2show = 10, $bool_return_output = true)
    {
        global $sysconf;
        $this->num2show = $int_num2show;
        // get page number from http get var
        if (!isset($_GET['page']) OR $_GET['page'] < 1)
        {
            $_page = 1;
        }
        else
        {
            $_page = (integer)$_GET['page'];
        }
        $this->current_page = $_page;

        // count the row offset
        if ($_page <= 1) 
        {
            $_offset = 0;
        }
        else
        {
            $_offset = ($_page*$this->num2show) - $this->num2show;
        }

            if($_GET['p']=="rescentview")
            {
                //echo "hoii";die;
                $_sql_str = "select biblio.file_att, biblio.biblio_id,biblio.title,biblio.image,biblio.gmd_id, biblio.isbn_issn, biblio.labels, b2.count, b2.last_update from biblio biblio INNER JOIN biblio_view b2 ON biblio.biblio_id = b2.biblio_id and b2.member_id='".$_SESSION['DUSER_ID']."' order by b2.last_update DESC LIMIT 10";                                            
                 
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
                    if ($bool_return_output) {
                        // return the html result

                     return $this->makeOutput();
                    }
            }
            else if($_GET['p']=="myeself")
            {
            $_sql_str = "select biblio.file_att, biblio.biblio_id,biblio.title,biblio.image,biblio.gmd_id, biblio.isbn_issn, biblio.labels from biblio biblio INNER JOIN biblio_myself b2 ON biblio.biblio_id = b2.biblio_id where b2.member_id = '".$_SESSION['DUSER_ID']."' ORDER BY b2.last_update DESC LIMIT 10";
             $this->resultset = $this->obj_db->query($_sql_str);
                    if ($this->obj_db->error) {
                        $this->query_error = $this->obj_db->error;
                    }
                    // get total number of rows from query
                    $_total_q = $this->obj_db->query('SELECT FOUND_ROWS()');
                    $_total_d = $_total_q->fetch_row();
                    $this->num_rows = $_total_d[0];
                    // end time
                    $_end = function_exists('microtime')?microtime(true):time();
                    $this->query_time = round($_end-$_start, 5);
                    if ($bool_return_output) {
                        // return the html result

                     return $this->makeOutput();
            }
            }
            else
            {
                    $_sql_str = 'SELECT SQL_CALC_FOUND_ROWS biblio.file_att, biblio.material_resource_id,biblio.biblio_id,biblio.title,biblio.image,biblio.gmd_id, biblio.isbn_issn, biblio.labels,biblio.abstract,biblio.notes,biblio.material_sub_id';//added by iresh on 5-4-2011
                    $custom_frontpage_record_file = (defined('UCS_BASE_DIR')?UCS_BASE_DIR:SENAYAN_BASE_DIR).$sysconf['template']['dir'].'/'.$sysconf['template']['theme'].'/custom_frontpage_record.inc.php';
                    if (file_exists($custom_frontpage_record_file)) 
                    {
                        include $custom_frontpage_record_file;
                        $this->enable_custom_frontpage = true;
                        $this->custom_fields = $custom_fields;
                        foreach ($this->custom_fields as $_field => $_field_opts) 
                       {
                            if ($_field_opts[0] == 1 && !in_array($_field, array('availability', 'isbn_issn'))) 
                            {
                                $_sql_str .= ", biblio.$_field";
                            }
                        }
                    }
                    $_add_sql_str = '';
                    if ($this->criteria) 
                    {
                        if (isset($this->criteria['searched_fields']['location']) || isset($this->criteria['searched_fields']['colltype'])) 
                        {
                            if (!$this->disable_item_data) 
                            {
                                              $_add_sql_str .= ' LEFT JOIN item ON biblio.biblio_id=item.biblio_id ';

                            }
                        }
                    }

                  $_add_sql_str .= ' WHERE opac_hide=0 ';
                    if ($this->criteria) 
                    {
                        
                        if($this->criteria['sql_criteria']!='' && !empty ($this->criteria['sql_criteria']))
                        $_add_sql_str .= ' AND ('.$this->criteria['sql_criteria'].') ';
                    }

                   $_sql_str .= ' FROM biblio '.$_add_sql_str.' ORDER BY biblio.last_update DESC LIMIT '.$_offset.', '.$this->num2show;
                    $_start = function_exists('microtime')?microtime(true):time();
                    
                    $this->resultset = $this->obj_db->query($_sql_str);
                    
                    if ($this->obj_db->error) 
                    {
                        $this->query_error = $this->obj_db->error;
                    }
                    $_total_q = $this->obj_db->query('SELECT FOUND_ROWS()');                    
                    $_total_d = $_total_q->fetch_row();
                    
                    $this->num_rows = $_total_d[0];
                    // end time
                    $_end = function_exists('microtime')?microtime(true):time();
                    $this->query_time = round($_end-$_start, 5);
                    if ($bool_return_output) 
                    {
                           return $this->makeOutput();
                    }
            }
    }
    protected function makeOutput()
    {
        //echo "Pooja";
        global $sysconf;
	$_buffer = '';
        $myhtml = '';
        $redirect_string=$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'];
        //echo"<form action='".$redirect_string."' method=\"post\">";
        $myhtml .= "<form action='".$redirect_string."' method=\"post\">";
        $i = 1;
       // print_r($this->resultset);
        if (!$this->resultset) 
        {
            
            return '<div style="border: 1px dotted #FF0000; color: #FF0000; padding: 5px; margin: 5px;">Query error : '.$this->query_error.'</div>';
        }
	//echo"<br>";
	if($_GET['p']!='rescentview' && $_GET['p']!='myeself')
	{
         	echo '<table cellpadding=5 cellspacing=5 align=center width=80% class="content1">';
	}
	else
	{
            //echo '<table cellpadding=5 cellspacing=5 align=center width=80% class="content1">';
            $myhtml .= '<table cellpadding=5 cellspacing=5 align=center width=80% class="content1">';
	}

        if ($this->resultset->num_rows==0) 
        {
            
           // echo "<th align='center'>No Data Found.</th>";
            $myhtml .= "<th align='center'>No Data Found.</th>";
        }
        
        while ($_biblio_d = $this->resultset->fetch_assoc()) 
         {
                $myurl = "https://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];                
                $raw_url = parse_url($myurl);
                
                $domain_only =str_replace ('www.','', $raw_url);
                $domain_only['host']; 
//                $var=preg_match("|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i","'.$_biblio_d[labels].'");
//                echo $var;die;
                
//                 echo '<pre>';
//                 print_r($a);
//                 echo '<pre>';
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
                   $_biblio_d['title'] = '<a href="'.$sysconf['baseurl'].'index.php?p=show_detail&id='.$_biblio_d['biblio_id'].'" title="'.__('Record Detail').'"><b><u>'.$_biblio_d['title'].'</u></b></a><br>';
                  //  $_biblio_d['title'] = $_biblio_d['title'].'<br>'; 
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
                    if($_biblio_d['file_att']!='')
                      $_biblio_d['title'] .= '<b>Attachment File: </b><a onclick="download_file('.$_biblio_d['file_att'].')" href='.REPO_BASE_DIR.'trizino_slibrary/'.$_biblio_d['file_att'].'>'.$_biblio_d['file_att'].'</a>';
                   
                        
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
	                    
	                }
				}
            }
                    $_biblio_d['detail_button'] = '<a href="'.$sysconf['baseurl'].'index.php?p=show_detail&id='.$_biblio_d['biblio_id'].'" class="detailLink" title="'.__('Record Detail').'">'.__('Record Detail').'</a>';
           
                    if ($this->xml_detail)
                     {
                        $_biblio_d['xml_button'] = '<a href="'.$sysconf['baseurl'].'index.php?p=show_detail&inXML=true&id='.$_biblio_d['biblio_id'].'" class="xmlDetailLink" title="View Detail in XML Format" target="_blank">XML Detail</a>';

                    }
                    else 
                    {

                        $_biblio_d['xml_button'] = '';

                    }
                    $_image_cover = '';
                    if (!empty($_biblio_d['image']) && !defined('LIGHTWEIGHT_MODE'))
                    {
                        
                        $_biblio_d['image'] = urlencode($_biblio_d['image']);
                        $images_loc = "images/docs/".urldecode($_biblio_d['image']);                
                        if (file_exists($images_loc)) 
                        {
                        $_image_cover = "<img id='".$_biblio_d['biblio_id']."' src='".$images_loc."' width='80px' height='120px'>" ;
                        }
                        else
                        {
                            $images_loc = "images/docs/imagesnotavailable2.jpg";                                        
                            $_image_cover = "<img id='".$_biblio_d['biblio_id']."' src='".$images_loc."' width='80px' height='120px'>" ;
                        }
                    }
                    else
                    {
                        $images_loc = "images/docs/imagesnotavailable2.jpg";                                        
                        $_image_cover = "<img id='".$_biblio_d['biblio_id']."' src='".$images_loc."' width='80px' height='120px'>" ;
                    }
		 $_alt_list = ($_i%3 == 0)?'alterList':'alterList2';	
                
                 if($_GET['p']!='rescentview' && $_GET['p']!='myeself')
                {		
                    
                     //echo  '<tr class="alterCell3"><td  align="left" valign="top">'.$_biblio_d['title'];  
                     $myhtml .= '<tr class="alterCell3"><td  align="left" valign="top">'.$_biblio_d['title'];
                   
                 }
                 else
                {
                      if($_GET['p']=="myeself")
                   {
                   //echo '<div align="right"><a href=index.php?set_delete='.$_biblio_d['biblio_id'].'&p=myeself>Delete</a></div><br/>';
                   $myhtml .= '<tr class="alterCell3"><td  align="left" valign="top">';            
                   $myhtml .= "<a onclick='return confirm(\"Are You Sure Want To Delete The Record\")' href='index.php?set_delete=".$_biblio_d['biblio_id']."&p=myeself' >Delete</a></td>";
                   $myhtml .= '<br/>';
                   $myhtml .=  '<td  align="left" valign="top"> '.$_biblio_d['title'];  
                   }
                   else
                   {
                  //echo  '<tr class="alterCell3"><td  align="left" valign="top"> '.$_biblio_d['title'];   
                     $myhtml .=  '<tr class="alterCell3"><td  align="left" valign="top"> '.$_biblio_d['title'];  
                   }
                 }  

            
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
            $_author_q = $this->obj_db->query('SELECT a.author_name FROM biblio_author AS ba
                LEFT JOIN biblio AS b ON ba.biblio_id=b.biblio_id
                LEFT JOIN mst_author AS a ON ba.author_id=a.author_id WHERE ba.biblio_id='.$_biblio_d['biblio_id']);
            $_authors = '';
            while ($_author_d = $_author_q->fetch_row())
            {
                $_authors .= $_author_d[0].' - ';
            }
          if ($_authors!='')
          {
                $_authors = substr_replace($_authors, '', -3);                
                //echo $_authors;
                //echo '<div class="subItem authorField"><b>'.__('By').'</b> : '.$_authors.'</div>';
                $myhtml .= '<div class="subItem authorField"><b>'.__('By').'</b> : '.$_authors.'</div>';
	
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
	 if($tt>0)
         {
	   if($available>0)
	   {
               
	   //	echo '<div class="subItem authorField"><b>'.__('Total Book').'</b> : '.$total.'</div>';
	   //     echo '<div class="subItem authorField"><b>'.__('Available Book').'</b> : '.$available.'</div>';
	
                $myhtml .= '<div class="subItem authorField"><b>'.__('Total Book').'</b> : '.$total.'</div>';
	        $myhtml .= '<div class="subItem authorField"><b>'.__('Available Book').'</b> : '.$available.'</div>';
	
	      $sql="select item_code from item where biblio_id=".$_biblio_d['biblio_id']." AND item_code NOT IN 
		   (select item_code from loan where biblio_id=".$_biblio_d['biblio_id']." AND return_date is null) limit 0,1";
               
	       $sql=$this->obj_db->query($sql);
	        $s1='';
	        while($s=$sql->fetch_assoc())
	        {
	          $s1.=$s['item_code'];
                  $_session['item_code']=$s['item_code'];
                //$s['item_code'];
	       }
		if (utility::isMemberLogin())
		 {	
		 }
	   }
	   else
	   {
			//echo '<div class="subItem authorField"><b>'.__('Total Book').'</b> : '.$total.'</div>';
	                //echo '<div class="subItem authorField"><b>'.__('Available Book').'</b> : '.$available.'</div>';
                        
                        $myhtml .= '<div class="subItem authorField"><b>'.__('Total Book').'</b> : '.$total.'</div>';
	                $myhtml .= '<div class="subItem authorField"><b>'.__('Available Book').'</b> : '.$available.'</div>';

                    $sql="select item_code from item where biblio_id=".$_biblio_d['biblio_id']." limit 1";
                    $sql=$this->obj_db->query($sql);
                    $s1='';
                    while($s=$sql->fetch_assoc())
                    {
                        $s1.=$s['item_code'];
                        $_session['item_code']=$s['item_code'];
                    }   
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

			//echo '<div align=left><strong width="50%" style="color: red;">'.__('All Copy Currently On Loan').'<br/>'.__('One Of The Copy Due On ').'('.date($sysconf['date_format'], strtotime($due1)).')</strong></div>'; 
                        $myhtml .= '<div align=left><strong width="50%" style="color: red;">'.__('All Copy Currently On Loan').'<br/>'.__('One Of The Copy Due On ').'('.date($sysconf['date_format'], strtotime($due1)).')</strong></div>'; 
                        
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
		if (utility::isMemberLogin())
		 {	
                  }
		
	   }          

           $redirect_string=$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING'];
           //echo"<form action='".$redirect_string."' method=\"post\">";
           $myhtml .= "<form action='".$redirect_string."' method=\"post\">";
           
//           echo "<div class='subItem'><input type='submit' name='sub' value='Add To Cart' >
//               <input type='hidden' name='item_code' value=".$_session['item_code']." ></div>";
//           echo "</form>";
           
           $myhtml .= "<div class='subItem'><input type='submit' name='sub' value='Add To Cart' >
               <input type='hidden' name='item_code' value=".$_session['item_code']." ><input type='hidden' name='biblio_id' value=".$_biblio_d['biblio_id']." ></div>";
           
           $myhtml .= "</form>";
            }
            else
            {	
                      $biblio_material_type=$this->obj_db->query('select material_resource_id from biblio where biblio_id='.$_biblio_d['biblio_id'].'');
                      $biblio_material_type_id=$biblio_material_type->fetch_assoc();
                      if($biblio_material_type_id['material_resource_id']==5)
                      {
                       //echo '<div class="subItem"><strong style="color: red; font-weight: bold;">'.__('No Copy Available For This Book').'</strong></div>';
                       //$myhtml .= '<div class="subItem"><strong style="color: red; font-weight: bold;">'.__('No Copy Available For This Book').'</strong></div>';
                      }

            }
                   $biblio_material_type_new=$this->obj_db->query('select material_sub_id from biblio where biblio_id='.$_biblio_d['biblio_id'].'');
                    $biblio_material_type_id_new=$biblio_material_type_new->fetch_assoc();
                    $biblio_material_type_name = $this->obj_db->query('select material_sub_name from mst_material_sub_type where material_sub_id ='.$biblio_material_type_id_new['material_sub_id'].'');
                    $biblio_material_type_name_id = $biblio_material_type_name->fetch_assoc();
                    $myhtml .= '<div><b>Material Type :- </b><strong style="color: green; font-weight: bold;">'.$biblio_material_type_name_id['material_sub_name'].'</strong></div>';	
                    //echo '<div><b>Material Type :- </b><strong style="color: red; font-weight: bold;">'.$biblio_material_type_name_id['material_sub_name'].'</strong></div>';	
                  

                if(empty($_biblio_d['count']) )
                {
                    $flag_sumamry=0;
                    if(!empty($_biblio_d['abstract']))
                    {
                     $myhtml .= '<div class="subItem authorField"><b>'.__('Summary').'</b> : '.$_biblio_d['abstract'].'</div>';
                     //echo '<div class="subItem authorField"><b>'.__('Summary').'</b> : '.$_biblio_d['abstract'].'</div>';
                    }
                    else
                    {
                        $flag_summary=1;
                    }
                }
                if(empty($_biblio_d['count']) && $_GET['p']!="myeself")
                {
                if(!empty($_SESSION['USER_GROUP_ID']))
                {
                //echo '<iframe src=lib/like.php?subid='.$_biblio_d['biblio_id'].'&memberid="'.$_SESSION['mid'].'" width=400px height=100px frameBorder=0></iframe>';
                $myhtml .= '<iframe src=lib/like.php?subid='.$_biblio_d['biblio_id'].'&memberid="'.$_SESSION['DUSER_ID'].'" width=400px height=100px frameBorder=0></iframe>';
                }
                }
                if(isset($_biblio_d['count']))
                {
                $myhtml .= '<div align="left">View :- '.$_biblio_d['count'].'</div>';
                //echo '<div align="left">View :- '.$_biblio_d['count'].'</div>';
                }
                    $myhtml .= '</td>';
                    //echo '</td>';
                    if(!empty($_image_cover))
                    {
                         if($_GET['p']!='rescentview' && $_GET['p']!='myeself')
                            {		
                                    $myhtml .=  '<td  align="center" valign="top" width=30%> '.$_image_cover; 
                                    //echo  '<td  align="center" valign="top" width=30%> '.$_image_cover; 
                            }
                         else
                            {
                                 //echo  '<td  align="center" valign="top"> '.$_image_cover;   
                                 $myhtml .=  '<td  align="center" valign="top" width=30%> '.$_image_cover; 
                            }  
  
                    }
                    else
                    {
                            $img_set = $_biblio_d['isbn_issn'];
                            if($_GET['p']!='rescentview' && $_GET['p']!='myeself')
                            {
                                $myhtml .= '<td  align="center" valign="top" width=30%> '; 
                                //echo '<td  align="center" valign="top" width=30%> '; 
                            }
                            else
                            {
                                $myhtml .= '<td  align="center" valign="top"> '; 
                                //echo '<td  align="center" valign="top"> '; 
                            }
                            if($_biblio_d['material_sub_id']=='85' || $_biblio_d['material_sub_id']=='84' || $_biblio_d['material_sub_id']=='113' || $_biblio_d['material_sub_id']=='112' || $_biblio_d['material_sub_id']=='130' || $_biblio_d['material_sub_id']=='132')
                            {   
                                    $myhtml .= '<img src="lib/images/imagesnotavailable.jpg" ID=$img_set  height=120px width=100px />';
                                  //echo '<img src="lib/images/imagesnotavailable.jpg" ID=$img_set  height=120px width=100px />';

                             }	
  
                      }
                    
                     //echo "<div align=center class='subItem'>";
                    if ($_SESSION['USER_GROUP_ID']=='2' || $_SESSION['USER_GROUP_ID']=='3')
                    {	
                                $biblio_myself=$this->obj_db->query('select biblio_id from biblio_myself where biblio_id='.$_biblio_d['biblio_id'].' AND member_id="'.$_SESSION['mid'].'"');
                                $biblio_id_myself=$biblio_myself->fetch_assoc();

                                            if($biblio_id_myself['biblio_id']!='')
                                            {
                                            }
                                            else
                                            {
                                                $url="http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
//echo $url;
                                                $records = explode("?",$url);                             
                                                $records[1] = str_replace("myself", "lastself", $records[1]);
                                                   //echo "index.php?".$records[1]."&myself=".$_biblio_d['biblio_id'];die;
                                                //echo "<a href=index.php?".$records[1]."&myself=".$_biblio_d['biblio_id']."><img src=lib/images/shelf.png height=30px width=30px border=0 title=Add-To-My-Eshelf /></a>";
                                                $myhtml .= "<a href=index.php?".$records[1]."&myself=".$_biblio_d['biblio_id']."><img src=lib/images/shelf.png height=30px width=30px border=0 title=Add-To-My-Eshelf /></a>";
                                            }
                    }
                    if($flag_summary==1)
                    {                                         
                        //echo "<a href='#' onclick=Summary_book($_biblio_d[isbn_issn]); >   <img src='lib/images/summary.png' height=30px width=30px border=0 title='summary'/></a>";                      
                        $myhtml .= '<a href="javascript:void(0)" onclick=javascript:window.open("lib/test3.php?img_set='.$_biblio_d['isbn_issn'].'","Window1","menubar=no,width=600,height=300,toolbar=no,scrollbars=yes,location=no,left=200px"); >                        <img src="lib/images/summary.png" height=30px width=30px border=0 title="summary"/></a>';                      
                        //echo '<a href="javascript:void(0)" onclick=javascript:window.open("lib/test3.php?img_set='.$_biblio_d['isbn_issn'].'","Window1","menubar=no,width=600,height=300,toolbar=no,scrollbars=yes,location=no,left=200px"); >                        <img src="lib/images/summary.png" height=30px width=30px border=0 title="summary"/></a>';                      
                        $flag_summary=0;
                    } 
                    else
                    {
                          $myhtml .= '<a href="javascript:void(0)" onclick=javascript:window.open("lib/test3.php?img_set='.$_biblio_d['isbn_issn'].'","Window1","menubar=no,width=600,height=300,toolbar=no,scrollbars=yes,location=no,left=200px"); >                        <img src="lib/images/summary.png" height=30px width=30px border=0 title="summary"/></a>';                      
                          //echo '<a href="javascript:void(0)" onclick=javascript:window.open("lib/test3.php?img_set='.$_biblio_d['isbn_issn'].'","Window1","menubar=no,width=600,height=300,toolbar=no,scrollbars=yes,location=no,left=200px"); >                        <img src="lib/images/summary.png" height=30px width=30px border=0 title="summary"/></a>';                      
                        $flag_summary=0;
                    }

//                    echo '<a href="javascript:void(0)" onclick=javascript:window.open("lib/abtautor.php?img_set='.$_biblio_d['isbn_issn'].'","Window1","menubar=no,width=600,height=600,toolbar=no,scrollbars=yes,location=no,left=200px"); ><img src="lib/images/author.png" height=30px width=30px border=0 title="Author-Biography"/></a>';
//                    echo $set_title.'</div><br/>'; 
//                    echo '</td></tr>';
                    
                    $myhtml .= '<a href="javascript:void(0)" onclick=javascript:window.open("lib/abtautor.php?img_set='.$_biblio_d['isbn_issn'].'","Window1","menubar=no,width=600,height=600,toolbar=no,scrollbars=yes,location=no,left=200px"); ><img src="lib/images/author.png" height=30px width=30px border=0 title="Author-Biography"/></a>';
                    $myhtml .= $set_title.'</div><br/>'; 
                    $myhtml .= '</td></tr>';

            if(isset($_biblio_d['count']) || $_GET['p']=='rescentview')
            {
               if($i%1 == 0){
                            $myhtml .= '<tr class="alterCell3">';
                            //echo '<tr class="alterCell3">';
                            }	
            }
            else if($_GET['p']=='myeself')
            {
            if($i%1 == 0){
                            $myhtml .= '<tr class="alterCell3">';
                            //echo '<tr class="alterCell3">';
                            }
            }
            else
            {
               if($i%1 == 0){
                            $myhtml .= '<tr class="alterCell3">';
                            //echo '<tr class="alterCell3">';
                            }	
            }
             $i++;
             
             //added started and commented by parth 3/8/2011
                
        }
if(isset($_biblio_d['count']) || $_GET['p']!='myeself')
                {
                   if($i%1 == 0){
                                //echo '</tr>';
                                 $myhtml .= '</tr>';
                                }	
                }
                else
                {
                   if($i%1 == 0)
                   {
                        //echo '</tr>';
                        $myhtml .= '</tr>';
                   }	
                }
                 
//echo "Buffer==".$_buffer;	
//echo "</table>";  
$myhtml .= "</table>";  
//echo "</form>";
$myhtml .= "</form>";        
        $this->resultset->free_result();
 
     if($_GET['p']!='rescentview') // paging
     {
        if (($this->num_rows > $this->num2show)) {
            $_paging = '<hr width="97%" size="1" />'."\n";
            $_paging .= '<div style="text-align: center;">'.simbio_paging::paging($this->num_rows, $this->num2show, 5).'</div>';
       } else {
//            $_paging = '<hr width="97%" size="1" />'."\n";
//            $_paging .= '<div style="text-align: center;">'.simbio_paging::paging($this->num_rows, $this->num2show, 5).'</div>';
        }
     }
        
        echo $myhtml;
        //return $p;
        return $_buffer.$_paging;
 }
 
 function download_file($file_name) {

    if (!file_exists($file_name)) { die("<b>404 File not found!</b>"); }
   
    $file_extension = strtolower(substr(strrchr($file_name,"."),1));
    $file_size = filesize($file_name);
    $md5_sum = md5_file($file_name);
   
   //This will set the Content-Type to the appropriate setting for the file
    switch($file_extension) {
        case "exe": $ctype="application/octet-stream"; break;
        case "zip": $ctype="application/zip"; break;
        case "mp3": $ctype="audio/mpeg"; break;
        case "mpg":$ctype="video/mpeg"; break;
        case "avi": $ctype="video/x-msvideo"; break;

        //The following are for extensions that shouldn't be downloaded (sensitive stuff, like php files)
        case "php":
        case "htm":
        case "html":
        case "txt": die("<b>Cannot be used for ". $file_extension ." files!</b>"); break;

        default: $ctype="application/force-download";
    }
   
    if (isset($_SERVER['HTTP_RANGE'])) {
        $partial_content = true;
        $range = explode("-", $_SERVER['HTTP_RANGE']);
        $offset = intval($range[0]);
        $length = intval($range[1]) - $offset;
    }
    else {
        $partial_content = false;
        $offset = 0;
        $length = $file_size;
    }
   
    //read the data from the file
    $handle = fopen($file_name, 'r');
    $buffer = '';
    fseek($handle, $offset);
    $buffer = fread($handle, $length);
    $md5_sum = md5($buffer);
    if ($partial_content) $data_size = intval($range[1]) - intval($range[0]);
    else $data_size = $file_size;
    fclose($handle);
   
    // send the headers and data
    header("Content-Length: " . $data_size);
    header("Content-md5: " . $md5_sum);
    header("Accept-Ranges: bytes");   
    if ($partial_content) header('Content-Range: bytes ' . $offset . '-' . ($offset + $length) . '/' . $file_size);
    header("Connection: close");
    header("Content-type: " . $ctype);
    header('Content-Disposition: attachment; filename=' . $file_name);
    echo $buffer;
    flush();
} 
 
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

?>






