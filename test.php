<?php
session_start();
$_SESSION['cliente_cedula'] = '23112102';
$_SESSION['cliente_nombre'] = 'Test';
$_SESSION['wisp_service_id'] = '858';
$_SESSION['dev_mode'] = true;

require 'portal/dashboard.php';
