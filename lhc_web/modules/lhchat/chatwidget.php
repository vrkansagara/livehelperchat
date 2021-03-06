<?php

// For IE to support headers if chat is installed on different domain
header('P3P:CP="IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR IND CNT"');

$embedMode = false;
$modeAppend = '';
if ((string)$Params['user_parameters_unordered']['mode'] == 'embed') {
	$embedMode = true;
	$modeAppend = '/(mode)/embed';
}

if (is_array($Params['user_parameters_unordered']['ua']) && !empty($Params['user_parameters_unordered']['ua'])) {
    $modeAppend .= '/(ua)/'.implode('/', $Params['user_parameters_unordered']['ua']);
}

$theme = false;
$modeAppendTheme = '';
if (isset($Params['user_parameters_unordered']['theme']) && (int)$Params['user_parameters_unordered']['theme'] > 0){
	try {
		$theme = erLhAbstractModelWidgetTheme::fetch($Params['user_parameters_unordered']['theme']);
		$Result['theme'] = $theme;		
		$modeAppendTheme = '/(theme)/'.$theme->id;
	} catch (Exception $e) {

	}
} else {
	$defaultTheme = erLhcoreClassModelChatConfig::fetch('default_theme_id')->current_value;
	if ($defaultTheme > 0) {
		try {
			$theme = erLhAbstractModelWidgetTheme::fetch($defaultTheme);
			$Result['theme'] = $theme;
			$modeAppendTheme = '/(theme)/'.$theme->id;
		} catch (Exception $e) {
		
		}
	}
}

// Perhaps it's direct argument
if ((string)$Params['user_parameters_unordered']['hash'] != '') {
	list($chatID,$hash) = explode('_',$Params['user_parameters_unordered']['hash']);

	$sound = is_numeric($Params['user_parameters_unordered']['sound']) ? '/(sound)/'.$Params['user_parameters_unordered']['sound'] : '';

	try {
		$chat = erLhcoreClassModelChat::fetch($chatID);
		if ($chat->status == erLhcoreClassModelChat::STATUS_CLOSED_CHAT) {			
			// Reopen chat automatically if possible
			if ( erLhcoreClassModelChatConfig::fetch('automatically_reopen_chat')->current_value == 1 && erLhcoreClassModelChatConfig::fetch('reopen_chat_enabled')->current_value == 1 && erLhcoreClassModelChatConfig::fetch('allow_reopen_closed')->current_value == 1 && erLhcoreClassChat::canReopen($chat) !== false ) {
				
				$sound = is_numeric($Params['user_parameters_unordered']['sound']) ? '/(sound)/'.$Params['user_parameters_unordered']['sound'] : '';
				erLhcoreClassModule::redirect('chat/reopen','/' .$chatID . '/' . $hash . '/(mode)/widget' . $modeAppend . $modeAppendTheme . $sound );
				exit;
			}
		}
	} catch (Exception $e) {
		
	}
	// Redirect user
	erLhcoreClassModule::redirect('chat/chatwidgetchat','/' . $chatID . '/' . $hash . $modeAppend . $modeAppendTheme . $sound );
	exit;
}

$tpl = erLhcoreClassTemplate::getInstance( 'lhchat/chatwidget.tpl.php');
$tpl->set('referer','');
$tpl->set('referer_site','');
$tpl->set('theme',$theme);

$disabled_department = false;

if (is_array($Params['user_parameters_unordered']['department']) && erLhcoreClassModelChatConfig::fetch('hide_disabled_department')->current_value == 1){
	try {
		
		erLhcoreClassChat::validateFilterIn($Params['user_parameters_unordered']['department']);				
		$departments = erLhcoreClassModelDepartament::getList(array('filterin' => array('id' => $Params['user_parameters_unordered']['department'])));
		
		$disabledAll = true;
		foreach ($departments as $department){
			if ($department->disabled == 0) {
				$disabledAll = false;
			}
		}
		
		// Disable only if all provided departments are disabled
		if ($disabledAll == true){
			$disabled_department = true;
		}
		
	} catch (Exception $e) {
		exit;
	}
}

