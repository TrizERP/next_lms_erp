<?php
          

/* MEMBER BASE LIBRARY */

class member
{	
    # class properties
    public $member_id = null;
    public $member_name = null;
	public $member_mobile = null;
	public $member_standard = null;
    public $member_type_id = null;
    public $member_type_name = null;
    public $register_date = null;
    public $expire_date = null;
    public $member_email = null;
    public $inst_name = null;
    public $is_member = false;
    public $member_image = '';
    public $member_notes = null;
    protected $is_expire = true;
//    protected $is_pending = true;
    protected $member_type_prop = array();
    protected $obj_db = false;

    # class constructor
    public function __construct($obj_db, $str_member_id)
    {

        $inte_schema = $_SESSION['inte_schema'];
        $dyear = $_SESSION['dyear'];
        $sub_institute_id = $_SESSION['SUB_INSTITUTE_ID'];

        $this->obj_db = $obj_db;

        if(!empty($str_member_id))
        {     

            $sql = "SELECT m.*,mt.*,m.created_on AS register_date,up.name AS user_group_name,up.id as user_group_id,
                    CONCAT_WS('-',cs.name,ss.name) AS standard
                    FROM ".$inte_schema.".tblstudent AS m
                    INNER JOIN ".$inte_schema.".tblstudent_enrollment se ON se.student_id=m.id AND se.syear = ".$dyear." AND se.sub_institute_id = m.sub_institute_id
                    INNER JOIN ".$inte_schema.".standard cs ON cs.id=se.standard_id AND cs.sub_institute_id = m.sub_institute_id
                    INNER JOIN ".$inte_schema.".division ss ON ss.id=se.section_id AND ss.sub_institute_id = m.sub_institute_id
                    INNER JOIN ".$inte_schema.".tbluserprofilemaster up ON up.id = m.user_profile_id AND up.sub_institute_id = m.sub_institute_id
                    INNER JOIN category_user AS cu ON up.name=cu.user_name
                    LEFT JOIN category_mast AS mt ON cu.user_id = mt.cat_mast_user
                    WHERE m.sub_institute_id = ".$sub_institute_id." AND m.enrollment_no= '".$this->obj_db->escape_string($str_member_id)."'
                    GROUP BY m.id ";
			$_member_q = $this->obj_db->query($sql);
			
            if ($_member_q->num_rows > 0) 
            {
             
                $this->is_member = true;
                $_member_d = $_member_q->fetch_assoc();

                // assign database value to class properties
                $this->member_id = $_member_d['enrollment_no'];
                $this->member_name = $_member_d['first_name'].' '.$_member_d['middle_name'].' '.$_member_d['last_name'];
    			$this->member_mobile = $_member_d['mobile'];
    			$this->member_standard = $_member_d['standard'];
                $this->member_type_id = $_member_d['user_group_id'];
                $this->member_type_name = $_member_d['user_group_name'];
                $this->register_date = $_member_d['register_date'];
                $this->expire_date = $_member_d['expire_date'];
                $this->member_email = $_member_d['email'];
                $this->inst_name = $_member_d['sub_institute_id'];
                $this->member_image = "https://".$_SERVER['SERVER_NAME']."/storage/student/".$_member_d['image'];
             
                $this->member_type_prop = array(
                        'loan_limit' => $_member_d['cat_issue_limit'],
                        'loan_periode' => $_member_d['cat_issue_period'],
                        'reserve_limit' => $_member_d['cat_issue_period'],
                        'reborrow_limit' => $_member_d['cat_re_issue_limit'],
                        'fine_each_day' => $_member_d['cat_fine_each_day'],
                    );

                $_current_date = date('Y-m-d');            
                $_expired_date = simbio_date::compareDates($_current_date, $_member_d['expire_date']);            
                if ($_expired_date <= $_member_d['expire_date'])
                {
                    $this->is_expire = false;
                }
            }
            else
            {
                
                $sql="SELECT m.user_name AS 'user',m.*,mt.*,m.created_on as register_date,cu.user_name AS user_group_name,up.id as user_group_id
                    FROM ".$inte_schema.".tbluser AS m
                    INNER JOIN ".$inte_schema.".tbluserprofilemaster up ON up.id = m.user_profile_id AND up.sub_institute_id = m.sub_institute_id 
                    INNER JOIN category_user AS cu ON up.name=cu.user_name
                    LEFT JOIN category_mast AS mt ON cu.user_id = mt.cat_mast_user
                    WHERE m.SUB_INSTITUTE_ID= ".$sub_institute_id." AND m.user_name='".$this->obj_db->escape_string($str_member_id)."' 
                    GROUP BY m.id";
                $_member_q = $this->obj_db->query($sql);

                if ($_member_q->num_rows > 0) 
                {
                    $this->is_member = true;
                    $_member_d = $_member_q->fetch_assoc();
  
                    // assign database value to class properties
                    $this->member_id = $_member_d['user'];
    				$this->member_name = $_member_d['first_name'].' '.$_member_d['middle_name'].' '.$_member_d['last_name'];
    				$this->member_mobile = $_member_d['mobile'];
                    $this->member_type_id = $_member_d['user_group_id'];
                    $this->member_type_name = $_member_d['user_group_name'];
                    $this->register_date = $_member_d['register_date'];
                    $this->expire_date = $_member_d['expire_date'];
                    $this->member_email = $_member_d['email'];
                    $this->inst_name = $_member_d['sub_institute_id'];
                    $this->member_image = "https://".$_SERVER['SERVER_NAME']."/storage/user/".$_member_d['image'];

                    $this->member_type_prop = array(
                            'loan_limit' => $_member_d['cat_issue_limit'],
                            'loan_periode' => $_member_d['cat_issue_period'],
                            'reserve_limit' => $_member_d['cat_issue_period'],
                            'reborrow_limit' => $_member_d['cat_re_issue_limit'],
                            'fine_each_day' => $_member_d['cat_fine_each_day'],
                        );

                    $_current_date = date('Y-m-d');                        
                    $_expired_date = simbio_date::compareDates($_current_date, $_member_d['expire_date']);                        
                    if ($_expired_date <= $_member_d['expire_date']) 
                    {
                        $this->is_expire = false;
                    }
                }			
            }
        }
        else
            $this->is_expire = true;
        }

