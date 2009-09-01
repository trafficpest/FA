<?php
/**********************************************************************
    Copyright (C) FrontAccounting, LLC.
	Released under the terms of the GNU General Public License, GPL, 
	as published by the Free Software Foundation, either version 3 
	of the License, or (at your option) any later version.
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
    See the License here <http://www.gnu.org/licenses/gpl-3.0.html>.
***********************************************************************/
$page_security = 'SA_SECROLES';
$path_to_root = "..";
include_once($path_to_root . "/includes/session.inc");

page(_("Access setup"));

include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/includes/access_levels.inc");
include_once($path_to_root . "/admin/db/security_db.inc");

$new_role = get_post('role')=='' || get_post('cancel') || get_post('clone'); 
//--------------------------------------------------------------------------------------------------
if (list_updated('role')) {
	$Ajax->activate('details');
	$Ajax->activate('controls');
}

function clear_data()
{
	unset($_POST);
}

if (get_post('addupdate'))
{
   	$input_error = 0;
	if ($_POST['description'] == '')
   	{
      	$input_error = 1;
      	display_error( _("Role description cannot be empty."));
		set_focus('description');
   	}
   	elseif ($_POST['name'] == '')
   	{
      	$input_error = 1;
      	display_error( _("Role name cannot be empty."));
		set_focus('name');
   	}
	
	if ($input_error == 0)
	{
		$sections = array();
		$areas = array();
		foreach($_POST as $p =>$val) {
			if (substr($p,0,4) == 'Area')
				$areas[] = substr($p, 4);
			if (substr($p,0,7) == 'Section')
				$sections[] = substr($p, 7);
		}
		sort($areas);
		sort($sections);
     	if ($new_role) 
       	{
			add_security_role($_POST['name'], $_POST['description'], $sections, $areas); 
			display_notification(_("New security role has been added."));
       	} else
       	{
			update_security_role($_POST['role'], $_POST['name'], $_POST['description'], 
				$sections, $areas); 
			update_record_status($_POST['role'], get_post('inactive'),
				'security_roles', 'id');

	  		display_notification(_("Security role has been updated."));
       	}
	$new_role = true;
	clear_data();
	$Ajax->activate('_page_body');
	}
}

//--------------------------------------------------------------------------------------------------

if (get_post('delete'))
{
	if (check_role_used(get_post('role'))) {
		display_error(_("This role is currently assigned to some users and cannot be deleted"));
 	} else {
		delete_security_role(get_post('role'));
		display_notification(_("Security role has been sucessfully deleted."));
		unset($_POST['role']);
	}
	$Ajax->activate('_page_body');
}

if (get_post('cancel'))
{
	unset($_POST['role']);
	$Ajax->activate('_page_body');
}

if (!isset($_POST['role']) || get_post('clone') || list_updated('role')) {
	$id = get_post('role');
	$clone = get_post('clone');
//	clear_data();
	unset($_POST);
	if ($id) {
		$row = get_security_role($id);
		$_POST['description'] = $row['description'];
		$_POST['name'] = $row['role'];
//	if ($row['inactive']
//		$_POST['inactive'] = 1;
	
		$_POST['inactive'] = $row['inactive'];
		$access = $row['areas'];
		$sections = $row['sections'];
	}
	else {
		$_POST['description'] = $_POST['name'] = '';
		unset($_POST['inactive']);
		$access = $sections = array();
	}
	foreach($access as $a) $_POST['Area'.$a] = 1;
	foreach($sections as $s) $_POST['Section'.$s] = 1;

	if($clone) {
		set_focus('name');
		$Ajax->activate('_page_body');
	} else
		$_POST['role'] = $id;
}

//--------------------------------------------------------------------------------------------------

start_form();

start_table("class='tablestyle_noborder'");
start_row();
security_roles_list_cells(_("Role:"). "&nbsp;", 'role', null, true, true, check_value('show_inactive'));
$new_role = get_post('role')=='';
check_cells(_("Show inactive:"), 'show_inactive', null, true);
end_row();
end_table();
echo "<hr>";

if (get_post('_show_inactive_update')) {
	$Ajax->activate('role');
	set_focus('role');
}
if (find_submit('_Section')) {
	$Ajax->activate('details');
//	set_focus('');
}
//-----------------------------------------------------------------------------------------------
div_start('details');
start_table($table_style2);
	text_row(_("Role name:"), 'name', null, 20, 22);
	text_row(_("Role description:"), 'description', null, 50, 52);
	record_status_list_row(_("Current status:"), 'inactive');
end_table(1);

	start_table("$table_style width=40%");

	$k = $j = 0; //row colour counter
	$m = 0;
	asort($security_areas); // in the case installed external modules has added some lines
	foreach($security_areas as $area =>$parms ) {
		if (($parms[0]&~0xff) != $m)
		{ // features set selection
			$m = $parms[0] & ~0xff;
			label_row($security_sections[$m].':', 
				checkbox( null, 'Section'.$m, null, true, 
					_("On/off set of features")),
			"class='tableheader2'", "class='tableheader'");
		}
		if (check_value('Section'.$m)) {
				alt_table_row_color($k);
				check_cells($parms[1], 'Area'.$parms[0], null, 
					false, '', "align='center'");
			end_row();
		} else {
			hidden('Area'.$parms[0]);
		}
	}
	end_table(1);
div_end();

div_start('controls');

if ($new_role) 
{
	submit_center_first('Update', _("Update view"), '', null);
	submit_center_last('addupdate', _("Insert New Role"), '', 'default');
} 
else 
{
	submit_center_first('addupdate', _("Save Role"), '', 'default');
	submit('Update', _("Update view"), true, '', null);
	submit('clone', _("Clone This Role"), true, '', true);
	submit('delete', _("Delete This Role"), true, '', true);
	submit_center_last('cancel', _("Cancel"), _("Cancel Edition"), 'cancel');
}

div_end();

end_form();
end_page();

?>
