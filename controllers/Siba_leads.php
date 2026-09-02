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

        $this->load->view('siba_leads/manage', $data);
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
     * AJAX: true when the phone is free, false when another lead already has it.
     */
    public function validate_phone()
    {
        if (!$this->input->is_ajax_request()) {
            show_404();
        }

        $exclude = siba_leads_resolve_lead_exclude_id_from_request();
        $dup     = siba_leads_phone_is_duplicate($this->input->post('phonenumber'), $exclude);
        echo json_encode(!$dup);
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
