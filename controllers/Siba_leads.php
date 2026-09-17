<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Siba_leads extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        $method = $this->router->method;
        if (!in_array($method, ['validate_phone', 'get_cities'], true)) {
            if (!is_admin() && !staff_can('view', SIBA_LEADS_MODULE_NAME)) {
                access_denied(SIBA_LEADS_MODULE_NAME);
            }
        }

        if (!is_staff_member()) {
            access_denied(SIBA_LEADS_MODULE_NAME);
        }

        $this->load->model('leads_model');
        $this->load->helper(SIBA_LEADS_MODULE_NAME . '/siba_leads');
    }

    public function index()
    {
        close_setup_menu();

        $data['title']         = _l('siba_leads_kanban');
        $data['statuses']      = $this->leads_model->get_status();
        $data['summary']       = siba_leads_get_summary();
        $data['base_currency'] = get_base_currency();
        $data['bodyclass']     = 'kan-ban-body siba-leads-kanban-body';
        $data['sort']          = get_option('default_leads_kanban_sort_type') ?: 'ASC';
        $data['sort_by']       = get_option('default_leads_kanban_sort') ?: 'dateadded';
        $data['can_view_all']  = siba_leads_can_view_all();

        if (function_exists('siba_leads_ensure_failure_reasons_table')) {
            siba_leads_ensure_failure_reasons_table();
        }
        $this->load->model('siba_leads/siba_leads_failure_reasons_model');
        $data['failure_reasons'] = $this->siba_leads_failure_reasons_model->get();

        $this->load->view('siba_leads/manage', $data);
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

        $leadId   = (int) $this->input->post('lead_id');
        $reasonId = (int) $this->input->post('failure_reason_id');

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
            'failure_reason_id' => $reasonId,
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
     * Aggregated lead reports (analysis / incoming / pipeline / conversion / speed / sources / failures / sales).
     */
    public function reports()
    {
        close_setup_menu();

        if (function_exists('siba_leads_ensure_lead_columns')) {
            siba_leads_ensure_lead_columns();
        }
        if (function_exists('siba_leads_ensure_failure_reasons_table')) {
            siba_leads_ensure_failure_reasons_table();
        }

        $this->load->model('leads_model');
        $this->load->model('staff_model');

        $filters = siba_leads_reports_parse_filters($this->input);
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
        $data['report_tabs']  = siba_leads_reports_tabs();
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

        $data['statuses']      = $this->leads_model->get_status();
        $data['base_currency'] = get_base_currency();
        $data['summary']       = siba_leads_get_summary();

        echo $this->load->view('siba_leads/kan-ban', $data, true);
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

        $leads = siba_leads_kanban_for_status($status['id'])
            ->search($this->input->get('search'))
            ->sortBy(
                $this->input->get('sort_by'),
                $this->input->get('sort')
            )
            ->page($page)
            ->get();

        foreach ($leads as $lead) {
            $this->load->view('siba_leads/_kan_ban_card', [
                'lead'          => $lead,
                'status'        => $status,
                'base_currency' => get_base_currency(),
            ]);
        }
    }

    public function update_lead_status()
    {
        if ($this->input->post() && $this->input->is_ajax_request()) {
            if (!is_admin() && !staff_can('edit', SIBA_LEADS_MODULE_NAME) && staff_cant('edit', 'leads')) {
                ajax_access_denied();
            }

            $leadId = (int) $this->input->post('leadid');
            if (!siba_leads_can_manage_lead($leadId)) {
                ajax_access_denied();
            }

            $this->leads_model->update_lead_status($this->input->post());
        }
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
        if (!is_admin()) {
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
}
