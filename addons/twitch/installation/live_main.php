<script>
	function Twitch(login) {
		var xhr = new XMLHttpRequest()
		xhr.open("GET", "https://api.twitch.tv/helix/streams?user_login="+login, true)
		xhr.setRequestHeader("Client-ID","gp762nuuoqcoxypju8c569th9wz7q5");
        xhr.setRequestHeader("Authorization"," Bearer j70jprw37veyeri3iljuae09due4i5");
		xhr.onreadystatechange = function () {
			if(xhr.readyState == 4) {
					var info = JSON.parse(xhr.responseText);
                    var icon = document.getElementById("live_icon"+login) 
                    //console.log(xhr.responseText)
					if(xhr.responseText === '{"data":[],"pagination":{}}' ){
                        // Pas en ligne
					}
					else {
                        // En ligne
						icon.className = "fa fa-circle";
                        icon.style.color = "red"

                        GameName(login, info.data[0].game_id, info.data[0].viewer_count)
					}
				}
			}
					
		xhr.send();
    }

    function GameName(login, gameid, numviewer) {
		var xhr = new XMLHttpRequest()
		xhr.open("GET", "https://api.twitch.tv/helix/games?id="+gameid, true)
		xhr.setRequestHeader("Client-ID","gp762nuuoqcoxypju8c569th9wz7q5");
        xhr.setRequestHeader("Authorization"," Bearer j70jprw37veyeri3iljuae09due4i5");
		xhr.onreadystatechange = function () {
            var viewer = document.getElementById("NumberViewer"+login)
			if(xhr.readyState == 4) {
                    var game = JSON.parse(xhr.responseText);
                    viewer.innerHTML = game.data[0].name+' '+numviewer
                    
			}
        }  
        xhr.send();     			
        
    }
</script>


