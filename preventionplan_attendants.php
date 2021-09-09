<?php
/* Copyright (C) 2021 EOXIA <dev@eoxia.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *   	\file       preventionplan_attendants.php
 *		\ingroup    digiriskdolibarr
 *		\brief      Page to create/edit/view preventionplan_signature
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) { $i--; $j--; }
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) $res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once __DIR__ . '/class/digiriskresources.class.php';
require_once __DIR__ . '/class/preventionplan.class.php';
require_once __DIR__ . '/lib/digiriskdolibarr_preventionplan.lib.php';
require_once __DIR__ . '/lib/digiriskdolibarr_function.lib.php';

global $db, $langs;

// Load translation files required by the page
$langs->loadLangs(array("digiriskdolibarr@digiriskdolibarr", "other"));

// Get parameters
$id                  = GETPOST('id', 'int');
$action              = GETPOST('action', 'aZ09');
$contextpage         = GETPOST('contextpage', 'aZ') ?GETPOST('contextpage', 'aZ') : 'preventionplansignature'; // To manage different context of search
$backtopage          = GETPOST('backtopage', 'alpha');

// Initialize technical objects
$object            = new PreventionPlan($db);
$signatory         = new PreventionPlanSignature($db);
$digiriskresources = new DigiriskResources($db);
$usertmp           = new User($db);
$contact           = new Contact($db);
$form              = new Form($db);

$object->fetch($id);

$hookmanager->initHooks(array('preventionplansignature', 'globalcard')); // Note that conf->hooks_modules contains array

$permissiontoread   = $user->rights->digiriskdolibarr->preventionplandocument->read;

if (!$permissiontoread) accessforbidden();

/*
/*
 * Actions
 */

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

//if ($action == 'add' && GETPOST('cancel')) {
//	// Creation prevention plan OK
//	$urltogo = str_replace('__ID__', $result, $backurlforlist);
//	$urltogo = preg_replace('/--IDFORBACKTOPAGE--/', $id, $urltogo); // New method to autoselect project after a New on another form object creation
//	header("Location: " . $urltogo);
//	exit;
//}

if (empty($backtopage) || ($cancel && empty($id))) {
	if (empty($backtopage) || ($cancel && strpos($backtopage, '__ID__'))) {
		$backtopage = dol_buildpath('/digiriskdolibarr/preventionplan_attendants.php', 1).'?id='.($object->id > 0 ? $object->id : '__ID__');
	}
}

// Action to add record
if ($action == 'addAttendant') {

	$object->fetch($id);
	$extintervenant_ids  = GETPOST('ext_intervenants');

	if (!empty($extintervenant_ids) && $extintervenant_ids > 0) {
		foreach ($extintervenant_ids as $extintervenant_id) {
			$contact->fetch($extintervenant_id);
			if (!dol_strlen($contact->email)) {
				setEventMessages($langs->trans('ErrorNoEmailForExtIntervenant', $langs->transnoentitiesnoconv('ExtIntervenant')), null, 'errors');
				$error++;
			}
		}
	}
	if (!$error) {
		$result = $signatory->setSignatory($object->id,'socpeople', $extintervenant_ids, 'PP_EXT_SOCIETY_INTERVENANTS', 1);
		if ($result > 0) {
			foreach ($extintervenant_ids as $extintervenant_id) {
				$contact->fetch($extintervenant_id);
				setEventMessages($langs->trans('AddAttendantMessage') . ' ' . $contact->firstname . ' ' . $contact->lastname, array());
				$signatory->call_trigger(strtoupper(get_class($signatory)) . '_ADDATTENDANT', $user);
			}
			// Creation attendant OK
			$urltogo = str_replace('__ID__', $result, $backtopage);
			$urltogo = preg_replace('/--IDFORBACKTOPAGE--/', $id, $urltogo); // New method to autoselect project after a New on another form object creation
			header("Location: " . $urltogo);
			exit;
		}
		else
		{
			// Creation attendant KO
			if (!empty($object->errors)) setEventMessages(null, $object->errors, 'errors');
			else  setEventMessages($object->error, null, 'errors');
		}
	}
}

