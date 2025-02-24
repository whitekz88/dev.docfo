<?php
/*
	Question2Answer by Gideon Greenspan and contributors
	http://www.question2answer.org/

	Description: Controller for user account page


	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	More about this license: http://www.question2answer.org/license.php
*/

if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
	header('Location: ../../');
	exit;
}

require_once QA_INCLUDE_DIR . 'db/users.php';
require_once QA_INCLUDE_DIR . 'app/format.php';
require_once QA_INCLUDE_DIR . 'app/users.php';
require_once QA_INCLUDE_DIR . 'db/selects.php';
require_once QA_INCLUDE_DIR . 'util/image.php';


// Check we're not using single-sign on integration, that we're logged in

if (QA_FINAL_EXTERNAL_USERS) {
	header('HTTP/1.1 404 Not Found');
	echo qa_lang_html('main/page_not_found');
	qa_exit();
}

$userid = qa_get_logged_in_userid();

if (!isset($userid))
	qa_redirect('login');


// Get current information on user

list($useraccount, $userprofile, $userpoints, $userfields) = qa_db_select_with_pending(
	qa_db_user_account_selectspec($userid, true),
	qa_db_user_profile_selectspec($userid, true),
	qa_db_user_points_selectspec($userid, true),
	qa_db_userfields_selectspec()
);


//Удаляем поля, если не доктор
if($useraccount['level'] <> 20){	
	foreach($userfields as $key => $value){
		if(strpos($key, 'doctor_') == 0) {
	       unset($userfields[$key]);
	    }
	}
}	


$changehandle = qa_opt('allow_change_usernames') || (!$userpoints['qposts'] && !$userpoints['aposts'] && !$userpoints['cposts']);
$doconfirms = qa_opt('confirm_user_emails') && $useraccount['level'] < QA_USER_LEVEL_EXPERT;
$isconfirmed = ($useraccount['flags'] & QA_USER_FLAGS_EMAIL_CONFIRMED) ? true : false;

$haspasswordold = isset($useraccount['passsalt']) && isset($useraccount['passcheck']);
if (QA_PASSWORD_HASH) {
	$haspassword = isset($useraccount['passhash']);
} else {
	$haspassword = $haspasswordold;
}
$permit_error = qa_user_permit_error();
$isblocked = $permit_error !== false;
$pending_confirmation = $doconfirms && !$isconfirmed;

//Если пользователь ДОКТОР, то получаем категории
if($useraccount['level'] == 20){

	//Полный список всех категорий пользователей
	$stw_users_categories = qa_db_select_with_pending(qa_db_category_stw_all());

	//Список категорий текущего пользователя
	$stw_users_categories_userid = qa_db_select_with_pending(qa_db_category_stw_user($userid));
	$stw_users_categories_userid_title = array();

	//Формируем список категорий текущего пользователя для вывода в select
	foreach($stw_users_categories_userid as $value){
		$value = intval($value);
		$stw_users_categories_userid_title[] = $stw_users_categories[$value]['title'];
	}

	//Формируем список всех категорий пользователей для вывода в select
	$stw_users_categories_select_array = array();
	foreach($stw_users_categories as $value){
	
		$stw_users_categories_select_array[$value['categoryid']] = $value['title'];
	
	}
	
}

// Process profile if saved

$errors = array();

// If the post_max_size is exceeded then the $_POST array is empty so no field processing can be done
if (qa_post_limit_exceeded())
	$errors['avatar'] = qa_lang('main/file_upload_limit_exceeded');
