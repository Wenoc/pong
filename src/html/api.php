<?php
namespace pong\html;
require __DIR__ . '/../../vendor/autoload.php';
//$log = new Monolog\Logger('log');
//require_once("../controllers/APIController.php");
$api = new \pong\controllers\APIController();
$api->post($_REQUEST);
echo json_encode($api->out);
?>