<?php
/************************************************************************/
/* ATutor																*/
/************************************************************************/
/* Copyright (c) 2002-2010                                              */
/* Inclusive Design Institute                                           */
/* http://atutor.ca														*/
/*																		*/
/* This program is free software. You can redistribute it and/or        */
/* modify it under the terms of the GNU General Public License          */
/* as published by the Free Software Foundation.                        */
/************************************************************************/
// $Id$

$page = 'gradebook';

define('AT_INCLUDE_PATH', '../../../include/');
require_once(AT_INCLUDE_PATH.'vitals.inc.php');
authenticate(AT_PRIV_GRADEBOOK);
tool_origin();
require_once("lib/gradebook.inc.php");
//$_pages['mods/_standard/gradebook/gradebook_add_tests.php']['parent'] = $_SERVER['HTTP_REFERRER'];
// Checks if the given test has students taken it more than once, if has, don't add
// print feedback, otherwise, add this test into gradebook.
function add_test($test_id, $title)
{
	global  $msg;	
	$no_error = true;
	
	$studs_take_num = get_studs_take_more_than_once($_SESSION["course_id"], $test_id);
	
	foreach ($studs_take_num as $member_id => $num)
	{
		if ($no_error) $no_error = false;
		$error_msg .= get_display_name($member_id) . ": " . $num . " times<br>";
	}
	
	if (!$no_error)
	{
		$f = array('ADD_TEST_INTO_GRADEBOOK',
						$title, 
						$error_msg);
		$msg->addFeedback($f);
	}

	if ($no_error)  // add into gradebook
	{
		$sql_insert = "INSERT INTO %sgradebook_tests (id, type, grade_scale_id) VALUES (%d, 'ATutor Test', %d)";
		$result_insert = queryDB($sql_insert, array(TABLE_PREFIX, $test_id, $_POST["selected_grade_scale_id"]));
	}
}

function add_assignment($assignment_id)
{
	$_POST["selected_grade_scale_id"] = intval($_POST["selected_grade_scale_id"]);

	$sql_insert = "INSERT INTO %sgradebook_tests (id, type, grade_scale_id) VALUES (%d, 'ATutor Assignment', %d)";
	$result_insert = queryDB($sql_insert, array(TABLE_PREFIX, $assignment_id, $_POST["selected_grade_scale_id"]));
}