else {
	require_once QA_INCLUDE_DIR . 'app/users-edit.php';

	if (qa_clicked('dosaveprofile') && !$isblocked) {
		$inhandle = $changehandle ? qa_post_text('handle') : $useraccount['handle'];
		$inemail = qa_post_text('email');
		$inmessages = qa_post_text('messages');
		$inwallposts = qa_post_text('wall');
		$inmailings = qa_post_text('mailings');
		$inavatar = qa_post_text('avatar');

		$inprofile = array();
		foreach ($userfields as $userfield)
			$inprofile[$userfield['fieldid']] = qa_post_text('field_' . $userfield['fieldid']);
		
		
		//Если пользователь ДОКТОР, то сохраняем категории
		if($useraccount['level'] == 20){
			
			//Добавляем категории пользователя
			$stw_user_category_post_array = qa_post_array('stw_user_category');
			$stw_user_category_insert_array = array();
			
			if(is_array($stw_user_category_post_array) AND count($stw_user_category_post_array)){
				
				foreach($stw_user_category_post_array as $key => $value){
					$value = intval($value);
					if(!$value OR !isset($stw_users_categories_select_array[$value])){
						unset($stw_user_category_post_array[$key]);
					} else {
						$stw_user_category_insert_array[] = array('userid' => $userid, 'categoryid' => $value);
					} 
			
				}
			
				if(count($stw_user_category_insert_array)){

					qa_db_query_sub(
						'DELETE FROM ^usercategories WHERE userid=#',
						$userid
					);
			
					qa_db_query_sub(
						'INSERT INTO ^usercategories (userid, categoryid) VALUES #',
						$stw_user_category_insert_array
					);
			
				}
				
			}
			
		}
		
		
		if (!qa_check_form_security_code('account', qa_post_text('code')))
			$errors['page'] = qa_lang_html('misc/form_security_again');
		else {
			$errors = qa_handle_email_filter($inhandle, $inemail, $useraccount);

			if (!isset($errors['handle']))
				qa_db_user_set($userid, 'handle', $inhandle);

			if (!isset($errors['email']) && $inemail !== $useraccount['email']) {
				qa_db_user_set($userid, 'email', $inemail);
				qa_db_user_set_flag($userid, QA_USER_FLAGS_EMAIL_CONFIRMED, false);
				$isconfirmed = false;

				if ($doconfirms)
					qa_send_new_confirm($userid);
			}

			if (qa_opt('allow_private_messages'))
				qa_db_user_set_flag($userid, QA_USER_FLAGS_NO_MESSAGES, !$inmessages);

			if (qa_opt('allow_user_walls'))
				qa_db_user_set_flag($userid, QA_USER_FLAGS_NO_WALL_POSTS, !$inwallposts);

			if (qa_opt('mailing_enabled'))
				qa_db_user_set_flag($userid, QA_USER_FLAGS_NO_MAILINGS, !$inmailings);

			qa_db_user_set_flag($userid, QA_USER_FLAGS_SHOW_AVATAR, ($inavatar == 'uploaded'));
			qa_db_user_set_flag($userid, QA_USER_FLAGS_SHOW_GRAVATAR, ($inavatar == 'gravatar'));

			if (is_array(@$_FILES['file'])) {
				$avatarfileerror = $_FILES['file']['error'];

				// Note if $_FILES['file']['error'] === 1 then upload_max_filesize has been exceeded
				if ($avatarfileerror === 1)
					$errors['avatar'] = qa_lang('main/file_upload_limit_exceeded');
				elseif ($avatarfileerror === 0 && $_FILES['file']['size'] > 0) {
					require_once QA_INCLUDE_DIR . 'app/limits.php';

					switch (qa_user_permit_error(null, QA_LIMIT_UPLOADS)) {
						case 'limit':
							$errors['avatar'] = qa_lang('main/upload_limit');
							break;

						default:
							$errors['avatar'] = qa_lang('users/no_permission');
							break;

						case false:
							qa_limits_increment($userid, QA_LIMIT_UPLOADS);
							$toobig = qa_image_file_too_big($_FILES['file']['tmp_name'], qa_opt('avatar_store_size'));

							if ($toobig)
								$errors['avatar'] = qa_lang_sub('main/image_too_big_x_pc', (int)($toobig * 100));
							elseif (!qa_set_user_avatar($userid, file_get_contents($_FILES['file']['tmp_name']), $useraccount['avatarblobid']))
								$errors['avatar'] = qa_lang_sub('main/image_not_read', implode(', ', qa_gd_image_formats()));
							break;
					}
				}  // There shouldn't be any need to catch any other error
			}

			if (count($inprofile)) {
				$filtermodules = qa_load_modules_with('filter', 'filter_profile');
				foreach ($filtermodules as $filtermodule){
					$filtermodule->filter_profile($inprofile, $errors, $useraccount, $userprofile);
					
				}
			}
			
			//Перебираем файлы доп. поля
			if (count($inprofile)) {

				foreach ($userfields as $userfield){
					
					if($userfield['flags'] == 3){ //Если поле - это файл
						
						$filed_image_id = 'field_' . $userfield['fieldid'];
						
						$inprofile[$userfield['fieldid']] = '';
						
						//Получаем blobid старого фото
						if(isset($userprofile[$userfield['title']]))
							$field_image_oldblobid = $userprofile[$userfield['title']];
						else
							$field_image_oldblobid = null;
						
						//Если нужно удалить фото
						if(qa_post_text('field_' . $userfield['fieldid'] . '_delete')){
							
							require_once QA_INCLUDE_DIR . 'app/blobs.php';
							
							if (isset($field_image_oldblobid))
								qa_delete_blob($field_image_oldblobid);
							
						} elseif(is_array(@$_FILES[$filed_image_id])){

							$field_image_fileerror = $_FILES[$filed_image_id]['error'];

							// Note if $_FILES['file']['error'] === 1 then upload_max_filesize has been exceeded
							if ($field_image_fileerror === 1)
								$errors[$filed_image_id] = qa_lang('main/file_upload_limit_exceeded');
							elseif ($field_image_fileerror === 0 && $_FILES[$filed_image_id]['size'] > 0) {
								require_once QA_INCLUDE_DIR . 'app/limits.php';

								switch (qa_user_permit_error(null, QA_LIMIT_UPLOADS)) {
									case 'limit':
										$errors[$filed_image_id] = qa_lang('main/upload_limit');
										break;

									default:
										$errors[$filed_image_id] = qa_lang('users/no_permission');
										break;

									case false:
										qa_limits_increment($userid, QA_LIMIT_UPLOADS);
										$toobig = qa_image_file_too_big($_FILES[$filed_image_id]['tmp_name'], qa_opt('field_image_size'));
										
										if ($toobig)
											$errors[$filed_image_id] = qa_lang_sub('main/image_too_big_x_pc', (int)($toobig * 100));
										else {
											
											$field_image_blobid = qa_field_image_save($userid, file_get_contents($_FILES[$filed_image_id]['tmp_name']), $field_image_oldblobid);
											
											if($field_image_blobid){
												$inprofile[$userfield['fieldid']] = $field_image_blobid;
											} else {
												$errors[$filed_image_id] = qa_lang_sub('main/image_not_read', implode(', ', qa_gd_image_formats()));
											}
								
										}
										break;
								}
								
							}  else {
								
								$inprofile[$userfield['fieldid']] = $userprofile[$userfield['title']];
								
							}
							
						}
					}
				}
			}
			
			foreach ($userfields as $userfield) {
				if (!isset($errors[$userfield['fieldid']]) AND isset($inprofile[$userfield['fieldid']]))
					qa_db_user_profile_set($userid, $userfield['title'], $inprofile[$userfield['fieldid']]);
			}
			
			list($useraccount, $userprofile) = qa_db_select_with_pending(
				qa_db_user_account_selectspec($userid, true), qa_db_user_profile_selectspec($userid, true)
			);

			qa_report_event('u_save', $userid, $useraccount['handle'], qa_cookie_get());

			if (empty($errors))
				qa_redirect('account', array('state' => 'profile-saved'));

			qa_logged_in_user_flush();
		}
	} elseif (qa_clicked('dosaveprofile') && $pending_confirmation) {
		// only allow user to update email if they are not confirmed yet
		$inemail = qa_post_text('email');

		if (!qa_check_form_security_code('account', qa_post_text('code')))
			$errors['page'] = qa_lang_html('misc/form_security_again');

		else {
			$errors = qa_handle_email_filter($useraccount['handle'], $inemail, $useraccount);

			if (!isset($errors['email']) && $inemail !== $useraccount['email']) {
				qa_db_user_set($userid, 'email', $inemail);
				qa_db_user_set_flag($userid, QA_USER_FLAGS_EMAIL_CONFIRMED, false);
				$isconfirmed = false;

				if ($doconfirms)
					qa_send_new_confirm($userid);
			}

			qa_report_event('u_save', $userid, $useraccount['handle'], qa_cookie_get());

			if (empty($errors))
				qa_redirect('account', array('state' => 'profile-saved'));

			qa_logged_in_user_flush();
		}
	}


	// Process change password if clicked

	if (qa_clicked('dochangepassword')) {
		$inoldpassword = (string)qa_post_text('oldpassword');
		$innewpassword1 = (string)qa_post_text('newpassword1');
		$innewpassword2 = (string)qa_post_text('newpassword2');

		if (!qa_check_form_security_code('password', qa_post_text('code')))
			$errors['page'] = qa_lang_html('misc/form_security_again');
		else {
			$errors = array();
			$passcheck = isset($useraccount['passcheck']) ? $useraccount['passcheck'] : '';
			$passsalt = isset($useraccount['passsalt']) ? $useraccount['passsalt'] : '';
			$legacyPassError = !hash_equals(strtolower($passcheck), strtolower(qa_db_calc_passcheck($inoldpassword, $passsalt)));

			if (QA_PASSWORD_HASH) {
				$passError = !password_verify($inoldpassword, isset($useraccount['passhash']) ? $useraccount['passhash'] : '');
				if (($haspasswordold && $legacyPassError) || (!$haspasswordold && $haspassword && $passError)) {
					$errors['oldpassword'] = qa_lang('users/password_wrong');
				}
			} else {
				if ($haspassword && $legacyPassError) {
					$errors['oldpassword'] = qa_lang('users/password_wrong');
				}
			}

			$useraccount['password'] = $inoldpassword;
			$errors = $errors + qa_password_validate($innewpassword1, $useraccount); // array union

			if ($innewpassword1 != $innewpassword2)
				$errors['newpassword2'] = qa_lang('users/password_mismatch');

			if (empty($errors)) {
				qa_db_user_set_password($userid, $innewpassword1);
				qa_db_user_set($userid, 'sessioncode', ''); // stop old 'Remember me' style logins from still working
				qa_set_logged_in_user($userid, $useraccount['handle'], false, $useraccount['sessionsource']); // reinstate this specific session

				qa_report_event('u_password', $userid, $useraccount['handle'], qa_cookie_get());

				qa_redirect('account', array('state' => 'password-changed'));
			}
		}
	}
}

