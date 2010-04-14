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
$page_security = 'SA_WORKORDERCOST';
$path_to_root = "..";
include_once($path_to_root . "/includes/session.inc");

include_once($path_to_root . "/includes/date_functions.inc");
include_once($path_to_root . "/gl/includes/db/gl_db_bank_trans.inc");
include_once($path_to_root . "/includes/db/inventory_db.inc");
include_once($path_to_root . "/includes/manufacturing.inc");

include_once($path_to_root . "/manufacturing/includes/manufacturing_db.inc");
include_once($path_to_root . "/manufacturing/includes/manufacturing_ui.inc");

$js = "";
if ($use_popup_windows)
	$js .= get_js_open_window(900, 500);
if ($use_date_picker)
	$js .= get_js_date_picker();
page(_($help_context = "Work Order Additional Costs"), false, false, "", $js);

if (isset($_GET['trans_no']) && $_GET['trans_no'] != "")
{
	$_POST['selected_id'] = $_GET['trans_no'];
}

//--------------------------------------------------------------------------------------------------

if (isset($_GET['AddedID']))
{
	$id = $_GET['AddedID'];
	$stype = ST_WORKORDER;

	display_notification(_("The additional cost has been entered."));

    display_note(get_trans_view_str($stype, $id, _("View this Work Order")));

   	display_note(get_gl_view_str($stype, $id, _("View the GL Journal Entries for this Work Order")), 1);

	hyperlink_params("work_order_costs.php", _("Enter another additional cost."), "trans_no=$id");
 
 	hyperlink_no_params("search_work_orders.php", _("Select another &Work Order to Process"));

	end_page();
	exit;
}

//--------------------------------------------------------------------------------------------------

$wo_details = get_work_order($_POST['selected_id']);

if (strlen($wo_details[0]) == 0)
{
	display_error(_("The order number sent is not valid."));
	exit;
}

//--------------------------------------------------------------------------------------------------

function can_process()
{
	global $wo_details;

	if (!check_num('costs', 0))
	{
		display_error(_("The amount entered is not a valid number or less then zero."));
		set_focus('costs');
		return false;
	}

	if (!is_date($_POST['date_']))
	{
		display_error(_("The entered date is invalid."));
		set_focus('date_');
		return false;
	}
	elseif (!is_date_in_fiscalyear($_POST['date_']))
	{
		display_error(_("The entered date is not in fiscal year."));
		set_focus('date_');
		return false;
	}
	if (date_diff2(sql2date($wo_details["released_date"]), $_POST['date_'], "d") > 0)
	{
		display_error(_("The additional cost date cannot be before the release date of the work order."));
		set_focus('date_');
		return false;
	}

	return true;
}

//--------------------------------------------------------------------------------------------------

if (isset($_POST['process']) && can_process() == true)
{
	begin_transaction();
	add_gl_trans_std_cost(ST_WORKORDER, $_POST['selected_id'], $_POST['date_'], $_POST['cr_acc'],
		0, 0, $wo_cost_types[$_POST['PaymentType']], -input_num('costs'), PT_WORKORDER, $_POST['PaymentType']);
	$is_bank_to = is_bank_account($_POST['cr_acc']);
	if ($is_bank_to)
	{
		add_bank_trans(ST_WORKORDER, $_POST['selected_id'], $is_bank_to, "",
			$_POST['date_'], -input_num('costs'), PT_WORKORDER, $_POST['PaymentType'], get_company_currency(),
			"Cannot insert a destination bank transaction");
	}

	add_gl_trans_std_cost(ST_WORKORDER, $_POST['selected_id'], $_POST['date_'], $_POST['db_acc'],
		$_POST['dim1'], $_POST['dim2'], $wo_cost_types[$_POST['PaymentType']], input_num('costs'), PT_WORKORDER, 
			$_POST['PaymentType']);
	commit_transaction();	

	meta_forward($_SERVER['PHP_SELF'], "AddedID=".$_POST['selected_id']);
}

//-------------------------------------------------------------------------------------

display_wo_details($_POST['selected_id']);

//-------------------------------------------------------------------------------------

start_form();

hidden('selected_id', $_POST['selected_id']);
//hidden('WOReqQuantity', $_POST['WOReqQuantity']);

start_table(TABLESTYLE2);

br();

yesno_list_row(_("Type:"), 'PaymentType', null,	$wo_cost_types[WO_OVERHEAD], $wo_cost_types[WO_LABOUR]);

date_row(_("Date:"), 'date_');

$item_accounts = get_stock_gl_code($wo_details['stock_id']);
$_POST['db_acc'] = $item_accounts['assembly_account'];
$r = get_default_bank_account(get_company_pref('curr_default'));
$_POST['cr_acc'] = $r[0];

amount_row(_("Additional Costs:"), 'costs');
gl_all_accounts_list_row(_("Debit Account"), 'db_acc', null);
gl_all_accounts_list_row(_("Credit Account"), 'cr_acc', null);

end_table(1);
hidden('dim1', $item_accounts["dimension_id"]);
hidden('dim2', $item_accounts["dimension2_id"]);

submit_center('process', _("Process Additional Cost"), true, '', true);

end_form();

end_page();

?>