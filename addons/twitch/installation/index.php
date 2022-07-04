<?php
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    $G_MAIN = true;

    session_start();

    if (file_exists('install.php')) {
        unlink('install.php');
    }

    include 'assets/php/utils.php';

    include 'assets/php/autoload.php';

    include 'config.php';

    date_default_timezone_set(Config::Get('timezone'));

    $db = new MysqliDb(array(
        'host' => Config::Get('mysql_server'),
        'username' => Config::Get('mysql_user'),
        'password' => Config::Get('mysql_pw'),
        'db' => Config::Get('mysql_db'),
        'port' => Config::Get('mysql_port'),
        'prefix' => 'gex_',
        'charset' => 'latin1',
    ));

    $addonManager = new AddonManager();

    $pagesManager = new FileManager('pages');
    $themeManager = new FileManager('themes');
    $languageManager = new LanguageManager('language');
    $mainManager = new FileManager('main');

    //Login Start -->
    if (!isset($_SESSION['login_redirect'])) {
        $_SESSION['login_redirect'] = '';
    }

    if (isset($_GET['login_started'])) {
        SteamAPI::FinishLogin();
    }

    if (!isset($_SESSION['gex_steamid64'])) {
        if (isset($_COOKIE['gex_loginsession'])) {
            $db->where('session', $_COOKIE['gex_loginsession']);
            $db->where('expires > NOW()');

            $steamid64 = $db->getValue('sessions', 'steamid64');

            if ($db->count) {
                $db->where('session', $_COOKIE['gex_loginsession']);
                $db->update('sessions', array('expires' => $db->func('DATE_ADD(NOW(), INTERVAL ? DAY)', array(30))));

                $_SESSION['gex_steamid64'] = $steamid64;

                setcookie('gex_loginsession', $_COOKIE['gex_loginsession'], time() + 2592000, '/');
            }
        }
    } elseif (isset($_GET['login_started'])) {
        $steamprofile = SteamAPI::GetUserData($_SESSION['gex_steamid64']);

        $steamid32 = $steamprofile['steamid32'];
        $nick = $steamprofile['personaname'];
        $avatar_small = $steamprofile['avatar'];
        $avatar_medium = $steamprofile['avatarmedium'];
        $avatar_large = $steamprofile['avatarfull'];

        $db->where('steamid64', $_SESSION['gex_steamid64']);
        $userdata = $db->getOne('users');

        $ip = GetClientIP();
        $ips = array($ip);

        if ($db->count) {
            $sessionkey = hash('sha512', random_string(25).$userdata['random']); //(hash("sha256", md5('Ew97f9N3vq4' . $_SESSION['gex_steamid64'] . '86V' . $result['email'] . 'b93z7' . $_SERVER['HTTP_USER_AGENT'] . '4E$HSJH8gjsA' . $result['random'] . '3oikjfA')));

            setcookie('gex_loginsession', $sessionkey, time() + 2592000, '/');

            if (!$ip) {
                die('Invalid IP');
            }

            $ips = FromJson($userdata['ips']);

            if (!in_array($ip, $ips)) {
                array_push($ips, $ip);
            }

            if (!Settings::Get('settings_privacy_collect_ips')) {
                $ips = array();
            }

            $data = array();

            if ($steamprofile) {
                $data = array(
                    'nick' => $nick,
                    'avatar_small' => $avatar_small,
                    'avatar_medium' => $avatar_medium,
                    'avatar_large' => $avatar_large,
                );
            }

            $ipinfo = GetIPInfo();

            if($ipinfo !== null && $ipinfo['country_code']){
                $data['country_code'] = $ipinfo['country_code'];
            }

            $data['ips'] = ToJson($ips);

            $db->where('steamid64', $_SESSION['gex_steamid64']);
            $db->update('users', $data);

            $data = array(
                'session' => $sessionkey,
                'ip' => $ip,
                'steamid64' => $_SESSION['gex_steamid64'],
                'useragent' => $_SERVER['HTTP_USER_AGENT'],
                'expires' => $db->func('DATE_ADD(NOW(), INTERVAL ? DAY)', array(30)),
            );

            $db->onDuplicate(array('session'), 'id');
            $db->insert('sessions', $data);

            if (!tempty($_SESSION['login_redirect'])) {
                header('Location: '.$_SESSION['login_redirect']);
            }
        } elseif ($steamprofile) {
            User::Create($_SESSION['gex_steamid64']);

            if (Settings::Get('settings_demo')) {
                Notifications::Send($_SESSION['gex_steamid64'], 'demo_console', array(), 'index.php?t=admin_console', 'terminal');
                Notifications::Send($_SESSION['gex_steamid64'], 'demo_settings', array(), 'index.php?t=admin_settings', 'cogs');
                Notifications::Send($_SESSION['gex_steamid64'], 'demo_bans', array(), 'index.php?t=admin_bans', 'ban');
                Notifications::Send($_SESSION['gex_steamid64'], 'demo_donations_statistics', array(), 'index.php?t=admin_donations&part=statistics', 'line-chart');
            }

            $_SESSION['gex_firstlogin'] = true;

            if (!tempty($_SESSION['login_redirect'])) {
                header('Location: '.$_SESSION['login_redirect']);
            }
        } else {
            $login_messages[] = '<i style="color:red;" class="fa fa-times-circle-o"></i> SteamAPI Error';
            DirectNotifications::Queue('title: "'.Lang('error').'", text: "SteamAPI Error", type: "error"');
        }
    }

    $_SESSION['login_redirect'] = '';

    // <-- Login End

    require __DIR__.'/vendor/autoload.php';

    ini_set('file_uploads', 'On');

    include 'auth.php';

    $languageManager->Load();

    $versionManager = new VersionManager();
    $currentPage = GetCurrentPage($pagesManager->files);

    $_SESSION['last_page'] = $currentPage['rawname'];

    if (!empty($_POST)) {
        $valid = CheckCSRFToken();

        if (!$valid) {
            $_POST = array();
        }
    }

    if (!DemoLock() && $auth_user) {
        if (Permissions::HasPagePermission('admin_settings') && Permissions::HasPermission('settings_update') && 'admin_settings' == $currentPage['rawname'] && isset($_GET['part'])) {
            if ('update' == $_GET['part']) {
                if (isset($_GET['finish'])) {
                    if (!empty($_GET['finish'])) {
                        $product = new Product($_GET['finish']);

                        if ($product->valid) {
                            $product->FinishUpdate();

                            DirectNotifications::Queue('title: "'.Lang('success').'", text: "'.Lang('update_success').'", type: "success"');

                            echo '<i class="fa fa-refresh fa-spin fa-fw"></i> '.Lang('updating').'...';
                            Redirect('index.php?t=admin_settings&part=update');
                        }
                    }
                }
            }
        }
    }

    if ($auth_user && isset($_POST['main_modal_settings_form_submit'])) {
        if (strlen($_POST['main_modal_settings_form_language']) <= 5) {
            $error = false;

            $checkboxes = array('main_modal_settings_form_emailnotifications');

            foreach ($checkboxes as $checkbox) {
                if (!array_key_exists($checkbox, $_POST)) {
                    $_POST[$checkbox] = 0;
                } else {
                    $_POST[$checkbox] = 1;
                }
            }

            if (isset($_POST['main_modal_settings_form_ts3uid'])) {
                if (!tempty($auth_user->GetValue('ts3uid'))) {
                    if ($auth_user->GetValue('ts3uid') != $_POST['main_modal_settings_form_ts3uid']) {
                        $auth_user->RemoveTs3Servergroups();
                    }
                }

                if (!$auth_user->SetValue('ts3uid', htmlspecialchars($_POST['main_modal_settings_form_ts3uid']))) {
                    $error = true;
                }

                if (!tempty($auth_user->GetValue('ts3uid'))) {
                    $auth_user->AddTs3Servergroups();
                }
            }

            if (isset($_POST['main_modal_settings_form_discord_username'])) {
                $username = $_POST['main_modal_settings_form_discord_username'];
                $username_exp = explode('#', $username);

                if ($username != $auth_user->GetValue('discord_username')) {
                    if (!empty($username) && (2 != sizeof($username_exp) || !is_numeric($username_exp[1]))) {
                        $error = true;
                    } else {
                        $update_result = $auth_user->UpdateDiscordUsername($username);
                        if (true !== $update_result) {
                            DirectNotifications::Queue('title: "'.Lang('error').'", text: "Discord error:<br/>'.$update_result.'", type: "error"');
                            $error = true;
                        }
                    }
                }
            }

            if (isset($_POST['main_modal_settings_form_language'])) {
                if (!$auth_user->SetValue('language', htmlspecialchars($_POST['main_modal_settings_form_language']))) {
                    $error = true;
                }
            }

            if (isset($_POST['main_modal_settings_form_email'])) {
                if (!$auth_user->SetValue('email', htmlspecialchars($_POST['main_modal_settings_form_email']))) {
                    $error = true;
                }
            }

            if (isset($_POST['main_modal_settings_form_emailnotifications'])) {
                if (!$auth_user->SetValue('emailnotifications', htmlspecialchars($_POST['main_modal_settings_form_emailnotifications']))) {
                    $error = true;
                }
            }

            if (!$error) {
                DirectNotifications::Queue('title: "'.Lang('success').'", text: "'.Lang('changes_saved').'", type: "success"');
                header('Location: index.php?t='.$currentPage['rawname']);
                die();
            } else {
                DirectNotifications::Queue('title: "'.Lang('error').'", text: "'.Lang('sqlerror_update').'", type: "error"');
            }
        }
    }

    if ($auth_user && !empty($_GET['notification_clicked'])) {
        Notifications::Devaluate($_GET['notification_clicked']);
    }