// Prepare content for theme

$qa_content = qa_content_prepare();

$qa_content['title'] = qa_lang_html('profile/my_account_title');
$qa_content['error'] = isset($errors['page']) ? $errors['page'] : null;

$qa_content['form_profile'] = array(
	'tags' => 'enctype="multipart/form-data" method="post" action="' . qa_self_html() . '"',

	'style' => 'wide',

	'fields' => array(
		'duration' => array(
			'type' => 'static',
			'label' => qa_lang_html('users/member_for'),
			'value' => qa_time_to_string(qa_opt('db_time') - $useraccount['created']),
		),

		'type' => array(
			'type' => 'static',
			'label' => qa_lang_html('users/member_type'),
			'value' => qa_html(qa_user_level_string($useraccount['level'])),
			'note' => $isblocked ? qa_lang_html('users/user_blocked') : null,
		),

		'handle' => array(
			'label' => qa_lang_html('users/handle_label'),
			'tags' => 'name="handle"',
			'value' => qa_html(isset($inhandle) ? $inhandle : $useraccount['handle']),
			'error' => qa_html(isset($errors['handle']) ? $errors['handle'] : null),
			'type' => ($changehandle && !$isblocked) ? 'text' : 'static',
		),

		'email' => array(
			'label' => qa_lang_html('users/email_label'),
			'tags' => 'name="email"',
			'value' => qa_html(isset($inemail) ? $inemail : $useraccount['email']),
			'error' => isset($errors['email']) ? qa_html($errors['email']) :
				($pending_confirmation ? qa_insert_login_links(qa_lang_html('users/email_please_confirm')) : null),
			'type' => $pending_confirmation ? 'text' : ($isblocked ? 'static' : 'text'),
		),

		'messages' => array(
			'label' => qa_lang_html('users/private_messages'),
			'tags' => 'name="messages"' . ($pending_confirmation ? ' disabled' : ''),
			'type' => 'checkbox',
			'value' => !($useraccount['flags'] & QA_USER_FLAGS_NO_MESSAGES),
			'note' => qa_lang_html('users/private_messages_explanation'),
		),

		'wall' => array(
			'label' => qa_lang_html('users/wall_posts'),
			'tags' => 'name="wall"' . ($pending_confirmation ? ' disabled' : ''),
			'type' => 'checkbox',
			'value' => !($useraccount['flags'] & QA_USER_FLAGS_NO_WALL_POSTS),
			'note' => qa_lang_html('users/wall_posts_explanation'),
		),

		'mailings' => array(
			'label' => qa_lang_html('users/mass_mailings'),
			'tags' => 'name="mailings"',
			'type' => 'checkbox',
			'value' => !($useraccount['flags'] & QA_USER_FLAGS_NO_MAILINGS),
			'note' => qa_lang_html('users/mass_mailings_explanation'),
		),

		'avatar' => null, // for positioning
	),

	'buttons' => array(
		'save' => array(
			'tags' => 'onclick="qa_show_waiting_after(this, false);"',
			'label' => qa_lang_html('users/save_profile'),
		),
	),

	'hidden' => array(
		'dosaveprofile' => array(
			'tags' => 'name="dosaveprofile"',
			'value' => '1',
		),
		'code' => array(
			'tags' => 'name="code"',
			'value' => qa_get_form_security_code('account'),
		),
	),
);

