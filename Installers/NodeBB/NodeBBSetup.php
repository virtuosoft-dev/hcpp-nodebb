<?php

namespace Hestia\WebApp\Installers\NodeBB;
use Hestia\WebApp\Installers\BaseSetup as BaseSetup;
require_once( '/usr/local/hestia/web/pluginable.php' );

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
			"nodebb_username" => ["value" => "nbbadmin"],
			"nodebb_password" => "password",
			"nodebb_email" => ["value" => ""],
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

	public function __construct($domain, $appcontext) {
		$v = trim( file_get_contents( '/usr/local/hestia/plugins/nodebb/nodebb_version.sh' ) );
		$v = str_replace( ['nodebb_version=', '"'], "", $v );
		$this->appInfo['version'] = $v;
		parent::__construct($domain, $appcontext);
	}

	public function install(array $options = null) {
		global $hcpp;
		$parse = explode( '/', $this->getDocRoot() );
		$options['user'] = $parse[2];
		$options['domain'] = $parse[4];
		$hcpp->run( 'invoke-plugin nodebb_install ' . escapeshellarg( json_encode( $options ) ) );
		return true;
	}
}
