<?php

namespace modules\siba_leads\services;

defined('BASEPATH') or exit('No direct script access allowed');

use app\services\leads\LeadsKanban;

class SibaLeadsKanban extends LeadsKanban
{
    protected function initiateQuery(): self
    {
        if (!function_exists('siba_leads_can_view_all')) {
            $this->ci->load->helper(SIBA_LEADS_MODULE_NAME . '/siba_leads');
        }

        $staffId = (int) get_staff_user_id();
        // Tasks_model::STATUS_COMPLETE
        $completeStatus = 5;
        $prefix = db_prefix();

        $myActiveTasksSql = '(SELECT COUNT(' . $prefix . 'tasks.id)
            FROM ' . $prefix . 'tasks
            INNER JOIN ' . $prefix . 'task_assigned
                ON ' . $prefix . 'task_assigned.taskid = ' . $prefix . 'tasks.id
            WHERE ' . $prefix . 'tasks.rel_type = "lead"
              AND ' . $prefix . 'tasks.rel_id = ' . $prefix . 'leads.id
              AND ' . $prefix . 'task_assigned.staffid = ' . $staffId . '
              AND ' . $prefix . 'tasks.status != ' . $completeStatus . '
        ) as my_active_tasks';

        $orderSelect = function_exists('siba_leads_kanban_active_order_select_sql')
            ? siba_leads_kanban_active_order_select_sql()
            : ',0 as active_order_id,0 as has_pending_fin_req,0 as existing_order_id,0 as lead_invoice_id,0 as lead_invoice_status,\'\' as lead_invoice_hash,0 as lead_order_paid,0 as lead_order_expired,0 as lead_order_pending_invoice';

        $formMetaSelect = '';
        $formMetaColumns = [
            'form_identifier',
            'form_source_link',
            'form_source_page_title',
            'sms_verification',
            'demo_request_tracking_id',
            'user_package_number',
            'request_type',
            'form_title',
            'job_id',
            'ref_code',
            'birth_date',
            'national_code',
            'job_group_id',
            'position_id',
        ];
        foreach ($formMetaColumns as $column) {
            if ($this->ci->db->field_exists($column, db_prefix() . 'leads')) {
                $formMetaSelect .= ',' . db_prefix() . 'leads.' . $column;
            } else {
                $formMetaSelect .= ',\'\' as ' . $column;
            }
        }

        if ($this->ci->db->field_exists('description', db_prefix() . 'leads')) {
            $formMetaSelect .= ',' . db_prefix() . 'leads.description';
        } else {
            $formMetaSelect .= ',\'\' as description';
        }

        $this->ci->db->select(db_prefix() . 'leads.title, ' . db_prefix() . 'leads.website, ' . db_prefix() . 'leads.lead_value, ' . db_prefix() . 'leads.address, ' . db_prefix() . 'leads.city, ' . db_prefix() . 'leads.state, ' . db_prefix() . 'leads.province_id, ' . db_prefix() . 'leads.city_id, ' . db_prefix() . 'leads.country, ' . db_prefix() . 'leads.zip, ' . db_prefix() . 'leads.name as lead_name,' . db_prefix() . 'leads_sources.name as source_name,' . db_prefix() . 'leads.id as id,' . db_prefix() . 'leads.assigned,' . db_prefix() . 'leads.email,' . db_prefix() . 'leads.phonenumber,' . db_prefix() . 'leads.company,' . db_prefix() . 'leads.dateadded,' . db_prefix() . 'leads.status,' . db_prefix() . 'leads.lastcontact' . $formMetaSelect . ',(SELECT COUNT(*) FROM ' . db_prefix() . 'clients WHERE leadid=' . db_prefix() . 'leads.id) as is_lead_client,(SELECT userid FROM ' . db_prefix() . 'clients WHERE leadid=' . db_prefix() . 'leads.id LIMIT 1) as client_userid, (SELECT COUNT(id) FROM ' . db_prefix() . 'files WHERE rel_id=' . db_prefix() . 'leads.id AND rel_type="lead") as total_files, (SELECT COUNT(id) FROM ' . db_prefix() . 'notes WHERE rel_id=' . db_prefix() . 'leads.id AND rel_type="lead") as total_notes,(SELECT GROUP_CONCAT(name SEPARATOR ",") FROM ' . db_prefix() . 'taggables JOIN ' . db_prefix() . 'tags ON ' . db_prefix() . 'taggables.tag_id = ' . db_prefix() . 'tags.id WHERE rel_id = ' . db_prefix() . 'leads.id and rel_type="lead" ORDER by tag_order ASC) as tags,' . $myActiveTasksSql . $orderSelect);
        $this->ci->db->from('leads');
        // LEFT JOIN: imported/legacy leads with missing source must still appear on the board.
        $this->ci->db->join(db_prefix() . 'leads_sources', db_prefix() . 'leads_sources.id=' . db_prefix() . 'leads.source', 'left');
        $this->ci->db->join(db_prefix() . 'staff', db_prefix() . 'staff.staffid=' . db_prefix() . 'leads.assigned', 'left');
        $this->ci->db->where(db_prefix() . 'leads.status', $this->status);

        if (!siba_leads_can_view_all()) {
            $this->ci->db->where(db_prefix() . 'leads.assigned', (int) get_staff_user_id());
        }

        return $this;
    }

    public function get()
    {
        $leads = parent::get();
        if (function_exists('siba_leads_annotate_phone_duplicates')) {
            return siba_leads_annotate_phone_duplicates($leads);
        }

        return $leads;
    }
}
