<?php
/**
 * Edit Reputation Comment
 * Copyright 2011 Starpaul20
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Tell MyBB when to run the hooks
$plugins->add_hook("reputation_start", "editrepcomment_run");
$plugins->add_hook("reputation_end", "editrepcomment_link");

// The information that shows up on the plugin manager
function editrepcomment_info()
{
	return array(
		"name"				=> "Edit Reputation Comment",
		"description"		=> "Allows Administrators and Super Moderators to edit reputation comments.",
		"website"			=> "http://galaxiesrealm.com/index.php",
		"author"			=> "Starpaul20",
		"authorsite"		=> "http://galaxiesrealm.com/index.php",
		"version"			=> "1.0",
		"guid"				=> "bd4319ca3aea2517f634a1423c730dbe",
		"compatibility"		=> "16*"
	);
}

// This function runs when the plugin is activated.
function editrepcomment_activate()
{
	global $db;
	$insert_array = array(
		'title'		=> 'reputation_edit',
		'template'	=> $db->escape_string('<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->reputation}</title>
{$headerinclude}
</head>
<body>
<br />
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="trow1" style="padding: 20px">
<strong>{$lang->edit_comment}</strong><br />
<form action="reputation.php" method="post">
<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
<input type="hidden" name="action" value="do_edit" />
<input type="hidden" name="uid" value="{$mybb->input[\'uid\']}" />
<input type="hidden" name="rid" value="{$mybb->input[\'rid\']}" />
<br /><br />
<span class="smalltext">{$lang->update_this_comment}</span>
<br />
<input type="text" class="textbox" name="comments" size="35" maxlength="350" value="{$editrep[\'comments\']}" style="width: 95%" />
<br /><br />
<div style="text-align: center;">
<input type="submit" class="button" value="{$lang->update_comment}" />
</div>
</form>
</td>
</tr>
</table>
</body>
</html>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	$insert_array = array(
		'title'		=> 'reputation_edit_error',
		'template'	=> $db->escape_string('<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->reputation}</title>
{$headerinclude}
</head>
<body>
<br />
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="trow1" style="padding: 20px">
<strong>{$lang->error}</strong><br /><br />
<blockquote>{$message}</blockquote>
<br /><br />
<div style="text-align: center;">
<script type="text/javascript">
<!--
var showBack = {$show_back};
if(showBack == 1)
{
document.write(\'[<a href="javascript:history.go(-1);">{$lang->go_back}</a>]\');
}
document.write(\'[<a href="javascript:window.close();">{$lang->close_window}</a>]\');
// -->
</script>
</div>
</td>
</tr>
</table>
</body>
</html>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("reputation_vote", "#".preg_quote('{$delete_link}')."#i", '<!-- editlink{$reputation_vote[\'rid\']} --> {$delete_link}');
}

// This function runs when the plugin is deactivated.
function editrepcomment_deactivate()
{
	global $db;
	$db->delete_query("templates", "title IN('reputation_edit','reputation_edit_error')");

	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("reputation_vote", "#".preg_quote('<!-- editlink{$reputation_vote[\'rid\']} --> ')."#i", '', 0);
}

// Edit Reputation comment
function editrepcomment_run()
{
	global $db, $mybb, $lang, $templates, $theme, $show_back, $headerinclude, $message, $editrep;
	$lang->load("editrep");

	// Saving the new comment
	if($mybb->input['action'] == "do_edit" && $mybb->request_method == "post")
	{
		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		$query = $db->simple_select("reputation", "rid, pid, comments", "rid='".intval($mybb->input['rid'])."'");
		$editrep = $db->fetch_array($query);

		// The length of the comment is too short (only if rep is not from a post)
		if($editrep['pid'] == 0)
		{
			$mybb->input['comments'] = trim($mybb->input['comments']); // Trim whitespace to check for length
			if(my_strlen($mybb->input['comments']) < 10)
			{
				$show_back = 1;
				$message = $lang->edit_no_comment;
				eval("\$error = \"".$templates->get("reputation_edit_error")."\";");
				output_page($error);
				exit;
			}
		}

		// The length of the comment is too long
		if(my_strlen($mybb->input['comments']) > $mybb->settings['maxreplength'])
		{
			$show_back = 1;
			$message = $lang->sprintf($lang->edit_toolong, $mybb->settings['maxreplength']);
			eval("\$error = \"".$templates->get("reputation_edit_error")."\";");
			output_page($error);
			exit;
		}

		// Build array of reputation data.
		$updatedcomment = array(
			"comments" => $db->escape_string($mybb->input['comments'])
		);

		$db->update_query("reputation", $updatedcomment, "rid='".intval($mybb->input['rid'])."'");

		$lang->vote_added = $lang->comment_updated;
		$lang->vote_added_message = $lang->comment_updated_message;

		eval("\$reputation = \"".$templates->get("reputation_added")."\";");
		output_page($reputation);
	}

	// Edit a current reputation comment
	if($mybb->input['action'] == "edit")
	{
		$query = $db->simple_select("reputation", "rid, comments", "rid='".intval($mybb->input['rid'])."'");
		$editrep = $db->fetch_array($query);

		if($mybb->usergroup['issupermod'] == 0 && $mybb->usergroup['cancp'] == 0)
		{
			$message = $lang->edit_nopermission;
			eval("\$error = \"".$templates->get("reputation_edit_error")."\";");
			output_page($error);
			exit;
		}

		if(!$editrep['rid'])
		{
			$message = $lang->edit_norep;
			eval("\$error = \"".$templates->get("reputation_edit_error")."\";");
			output_page($error);
			exit;
		}

	eval("\$reputation_edit = \"".$templates->get("reputation_edit")."\";");
	output_page($reputation_edit);
	}
}

// Edit Reputation comment link
function editrepcomment_link()
{
	global $mybb, $db, $lang, $reputation_votes;
	$lang->load("editrep");

	// Does the current user have permission to edit this reputation? Show edit link
	if($mybb->usergroup['cancp'] == 1 || $mybb->usergroup['issupermod'] == 1)
	{
		preg_match_all("#editlink[0-9]{1,10}#", $reputation_votes, $matches);
		foreach($matches[0] as $match)
		{
			$rid = str_replace("editlink", "", $match);

			$editlink = "[<a href=\"javascript:MyBB.popupWindow('reputation.php?action=edit&amp;uid={$mybb->user['uid']}&amp;rid={$rid['rid']}', 'editrep', '400', '300') \">{$lang->edit_vote}</a>]";
			$reputation_votes = str_replace("<!-- editlink{$rid} -->", $editlink, $reputation_votes);
		}
	}
}

?>