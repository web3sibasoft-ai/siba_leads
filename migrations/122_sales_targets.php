<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Sales_targets extends App_module_migration {public function up(){require_once __DIR__.'/../helpers/siba_leads_sales_targets_helper.php';siba_leads_sales_targets_schema();}}
