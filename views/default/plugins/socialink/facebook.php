<?php 
	$user = $vars["user"];
	$usersettings = $vars["entity"];
	
	// for yes/no dropdowns
	$yesno_options_values = array(
		"no" => elgg_echo("option:no"),
		"yes" => elgg_echo("option:yes")
	);

	echo "<div>";
	echo "<div class='socialink_usersettings_network_icon' id='socialink_usersettings_facebook_icon'></div>";
	echo "<div class='socialink_usersettings_network_config'>";
	
	// is the user conntected
	if($keys = socialink_facebook_is_connected($user->getGUID())){
		$facebook_remove_link = elgg_add_action_tokens_to_url($vars["url"] . "action/socialink/remove?service=facebook");
		
		$link_begin = "<a href='" . $facebook_remove_link . "'>";
		$link_end = "</a>";
		
		echo "<div>" . elgg_echo("socialink:usersettings:facebook:remove", array($link_begin, $link_end)) . "</div>";
		
		// check for the wire
//		if(is_plugin_enabled("thewire")){
//			echo "<div>";
//			echo elgg_echo("socialink:usersettings:facebook:thewire");
//			echo "&nbsp;" . elgg_view("input/dropdown", array("name" => "params[thewire_post_facebook]", "options_values" => $yesno_options_values, "value" => $usersettings->thewire_post_facebook));
//			echo "</div>";
//		}
		
		// configure profile synchronisation
		if($fields = socialink_get_configured_network_fields("facebook")){
			$network_name = elgg_echo("socialink:network:facebook");
			
			echo "<br />";
			echo "<div>";
			echo elgg_echo("socialink:usersettings:profile_sync", array($network_name));
			echo "&nbsp;" . elgg_view("input/dropdown", array("name" => "params[facebook_sync_allow]", "options_values" => array_reverse($yesno_options_values), "value" => $usersettings->facebook_sync_allow, "js" => "onchange='socialink_toggle_network_configure(this, \"facebook\");'"));
			echo "&nbsp;<span id='socialink_facebook_sync_configure' ";
			if($usersettings->facebook_sync_allow != "no"){
				echo "class='socialink_network_sync_allow'";
			}
			echo "><a href='javascript:void(0);' onclick='$(\"#socialink_facebook_sync_fields\").toggle();'>" . elgg_echo("socialink:configure") . "</a></span>";
			echo "</div>";
			
			echo "<table id='socialink_facebook_sync_fields' class='elgg-table'>";
			echo "<tr>";
			echo "<th>" . elgg_echo("socialink:usersettings:profile_field", array($network_name)) . "</th>";
			echo "<th>" . elgg_echo("socialink:usersettings:profile_sync:allow") . "</th>";
			echo "<tr>";
			
			foreach($fields as $setting_name => $profile_field){
				$setting = "facebook_sync_" . $setting_name;
				$network_string = elgg_echo("socialink:facebook:field:" . $setting_name);
				
				$lan_key = "profile:" . $profile_field;
				if($lan_key == elgg_echo($lan_key)){
					$profile_string = $profile_field;
				} else {
					$profile_string = elgg_echo($lan_key);
				}
				
				echo "<tr>";
				echo "<td>" . elgg_echo("socialink:usersettings:profile_sync:explain", array($network_string, $profile_string)) . "</td>";
				echo "<td>" . elgg_view("input/dropdown", array("name" => "params[" . $setting . "]", "options_values" => array_reverse($yesno_options_values, true), "value" => $usersettings->$setting)) . "</td>";
				echo "<tr>";
			}
			
			echo "</table>";
		}
		
	} else {
		$link_begin = "<a href='" . $vars["url"] . "socialink/forward/facebook/authorize' target='_self'>";
		$link_end = "</a>";
		
		echo elgg_echo("socialink:usersettings:facebook:not_connected", array($link_begin, $link_end));
	}
	
	echo "</div>";
	echo "<div class='clearfloat'></div>";
	echo "</div>";
	