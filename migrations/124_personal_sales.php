<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Personal_sales extends App_module_migration {public function up(){require_once __DIR__.'/../helpers/siba_leads_sales_targets_helper.php';require_once __DIR__.'/../helpers/siba_leads_sales_access_helper.php';siba_leads_sales_targets_schema();siba_leads_sales_access_schema();}}
