<?php
// handle template change
if (isset($_POST['change_template_id'])) {
	$settings_request = new CASHRequest(
		array(
			'cash_request_type' => 'system', 
			'cash_action' => 'setsettings',
			'type' => 'public_profile_template',
			'value' => $_POST['change_template_id'],
			'user_id' => $cash_admin->effective_user_id
		)
	);
}



// get username and any user data
$user_response = $cash_admin->requestAndStore(
	array(
		'cash_request_type' => 'people', 
		'cash_action' => 'getuser',
		'user_id' => $cash_admin->effective_user_id
	)
);
if (is_array($user_response['payload'])) {
	$current_username = $user_response['payload']['username'];
	$current_userdata = $user_response['payload']['data'];
}



// get news for the news feed
$session_news = AdminHelper::getActivity($current_userdata);

if (is_array($session_news['activity']['lists'])) {
	foreach ($session_news['activity']['lists'] as &$list_stats) {
		if ($list_stats['total'] == 1) {
			$list_stats['singular'] = true;
		} else {
			$list_stats['singular'] = false;
		}
	}
}

$cash_admin->page_data['dashboard_lists'] = $session_news['activity']['lists'];
if ($session_news['activity']['orders']) {
	$cash_admin->page_data['dashboard_orders'] = count($session_news['activity']['orders']);
	if ($cash_admin->page_data['dashboard_orders'] == 1) {
		$cash_admin->page_data['dashboard_orders_singular'] = true;
	}
} else {
	$cash_admin->page_data['dashboard_orders'] = false;
}



// get page url
if (SUBDOMAIN_USERNAMES) {
	$cash_admin->page_data['user_page_uri'] = str_replace('https','http',rtrim(str_replace('admin', '', CASH_ADMIN_URL),'/'));
	$cash_admin->page_data['user_page_uri'] = str_replace('://','://' . $current_username . '.',$cash_admin->page_data['user_page_uri']);
} else {
	$cash_admin->page_data['user_page_uri'] = str_replace('https','http',rtrim(str_replace('admin', $current_username, CASH_ADMIN_URL),'/'));
}
$cash_admin->page_data['user_page_display_uri'] = str_replace('http://','',$cash_admin->page_data['user_page_uri']);

// all user elements defined
$elements_response = $cash_admin->requestAndStore(
	array(
		'cash_request_type' => 'element', 
		'cash_action' => 'getelementsforuser',
		'user_id' => $cash_admin->effective_user_id
	)
);
if (!is_array($elements_response['payload'])) {
	$elements_response['payload'] = array();
}



// get all campaigns 
$campaigns_response = $cash_admin->requestAndStore(
	array(
		'cash_request_type' => 'element', 
		'cash_action' => 'getcampaignsforuser',
		'user_id' => $cash_admin->effective_user_id
	)
);

$total_campaigns = count($campaigns_response['payload']);
$total_elements = count($elements_response['payload']);

if ($total_campaigns) {
	// 
	// 
	// TODO: proper selection of elements instead of just the first one because whatever
	$current_campaign = $campaigns_response['payload'][0]['id'];
	$admin_primary_cash_request->sessionSet('current_campaign',$current_campaign);

	$campaign_elements = array();
	if (is_array($campaigns_response['payload'])) {
		foreach ($campaigns_response['payload'] as &$campaign) {
			// pull out element details
			$campaign['elements'] = json_decode($campaign['elements'],true);
			if (is_array($campaign['elements'])) {
				$campaign_elements = array_merge($campaign['elements'],$campaign_elements);
				if ($campaign['id'] == $current_campaign) {
					$elements_response = $cash_admin->requestAndStore(
						array(
							'cash_request_type' => 'element', 
							'cash_action' => 'getelementsforcampaign',
							'id' => $campaign['id']
						)
					);

					if (is_array($elements_response['payload'])) {
						$elements_response['payload'] = array_reverse($elements_response['payload']);
						foreach ($elements_response['payload'] as &$element) {
							if ($element['modification_date'] == 0) {
								$element['formatted_date'] = CASHSystem::formatTimeAgo($element['creation_date']);	
							} else {
								$element['formatted_date'] = CASHSystem::formatTimeAgo($element['modification_date']);
							}
						}
						$cash_admin->page_data['elements_for_campaign'] = new ArrayIterator($elements_response['payload']);
					} 
				}
			}
			// set element count
			$campaign['element_count'] = count($campaign['elements']);

			// normalize modification/creation dates
			if ($campaign['modification_date'] == 0) {
				$campaign['formatted_date'] = CASHSystem::formatTimeAgo($campaign['creation_date']);	
			} else {
				$campaign['formatted_date'] = CASHSystem::formatTimeAgo($campaign['modification_date']);
			}

			if ($campaign['id'] == $current_campaign) {
				// set the campaign as the selected campaign
				$cash_admin->page_data['selected_campaign']	= $campaign;

				// get campaign analytics
				$analytics_response = $cash_admin->requestAndStore(
					array(
						'cash_request_type' => 'element', 
						'cash_action' => 'getanalyticsforcampaign',
						'id' => $campaign['id']
					)
				);
				$campaign['formatted_views'] = CASHSystem::formatCount($analytics_response['payload']['total_views']);
			}
		}
	}

	// set all campaigns as a mustache var
	$cash_admin->page_data['campaigns_for_user'] = new ArrayIterator($campaigns_response['payload']);
}



// handle users migrated from beta
$extra_elements = $total_elements - count($campaign_elements);
if ($extra_elements !== 0) {
	$cash_admin->page_data['show_archive'] = true;
}



// figure out and select 	the correct view
if ($total_campaigns) {
	$cash_admin->setPageContentTemplate('mainpage');
	$cash_admin->page_data['has_campaigns'] = true;
	if (!$total_elements) {
		$cash_admin->page_data['campaigns_noelements'] = true;
	}
} else {
	$cash_admin->setPageContentTemplate('mainpage_firstuse');
	if ($total_elements) {
		$cash_admin->page_data['migrated'] = true;
	}
}
?>