<?php
/**
 * Change Email
 *
 * @package   MyAAC
 * @author    Gesior <jerzyskalski@wp.pl>
 * @author    Slawkens <slawkens@gmail.com>
 * @author    OpenTibiaBR
 * @copyright 2023 MyAAC
 * @link      https://github.com/opentibiabr/myaac
 */
defined('MYAAC') or die('Direct access not allowed!');

$email_new_time = $account_logged->getCustomField("email_new_time");

if($email_new_time > 10) {
	$email_new = $account_logged->getCustomField("email_new");
}

if($email_new_time < 10) {
	if(isset($_POST['changeemailsave']) && $_POST['changeemailsave'] == 1) {
		$email_new = mb_strtolower(trim($_POST['new_email'] ?? ''));
		$post_password = $_POST['password'] ?? '';

		if(!Validator::email($email_new)) {
			$errors[] = Validator::getLastError();
		}

		if(empty($post_password)) {
			$errors[] = 'Please enter password to your account.';
		}
		else {
			$post_password = encrypt(($config_salt_enabled ? $account_logged->getCustomField('salt') : '') . $post_password);
			if($post_password != $account_logged->getPassword()) {
				$errors[] = 'Wrong password to account.';
			}
		}

		if(empty($errors) && $config['account_mail_unique']) {
			$account_id = $account_logged->getId();
			$email_exists = $db->query('SELECT `id` FROM `accounts` WHERE `email` = ' . $db->quote($email_new) . ' AND `id` != ' . (int)$account_id . ' LIMIT 1')->fetch();
			if($email_exists) {
				$errors[] = 'This email address is already used by another account.';
			}
		}

		if(empty($errors)) {
			if((int)$config['account_mail_change'] <= 0) {
				$account_logged->setCustomField("email_new", "");
				$account_logged->setCustomField("email_new_time", 0);
				$account_logged->setEMail($email_new);
				$account_logged->save();
				$account_logged->logAction('Account email changed to <b>' . $email_new . '</b>');

				$twig->display('success.html.twig', array(
					'title' => 'Email Address Changed',
					'description' => 'Your email address has been changed to <b>' . $account_logged->getEMail() . '</b>.'
				));
			}
			else {
				$email_new_time = time() + $config['account_mail_change'] * 24 * 3600;
				$account_logged->setCustomField("email_new", $email_new);
				$account_logged->setCustomField("email_new_time", $email_new_time);
				$twig->display('success.html.twig', array(
					'title' => 'New Email Address Requested',
					'description' => 'You have requested to change your email address to <b>' . $email_new . '</b>. The actual change will take place after <b>' . date("j F Y, G:i:s", $email_new_time) . '</b>, during which you can cancel the request at any time.'
				));
			}
		}
		else
		{
			//show errors
			$twig->display('error_box.html.twig', array('errors' => $errors));

			//show form
			$twig->display('account.change_mail.html.twig', array(
				'new_email' => isset($_POST['new_email']) ? $_POST['new_email'] : null
			));
		}
	}
	else
	{
		$twig->display('account.change_mail.html.twig', array(
			'new_email' => isset($_POST['new_email']) ? $_POST['new_email'] : null
		));
	}

}
else
{
	if($email_new_time < time() || (int)$config['account_mail_change'] <= 0) {
		if (isset($_POST['changeemailsave']) && $_POST['changeemailsave'] == 1) {
			if($config['account_mail_unique']) {
				$account_id = $account_logged->getId();
				$email_exists = $db->query('SELECT `id` FROM `accounts` WHERE `email` = ' . $db->quote($email_new) . ' AND `id` != ' . (int)$account_id . ' LIMIT 1')->fetch();
				if($email_exists) {
					$errors[] = 'This email address is already used by another account.';
				}
			}

			if(empty($errors)) {
				$account_logged->setCustomField("email_new", "");
				$account_logged->setCustomField("email_new_time", 0);
				$account_logged->setEMail($email_new);
				$account_logged->save();
				$account_logged->logAction('Account email changed to <b>' . $email_new . '</b>');

				$twig->display('success.html.twig', array(
					'title' => 'Email Address Change Accepted',
					'description' => 'You have accepted <b>' . $account_logged->getEMail() . '</b> as your new email adress.'
				));
			}
			else {
				$twig->display('error_box.html.twig', array('errors' => $errors));
			}
		}
		else
		{
			$custom_buttons = '
<table width="100%">
	<tr>
		<td width="30">&nbsp;</td>
		<td align=left>
			<form action="' . getLink('account/email') . '" method="post"><input type="hidden" name="changeemailsave" value=1 >
				<INPUT TYPE=image NAME="I Agree" SRC="' . $template_path . '/images/global/buttons/sbutton_iagree.gif" BORDER=0 WIDTH=120 HEIGHT=17>
			</form>
		</td>
		<td align=left>
			<form action="' . getLink('account/email') . '" method="post">
				<input type="hidden" name="emailchangecancel" value=1 >
				' . $twig->render('buttons.cancel.html.twig') . '
			</form>
		</td>
		<td align=right>
			<form action="?subtopic=accountmanagement" method="post" >
				' . $twig->render('buttons.back.html.twig') . '
			</form>
		</td>
		<td width="30">&nbsp;</td>
	</tr>
</table>';
			$twig->display('success.html.twig', array(
				'title' => 'Email Address Change Accepted',
				'description' => 'Do you accept <b>'.$email_new.'</b> as your new email adress?',
				'custom_buttons' => $custom_buttons
			));
		}
	}
	else if(!isset($_POST['emailchangecancel']) || $_POST['emailchangecancel'] != 1)
	{
		$custom_buttons = '
<table style="width:100%;" >
	<tr align="center">
		<td>
			<table border="0" cellspacing="0" cellpadding="0" >
				<form action="' .getLink('account/email') . '" method="post" >
					<tr>
						<td style="border:0px;" >
							<input type="hidden" name="emailchangecancel" value="1" >
							' . $twig->render('buttons.cancel.html.twig') . '
						</td>
					</tr>
				</form>
			</table>
		</td>
		<td>
			<table border="0" cellspacing="0" cellpadding="0" >
				<form action="' . getLink('account/manage') . '" method="post" >
					<tr>
						<td style="border:0px;" >
							' . $twig->render('buttons.back.html.twig') . '
						</td>
					</tr>
				</form>
			</table>
		</td>
	</tr>
</table>';
		$twig->display('success.html.twig', array(
			'title' => 'Change of Email Address',
			'description' => 'A request has been submitted to change the email address of this account to <b>'.$email_new.'</b>.<br/>The actual change will take place on <b>'.date("j F Y, G:i:s", $email_new_time).'</b>.<br>If you do not want to change your email address, please click on "Cancel".',
			'custom_buttons' => $custom_buttons
		));
	}
}
if(isset($_POST['emailchangecancel']) && $_POST['emailchangecancel'] == 1) {
	$account_logged->setCustomField("email_new", "");
	$account_logged->setCustomField("email_new_time", 0);

	$custom_buttons = '<div style="text-align:center"><table border="0" cellspacing="0" cellpadding="0" ><form action="?subtopic=accountmanagement" method="post" ><tr><td style="border:0px;" >' . $twig->render('buttons.back.html.twig') . '</td></tr></form></table></div>';

	$twig->display('success.html.twig', array(
		'title' => 'Email Address Change Cancelled',
		'description' => 'Your request to change the email address of your account has been cancelled. The email address will not be changed.',
		'custom_buttons' => $custom_buttons
	));
}
?>