// Action to add record
if ($action == 'addSignature') {

	$signatoryID = GETPOST('signatoryID');
	$signature = GETPOST('signature');
	$request_body = file_get_contents('php://input');

	$signatory->fetch($signatoryID);
	$signatory->signature = $request_body;
	$signatory->signature_date = dol_now('tzuser');

	if (!$error) {

		$result = $signatory->update($user, false);

		if ($result > 0) {
			// Creation signature OK
			$signatory->setSigned($user, false);
			$urltogo = str_replace('__ID__', $result, $backtopage);
			$urltogo = preg_replace('/--IDFORBACKTOPAGE--/', $id, $urltogo); // New method to autoselect project after a New on another form object creation
			header("Location: " . $urltogo);
			exit;
		}
		else
		{
			// Creation signature KO
			if (!empty($signatory->errors)) setEventMessages(null, $signatory->errors, 'errors');
			else  setEventMessages($signatory->error, null, 'errors');
		}
	}
}

// Action to set status STATUS_ABSENT
if ($action == 'setAbsent') {
	$signatoryID = GETPOST('signatoryID');

	$signatory->fetch($signatoryID);

	if (!$error) {
		$result = $signatory->setAbsent($user, false);
		if ($result > 0) {
			// set absent OK
			setEventMessages($langs->trans('Attendant').' '.$signatory->firstname.' '.$signatory->lastname.' '.$langs->trans('SetAbsentAttendant'),array());
			$urltogo = str_replace('__ID__', $result, $backtopage);
			$urltogo = preg_replace('/--IDFORBACKTOPAGE--/', $id, $urltogo); // New method to autoselect project after a New on another form object creation
			header("Location: " . $urltogo);
			exit;
		}
		else
		{
			// set absent KO
			if (!empty($signatory->errors)) setEventMessages(null, $signatory->errors, 'errors');
			else  setEventMessages($signatory->error, null, 'errors');
		}
	}
}

// Action to send Email
if ($action == 'send') {
	$signatoryID = GETPOST('signatoryID');
	$signatory->fetch($signatoryID);

	if (!$error) {
		$signatory->last_email_sent_date = dol_now('tzuser');
		$result = $signatory->update($user, true);

		if ($result > 0) {
			$langs->load('mails');
			//$trackid = 'PreventionPlanSignature'.$element->id;
			$sendto = $signatory->email;

			if (dol_strlen($sendto) && (!empty($conf->global->DIGIRISKDOLIBARR_SENDMAIL_SIGNATURE))) {
				require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';

				$from = $conf->global->DIGIRISKDOLIBARR_SENDMAIL_SIGNATURE;
				$url = dol_buildpath('/custom/digiriskdolibarr/public/signature/add_signature.php?track_id='.$signatory->signature_url, 3);

				$message = $langs->trans('SignatureEmailMessage') . ' ' . $url;
				$subject = $langs->trans('SignatureEmailSubject') . ' ' . $object->ref;

				// Create form object
				// Send mail (substitutionarray must be done just before this)
				$mailfile = new CMailFile($subject, $sendto, $from, $message, array(), array(), array(), "", "", 0, -1, '', '', '', '', 'mail');

				if ($mailfile->error) {
					setEventMessages($mailfile->error, $mailfile->errors, 'errors');
				} else {
					$result = $mailfile->sendfile();
					if ($result) {
						$signatory->setPendingSignature($user, false);
						setEventMessages($langs->trans('SendEmailAt').' '.$signatory->email,array());
						// This avoid sending mail twice if going out and then back to page
						header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
						exit;
					} else {
						$langs->load("other");
						$mesg = '<div class="error">';
						if ($mailfile->error) {
							$mesg .= $langs->transnoentities('ErrorFailedToSendMail', dol_escape_htmltag($from), dol_escape_htmltag($sendto));
							$mesg .= '<br>'.$mailfile->error;
						} else {
							$mesg .= $langs->transnoentities('ErrorFailedToSendMail', dol_escape_htmltag($from), dol_escape_htmltag($sendto));
						}
						$mesg .= '</div>';
						setEventMessages($mesg, null, 'warnings');
					}
				}
			} else {
				$langs->load("errors");
				setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv("MailTo")), null, 'warnings');
				dol_syslog('Try to send email with no recipient defined', LOG_WARNING);
			}
		} else {
			// Mail sent KO
			if (!empty($signatory->errors)) setEventMessages(null, $signatory->errors, 'errors');
			else  setEventMessages($signatory->error, null, 'errors');
		}
	}
}

