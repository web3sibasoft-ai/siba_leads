<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Migration_Lead_job_subgroup extends App_module_migration { public function up(){ $CI=&get_instance();$table=db_prefix().'leads';if(!$CI->db->field_exists('job_sub_group_id',$table))$CI->db->query('ALTER TABLE `'.$table.'` ADD COLUMN `job_sub_group_id` INT(11) NOT NULL DEFAULT 0'); }}
