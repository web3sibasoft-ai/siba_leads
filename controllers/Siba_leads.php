<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Siba_leads extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        $this->load->helper(SIBA_LEADS_MODULE_NAME . '/siba_leads');

        $method = $this->router->method;
        if ($method === 'reports') {
            if (!is_admin() && !siba_leads_can_view_reports()) {
                access_denied(SIBA_LEADS_MODULE_NAME);
            }
        } elseif ($method === 'teams') {
            if (!siba_leads_can_manage_teams()) {
                access_denied(SIBA_LEADS_MODULE_NAME);
            }
        } elseif (in_array($method, ['didar', 'didar_wipe', 'didar_import', 'didar_migrate', 'didar_test', 'ai_settings'], true)) {
            if (!is_admin()) {
                access_denied(SIBA_LEADS_MODULE_NAME);
            }
        } elseif (!in_array($method, ['validate_phone', 'get_cities', 'timeline', 'kanban_card_details', 'ai_call_guide'], true)) {
            if (!is_admin() && !staff_can('view', SIBA_LEADS_MODULE_NAME)) {
                access_denied(SIBA_LEADS_MODULE_NAME);
            }
        }

        if (!is_staff_member()) {
            access_denied(SIBA_LEADS_MODULE_NAME);
        }

        $this->load->model('leads_model');
    }

    public function index()
    {
        close_setup_menu();

        $isKanBan = !$this->session->has_userdata('siba_leads_kanban_view')
            || $this->session->userdata('siba_leads_kanban_view') === 'true';

        $data['title']         = $isKanBan ? _l('siba_leads_kanban') : _l('siba_leads_list');
        $data['statuses']      = function_exists('siba_leads_kanban_statuses')
            ? siba_leads_kanban_statuses()
            : $this->leads_model->get_status();
        // Summary and native cards are prepared together only for the kanban view.
        $data['base_currency'] = get_base_currency();
        $data['isKanBan']      = $isKanBan;
        $data['switch_kanban'] = $isKanBan ? 0 : 1;
        $data['bodyclass']     = $isKanBan ? 'kan-ban-body siba-leads-kanban-body' : 'siba-leads-list-body';
        // Siba kanban: newest first by dateadded (DESC), independent of core leads settings.
        $data['sort']          = 'DESC';
        $data['sort_by']       = 'dateadded';
        $data['can_view_all']  = siba_leads_can_view_all();

        if (function_exists('siba_leads_ensure_failure_reasons_table')) {
            siba_leads_ensure_failure_reasons_table();
        }
        $this->load->model('siba_leads/siba_leads_failure_reasons_model');
        $data['failure_reasons'] = $this->siba_leads_failure_reasons_model->get();
        $data['tags']            = function_exists('get_tags') ? get_tags() : [];
        $data['pipelines']       = function_exists('siba_leads_didar_list_pipeline_maps')
            ? siba_leads_didar_list_pipeline_maps()
            : [];
        $data['active_pipeline_id'] = function_exists('siba_leads_active_pipeline_id')
            ? siba_leads_active_pipeline_id()
            : '';
        $activePipeline = function_exists('siba_leads_active_pipeline_map')
            ? siba_leads_active_pipeline_map()
            : null;
        $data['active_pipeline_tag_id'] = (int) ($activePipeline['tag_id'] ?? 0);
        $data['staff'] = [];
        if (!empty($data['can_view_all'])) {
            $this->load->model('staff_model');
            $staffRows = $this->staff_model->get('', ['active' => 1, 'is_not_staff' => 0]);
            if (!is_array($staffRows)) {
                $staffRows = [];
            }
            array_unshift($staffRows, [
                'staffid'   => '0',
                'firstname' => _l('siba_leads_unassigned'),
                'lastname'  => '',
            ]);
            $data['staff'] = $staffRows;
        }

        // Paint the first board in this response; no second bootstrap or six column requests.
        if ($isKanBan) {
            $board = $this->kanban_board_data($data['statuses'], $data['base_currency']);
            $data['initial_board_html'] = $board['html'];
        }

        $this->load->view('siba_leads/manage', $data);
    }

    /**
     * Toggle between kanban and table list (own session key — does not affect core /admin/leads).
     *
     * @param int $set 1 = kanban, 0 = list
     */
    public function switch_kanban($set = 0)
    {
        $this->session->set_userdata([
            'siba_leads_kanban_view' => ((int) $set === 1) ? 'true' : 'false',
        ]);
        redirect(previous_url() ?: admin_url('siba_leads'));
    }

    /**
     * Switch the active Didar pipeline board (different columns per pipeline).
     *
     * @param string $pipelineId Didar pipeline UUID
     */
    public function switch_pipeline($pipelineId = '')
    {
        $pipelineId = trim(rawurldecode((string) $pipelineId));
        $valid      = false;
        if ($pipelineId !== '' && function_exists('siba_leads_didar_list_pipeline_maps')) {
            foreach (siba_leads_didar_list_pipeline_maps() as $row) {
                if (trim((string) ($row['didar_pipeline_id'] ?? '')) === $pipelineId) {
                    $valid = true;
                    break;
                }
            }
        }
        $this->session->set_userdata([
            'siba_leads_active_pipeline_id' => $valid ? $pipelineId : '',
        ]);
        redirect(admin_url('siba_leads'));
    }

    /**
     * DataTables JSON for the list view (same open-lead scope as the kanban).
     */
    public function table()
    {
        if (!is_staff_member()) {
            ajax_access_denied();
        }

        $this->load->helper('datatables');

        $prefix = db_prefix();
        $tagsSubquery = '(SELECT GROUP_CONCAT(name ORDER BY tag_order ASC SEPARATOR ",") FROM ' . $prefix . 'taggables'
            . ' JOIN ' . $prefix . 'tags ON ' . $prefix . 'taggables.tag_id = ' . $prefix . 'tags.id'
            . ' WHERE rel_id = ' . $prefix . 'leads.id AND rel_type="lead") as tags';

        $aColumns = [
            $prefix . 'leads.id as id',
            $prefix . 'leads.name as name',
            $prefix . 'leads.company as company',
            $prefix . 'leads.phonenumber as phonenumber',
            $prefix . 'leads.email as email',
            'firstname as assigned_firstname',
            $prefix . 'leads_status.name as status_name',
            $prefix . 'leads_sources.name as source_name',
            $tagsSubquery,
            $prefix . 'leads.lastcontact as lastcontact',
            $prefix . 'leads.dateadded as dateadded',
        ];

        $sIndexColumn = 'id';
        $sTable       = $prefix . 'leads';
        $join = [
            'LEFT JOIN ' . $prefix . 'staff ON ' . $prefix . 'staff.staffid = ' . $prefix . 'leads.assigned',
            'LEFT JOIN ' . $prefix . 'leads_status ON ' . $prefix . 'leads_status.id = ' . $prefix . 'leads.status',
            'LEFT JOIN ' . $prefix . 'leads_sources ON ' . $prefix . 'leads_sources.id = ' . $prefix . 'leads.source',
        ];

        $where = [
            'AND ' . siba_leads_kanban_open_where_sql($prefix . 'leads'),
        ];
        if (!siba_leads_can_view_all()) {
            $where[] = 'AND ' . $prefix . 'leads.assigned = ' . (int) get_staff_user_id();
        } elseif (function_exists('siba_leads_request_assigned_filter')) {
            $assignedFilter = siba_leads_request_assigned_filter();
            if ($assignedFilter !== null) {
                $where[] = 'AND ' . $prefix . 'leads.assigned = ' . (int) $assignedFilter;
            }
        }

        // Same scope as kanban: request tags, or active pipeline tag when none set.
        $tagFilter = function_exists('siba_leads_effective_tag_filter')
            ? siba_leads_effective_tag_filter()
            : (function_exists('siba_leads_request_tag_filter')
                ? siba_leads_request_tag_filter()
                : ['tag_ids' => [], 'tag_none' => false]);
        $taggables = $prefix . 'taggables';
        if (!empty($tagFilter['tag_none'])) {
            $where[] = 'AND ' . $prefix . 'leads.id NOT IN (SELECT rel_id FROM ' . $taggables . " WHERE rel_type = 'lead')";
        } elseif (!empty($tagFilter['tag_ids'])) {
            $where[] = 'AND ' . $prefix . 'leads.id IN (SELECT rel_id FROM ' . $taggables
                . " WHERE rel_type = 'lead' AND tag_id IN (" . implode(',', array_map('intval', $tagFilter['tag_ids'])) . '))';
        }

        $additionalSelect = [
            $prefix . 'leads.assigned as assigned',
            $prefix . 'leads.status as status',
            $prefix . 'leads_status.color as color',
            'lastname as assigned_lastname',
        ];

        $result  = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, $additionalSelect);
        $output  = $result['output'];
        $rResult = $result['rResult'];

        foreach ($rResult as $aRow) {
            $row = [];
            $leadId = (int) $aRow['id'];

            $row[] = '<a href="' . admin_url('leads/index/' . $leadId) . '" onclick="init_lead(' . $leadId . '); return false;">#'
                . $leadId . '</a>';

            $name = '<a href="' . admin_url('leads/index/' . $leadId) . '" onclick="init_lead(' . $leadId . '); return false;">'
                . e($aRow['name']) . '</a>';
            $name .= '<div class="row-options">';
            $name .= '<a href="#" onclick="init_lead(' . $leadId . '); return false;">' . _l('view') . '</a>';
            $name .= '</div>';
            $row[] = $name;

            $row[] = e($aRow['company'] ?? '');
            $row[] = ($aRow['phonenumber'] !== '' && $aRow['phonenumber'] !== null)
                ? '<a href="tel:' . e($aRow['phonenumber']) . '" dir="ltr">' . e($aRow['phonenumber']) . '</a>'
                : '';
            $row[] = ($aRow['email'] !== '' && $aRow['email'] !== null)
                ? '<a href="mailto:' . e($aRow['email']) . '">' . e($aRow['email']) . '</a>'
                : '';

            $assignedOutput = '';
            if ((int) ($aRow['assigned'] ?? 0) > 0) {
                $fullName = e(trim(($aRow['assigned_firstname'] ?? '') . ' ' . ($aRow['assigned_lastname'] ?? '')));
                $assignedOutput = '<a data-toggle="tooltip" data-title="' . $fullName . '" href="'
                    . admin_url('profile/' . (int) $aRow['assigned']) . '">'
                    . staff_profile_image((int) $aRow['assigned'], ['staff-profile-image-small']) . '</a>';
                $assignedOutput .= '<span class="hide">' . $fullName . '</span>';
            } else {
                $assignedOutput = e(_l('siba_leads_unassigned'));
            }
            $row[] = $assignedOutput;

            $color = (string) ($aRow['color'] ?? '');
            if ($aRow['status_name'] !== null && $aRow['status_name'] !== '') {
                $row[] = '<span class="label' . ($color === '' ? ' label-default' : '') . '" style="'
                    . ($color !== ''
                        ? 'color:' . e($color) . ';border:1px solid ' . e(adjust_hex_brightness($color, 0.4))
                          . ';background:' . e(adjust_hex_brightness($color, 0.04)) . ';'
                        : '')
                    . '">' . e($aRow['status_name']) . '</span>';
            } else {
                $row[] = '—';
            }

            $row[] = e($aRow['source_name'] ?? '');
            $row[] = !empty($aRow['tags']) && function_exists('render_tags')
                ? render_tags($aRow['tags'])
                : '';

            $last = $aRow['lastcontact'] ?? '';
            $row[] = ($last === '0000-00-00 00:00:00' || !is_date($last))
                ? ''
                : '<span data-toggle="tooltip" data-title="' . e(_dt($last)) . '" class="text-has-action is-date">'
                    . e(time_ago($last)) . '</span>';

            $added = $aRow['dateadded'] ?? '';
            $row[] = is_date($added)
                ? '<span data-toggle="tooltip" data-title="' . e(_dt($added)) . '" class="text-has-action is-date">'
                    . e(time_ago($added)) . '</span>'
                : '';

            $row['DT_RowId'] = 'lead_' . $leadId;
            $output['aaData'][] = $row;
        }

        echo json_encode($output);
    }

    /**
     * Mark lead as failed (requires failure reason). Removes from kanban.
     */
    public function mark_failed()
    {
        if (!$this->input->is_ajax_request() || !$this->input->post()) {
            show_404();
        }

        if (!is_admin() && !staff_can('edit', SIBA_LEADS_MODULE_NAME) && staff_cant('edit', 'leads')) {
            ajax_access_denied();
        }

        $leadId             = (int) $this->input->post('lead_id');
        $reasonId           = (int) $this->input->post('failure_reason_id');
        $failureDescription = trim((string) $this->input->post('failure_description'));

        if ($leadId < 1) {
            echo json_encode([
                'success' => false,
                'message' => _l('siba_leads_mark_failed_invalid'),
            ]);

            return;
        }

        if (!siba_leads_can_manage_lead($leadId)) {
            ajax_access_denied();
        }

        if ($reasonId < 1) {
            echo json_encode([
                'success' => false,
                'message' => _l('siba_leads_mark_failed_reason_required'),
            ]);

            return;
        }

        if (function_exists('siba_leads_ensure_failure_reasons_table')) {
            siba_leads_ensure_failure_reasons_table();
        }
        $this->load->model('siba_leads/siba_leads_failure_reasons_model');
        $reason = $this->siba_leads_failure_reasons_model->get_row($reasonId);
        if (!$reason) {
            echo json_encode([
                'success' => false,
                'message' => _l('siba_leads_mark_failed_reason_required'),
            ]);

            return;
        }

        $lead = $this->leads_model->get($leadId);
        if (!$lead) {
            echo json_encode([
                'success' => false,
                'message' => _l('siba_leads_mark_failed_invalid'),
            ]);

            return;
        }

        if (!empty($lead->date_converted) && $lead->date_converted !== '0000-00-00 00:00:00') {
            echo json_encode([
                'success' => false,
                'message' => _l('siba_leads_mark_failed_already_converted'),
            ]);

            return;
        }

        $ok = siba_leads_set_outcome($leadId, 'failed', 'manual_fail', [
            'failure_reason_id'   => $reasonId,
            'failure_description' => $failureDescription,
        ]);

        if (!$ok) {
            echo json_encode([
                'success' => false,
                'message' => _l('siba_leads_mark_failed_invalid'),
            ]);

            return;
        }

        echo json_encode([
            'success' => true,
            'message' => _l('siba_leads_mark_failed_success'),
            'id'      => $leadId,
        ]);
    }

    /**
     * Failed leads archive with filters.
     */
    public function failed()
    {
        close_setup_menu();

        if (function_exists('siba_leads_ensure_lead_columns')) {
            siba_leads_ensure_lead_columns();
        }
        if (function_exists('siba_leads_ensure_failure_reasons_table')) {
            siba_leads_ensure_failure_reasons_table();
        }

        $this->load->model('siba_leads/siba_leads_failure_reasons_model');
        $this->load->model('staff_model');

        $filters = [
            'reason_id'  => (int) $this->input->get('reason_id'),
            'outcome_by' => (int) $this->input->get('outcome_by'),
            'assigned'   => (int) $this->input->get('assigned'),
            'date_from'  => trim((string) $this->input->get('date_from')),
            'date_to'    => trim((string) $this->input->get('date_to')),
            'q'          => trim((string) $this->input->get('q')),
        ];

        $prefix  = db_prefix();
        $leadsT  = $prefix . 'leads';
        $reasonT = $prefix . 'siba_leads_failure_reasons';

        $this->db->select($leadsT . '.*, r.title as reason_title, r.color as reason_color');
        $this->db->from($leadsT);
        $this->db->join($reasonT . ' r', 'r.id = ' . $leadsT . '.siba_failure_reason_id', 'left');
        $this->db->group_start();
        $this->db->where($leadsT . '.lost', 1);
        $this->db->or_where($leadsT . '.siba_outcome', 'failed');
        $this->db->group_end();

        if (!siba_leads_can_view_all()) {
            $this->db->where($leadsT . '.assigned', (int) get_staff_user_id());
        }

        if ($filters['reason_id'] > 0) {
            $this->db->where($leadsT . '.siba_failure_reason_id', $filters['reason_id']);
        }
        if ($filters['outcome_by'] > 0) {
            $this->db->where($leadsT . '.siba_outcome_by', $filters['outcome_by']);
        }
        if ($filters['assigned'] > 0) {
            $this->db->where($leadsT . '.assigned', $filters['assigned']);
        }
        if ($filters['date_from'] !== '') {
            if (!function_exists('to_sql_date_custom')) {
                $this->load->helper('siba_license/siba_license');
            }
            $fromRaw = function_exists('siba_license_to_latin_digits')
                ? siba_license_to_latin_digits($filters['date_from'])
                : $filters['date_from'];
            $from = function_exists('to_sql_date_custom')
                ? to_sql_date_custom($fromRaw)
                : to_sql_date($fromRaw);
            // Only query with a real Gregorian SQL date.
            if (is_string($from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
                $this->db->where($leadsT . '.siba_outcome_at >=', $from . ' 00:00:00');
                if (function_exists('to_view_date_custom')) {
                    $viewFrom = to_view_date_custom($from);
                    if ($viewFrom) {
                        $filters['date_from'] = $viewFrom;
                    }
                }
            } else {
                $filters['date_from'] = '';
            }
        }
        if ($filters['date_to'] !== '') {
            if (!function_exists('to_sql_date_custom')) {
                $this->load->helper('siba_license/siba_license');
            }
            $toRaw = function_exists('siba_license_to_latin_digits')
                ? siba_license_to_latin_digits($filters['date_to'])
                : $filters['date_to'];
            $to = function_exists('to_sql_date_custom')
                ? to_sql_date_custom($toRaw)
                : to_sql_date($toRaw);
            if (is_string($to) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
                $this->db->where($leadsT . '.siba_outcome_at <=', $to . ' 23:59:59');
                if (function_exists('to_view_date_custom')) {
                    $viewTo = to_view_date_custom($to);
                    if ($viewTo) {
                        $filters['date_to'] = $viewTo;
                    }
                }
            } else {
                $filters['date_to'] = '';
            }
        }
        if ($filters['q'] !== '') {
            $q = $this->db->escape_like_str($filters['q']);
            $this->db->group_start();
            $this->db->like($leadsT . '.name', $q);
            $this->db->or_like($leadsT . '.phonenumber', $q);
            $this->db->or_like($leadsT . '.email', $q);
            $this->db->or_like($leadsT . '.company', $q);
            $this->db->or_like($leadsT . '.siba_failure_description', $q);
            $this->db->group_end();
        }

        $this->db->order_by($leadsT . '.siba_outcome_at', 'DESC');
        $this->db->order_by($leadsT . '.id', 'DESC');
        $leads = $this->db->get()->result_array();

        $data['title']   = _l('siba_leads_failed');
        $data['leads']   = $leads;
        $data['filters'] = $filters;
        $data['reasons'] = $this->siba_leads_failure_reasons_model->get();
        $data['staff']   = $this->staff_model->get('', ['active' => 1, 'is_not_staff' => 0]);

        $this->load->view('siba_leads/failed/manage', $data);
    }

    /**
     * Archive of successful / converted leads (after financial approval creates the client).
     */
    public function successful()
    {
        close_setup_menu();

        if (function_exists('siba_leads_ensure_lead_columns')) {
            siba_leads_ensure_lead_columns();
        }

        $this->load->model('staff_model');
        if (!function_exists('siba_leads_reports_is_sale_sql')) {
            $this->load->helper('siba_leads/siba_leads_reports');
        }

        $filters = [
            'outcome_by'  => (int) $this->input->get('outcome_by'),
            'assigned'    => (int) $this->input->get('assigned'),
            'outcome_how' => trim((string) $this->input->get('outcome_how')),
            'date_from'   => trim((string) $this->input->get('date_from')),
            'date_to'     => trim((string) $this->input->get('date_to')),
            'q'           => trim((string) $this->input->get('q')),
        ];

        $prefix   = db_prefix();
        $leadsT   = $prefix . 'leads';
        $clientsT = $prefix . 'clients';
        $saleDate = function_exists('siba_leads_reports_sale_date_sql')
            ? siba_leads_reports_sale_date_sql($leadsT)
            : 'COALESCE(' . $leadsT . '.siba_outcome_at, ' . $leadsT . '.date_converted)';
        $saleWhere = function_exists('siba_leads_reports_is_sale_sql')
            ? siba_leads_reports_is_sale_sql($leadsT)
            : '(' . $leadsT . ".siba_outcome = 'success' OR (" . $leadsT . '.date_converted IS NOT NULL AND '
                . $leadsT . ".date_converted > '1971-01-01 00:00:00'))";

        $select = $leadsT . '.*, c.userid as client_userid, c.company as client_company, '
            . $saleDate . ' as success_at';
        $ordersT = $prefix . 'siba_orders';
        if ($this->db->table_exists($ordersT)) {
            $select .= ', ('
                . 'SELECT o.order_id FROM `' . $ordersT . '` o'
                . ' WHERE o.order_lead_id = ' . $leadsT . '.id'
                . ' AND o.order_financial_approval = 1'
                . ' ORDER BY o.order_id DESC LIMIT 1'
                . ') as approved_order_id';
        }

        $this->db->select($select, false);
        $this->db->from($leadsT);
        $this->db->join($clientsT . ' c', 'c.leadid = ' . $leadsT . '.id', 'left');
        $this->db->where($saleWhere, null, false);

        if (!siba_leads_can_view_all()) {
            $this->db->where($leadsT . '.assigned', (int) get_staff_user_id());
        }

        if ($filters['outcome_by'] > 0) {
            $this->db->where($leadsT . '.siba_outcome_by', $filters['outcome_by']);
        }
        if ($filters['assigned'] > 0) {
            $this->db->where($leadsT . '.assigned', $filters['assigned']);
        }
        if ($filters['outcome_how'] !== '') {
            $this->db->where($leadsT . '.siba_outcome_how', $filters['outcome_how']);
        }
        if ($filters['date_from'] !== '') {
            if (!function_exists('to_sql_date_custom')) {
                $this->load->helper('siba_license/siba_license');
            }
            $fromRaw = function_exists('siba_license_to_latin_digits')
                ? siba_license_to_latin_digits($filters['date_from'])
                : $filters['date_from'];
            $from = function_exists('to_sql_date_custom')
                ? to_sql_date_custom($fromRaw)
                : to_sql_date($fromRaw);
            if (is_string($from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
                $this->db->where($saleDate . ' >= ' . $this->db->escape($from . ' 00:00:00'), null, false);
                if (function_exists('to_view_date_custom')) {
                    $viewFrom = to_view_date_custom($from);
                    if ($viewFrom) {
                        $filters['date_from'] = $viewFrom;
                    }
                }
            } else {
                $filters['date_from'] = '';
            }
        }
        if ($filters['date_to'] !== '') {
            if (!function_exists('to_sql_date_custom')) {
                $this->load->helper('siba_license/siba_license');
            }
            $toRaw = function_exists('siba_license_to_latin_digits')
                ? siba_license_to_latin_digits($filters['date_to'])
                : $filters['date_to'];
            $to = function_exists('to_sql_date_custom')
                ? to_sql_date_custom($toRaw)
                : to_sql_date($toRaw);
            if (is_string($to) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
                $this->db->where($saleDate . ' <= ' . $this->db->escape($to . ' 23:59:59'), null, false);
                if (function_exists('to_view_date_custom')) {
                    $viewTo = to_view_date_custom($to);
                    if ($viewTo) {
                        $filters['date_to'] = $viewTo;
                    }
                }
            } else {
                $filters['date_to'] = '';
            }
        }
        if ($filters['q'] !== '') {
            $q = $this->db->escape_like_str($filters['q']);
            $this->db->group_start();
            $this->db->like($leadsT . '.name', $q);
            $this->db->or_like($leadsT . '.phonenumber', $q);
            $this->db->or_like($leadsT . '.email', $q);
            $this->db->or_like($leadsT . '.company', $q);
            $this->db->or_like('c.company', $q);
            $this->db->group_end();
        }

        $this->db->order_by($saleDate, 'DESC', false);
        $this->db->order_by($leadsT . '.id', 'DESC');
        $leads = $this->db->get()->result_array();

        $howOptions = [];
        if (function_exists('siba_leads_outcome_how_labels')) {
            foreach (siba_leads_outcome_how_labels() as $key => $label) {
                if (strpos((string) $key, 'success') !== false || $key === 'financial_approval' || $key === 'manual_success') {
                    $howOptions[] = ['id' => $key, 'name' => $label];
                }
            }
        }
        if ($howOptions === []) {
            $howOptions = [
                ['id' => 'financial_approval', 'name' => _l('siba_leads_outcome_how_financial_approval')],
                ['id' => 'manual_success', 'name' => _l('siba_leads_outcome_how_manual_success')],
            ];
        }

        $data['title']      = _l('siba_leads_successful');
        $data['leads']      = $leads;
        $data['filters']    = $filters;
        $data['staff']      = $this->staff_model->get('', ['active' => 1, 'is_not_staff' => 0]);
        $data['howOptions'] = $howOptions;

        $this->load->view('siba_leads/successful/manage', $data);
    }

    /**
     * Aggregated lead reports (analysis / incoming / pipeline / conversion / speed / sources / failures / sales).
     */
    public function reports()
    {
        close_setup_menu();

        if (!siba_leads_can_view_reports()) {
            access_denied(SIBA_LEADS_MODULE_NAME);
        }

        if (function_exists('siba_leads_ensure_lead_columns')) {
            siba_leads_ensure_lead_columns();
        }
        if (function_exists('siba_leads_ensure_failure_reasons_table')) {
            siba_leads_ensure_failure_reasons_table();
        }

        $this->load->model('leads_model');
        $this->load->model('staff_model');

        $filters = siba_leads_reports_parse_filters($this->input);
        $allowedTabs = siba_leads_allowed_report_tabs();
        if (empty($allowedTabs)) {
            access_denied(SIBA_LEADS_MODULE_NAME);
        }

        if (!in_array($filters['report'], $allowedTabs, true)) {
            $filters['report'] = $allowedTabs[0];
        }

        $groupBy = $filters['group_by'];

        $result = siba_leads_reports_run($filters, $groupBy);

        $data['title']     = _l('siba_leads_reports');
        $data['filters']   = $filters;
        $data['group_by']  = $groupBy;
        $data['rows']      = $result['rows'] ?? [];
        $data['summary']   = $result['summary'] ?? [];
        $data['chart']     = siba_leads_reports_chart_payload(
            $filters['report'],
            $data['rows'],
            $groupBy,
            $filters
        );
        $data['staff']     = $this->staff_model->get('', ['active' => 1, 'is_not_staff' => 0]);
        $data['sources']   = $this->leads_model->get_source();
        $data['tags']      = function_exists('get_tags') ? get_tags() : [];
        $data['can_view_all'] = siba_leads_can_view_all();
        $data['report_tabs']  = $allowedTabs;
        $data['team_options'] = [];
        if (function_exists('siba_leads_team_definitions')) {
            foreach (siba_leads_team_definitions() as $key => $def) {
                $data['team_options'][] = [
                    'id'   => $key,
                    'name' => (string) ($def['label'] ?? $key),
                ];
            }
        }

        $this->load->view('siba_leads/reports/manage', $data);
    }

    public function kanban()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $data['statuses']      = function_exists('siba_leads_kanban_statuses')
            ? siba_leads_kanban_statuses()
            : $this->leads_model->get_status();
        $data['base_currency'] = get_base_currency();
        $data['summary']       = siba_leads_get_summary();

        echo $this->load->view('siba_leads/kan-ban', $data, true);
    }

    /**
     * Legacy first-page (or refresh) response for one status column.
     */
    public function kanban_column()
    {
        if (!$this->input->is_ajax_request()) show_404();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->kanban_column_data(), JSON_UNESCAPED_UNICODE);
    }

    /** One read-only request for the same board, column pages and native card rendering. */
    public function kanban_board()
    {
        if (!$this->input->is_ajax_request() || strtoupper($this->input->method()) !== 'GET') show_404();
        $statuses = siba_leads_kanban_statuses();
        $currency = get_base_currency();
        // Resolve the pipeline/session defaults before releasing this read-only request's lock.
        siba_leads_get_summary();
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->kanban_board_data($statuses, $currency), JSON_UNESCAPED_UNICODE);
    }

    private function kanban_board_data(array $statuses, $currency): array
    {
        $summary = siba_leads_get_summary();
        $totals = [];
        foreach ($summary as $item) if (isset($item['id'])) $totals[(int) $item['id']] = (int) $item['total'];
        $limit = max(1, (int) get_option('leads_kanban_limit'));
        $saved = $_GET;
        $refresh = isset($saved['refresh']) && is_array($saved['refresh']) ? $saved['refresh'] : [];
        $columns = [];
        try {
            foreach ($statuses as $status) {
                if (!empty($status['isdefault'])) continue;
                $id = (int) $status['id'];
                $_GET['status'] = $id;
                $_GET['total_pages'] = (int) ceil(($totals[$id] ?? 0) / $limit);
                if (isset($refresh[$id]) && is_scalar($refresh[$id])) $_GET['refresh'] = $refresh[$id];
                else unset($_GET['refresh']);
                $columns[$id] = $this->kanban_column_data();
            }
        } finally {
            $_GET = $saved;
        }
        return ['success' => true, 'html' => $this->load->view('siba_leads/kan-ban', [
            'statuses' => $statuses, 'base_currency' => $currency, 'summary' => $summary,
            'initial_columns' => $columns,
        ], true)];
    }

    private function kanban_column_data(): array
    {
        $statusId = (int) $this->input->get('status');
        $this->db->where(db_prefix() . 'leads_status.id', $statusId);
        $status = $this->db->get(db_prefix() . 'leads_status')->row_array();

        if (!$status || !empty($status['isdefault'])) {
            return ['success' => false, 'html' => '', 'count' => 0, 'page' => 1, 'total_pages' => 0];
        }

        $kanBan = siba_leads_kanban_for_status($status['id'])
            ->search($this->input->get('search'))
            ->sortBy(
                $this->input->get('sort_by'),
                $this->input->get('sort')
            );
        if (function_exists('siba_leads_kanban_apply_tag_filter')) {
            $kanBan = siba_leads_kanban_apply_tag_filter($kanBan);
        }
        if (function_exists('siba_leads_kanban_apply_urgency_filter')) {
            $kanBan = siba_leads_kanban_apply_urgency_filter($kanBan);
        }
        if (function_exists('siba_leads_kanban_apply_assigned_filter')) {
            $kanBan = siba_leads_kanban_apply_assigned_filter($kanBan);
        }

        $refresh = $this->input->get('refresh');
        if ($refresh !== null && $refresh !== '') {
            $kanBan->refresh($refresh);
        }

        $leads         = $kanBan->get();
        $base_currency = get_base_currency();
        $html          = '';
        foreach ($leads as $lead) {
            $html .= $this->load->view('siba_leads/_kan_ban_card', [
                'lead'          => $lead,
                'status'        => $status,
                'base_currency' => $base_currency,
            ], true);
        }

        $searchQ     = $this->input->get('search');
        // Only user-requested tags (not the default pipeline tag) — shell summary already includes pipeline tag.
        $tagFilter   = function_exists('siba_leads_request_tag_filter')
            ? siba_leads_request_tag_filter()
            : ['tag_ids' => [], 'tag_none' => false];
        $hasTagFilter = !empty($tagFilter['tag_none']) || !empty($tagFilter['tag_ids']);
        $urgencyFilter = function_exists('siba_leads_request_urgency_filter')
            ? siba_leads_request_urgency_filter()
            : '';
        $assignedFilter = function_exists('siba_leads_request_assigned_filter')
            ? siba_leads_request_assigned_filter()
            : null;
        $limit       = max(1, (int) $kanBan->limit());
        $count       = count($leads);
        $total_pages = (int) $this->input->get('total_pages');
        // Search/tag/urgency/assignee filter needs a real count; otherwise trust shell total_pages from the summary.
        if (($searchQ !== null && $searchQ !== '') || $hasTagFilter || $urgencyFilter !== '' || $assignedFilter !== null) {
            $total_pages = (int) $kanBan->totalPages();
        } elseif ($total_pages < 0) {
            $total_pages = 0;
        }

        $page = max(1, (int) $kanBan->getPage());
        // When refresh loads multiple pages worth of cards, treat as that page for load-more.
        if ($refresh !== null && $refresh !== '' && $limit > 0) {
            $page = max(1, (int) ceil($count / $limit));
        }

        return [
            'success'     => true,
            'status'      => (int) $status['id'],
            'html'        => $html,
            'count'       => $count,
            'page'        => $page,
            'total_pages' => $total_pages,
            'has_more'    => $total_pages > $page,
        ];
    }

    public function kanban_load_more()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $statusId = $this->input->get('status');
        $page     = $this->input->get('page');

        $this->db->where(db_prefix() . 'leads_status.id', (int) $statusId);
        $status = $this->db->get(db_prefix() . 'leads_status')->row_array();

        if (!$status) {
            return;
        }

        $kanBan = siba_leads_kanban_for_status($status['id'])
            ->search($this->input->get('search'))
            ->sortBy(
                $this->input->get('sort_by'),
                $this->input->get('sort')
            )
            ->page($page);
        if (function_exists('siba_leads_kanban_apply_tag_filter')) {
            $kanBan = siba_leads_kanban_apply_tag_filter($kanBan);
        }
        if (function_exists('siba_leads_kanban_apply_urgency_filter')) {
            $kanBan = siba_leads_kanban_apply_urgency_filter($kanBan);
        }
        if (function_exists('siba_leads_kanban_apply_assigned_filter')) {
            $kanBan = siba_leads_kanban_apply_assigned_filter($kanBan);
        }
        $leads = $kanBan->get();

        foreach ($leads as $lead) {
            $this->load->view('siba_leads/_kan_ban_card', [
                'lead'          => $lead,
                'status'        => $status,
                'base_currency' => get_base_currency(),
            ]);
        }
    }

    /**
     * Lazy-loaded expand panel HTML for a kanban card (details + website form meta).
     *
     * @param int $id Lead id
     */
    public function kanban_card_details($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        if (!is_admin() && !staff_can('view', SIBA_LEADS_MODULE_NAME)) {
            ajax_access_denied();
        }

        $id = (int) $id;
        // Same visibility as the board: view_all or assigned to this lead.
        if ($id < 1 || !siba_leads_can_manage_lead($id)) {
            ajax_access_denied();
        }

        $lead = $this->leads_model->get($id);
        if (!$lead) {
            show_404();
        }

        $leadArr = is_object($lead) ? (array) $lead : $lead;
        echo $this->load->view('siba_leads/_kan_ban_card_details', [
            'lead' => $leadArr,
        ], true);
    }

    /**
     * AI Persian sales call-guide for a kanban lead (OpenRouter).
     *
     * @param int $id Lead id
     */
    public function ai_call_guide($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        if (!is_admin() && !staff_can('ai_call_guide', SIBA_LEADS_MODULE_NAME)) {
            ajax_access_denied();
        }

        if (!is_admin() && !staff_can('view', SIBA_LEADS_MODULE_NAME)) {
            ajax_access_denied();
        }

        $id = (int) $id;
        if ($id < 1 || !siba_leads_can_manage_lead($id)) {
            ajax_access_denied();
        }

        $this->load->library('siba_leads/Siba_leads_ai_call_guide', null, 'siba_leads_ai_call_guide');
        $result = $this->siba_leads_ai_call_guide->generate_for_lead($id);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => !empty($result['success']),
            'content' => (string) ($result['content'] ?? ''),
            'message' => (string) ($result['message'] ?? ''),
            'prompt' => (string) ($result['prompt'] ?? ''),
            'lead_id' => $id,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * JSON urgency payload for live kanban card updates (e.g. after task due date change).
     *
     * @param int $id Lead id
     */
    public function kanban_card_urgency($id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        if (!is_admin() && !staff_can('view', SIBA_LEADS_MODULE_NAME)) {
            ajax_access_denied();
        }

        $id = (int) $id;
        if ($id < 1 || !siba_leads_can_manage_lead($id)) {
            ajax_access_denied();
        }

        $payload = function_exists('siba_leads_lead_card_urgency')
            ? siba_leads_lead_card_urgency($id)
            : null;

        if (!$payload) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false]);

            return;
        }

        $payload['success'] = true;
        header('Content-Type: application/json');
        echo json_encode($payload);
    }

    public function update_lead_status()
    {
        if (!$this->input->post() || !$this->input->is_ajax_request()) {
            show_404();
        }

        if (!is_admin() && !staff_can('edit', SIBA_LEADS_MODULE_NAME) && staff_cant('edit', 'leads')) {
            ajax_access_denied();
        }

        $leadId   = (int) $this->input->post('leadid');
        $statusId = (int) $this->input->post('status');
        if ($leadId < 1 || $statusId < 1 || !siba_leads_can_manage_lead($leadId)) {
            ajax_access_denied();
        }

        $this->db->where(db_prefix() . 'leads_status.id', $statusId);
        $statusRow = $this->db->get(db_prefix() . 'leads_status')->row();
        if (!$statusRow || !empty($statusRow->isdefault)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => _l('siba_leads_move_failed'),
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        $this->leads_model->update_lead_status($this->input->post());

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'leadid'  => $leadId,
            'status'  => $statusId,
        ], JSON_UNESCAPED_UNICODE);
    }

    public function update_status_order()
    {
        if ($post_data = $this->input->post()) {
            if (!is_admin()) {
                ajax_access_denied();
            }

            $this->leads_model->update_status_order($post_data);
        }
    }

    /**
     * Map Perfex roles to company teams (sales / support / tech / ...).
     */
    public function teams()
    {
        if (!siba_leads_can_manage_teams()) {
            access_denied(SIBA_LEADS_MODULE_NAME);
        }

        if ($this->input->post()) {
            siba_leads_save_team_org_units($this->input->post('org_units') ?: []);
            siba_leads_save_team_roles($this->input->post('teams') ?: []);
            update_option(
                SIBA_LEADS_AUTO_ASSIGN_OPTION,
                $this->input->post('siba_leads_auto_assign_sales') === '1' ? '1' : '0'
            );
            set_alert('success', _l('updated_successfully', _l('siba_leads_teams')));
            redirect(admin_url('siba_leads/teams'));
        }

        $definitions = siba_leads_team_definitions();
        $roleMap     = siba_leads_get_team_roles_map();
        $orgMap      = siba_leads_get_team_org_units_map();
        $teams       = [];

        foreach ($definitions as $key => $def) {
            $orgUnitId = !empty($orgMap[$key]) ? (int) $orgMap[$key] : 0;
            $roleId    = !empty($roleMap[$key]) ? (int) $roleMap[$key] : (siba_leads_get_team_role_id($key) ?: 0);
            $members   = siba_leads_get_team_members($key, true);
            $role      = $roleId ? siba_leads_get_role($roleId) : null;

            $teams[$key] = [
                'key'         => $key,
                'label'       => $def['label'],
                'org_unit_id' => $orgUnitId,
                'role_id'     => $roleId,
                'role_name'   => $role->name ?? '',
                'members'     => $members,
                'member_ids'  => array_map(static function ($m) {
                    return (int) $m['staffid'];
                }, $members),
                'source'      => $orgUnitId ? 'org' : ($roleId ? 'role' : 'none'),
            ];
        }

        $data['title']              = _l('siba_leads_teams');
        $data['teams']              = $teams;
        $data['roles']              = siba_leads_get_all_roles();
        $data['org_units']          = function_exists('siba_webkit_org_units_select_options')
            ? siba_webkit_org_units_select_options()
            : [];
        $data['auto_assign_sales']  = siba_leads_auto_assign_enabled();
        $data['backlog_status_id']  = siba_leads_ensure_backlog_status();
        $data['sales_member_count'] = count(siba_leads_get_team_member_ids('sales', true));

        $this->load->view('siba_leads/teams', $data);
    }

    /**
     * CRUD: lead failure reasons (title + color).
     */
    public function failure_reasons()
    {
        if (!is_admin()) {
            access_denied(SIBA_LEADS_MODULE_NAME);
        }

        if (function_exists('siba_leads_ensure_failure_reasons_table')) {
            siba_leads_ensure_failure_reasons_table();
        }

        $this->load->model('siba_leads/siba_leads_failure_reasons_model');

        $data['title']   = _l('siba_leads_failure_reasons');
        $data['reasons'] = $this->siba_leads_failure_reasons_model->get();

        $this->load->view('siba_leads/failure_reasons/manage', $data);
    }

    public function failure_reason_modal($id = '')
    {
        if (!is_admin() || !$this->input->is_ajax_request()) {
            show_404();
        }

        $this->load->model('siba_leads/siba_leads_failure_reasons_model');

        $data['reason'] = null;
        if ($id !== '') {
            $data['reason'] = $this->siba_leads_failure_reasons_model->get_row($id);
        }

        echo json_encode([
            'success' => true,
            'view'    => $this->load->view('siba_leads/failure_reasons/modal', $data, true),
        ]);
    }

    public function failure_reason_save()
    {
        if (!is_admin()) {
            access_denied(SIBA_LEADS_MODULE_NAME);
        }

        if (!$this->input->post()) {
            redirect(admin_url('siba_leads/failure_reasons'));
        }

        $this->load->model('siba_leads/siba_leads_failure_reasons_model');

        $id    = (int) $this->input->post('id');
        $title = trim((string) $this->input->post('title'));
        $color = trim((string) $this->input->post('color'));

        if ($title === '') {
            set_alert('warning', _l('siba_leads_failure_reason_title_required'));
            redirect(admin_url('siba_leads/failure_reasons'));
        }

        if ($color === '' || !preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            $color = '#6b7280';
        }

        $payload = [
            'title' => $title,
            'color' => strtolower($color),
        ];

        if ($id > 0) {
            $this->siba_leads_failure_reasons_model->update($id, $payload);
            set_alert('success', _l('updated_successfully', _l('siba_leads_failure_reason')));
        } else {
            $payload['datecreated'] = date('Y-m-d H:i:s');
            $this->siba_leads_failure_reasons_model->add($payload);
            set_alert('success', _l('added_successfully', _l('siba_leads_failure_reason')));
        }

        redirect(admin_url('siba_leads/failure_reasons'));
    }

    public function failure_reason_delete($id = '')
    {
        if (!is_admin()) {
            access_denied(SIBA_LEADS_MODULE_NAME);
        }

        $id = (int) $id;
        if ($id < 1) {
            redirect(admin_url('siba_leads/failure_reasons'));
        }

        $this->load->model('siba_leads/siba_leads_failure_reasons_model');
        $this->siba_leads_failure_reasons_model->delete($id);
        set_alert('success', _l('deleted', _l('siba_leads_failure_reason')));
        redirect(admin_url('siba_leads/failure_reasons'));
    }

    /**
     * CRUD: lead tags (Perfex global tags table, used with rel_type=lead).
     */
    public function tags()
    {
        if (!is_admin()) {
            access_denied(SIBA_LEADS_MODULE_NAME);
        }

        $prefix = db_prefix();
        $tags   = function_exists('get_tags') ? get_tags() : [];
        foreach ($tags as &$tag) {
            $tag['usage_count'] = (int) total_rows($prefix . 'taggables', [
                'tag_id'   => (int) $tag['id'],
                'rel_type' => 'lead',
            ]);
        }
        unset($tag);

        $data['title'] = _l('siba_leads_tags');
        $data['tags']  = $tags;

        $this->load->view('siba_leads/tags/manage', $data);
    }

    public function tag_modal($id = '')
    {
        if (!is_admin() || !$this->input->is_ajax_request()) {
            show_404();
        }

        $data['tag'] = null;
        $id = (int) $id;
        if ($id > 0) {
            $row = $this->db->where('id', $id)->get(db_prefix() . 'tags')->row_array();
            $data['tag'] = $row ?: null;
        }

        echo json_encode([
            'success' => true,
            'view'    => $this->load->view('siba_leads/tags/modal', $data, true),
        ]);
    }

    public function tag_save()
    {
        if (!is_admin()) {
            access_denied(SIBA_LEADS_MODULE_NAME);
        }

        if (!$this->input->post()) {
            redirect(admin_url('siba_leads/tags'));
        }

        $id   = (int) $this->input->post('id');
        $name = trim((string) $this->input->post('name'));
        $name = str_replace('"', "'", $name);

        if ($name === '') {
            set_alert('warning', _l('siba_leads_tag_name_required'));
            redirect(admin_url('siba_leads/tags'));
        }

        $this->db->where('name', $name);
        if ($id > 0) {
            $this->db->where('id !=', $id);
        }
        $exists = $this->db->get(db_prefix() . 'tags')->row();
        if ($exists) {
            set_alert('warning', _l('tags_update_replace_warning'));
            redirect(admin_url('siba_leads/tags'));
        }

        if ($id > 0) {
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'tags', ['name' => $name]);
            if (function_exists('siba_leads_clear_tags_cache')) {
                siba_leads_clear_tags_cache();
            }
            set_alert('success', _l('updated_successfully', _l('siba_leads_tag')));
        } else {
            $this->db->insert(db_prefix() . 'tags', ['name' => $name]);
            $newId = (int) $this->db->insert_id();
            if ($newId > 0) {
                hooks()->do_action('new_tag_created', $newId);
            }
            if (function_exists('siba_leads_clear_tags_cache')) {
                siba_leads_clear_tags_cache();
            }
            set_alert('success', _l('added_successfully', _l('siba_leads_tag')));
        }

        redirect(admin_url('siba_leads/tags'));
    }

    public function tag_delete($id = '')
    {
        if (!is_admin()) {
            access_denied(SIBA_LEADS_MODULE_NAME);
        }

        $id = (int) $id;
        if ($id < 1) {
            redirect(admin_url('siba_leads/tags'));
        }

        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'tags');
        $this->db->where('tag_id', $id);
        $this->db->delete(db_prefix() . 'taggables');
        if (function_exists('siba_leads_clear_tags_cache')) {
            siba_leads_clear_tags_cache();
        }

        set_alert('success', _l('deleted', _l('siba_leads_tag')));
        redirect(admin_url('siba_leads/tags'));
    }

    /**
     * AI call-guide (OpenRouter) settings — API key + model.
     */
    public function ai_settings()
    {
        if ($this->input->post('save_ai_settings')) {
            $postedKey = trim((string) $this->input->post('openrouter_api_key'));
            if ($postedKey !== '') {
                update_option('siba_leads_openrouter_api_key', $postedKey);
            }

            $this->load->library('siba_leads/Siba_leads_ai_call_guide', null, 'siba_leads_ai_call_guide');
            $defaultModel = Siba_leads_ai_call_guide::DEFAULT_MODEL;

            $model = trim((string) $this->input->post('openrouter_model'));
            if ($model === '') {
                $model = $defaultModel;
            }
            update_option('siba_leads_openrouter_model', $model);

            update_option(
                'siba_leads_openrouter_ssl_verify',
                $this->input->post('openrouter_ssl_verify') === '1' ? '1' : '0'
            );

            set_alert('success', _l('settings_updated'));
            redirect(admin_url('siba_leads/ai_settings'));
        }

        $this->load->library('siba_leads/Siba_leads_ai_call_guide', null, 'siba_leads_ai_call_guide');
        $defaultModel = Siba_leads_ai_call_guide::DEFAULT_MODEL;

        $apiKey = trim((string) get_option('siba_leads_openrouter_api_key'));
        $model  = trim((string) get_option('siba_leads_openrouter_model'));
        if ($model === '') {
            $model = $defaultModel;
        }

        $sslOpt = get_option('siba_leads_openrouter_ssl_verify');
        if ($sslOpt === false || $sslOpt === null || $sslOpt === '') {
            $sslVerify = function_exists('siba_leads_didar_ssl_verify_enabled')
                ? siba_leads_didar_ssl_verify_enabled()
                : true;
        } else {
            $sslVerify = (string) $sslOpt === '1';
        }

        $data['title']                 = _l('siba_leads_ai_settings');
        $data['openrouter_model']      = $model;
        $data['api_key_configured']    = $apiKey !== '';
        $data['openrouter_ssl_verify'] = $sslVerify;

        $this->load->view('siba_leads/ai_settings', $data);
    }

    /**
     * Didar CRM → Siba Leads migration settings and actions.
     */
    public function didar()
    {
        $ajaxAction = trim((string) $this->input->post('didar_ajax_action'));
        if ($ajaxAction !== '' && $this->input->is_ajax_request()) {
            $this->respond_didar_ajax($ajaxAction);

            return;
        }

        if ($this->input->post('save_didar_settings')) {
            update_option(SIBA_LEADS_DIDAR_BASE_URL_OPTION, trim((string) $this->input->post('didar_base_url')));
            update_option(SIBA_LEADS_DIDAR_API_KEY_OPTION, trim((string) $this->input->post('didar_api_key')));
            update_option(SIBA_LEADS_DIDAR_PIPELINE_OPTION, trim((string) $this->input->post('didar_pipeline_id')));
            update_option(
                SIBA_LEADS_DIDAR_SSL_VERIFY_OPTION,
                $this->input->post('didar_ssl_verify') === '1' ? '1' : '0'
            );
            update_option(
                SIBA_LEADS_DIDAR_STAFF_FIELD_SLUG_OPTION,
                trim((string) $this->input->post('didar_staff_field_slug'))
            );
            update_option(
                SIBA_LEADS_DIDAR_STAFF_MAP_OPTION,
                trim((string) $this->input->post('didar_staff_map_json'))
            );
            set_alert('success', _l('settings_updated'));
            redirect(admin_url('siba_leads/didar'));
        }

        $this->load->library('siba_leads/Siba_leads_didar_client', [], 'didar_client');
        $pipelines = siba_leads_didar_api_key() !== ''
            ? $this->didar_client->list_deal_pipelines()
            : [];

        $data['title']            = _l('siba_leads_didar_migration');
        $data['didar_base_url']   = siba_leads_didar_base_url();
        $data['didar_api_key']    = siba_leads_didar_api_key();
        $data['didar_pipeline_id']  = trim((string) get_option(SIBA_LEADS_DIDAR_PIPELINE_OPTION));
        $data['didar_ssl_verify']   = siba_leads_didar_ssl_verify_enabled();
        $data['didar_staff_field_slug'] = trim((string) get_option(SIBA_LEADS_DIDAR_STAFF_FIELD_SLUG_OPTION));
        $data['didar_staff_map_json']   = (string) get_option(SIBA_LEADS_DIDAR_STAFF_MAP_OPTION);
        $data['pipelines']          = $pipelines;

        $this->load->view('siba_leads/didar', $data);
    }

    public function didar_test()
    {
        $this->respond_didar_ajax('test');
    }

    public function didar_wipe()
    {
        $this->respond_didar_ajax('wipe');
    }

    /** @deprecated Use POST siba_leads/didar with didar_ajax_action=migrate */
    public function didar_import()
    {
        $this->respond_didar_ajax('migrate');
    }

    public function didar_migrate()
    {
        $this->respond_didar_ajax('migrate');
    }

    /**
     * @param string $action test|wipe|migrate
     */
    protected function respond_didar_ajax($action)
    {
        if (!$this->input->is_ajax_request() || !$this->input->post()) {
            show_404();
        }

        $action = strtolower(trim($action));

        if ($action === 'test') {
            $this->load->library('siba_leads/Siba_leads_didar_client', [], 'didar_client');
            $result = $this->didar_client->test_connection();
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode($result, JSON_UNESCAPED_UNICODE));

            return;
        }

        if ($action === 'wipe') {
            $confirm = trim((string) $this->input->post('confirm'));
            if ($confirm !== 'WIPE') {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'ok'      => false,
                        'message' => _l('siba_leads_didar_wipe_confirm_invalid'),
                    ], JSON_UNESCAPED_UNICODE));

                return;
            }

            $stats = siba_leads_didar_wipe_workspace();
            log_activity('Siba Leads Didar wipe: ' . json_encode($stats));
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'ok'      => true,
                    'message' => _l('siba_leads_didar_wipe_done'),
                    'stats'   => $stats,
                ], JSON_UNESCAPED_UNICODE));

            return;
        }

        if ($action === 'migrate' || $action === 'import') {
            $this->respond_didar_migrate_batch();

            return;
        }

        if ($action === 'preview') {
            $this->respond_didar_preview();

            return;
        }

        if ($action === 'sync_staff') {
            $this->load->library('siba_leads/Siba_leads_didar_client', [], 'didar_client');
            $users = $this->didar_client->list_users();
            $stats = siba_leads_didar_sync_staff_didar_ids($users, false);
            $built = siba_leads_didar_build_staff_map($users, false);
            $log = [
                siba_leads_didar_log_entry(
                    'success',
                    sprintf(
                        _l('siba_leads_didar_log_staff_didar_id_sync'),
                        (string) ($stats['updated'] ?? 0),
                        (string) ($stats['skipped'] ?? 0),
                        (string) ($stats['unmatched'] ?? 0)
                    )
                ),
                siba_leads_didar_log_entry(
                    'info',
                    sprintf(_l('siba_leads_didar_log_staff_map'), (string) count($built['map'] ?? []))
                ),
            ];
            foreach (($built['preview'] ?? []) as $line) {
                $log[] = siba_leads_didar_log_entry('info', (string) $line);
            }
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'ok'      => true,
                    'message' => _l('siba_leads_didar_sync_staff_done'),
                    'stats'   => $stats,
                    'mapped'  => count($built['map'] ?? []),
                    'log'     => $log,
                ], JSON_UNESCAPED_UNICODE));

            return;
        }

        if ($action === 'sync_sources') {
            $this->load->library('siba_leads/Siba_leads_didar_client', [], 'didar_client');
            $didarSources = $this->didar_client->list_sources();
            if ($didarSources === []) {
                $this->output
                    ->set_content_type('application/json')
                    ->set_output(json_encode([
                        'ok'      => false,
                        'message' => _l('siba_leads_didar_sync_sources_empty'),
                        'log'     => [
                            siba_leads_didar_log_entry('error', _l('siba_leads_didar_sync_sources_empty')),
                        ],
                    ], JSON_UNESCAPED_UNICODE));

                return;
            }

            $synced = siba_leads_didar_sync_lead_sources($didarSources);
            $stats  = $synced['stats'] ?? [];
            $titles = $stats['titles'] ?? [];
            $log = [
                siba_leads_didar_log_entry(
                    'success',
                    sprintf(
                        _l('siba_leads_didar_log_sources_sync'),
                        (string) ($stats['created'] ?? 0),
                        (string) ($stats['existing'] ?? 0),
                        (string) ($stats['skipped'] ?? 0),
                        (string) count($synced['map'] ?? [])
                    )
                ),
            ];
            if ($titles !== []) {
                $log[] = siba_leads_didar_log_entry(
                    'info',
                    _l('siba_leads_didar_log_sources_titles') . ': ' . implode(' | ', $titles)
                );
            }

            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode([
                    'ok'      => true,
                    'message' => _l('siba_leads_didar_sync_sources_done'),
                    'stats'   => $stats,
                    'mapped'  => count($synced['map'] ?? []),
                    'log'     => $log,
                ], JSON_UNESCAPED_UNICODE));

            return;
        }

        show_404();
    }

    protected function respond_didar_preview()
    {
        try {
            $this->load->library('siba_leads/Siba_leads_didar_client', [], 'didar_client');
            siba_leads_didar_ensure_mapping_tables();

            $scoped = array_fill_keys(siba_leads_didar_scoped_pipeline_ids(), true);
            $all    = $this->didar_client->list_deal_pipelines();
            $rows   = [];
            $log    = [];

            foreach ($all as $pipeline) {
                if (!is_array($pipeline)) {
                    continue;
                }
                $id = trim((string) ($pipeline['Id'] ?? ''));
                if ($id === '' || !isset($scoped[$id])) {
                    continue;
                }
                $mapped = siba_leads_didar_upsert_pipeline_map($pipeline);
                $pending = $this->didar_client->count_pending_deals($id);
                $rows[] = [
                    'id'      => $id,
                    'title'   => (string) ($mapped['title'] ?? $pipeline['Title'] ?? $id),
                    'tag'     => (string) ($mapped['tag_name'] ?? ''),
                    'pending' => $pending,
                ];
                $log[] = siba_leads_didar_log_entry(
                    'info',
                    sprintf(
                        _l('siba_leads_didar_log_pipeline_pending'),
                        (string) ($mapped['title'] ?? $id),
                        (string) $pending
                    )
                );
            }

            if ($rows === []) {
                $this->output_didar_json([
                    'ok'      => false,
                    'message' => _l('siba_leads_didar_scoped_pipelines_missing'),
                    'rows'    => [],
                    'log'     => [
                        siba_leads_didar_log_entry('error', _l('siba_leads_didar_scoped_pipelines_missing')),
                    ],
                ]);

                return;
            }

            $this->output_didar_json([
                'ok'      => true,
                'message' => _l('siba_leads_didar_preview_done'),
                'rows'    => $rows,
                'log'     => $log,
            ]);
        } catch (Throwable $e) {
            $this->output_didar_json([
                'ok'      => false,
                'message' => $e->getMessage(),
                'rows'    => [],
                'log'     => [
                    siba_leads_didar_log_entry('error', $e->getMessage()),
                ],
            ]);
        }
    }

    protected function respond_didar_migrate_batch()
    {
        try {
            // Import batches call Didar API + many DB writes; 120s is too low on local/Windows.
            @ini_set('max_execution_time', '600');
            @set_time_limit(600);
            ignore_user_abort(true);

            $this->load->library('siba_leads/Siba_leads_didar_importer', [], 'didar_importer');

            $step   = strtolower(trim((string) $this->input->post('migrate_step')));
            $offset = (int) $this->input->post('migrate_offset');
            $batch  = (int) $this->input->post('migrate_batch');
            // Page size for Didar search (skips are cheap; imports are capped inside importer).
            if ($batch < 1) {
                $batch = 15;
            }
            $batch = min(25, $batch);

            if ($step === 'init' || $step === '') {
                siba_leads_didar_import_run_clear();
                $result = $this->didar_importer->run_prepare();
            } else {
                $result = $this->didar_importer->run_deals_batch($offset, $batch);
            }

            if (!empty($result['done']) && !empty($result['ok'])) {
                log_activity('Siba Leads Didar import: ' . json_encode($result['stats'] ?? []));
            }

            $this->output_didar_json($result);
        } catch (Throwable $e) {
            $this->output_didar_json([
                'ok'      => false,
                'done'    => true,
                'message' => $e->getMessage(),
                'log'     => [
                    siba_leads_didar_log_entry(
                        'error',
                        $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine()
                    ),
                ],
            ]);
        }
    }

    /**
     * @param array<string,mixed> $payload
     */
    protected function output_didar_json(array $payload): void
    {
        $this->output
            ->set_content_type('application/json; charset=utf-8')
            ->set_output(json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    /**
     * CSV import for leads (mirrors core leads/import, hosted in siba_leads).
     */
    public function import()
    {
        if (!is_admin() && get_option('allow_non_admin_members_to_import_leads') != '1') {
            access_denied('Leads Import');
        }

        $this->load->model('staff_model');

        $dbFields = $this->db->list_fields(db_prefix() . 'leads');
        array_push($dbFields, 'tags');

        $this->load->library('siba_leads/Import_siba_leads', [], 'import');
        $this->import->setDatabaseFields($dbFields)
            ->setCustomFields(get_custom_fields('leads'));

        if ($this->input->post('download_sample') === 'true') {
            $this->import->downloadSample();
        }

        if ($this->input->post()
            && isset($_FILES['file_csv']['name']) && $_FILES['file_csv']['name'] != '') {
            $this->import->setSimulation($this->input->post('simulate'))
                ->setTemporaryFileLocation($_FILES['file_csv']['tmp_name'])
                ->setFilename($_FILES['file_csv']['name'])
                ->perform();

            $data['total_rows_post'] = $this->import->totalRows();

            if (!$this->import->isSimulation()) {
                set_alert('success', _l('import_total_imported', $this->import->totalImported()));
            }
        }

        $data['statuses'] = $this->leads_model->get_status();
        $data['sources']  = $this->leads_model->get_source();
        $data['members']  = $this->staff_model->get('', ['is_not_staff' => 0, 'active' => 1]);
        $data['title']    = _l('import_leads');

        $this->load->view('siba_leads/import', $data);
    }

    /**
     * Return the staff assigned to a lead (for defaulting new task assignees).
     */
    public function lead_assignee($id = '')
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $leadId = (int) $id;
        if ($leadId <= 0) {
            echo json_encode(['success' => false, 'assigned' => 0]);

            return;
        }

        $this->db->select('assigned');
        $this->db->where('id', $leadId);
        $lead = $this->db->get(db_prefix() . 'leads')->row();

        echo json_encode([
            'success'  => (bool) $lead,
            'assigned' => $lead ? (int) $lead->assigned : 0,
        ]);
    }

    /**
     * AJAX: soft phone check — never blocks save.
     * Returns { ok, available, is_duplicate, existing_id, message }.
     */
    public function validate_phone()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $exclude = siba_leads_resolve_lead_exclude_id_from_request();
        $info    = function_exists('siba_leads_phone_duplicate_info')
            ? siba_leads_phone_duplicate_info($this->input->post('phonenumber'), $exclude)
            : ['is_duplicate' => false, 'existing_id' => 0];

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'ok'           => true,
                'available'    => empty($info['is_duplicate']),
                'is_duplicate' => !empty($info['is_duplicate']),
                'existing_id'  => !empty($info['existing_id']) ? (int) $info['existing_id'] : null,
                'message'      => !empty($info['is_duplicate'])
                    ? siba_leads_duplicate_phone_warning_message((int) ($info['existing_id'] ?: 0))
                    : '',
            ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * AJAX: cities for a province (used when siba_license is inactive).
     */
    public function get_cities()
    {
        $province_id = (int) ($this->input->post('province_id') ?: $this->input->get('province_id'));
        $cities      = function_exists('siba_leads_get_active_cities')
            ? siba_leads_get_active_cities($province_id)
            : [];

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode($cities, JSON_UNESCAPED_UNICODE));
    }

    /**
     * AJAX: unified lead timeline (notes + activity + tasks + task reports).
     *
     * @param int $lead_id
     */
    public function timeline($lead_id = 0)
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $lead_id = (int) $lead_id;
        if ($lead_id < 1) {
            ajax_access_denied();
        }

        if (!$this->leads_model->staff_can_access_lead($lead_id)) {
            ajax_access_denied();
        }

        $items = function_exists('siba_leads_build_lead_timeline')
            ? siba_leads_build_lead_timeline($lead_id)
            : [];

        $this->load->view('siba_leads/partials/lead_timeline', [
            'items'   => $items,
            'lead_id' => $lead_id,
        ]);
    }
}
