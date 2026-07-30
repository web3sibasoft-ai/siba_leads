<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Siba_leads extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if (!is_admin() && !staff_can('view', SIBA_LEADS_MODULE_NAME)) {
            access_denied(SIBA_LEADS_MODULE_NAME);
        }
    }

    public function index()
    {
        $data['title'] = _l('siba_leads_workspace');
        $data['repo_url'] = get_option('siba_leads_repo_url');
        $this->load->view('siba_leads/manage', $data);
    }
}
