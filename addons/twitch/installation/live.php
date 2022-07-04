<!--{"language":"settings_live","icon":"fa-twitch","place":"live","position":10,"nologin":true}-->

<?php 
	if(!isset($G_MAIN)){
		die(json_encode(array("error" => "authentification failed")));
	}

	if (isset($_GET["stream"])) {
		$stream = $_GET["stream"];
	}

	foreach (GetAllLive() as $live) {
		if ($live['url'] == $stream) {
			$name = $live['name'];
		}
	}


    function GetAllLive(){
            $db = MysqliDb::getInstance();
            
            $db->orderBy("id", "Asc");
            
			return $db->get('twitch');
	}
?>


<html>
	<body>
		<link rel="stylesheet" href="assets/css/image-frame.css">
		<div class="row">
			<div class="col-md-12">
				<h1 class="page-header"><?php echo $name; ?></h1>
			</div>
		</div>

		<?php 
		echo '<iframe src="https://player.twitch.tv/?channel='.$stream.'&parent='.$_SERVER['HTTP_HOST'].'" frameborder="0" allowfullscreen="true" scrolling="no" height="449" width="70%"></iframe>';
		echo '<iframe src="https://www.twitch.tv/embed/'.$stream.'/chat?parent='.$_SERVER['HTTP_HOST'].'" frameborder="0" scrolling="no" height="449" width="29%"></iframe>';
		?>
		
	</body>
</html>



