<?php
/**
 * Plugin Name: NodeBB
 * Plugin URI: https://github.com/virtuosoft-dev/hcpp-nodebb
 * Description: NodeBB is a plugin for HestiaCP that allows you to Quick Install a NodeBB instance.
 * Version: 1.0.0
 * Author: Stephen J. Carnam
 */

// Register the install and uninstall scripts
global $hcpp;
require_once( dirname(__FILE__) . '/nodebb.php' );

$hcpp->register_install_script( dirname(__FILE__) . '/install' );
$hcpp->register_uninstall_script( dirname(__FILE__) . '/uninstall' );
