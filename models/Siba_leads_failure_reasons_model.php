<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Siba_leads_failure_reasons_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function table()
    {
        return db_prefix() . 'siba_leads_failure_reasons';
    }

    public function get($id = '')
    {
        if ($id !== '') {
            $this->db->where('id', (int) $id);
        }

        $this->db->order_by('id', 'ASC');

        return $this->db->get($this->table())->result_array();
    }

    public function get_row($id)
    {
        return $this->db
            ->where('id', (int) $id)
            ->get($this->table())
            ->row_array();
    }

    public function add($data)
    {
        $this->db->insert($this->table(), $data);

        return $this->db->insert_id();
    }

    public function update($id, $data)
    {
        $this->db->where('id', (int) $id);
        $this->db->update($this->table(), $data);

        return $this->db->affected_rows() > 0;
    }

    public function delete($id)
    {
        $this->db->where('id', (int) $id);
        $this->db->delete($this->table());

        return $this->db->affected_rows() > 0;
    }
}
