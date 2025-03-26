<?php
namespace Hestia\WebApp\Installers\NodeBB;
use Hestia\WebApp\Installers\BaseSetup as BaseSetup;

class NodeBBSetup extends BaseSetup {
	protected $appInfo = [
		"name" => "NodeBB",
		"group" => "forum",
		"enabled" => true,
		"version" => "",
		"thumbnail" => "nodebb-thumb.png",
	];
 
	protected $appname = "nodebb";
	protected $config = [
		"form" => [
			"nodeBB_username" => ["value" => "nbbadmin"],
			"nodeBB_password" => "password",
			"nodeBB_email" => ["value" => ""],
			"nodebb_folder" => ["type" => "text", "value" => "", "placeholder" => "/", "label" => "Install Directory"]
		],
		"database" => true,
		"resources" => [
		],
		"server" => [
			"nginx" => [],
			"php" => [
				"supported" => ["7.3", "7.4", "8.0", "8.1", "8.2"],
			],
		],
	];

	public function install(array $options = null) {
		global $hcpp;
		$parse = explode( '/', $this->getDocRoot() );
		$options['user'] = $parse[2];
		$options['domain'] = $parse[4];
		$hcpp->run( 'v-invoke-plugin nodebb_setup ' . escapeshellarg( json_encode( $options ) ) );
		return true;
	}
}
