<?php
/**
 * module class
 * Application modules related class
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

class module extends simbio {
	private $modules_dir = 'modules';
	private $module_table = 'mst_module';
	public $module_list = array();
	//(comment by iresh on 11/1/2011)public $appended_first = '<li><a class="menu" href="index.php">Home</a></li><li><a class="menu" href="../index.php" title="View OPAC in New Window" target="_blank">OPAC</a></li>';
	/*added by iresh on 11/1/2011 */
	public $appended_first = '<li><a class="waves-effect" href="index.php">Home</a></li><!--<li><a class="menu" href="../index.php" title="View OPAC in New Window" target="_blank">Member Area</a></li>-->';
	//public $appended_last = '<li><a class="menu" href="logout.php">Logout</a></li>';

	/**
	 * Method to set modules directory
	 *
	 * @param   string  $str_modules_dir
	 * @return  void
	 */
	public function setModulesDir($str_modules_dir) {
		$this->modules_dir = $str_modules_dir;
	}

	/**
	 * Method to generate a list of module menu
	 *
	 * @param   object  $obj_db
	 * @return  string
	 */
	public function generateModuleMenu($obj_db) {
		// get module data from database
		//echo 'SELECT * FROM '.$this->module_table;die;
		$_mods_q = $obj_db->query('SELECT * FROM ' . $this->module_table);
		while ($_mods_d = $_mods_q->fetch_assoc()) {

			if ($_mods_d['module_name'] != 'stock_take' && $_mods_d['module_name'] != 'serial control' && $_mods_d['module_name'] != 'budget' && $_mods_d['module_name'] != 'system') {
				$this->module_list[] = array('name' => $_mods_d['module_name'], 'path' => $_mods_d['module_path'], 'desc' => $_mods_d['module_desc']);
			}

		}

		// create the HTML Hyperlinks
		// $_menu = '';
		//      $_menu .= $this->appended_first;
		//      // sort modules
		//      if ($this->module_list) {
		//          foreach ($this->module_list as $_module) {
		//              $_formated_module_name = ucwords(str_replace('_', ' ', $_module['name']));
		//              $_mod_dir = $_module['path'];
		//              if (isset($_SESSION['priv'][$_module['path']]['r']) && $_SESSION['priv'][$_module['path']]['r'] && file_exists($this->modules_dir.$_mod_dir)) {
		//                  $_menu .= '<a class="menu'.( (isset($_GET['mod']) && $_GET['mod']==$_module['path'])?' menuCurrent':'' ).'" title="'.$_module['desc'].'" href="index.php?mod='.$_mod_dir.'">'.__($_formated_module_name).'</a>';
		//              }
		//          }
		//      }
		//      $_menu .= $this->appended_last;
		//      $_menu .= '';

		$_menu = '<div class="sidebarNew" role="navigation" style="background:white;width:200px;">


        <ul class="nav in" id="side-menu" style="background:white;width:200px;">';
		$_menu .= $this->appended_first;
		// sort modules
		if ($this->module_list) {
			foreach ($this->module_list as $_module) {
				$_formated_module_name = ucwords(str_replace('_', ' ', $_module['name']));
				$_mod_dir = $_module['path'];
//                if($_mods_d['module_name']=='Circulation' && !isset($_REQUEST['finish']))
				//                {
				//                    $_menu .= '<li><a class="menu" href="circulation/circulation_action.php">'.$_mods_d['module_name'] .'</a></li>';
				//
				if (isset($_SESSION['priv'][$_module['path']]['r']) && $_SESSION['priv'][$_module['path']]['r'] && file_exists($this->modules_dir . $_mod_dir)) {
					$_menu .= '<li><a class="waves-effect"' . ((isset($_GET['mod']) && $_GET['mod'] == $_module['path']) ? ' menuCurrent' : '') . '" title="' . $_module['desc'] . '" href="index.php?mod=' . $_mod_dir . '"><i class="mdi mdi-av-timer fa-fw" data-icon="v"></i> <span class="hide-menu">' . __($_formated_module_name) . '</span></a>';
					if ($_formated_module_name != 'Home') {
						$menu = array();
						// $_menu .= $this->generateSubMenu($_formated_module_name);
						if ($_formated_module_name == 'Catalog') {
							$_formated_module_name = 'bibliography';
						}
						if ($_formated_module_name == 'Member Area') {
							$_formated_module_name = 'membership';
						}
						$url = '../admin/modules/' . str_replace(" ", "_", strtolower($_formated_module_name)) . '/submenu.php';
						include $url;

						foreach ($menu as $_list) {
							// if ($_list[0] == 'Header2') {
							// 	$_menu .= '<div class="cal_top_bg"><p class="title">' . $_list[1] . '</p></div><div class="side_mid_bg">';
							// } else if ($_list[0] == 'Header') {
							// 	$_menu .= '<div class="subMenuHeader">' . $_list[1] . '</div>';
							// } else {

							$_menu .= '<li><a class="waves-effect" '
								. ' href="' . $_list[1] . '"'
								. ' title="' . (isset($_list[2]) ? $_list[2] : $_list[0]) . '" href="#"><i class="mdi mdi-av-timer fa-fw" data-icon="v"></i> <span class="hide-menu">' . $_list[0] . '</span></a></li>';
							// }
						}

					}
					$_menu .= "</li>";
				}
			}
		}
		$_menu .= $this->appended_last;
		$_menu .= '</ul></div>';

		return $_menu;
	}

	/**
	 * Method to generate a list of module submenu
	 *
	 * @param   string  $str_module
	 * @return  string
	 */
	public function generateSubMenu($str_module = '') {
		//echo $str_module;die;
		global $dbs;
		$_submenu = '';
		echo $_submenu_file = $this->modules_dir . $str_module . DIRECTORY_SEPARATOR . 'submenu.php';
		if (file_exists($_submenu_file)) {
			include $_submenu_file;
		} else {
			//  include 'default/submenu.php';
		}
		// iterate menu array
		foreach ($menu as $_list) {
			if ($_list[0] == 'Header2') {
				$_submenu .= '<div class="cal_top_bg"><p class="title">' . $_list[1] . '</p></div><div class="side_mid_bg">';
			} else if ($_list[0] == 'Header') {
				$_submenu .= '<div class="subMenuHeader">' . $_list[1] . '</div>';
			} else {

				$_submenu .= '<a class="subMenuItem" '
					. ' href="' . $_list[1] . '"'
					. ' title="' . (isset($_list[2]) ? $_list[2] : $_list[0]) . '" href="#">' . $_list[0] . '</a>';
			}
		}
		$_submenu .= '</div><div class="cal_bot_bg"></div>&nbsp;';
		return $_submenu;
	}
//added started by Parth 24/8/2011
	public function generateSubMenu_admin_home($str_module = '') {

		global $dbs;
		$_submenu = '';
		$_submenu_file = $this->modules_dir . $str_module . DIRECTORY_SEPARATOR . 'submenu.php';
		if (file_exists($_submenu_file)) {
			include $_submenu_file;

		} else {
			include 'default/submenu.php';
		}
		// iterate menu array
		foreach ($menu as $_list) {
			if ($_list[0] == 'Header2') {
			} else if ($_list[0] == 'Header') {
				$_submenu .= '<div class="subMenuHeader">' . $_list[1] . '</div>';
			} else {

				$_submenu .= '<a class="subMenuItem" '
					. ' href="' . $_list[1] . '"'
					. ' title="' . (isset($_list[2]) ? $_list[2] : $_list[0]) . '" href="#">' . $_list[0] . '</a>';
			}
		}

		$_submenu .= '&nbsp;';
		return $_submenu;
	}
//added ended by Parth 24/8/2011
}
?>
