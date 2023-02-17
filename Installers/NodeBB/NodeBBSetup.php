<?php

namespace Hestia\WebApp\Installers\NodeBB;
use Hestia\WebApp\Installers\BaseSetup as BaseSetup;
require_once( '/usr/local/hestia/web/pluginable.php' );

class NodeBBSetup extends BaseSetup {
	protected $appInfo = [
		"name" => "NodeBB",
		"group" => "framework",
		"enabled" => true,
		"version" => "3.0.2",
		"thumbnail" => "nodebb-thumb.png",
	];

	protected $appname = "nodebb";
	protected $config = [
		"form" => [
			"nodebb_username" => ["value" => "nbbadmin"],
			"nodebbpassword" => "password",
			"nodebb_folder" => ["type" => "text", "value" => "", "placeholder" => "/", "label" => "Install Directory"]
		],
		"database" => false,
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
		$hcpp->run( 'invoke-plugin nodebb_install ' . escapeshellarg( json_encode( $options ) ) );
		return true;
	}
}