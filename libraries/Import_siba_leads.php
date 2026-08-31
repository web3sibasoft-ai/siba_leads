<?php

defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'libraries/import/Import_leads.php';

/**
 * Leads CSV import for the Siba Leads module.
 * Extends core import and coerces empty values for NOT NULL columns
 * (notably lm_follow_up) so CSV blanks do not become SQL NULL.
 */
class Import_siba_leads extends Import_leads
{
    protected $notNullDefaults = [
        'lm_follow_up'                       => 0,
        'converted_by_lead_manager'          => 0,
        'country'                            => 0,
        'assigned'                           => 0,
        'from_form_id'                       => 0,
        'lost'                               => 0,
        'junk'                               => 0,
        'last_lead_status'                   => 0,
        'is_imported_from_email_integration' => 0,
        'is_public'                          => 0,
        'client_id'                          => 0,
    ];

    /** @var array<string,bool> */
    protected $seenPhones = [];

    public function __construct()
    {
        parent::__construct();
        $this->addImportGuidelinesInfo(_l('siba_leads_import_duplicate_phone_info'));
    }

    public function perform()
    {
        $this->initialize();

        $databaseFields      = $this->getImportableDatabaseFields();
        $totalDatabaseFields = count($databaseFields);
        $uniqueFields        = json_decode(get_option('lead_unique_validation')) ?: [];

        foreach ($this->getRows() as $rowNumber => $row) {
            $insert = [];
            for ($i = 0; $i < $totalDatabaseFields; $i++) {
                $value = $this->checkNullValueAddedByUser($row[$i] ?? '');

                if ($databaseFields[$i] == 'name' && empty($value)) {
                    $value = '/';
                } elseif ($databaseFields[$i] == 'country') {
                    $value = $this->resolveCountryValue($value);
                } elseif ($databaseFields[$i] == 'source') {
                    $value = $this->sourceValue($value);
                } elseif ($databaseFields[$i] == 'status') {
                    $value = $this->statusValue($value);
                }

                $insert[$databaseFields[$i]] = $value;
            }

            $insert = $this->trimInsertValues($insert);
            $insert = $this->applyNotNullDefaults($insert);

            if (count($insert) === 0) {
                continue;
            }

            if ($this->isDuplicateByUniqueFields($insert, $uniqueFields) || $this->isDuplicatePhone($insert)) {
                continue;
            }

            $this->incrementImported();

            $id = null;

            if (!$this->isSimulation()) {
                if (!isset($insert['dateadded'])) {
                    $insert['dateadded'] = date('Y-m-d H:i:s');
                }

                if (!isset($insert['addedfrom'])) {
                    $insert['addedfrom'] = get_staff_user_id();
                }

                if ($this->ci->input->post('responsible')) {
                    $insert['assigned'] = $this->ci->input->post('responsible');
                }

                $tags = '';
                if (array_key_exists('tags', $insert)) {
                    $tags = $insert['tags'] ?? '';
                    unset($insert['tags']);
                }

                $this->ci->db->insert(db_prefix() . 'leads', $insert);
                $id = $this->ci->db->insert_id();

                if ($id) {
                    handle_tags_save($tags, $id, 'lead');
                    $this->rememberImportedPhone($insert['phonenumber'] ?? '');
                }
            } else {
                $this->rememberImportedPhone($insert['phonenumber'] ?? '');
                $this->simulationData[$rowNumber] = $this->formatSimulationRow($insert);
            }

            $this->handleCustomFieldsInsert($id, $row, $i, $rowNumber, 'leads');

            if ($this->isSimulation() && $rowNumber >= $this->maxSimulationRows) {
                break;
            }
        }
    }

    protected function failureRedirectURL()
    {
        return admin_url('siba_leads/import');
    }

    protected function applyNotNullDefaults(array $insert)
    {
        foreach ($this->notNullDefaults as $field => $default) {
            if (!array_key_exists($field, $insert)) {
                continue;
            }

            if ($insert[$field] === null || $insert[$field] === '') {
                $insert[$field] = $default;
            }
        }

        return $insert;
    }

    protected function isDuplicateByUniqueFields(array $data, array $uniqueFields)
    {
        foreach ($uniqueFields as $field) {
            if ((isset($data[$field]) && $data[$field] != '')
                && total_rows(db_prefix() . 'leads', [$field => $data[$field]]) > 0) {
                return true;
            }
        }

        return false;
    }

    protected function isDuplicatePhone(array $data)
    {
        $phone = $data['phonenumber'] ?? '';
        $normalized = function_exists('siba_leads_normalize_phone')
            ? siba_leads_normalize_phone($phone)
            : trim((string) $phone);

        if ($normalized === '') {
            return false;
        }

        if (!empty($this->seenPhones[$normalized])) {
            return true;
        }

        return function_exists('siba_leads_phone_is_duplicate')
            && siba_leads_phone_is_duplicate($phone);
    }

    protected function rememberImportedPhone($phone)
    {
        $normalized = function_exists('siba_leads_normalize_phone')
            ? siba_leads_normalize_phone($phone)
            : trim((string) $phone);
        if ($normalized !== '') {
            $this->seenPhones[$normalized] = true;
            if (function_exists('siba_leads_remember_created_phone')) {
                siba_leads_remember_created_phone($phone);
            }
        }
    }

    protected function resolveCountryValue($value)
    {
        if ($value != '') {
            if (!is_numeric($value)) {
                $this->ci->db->group_start()
                    ->where('iso2', $value)
                    ->or_where('short_name', $value)
                    ->or_where('long_name', $value)
                    ->group_end();
                $country = $this->ci->db->get(db_prefix() . 'countries')->row();
                $value   = $country ? $country->country_id : 0;
            }
        } else {
            $value = 0;
        }

        return $value;
    }

    protected function formatSimulationRow(array $values)
    {
        foreach ($values as $column => $val) {
            if ($column == 'country' && !empty($val) && is_numeric($val)) {
                $this->ci->db->where('country_id', $val);
                $country = $this->ci->db->get(db_prefix() . 'countries')->row();
                if ($country) {
                    $values[$column] = $country->short_name;
                }
            } elseif ($column == 'source') {
                $values[$column] = $this->findSource($val)['name'] ?? 'N/A';
            } elseif ($column == 'status') {
                $values[$column] = $this->findStatus($val)['name'] ?? 'N/A';
            }
        }

        return $values;
    }
}
