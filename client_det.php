<?php

/*
	This file is part of the Legal Case Management System (LCM).
	(C) 2004-2005 Free Software Foundation, Inc.

	This program is free software; you can redistribute it and/or modify it
	under the terms of the GNU General Public License as published by the
	Free Software Foundation; either version 2 of the License, or (at your
	option) any later version.

	This program is distributed in the hope that it will be useful, but
	WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
	or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License
	for more details.

	You should have received a copy of the GNU General Public License along
	with this program; if not, write to the Free Software Foundation, Inc.,
	59 Temple Place, Suite 330, Boston, MA  02111-1307, USA

	$Id: client_det.php,v 1.38 2005/03/28 07:48:08 mlutfy Exp $
*/

include('inc/inc.php');
include('inc/inc_acc.php');
include_lcm('inc_contacts');

$client = intval($_REQUEST['client']);

if ($client > 0) {
	$q="SELECT *
		FROM lcm_client
		WHERE lcm_client.id_client = $client";

	$result = lcm_query($q);

	if ($row = lcm_fetch_array($result)) {
		lcm_page_start(_T('title_client_view') . ' ' . get_person_name($row));

		/* Saved for future use
			// Check for access rights
			if (!($row['public'] || allowed($client,'r'))) {
				die("You don't have permission to view this client details!");
			}
			$edit = allowed($client,'w');
		*/

		$edit = true;

		if ($row['gender'] == 'male' || $row['gender'] == 'female')
			$gender = _T('person_input_gender_' . $row['gender']);
		else
			$gender = _T('info_not_available');

		// Show tabs
		$groups = array(
					'general' => _T('generic_tab_general'),
					'organisations' => _T('generic_tab_org'),
					'cases' => _T('generic_tab_cases'),
					'attachments' => _T('generic_tab_documents'));

		$tab = ( isset($_GET['tab']) ? $_GET['tab'] : 'general' );
		show_tabs($groups,$tab,$_SERVER['REQUEST_URI']);

		if (isset($_SESSION['client']['attach_case'])) {
			$q = "SELECT title
					FROM lcm_case
					WHERE id_case = " . intval($_SESSION['client']['attach_case']);
			$result = lcm_query($q);

			while ($row1 = lcm_fetch_array($result)) {
				echo '<p>' . 'The client was created and attached to the case: ' 
					. '<a href="case_det.php?case=' . $_SESSION['client']['attach_case'] . '">' 
					. $row1['title'] 
					. "</a></p>\n";
			}
		}

		switch ($tab) {
			case 'general':
				//
				// Show client general information
				//
				echo '<fieldset class="info_box">';
				echo '<div class="prefs_column_menu_head">' . _T('generic_subtitle_general') . "</div>\n";
		
				echo '<p class="normal_text">';
				echo _T('client_input_id') . ' ' . $row['id_client'] . "<br/>\n";
				echo _T('person_input_gender') . ' ' . $gender . "<br/>\n";
		
				if (read_meta('client_citizen_number') == 'yes')
					echo _T('person_input_citizen_number') . ' ' . $row['citizen_number'] . "<br/>\n";
		
				// [ML] this has been moved in contacts
				// echo _T('person_input_address') . ' ' . $row['address'] . "<br/>\n";

				if (read_meta('client_civil_status') == 'yes')
					echo _Ti('person_input_civil_status') . $row['civil_status'] . "<br/>\n";

				if (read_meta('client_income') == 'yes')
					echo _Ti('person_input_income') . $row['income'] . "<br/>\n";

				echo _Ti('case_input_date_creation') . format_date($row['date_creation']) . "<br/>\n";
				// [ML] echo 'Last update date: ' . $row['date_update'] . "<br/>\n";
				echo "</p>\n";
		
				// Show client contacts (if any)
				show_all_contacts('client', $row['id_client']);

				if ($edit)
					echo '<p><a href="edit_client.php?client=' . $row['id_client'] . '" class="edit_lnk">Edit client information</a>' . "</p><br />\n"; // TRAD

				echo "</fieldset>\n";
				break;
			case 'organisations':
				//
				// Show client associated organisations
				//
				echo '<fieldset class="info_box">';
				echo '<div class="prefs_column_menu_head">' . _T('client_subtitle_associated_org') . "</div>\n";
		
				echo '
				<br /><table border="0" class="tbl_usr_dtl" width="100%">
				<tr>
					<th class="heading">&nbsp;</th>
					<th class="heading">' . _Th('org_input_name') . '</th>
				</tr>';
		
				//
				// Show organisation(s)
				//
				$q = "SELECT lcm_org.id_org,name
						FROM lcm_client_org,lcm_org
						WHERE id_client=$client
							AND lcm_client_org.id_org=lcm_org.id_org";
		
				$result = lcm_query($q);
		
				while ($row1 = lcm_fetch_array($result)) {
					echo "<tr>\n";
					echo '<td width="25" align="center"><img src="images/jimmac/stock_people.png" alt="" height="16" width="16" /></td>' . "\n";
					echo '<td><a style="display: block;" href="org_det.php?org=' . $row1['id_org'] .  '" class="content_link">' . $row1['name'] . "</a></td>\n";

					/* [ML] Not appropriate here
					echo "<td>";
					if ($edit)
						echo '<a href="edit_org.php?org=' . $row1['id_org'] . '" class="content_link">Edit</a>';
					echo "</td>";
					*/

					echo "</tr>\n";
				}
				
				echo "</table>";
		
				if ($edit)
					echo "<br /><a href=\"sel_org_cli.php?client=$client\" class=\"add_lnk\">Add organisation(s)</a><br />";
		
				echo "<br /></fieldset>";
				
				break;

			case 'cases':
				//
				// Show recent cases
				// [AG] Since this info is on separate tab, they could be more, i.e. $prefs['page_rows']
				//

				$q = "SELECT clo.id_case, c.title, c.date_creation, c.id_court_archive, c.status
						FROM lcm_case_client_org as clo, lcm_case as c
						WHERE id_client = " . $client . "
						AND clo.id_case = c.id_case ";

				// Sort cases by creation date
				$case_order = 'DESC';
				if (isset($_REQUEST['case_order']))
					if ($_REQUEST['case_order'] == 'ASC' || $_REQUEST['case_order'] == 'DESC')
						$case_order = $_REQUEST['case_order'];
				
				$q .= " ORDER BY c.date_creation " . $case_order;
		
				$result = lcm_query($q);
				$number_of_rows = lcm_num_rows($result);
				$list_pos = 0;
				
				if (isset($_REQUEST['list_pos']))
					$list_pos = $_REQUEST['list_pos'];
				
				if ($list_pos >= $number_of_rows)
					$list_pos = 0;
				
				// Position to the page info start
				if ($list_pos > 0)
					if (!lcm_data_seek($result,$list_pos))
						lcm_panic("Error seeking position $list_pos in the result");

				if (lcm_num_rows($result)) {
					echo '<fieldset class="info_box">' . "\n";
					echo '<div class="prefs_column_menu_head">' . _T('client_subtitle_cases') . "</div>\n";
					show_listcase_start();
		
					for ($cpt = 0; $row1 = lcm_fetch_array($result); $cpt++) {
						show_listcase_item($row1, $cpt);
					}

					show_listcase_end($list_pos, $number_of_rows);
					echo "</fieldset>\n";
				}

				break;
			//
			// Client attachments
			//
			case 'attachments' :
				echo '<fieldset class="info_box">';
				echo '<div class="prefs_column_menu_head">' . _T('client_subtitle_attachments') . '</div>';
				echo "<p class=\"normal_text\">\n";

				// List of attached files
				show_attachments_list('client', $client);

				// Attach new file form
				if ($edit)
					show_attachments_upload('client', $client);

				echo "</p>\n";
				echo "</fieldset>\n";
				break;
		}

		// Show this in all tabs
		echo '<p>';
		echo '<a href="edit_case.php?case=0&amp;attach_client=' . $row['id_client'] . '" class="create_new_lnk">';
		echo "Open new case involving this client"; // TRAD
		echo "</a>";
		echo "</p>\n";
				
	} else die("There's no such client!");
} else die("Which client?");

lcm_page_end();
?>
