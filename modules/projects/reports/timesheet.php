<?php /* PROJECTS $Id$ */
if (!defined('DP_BASE_DIR')) {
  die('You should not access this file directly.');
}

/**
* Generates an excel timesheet
*/
if (!(getPermission('task_log', 'view'))) {
	redirect('m=public&a=access_denied');
}

header('Content-Type: application/vnd.ms-excel;');
header("Content-type: application/x-msexcel");
header('Content-Disposition: attachment; filename="timesheet.csv"');

$do_report = dPgetParam($_GET, "do_report", 0);
$log_all = dPgetParam($_GET, 'log_all', 0);
$log_pdf = dPgetParam($_GET, 'log_pdf', 0);
$log_ignore = dPgetParam($_GET, 'log_ignore', 0);
$log_userfilter = dPgetParam($_GET, 'log_userfilter', '0');

$log_start_date = dPgetParam($_GET, "log_start_date", 0);
$log_end_date = dPgetParam($_GET, "log_end_date", 0);
$project_id = 0;
$rate = 68;
$total = 0;

// create Date objects from the datetime fields
$start_date = intval($log_start_date) ? new CDate($log_start_date) : new CDate();
$end_date = intval($log_end_date) ? new CDate($log_end_date) : new CDate();

if (!$log_start_date) {
	$start_date->subtractSpan(new Date_Span("9,0,0,0"));
}
$end_date->setTime(23, 59, 59);


	$sql = "select tl.task_log_hours, p.project_name, t.task_name, c.company_fax, date(tl.task_log_date) task_log_date, bc.billingcode_name, bc.billingcode_desc, tl.task_log_name
		from projects as p, companies as c, task_log tl LEFT JOIN billingcode bc ON bc.billingcode_id = tl.task_log_costcode, tasks as t
		where
			tl.task_log_task = t.task_id and
			t.task_project = p.project_id and
			p.project_company = c.company_id and
			c.company_type = 7";

	if ($project_id != 0) {
		$sql .= "\nAND task_project = $project_id";
	}
	if (!$log_all) {
		$sql .= "\n	AND task_log_date >= '".$start_date->format(FMT_DATETIME_MYSQL)."'"
		."\n	AND task_log_date <= '".$end_date->format(FMT_DATETIME_MYSQL)."'";
	}
	if ($log_ignore) {
		$sql .= "\n	AND task_log_hours > 0";
	}
	if ($log_userfilter) {
		$sql .= "\n	AND task_log_creator = $log_userfilter";
	}

	$proj = new CProject;
	$allowedProjects = $proj->getAllowedSQL($AppUI->user_id, 'task_project');
	if (count($allowedProjects)) {
		$sql .= "\n     AND " . implode(" AND ", $allowedProjects);
	}

	$obj = new CTask;
	$allowedTasks = $obj->getAllowedSQL($AppUI->user_id, 'tasks.task_id');
	if (count($allowedTasks)) {
		$sql .= ' AND ' . implode(' AND ', $allowedTasks);
	}
	$allowedChildrenTasks = $obj->getAllowedSQL($AppUI->user_id, 'tasks.task_parent');
	if (count($allowedChildrenTasks)) {
		$sql .= ' AND ' . implode(' AND ', $allowedChildrenTasks);
	}

	$sql .= " ORDER BY task_log_date";

	//echo "<pre>$sql</pre>";

	$logs = db_loadList($sql);
	echo db_error();

	$hours = 0.0;

        foreach ($logs as $log) {
		$date = new CDate($log['task_log_date']);
		$hours += $log['task_log_hours'];

		$amount = $rate * $log['task_log_hours'];
    $billingcode_name = $log['billingcode_name'];
    if (strpos($billingcode_name, ',') === false) {
      $billingcode_name .= ',';
    }
		echo "$log[task_log_date],$log[company_fax],$billingcode_name,$log[billingcode_desc],$log[project_name] $log[task_name],$log[task_log_hours],$log[task_log_name]\n";

	}

?>