    # checking wether member is valid or not
    # return : boolean
    public function valid()
    {
        return $this->is_member;
    }


    # checking wether membership is already expired
    # return : boolean
    public function isExpired()
    {
        return $this->is_expire;
    }


    # checking wether membership is pending
    # return : boolean
//    public function isPending()
//    {
//        return $this->is_pending;
//    }


    # get all member properties
    # retuen : array
    public function getMemberTypeProp()
    {
        // return all member information as an array
        return $this->member_type_prop;
    }


    # get info about item being lent by member
    # return : array
    protected function getItemLoan($int_loan_rules = 0)
    {
       
      
    $_arr_on_loan = array();
        // count how many items is in loan for this member
        if ($int_loan_rules) 
        {
            $_sql_str = "SELECT l.item_code FROM loan AS l 
                            inner join item it on it.item_code=l.item_code
                            inner join biblio b on it.biblio_id=b.biblio_id
                            WHERE l.member_id='".$this->member_id."' AND 
                            l.loan_date is not null AND l.return_date is null and b.material_sub_id=$int_loan_rules";
        }
        else
        {
            $_sql_str = "SELECT l.item_code FROM loan AS l
                inner join item it on it.item_code=l.item_code
                inner join biblio b on it.biblio_id=b.biblio_id
                WHERE l.member_id='".$this->member_id."' AND l.loan_date is not null  AND l.return_date is null";
        }
        
        $_count_q = $this->obj_db->query($_sql_str);
        while ($_count_d = $_count_q->fetch_row()) {
            $_arr_on_loan[] = $_count_d[0];
        }

        return $_arr_on_loan;
    }
    
  }
?>
