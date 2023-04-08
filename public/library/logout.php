<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

session_start();

unset ($_SESSION['mid']);
unset ($_SESSION['m_name']);
unset ($_SESSION['m_email']);
unset ($_SESSION['m_institution']);
unset ($_SESSION['m_logintime']);

header("location:../../dashboard.php");
?>
