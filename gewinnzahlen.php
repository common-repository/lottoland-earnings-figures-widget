<?php
/**
 * Plugin Name: Lottoland Earnings Figures Widget
 * Plugin URI:
 * Description: The last earnings figures from lottoland.com for your front end.
 * Author:      Lottoland
 * Version:     1.0.0
 * Author URI:  https://www.lottoland.co.uk/lottery-results
 * License:     GPLv2+
 * License URI: ./assets/license.txt
 * Text Domain: lottololand
 * Domain Path: /languages
 * Network:     false
 */

// Check the php version
$correct_php_version = version_compare( phpversion(), "5.2", ">=" );

if ( ! $correct_php_version ) {
	echo 'This plugin requires <strong>PHP 5.2</strong> or higher.<br>';
	echo 'You are running PHP ' . phpversion();
	exit;
}

// Check, if is WordPress
! defined( 'ABSPATH' ) and exit;

// Include the widget class
require_once 'php/widget.php';