$tpl->set('disabled_department',$disabled_department);
$tpl->set('append_mode',$modeAppend);
$tpl->set('append_mode_theme',$modeAppendTheme);

// Start chat field options
$startData = erLhcoreClassModelChatConfig::fetch('start_chat_data');
$startDataFields = (array)$startData->data;

// Allow extension override start chat fields
erLhcoreClassChatEventDispatcher::getInstance()->dispatch('chat.chatwidget_data_field',array('data_fields' => & $startDataFields, 'params' => $Params));

$inputData = new stdClass();
$inputData->chatprefill = '';
$inputData->email = '';
$inputData->username = '';
$inputData->phone = '';

if (is_array($Params['user_parameters_unordered']['department']) && count($Params['user_parameters_unordered']['department']) == 1){
	erLhcoreClassChat::validateFilterIn($Params['user_parameters_unordered']['department']);
	$inputData->departament_id = array_shift($Params['user_parameters_unordered']['department']);
} else {
	$inputData->departament_id = 0;
}

if (is_array($Params['user_parameters_unordered']['department'])){
	erLhcoreClassChat::validateFilterIn($Params['user_parameters_unordered']['department']);
	$inputData->departament_id_array = $Params['user_parameters_unordered']['department'];
}

$inputData->accept_tos = false;
$inputData->question = '';
$inputData->operator = (int)$Params['user_parameters_unordered']['operator'];

// Perhaps user was redirected to leave a message form because chat was not acceptend in some time interval
if ((string)$Params['user_parameters_unordered']['chatprefill'] != '') {
	list($chatID,$hash) = explode('_',$Params['user_parameters_unordered']['chatprefill']);

	try {
		$chatPrefill = erLhcoreClassModelChat::fetch($chatID);
		if ($chatPrefill->hash == $hash) {
			$inputData->chatprefill = $Params['user_parameters_unordered']['chatprefill'];
			$inputData->username = $chatPrefill->nick;
			$inputData->departament_id = $chatPrefill->dep_id;
			$inputData->email = $chatPrefill->email;
			$inputData->phone = $chatPrefill->phone;
			$inputData->accept_tos = true;			
			$inputData->question = erLhcoreClassChat::getFirstUserMessage($chatPrefill->id);				
		} else {
			unset($chatPrefill);
		}
	} catch (Exception $e) {
		// Do nothing
	}
}

$inputData->username = isset($_GET['prefill']['username']) ? (string)$_GET['prefill']['username'] : $inputData->username;
$inputData->hash_resume = false;
$inputData->vid = false;
$inputData->question = isset($_GET['prefill']['question']) ? (string)$_GET['prefill']['question'] : $inputData->question;
$inputData->email = isset($_GET['prefill']['email']) ? (string)$_GET['prefill']['email'] : $inputData->email;
$inputData->phone = isset($_GET['prefill']['phone']) ? (string)$_GET['prefill']['phone'] : $inputData->phone;
$inputData->validate_start_chat = false;
$inputData->name_items = array();
$inputData->value_items = array();
$inputData->value_sizes = array();
$inputData->value_types = array();
$inputData->value_items_admin = array(); // These variables get's filled from start chat form settings
$inputData->hattr = array();
$inputData->ua = $Params['user_parameters_unordered']['ua'];
$inputData->priority = is_numeric($Params['user_parameters_unordered']['priority']) ? (int)$Params['user_parameters_unordered']['priority'] : false;

// Perhaps it's direct argument
if ((string)$Params['user_parameters_unordered']['hash_resume'] != '') {
	CSCacheAPC::getMem()->setSession('chat_hash_widget_resume',(string)$Params['user_parameters_unordered']['hash_resume'],true,true);
	$inputData->hash_resume = (string)$Params['user_parameters_unordered']['hash_resume'];
}