if (qa_get_state() == 'profile-saved')
	$qa_content['form_profile']['ok'] = qa_lang_html('users/profile_saved');

if (!qa_opt('allow_private_messages'))
	unset($qa_content['form_profile']['fields']['messages']);

if (!qa_opt('allow_user_walls'))
	unset($qa_content['form_profile']['fields']['wall']);

if (!qa_opt('mailing_enabled'))
	unset($qa_content['form_profile']['fields']['mailings']);

if ($isblocked && !$pending_confirmation) {
	unset($qa_content['form_profile']['buttons']['save']);
	$qa_content['error'] = qa_lang_html('users/no_permission');
}

// Avatar upload stuff

if (qa_opt('avatar_allow_gravatar') || qa_opt('avatar_allow_upload')) {
	$avataroptions = array();

	if (qa_opt('avatar_default_show') && strlen(qa_opt('avatar_default_blobid'))) {
		$avataroptions[''] = '<span style="margin:2px 0; display:inline-block;">' .
			qa_get_avatar_blob_html(qa_opt('avatar_default_blobid'), qa_opt('avatar_default_width'), qa_opt('avatar_default_height'), 32) .
			'</span> ' . qa_lang_html('users/avatar_default');
	} else
		$avataroptions[''] = qa_lang_html('users/avatar_none');

	$avatarvalue = $avataroptions[''];

	if (qa_opt('avatar_allow_gravatar') && !$pending_confirmation) {
		$avataroptions['gravatar'] = '<span style="margin:2px 0; display:inline-block;">' .
			qa_get_gravatar_html($useraccount['email'], 32) . ' ' . strtr(qa_lang_html('users/avatar_gravatar'), array(
				'^1' => '<a href="http://www.gravatar.com/" target="_blank">',
				'^2' => '</a>',
			)) . '</span>';

		if ($useraccount['flags'] & QA_USER_FLAGS_SHOW_GRAVATAR)
			$avatarvalue = $avataroptions['gravatar'];
	}

	if (qa_has_gd_image() && qa_opt('avatar_allow_upload') && !$pending_confirmation) {
		$avataroptions['uploaded'] = '<input name="file" type="file">';

		if (isset($useraccount['avatarblobid']))
			$avataroptions['uploaded'] = '<span style="margin:2px 0; display:inline-block;">' .
				qa_get_avatar_blob_html($useraccount['avatarblobid'], $useraccount['avatarwidth'], $useraccount['avatarheight'], 32) .
				'</span>' . $avataroptions['uploaded'];

		if ($useraccount['flags'] & QA_USER_FLAGS_SHOW_AVATAR)
			$avatarvalue = $avataroptions['uploaded'];
	}

	$qa_content['form_profile']['fields']['avatar'] = array(
		'type' => 'select-radio',
		'label' => qa_lang_html('users/avatar_label'),
		'tags' => 'name="avatar"',
		'options' => $avataroptions,
		'value' => $avatarvalue,
		'error' => qa_html(isset($errors['avatar']) ? $errors['avatar'] : null),
	);

} else {
	unset($qa_content['form_profile']['fields']['avatar']);
}