if (isset($_POST['cancel'])) 
{
	$msg->addFeedback('CANCELLED');
	$return_url = $_SESSION['tool_origin']['url'];
    tool_origin('off');
	header('Location: '.$return_url);
	//header('Location: gradebook_tests.php');
	exit;
} 
else if (isset($_POST['addATutorTest'])) 
{
	if (preg_match('/^at_(.*)$/', $_POST["id"], $matches) > 0) // add atutor test
	{
		if ($matches[1] == 0) // add all applicable tests
		{

			$sql = "SELECT * FROM %stests t WHERE course_id = %d AND num_takes = 1 AND NOT EXISTS". 
			    " (SELECT 1 FROM %sgradebook_tests g WHERE g.id = t.test_id AND g.type='ATutor Test')";
			$rows_tests	= queryDB($sql, array(TABLE_PREFIX, $_SESSION["course_id"], TABLE_PREFIX));		
			
			foreach($rows_tests as $row){
				add_test($row["test_id"], $row["title"]);
			}
		}
		else // add one atutor test
		{
			
			$sql = "SELECT * FROM %stests t WHERE test_id=%d";
			$row	= queryDB($sql, array(TABLE_PREFIX, $matches[1]), TRUE);

			add_test($matches[1], $row["title"]);
		}
	}
	else if (preg_match('/^aa_(.*)$/', $_POST["id"], $matches) > 0) // add atutor test
	{
		if ($matches[1] == 0) // add all applicable tests
		{

			$sql = "SELECT * FROM %sassignments a WHERE course_id=%d AND NOT EXISTS (SELECT 1 FROM %sgradebook_tests g ".
			        "WHERE g.id = a.assignment_id AND g.type='ATutor Assignment')";
			$rows_assignments	= queryDB($sql, array(TABLE_PREFIX, $_SESSION["course_id"], TABLE_PREFIX));	
			
			foreach($rows_assignments as $row){		
				add_assignment($row["assignment_id"]);
			}
		}
		else // add one test_id
		{
			add_assignment($matches[1]);
		}
	}

	$msg->addFeedback('ACTION_COMPLETED_SUCCESSFULLY');
	//header('Location: gradebook_tests.php');
	//header("Location: ".$_pages['mods/_standard/gradebook/gradebook_add_tests.php']['parent']);
        $return_url = $_SESSION['tool_origin']['url'];
        tool_origin('off');
		header('Location: '.$return_url);
	exit;
} 
else if (isset($_POST['addExternalTest'])) 
{
	$missing_fields = array();

	if ($_POST['title'] == '') {
		$missing_fields[] = _AT('title');
	}

	if ($missing_fields) {
		$missing_fields = implode(', ', $missing_fields);
		$msg->addError(array('EMPTY_FIELDS', $missing_fields));
	}

	if (!$msg->containsErrors()) 
	{
	    $_POST["year_due"] = intval($_POST["year_due"]);
	    $_POST["month_due"] = intval($_POST["month_due"]);
	    $_POST["day_due"] = intval($_POST["day_due"]);
	    $_POST["hour_due"] = intval($_POST["hour_due"]);
	    $_POST["min_due"] = intval($_POST["min_due"]);
	    $_POST["title"] = $addslashes($_POST["title"]);
	    $_POST["selected_grade_scale_id"] = intval($_POST["selected_grade_scale_id"]);
	    
		if ($_POST["has_due_date"] == 'true')
			$date_due = $_POST["year_due"]. '-' .str_pad ($_POST["month_due"], 2, "0", STR_PAD_LEFT). '-' .str_pad ($_POST["day_due"], 2, "0", STR_PAD_LEFT). ' '.str_pad ($_POST["hour_due"], 2, "0", STR_PAD_LEFT). ':' .str_pad ($_POST["min_due"], 2, "0", STR_PAD_LEFT) . ':00';

		$sql_insert = "INSERT INTO %sgradebook_tests (course_id, type, title, due_date, grade_scale_id) VALUES (%d, 'External', '%s', '%s', %d)";
		$result_insert = queryDB($sql_insert, array(TABLE_PREFIX, $_SESSION["course_id"], $_POST["title"], $date_due, $_POST["selected_grade_scale_id"]));

		$msg->addFeedback('ACTION_COMPLETED_SUCCESSFULLY');
		//header('Location: gradebook_tests.php');
        $return_url = $_SESSION['tool_origin']['url'];
        tool_origin('off');
		header('Location: '.$return_url);
		exit;
	}
}

$onload .= ' disable_dates (true, \'_due\');';
require(AT_INCLUDE_PATH.'header.inc.php');

?>
<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
<div class="input-form">
	<fieldset class="group_form"><legend class="group_form"><?php echo _AT('add_atutor_test'); ?></legend>

	<div class="row">
		<p><?php echo _AT('add_atutor_test_info'); ?></p>
	</div>

<?php
// list of atutor tests that can be added into gradebook. 
// These tests can only be taken once and are not in gradebook yet
// note: surveys are excluded by checking if question weights are defined

$sql_at = "SELECT * FROM %stests t WHERE course_id=%d AND num_takes = 1".
				" AND NOT EXISTS (SELECT 1 FROM %sgradebook_tests g WHERE g.id = t.test_id AND g.type='ATutor Test')".
				" AND test_id IN (SELECT test_id FROM %stests_questions_assoc GROUP BY test_id HAVING sum(weight) > 0) ORDER BY title";
$rows_tests = queryDB($sql_at, array(TABLE_PREFIX, $_SESSION["course_id"], TABLE_PREFIX, TABLE_PREFIX));

$sql_aa = "SELECT * FROM %sassignments a WHERE course_id=%d AND NOT EXISTS (SELECT 1".
				" FROM %sgradebook_tests g WHERE g.id = a.assignment_id AND g.type='ATutor Assignment') ORDER BY title";
$rows_assignments = queryDB($sql_aa, array(TABLE_PREFIX, $_SESSION["course_id"], TABLE_PREFIX));