?>

<!--
	//////////////////////////////////////
    //	     GExtension (c) 2019  	 	//
    //									//
    // Created by Jakob 'ibot3' MÃ¼ller  //
    //									//
    //  You are not permitted to share, //
    //   	trade, give away, sell 		//
    //      or otherwise distribute 	//
    //////////////////////////////////////
-->

<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">

		<title><?php echo Lang($currentPage['data']['language']).' - '.(tempty(Settings::Get('settings_general_community_name')) ? 'GMOD Web' : Settings::Get('settings_general_community_name')); ?></title>

		<meta name="description" content="<?php echo Settings::Get('settings_general_description'); ?>">
		<meta name="author" content="Jakob 'ibot3' MÃ¼ller">

		<!-- Botstrap CSS -->
		<link rel="stylesheet" href="assets/css/bootstrap.min.css" />
		<!-- FontAwesome CSS -->
		<link rel="stylesheet" href="assets/css/font-awesome.min.css" />
		<!-- PNotify CSS -->
		<link rel="stylesheet" href="assets/css/pnotify.custom.min.css" media="all" />
		<!-- Panel_Box CSS -->
		<link rel="stylesheet" href="assets/css/panel_box.css" />
		<!-- Navbar CSS -->
		<link rel="stylesheet" href="assets/css/main_navbar.css" />
		<!-- Chat CSS -->
		<link rel="stylesheet" href="assets/css/chat.css" />
		<!-- touchspin CSS -->
		<link rel="stylesheet" href="assets/css/jquery.bootstrap-touchspin.css" />
		<!-- Other CSS -->
		<link rel="stylesheet" href="assets/css/main.css" />
		<!-- JQueryUI CSS -->
		<link rel="stylesheet" href="assets/css/jquery-ui.css" />

		<!-- Theme CSS -->
		<?php
            $theme = GetSelectedTheme();

            if ($theme) {
                foreach ($theme['files'] as $file) {
                    echo '<link rel="stylesheet" href="'.$file.'?color='.str_replace('#', '', Settings::Get('settings_design_color')).'">';
                }
            }
        ?>

		<!-- Page CSS -->
		<?php
            if (file_exists('assets/css/pages/'.$currentPage['rawname'].'.css')) {
                echo '<link rel="stylesheet" href="assets/css/pages/'.$currentPage['rawname'].'.css">';
            }
        ?>

		<!-- Custom CSS -->
		<style>
			<?php
                echo Settings::Get('settings_design_css');
            ?>
		</style>


		<!--Custom Design-->
		<?php
            if (Settings::Get('settings_design_square')) {
                echo '
					<style>
						.btn{
						    border-radius: 0;
						}

						.form-control{
						    border-radius: 0;
						}

						html * {
						    border-radius: 0px !important;
						}
					</style>
				';
            }
        ?>

		<!-- Favicon -->
		<link rel="shortcut icon" href="assets/img/font-awesome/<?php echo $currentPage['data']['icon']; ?>.ico" type="image/x-icon">
	</head>

	<body>
		<!-- JQuery JS-->
		<script src="assets/js/jquery.min.js"></script>
		<!-- JQueryUI JS-->
		<script src="assets/js/jquery-ui.min.js"></script>
		<!-- Bootstrap JS-->
		<script src="assets/js/bootstrap.min.js"></script>
		<!-- Bootstrap TouchSpin JS -->
		<script src="assets/js/jquery.bootstrap-touchspin.js"></script>
        <!-- Material Kit JS -->
		<script src="assets/js/material.min.js"></script>
		<script src="assets/js/material-kit.js"></script>
		<!-- PNotify JS -->
		<script src="assets/js/pnotify.custom.min.js"></script>
		<!-- Moment JS -->
        <script src="assets/js/moment.js"></script>
		<!-- Utils JS -->
		<script src="assets/js/utils.js"></script>
		
		<!-- Snow -->
		<?php
           if (Settings::Get('settings_design_snow')) {
               echo '<script src="assets/js/let-it-snow.js"></script>';
           }
        ?>
		
		<script>
			var steamid64 = '<?php echo $auth_user ? $auth_user->GetValue('steamid64') : 'guest'; ?>';
			var main_allowResubmit = false;
			var csrf = '<?php echo GenerateCSRFToken(); ?>';
		</script>

		<script>
			if(steamid64 != 'guest'){
				PNotify.desktop.permission();
			}
		</script>

		<?php
            DirectNotifications::ExecuteAll();
        ?>

		<?php if (Settings::Get('settings_general_shoutbox')) {
            ?>
        <div class="no-print chatpanel box">
            <div class="box-header">
            	<div class="box-title pull-left">
		         	<p><i class="fa fa-comment fa-lg"></i></p>
		          	<p><b><?php echo Lang('shoutbox'); ?></b></p>
		        </div>

		        <div class="box-icon pull-right">
		         	<a style="height:40px;" id="main_chat_button_collapse" type="button" class="btn btn-link btn-xs" data-toggle="collapse" onclick="$('.rotate').toggleClass('down'); main_chat_load();" href="#main_chat_collapse">
                         <i class="rotate fa fa-angle-up fa-lg"></i>
                    </a>
		        </div>
            </div>
      		<div class="panel-collapse collapse box-content" style="padding:0px;" id="main_chat_collapse">
                <div class="panel-body">
                    <ul class="chat" id="main_chat_content">
                		<i class="fa fa-spinner fa-pulse fa-lg"></i> <?php echo Lang('loading'); ?>...
                    </ul>
                </div>
                <div class="panel-footer" id="main_chat_footer">
                    <div class="input-group">
                        <input id="main_chat_message" type="text" class="form-control input-sm" placeholder="<?php echo Lang('type_your_message_here'); ?>..." />

                        <span class="input-group-btn">
                            <button class="btn btn-default btn-sm" onclick="main_chat_entry_add();" id="main_chat_submit">
                                <?php echo Lang('send'); ?>
                            </button>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <?php
        } ?>

		<?php
            $highlighted = false;
            $navbar_entries = array('main' => array(), 'help' => array(), 'admin' => array(), 'user' => array());

            foreach (ExternalUrls::GetAll() as $exturl) {
                $exturl['class'] = '';
                $exturl['target'] = ($exturl['newtab'] ? '_blank' : '');
                $navbar_entries[$exturl['place']][] = $exturl;
            }
        ?>

		<script type="text/javascript">
			if($('#main_navbar_main').offset().left + $('#main_navbar_main').width() > $('#main_navbar_help').offset().left){
				$('.main_navbar_main_text').hide();
			}
		</script>


		<div class="container">
			<?php
                if (null == $auth_user) {
                    if (isset($currentPage['data']['nologin']) && Permissions::HasPagePermission($currentPage['rawname'], new Group(Settings::Get('settings_general_defaultgroup')))) {
                        foreach ($currentPage['files'] as $_file) {
                            if (file_exists($_file)) {
                                include $_file;
                            }
                        }
                    } else {
                        ?>
                        	<br/><br/><br/>
                        	<div class="row">
                        		<div class="col-md-4 col-md-offset-4 col-sm-6 col-sm-offset-3">
                        			<div class="panel panel-danger">
										<div class="panel-heading">
											<i class="fa fa-sign-in"></i> <?php echo Lang('sign_in_please'); ?>
										</div>

										<div class="panel-body text-center">
											<?php echo Lang('login_text'); ?>
										</div>
									</div>
                        		</div>
                        	</div>

							<br/><br/>
						<?php
                    }
                } else {
                    if ($auth_user->IsBanned(0) && !Permissions::HasPermission('super')) {
                        DirectNotifications::Execute('title: "'.Lang('ban_message').'", text: "'.Lang('banned_web').'", type: "error", hide:false, buttons:{sticker:false}');
                        include 'pages/violations.php';
                    } else {
                        if (Permissions::HasPagePermission($currentPage['rawname'], $auth_user) || (isset($currentPage['data']['nologin']) && $currentPage['data']['nologin'] && Permissions::HasPagePermission($currentPage['rawname'], new Group(Settings::Get('settings_general_defaultgroup'))))) {
                            if ($auth_user->IsBanned()) {
                                DirectNotifications::Execute('title: "'.Lang('ban_message').'", text: "'.Lang('banned_other').'", type: "error", hide:false, buttons:{sticker:false}, confirm:{confirm:true, buttons:[{text:"'.Lang('details').'", click: function(){ window.location.replace(`index.php?t=violations`) } }, null]}');
                            }

                            foreach ($currentPage['files'] as $_file) {
                                if (file_exists($_file)) {
                                    include $_file;
                                }
                            }
                        } else {
                            DirectNotifications::Execute('title: "'.Lang('error').'", text: "'.Lang('permerror').'", type: "warning"');
                            echo '<br>'.Lang('permerror');
                        }
                    }
                }
            ?>
		</div>

		<?php if ($auth_user) {
                ?>
		<!-- Settings Modal -->
		<div class="modal fade" id="main_modal_settings" tabindex="-1" role="dialog" aria-hidden="true">
			<div class="modal-dialog modal-sm">
				<div class="modal-content">
					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true"><i class="fa fa-times"></i></span></button>
						<h4 class="modal-title"><i class="fa fa-cogs"></i> &nbsp;<?php echo Lang('settings'); ?></h4>
					</div>
					<form method="post" action="index.php?t=<?php echo $currentPage['rawname']; ?>">
						<div class="modal-body">
							<!-- Language -->
				            <label><?php echo Lang('lang'); ?></label><br>
				            <select name="main_modal_settings_form_language" class="form-control">
					            <?php
                                    foreach ($languageManager->GetLanguages() as $lang_name) {
                                        echo '<option value="'.$lang_name.'">'.$LANGUAGES[$lang_name]['name'].' ('.$LANGUAGES[$lang_name]['nativeName'].')</option>';
                                    } ?>
				            </select>
				            <br />

				            <label><?php echo Lang('email'); ?></label><br>
				            <input type="text" name="main_modal_settings_form_email" class="form-control" />
				            <br />

				            <label><?php echo Lang('emailnotifications'); ?></label><br>
				            <?php CreateCheckBox('primary', 'main_modal_settings_form_emailnotifications', '', Lang('emailnotifications_receive')); ?>
				            <br />

							<!-- TS3 UID -->
							<?php if (!tempty(Settings::Get('settings_teamspeak_query_password'))) {
                                        ?>
				            <label><?php echo Lang('ts3uid'); ?></label><span class="pull-right"><?php CreateTooltip(Lang('ts3uid_definition'), 'left'); ?></span><br>
				            <input type="text" class="form-control" name="main_modal_settings_form_ts3uid" />
				            <br />
				            <?php
                                    } ?>
                                    
                            <!-- Discord Username -->
							<?php if (!tempty(Settings::Get('settings_discord_bot_token'))) {
                                        ?>
				            <label><?php echo Lang('discord_username_with_id'); ?></label><br>
				            <input type="text" class="form-control" name="main_modal_settings_form_discord_username" placeholder="username#1234" />
				            <?php
                                    } ?>
						</div>
						<div class="modal-footer">
							<button type="submit" name="main_modal_settings_form_submit" id="main_modal_settings_form_submit" class="btn btn-primary"><?php echo Lang('save'); ?></button>
						</div>
					</form>
				</div>
			</div>
		</div>
		
		<!-- Reg Modal -->
		<div class="modal fade" id="main_modal_registration_accept" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
			<div class="modal-dialog">
				<div class="modal-content">
				    <div class="modal-header">
						<h4 class="modal-title"><i class="fa fa-check"></i> &nbsp;<?php echo Lang('accept'); ?></h4>
					</div>
				
					<div class="modal-body">
			            <?php CreateCheckBox('primary', 'main_modal_registration_accept_tos', '', Lang('tos_accept'), '', '', false, false); ?>
			            <?php CreateCheckBox('primary', 'main_modal_registration_accept_privacy_policy', '', Lang('accept_privacy_policy'), '', '', false, false); ?>

					</div>
					<div class="modal-footer">
						<button onclick="$.post('request.php?t=registration_accept'); $('#main_modal_registration_accept').modal('hide');" type="button" id="main_modal_registration_accept_form_submit" class="btn btn-success" disabled><?php echo Lang('save'); ?></button>
					</div>
				</div>
			</div>
			
			<script>
		        $('#main_modal_registration_accept_tos, #main_modal_registration_accept_privacy_policy').on('change', function(){
		            if($('#main_modal_registration_accept_tos').prop('checked') && $('#main_modal_registration_accept_privacy_policy').prop('checked')){
		                $('#main_modal_registration_accept_form_submit').prop('disabled', false);
		            }else{
		                $('#main_modal_registration_accept_form_submit').prop('disabled', true);
		            }
		        })
		    </script>
		</div>

		<script>
			//Settings -->
			$('select[name="main_modal_settings_form_language"]').val("<?php echo OneLine(($auth_user->GetValue('language') ? $auth_user->GetValue('language') : Settings::Get('settings_general_defaultlanguage'))); ?>");
			$('input[name="main_modal_settings_form_ts3uid"]').val("<?php echo OneLine($auth_user->GetValue('ts3uid')); ?>");
			$('input[name="main_modal_settings_form_email"]').val("<?php echo OneLine($auth_user->GetValue('email')); ?>");
			$('input[name="main_modal_settings_form_discord_username"]').val("<?php echo OneLine($auth_user->GetValue('discord_username')); ?>");
			$('input[name="main_modal_settings_form_emailnotifications"]').prop('checked', <?php echo OneLine($auth_user->GetValue('emailnotifications')); ?>);
			// <-- Settings
		</script>

		<?php
            } ?>

		<div class="container no-print" id="main_footer">
			<div class="row">
				<hr>
			</div>
			<div class="row">
				<div class="col-sm-2 col-xs-6" id="main_footer_left">
					<?php
                        $footerLinks = array();

                        if (!tempty(Settings::Get('settings_general_about'))) {
                            $footerLinks[] = '<a target="_blank" href="?t=about">'.Lang('about').'</a>';
                        }

                        if (!tempty(Settings::Get('settings_general_tos'))) {
                            $footerLinks[] = '<a target="_blank" href="?t=tos">'.Lang('tos').'</a>';
                        }

                        if (!tempty(Settings::Get('settings_privacy_policy'))) {
                            $footerLinks[] = '<a target="_blank" href="?t=privacy_policy">'.Lang('privacy_policy').'</a>';
                        }

                        echo implode('<br/>', $footerLinks);
                    ?>
				</div>
				<div class="col-sm-8 hidden-xs text-center" id="main_footer_center">
					<?php
                        if (Settings::Get('settings_donations_footerlogos')) {
                            $first = true;

                            foreach (Gateway::GetAll() as $gateway) {
                                if ($gateway->GetValue('enabled') && 'coupon' != $gateway->GetValue('name')) {
                                    if (file_exists('assets/img/gateways/'.$gateway->GetValue('name').'.png')) {
                                        if (!$first) {
                                            echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
                                        } else {
                                            $first = false;
                                        }

                                        echo '<img style="height: 30px;" src="assets/img/gateways/'.$gateway->GetValue('name').'.png" />';
                                    }
                                }
                            }
                        }
                    ?>
				</div>
				<div class="col-sm-2 col-xs-6 text-right" id="main_footer_right">
					<div class="pull-right" style="text-align: right;">
						<span id="main_brand">
							GExtension by <a target="_blank" href="http://steamcommunity.com/id/ibot3/">ibot3</a>
							<br>
						</span>
					</div>
				</div>
			</div>
		</div>

		<br>

		<script>
			console.log("GExtension by Jakob 'ibot3' MÃ¼ller");
			console.log("Version: <?php echo $versionManager->version; ?>");
			console.log("2019");
			console.log("");

			//Notifications -->
			var notifications = {};
			var nonot = true;

			function main_notification_icons_adjust(){
				setTimeout(function(){
					$('#main_notifications').find('li').each(function(){
			            var current = $(this);

			            var icon = $($($(current.children()[0]).children()[0]).children()[0]);

			            var height = $(current.children()[0]).height();

			            var newpadding = (height-icon.height())/2 - 1;

						if( newpadding > icon.css("padding-top").substr(0, icon.css("padding-top").length - 2) ){

							icon.css("padding-top", newpadding);
						}
			        });
				}, 10)
			}

			/*function main_notification_devaluate(id){
				$.ajax({
					type: 'POST',
					url: 'request.php?t=main_notifications',
					data: {
		                id: id
					},
				});
			}*/

			function main_notifications_icon_adjust(count){
				if(count != 0){
					$('#main_notifications_bell').removeClass("fa-bell-o");
					$('#main_notifications_bell').addClass("fa-bell");
					$('#main_notifications_badge').html(count);
					$('#main_notifications_badge').show();
					return true;
				}else{
					$('#main_notifications_bell').removeClass("fa-bell");
					$('#main_notifications_bell').addClass("fa-bell-o");
					$('#main_notifications').html('<br><i style="color: black;"><?php echo Lang('notifications_no_notifications'); ?></i>');
					$('#main_notifications_badge').html('');
					$('#main_notifications_badge').hide();
					return false;
				}
			}

			var main_notifications_cooldown  = false;

			function main_notifications_refresh(){
				$.getJSON('request.php?t=main_notifications', function(data) {
					SaveInStorage('notifications_count', data['count']);

					if(main_notifications_icon_adjust(data['count'])){
						if(nonot){
							$('#main_notifications').html('');
						}

						data['notifications'].forEach(function(notification){
							if(!notifications.hasOwnProperty(notification['id'])){
								$('#main_notifications').prepend(notification['html']);

								notifications[notification['id']] = true;

								if(!notification['seen'] && !main_notifications_cooldown){
									main_notifications_cooldown = true;

									setTimeout(function(){
										main_notifications_cooldown = false;
									}, 3000)

									PlaySound('assets/sounds/notification.mp3');

									(new PNotify({
									    title: '<?php echo Lang('new_notification'); ?>',
									    text: notification['text'],
									    desktop: {
									        desktop: true,
									        icon: 'assets/img/gextension_logo.png'
									    }
									})).get().click(function(e) {
									    if ($('.ui-pnotify-closer, .ui-pnotify-sticker, .ui-pnotify-closer *, .ui-pnotify-sticker *').is(e.target)) return;

									    if(notification['url']){
									    	window.location.replace(notification['url'] + '&notification_clicked=' + notification['id']);
									    }
									});
								}
							}
						});

						nonot = false;
					}else{
						nonot = true;
					}

					setTimeout(function(){
						main_notification_icons_adjust();
					}, 100);
				});
			}

			function main_notifications_load(){
				var count_saved = LoadFromStorage('notifications_count');

				if($.isNumeric(count_saved)){
					main_notifications_icon_adjust(count_saved);
				}

				main_notifications_refresh();

				setInterval(main_notifications_refresh, 30000);
			}

			//<-- Notifications

			//Chat -->

			var chat_loaded = false;

			function main_chat_entry_delete(entryid){
				$.ajax({
					type: 'POST',
					url: 'request.php?t=main_chat',
					data: {
		                entryid: entryid
					},
					success: function(){
						main_chat_refresh();
					}
				});
			}

			function main_chat_entry_add(){
				var message = $('#main_chat_message').val();

				$('#main_chat_message').val('');

				$.ajax({
					type: 'POST',
					url: 'request.php?t=main_chat',
					data: {
		                steamid64: steamid64,
						message: message
					},
					success: function(){
						main_chat_refresh();
					}
				});
			}

			$('#main_chat_message').keyup(function(event){
				if(event.keyCode == 13){
					main_chat_entry_add();
				}
			});

			function main_chat_entry_create(id, image, text, nick, time, tag, steamid, color, modperm){
				var chatentry = '<li class="left clearfix"><span class="chat-img pull-left"><img src="'+image+'" alt="Avatar" class="img-circle" /></span><div class="chat-body clearfix"><div class="header"><strong class="primary-font"><a target="_blank" href="index.php?t=user&id='+steamid+'">'+ tag + '<font color="'+color+'">' +  nick+'</font></a></strong> <small class="pull-right text-muted"><i class="fa fa-clock-o"></i> '+time;

				if(modperm){
					chatentry = chatentry + ' <a href="#" onclick="main_chat_entry_delete(' + id + ')"><i class="fa fa-times"></i></a>';
				}

				chatentry = chatentry +  '</small></div><p>'+text+'</p></div></li>';

				return chatentry;
			}

			function main_chat_refresh(){
				chat_loaded = true;

				$.getJSON('request.php?t=main_chat', function(data) {
					$('#main_chat_content').html('');
					data.forEach(function(entry){
						var tag = '';

						if(entry['donator']){
							tag = '<font color="#16AA00">[Donator] </font>';
						}

						$("#main_chat_content").prepend($(main_chat_entry_create(entry['id'], entry['image'], entry['message'], entry['nick'], moment(entry['time']).fromNow(), tag, entry['steamid64'], entry['groupcolor'], entry['modperm'])));

						SaveInStorage('chat_content', $('#main_chat_content').html());
					});
				});
			}

			function main_chat_load(){
				if(!chat_loaded){
					main_chat_refresh();
					setInterval(main_chat_refresh, 3000);
				}

				SaveInStorage('chat_content', $('#main_chat_content').html());

				setTimeout(function(){
					SaveInStorage('chat_state', isCollapsed('#main_chat_collapse'));
				}, 1000);
			}

			if(LoadFromStorage('chat_state') === 'true'){
				$('#main_chat_content').html( LoadFromStorage('chat_content'));
				$('#main_chat_button_collapse').click();
				main_chat_entry_delete();
			}

			//<-- Chat

			function main_load(){
				// Init Material scripts for buttons ripples, inputs animations etc, more info on the next link https://github.com/FezVrasta/bootstrap-material-design#materialjs
				$.material.init();

				//  Activate the Tooltips
				$('[data-toggle="tooltip"], [rel="tooltip"]').tooltip();

				// Activate Datepicker
				if($('.datepicker').length != 0){
				    $('.datepicker').datepicker({
				         weekStart:1,
				         format: "yyyy-mm-dd"
				    });
				}

				// Check if we have the class "navbar-color-on-scroll" then add the function to remove the class "navbar-transparent" so it will transform to a plain color.
				if($('.navbar-color-on-scroll').length != 0){
				    $(window).on('scroll', materialKit.checkScrollForTransparentNavbar)
				}

				// Activate Popovers
				$('[data-toggle="popover"]').popover();

				// Active Carousel
				$('.carousel').carousel({
				  interval: 400000
				});

				$('.modal-footer button').each(function(key, value){
					var btn = $(value);

					if(!btn.hasClass('btn-simple')){
						btn.addClass('btn-simple');
					}
				});
				
				csrf_bind();
			}

			//Rewards + E-Mail Queue -->
				<?php
                    if (!empty($_POST)) {
                        echo "$.get('request.php?t=main');";
                    } else {
                        ?>

                var seconds = new Date().getTime() / 1000;
				var mgtime = sessionStorage.getItem("_mgtime");
				if(mgtime == null || seconds - mgtime > 300){
					$.get('request.php?t=main');
					sessionStorage.setItem("_mgtime", seconds );
				}

				<?php
                    } ?>
			// <-- Rewards + E-Mail Queue

			//CSRF -->
			var submitting = false
		
		    function csrf_handle(e){
				if(!submitting){
					submitting = true;

				    $(this).append('<input type="hidden" name="__token" value="' + window.csrf + '">');
				    
				    if(window.main_allowResubmit){
				        $(this).append('<input type="hidden" name="__resubmit" value="' + window.main_allowResubmit + '">');
				    }
				}
		    }
		
		    function csrf_bind(){
		        $('form').off('submit', csrf_handle);
				$("form").on('submit', csrf_handle);
		    }
		    
			// <-- CSRF

			//First Login
			<?php
                if (isset($_SESSION['gex_firstlogin'])) {
                    unset($_SESSION['gex_firstlogin']);
                    echo "$('#main_modal_settings').modal('show');";
                }
            ?>
            
            //Load -->
				$(document).ready(function(){
					main_load();

					/*UpdateURL(window.location.href.replace(/index.php/g, ""));*/
				});
			// <-- Load

			//Logged IN
			<?php if ($auth_user) {
                ?>
				main_notifications_load();
				
				<?php 
                if (Settings::Get('settings_privacy_enforce_policy') && !$auth_user->GetValue('accepted') && !in_array($currentPage['rawname'], array('privacy_policy', 'tos', 'about'))) {
                    ?>
                    
                    $('#main_modal_registration_accept').modal('show');
                    
                <?php
                } ?>
			<?php
            } else {
                ?>
				$('#main_chat_footer').hide();
			<?php
            } ?>

        </script>

        <!-- Addon content for "main" -->
		<?php
            foreach ($mainManager->files as $file) {
                foreach ($file['files'] as $_file) {
                    if (file_exists($_file)) {
                        include $_file;
                    }
                }
            }
        ?>
	</body>
</html>