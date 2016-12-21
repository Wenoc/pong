<?php
namespace pong;
print_r($this);
require_once("../php/controller.php");
?>
<html>
<?php include("inc/meta.html"); ?>
<body>


<form action="index.php" method=POST>
	<input type="hidden" name="newgame" value="1"/>
	Winner <input type="text" name="winner"/><br/>
	Loser&nbsp; <input type="text" name="loser"/><br/>
	<input type="checkbox" name="draw" value="draw">Draw game
	<input type="submit" value="Submit"/>
</form>


<form action="index.php" method="POST">
	<input type="hidden" name="newplayer" value="1"/>
	<input type="text" name="player"/><br/>
	<input type="submit" value="Submit"/>
</form>
Statistics:
<?php 
foreach($db->get_stats() as $player){
	echo print_r($player,true)."<br/>"; 
}
?>
<br/><br/>
Errors: <?php echo print_r($errors,true); ?>
<br/>
Output: <?php echo print_r($out,true); ?>
</body></html>
