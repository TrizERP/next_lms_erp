<?php

class content
{
    public $strip_html = false;
    public $allowed_tags = null;

    public function get($obj_db, $str_path = '')
    {
                
        $_path = strtolower(trim($str_path));
        if (!$_path) {
            return;
        }

        if (preg_match('@^admin.+@i', $_path)) {
            $_unauthorized = !isset($_SESSION['uid']) AND !isset($_SESSION['uname']) AND !isset($_SESSION['realname']);
            if ($_unauthorized) {
                return;
            }
        }
        // query content
        $_sql_content = sprintf('SELECT * FROM content WHERE content_path=\'%s\'', $obj_db->escape_string($_path));
        $_content_q = $obj_db->query($_sql_content);
        // get content data
        $_content_d = $_content_q->fetch_assoc();
        if (!$_content_d['content_title'] OR !$_content_d['content_path']) {
            return false;
        } else {
            $_content['Title'] = $_content_d['content_title'];
            $_content['Path'] = $_content_d['content_path'];
            $_content['Content'] = $_content_d['content_desc'];
            // strip html
            if ($this->strip_html) {
                $_content['Content'] = strip_tags($_content['Content'], $this->allowed_tags);
            }

           return $_content;
        }
    }
}

?>
