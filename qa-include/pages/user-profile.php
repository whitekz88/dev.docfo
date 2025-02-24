<?php
/*
	Question2Answer by Gideon Greenspan and contributors
	http://www.question2answer.org/

	Description: Controller for user profile page, including wall


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

require_once QA_INCLUDE_DIR . 'db/selects.php';
require_once QA_INCLUDE_DIR . 'app/format.php';
require_once QA_INCLUDE_DIR . 'app/limits.php';
require_once QA_INCLUDE_DIR . 'app/updates.php';

require_once QA_INCLUDE_DIR . 'util/image.php';


// $handle, $userhtml are already set by /qa-include/page/user.php - also $userid if using external user integration


// Redirect to 'My Account' page if button clicked

if (qa_clicked('doaccount'))
	qa_redirect('account');


// Find the user profile and questions and answers for this handle


$loginuserid = qa_get_logged_in_userid();

if (!QA_FINAL_EXTERNAL_USERS) {
	$useraccount = qa_db_single_select(qa_db_user_account_selectspec($handle, false));
	if ($useraccount === null) {
		return include QA_INCLUDE_DIR . 'qa-page-not-found.php';
	}

	$userid = $useraccount['userid'];
}

list($userprofile, $userfields, $usermessages, $userpoints, $userlevels, $navcategories, $userrank) =
	qa_db_select_with_pending(
		QA_FINAL_EXTERNAL_USERS ? null : qa_db_user_profile_selectspec($userid, true),
		QA_FINAL_EXTERNAL_USERS ? null : qa_db_userfields_selectspec(),
		QA_FINAL_EXTERNAL_USERS ? null : qa_db_recent_messages_selectspec(null, null, $userid, true, qa_opt_if_loaded('page_size_wall')),
		qa_db_user_points_selectspec($userid, true),
		qa_db_user_levels_selectspec($userid, true, true),
		qa_db_category_nav_selectspec(null, true),
		qa_db_user_rank_selectspec($userid, true)
	);
	


//Удаляем поля, если не доктор
if($useraccount['level'] <> 20){	
	foreach($userfields as $key => $value){
		if(strpos($key, 'doctor_') == 0) {
	       unset($userfields[$key]);
	    }
	}
}	

if (!QA_FINAL_EXTERNAL_USERS && $handle !== qa_get_logged_in_handle()) {
	foreach ($userfields as $index => $userfield) {
		if (isset($userfield['permit']) && qa_permit_value_error($userfield['permit'], $loginuserid, qa_get_logged_in_level(), qa_get_logged_in_flags()))
			unset($userfields[$index]); // don't pay attention to user fields we're not allowed to view
	}
}


// Check the user exists and work out what can and can't be set (if not using single sign-on)

$errors = array();

$loginlevel = qa_get_logged_in_level();

if (!QA_FINAL_EXTERNAL_USERS) { // if we're using integrated user management, we can know and show more
	require_once QA_INCLUDE_DIR . 'app/messages.php';

	if (!is_array($userpoints))
		return include QA_INCLUDE_DIR . 'qa-page-not-found.php';

	$fieldseditable = false;
	$maxlevelassign = null;

	$maxuserlevel = $useraccount['level'];
	foreach ($userlevels as $userlevel)
		$maxuserlevel = max($maxuserlevel, $userlevel['level']);

	if (isset($loginuserid) && $loginuserid != $userid &&
		($loginlevel >= QA_USER_LEVEL_SUPER || $loginlevel > $maxuserlevel) &&
		!qa_user_permit_error()
	) { // can't change self - or someone on your level (or higher, obviously) unless you're a super admin
		if ($loginlevel >= QA_USER_LEVEL_SUPER)
			$maxlevelassign = QA_USER_LEVEL_SUPER;
		elseif ($loginlevel >= QA_USER_LEVEL_ADMIN)
			$maxlevelassign = QA_USER_LEVEL_MODERATOR;
		elseif ($loginlevel >= QA_USER_LEVEL_MODERATOR)
			$maxlevelassign = QA_USER_LEVEL_EXPERT;

		if ($loginlevel >= QA_USER_LEVEL_ADMIN)
			$fieldseditable = true;

		if (isset($maxlevelassign) && ($useraccount['flags'] & QA_USER_FLAGS_USER_BLOCKED))
			$maxlevelassign = min($maxlevelassign, QA_USER_LEVEL_EDITOR); // if blocked, can't promote too high
	}

	$approvebutton = isset($maxlevelassign)
		&& $useraccount['level'] < QA_USER_LEVEL_APPROVED
		&& $maxlevelassign >= QA_USER_LEVEL_APPROVED
		&& !($useraccount['flags'] & QA_USER_FLAGS_USER_BLOCKED)
		&& qa_opt('moderate_users');
	$usereditbutton = $fieldseditable || isset($maxlevelassign);
	$userediting = $usereditbutton && (qa_get_state() == 'edit');

	$wallposterrorhtml = qa_wall_error_html($loginuserid, $useraccount['userid'], $useraccount['flags']);

	// This code is similar but not identical to that in to qq-page-user-wall.php

	$usermessages = array_slice($usermessages, 0, qa_opt('page_size_wall'));
	$usermessages = qa_wall_posts_add_rules($usermessages, 0);

	foreach ($usermessages as $message) {
		if ($message['deleteable'] && qa_clicked('m' . $message['messageid'] . '_dodelete')) {
			if (!qa_check_form_security_code('wall-' . $useraccount['handle'], qa_post_text('code')))
				$errors['page'] = qa_lang_html('misc/form_security_again');
			else {
				qa_wall_delete_post($loginuserid, qa_get_logged_in_handle(), qa_cookie_get(), $message);
				qa_redirect(qa_request(), null, null, null, 'wall');
			}
		}
	}
}


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


// Process edit or save button for user, and other actions

if (!QA_FINAL_EXTERNAL_USERS) {
	$reloaduser = false;

	if ($usereditbutton) {
		if (qa_clicked('docancel')) {
			qa_redirect(qa_request());
		} elseif (qa_clicked('doedit')) {
			qa_redirect(qa_request(), array('state' => 'edit'));
		} elseif (qa_clicked('dosave')) {
			require_once QA_INCLUDE_DIR . 'app/users-edit.php';
			require_once QA_INCLUDE_DIR . 'db/users.php';

			
			$inemail = qa_post_text('email');

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
			
			
			if (!qa_check_form_security_code('user-edit-' . $handle, qa_post_text('code'))) {
				$errors['page'] = qa_lang_html('misc/form_security_again');
				$userediting = true;
			} else {
				if (qa_post_text('removeavatar')) {
					qa_db_user_set_flag($userid, QA_USER_FLAGS_SHOW_AVATAR, false);
					qa_db_user_set_flag($userid, QA_USER_FLAGS_SHOW_GRAVATAR, false);

					if (isset($useraccount['avatarblobid'])) {
						require_once QA_INCLUDE_DIR . 'app/blobs.php';

						qa_db_user_set($userid, array(
							'avatarblobid' => null,
							'avatarwidth' => null,
							'avatarheight' => null,
						));

						qa_delete_blob($useraccount['avatarblobid']);
					}
				}

				if ($fieldseditable) {
					$filterhandle = $handle; // we're not filtering the handle...
					$errors = qa_handle_email_filter($filterhandle, $inemail, $useraccount);
					unset($errors['handle']); // ...and we don't care about any errors in it

					if (!isset($errors['email'])) {
						if ($inemail != $useraccount['email']) {
							qa_db_user_set($userid, 'email', $inemail);
							qa_db_user_set_flag($userid, QA_USER_FLAGS_EMAIL_CONFIRMED, false);
						}
					}

					if (count($inprofile)) {
						$filtermodules = qa_load_modules_with('filter', 'filter_profile');
						foreach ($filtermodules as $filtermodule)
							$filtermodule->filter_profile($inprofile, $errors, $useraccount, $userprofile);
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

					if (count($errors))
						$userediting = true;

					qa_report_event('u_edit', $loginuserid, qa_get_logged_in_handle(), qa_cookie_get(), array(
						'userid' => $userid,
						'handle' => $useraccount['handle'],
					));
				}

				if (isset($maxlevelassign)) {
					$inlevel = min($maxlevelassign, (int)qa_post_text('level')); // constrain based on maximum permitted to prevent simple browser-based attack
					if ($inlevel != $useraccount['level'])
						qa_set_user_level($userid, $useraccount['handle'], $inlevel, $useraccount['level']);

					if (qa_using_categories()) {
						$inuserlevels = array();

						for ($index = 1; $index <= 999; $index++) {
							$inlevel = qa_post_text('uc_' . $index . '_level');
							if (!isset($inlevel))
								break;

							$categoryid = qa_get_category_field_value('uc_' . $index . '_cat');

							if (strlen((string)$categoryid) && strlen($inlevel)) {
								$inuserlevels[] = array(
									'entitytype' => QA_ENTITY_CATEGORY,
									'entityid' => $categoryid,
									'level' => min($maxlevelassign, (int)$inlevel),
								);
							}
						}

						qa_db_user_levels_set($userid, $inuserlevels);
					}
				}

				if (empty($errors))
					qa_redirect(qa_request());

				list($useraccount, $userprofile, $userlevels) = qa_db_select_with_pending(
					qa_db_user_account_selectspec($userid, true),
					qa_db_user_profile_selectspec($userid, true),
					qa_db_user_levels_selectspec($userid, true, true)
				);
			}
		}
	}

	if (qa_clicked('doapprove') || qa_clicked('doblock') || qa_clicked('dounblock') || qa_clicked('dohideall') || qa_clicked('dodelete')) {
		if (!qa_check_form_security_code('user-' . $handle, qa_post_text('code')))
			$errors['page'] = qa_lang_html('misc/form_security_again');

		else {
			if ($approvebutton && qa_clicked('doapprove')) {
				require_once QA_INCLUDE_DIR . 'app/users-edit.php';
				qa_set_user_level($userid, $useraccount['handle'], QA_USER_LEVEL_APPROVED, $useraccount['level']);
				qa_redirect(qa_request());
			}

			if (isset($maxlevelassign) && ($maxuserlevel < QA_USER_LEVEL_MODERATOR)) {
				if (qa_clicked('doblock')) {
					require_once QA_INCLUDE_DIR . 'app/users-edit.php';

					qa_set_user_blocked($userid, $useraccount['handle'], true);
					qa_redirect(qa_request());
				}

				if (qa_clicked('dounblock')) {
					require_once QA_INCLUDE_DIR . 'app/users-edit.php';

					qa_set_user_blocked($userid, $useraccount['handle'], false);
					qa_redirect(qa_request());
				}

				if (qa_clicked('dohideall') && !qa_user_permit_error('permit_hide_show')) {
					require_once QA_INCLUDE_DIR . 'db/admin.php';
					require_once QA_INCLUDE_DIR . 'app/posts.php';

					$postids = qa_db_get_user_visible_postids($userid);

					foreach ($postids as $postid)
						qa_post_set_status($postid, QA_POST_STATUS_HIDDEN, $loginuserid);

					qa_redirect(qa_request());
				}

				if (qa_clicked('dodelete') && ($loginlevel >= QA_USER_LEVEL_ADMIN)) {
					require_once QA_INCLUDE_DIR . 'app/users-edit.php';

					qa_report_event('u_delete_before', $loginuserid, qa_get_logged_in_handle(), qa_cookie_get(), array(
						'userid' => $userid,
						'handle' => $useraccount['handle'],
					));

					qa_delete_user($userid, $useraccount);

					qa_report_event('u_delete', $loginuserid, qa_get_logged_in_handle(), qa_cookie_get(), array(
						'userid' => $userid,
						'handle' => $useraccount['handle'],
					));

					qa_redirect('users');
				}
			}
		}
	}


	if (qa_clicked('dowallpost')) {
		$inmessage = qa_post_text('message');

		if (!strlen((string)$inmessage)) {
			$errors['message'] = qa_lang('profile/post_wall_empty');
		} elseif (!qa_check_form_security_code('wall-' . $useraccount['handle'], qa_post_text('code'))) {
			$errors['message'] = qa_lang_html('misc/form_security_again');
		} elseif (!$wallposterrorhtml) {
			qa_wall_add_post($loginuserid, qa_get_logged_in_handle(), qa_cookie_get(), $userid, $useraccount['handle'], $inmessage, '');
			qa_redirect(qa_request(), null, null, null, 'wall');
		}
	}
}


// Process bonus setting button

if ($loginlevel >= QA_USER_LEVEL_ADMIN && qa_clicked('dosetbonus')) {
	require_once QA_INCLUDE_DIR . 'db/points.php';

	$inbonus = (int)qa_post_text('bonus');

	if (!qa_check_form_security_code('user-activity-' . $handle, qa_post_text('code'))) {
		$errors['page'] = qa_lang_html('misc/form_security_again');
	} else {
		qa_db_points_set_bonus($userid, $inbonus);
		qa_db_points_update_ifuser($userid, null);
		qa_redirect(qa_request(), null, null, null, 'activity');
	}
}


// Prepare content for theme


$qa_content = qa_content_prepare();

$qa_content['title'] = qa_lang_html_sub('profile/user_x', $userhtml);
$qa_content['error'] = isset($errors['page']) ? $errors['page'] : null;

if (!QA_FINAL_EXTERNAL_USERS && isset($loginuserid) && $loginuserid != $useraccount['userid']) {
	$favoritemap = qa_get_favorite_non_qs_map();
	$favorite = @$favoritemap['user'][$useraccount['userid']];

	$qa_content['favorite'] = qa_favorite_form(QA_ENTITY_USER, $useraccount['userid'], $favorite,
		qa_lang_sub($favorite ? 'main/remove_x_favorites' : 'users/add_user_x_favorites', $handle));
}


$field = array();


// General information about the user, only available if we're using internal user management

if (!QA_FINAL_EXTERNAL_USERS) {
	$membertime = qa_time_to_string(qa_opt('db_time') - $useraccount['created']);
	$joindate = qa_when_to_html($useraccount['created'], 0);

	$qa_content['form_profile'] = array(
		'tags' => 'enctype="multipart/form-data" method="post" action="' . qa_self_html() . '"',

		'style' => 'wide',

		'fields' => array(
			'avatar' => array(
				'type' => 'image',
				'style' => 'tall',
				'label' => '',
				'html' => qa_get_user_avatar_html($useraccount['flags'], $useraccount['email'], $useraccount['userid'],
					$useraccount['avatarblobid'], $useraccount['avatarwidth'], $useraccount['avatarheight'], qa_opt('avatar_profile_size')),
				'id' => 'avatar',
			),

			'removeavatar' => null,

			'duration' => array(
				'type' => 'static',
				'label' => qa_lang_html('users/member_for'),
				'value' => qa_html($membertime . ' (' . qa_lang_sub('main/since_x', $joindate['data']) . ')'),
				'id' => 'duration',
			),

			'level' => array(
				'type' => 'static',
				'label' => qa_lang_html('users/member_type'),
				'tags' => 'name="level"',
				'value' => qa_html(qa_user_level_string($useraccount['level'])),
				'note' => (($useraccount['flags'] & QA_USER_FLAGS_USER_BLOCKED) && isset($maxlevelassign)) ? qa_lang_html('users/user_blocked') : '',
				'id' => 'level',
			),
		),
	);

	if (empty($qa_content['form_profile']['fields']['avatar']['html']))
		unset($qa_content['form_profile']['fields']['avatar']);


	// Private message link

	if (qa_opt('allow_private_messages') && isset($loginuserid) && $loginuserid != $userid && !($useraccount['flags'] & QA_USER_FLAGS_NO_MESSAGES) && !$userediting) {
		$qa_content['form_profile']['fields']['level']['value'] .= strtr(qa_lang_html('profile/send_private_message'), array(
			'^1' => '<a href="' . qa_path_html('message/' . $handle) . '">',
			'^2' => '</a>',
		));
	}


	// Levels editing or viewing (add category-specific levels)

	if ($userediting) {
		if (isset($maxlevelassign)) {
			$qa_content['form_profile']['fields']['level']['type'] = 'select';

			$showlevels = array(QA_USER_LEVEL_BASIC);
			if (qa_opt('moderate_users'))
				$showlevels[] = QA_USER_LEVEL_APPROVED;

			array_push($showlevels, QA_USER_LEVEL_EXPERT, QA_USER_LEVEL_EDITOR, QA_USER_LEVEL_MODERATOR, QA_USER_LEVEL_ADMIN, QA_USER_LEVEL_SUPER);

			$leveloptions = array();
			$catleveloptions = array('' => qa_lang_html('users/category_level_none'));

			foreach ($showlevels as $showlevel) {
				if ($showlevel <= $maxlevelassign) {
					$leveloptions[$showlevel] = qa_html(qa_user_level_string($showlevel));
					if ($showlevel > QA_USER_LEVEL_BASIC)
						$catleveloptions[$showlevel] = $leveloptions[$showlevel];
				}
			}

			$qa_content['form_profile']['fields']['level']['options'] = $leveloptions;
			
			// Category-specific levels

			if (qa_using_categories()) {
				$catleveladd = strlen((string)qa_get('catleveladd')) > 0;

				if (!$catleveladd && !count($userlevels)) {
					$qa_content['form_profile']['fields']['level']['suffix'] = strtr(qa_lang_html('users/category_level_add'), array(
						'^1' => '<a href="' . qa_path_html(qa_request(), array('state' => 'edit', 'catleveladd' => 1)) . '">',
						'^2' => '</a>',
					));
				} else {
					$qa_content['form_profile']['fields']['level']['suffix'] = qa_lang_html('users/level_in_general');
				}

				if ($catleveladd || count($userlevels))
					$userlevels[] = array('entitytype' => QA_ENTITY_CATEGORY);

				$index = 0;
				foreach ($userlevels as $userlevel) {
					if ($userlevel['entitytype'] == QA_ENTITY_CATEGORY) {
						$index++;
						$id = 'ls_' . +$index;

						$qa_content['form_profile']['fields']['uc_' . $index . '_level'] = array(
							'label' => qa_lang_html('users/category_level_label'),
							'type' => 'select',
							'tags' => 'name="uc_' . $index . '_level" id="' . qa_html($id) . '" onchange="this.qa_prev=this.options[this.selectedIndex].value;"',
							'options' => $catleveloptions,
							'value' => isset($userlevel['level']) ? qa_html(qa_user_level_string($userlevel['level'])) : '',
							'suffix' => qa_lang_html('users/category_level_in'),
						);

						$qa_content['form_profile']['fields']['uc_' . $index . '_cat'] = array();

						if (isset($userlevel['entityid']))
							$fieldnavcategories = qa_db_select_with_pending(qa_db_category_nav_selectspec($userlevel['entityid'], true));
						else
							$fieldnavcategories = $navcategories;

						qa_set_up_category_field($qa_content, $qa_content['form_profile']['fields']['uc_' . $index . '_cat'],
							'uc_' . $index . '_cat', $fieldnavcategories, @$userlevel['entityid'], true, true);

						unset($qa_content['form_profile']['fields']['uc_' . $index . '_cat']['note']);
					}
				}

				$qa_content['script_lines'][] = array(
					"function qa_update_category_levels()",
					"{",
					"\tglob=document.getElementById('level_select');",
					"\tif (!glob)",
					"\t\treturn;",
					"\tvar opts=glob.options;",
					"\tvar lev=parseInt(opts[glob.selectedIndex].value);",
					"\tfor (var i=1; i<9999; i++) {",
					"\t\tvar sel=document.getElementById('ls_'+i);",
					"\t\tif (!sel)",
					"\t\t\tbreak;",
					"\t\tsel.qa_prev=sel.qa_prev || sel.options[sel.selectedIndex].value;",
					"\t\tsel.options.length=1;", // just leaves "no upgrade" element
					"\t\tfor (var j=0; j<opts.length; j++)",
					"\t\t\tif (parseInt(opts[j].value)>lev)",
					"\t\t\t\tsel.options[sel.options.length]=new Option(opts[j].text, opts[j].value, false, (opts[j].value==sel.qa_prev));",
					"\t}",
					"}",
				);

				$qa_content['script_onloads'][] = array(
					"qa_update_category_levels();",
				);

				$qa_content['form_profile']['fields']['level']['tags'] .= ' id="level_select" onchange="qa_update_category_levels();"';
			}
		}

	} else {
		foreach ($userlevels as $userlevel) {
			if ($userlevel['entitytype'] == QA_ENTITY_CATEGORY && $userlevel['level'] > $useraccount['level']) {
				$qa_content['form_profile']['fields']['level']['value'] .= '<br/>' .
					strtr(qa_lang_html('users/level_for_category'), array(
						'^1' => qa_html(qa_user_level_string($userlevel['level'])),
						'^2' => '<a href="' . qa_path_html(implode('/', array_reverse(explode('/', $userlevel['backpath'])))) . '">' . qa_html($userlevel['title']) . '</a>',
					));
			}
		}
		
	}


	// Show any extra privileges due to user's level or their points

	$showpermits = array();
	$permitoptions = qa_get_permit_options();

	foreach ($permitoptions as $permitoption) {
		// if not available to approved and email confirmed users with no points, but yes available to the user, it's something special
		if (qa_permit_error($permitoption, $userid, QA_USER_LEVEL_APPROVED, QA_USER_FLAGS_EMAIL_CONFIRMED, 0) &&
			!qa_permit_error($permitoption, $userid, $useraccount['level'], $useraccount['flags'], $userpoints['points'])
		) {
			if ($permitoption == 'permit_retag_cat')
				$showpermits[] = qa_lang(qa_using_categories() ? 'profile/permit_recat' : 'profile/permit_retag');
			else
				$showpermits[] = qa_lang('profile/' . $permitoption); // then show it as an extra priviliege
		}
	}

	if (count($showpermits)) {
		$qa_content['form_profile']['fields']['permits'] = array(
			'type' => 'static',
			'label' => qa_lang_html('profile/extra_privileges'),
			'value' => qa_html(implode("\n", $showpermits), true),
			'rows' => count($showpermits),
			'id' => 'permits',
		);
	}


	// Show email address only if we're an administrator

	if ($loginlevel >= QA_USER_LEVEL_ADMIN && !qa_user_permit_error()) {
		$doconfirms = qa_opt('confirm_user_emails') && $useraccount['level'] < QA_USER_LEVEL_EXPERT;
		$isconfirmed = ($useraccount['flags'] & QA_USER_FLAGS_EMAIL_CONFIRMED) > 0;
		$htmlemail = qa_html(isset($inemail) ? $inemail : $useraccount['email']);

		$qa_content['form_profile']['fields']['email'] = array(
			'type' => $userediting ? 'text' : 'static',
			'label' => qa_lang_html('users/email_label'),
			'tags' => 'name="email"',
			'value' => $userediting ? $htmlemail : ('<a href="mailto:' . $htmlemail . '">' . $htmlemail . '</a>'),
			'error' => qa_html(isset($errors['email']) ? $errors['email'] : null),
			'note' => ($doconfirms ? (qa_lang_html($isconfirmed ? 'users/email_confirmed' : 'users/email_not_confirmed') . ' ') : '') .
				($userediting ? '' : qa_lang_html('users/only_shown_admins')),
			'id' => 'email',
		);
	}


	// Show IP addresses and times for last login or write - only if we're a moderator or higher

	if ($loginlevel >= QA_USER_LEVEL_MODERATOR && !qa_user_permit_error()) {
		$qa_content['form_profile']['fields']['lastlogin'] = array(
			'type' => 'static',
			'label' => qa_lang_html('users/last_login_label'),
			'value' =>
				strtr(qa_lang_html('users/x_ago_from_y'), array(
					'^1' => qa_time_to_string(qa_opt('db_time') - $useraccount['loggedin']),
					'^2' => qa_ip_anchor_html(@inet_ntop($useraccount['loginip'])),
				)),
			'note' => $userediting ? null : qa_lang_html('users/only_shown_moderators'),
			'id' => 'lastlogin',
		);

		if (isset($useraccount['written'])) {
			$qa_content['form_profile']['fields']['lastwrite'] = array(
				'type' => 'static',
				'label' => qa_lang_html('users/last_write_label'),
				'value' =>
					strtr(qa_lang_html('users/x_ago_from_y'), array(
						'^1' => qa_time_to_string(qa_opt('db_time') - $useraccount['written']),
						'^2' => qa_ip_anchor_html(@inet_ntop($useraccount['writeip'])),
					)),
				'note' => $userediting ? null : qa_lang_html('users/only_shown_moderators'),
				'id' => 'lastwrite',
			);
		} else {
			unset($qa_content['form_profile']['fields']['lastwrite']);
		}
	}
	
	
	if ($userediting) {
		
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
	
	} else {
		
		//Если пользователь ДОКТОР, то выводим его категории в профиле
		if($useraccount['level'] == 20){

			$qa_content['form_profile']['fields']['stw_user_category'] = array(
				'type' => 'static',
				'label' => qa_lang_html('profile/stw_user_category_edit_label'),
				'value' => qa_html(implode("\n", $stw_users_categories_userid_title), true),
				'rows' => count($stw_users_categories_userid_title),
				'id' => 'stw_user_category',
			);
		
		}

	}
	
	// Show other profile fields

	$fieldsediting = $fieldseditable && $userediting;

	foreach ($userfields as $userfield) {
		if (($userfield['flags'] & QA_FIELD_FLAGS_LINK_URL) && !$fieldsediting) {
			$valuehtml = qa_url_to_html_link(isset($userprofile[$userfield['title']]) ? $userprofile[$userfield['title']] : '', qa_opt('links_in_new_window'));
		} else {
			$value = @$inprofile[$userfield['fieldid']];
			if (!isset($value))
				$value = @$userprofile[$userfield['title']];

			$valuehtml = qa_html($value, (($userfield['flags'] & QA_FIELD_FLAGS_MULTI_LINE) && !$fieldsediting));
		}

		$label = trim(qa_user_userfield_label($userfield), ':');
		if (strlen($label))
			$label .= ':';

		$notehtml = null;
		if (isset($userfield['permit']) && !$userediting) {
			if ($userfield['permit'] <= QA_PERMIT_ADMINS)
				$notehtml = qa_lang_html('users/only_shown_admins');
			elseif ($userfield['permit'] <= QA_PERMIT_MODERATORS)
				$notehtml = qa_lang_html('users/only_shown_moderators');
			elseif ($userfield['permit'] <= QA_PERMIT_EDITORS)
				$notehtml = qa_lang_html('users/only_shown_editors');
			elseif ($userfield['permit'] <= QA_PERMIT_EXPERTS)
				$notehtml = qa_lang_html('users/only_shown_experts');
		}
		
		if($userfield['flags'] == '3'){ //Если тип image
			
			if($fieldsediting){
				
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
				'type' => $fieldsediting ? 'text' : 'static',
				'label' => qa_html($label),
				'tags' => 'name="field_' . $userfield['fieldid'] . '"',
				'value' => $valuehtml,
				'error' => qa_html(isset($errors[$userfield['fieldid']]) ? $errors[$userfield['fieldid']] : null),
				'note' => $notehtml,
				'rows' => ($userfield['flags'] & QA_FIELD_FLAGS_MULTI_LINE) ? 8 : null,
				'id' => 'userfield-' . $userfield['fieldid'],
			);
			
		}
	}


	// Edit form or button, if appropriate

	if ($userediting) {
		if ((qa_opt('avatar_allow_gravatar') && ($useraccount['flags'] & QA_USER_FLAGS_SHOW_GRAVATAR)) ||
			(qa_opt('avatar_allow_upload') && ($useraccount['flags'] & QA_USER_FLAGS_SHOW_AVATAR) && isset($useraccount['avatarblobid']))
		) {
			$qa_content['form_profile']['fields']['removeavatar'] = array(
				'type' => 'checkbox',
				'label' => qa_lang_html('users/remove_avatar'),
				'tags' => 'name="removeavatar"',
			);
		}

		$qa_content['form_profile']['buttons'] = array(
			'save' => array(
				'tags' => 'onclick="qa_show_waiting_after(this, false);"',
				'label' => qa_lang_html('users/save_user'),
			),

			'cancel' => array(
				'tags' => 'name="docancel"',
				'label' => qa_lang_html('main/cancel_button'),
			),
		);

		$qa_content['form_profile']['hidden'] = array(
			'dosave' => '1',
			'code' => qa_get_form_security_code('user-edit-' . $handle),
		);

	} elseif ($usereditbutton) {
		$qa_content['form_profile']['buttons'] = array();

		if ($approvebutton) {
			$qa_content['form_profile']['buttons']['approve'] = array(
				'tags' => 'name="doapprove"',
				'label' => qa_lang_html('users/approve_user_button'),
			);
		}

		$qa_content['form_profile']['buttons']['edit'] = array(
			'tags' => 'name="doedit"',
			'label' => qa_lang_html('users/edit_user_button'),
		);

		if (isset($maxlevelassign) && $useraccount['level'] < QA_USER_LEVEL_MODERATOR) {
			if ($useraccount['flags'] & QA_USER_FLAGS_USER_BLOCKED) {
				$qa_content['form_profile']['buttons']['unblock'] = array(
					'tags' => 'name="dounblock"',
					'label' => qa_lang_html('users/unblock_user_button'),
				);

				if (!qa_user_permit_error('permit_hide_show')) {
					$qa_content['form_profile']['buttons']['hideall'] = array(
						'tags' => 'name="dohideall" onclick="qa_show_waiting_after(this, false);"',
						'label' => qa_lang_html('users/hide_all_user_button'),
					);
				}

				if ($loginlevel >= QA_USER_LEVEL_ADMIN) {
					$qa_content['form_profile']['buttons']['delete'] = array(
						'tags' => 'name="dodelete" onclick="qa_show_waiting_after(this, false);"',
						'label' => qa_lang_html('users/delete_user_button'),
					);
				}

			} else {
				$qa_content['form_profile']['buttons']['block'] = array(
					'tags' => 'name="doblock"',
					'label' => qa_lang_html('users/block_user_button'),
				);
			}

			$qa_content['form_profile']['hidden'] = array(
				'code' => qa_get_form_security_code('user-' . $handle),
			);
		}

	} elseif (isset($loginuserid) && ($loginuserid == $userid)) {
		$qa_content['form_profile']['buttons'] = array(
			'account' => array(
				'tags' => 'name="doaccount"',
				'label' => qa_lang_html('users/edit_profile'),
			),
		);
	}


	if (!is_array($qa_content['form_profile']['fields']['removeavatar']))
		unset($qa_content['form_profile']['fields']['removeavatar']);

	$qa_content['raw']['account'] = $useraccount; // for plugin layers to access
	$qa_content['raw']['profile'] = $userprofile;
}


// Information about user activity, available also with single sign-on integration

$qa_content['form_activity'] = array(
	'title' => '<span id="activity">' . qa_lang_html_sub('profile/activity_by_x', $userhtml) . '</span>',

	'style' => 'wide',

	'fields' => array(
		'bonus' => array(
			'label' => qa_lang_html('profile/bonus_points'),
			'tags' => 'name="bonus"',
			'value' => qa_html(isset($inbonus) ? $inbonus : @$userpoints['bonus']),
			'type' => 'number',
			'note' => qa_lang_html('users/only_shown_admins'),
			'id' => 'bonus',
		),

		'points' => array(
			'type' => 'static',
			'label' => qa_lang_html('profile/score'),
			'value' => (@$userpoints['points'] == 1)
				? qa_lang_html_sub('main/1_point', '<span class="qa-uf-user-points">1</span>', '1')
				: qa_lang_html_sub('main/x_points', '<span class="qa-uf-user-points">' . qa_html(qa_format_number(@$userpoints['points'])) . '</span>'),
			'id' => 'points',
		),

		'title' => array(
			'type' => 'static',
			'label' => qa_lang_html('profile/title'),
			'value' => qa_get_points_title_html(@$userpoints['points'], qa_get_points_to_titles()),
			'id' => 'title',
		),

		'questions' => array(
			'type' => 'static',
			'label' => qa_lang_html('profile/questions'),
			'value' => '<span class="qa-uf-user-q-posts">' . qa_html(qa_format_number(@$userpoints['qposts'])) . '</span>',
			'id' => 'questions',
		),

		'answers' => array(
			'type' => 'static',
			'label' => qa_lang_html('profile/answers'),
			'value' => '<span class="qa-uf-user-a-posts">' . qa_html(qa_format_number(@$userpoints['aposts'])) . '</span>',
			'id' => 'answers',
		),
	),
);

if ($loginlevel >= QA_USER_LEVEL_ADMIN) {
	$qa_content['form_activity']['tags'] = 'method="post" action="' . qa_self_html() . '"';

	$qa_content['form_activity']['buttons'] = array(
		'setbonus' => array(
			'tags' => 'name="dosetbonus"',
			'label' => qa_lang_html('profile/set_bonus_button'),
		),
	);

	$qa_content['form_activity']['hidden'] = array(
		'code' => qa_get_form_security_code('user-activity-' . $handle),
	);

} else {
	unset($qa_content['form_activity']['fields']['bonus']);
}

if (!isset($qa_content['form_activity']['fields']['title']['value']))
	unset($qa_content['form_activity']['fields']['title']);

if (qa_opt('comment_on_qs') || qa_opt('comment_on_as')) { // only show comment count if comments are enabled
	$qa_content['form_activity']['fields']['comments'] = array(
		'type' => 'static',
		'label' => qa_lang_html('profile/comments'),
		'value' => '<span class="qa-uf-user-c-posts">' . qa_html(qa_format_number(@$userpoints['cposts'])) . '</span>',
		'id' => 'comments',
	);
}

if (qa_opt('voting_on_qs') || qa_opt('voting_on_as')) { // only show vote record if voting is enabled
	$votedonvalue = '';

	if (qa_opt('voting_on_qs')) {
		$qvotes = @$userpoints['qupvotes'] + @$userpoints['qdownvotes'];

		$innervalue = '<span class="qa-uf-user-q-votes">' . qa_format_number($qvotes) . '</span>';
		$votedonvalue .= ($qvotes == 1) ? qa_lang_html_sub('main/1_question', $innervalue, '1')
			: qa_lang_html_sub('main/x_questions', $innervalue);

		if (qa_opt('voting_on_as'))
			$votedonvalue .= ', ';
	}

	if (qa_opt('voting_on_as')) {
		$avotes = @$userpoints['aupvotes'] + @$userpoints['adownvotes'];

		$innervalue = '<span class="qa-uf-user-a-votes">' . qa_format_number($avotes) . '</span>';
		$votedonvalue .= ($avotes == 1) ? qa_lang_html_sub('main/1_answer', $innervalue, '1')
			: qa_lang_html_sub('main/x_answers', $innervalue);
	}

	$qa_content['form_activity']['fields']['votedon'] = array(
		'type' => 'static',
		'label' => qa_lang_html('profile/voted_on'),
		'value' => $votedonvalue,
		'id' => 'votedon',
	);

	$upvotes = @$userpoints['qupvotes'] + @$userpoints['aupvotes'];
	$innervalue = '<span class="qa-uf-user-upvotes">' . qa_format_number($upvotes) . '</span>';
	$votegavevalue = (($upvotes == 1) ? qa_lang_html_sub('profile/1_up_vote', $innervalue, '1') : qa_lang_html_sub('profile/x_up_votes', $innervalue)) . ', ';

	$downvotes = @$userpoints['qdownvotes'] + @$userpoints['adownvotes'];
	$innervalue = '<span class="qa-uf-user-downvotes">' . qa_format_number($downvotes) . '</span>';
	$votegavevalue .= ($downvotes == 1) ? qa_lang_html_sub('profile/1_down_vote', $innervalue, '1') : qa_lang_html_sub('profile/x_down_votes', $innervalue);

	$qa_content['form_activity']['fields']['votegave'] = array(
		'type' => 'static',
		'label' => qa_lang_html('profile/gave_out'),
		'value' => $votegavevalue,
		'id' => 'votegave',
	);

	$innervalue = '<span class="qa-uf-user-upvoteds">' . qa_format_number(@$userpoints['upvoteds']) . '</span>';
	$votegotvalue = ((@$userpoints['upvoteds'] == 1) ? qa_lang_html_sub('profile/1_up_vote', $innervalue, '1')
			: qa_lang_html_sub('profile/x_up_votes', $innervalue)) . ', ';

	$innervalue = '<span class="qa-uf-user-downvoteds">' . qa_format_number(@$userpoints['downvoteds']) . '</span>';
	$votegotvalue .= (@$userpoints['downvoteds'] == 1) ? qa_lang_html_sub('profile/1_down_vote', $innervalue, '1')
		: qa_lang_html_sub('profile/x_down_votes', $innervalue);

	$qa_content['form_activity']['fields']['votegot'] = array(
		'type' => 'static',
		'label' => qa_lang_html('profile/received'),
		'value' => $votegotvalue,
		'id' => 'votegot',
	);
}

if (@$userpoints['points']) {
	$qa_content['form_activity']['fields']['points']['value'] .=
		qa_lang_html_sub('profile/ranked_x', '<span class="qa-uf-user-rank">' . qa_format_number($userrank) . '</span>');
}

if (@$userpoints['aselects']) {
	$qa_content['form_activity']['fields']['questions']['value'] .= ($userpoints['aselects'] == 1)
		? qa_lang_html_sub('profile/1_with_best_chosen', '<span class="qa-uf-user-q-selects">1</span>', '1')
		: qa_lang_html_sub('profile/x_with_best_chosen', '<span class="qa-uf-user-q-selects">' . qa_format_number($userpoints['aselects']) . '</span>');
}

if (@$userpoints['aselecteds']) {
	$qa_content['form_activity']['fields']['answers']['value'] .= ($userpoints['aselecteds'] == 1)
		? qa_lang_html_sub('profile/1_chosen_as_best', '<span class="qa-uf-user-a-selecteds">1</span>', '1')
		: qa_lang_html_sub('profile/x_chosen_as_best', '<span class="qa-uf-user-a-selecteds">' . qa_format_number($userpoints['aselecteds']) . '</span>');
}


// For plugin layers to access

$qa_content['raw']['userid'] = $userid;
$qa_content['raw']['points'] = $userpoints;
$qa_content['raw']['rank'] = $userrank;


// Wall posts

if (!QA_FINAL_EXTERNAL_USERS && qa_opt('allow_user_walls')) {
	$qa_content['message_list'] = array(
		'title' => '<span id="wall">' . qa_lang_html_sub('profile/wall_for_x', $userhtml) . '</span>',

		'tags' => 'id="wallmessages"',

		'form' => array(
			'tags' => 'name="wallpost" method="post" action="' . qa_self_html() . '#wall"',
			'style' => 'tall',
			'hidden' => array(
				'qa_click' => '', // for simulating clicks in Javascript
				'handle' => qa_html($useraccount['handle']),
				'start' => 0,
				'code' => qa_get_form_security_code('wall-' . $useraccount['handle']),
			),
		),

		'messages' => array(),
	);

	if ($wallposterrorhtml) {
		$qa_content['message_list']['error'] = $wallposterrorhtml; // an error that means we are not allowed to post
	} else {
		$qa_content['message_list']['form']['fields'] = array(
			'message' => array(
				'tags' => 'name="message" id="message"',
				'value' => qa_html(@$inmessage, false),
				'rows' => 2,
				'error' => qa_html(isset($errors['message']) ? $errors['message'] : null),
			),
		);

		$qa_content['message_list']['form']['buttons'] = array(
			'post' => array(
				'tags' => 'name="dowallpost" onclick="return qa_submit_wall_post(this, true);"',
				'label' => qa_lang_html('profile/post_wall_button'),
			),
		);
	}

	foreach ($usermessages as $message)
		$qa_content['message_list']['messages'][] = qa_wall_post_view($message);

	if ($useraccount['wallposts'] > count($usermessages))
		$qa_content['message_list']['messages'][] = qa_wall_view_more_link($userid, $handle, count($usermessages));
}


// Sub menu for navigation in user pages

$ismyuser = isset($loginuserid) && $loginuserid == (QA_FINAL_EXTERNAL_USERS ? $userid : $useraccount['userid']);
$qa_content['navigation']['sub'] = qa_user_sub_navigation($handle, 'profile', $ismyuser);


return $qa_content;