if ((string)$Params['user_parameters_unordered']['vid'] != '') {
	$inputData->vid = (string)$Params['user_parameters_unordered']['vid'];
}

// Reopen chat automatically if possible
if ( erLhcoreClassModelChatConfig::fetch('automatically_reopen_chat')->current_value == 1 && erLhcoreClassModelChatConfig::fetch('reopen_chat_enabled')->current_value == 1 && ($reopenData = erLhcoreClassChat::canReopenDirectly(array('reopen_closed' => erLhcoreClassModelChatConfig::fetch('allow_reopen_closed')->current_value))) !== false ) {
	$sound = is_numeric($Params['user_parameters_unordered']['sound']) ? '/(sound)/'.$Params['user_parameters_unordered']['sound'] : '';
	erLhcoreClassModule::redirect('chat/reopen','/' . $reopenData['id'] . '/' . $reopenData['hash'] . '/(mode)/widget' . $modeAppend . $modeAppendTheme . $sound );
	exit;
}

$chat = new erLhcoreClassModelChat();

// Assign department instantly
if ($inputData->departament_id > 0) {
	$chat->dep_id = $inputData->departament_id;
	$tpl->set('department',$chat->dep_id);
} else {
	$tpl->set('department',false);
}

// Leave a message functionality
$leaveamessage = ((string)$Params['user_parameters_unordered']['leaveamessage'] == 'true' || (isset($startDataFields['force_leave_a_message']) && $startDataFields['force_leave_a_message'] == true)) ? true : false;

$tpl->set('forceoffline',false);
$additionalParams = array();
if ((string)$Params['user_parameters_unordered']['offline'] == 'true' && $leaveamessage == true) {
	$additionalParams['offline'] = true;
	$tpl->set('forceoffline',true);
}

$tpl->set('leaveamessage',$leaveamessage);