<body>
    <div id="gex_navbar">
			<div style="" class="navbar navbar-fixed-top">
				<div class="container">
					<div class="navbar-header">
						<a class="navbar-brand" href="index.php">
							<img class="img-responsive" src="<?php echo file_exists('assets/img/banner_custom.png') ? 'assets/img/banner_custom.png' : 'assets/img/banner_default.png'; ?>">
						</a>
						<button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
							<i class="fa fa-bars fa-lg"></i>
						</button>
					</div>
					<div class="collapse navbar-collapse">
						<ul class="nav navbar-nav" id="main_navbar_main" >
							<?php
                                $navbar_entries_main = array();

                                $highlight_connect = array(
                                    array('connect' => 'bans', 'with' => 'home'),
                                    array('connect' => 'user', 'with' => 'search'),
                                    array('connect' => 'purchase', 'with' => 'donate'),
                                );

                                foreach ($pagesManager->files as $page) {
                                    if ($page['data']['place'] == 'main') {
                                        $addition = '';
                                        if ($currentPage['rawname'] == $page['rawname']) {
                                            $addition = 'active';
                                        } else {
                                            foreach ($highlight_connect as $connect) {
                                                if ($connect['connect'] == $currentPage['rawname']) {
                                                    if ($page['rawname'] == $connect['with']) {
                                                        $addition = 'active';
                                                    }
                                                }
                                            }
                                        }

                                        if ('active' == $addition) {
                                            $highlighted = true;
                                        }

                                        $navbar_entries['main'][] = array('title' => Lang($page['data']['language']), 'icon' => $page['data']['icon'], 'position' => $page['data']['position'], 'url' => 'index.php?t='.$page['rawname'], 'class' => $addition, 'target' => '');
                                    }
                                }

                                array_multisort(array_column($navbar_entries['main'], 'position'), $navbar_entries['main']);

                                foreach ($navbar_entries['main'] as $entry) {
                                    echo '<li class="'.$entry['class'].'"><a target="'.$entry['target'].'" href="'.$entry['url'].'"><i class="fa fa-lg '.$entry['icon'].'"></i> <span style="display: inline-block;vertical-align: middle;line-height: normal;" class="hidden-sm hidden-md main_navbar_main_text" >'.$entry['title'].'</span></a></li>';
                                }
                            ?>
						</ul>
						<ul class="nav navbar-nav navbar-right" id="main_navbar_help">
							<li <?php if (!StartsWith($currentPage['rawname'], 'admin_') && !StartsWith($currentPage['rawname'], 'live') && !$highlighted && 'notifications' != $currentPage['rawname']) {
                                echo 'class="active"';
                            } ?> class="dropdown">
								<a href="#" class="dropdown-toggle" data-toggle="dropdown"><span class="fa fa-question-circle-o fa-lg"></span></a>
								<ul class="dropdown-menu">
									<?php
                                        foreach ($pagesManager->files as $page) {
                                            if ($page['data']['place'] == 'help') {
                                                $navbar_entries['help'][] = array('title' => Lang($page['data']['language']), 'icon' => $page['data']['icon'], 'position' => $page['data']['position'], 'url' => 'index.php?t='.$page['rawname'], 'class' => '', 'target' => '');
                                            }
                                        }

                                        array_multisort(array_column($navbar_entries['help'], 'position'), $navbar_entries['help']);

                                        foreach ($navbar_entries['help'] as $entry) {
                                            echo '<li><a target="'.$entry['target'].'" href="'.$entry['url'].'"><i class="fa '.$entry['icon'].'"></i> '.$entry['title'].'</a></li>';
                                        }
                                    ?>
								</ul>
							</li>

							<?php
                                if ($auth_user) {
                                    ?>
										<li <?php if ('notifications' == $currentPage['rawname']) {
                                        echo 'class="active"';
                                    } ?> class="dropdown">
											<a href="#" onclick="main_notification_icons_adjust();" class="dropdown-toggle" data-toggle="dropdown"><span id="main_notifications_badge" class="badge-notify badge"></span> <span id="main_notifications_bell" class="fa fa-bell-o fa-lg"></span></a>
											<ul class="dropdown-menu dropdown-notifications">
												<div class="text-center">
													<div class="col-md-12">
														<div class="list-group notifications-list">
															<div id="main_notifications">

															</div>
														</div>
														<hr>
														<a href="index.php?t=notifications"><button style="width: 100%; margin-bottom: 5px;" class="btn btn-sm btn-primary"><?php echo Lang('view_all'); ?></button></a>
														<br>
													</div>
												</div>
											</ul>
										</li>
									<?php
                                }
                            ?>
                        <?php if (GetAll()) { ?>
                            <li <?php if (StartsWith($currentPage['rawname'], 'live') && !$highlighted && 'notifications' != $currentPage['rawname']) {
                                            echo 'class="active"';
                                        } ?> class="dropdown" style="padding-right:0px;">
                                        <a href="#" class="dropdown-toggle" data-toggle="dropdown"><span class="fa fa-twitch fa-lg" aria-hidden="true"></span></a>
                                        <ul class="dropdown-menu">
                                            <?php
                                                

                                                foreach (GetAll() as $page) {
                                                    $navbar_entries['live'][] = array('title' => $page['name'], 'icon' => 'fa fa-twitch fa-lg', 'position' => '0', 'url' => 'index.php?t=live&stream='.$page['url'], 'class' => '', 'target' => '', 'namestream' => $page['name'], 'id' => $page['url']);
                                                }

                                        array_multisort(array_column($navbar_entries['live'], 'position'), $navbar_entries['live']);

                                        foreach ($navbar_entries['live'] as $entry) {
                                            ?>
                                                    <script>Twitch(<?php echo '"'.$entry['id'].'"' ?>)</script>
                                            <?php
                                            echo '<li><a target="'.$entry['target'].'" href="'.$entry['url'].'"><i id="live_icon'.$entry['id'].'" class="fa '.$entry['icon'].'"></i> '.$entry['title'].' <p id="NumberViewer'.$entry['id'].'"></p> </a></li>';
                                        } ?>
                                        </ul>
                            </li>
                        <?php } ?>
							<?php
                                $access_adminpage = false;

                                foreach ($pagesManager->files as $page) {
                                    if (StartsWith($page['rawname'], 'admin_')) {
                                        if (Permissions::HasPagePermission($page['rawname'])) {
                                            $access_adminpage = true;
                                        }
                                    }
                                }

                                if ($access_adminpage) {
                                    ?>
								<li <?php if (StartsWith($currentPage['rawname'], 'admin_') && !$highlighted) {
                                        echo 'class="active"';
                                    } ?> class="dropdown" style="padding-right:0px;">
									<a href="#" class="dropdown-toggle" data-toggle="dropdown"><span class="fa fa-bullhorn fa-lg" aria-hidden="true"></span></a>
									<ul class="dropdown-menu">
										<?php
                                            foreach ($pagesManager->files as $page) {
                                                if ($page['data']['place'] == 'admin') {
                                                    $navbar_entries['admin'][] = array('title' => Lang($page['data']['language']), 'icon' => $page['data']['icon'], 'position' => $page['data']['position'], 'url' => 'index.php?t='.$page['rawname'], 'class' => '', 'target' => '');
                                                }
                                            }

                                    array_multisort(array_column($navbar_entries['admin'], 'position'), $navbar_entries['admin']);

                                    foreach ($navbar_entries['admin'] as $entry) {
                                        echo '<li><a target="'.$entry['target'].'" href="'.$entry['url'].'"><i class="fa '.$entry['icon'].'"></i> '.$entry['title'].'</a></li>';
                                    } ?>
									</ul>
								</li>
							<?php
                                } ?>

							<li class="dropdown" >
								<?php
                                    if (!$auth_user) {
                                        ?>
											<a href="request.php?t=main_login"><img style="max-height:25px;" src="https://i.imgur.com/qJTlqDO.png" /></a>
										<?php
                                    } else {
                                        ?>
											<a style="padding-top: 14px;" href="#" class="dropdown-toggle" data-toggle="dropdown">
												<img style="border-radius: 4px; height: 25px;" src="<?php echo $auth_user->GetValue('avatar_small'); ?>" />
												&nbsp;<span class="fa fa-angle-down"></span>
											</a>
											<ul class="dropdown-menu">
												<?php
                                                    $userpage = false;

                                        foreach ($pagesManager->files as $page) {
                                            if ($page['data']['place'] == 'user') {
                                                $navbar_entries['user'][] = array('title' => Lang($page['data']['language']), 'icon' => $page['data']['icon'], 'position' => $page['data']['position'], 'url' => 'index.php?t='.$page['rawname'], 'class' => '', 'target' => '');
                                            }
                                        }

                                        array_multisort(array_column($navbar_entries['user'], 'position'), $navbar_entries['user']);

                                        foreach ($navbar_entries['user'] as $entry) {
                                            $userpage = true;
                                            echo '<li><a target="'.$entry['target'].'" href="'.$entry['url'].'"><i class="fa '.$entry['icon'].'"></i> '.$entry['title'].'</a></li>';
                                        }

                                        if ($userpage) {
                                            echo '<li class="divider"></li>';
                                        } ?>
												<li><a href="#" data-toggle="modal" data-target="#main_modal_settings"><i class="fa fa-cogs"></i> <?php echo Lang('settings'); ?></a></li>
												<li><a href="logout.php"><i class="fa fa-sign-out"></i> <?php echo Lang('logoff'); ?></a></li>
											</ul>
										<?php
                                    }
                                ?>
							</li>
						</ul>
					</div>
				</div>
			</div>
		</div>				
</body>


<?php
     function GetAll(){
        $db = MysqliDb::getInstance();
        
        $db->orderBy("id", "Asc");

        return $db->get('twitch');
    }
?>