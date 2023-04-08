<?php


class member_logon
{
    protected $username = '';
    protected $password = '';

    /**
     * Class Constructor
     *
     * @param   string  $str_username
     * @param   string  $str_password
     */
    public function __construct($str_username, $str_password)
    {
        $this->username = $str_username;
        $this->password = $str_password;
    }


    /**
     * Method to check user validity
     *
     * @param   object  $obj_db
     * @return  void
     */
    public function valid($obj_db)
    {
        
                
        $_sql_member_login = sprintf("SELECT m.enrollment_no, m.first_name, m.institute_id,
            m.email, m.expire_date, m.created_on, m.is_active,
            m.user_group_id, mt.user_group_name
            FROM ".$inte_schema.".tblstudent AS m 
			LEFT JOIN ".$inte_schema.".tbluser_groups AS mt ON m.user_group_id=mt.user_group_id
            WHERE m.enrollment_no='%s' and m.sub_institute_id = '".$_SESSION['SUB_INSTITUTE_ID']."'
                AND m.password=MD5('%s')", $obj_db->escape_string(trim($this->username)), $obj_db->escape_string(trim($this->password)));
        $_member_q = $obj_db->query($_sql_member_login);

        // error check
        if ($obj_db->error) {
            echo '<div style="border: 1px dotted #FF0000; color: #FF0000; padding: 5px; margin: 3px;">'.__('Error authenticating user and password to database').'</div>';
            return false;
        }

        // check if the user exist in database
        if ($_member_q->num_rows < 1) {
            return false;
        } else {
            // fetch data
            $_member_d = $_member_q->fetch_assoc();

            // fill all sessions var
            $_SESSION['mid'] = $_member_d['enrollment_no'];
            $_SESSION['m_name'] = $_member_d['first_name'];
            $_SESSION['m_email'] = $_member_d['email'];
            $_SESSION['m_institution'] = $_member_d['institute_id'];
            $_SESSION['m_logintime'] = time();
            $_SESSION['m_expire_date'] = $_member_d['expire_date'];
            $_SESSION['m_member_type_id'] = $_member_d['user_group_id'];
            $_SESSION['m_member_type'] = $_member_d['user_group_name'];
            $_SESSION['m_register_date'] = $_member_d['created_on'];
            $_SESSION['m_membership_pending'] = intval($_member_d['is_active'])?true:false;
            $_SESSION['m_is_expired'] = false;
            // check member expiry date
            require_once SIMBIO_BASE_DIR.'simbio_UTILS/simbio_date.inc.php';
            $_curr_date = date('Y-m-d');
            if (simbio_date::compareDates($_member_d['expire_date'], $_curr_date) == $_curr_date) {
                $_SESSION['m_is_expired'] = true;
            }
            // save md5sum of current application path
            // $_SESSION['checksum'] = md5($_SERVER['SERVER_ADDR'].SENAYAN_BASE_DIR);

            // update the last login time
            $obj_db->query("UPDATE ".$inte_schema.".tblstudent SET last_login='".date("Y-m-d H:i:s")."',
                last_login_ip='".$_SERVER['REMOTE_ADDR']."'
                WHERE enrollment_no='".$_member_d['enrollment_no']."'");
//echo "hi";
            return true;
        }
        return false;
    }
}
?>