if(count($rows_tests) == 0 && count($rows_assignments) == 0 ){

	 echo _AT('none_found');
}
else
{
	echo '	<div class="row">'."\n\r";
	echo '		<label for="select_tid">'. _AT("title") .'</label><br />'."\n\r";
	echo '		<select name="id" id="select_tid">'."\n\r";
	
	if(count($rows_assignments) > 0 ){
		echo '			<optgroup label="'. _AT('assignments') .'">'."\n\r";
		echo '				<option value="aa_0">'._AT('all_atutor_assignments').'</option>'."\n\r";
	
	    foreach($rows_assignments as $row_aa){
			echo '			<option value="aa_'.$row_aa[assignement_id].'">'.$row_aa[title].'</option>'."\n\r";
		}
		echo '			</optgroup>'."\n\r";
	}

    if(count($rows_tests) > 0){
		echo '			<optgroup label="'. _AT('tests') .'">'."\n\r";
		echo '				<option value="at_0">'._AT('all_atutor_tests').'</option>'."\n\r";
	    
	    foreach($rows_tests as $row_at){
			echo '			<option value="at_'.$row_at[test_id].'">'.$row_at[title].'</option>'."\n\r";
		}
		echo '			</optgroup>'."\n\r";
	}

	echo '		</select>'."\n\r";
	echo '	</div>'."\n\r";

?>
	<div class="row">
		<label for="selected_grade_scale_id"><?php echo _AT('grade_scale'); ?></label><br />
		<?php print_grade_scale_selectbox($_POST["selected_grade_scale_id"]); ?>
	</div>

	<div class="row buttons">
		<input type="submit" name="addATutorTest" value="<?php echo _AT('add'); ?>" />
		<input type="submit" name="cancel" value="<?php echo _AT('cancel'); ?>" />
	</div>
<?php
}
?>
	</fieldset>

</div>
</form>

<form method="post" name="form" action="<?php echo $_SERVER['PHP_SELF']; ?>">
<div class="input-form">
	<fieldset class="group_form"><legend class="group_form"><?php echo _AT('add_external_test'); ?></legend>

	<div class="row">
		<span class="required" title="<?php echo _AT('required_field'); ?>">*</span><label for="title"><?php echo _AT('title'); ?></label><br />
		<input type="text" name="title" id="title" size="30" value="<?php echo AT_print($_POST['title'], 'input.title'); ?>" />
	</div>

	<div class="row">
		<label for="selected_grade_scale_id1"><?php echo _AT('grade_scale'); ?></label><br />
		<?php print_grade_scale_selectbox($_POST["selected_grade_scale_id"], "selected_grade_scale_id1"); ?>
	</div>

	<div class="row">
		<?php  echo _AT('due_date'); ?><br />
		<input type="radio" name="has_due_date" value="false" id="noduedate" checked="checked"
		onfocus="disable_dates (true, '_due');" />
		<label for="noduedate" title="<?php echo _AT('due_date'). ': '. _AT('none');  ?>"><?php echo _AT('none'); ?></label><br />

		<input type="radio" name="has_due_date" value="true" id="hasduedate" onfocus="disable_dates (false, '_due');" />
		<label for="hasduedate"  title="<?php echo _AT('due_date') ?>"><?php  echo _AT('date'); ?></label>

		<?php
		$today = getdate();
		$today_day		= $today['mday'];
		$today_mon	= $today['mon'];
		$today_year	= $today['year'];
		$today_hour	= '12';
		$today_min	= '0';
	
		$name = '_due';
		require(AT_INCLUDE_PATH.'html/release_date.inc.php');
		?>
	</div>

	<div class="row buttons">
		<input type="submit" name="addExternalTest" value="<?php echo _AT('add'); ?>" />
		<input type="submit" name="cancel" value="<?php echo _AT('cancel'); ?>" />
	</div>

	</fieldset>

</div>
</form>

<script language="javascript" type="text/javascript">
function disable_dates (state, name) {
	document.form['day' + name].disabled=state;
	document.form['month' + name].disabled=state;
	document.form['year' + name].disabled=state;
	document.form['hour' + name].disabled=state;
	document.form['min' + name].disabled=state;
}
</script>

<?php require (AT_INCLUDE_PATH.'footer.inc.php');  ?>
