<?php

namespace modules\siba_leads\services;

defined('BASEPATH') or exit('No direct script access allowed');

use app\services\leads\LeadsKanban;

class SibaLeadsKanban extends LeadsKanban
{
    /**
     * Request-scoped cache for schema checks (field_exists hits information_schema).
     *
     * @var array<string, bool>
     */
    private static $fieldExistsCache = [];

    /**
     * Newest leads first on the Siba board (by dateadded).
     */
    public function defaultSortColumn()
    {
        return 'dateadded';
    }

    public function defaultSortDirection()
    {
        return 'DESC';
    }

    /**
     * Lightweight COUNT — same filters as get(), without heavy SELECT subqueries.
     */
    public function countAll()
    {
        $this->initiateCountQuery();

        if ($this->q) {
            $this->applySearchQuery($this->q);
        }

        $this->tapQueryIfNeeded();

        return $this->ci->db->count_all_results();
    }

    protected function initiateCountQuery(): self
    {
        if (!function_exists('siba_leads_can_view_all')) {
            $this->ci->load->helper(SIBA_LEADS_MODULE_NAME . '/siba_leads');
        }

        $prefix = db_prefix();
        $this->ci->db->select('1', false);
        $this->ci->db->from('leads');
        $this->ci->db->join($prefix . 'leads_sources', $prefix . 'leads_sources.id=' . $prefix . 'leads.source', 'left');
        $this->ci->db->join($prefix . 'staff', $prefix . 'staff.staffid=' . $prefix . 'leads.assigned', 'left');
        $this->ci->db->where($prefix . 'leads.status', $this->status);

        if (function_exists('siba_leads_kanban_open_where_sql')) {
            $this->ci->db->where(siba_leads_kanban_open_where_sql($prefix . 'leads'), null, false);
        } else {
            $this->ci->db->where($prefix . 'leads.lost', 0);
            $this->ci->db->where($prefix . 'leads.junk', 0);
            $this->ci->db->where(
                '(' . $prefix . 'leads.date_converted IS NULL'
                . ' OR ' . $prefix . "leads.date_converted < '1971-01-01 00:00:00')",
                null,
                false
            );
        }

        if (!siba_leads_can_view_all()) {
            $this->ci->db->where($prefix . 'leads.assigned', (int) get_staff_user_id());
        }

        return $this;
    }

    protected function initiateQuery(): self
    {
        if (!function_exists('siba_leads_can_view_all')) {
            $this->ci->load->helper(SIBA_LEADS_MODULE_NAME . '/siba_leads');
        }

        $staffId = (int) get_staff_user_id();
        $prefix  = db_prefix();
        $leads   = $prefix . 'leads';

        $formMetaSelect = $this->buildFormMetaSelect($leads);

        // Lean card SELECT: no per-row files/notes/tags/order/client correlated subqueries.
        // City/state text + description/website load on expand; address/ids stay for order readiness.
        $select = $leads . '.title, ' . $leads . '.lead_value, ' . $leads . '.address, '
            . $leads . '.province_id, ' . $leads . '.city_id, '
            . $leads . '.name as lead_name,'
            . $prefix . 'leads_sources.name as source_name,'
            . $leads . '.id as id,'
            . $leads . '.assigned,'
            . $leads . '.email,'
            . $leads . '.phonenumber,'
            . $leads . '.company,'
            . $leads . '.dateadded,'
            . $leads . '.status,'
            . $leads . '.lastcontact'
            . $formMetaSelect
            . ',' . $prefix . 'staff.firstname as assigned_firstname'
            . ',' . $prefix . 'staff.lastname as assigned_lastname'
            . ',' . $prefix . 'staff.profile_image as assigned_profile_image'
            . ',0 as is_lead_client,0 as client_userid,0 as total_files,0 as total_notes,\'\' as tags,0 as my_active_tasks'
            . ',0 as active_order_id,0 as has_pending_fin_req,0 as existing_order_id'
            . ',0 as lead_invoice_id,0 as lead_invoice_status,\'\' as lead_invoice_hash'
            . ',0 as lead_order_paid,0 as lead_order_expired,0 as lead_order_pending_invoice';

        $this->ci->db->select($select, false);
        $this->ci->db->from('leads');
        // LEFT JOIN: imported/legacy leads with missing source must still appear on the board.
        $this->ci->db->join($prefix . 'leads_sources', $prefix . 'leads_sources.id=' . $leads . '.source', 'left');
        $this->ci->db->join($prefix . 'staff', $prefix . 'staff.staffid=' . $leads . '.assigned', 'left');
        $this->ci->db->where($leads . '.status', $this->status);

        if (function_exists('siba_leads_kanban_open_where_sql')) {
            $this->ci->db->where(siba_leads_kanban_open_where_sql($leads), null, false);
        } else {
            $this->ci->db->where($leads . '.lost', 0);
            $this->ci->db->where($leads . '.junk', 0);
            $this->ci->db->where(
                '(' . $leads . '.date_converted IS NULL'
                . ' OR ' . $leads . ".date_converted < '1971-01-01 00:00:00')",
                null,
                false
            );
        }

        if (!siba_leads_can_view_all()) {
            $this->ci->db->where($leads . '.assigned', $staffId);
        }

        return $this;
    }

    /**
     * @param string $leadsTable Fully-prefixed leads table name
     */
    private function buildFormMetaSelect(string $leadsTable): string
    {
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
        if (function_exists('siba_leads_attribution_columns')) {
            $formMetaColumns = array_merge($formMetaColumns, siba_leads_attribution_columns());
        }
        foreach ($formMetaColumns as $column) {
            if ($this->fieldExistsCached($column, $leadsTable)) {
                $formMetaSelect .= ',' . $leadsTable . '.' . $column;
            } else {
                $formMetaSelect .= ',\'\' as ' . $column;
            }
        }

        return $formMetaSelect;
    }

    private function fieldExistsCached(string $column, string $table): bool
    {
        $key = $table . '.' . $column;
        if (!array_key_exists($key, self::$fieldExistsCache)) {
            self::$fieldExistsCache[$key] = (bool) $this->ci->db->field_exists($column, $table);
        }

        return self::$fieldExistsCache[$key];
    }

    public function get()
    {
        $leads = parent::get();
        if ($leads === []) {
            return $leads;
        }

        if (function_exists('siba_leads_kanban_batch_enrich_leads')) {
            $leads = siba_leads_kanban_batch_enrich_leads($leads);
        } else {
            if (function_exists('siba_leads_annotate_phone_duplicates')) {
                $leads = siba_leads_annotate_phone_duplicates($leads);
            }
            if (function_exists('siba_leads_annotate_related_customers')) {
                $leads = siba_leads_annotate_related_customers($leads);
            }
        }

        return $leads;
    }
}
