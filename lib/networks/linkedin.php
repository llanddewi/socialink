<?php 

	function socialink_linkedin_get_api_object($keys){
		$result = false;
		
		if(!empty($keys) && is_array($keys)){
			$api_config = array(
				"appKey" => $keys["consumer_key"],
				"appSecret" => $keys["consumer_secret"]
			);
			
			try {
				$api = new LinkedInProxy($api_config);
				
				if(isset($keys["oauth_token"]) && isset($keys["oauth_secret"])){
					$tokens = array(
						"oauth_token" => $keys["oauth_token"],
						"oauth_token_secret" => $keys["oauth_secret"]
					);
					
					$api->setTokenAccess($tokens);
				}
				
				// set response format to JSON 
				$api->setResponseFormat(LinkedIn::_RESPONSE_JSON);
				
				if($proxy_settings = socialink_get_proxy_settings()){
					$api->setProxySettings($proxy_settings);
				}
				
				$result = $api;
			} catch(Exception $e){}
		}
		
		return $result;
	}

	function socialink_linkedin_is_connected($user_guid = 0){
		$result = false;
		
		if(empty($user_guid)){
			$user_guid = elgg_get_logged_in_user_guid();
		}
		
		if(!empty($user_guid) && ($keys = socialink_linkedin_available())){
			$oauth_token = elgg_get_plugin_user_setting("linkedin_oauth_token", $user_guid, "socialink");
			$oauth_secret = elgg_get_plugin_user_setting("linkedin_oauth_secret", $user_guid, "socialink");
			
			if(!empty($oauth_token) && !empty($oauth_secret)){
				$result = $keys;
				$result["oauth_token"] = $oauth_token;
				$result["oauth_secret"] = $oauth_secret;
			}
		}
		
		return $result;
	}
	
	function socialink_linkedin_verify_response($response){
		$result = false;
		
		if(!empty($response) && is_array($response)){
			if(array_key_exists("success", $response)){
				if($response["success"] === true){
					$result = $response["linkedin"];
				}
			}
		}
		
		return $result;
	}
	
	function socialink_linkedin_get_authorize_url($callback = NULL) {
		global $SESSION;
		
		$result = false;
	
		if($keys = socialink_linkedin_available()){
			if($api = socialink_linkedin_get_api_object($keys)){
				try {
					$api->setCallbackUrl($callback);
					$response = $api->retrieveTokenRequest();
					
					if($token = socialink_linkedin_verify_response($response)){
						//$token = $response["linkedin"];
						
						// save token in session for use after authorization
						$SESSION['socialink_linkedin'] = array(
							'oauth_token' => $token["oauth_token"],
							'oauth_token_secret' => $token["oauth_token_secret"],
						);
					
						$result = LinkedIn::_URL_AUTH . $token["oauth_token"];
					}
				} catch(Exception $e){}
			}
		}
		
		return $result;
	}
	
	function socialink_linkedin_get_access_token($oauth_verifier = NULL) {
		global $SESSION;
		
		$result = false;
	
		if($keys = socialink_linkedin_available()){
			if(isset($SESSION["socialink_linkedin"])){
				if($api = socialink_linkedin_get_api_object($keys)){
					// retrieve stored tokens
					$oauth_token = $SESSION['socialink_linkedin']['oauth_token'];
					$oauth_token_secret = $SESSION['socialink_linkedin']['oauth_token_secret'];
					$SESSION->offsetUnset('socialink_linkedin');
				
					// fetch an access token
					try {
						$response = $api->retrieveTokenAccess($oauth_token, $oauth_token_secret, $oauth_verifier);
						
						$result = socialink_linkedin_verify_response($response);
					} catch(Exception $e){}
				}
			} elseif($SESSION["socialink_token"]) {
				$result = $SESSION["socialink_token"];
			}
		}
		
		return $result;
	}
	
	function socialink_linkedin_authorize($user_guid = 0) {
		$result = false;
		
		if(empty($user_guid)){
			$user_guid = elgg_get_logged_in_user_guid();
		}
		
		$oauth_verifier = get_input('oauth_verifier', NULL);
		
		if(!empty($user_guid) && ($token = socialink_linkedin_get_access_token($oauth_verifier))){
			if (isset($token['oauth_token']) && isset($token['oauth_token_secret'])) {
				// only one user per tokens
				$params = array(
					"type" => "user",
					"limit" => false,
					"site_guids" => false,
					"plugin_id" => "socialink",
					"plugin_user_setting_name_value_pairs" => array(
						"linkedin_oauth_token" => $token["oauth_token"],
						"linkedin_oauth_secret" => $token["oauth_token_secret"],
					)
				);
				
				// find hidden users (just created)
				$access_status = access_get_show_hidden_status();
				access_show_hidden_entities(true);
				
				if ($users = elgg_get_entities_from_plugin_user_settings($params)) {
					foreach ($users as $user) {
						// revoke access
						elgg_unset_plugin_user_setting("linkedin_oauth_token", $user->getGUID(), "socialink");
						elgg_unset_plugin_user_setting("linkedin_oauth_secret", $user->getGUID(), "socialink");
					}
				}
				
				// restore hidden status
				access_show_hidden_entities($access_status);
				
				// register user"s access tokens
				elgg_set_plugin_user_setting("linkedin_oauth_token", $token["oauth_token"], $user_guid, "socialink");
				elgg_set_plugin_user_setting("linkedin_oauth_secret", $token["oauth_token_secret"], $user_guid, "socialink");
			
				$result = true;
			}
		}
		
		return $result;
	}
	
	function socialink_linkedin_remove_connection($user_guid = 0){
		$result = false;
		
		if(empty($user_guid)){
			$user_guid = elgg_get_logged_in_user_guid();
		}
		
		if(!empty($user_guid) && ($keys = socialink_linkedin_is_connected($user_guid))){
			if($api = socialink_linkedin_get_api_object($keys)){
				// remove LinkedIn subscription
				try {
					$api->revoke();
				} catch(Exception $e){}
			}
			
			// remove plugin settings
			elgg_unset_plugin_user_setting("linkedin_oauth_token", $user_guid, "socialink");
			elgg_unset_plugin_user_setting("linkedin_oauth_secret", $user_guid, "socialink");
			
			$result = true;
		}
		
		return $result;
	}

	function socialink_linkedin_post_message($message, $user_guid = 0){
		$result = false;
		
		if(empty($user_guid)){
			$user_guid = elgg_get_logged_in_user_guid();
		}
		
		if(!empty($message) && !empty($user_guid) && ($keys = socialink_linkedin_is_connected($user_guid))){
			if($api = socialink_linkedin_get_api_object($keys)){
				$api->setResponseFormat(LinkedIn::_RESPONSE_XML); // update network does not support JSON response
				try{
					$response = $api->updateNetwork($message);
					$result = socialink_linkedin_verify_response($response);
				} catch(Exception $e){ }
			}
		}
		
		return $result;
	}
	
	function socialink_linkedin_get_profile_information($user_guid = 0){
		$result = false;
		
		if(empty($user_guid)){
			$user_guid = elgg_get_logged_in_user_guid();
		}
		
		if(!empty($user_guid) && ($keys = socialink_linkedin_is_connected($user_guid))){
			if($api = socialink_linkedin_get_api_object($keys)){
				try {
					$response = $api->profile("~:(first-name,last-name,industry,public-profile-url,location:(name),picture-url)");
					
					if($result = socialink_linkedin_verify_response($response)){
						$temp = json_decode($result);
						$temp->socialink_name = ucwords($temp->firstName . " " . $temp->lastName);
						$result = json_encode($temp);
					}
				} catch(Exception $e){}
			}
		}
		
		return $result;
	}
	
	function socialink_linkedin_sync_profile_metadata($user_guid = 0){
		global $CONFIG;
		
		if(empty($user_guid)){
			$user_guid = elgg_get_logged_in_user_guid();
		}
		
		// can we get a user
		if(($user = get_user($user_guid)) && socialink_linkedin_is_connected($user_guid)){
			// does the user allow sync
			if(elgg_get_plugin_user_setting("linkedin_sync_allow", $user->getGUID(), "socialink") != "no"){
				// get configured fields and network fields
				if(($configured_fields = socialink_get_configured_network_fields("linkedin")) && ($network_fields = socialink_get_network_fields("linkedin"))){
					// ask the api for all fields
					if($api_result = socialink_linkedin_get_profile_information($user->getGUID())){
						$api_result = json_decode($api_result);
						
						// check settings for each field
						foreach($configured_fields as $setting_name => $profile_field){
							$setting = "linkedin_sync_" . $setting_name;
							
							if(elgg_get_plugin_user_setting($setting, $user->getGUID(), "socialink") != "no"){
								$api_setting = $network_fields[$setting_name];
								
								// get the correct value from api result
								if(stristr($api_setting, "->")){
									$temp_fields = explode("->", $api_setting);
									$temp_result = $api_result;
									
									for($i = 0; $i < count($temp_fields); $i++){
										$temp_result = $temp_result->$temp_fields[$i];
									}
								} else {
									$temp_result = $api_result->$api_setting;
								}
								
								// are we dealing with a tags profile field type
								if(!empty($CONFIG->profile) && is_array($CONFIG->profile)){
									if(array_key_exists($profile_field, $CONFIG->profile) && $CONFIG->profile[$profile_field] == "tags"){
										$temp_result = string_to_tag_array($temp_result);
									}
								}
								
								// check if the user has this metadata field, to get access id
								$params = array(
									"guid" => $user->getGUID(),
									"metadata_name" => $profile_field,
									"limit" => false
								);
								
								if($metadata = elgg_get_metadata($params)){
									if(is_array($metadata)){
										$access_id = $metadata[0]->access_id;
									} else {
										$access_id = $metadata->access_id;
									}
								} else {
									$access_id = get_default_access($user);
								}
								
								// remove metadata to set new values
								elgg_delete_metadata($params);
								
								// make new metadata field
								if(!empty($temp_result)){
									if(is_array($temp_result)){
										foreach($temp_result as $index => $temp_value){
											if($index > 0){
												$multiple = true;
											} else {
												$multiple = false;
											}
											
											create_metadata($user->getGUID(), $profile_field, $temp_value, 'text', $user->getGUID(), $access_id, $multiple);
										}
									} else {
										create_metadata($user->getGUID(), $profile_field, $temp_result, 'text', $user->getGUID(), $access_id);
									}
								}
							}
						}
					}
				}
				
				// sync profile icon, only if the user has no icon
				if(empty($user->icontime)){
					socialink_linkedin_sync_profile_icon($user->getGUID());
				}
			}
		}
	}
	
	function socialink_linkedin_sync_profile_icon($user_guid = 0){
		$result = false;
		
		if(empty($user_guid)){
			$user_guid = elgg_get_logged_in_user_guid();
		}
		
		if(($user = get_user($user_guid)) && socialink_linkedin_is_connected($user_guid)){
			if($api_result = socialink_linkedin_get_profile_information($user_guid)){
				$api_result = json_decode($api_result);
				
				if($icon_url = $api_result->pictureUrl){
					if(file_get_contents($icon_url)){
						$icon_sizes = elgg_get_config("icon_sizes");
						
						if(!empty($icon_sizes)){
							$fh = new ElggFile();
							$fh->owner_guid = $user->getGUID();
							
							foreach($icon_sizes as $name => $properties){
								$resize = get_resized_image_from_existing_file($icon_url, $properties["w"], $properties["h"], $properties["square"], 0, 0, 0, 0, $properties["upscale"]);
								
								if(!empty($resize)){
									$fh->setFilename("profile/" . $user->getGUID() . $name . ".jpg");
									$fh->open("write");
									$fh->write($resize);
									$fh->close();
									
									$result = true;
								}
							}
						}
						
						if(!empty($result)){
							$user->icontime = time();
							
							// trigger event to let others know the icon was updated
							elgg_trigger_event("profileiconupdate", $user->type, $user);
						}
					}
				}
			}
		}
		
		return $result;
	}
	
	function socialink_linkedin_create_user($token, $email){
		$result = false;
		
		if(!empty($token) && is_array($token) && !empty($email)){
			if(!get_user_by_email($email) && is_email_address($email)){
				$keys = socialink_linkedin_available();
				
				$keys["oauth_token"] = $token["oauth_token"];
				$keys["oauth_secret"] = $token["oauth_token_secret"];
				
				if($api = socialink_linkedin_get_api_object($keys)){
					try {
						$response = $api->profile("~:(first-name,last-name)");
					} catch(Exception $e){}
					
					if($api_result = socialink_linkedin_verify_response($response)){
						$api_result = json_decode($api_result);
						
						$name = $api_result->firstName . " " . $api_result->lastName;
						$pwd = generate_random_cleartext_password();
						
						$username = socialink_create_username_from_email($email);
						
						try {
							// register user
							if($user_guid = register_user($username, $pwd, $name, $email)){
								// show hidden entities
								$access = access_get_show_hidden_status();
								access_show_hidden_entities(TRUE);
								
								if($user = get_user($user_guid)){
									// save user tokens
									elgg_set_plugin_user_setting('linkedin_oauth_token', $token['oauth_token'], $user_guid, "socialink");
									elgg_set_plugin_user_setting('linkedin_oauth_secret', $token['oauth_token_secret'], $user_guid, "socialink");
									
									// sync user data
									socialink_linkedin_sync_profile_metadata($user->getGUID());
									
									// trigger hook for registration
									$params = array(
										"user" => $user,
										"password" => $pwd,
										"friend_guid" => 0,
										"invitecode" => ""
									);
									
									if(elgg_trigger_plugin_hook("register", "user", $params, true) !== false){
										// return the user
										$result = $user;
									}
								}
								
								// restore hidden entities
								access_show_hidden_entities($access);
							}
						} catch(Exception $e){}
					}
				}
			} else {
				register_error(elgg_echo("socialink:networks:create_user:error:email"));
			}
		}
		
		return $result;
	}
	
	function socialink_linkedin_validate_connection($user_guid = 0){
		global $CONFIG;
		$result = true;
		
		if(empty($user_guid)){
			$user_guid = elgg_get_logged_in_user_guid();
		}
		
		// can we get a user
		if($keys = socialink_linkedin_is_connected($user_guid)){
			if($api = socialink_linkedin_get_api_object($keys)){
				try {
					$response = $api->profile("~:(first-name,last-name)");
					$result = socialink_linkedin_verify_response($response);
				} catch(Exception $e){}
			}
		}
		return $result;		
	}
