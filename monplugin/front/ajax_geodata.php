<?php
include('../../../inc/includes.php');
include_once('../inc/dashboard.class.php');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
Session::checkLoginUser();

PluginMonpluginDashboard::ajaxHandler();