////// Action to delete attendant
if ($action == 'deleteAttendant') {

	$signatoryToDeleteID = GETPOST('signatoryID');
	$signatory->fetch($signatoryToDeleteID);

	if (!$error) {
		$result = $signatory->setDeleted($user, false);
		if ($result > 0) {
			setEventMessages($langs->trans('DeleteAttendantMessage').' '.$signatory->firstname.' '.$signatory->lastname,array());
			// Deletion attendant OK
			$urltogo = str_replace('__ID__', $result, $backtopage);
			$urltogo = preg_replace('/--IDFORBACKTOPAGE--/', $id, $urltogo); // New method to autoselect project after a New on another form object creation
			header("Location: " . $urltogo);
			exit;
		}
		else
		{
			// Deletion attendant KO
			if (!empty($object->errors)) setEventMessages(null, $object->errors, 'errors');
			else  setEventMessages($object->error, null, 'errors');
		}
	} else {
		$action = 'create';
	}
}

/*
 *  View
 */

$title    = $langs->trans("PreventionPlanAttendants");
$help_url = '';
$morejs   = array("/digiriskdolibarr/js/signature-pad.min.js", "/digiriskdolibarr/js/digiriskdolibarr.js.php");
$morecss  = array("/digiriskdolibarr/css/digiriskdolibarr.css");

llxHeader('', $title, $help_url, '', '', '', $morejs, $morecss);

if (!empty($object->id)) $res = $object->fetch_optionals();

// Object card
// ------------------------------------------------------------

$head = preventionplanPrepareHead($object);
print dol_get_fiche_head($head, 'preventionplanAttendants', $langs->trans("PreventionPlan"), -1, "digiriskdolibarr@digiriskdolibarr");
dol_strlen($object->label) ? $morehtmlref = ' - ' . $object->label : '';
$morehtmlleft .= '<div class="floatleft inline-block valignmiddle divphotoref">'.digirisk_show_photos('digiriskdolibarr', $conf->digiriskdolibarr->multidir_output[$entity].'/'.$object->element_type, 'small', 5, 0, 0, 0, $width,0, 0, 0, 0, $object->element_type, $object).'</div>';

digirisk_banner_tab($object, 'ref', '', 0, 'ref', 'ref', $morehtmlref, '', 0, $morehtmlleft, $object->getLibStatut(5));

print '<div class="fichecenter"></div>';
print '<div class="underbanner clearboth"></div>';

print dol_get_fiche_end(); ?>

<?php if ( $object->status == 1 ) : ?>
<div class="wpeo-notice notice-warning">
	<div class="notice-content">
		<div class="notice-title"><?php echo $langs->trans('DisclaimerSignatureTitle') ?></div>
		<div class="notice-subtitle"><?php echo $langs->trans("PreventionPlanMustBeValidated") ?></div>
	</div>
</div>
<?php endif; ?>

<?php
// Show direct link to public interface
if (!empty($conf->global->DIGIRISKDOLIBARR_SIGNATURE_ENABLE_PUBLIC_INTERFACE)) {
	print showDirectPublicLinkSignature();
}

