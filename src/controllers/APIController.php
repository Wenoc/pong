<?php
namespace pong\controllers;
require __DIR__ . '/../../vendor/autoload.php';
require_once("GenericController.php");

class APIController extends GenericController
{
	private $db;
	private $ctrl;

	__construct(){
		$this->db = new DB();
		$this->ctrl = new GenericController();
	}

	public post($post)
	{
		switch ($post["command"]) {
			case 'new_game':
				# code...
				break;
			
			default:
				# code...
				break;
		}
	}
}
?>

