<?php
namespace pong\html;
require __DIR__ . '/../../vendor/autoload.php';
//$log = new Monolog\Logger('log');
require_once("../controllers/APIController.php");
$api = APIController();
$api->post($_POST);
return $out;

?>