//Если пользователь ДОКТОР, то выводим выбор категории - SELECT 
if($useraccount['level'] == 20){
	
	$qa_content['form_profile']['fields']['stw_user_category'] = array(
		'type' => 'select',
		'label' => qa_lang_html('profile/stw_user_category_edit_label'),
		'tags' => 'name="stw_user_category[]" multiple class="js-select2" style="width: 100%; max-width: 200px;"',
		'options' => $stw_users_categories_select_array,
		'value' => $stw_users_categories_userid_title,
		'error' => qa_html(isset($errors['stw_user_category']) ? $errors['stw_user_category'] : null),
		'id' => 'stw_user_category',
	);
	
}


// Other profile fields

foreach ($userfields as $userfield) {
	$value = @$inprofile[$userfield['fieldid']];
	if (!isset($value))
		$value = @$userprofile[$userfield['title']];

	$label = trim(qa_user_userfield_label($userfield), ':');
	if (strlen($label))
		$label .= ':';
	
	if($userfield['flags'] == '3'){ //Если тип image
		
		if(!$isblocked){
		
			$qa_content['form_profile']['fields'][$userfield['title']] = array(
				'label' => qa_html($label),
				'tags' => 'name="field_' . $userfield['fieldid'] . '"',
				'value' => qa_html($value),
				'error' => qa_html(isset($errors[$userfield['fieldid']]) ? $errors[$userfield['fieldid']] : null),
				'type' => 'file',
			);
		
			$field_image_html_input = '<input name="field_'.$userfield['fieldid'].'" type="file" class="qa-form-wide-file">';
		
		
					
			if(isset($userprofile[$userfield['title']]) AND $userprofile[$userfield['title']]){
				$field_image_html_img = '
					<a href="/?qa=image&qa_blobid='.$userprofile[$userfield['title']].'" data-fancybox="diploms" data-caption="Диплом">
						<img src="/?qa=image&qa_blobid='.$userprofile[$userfield['title']].'&qa_size=200" alt="" />
					</a>
					';
				$field_image_html_delete = '<input name="field_'.$userfield['fieldid'].'_delete" type="checkbox" value="1" class="qa-form-wide-checkbox"><span class="qa-form-wide-note">Удалить?</span>';
			} else {
				$field_image_html_img = '';
				$field_image_html_delete = '';
			}
		
			$qa_content['form_profile']['fields'][$userfield['title']] = array(
				'label' => qa_html($label),
				'html' => '
					<div>' . $field_image_html_img . '</div>
					<div>' . $field_image_html_delete . '</div>
					<div>' . $field_image_html_input . '</div><br /><br />
					',
				'type' => 'custom',
			);
			
		} else {
			
			if(isset($userprofile[$userfield['title']]) AND $userprofile[$userfield['title']]){
				
				$qa_content['form_profile']['fields'][$userfield['title']] = array(
					/*'label' => false,
					'style' => 'wide',
					'columns' => 1,*/
					'label' => qa_html($label),
					'html' => '
						<a href="/?qa=image&qa_blobid='.$userprofile[$userfield['title']].'" data-fancybox="diploms" data-caption="Диплом">
							<img src="/?qa=image&qa_blobid='.$userprofile[$userfield['title']].'&qa_size=200" alt="" />
						</a>
						',
					'type' => 'custom',
				);
				
			}
			
		}
				
	} else {
		
		$qa_content['form_profile']['fields'][$userfield['title']] = array(
			'label' => qa_html($label),
			'tags' => 'name="field_' . $userfield['fieldid'] . '"',
			'value' => qa_html($value),
			'error' => qa_html(isset($errors[$userfield['fieldid']]) ? $errors[$userfield['fieldid']] : null),
			'rows' => ($userfield['flags'] & QA_FIELD_FLAGS_MULTI_LINE) ? 8 : null,
			'type' => $isblocked ? 'static' : 'text',
		);
		
	}
	
}


