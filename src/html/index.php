<?php
namespace pong;
	//echo phpinfo();
    require_once("../inc/db.php");
    $db = new Db();
    $stuff = $db->get_stats();
?>

<html>
<body>Hopla. <?php print_r($stuff);?></body>
</html>


