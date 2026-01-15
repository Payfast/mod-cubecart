<?php

/**
 * index.inc.php
 *
 * Copyright (c) 2026 Payfast (Pty) Ltd
 *
 * @author App Inlet (Pty) Ltd
 * @link http://www.payfast.co.za/help/cube_cart
 */

$module       = new Module(__FILE__, $_GET['module'], 'admin/index.tpl', true);
$page_content = $module->display();