// Raw information for plugin layers to access

$qa_content['raw']['account'] = $useraccount;
$qa_content['raw']['profile'] = $userprofile;
$qa_content['raw']['points'] = $userpoints;


// Change password form

$qa_content['form_password'] = array(
	'tags' => 'method="post" action="' . qa_self_html() . '"',

	'style' => 'wide',

	'title' => qa_lang_html('users/change_password'),

	'fields' => array(
		'old' => array(
			'label' => qa_lang_html('users/old_password'),
			'tags' => 'name="oldpassword"',
			'value' => qa_html(@$inoldpassword),
			'type' => 'password',
			'error' => qa_html(isset($errors['oldpassword']) ? $errors['oldpassword'] : null),
		),

		'new_1' => array(
			'label' => qa_lang_html('users/new_password_1'),
			'tags' => 'name="newpassword1"',
			'type' => 'password',
			'error' => qa_html(isset($errors['password']) ? $errors['password'] : null),
		),

		'new_2' => array(
			'label' => qa_lang_html('users/new_password_2'),
			'tags' => 'name="newpassword2"',
			'type' => 'password',
			'error' => qa_html(isset($errors['newpassword2']) ? $errors['newpassword2'] : null),
		),
	),

	'buttons' => array(
		'change' => array(
			'label' => qa_lang_html('users/change_password'),
		),
	),

	'hidden' => array(
		'dochangepassword' => array(
			'tags' => 'name="dochangepassword"',
			'value' => '1',
		),
		'code' => array(
			'tags' => 'name="code"',
			'value' => qa_get_form_security_code('password'),
		),
	),
);

if (!$haspassword && !$haspasswordold) {
	$qa_content['form_password']['fields']['old']['type'] = 'static';
	$qa_content['form_password']['fields']['old']['value'] = qa_lang_html('users/password_none');
}

if (qa_get_state() == 'password-changed')
	$qa_content['form_profile']['ok'] = qa_lang_html('users/password_changed');


$qa_content['navigation']['sub'] = qa_user_sub_navigation($useraccount['handle'], 'account', true);


return $qa_content;
