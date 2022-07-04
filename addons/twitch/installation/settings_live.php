<!--{"language":"settings_live","icon":"fa-twitch","position":10}-->

<?php 
    if (!isset($G_MAIN) && !isset($G_REQUEST)) {
        die(json_encode(array("error" => "authentification failed")));
    }
?>

<?php
    if (!DemoLock()) {

        if (isset($_POST['settings_live_new_stream_submit'])) {
            $url = htmlspecialchars($_POST['settings_live_create_modal_url']);
            $name = htmlspecialchars($_POST['settings_live_create_modal_name']);

            if (!empty($url) && !empty($name) ) {
                if (Create($url, $name)) {
                    DirectNotifications::Queue('title: "'.Lang('success').'", text: "'.Lang('live_created_success').'", type: "success"');
                } else {
                    DirectNotifications::Queue('title: "'.Lang('error').'", text: "'.Lang('sqlerror_insert').'", type: "error"');
                }
            }

            Redirect('index.php');
        } elseif (!empty($_POST['settings_live_delete'])) {
            if (Delete($_POST['settings_live_delete'])) {
                DirectNotifications::Queue('title: "'.Lang('success').'", text: "'.Lang('live_deleted_success').'", type: "success"');
            } else {
                DirectNotifications::Queue('title: "'.Lang('error').'", text: "'.Lang('sqlerror_delete').'", type: "error"');
            }

            Redirect('index.php');
        }
    }
?>

<script src="assets/js/fontawesome-iconpicker.min.js"></script>
<link rel="stylesheet" href="assets/css/fontawesome-iconpicker.min.css">

<script src="assets/js/codemirror/codemirror.js"></script>
<link rel="stylesheet" href="assets/css/codemirror/codemirror.css">
<script src="assets/js/codemirror/modes/css.js"></script>

<link rel="stylesheet" href="assets/css/bootstrap.colorpickersliders.css">
<script src='assets/js/bootstrap.colorpickersliders.js'></script>
<script src='assets/js/tinycolor-min.js'></script>

<h3>
    <?php echo Lang('settings_live'); ?>
    <div class="pull-right">
		<button onclick="settings_live_new_create();" type="button" class="btn btn-success btn-sm"><i class="fa fa-plus-circle"></i> <?php echo Lang('settings_live_new_stream'); ?></button>
	</div>
</h3>

<hr>

<div class="row">
    <div class="col-xs-12">
        <div class="table-responsive">
            <table class="table table-hover">
            	<caption><?php echo Lang('settings_live_streamers'); ?></caption>
    
            	<tr class="active">
            		<th><?php echo Lang('streamer_id'); ?></th>
            		<th><?php echo Lang('streamer_name'); ?></th>
            		<th class="text-right"><i class="fa fa-cogs"></i></th>
            	</tr>
    
            	<tbody>
            		<?php
                        foreach (GetAllStream() as $live) {
                            echo '<tr>';
                            echo '<td>';
                            echo $live['url'];
                            echo '</td>';
                            echo '<td>';
                            echo $live['name'];
                            echo '<td class="text-right">';
                            echo '<button onclick="settings_live_delete('.$live['id'].');" type="button" class="btn btn-danger btn-xs"><i class="fa fa-trash"></i> '.Lang('delete').'</button>';
                            echo '</td>';
                            echo '</tr>';
                        }
                    ?>
            	</tbody>
            </table>
        </div>
    </div>
</div>

<!-- External URL Create Modal -->
<div class="modal fade" id="settings_live_new_create_modal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true"><i class="fa fa-times"></i></span></button>
				<h4 class="modal-title"><i class="fa fa-link"></i> &nbsp;<?php echo Lang('settings_live_new_stream'); ?></h4>
			</div>

			<form method="post" action="index.php?t=admin_settings&part=settings_live">
				<div class="modal-body">
		            <label><?php echo Lang('streamer_id'); ?></label><br>
		            <input name="settings_live_create_modal_url" type="text" class="form-control" />
		            <br>

		            <label><?php echo Lang('streamer_name'); ?></label><br>
		            <input name="settings_live_create_modal_name" type="text" class="form-control" />
		            <br>
                        
				</div>
				<div class="modal-footer">
					<button type="submit" name="settings_live_new_stream_submit" class="btn btn-success"><?php echo Lang('create'); ?></button>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
    function settings_live_delete(id){
        post('index.php?t=admin_settings&part=settings_live', {settings_live_delete: id});
    }

    function settings_live_new_create(){
            $('input[name="settings_live_new_create_modal_newtab"]').prop('checked', true);
            $('#settings_live_new_create_modal').modal('show');
    }
</script>







<?php
    function GetAllStream(){
            $db = MysqliDb::getInstance();
            
            $db->orderBy("id", "Asc");
            
            return $db->get('twitch');
    }
        
    function Create($id, $name){
            $db = MysqliDb::getInstance();
            
            $data = array(
                'url' => $id,
                'name' => $name
            );
            
            $db->insert('twitch', $data);
            
            if($db->count){
                return true;
            }else{
                return false;
            }
    }
        
    function Delete($id){
            $db = MysqliDb::getInstance();
            
            $db->where("id", $id);
            
            $db->delete('twitch');
            
                return true;

    }
?>