if (isset($_POST['StartChat']) && $disabled_department === false)
{
   // Validate post data
   $Errors = erLhcoreClassChatValidator::validateStartChat($inputData,$startDataFields,$chat,$additionalParams);

   if (count($Errors) == 0 && !isset($_POST['switchLang']))
   {   	
   		$chat->setIP();
   		erLhcoreClassModelChat::detectLocation($chat);
   		
   		$statusGeoAdjustment = erLhcoreClassChat::getAdjustment(erLhcoreClassModelChatConfig::fetch('geoadjustment_data')->data_value, $inputData->vid);

   		if ($statusGeoAdjustment['status'] == 'hidden') { // This should never happen
   			exit('Chat not available in your country');
   		}
   		
   		if ((isset($additionalParams['offline']) && $additionalParams['offline'] == true) || $statusGeoAdjustment['status'] == 'offline') {   			
   			erLhcoreClassChatMail::sendMailRequest($inputData,$chat,array('chatprefill' => isset($chatPrefill) ? $chatPrefill : false));
   			
   			if (isset($chatPrefill) && ($chatPrefill instanceof erLhcoreClassModelChat)) {
   				erLhcoreClassChatValidator::updateInitialChatAttributes($chatPrefill, $chat);
   			}
   			
   			erLhcoreClassChatEventDispatcher::getInstance()->dispatch('chat.chat_offline_request',array(
   			'input_data' => $inputData,
   			'chat' => $chat,
   			'prefill' => array('chatprefill' => isset($chatPrefill) ? $chatPrefill : false)));
   			
   			$Result['parent_messages'][] = 'lh_callback:offline_request_cb';
   			$tpl->set('request_send',true);
   		} else {
	       $chat->time = time();
	       $chat->status = 0;
	       
	       $chat->hash = erLhcoreClassChat::generateHash();
	       $chat->referrer = isset($_POST['URLRefer']) ? $_POST['URLRefer'] : '';
	       $chat->session_referrer = isset($_POST['r']) ? $_POST['r'] : '';

	       if ( empty($chat->nick) ) {
	           $chat->nick = erTranslationClassLhTranslation::getInstance()->getTranslation('chat/startchat','Visitor');
	       }

	       // Store chat
	       $chat->saveThis();

	       // Assign chat to user
	       if ( erLhcoreClassModelChatConfig::fetch('track_online_visitors')->current_value == 1 ) {
	            // To track online users
	            $userInstance = erLhcoreClassModelChatOnlineUser::handleRequest(array('check_message_operator' => true, 'message_seen_timeout' => erLhcoreClassModelChatConfig::fetch('message_seen_timeout')->current_value, 'vid' => $Params['user_parameters_unordered']['vid']));

	            if ($userInstance !== false) {
	                $userInstance->chat_id = $chat->id;
	                $userInstance->dep_id = $chat->dep_id;
	                $userInstance->message_seen = 1;
	                $userInstance->message_seen_ts = time();
	                $userInstance->saveThis();

	                $chat->online_user_id = $userInstance->id;
	                $chat->saveThis();
	                
	                if ( erLhcoreClassModelChatConfig::fetch('track_footprint')->current_value == 1) {
	            		erLhcoreClassModelChatOnlineUserFootprint::assignChatToPageviews($userInstance);
	            	}
	            }
	       }

	       // Store message if required
	       if (isset($startDataFields['message_visible_in_page_widget']) && $startDataFields['message_visible_in_page_widget'] == true) {
	           if ( $inputData->question != '' ) {
	               // Store question as message
	               $msg = new erLhcoreClassModelmsg();
	               $msg->msg = trim($inputData->question);
	               $msg->chat_id = $chat->id;
	               $msg->user_id = 0;
	               $msg->time = time();
	               erLhcoreClassChat::getSession()->save($msg);

	               $chat->last_msg_id = $msg->id;
	               $chat->saveThis();
	           }
	       }

	       // Auto responder
	       $responder = erLhAbstractModelAutoResponder::processAutoResponder($chat);

	       if ($responder instanceof erLhAbstractModelAutoResponder) {
	       		$chat->wait_timeout = $responder->wait_timeout;
	       		$chat->timeout_message = $responder->timeout_message;
	       		$chat->wait_timeout_send = 1-$responder->repeat_number;
	       		$chat->wait_timeout_repeat = $responder->repeat_number;
	       		
	       		if ($responder->wait_message != '') {
		       		$msg = new erLhcoreClassModelmsg();
		       		$msg->msg = trim($responder->wait_message);
		       		$msg->chat_id = $chat->id;
		       		$msg->name_support = erTranslationClassLhTranslation::getInstance()->getTranslation('chat/startchat','Live Support');
		       		$msg->user_id = -2;
		       		$msg->time = time()+5;
		       		erLhcoreClassChat::getSession()->save($msg);

		       		if ($chat->last_msg_id < $msg->id) {
		       			$chat->last_msg_id = $msg->id;
		       		}
	       		}

	       		$chat->saveThis();
	       		
	       		erLhcoreClassChatEventDispatcher::getInstance()->dispatch('chat.auto_responder_triggered',array('chat' => & $chat));
	       }
    	       	       
	       // Redirect user
	       erLhcoreClassModule::redirect('chat/chatwidgetchat','/' . $chat->id . '/' . $chat->hash . $modeAppend . $modeAppendTheme . '/(cstarted)/online_chat_started_cb');
	       
	       flush();
	       session_write_close();
	       
	       if ( function_exists('fastcgi_finish_request') ) {
	           fastcgi_finish_request();
	       };

	       erLhcoreClassChat::updateDepartmentStats($chat->department);
	       
	       erLhcoreClassChatEventDispatcher::getInstance()->dispatch('chat.chat_started',array('chat' => & $chat));	       
	       exit;
   	   }


    } else {
    	// Show errors only if user is not switching form mode and not swithing language
    	if ($Params['user_parameters_unordered']['switchform'] != 'true' && !isset($_POST['switchLang'])){
        	$tpl->set('errors',$Errors);
    	} elseif (isset($_POST['switchLang'])) {
    		$Result['additional_post_message'] = 'lhc_lang:'.erLhcoreClassSystem::instance()->WWWDirLang;
    	}
    }
}