// Part to create
if ($action == 'create')
{
	print load_fiche_titre($title_create, '', "digiriskdolibarr32px@digiriskdolibarr");

	print '<form method="POST" action="'.$_SERVER["HTTP_REFERER"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="addAttendant">';
	print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';

	if ($backtopageforcancel) print '<input type="hidden" name="backtopageforcancel" value="'.$backtopageforcancel.'">';

	dol_fiche_head(array(), '');

	print '<table class="border centpercent tableforfieldcreate">'."\n";

	//Intervenants extérieurs
	print '<tr class="oddeven"><td>'.$langs->trans("ExtSocietyIntervenants").'</td><td>';
	print $form->selectcontacts(GETPOST('ext_society', 'int'), '', 'ext_intervenants[]', 1, '', '', 0, 'quatrevingtpercent', false, 0, array(), false, 'multiple', 'ext_intervenants');
	print '</td></tr>';
	print '</table>'."\n";

	dol_fiche_end();

	print '<div class="center">';
	print '<input type="submit" class="button" id ="actionButtonCreate" name="addAttendant" value="'.dol_escape_htmltag($langs->trans("Create")).'">';
	print ' &nbsp; <input type="submit" id ="actionButtonCancelCreate" class="button" name="cancel" value="'.$langs->trans("Cancel").'">';
	print '</div>';

	print '</form>';

}

// Part to show record
if ((empty($action) || ($action != 'create' && $action != 'edit'))) {
	$url = $_SERVER['REQUEST_URI'];
	$zone = "private";

	//Master builder -- Maitre Oeuvre
	$element = $signatory->fetchSignatory('PP_MAITRE_OEUVRE', $id);
	if ($element > 0) {
		$element = array_shift($element);
		$usertmp->fetch($element->element_id);
	}

	print load_fiche_titre($langs->trans("SignatureMaitreOeuvre"), '', '');

	print '<table class="border centpercent tableforfield">';
	print '<tr class="liste_titre">';
	print '<td>' . $langs->trans("Name") . '</td>';
	print '<td>' . $langs->trans("Role") . '</td>';
	print '<td class="center">' . $langs->trans("SignatureLink") . '</td>';
	print '<td class="center">' . $langs->trans("SendMailDate") . '</td>';
	print '<td class="center">' . $langs->trans("SignatureDate") . '</td>';
	print '<td class="center">' . $langs->trans("Status") . '</td>';
	print '<td class="center">' . $langs->trans("ActionsSignature") . '</td>';
	print '<td class="center">' . $langs->trans("Signature") . '</td>';
	print '</tr>';

	print '<tr class="oddeven"><td class="minwidth200">';
	print $usertmp->getNomUrl(1);
	print '</td><td>';
	print $langs->trans("MaitreOeuvre");
	print '</td><td class="center">';
	if ($object->status == 2) {
		$signatureUrl = dol_buildpath('/custom/digiriskdolibarr/public/signature/add_signature.php?track_id='.$element->signature_url, 3);
		print '<a href='.$signatureUrl.' target="_blank"><i class="fas fa-external-link-alt"></i></a>';
	} else {
		print '-';
	}
	print '</td><td class="center">';
	print dol_print_date($element->last_email_sent_date, 'dayhour');
	print '</td><td class="center">';
	print dol_print_date($element->signature_date, 'dayhour');
	print '</td><td class="center">';
	print $element->getLibStatut(5);
	print '</td>';
	if ($object->status == 2) {
		print '<td class="center">';
		require __DIR__ . "/core/tpl/digiriskdolibarr_signature_action_view.tpl.php";
		print '</td>';
		if ($element->signature != $langs->trans("FileGenerated")) {
			print '<td class="center">';
			require __DIR__ . "/core/tpl/digiriskdolibarr_signature_view.tpl.php";
			print '</td>';
		}
	} else {
		print '<td class="center">';
		print '-';
		print '</td>';
		print '<td class="center">';
		print '-';
		print '</td>';
	}

	print '</tr>';
	print '</table>';
	print '<br>';

	//External Society Responsible -- Responsable Société extérieure
	$element = $signatory->fetchSignatory('PP_EXT_SOCIETY_RESPONSIBLE', $id);
	if ($element > 0) {
		$element = array_shift($element);
		$contact->fetch($element->element_id);
	}

	print load_fiche_titre($langs->trans("SignatureResponsibleExtSociety"), '', '');

	print '<table class="border centpercent tableforfield">';
	print '<tr class="liste_titre">';
	print '<td>' . $langs->trans("Name") . '</td>';
	print '<td>' . $langs->trans("Role") . '</td>';
	print '<td class="center">' . $langs->trans("SignatureLink") . '</td>';
	print '<td class="center">' . $langs->trans("SendMailDate") . '</td>';
	print '<td class="center">' . $langs->trans("SignatureDate") . '</td>';
	print '<td class="center">' . $langs->trans("Status") . '</td>';
	print '<td class="center">' . $langs->trans("ActionsSignature") . '</td>';
	print '<td class="center">' . $langs->trans("Signature") . '</td>';

	print '</tr>';

	print '<tr class="oddeven"><td class="minwidth200">';
	print $contact->getNomUrl(1);
	print '</td><td>';
	print $langs->trans("ExtSocietyResponsible");
	print '</td><td class="center">';
	if ($object->status == 2) {
		$signatureUrl = dol_buildpath('/custom/digiriskdolibarr/public/signature/add_signature.php?track_id='.$element->signature_url, 3);
		print '<a href='.$signatureUrl.' target="_blank"><i class="fas fa-external-link-alt"></i></a>';
	} else {
		print '-';
	}
	print '</td><td class="center">';
	print dol_print_date($element->last_email_sent_date, 'dayhour');
	print '</td><td class="center">';
	print dol_print_date($element->signature_date, 'dayhour');
	print '</td><td class="center">';
	print $element->getLibStatut(5);
	print '</td>';
	if ($object->status == 2) {
		print '<td class="center">';
		require __DIR__ . "/core/tpl/digiriskdolibarr_signature_action_view.tpl.php";
		print '</td>';
		if ($element->signature != $langs->trans("FileGenerated")) {
			print '<td class="center">';
			require __DIR__ . "/core/tpl/digiriskdolibarr_signature_view.tpl.php";
			print '</td>';
		}
	} else {
		print '<td class="center">';
		print '-';
		print '</td>';
		print '<td class="center">';
		print '-';
		print '</td>';
	}

	print '</tr>';
	print '</table>';
	print '<br>';

	//External Society Interventants -- Intervenants Société extérieure
	$ext_society_intervenants = $signatory->fetchSignatory('PP_EXT_SOCIETY_INTERVENANTS', $id);

	print load_fiche_titre($langs->trans("SignatureIntervenants"), $newcardbutton, '');

	print '<table class="border centpercent tableforfield">';
	print '<tr class="liste_titre">';

	print '<td>' . $langs->trans("Name") . '</td>';
	print '<td>' . $langs->trans("Role") . '</td>';
	print '<td class="center">' . $langs->trans("SignatureLink") . '</td>';
	print '<td class="center">' . $langs->trans("SendMailDate") . '</td>';
	print '<td class="center">' . $langs->trans("SignatureDate") . '</td>';
	print '<td class="center">' . $langs->trans("Status") . '</td>';
	print '<td class="center">' . $langs->trans("ActionsSignature") . '</td>';
	print '<td class="center">' . $langs->trans("Signature") . '</td>';

	print '</tr>';

	$already_selected_intervenants[$contact->id] = $contact->id;
	$j = 1;
	if (is_array($ext_society_intervenants) && !empty ($ext_society_intervenants) && $ext_society_intervenants > 0) {
		foreach ($ext_society_intervenants as $element) {
			$contact->fetch($element->element_id);
			print '<tr class="oddeven"><td class="minwidth200">';
			print $contact->getNomUrl(1);
			print '</td><td>';
			print $langs->trans("ExtSocietyIntervenant") . ' ' . $j;
			print '</td><td class="center">';
			if ($object->status == 2) {
				$signatureUrl = dol_buildpath('/custom/digiriskdolibarr/public/signature/add_signature.php?track_id='.$element->signature_url, 3);
				print '<a href='.$signatureUrl.' target="_blank"><i class="fas fa-external-link-alt"></i></a>';
			} else {
				print '-';
			}
			print '</td><td class="center">';
			print dol_print_date($element->last_email_sent_date, 'dayhour');
			print '</td><td class="center">';
			print dol_print_date($element->signature_date, 'dayhour');
			print '</td><td class="center">';
			print $element->getLibStatut(5);
			print '</td>';
			if ($object->status == 2) {
				print '<td class="center">';
				require __DIR__ . "/core/tpl/digiriskdolibarr_signature_action_view.tpl.php";
				print '</td>';
				if ($element->signature != $langs->trans("FileGenerated")) {
					print '<td class="center">';
					require __DIR__ . "/core/tpl/digiriskdolibarr_signature_view.tpl.php";
					print '</td>';
				}
			} else {
				print '<td class="center">';
				print '-';
				print '</td>';
				print '<td class="center">';
				print '-';
				print '</td>';
			}
			print '</tr>';
			$already_selected_intervenants[$element->element_id] = $element->element_id;
			$j++;
		}
	}

	if ($object->status == 1) {
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="addAttendant">';
		print '<input type="hidden" name="id" value="'.$id.'">';
		print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';

		if ($backtopageforcancel) print '<input type="hidden" name="backtopageforcancel" value="'.$backtopageforcancel.'">';

		//Intervenants extérieurs
		print '<tr class="oddeven"><td class="maxwidth200">';
		print $form->selectcontacts(GETPOST('ext_society', 'int'), '', 'ext_intervenants[]', 0, $already_selected_intervenants, '', 0, 'width200', false, 0, array(), false, 'multiple', 'ext_intervenants');
		print '</td>';
		print '<td>'.$langs->trans("ExtSocietyIntervenants").'</td>';
		print '<td class="center">';
		print '-';
		print '</td><td class="center">';
		print '-';
		print '</td><td class="center">';
		print '-';
		print '</td><td class="center">';
		print '-';
		print '</td><td class="center">';
		print '<button type="submit" class="wpeo-button button-blue " name="addline" id="addline"><i class="fas fa-plus"></i>  '. $langs->trans('Add').'</button>';
		print '<td class="center">';
		print '-';
		print '</td>';
		print '</tr>';
		print '</table>'."\n";
		print '</form>';
	}
}

// End of page
llxFooter();
$db->close();