$tpl->set('start_data_fields',$startDataFields);

$definition = array(
		'name'  => new ezcInputFormDefinitionElement(
				ezcInputFormDefinitionElement::OPTIONAL, 'unsafe_raw',
				null,
				FILTER_REQUIRE_ARRAY
		),
		'value' => new ezcInputFormDefinitionElement(
				ezcInputFormDefinitionElement::OPTIONAL, 'unsafe_raw',
				null,
				FILTER_REQUIRE_ARRAY
		),
		'type' => new ezcInputFormDefinitionElement(
				ezcInputFormDefinitionElement::OPTIONAL, 'string',
				null,
				FILTER_REQUIRE_ARRAY
		),
		'size' => new ezcInputFormDefinitionElement(
				ezcInputFormDefinitionElement::OPTIONAL, 'string',
				null,
				FILTER_REQUIRE_ARRAY
		),
		'req' => new ezcInputFormDefinitionElement(
				ezcInputFormDefinitionElement::OPTIONAL, 'string',
				null,
				FILTER_REQUIRE_ARRAY
		),
		'sh' => new ezcInputFormDefinitionElement(
				ezcInputFormDefinitionElement::OPTIONAL, 'string',
				null,
				FILTER_REQUIRE_ARRAY
		),
		'hattr' => new ezcInputFormDefinitionElement(
				ezcInputFormDefinitionElement::OPTIONAL, 'string',
				null,
				FILTER_REQUIRE_ARRAY
		)
);

$form = new ezcInputForm( INPUT_GET, $definition );

if ( $form->hasValidData( 'name' ) && !empty($form->name))
{
	$inputData->name_items = $form->name;
}

if ( $form->hasValidData( 'value' ) && !empty($form->value))
{
	$inputData->value_items = $form->value;
}

if ( $form->hasValidData( 'hattr' ) && !empty($form->hattr))
{
	$inputData->hattr = $form->hattr;
}

if ( $form->hasValidData( 'sh' ) && !empty($form->sh))
{
	$inputData->value_show = $form->sh;
}

if ( $form->hasValidData( 'req' ) && !empty($form->req))
{
	$inputData->values_req = $form->req;
}

if ( $form->hasValidData( 'type' ) && !empty($form->type))
{
	$inputData->value_types = $form->type;
}

if ( $form->hasValidData( 'size' ) && !empty($form->size))
{
	$inputData->value_sizes = $form->size;
}

$tpl->set('input_data',$inputData);

if (isset($_GET['URLReferer']))
{
    $tpl->set('referer',$_GET['URLReferer']);
}

if (isset($_POST['URLRefer']))
{
    $tpl->set('referer',$_POST['URLRefer']);
}

if (isset($_GET['r']))
{
    $tpl->set('referer_site',$_GET['r']);
}

if (isset($_POST['r']))
{
    $tpl->set('referer_site',$_POST['r']);
}

erLhcoreClassChatEventDispatcher::getInstance()->dispatch('chat.chatwidget',array('tpl' => $tpl, 'params' => & $Params));

$Result['content'] = $tpl->fetch();
$Result['pagelayout'] = 'widget';
$Result['dynamic_height'] = true;
$Result['dynamic_height_message'] = 'lhc_sizing_chat';
$Result['pagelayout_css_append'] = 'widget-chat';

if ($embedMode == true) {
	$Result['dynamic_height_message'] = 'lhc_sizing_chat_page';
	$Result['pagelayout_css_append'] = 'embed-widget';
}


?>
