<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Siba_leads_didar_importer
{
    /** @var CI_Controller */
    protected $ci;

    /** @var Siba_leads_didar_client */
    protected $client;

    /** @var array<string,int> */
    protected $staffMap = [];

    /** @var array<string,int> */
    protected $stageStatusMap = [];

    /** @var array<string,int> */
    protected $activityTypeMap = [];

    /** @var int */
    protected $sourceId = 0;

    /** @var array<string,int> Didar SourceId → Perfex leads_sources.id */
    protected $sourceMap = [];

    /** @var array<int,array{level:string,time:string,message:string}> */
    protected $log = [];

    /**
     * @param array<string,mixed>|null $params Ignored; required for CodeIgniter load->library().
     */
    public function __construct($params = null)
    {
        $this->ci = &get_instance();
        $this->ci->load->model('leads_model');
        $this->ci->load->model('tasks_model');

        if (!function_exists('siba_leads_didar_base_url')) {
            $this->ci->load->helper('siba_leads/siba_leads');
        }

        $this->client = $this->load_didar_client();
    }

    protected function load_didar_client(): Siba_leads_didar_client
    {
        if (!class_exists('Siba_leads_didar_client', false)) {
            require_once __DIR__ . DIRECTORY_SEPARATOR . 'Siba_leads_didar_client.php';
        }

        return new Siba_leads_didar_client();
    }

    /**
     * Step 1: pipelines, statuses, maps — persisted for batch steps.
     *
     * @return array<string,mixed>
     */
    public function run_prepare(): array
    {
        @ini_set('max_execution_time', '600');
        @set_time_limit(600);
        $this->log = [];
        $GLOBALS['siba_leads_didar_import'] = true;

        if (siba_leads_didar_api_key() === '') {
            return $this->finish(false, _l('siba_leads_didar_api_key_required'), true);
        }

        $this->info(_l('siba_leads_didar_log_connecting'));

        $pipelineId = trim((string) get_option(SIBA_LEADS_DIDAR_PIPELINE_OPTION));
        $pipelines  = $this->client->list_deal_pipelines();
        if ($pipelines === []) {
            return $this->finish(false, _l('siba_leads_didar_no_pipelines'), true);
        }

        $pipeline = null;
        foreach ($pipelines as $p) {
            if ($pipelineId !== '' && ($p['Id'] ?? '') === $pipelineId) {
                $pipeline = $p;
                break;
            }
        }
        if ($pipeline === null) {
            $pipeline = $pipelines[0];
        }

        $this->info(sprintf(_l('siba_leads_didar_log_pipeline'), (string) ($pipeline['Title'] ?? '')));

        $this->info(_l('siba_leads_didar_log_loading_users'));
        $didarUsers     = $this->client->list_users();
        $staffBuilt     = siba_leads_didar_build_staff_map($didarUsers, true);
        $this->staffMap = is_array($staffBuilt['map'] ?? null) ? $staffBuilt['map'] : [];
        $this->info(sprintf(_l('siba_leads_didar_log_staff_map'), (string) count($this->staffMap)));
        $sync = is_array($staffBuilt['sync'] ?? null) ? $staffBuilt['sync'] : [];
        $this->info(sprintf(
            _l('siba_leads_didar_log_staff_didar_id_sync'),
            (string) ($sync['updated'] ?? 0),
            (string) ($sync['skipped'] ?? 0),
            (string) ($sync['unmatched'] ?? 0)
        ));
        $src = is_array($staffBuilt['sources'] ?? null) ? $staffBuilt['sources'] : [];
        $this->info(sprintf(
            _l('siba_leads_didar_log_staff_map_sources'),
            (string) ($src['didar_id'] ?? 0),
            (string) ($src['custom_field'] ?? 0),
            (string) ($src['manual_json'] ?? 0),
            (string) ($src['m_user'] ?? 0),
            (string) ($src['email'] ?? 0)
        ));
        foreach (($staffBuilt['preview'] ?? []) as $line) {
            $this->info((string) $line);
        }

        $this->info(_l('siba_leads_didar_log_activity_types'));
        $this->build_activity_type_map();
        $this->info(sprintf(_l('siba_leads_didar_log_activity_map'), (string) count($this->activityTypeMap)));

        $this->info(_l('siba_leads_didar_log_loading_sources'));
        $this->sourceId = siba_leads_didar_ensure_source_id();
        $didarSources   = $this->client->list_sources();
        $syncedSources  = siba_leads_didar_sync_lead_sources($didarSources);
        $this->sourceMap = $syncedSources['map'] ?? [];
        $srcStats        = $syncedSources['stats'] ?? [];
        $this->info(sprintf(_l('siba_leads_didar_log_sources_map'), (string) count($this->sourceMap)));
        $this->info(sprintf(
            _l('siba_leads_didar_log_sources_sync'),
            (string) ($srcStats['created'] ?? 0),
            (string) ($srcStats['existing'] ?? 0),
            (string) ($srcStats['skipped'] ?? 0),
            (string) count($this->sourceMap)
        ));
        if (!empty($srcStats['titles']) && is_array($srcStats['titles'])) {
            $this->info(_l('siba_leads_didar_log_sources_titles') . ': ' . implode(' | ', $srcStats['titles']));
        }
        if ($didarSources === []) {
            $this->info(_l('siba_leads_didar_sync_sources_empty'));
        }

        $statusCount = $this->import_pipeline_stages($pipeline);
        $this->info(sprintf(_l('siba_leads_didar_log_statuses_created'), (string) $statusCount));

        siba_leads_ensure_backlog_status();

        $criteria = [];
        if (!empty($pipeline['Id'])) {
            $criteria['PipelineId'] = $pipeline['Id'];
        }

        $run = [
            'criteria'          => $criteria,
            'stage_status_map'  => $this->stageStatusMap,
            'staff_map'         => $this->staffMap,
            'activity_type_map' => $this->activityTypeMap,
            'source_id'         => $this->sourceId,
            'source_map'        => $this->sourceMap,
            'deal_from'         => 0,
            'stats'             => [
                'statuses' => $statusCount,
                'leads'    => 0,
                'tasks'    => 0,
                'reports'  => 0,
                'skipped'  => 0,
                'errors'   => 0,
            ],
            'pipeline_title' => (string) ($pipeline['Title'] ?? ''),
        ];
        siba_leads_didar_import_run_set($run);

        $this->info(_l('siba_leads_didar_log_prepare_done'));

        return $this->finish(true, _l('siba_leads_didar_log_ready_for_deals'), false, 0);
    }

    /**
     * Step 2+: import deals page-by-page.
     *
     * @return array<string,mixed>
     */
    public function run_deals_batch(int $from, int $limit = 1): array
    {
        @ini_set('max_execution_time', '600');
        @set_time_limit(600);
        $this->log = [];
        $GLOBALS['siba_leads_didar_import'] = true;

        $run = siba_leads_didar_import_run_get();
        if ($run === []) {
            return $this->finish(false, _l('siba_leads_didar_log_run_missing'), true);
        }

        $this->hydrate_from_run($run);
        // Fetch larger pages: most deals may be skipped (stage filter); skips are cheap.
        $limit = max(1, min(25, $limit));

        $this->info(sprintf(_l('siba_leads_didar_log_fetch_deals'), (string) $from, (string) $limit));
        $deals = $this->client->search_deals($run['criteria'] ?? [], $from, $limit);

        if ($deals === []) {
            siba_leads_didar_import_run_clear();
            unset($GLOBALS['siba_leads_didar_import']);
            $this->info(_l('siba_leads_didar_log_no_more_deals'));

            return $this->finish(true, _l('siba_leads_didar_import_done'), true, $from, $run['stats'] ?? []);
        }

        foreach ($deals as $deal) {
            $title = trim((string) ($deal['Title'] ?? $deal['Id'] ?? '?'));
            try {
                $rowStats = $this->import_one_deal($deal);
                if (!empty($rowStats['skipped'])) {
                    $run['stats']['skipped'] = (int) ($run['stats']['skipped'] ?? 0) + 1;
                    if (($rowStats['reason'] ?? '') === 'stage') {
                        $this->info(sprintf(
                            _l('siba_leads_didar_log_deal_skip_stage'),
                            $title,
                            (string) ($rowStats['stage'] ?? '')
                        ));
                    } else {
                        $this->info(sprintf(_l('siba_leads_didar_log_deal_skip'), $title));
                    }
                } else {
                    $run['stats']['leads']   = (int) ($run['stats']['leads'] ?? 0) + (int) ($rowStats['lead'] ?? 0);
                    $run['stats']['tasks']   = (int) ($run['stats']['tasks'] ?? 0) + (int) ($rowStats['task'] ?? 0);
                    $run['stats']['reports'] = (int) ($run['stats']['reports'] ?? 0) + (int) ($rowStats['reports'] ?? 0);
                    $this->success(sprintf(
                        _l('siba_leads_didar_log_deal_ok'),
                        $title,
                        (string) ($rowStats['reports'] ?? 0)
                    ));
                }
            } catch (Throwable $e) {
                $run['stats']['errors'] = (int) ($run['stats']['errors'] ?? 0) + 1;
                $msg = $title . ': ' . $e->getMessage();
                $this->error($msg . ' [' . basename($e->getFile()) . ':' . $e->getLine() . ']');
            }
        }

        $nextFrom = $from + count($deals);
        $run['deal_from'] = $nextFrom;
        siba_leads_didar_import_run_set($run);

        $done = count($deals) < $limit;
        if ($done) {
            siba_leads_didar_import_run_clear();
            $this->info(_l('siba_leads_didar_log_import_complete'));
        }

        unset($GLOBALS['siba_leads_didar_import']);

        return $this->finish(
            true,
            $done ? _l('siba_leads_didar_import_done') : _l('siba_leads_didar_log_batch_done'),
            $done,
            $nextFrom,
            $run['stats'] ?? []
        );
    }

    /**
     * @param array<string,mixed> $run
     */
    protected function hydrate_from_run(array $run): void
    {
        $this->stageStatusMap  = is_array($run['stage_status_map'] ?? null) ? $run['stage_status_map'] : [];
        $this->staffMap        = is_array($run['staff_map'] ?? null) ? $run['staff_map'] : [];
        $this->activityTypeMap = is_array($run['activity_type_map'] ?? null) ? $run['activity_type_map'] : [];
        $this->sourceId        = (int) ($run['source_id'] ?? 0);
        $this->sourceMap       = is_array($run['source_map'] ?? null) ? $run['source_map'] : [];
    }

    /**
     * @return array<string,mixed>
     */
    protected function finish(
        bool $ok,
        string $message,
        bool $done,
        int $nextOffset = 0,
        ?array $stats = null
    ): array {
        return [
            'ok'          => $ok,
            'done'        => $done,
            'message'     => $message,
            'next_offset' => $nextOffset,
            'stats'       => $stats,
            'log'         => $this->log,
        ];
    }

    protected function info(string $message): void
    {
        $this->log[] = siba_leads_didar_log_entry('info', $message);
    }

    protected function success(string $message): void
    {
        $this->log[] = siba_leads_didar_log_entry('success', $message);
    }

    protected function error(string $message): void
    {
        $this->log[] = siba_leads_didar_log_entry('error', $message);
    }

    /**
     * @param array<string,mixed> $pipeline
     */
    protected function import_pipeline_stages(array $pipeline): int
    {
        $stages = $pipeline['Stages'] ?? [];
        if (!is_array($stages)) {
            return 0;
        }

        usort($stages, static function ($a, $b) {
            return ((int) ($a['Index'] ?? 0)) <=> ((int) ($b['Index'] ?? 0));
        });

        // Keep Didar stage order among the allow-listed titles.
        $allowedOrder = [];
        foreach (siba_leads_didar_allowed_stage_titles() as $i => $title) {
            $allowedOrder[siba_leads_didar_normalize_stage_title($title)] = $i;
        }

        $existingByName = [];
        foreach ($this->ci->leads_model->get_status() as $status) {
            $key = siba_leads_didar_normalize_stage_title((string) ($status['name'] ?? ''));
            if ($key !== '') {
                $existingByName[$key] = (int) $status['id'];
            }
        }

        $count = 0;
        $importedTitles = [];
        foreach ($stages as $stage) {
            if (!is_array($stage)) {
                continue;
            }
            $stageId = trim((string) ($stage['Id'] ?? ''));
            $title   = trim((string) ($stage['Title'] ?? ''));
            if ($stageId === '' || $title === '') {
                continue;
            }
            if (!siba_leads_didar_is_allowed_stage_title($title)) {
                $this->info(sprintf(_l('siba_leads_didar_log_stage_ignored'), $title));
                continue;
            }

            $norm = siba_leads_didar_normalize_stage_title($title);
            $color = trim((string) ($stage['Color'] ?? '#757575'));
            if ($color === '' || $color[0] !== '#') {
                $color = '#757575';
            }

            $statusId = (int) ($existingByName[$norm] ?? 0);
            if ($statusId < 1) {
                $order = 1 + (int) ($allowedOrder[$norm] ?? $count);
                $statusId = (int) $this->ci->leads_model->add_status([
                    'name'        => $title,
                    'color'       => $color,
                    'statusorder' => $order,
                    'isdefault'   => $count === 0 ? 1 : 0,
                ]);
                if ($statusId > 0) {
                    $existingByName[$norm] = $statusId;
                }
            }

            if ($statusId > 0) {
                $this->stageStatusMap[$stageId] = $statusId;
                $importedTitles[] = $title;
                $count++;
            }
        }

        if ($importedTitles !== []) {
            $this->info(_l('siba_leads_didar_log_stages_allowed') . ': ' . implode(' | ', $importedTitles));
        }

        if ($count > 0) {
            $firstStatus = reset($this->stageStatusMap);
            if ($firstStatus) {
                update_option('leads_default_status', (int) $firstStatus);
            }
        }

        return $count;
    }

    protected function build_activity_type_map(): void
    {
        $this->ci->tasks_model->ensure_task_comment_types_table();
        $existing = [];
        foreach ($this->ci->tasks_model->get_comment_types() as $type) {
            $name = trim((string) ($type['name'] ?? ''));
            if ($name !== '') {
                $existing[$this->str_lower($name)] = (int) $type['id'];
            }
        }

        foreach ($this->client->list_activity_types() as $atype) {
            if (!is_array($atype)) {
                continue;
            }
            $didarId = trim((string) ($atype['Id'] ?? ''));
            $title   = trim((string) ($atype['Title'] ?? ''));
            if ($didarId === '' || $title === '') {
                continue;
            }

            $key = $this->str_lower($title);
            if (isset($existing[$key])) {
                $this->activityTypeMap[$didarId] = $existing[$key];
                continue;
            }

            $newId = (int) $this->ci->tasks_model->add_comment_type([
                'name'  => $title,
                'color' => '#64748b',
            ]);
            if ($newId > 0) {
                $existing[$key] = $this->activityTypeMap[$didarId] = $newId;
            }
        }
    }

    protected function str_lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
    }

    /**
     * @param array<string,mixed> $deal
     * @return array{lead:int,task:int,reports:int,skipped:int}
     */
    protected function import_one_deal(array $deal): array
    {
        $dealId = trim((string) ($deal['Id'] ?? ''));
        if ($dealId === '') {
            return ['lead' => 0, 'task' => 0, 'reports' => 0, 'skipped' => 1];
        }

        $existingLead = siba_leads_didar_find_lead_by_deal_id($dealId);
        if ($existingLead > 0) {
            return ['lead' => 0, 'task' => 0, 'reports' => 0, 'skipped' => 1];
        }

        // Only import deals whose stage is in the allow-list (mapped during prepare).
        $stageId = trim((string) ($deal['PipelineStageId'] ?? ''));
        if ($stageId === '' || !isset($this->stageStatusMap[$stageId])) {
            $stageTitle = '';
            if (!empty($deal['PipelineStage']['Title'])) {
                $stageTitle = trim((string) $deal['PipelineStage']['Title']);
            } elseif (!empty($deal['Stage']['Title'])) {
                $stageTitle = trim((string) $deal['Stage']['Title']);
            }

            return [
                'lead'    => 0,
                'task'    => 0,
                'reports' => 0,
                'skipped' => 1,
                'reason'  => 'stage',
                'stage'   => $stageTitle !== '' ? $stageTitle : $stageId,
            ];
        }

        $person = $deal['Person'] ?? $deal['Contact'] ?? [];
        if (!is_array($person)) {
            $person = [];
        }

        $name = trim((string) ($person['DisplayName'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($deal['Title'] ?? 'Didar deal'));
        }
        if ($name === '') {
            $name = '/';
        }

        $phone = trim((string) ($person['MobilePhone'] ?? ''));
        if ($phone === '') {
            $phone = trim((string) ($person['WorkPhone'] ?? ''));
        }

        $email   = trim((string) ($person['Email'] ?? ''));
        $company = trim((string) ($person['CompanyName'] ?? ''));
        if ($company === '' && !empty($deal['Company']['DisplayName'])) {
            $company = trim((string) $deal['Company']['DisplayName']);
        }

        $status = (int) ($this->stageStatusMap[$stageId] ?? 0);
        if ($status < 1) {
            return [
                'lead'    => 0,
                'task'    => 0,
                'reports' => 0,
                'skipped' => 1,
                'reason'  => 'stage',
                'stage'   => $stageId,
            ];
        }

        $assigned = function_exists('siba_leads_didar_resolve_deal_owner_staff_id')
            ? siba_leads_didar_resolve_deal_owner_staff_id($deal, $this->staffMap)
            : siba_leads_didar_resolve_staff_id($deal['OwnerId'] ?? '', $this->staffMap, 0);
        if ($assigned < 1) {
            $this->info(sprintf(
                _l('siba_leads_didar_log_owner_unmapped'),
                (string) ($deal['OwnerId'] ?? ''),
                (string) ($deal['Title'] ?? $deal['Id'] ?? '')
            ));
        }

        $descriptionParts = [];
        if (!empty($deal['Title'])) {
            $descriptionParts[] = _l('siba_leads_didar_deal_title') . ': ' . $deal['Title'];
        }
        if (!empty($deal['Description'])) {
            $descriptionParts[] = (string) $deal['Description'];
        }
        if (!empty($deal['Price'])) {
            $descriptionParts[] = _l('siba_leads_didar_deal_price') . ': ' . $deal['Price'];
        }

        $leadData = [
            'name'        => $name,
            'title'       => trim((string) ($deal['Title'] ?? '')),
            'company'     => $company,
            'email'       => $email,
            'phonenumber' => $phone,
            'address'     => '',
            'city'        => '',
            'state'       => '',
            'zip'         => '',
            'website'     => '',
            'description' => $descriptionParts !== [] ? implode("\n", $descriptionParts) : '',
            'source'      => siba_leads_didar_resolve_deal_source_id($deal, $this->sourceMap, $this->sourceId),
            'status'      => $status,
            'assigned'    => $assigned,
            'country'     => 0,
            'is_public'   => 0,
        ];

        if (strcasecmp((string) ($deal['Status'] ?? ''), 'Lost') === 0) {
            $leadData['lost'] = 1;
        }

        $leadId = (int) $this->ci->leads_model->add($leadData);
        if ($leadId < 1) {
            throw new RuntimeException(_l('siba_leads_didar_lead_create_failed'));
        }

        $registerDate = siba_leads_didar_utc_to_mysql($deal['RegisterTime'] ?? '');
        $leadTouch = ['dateadded' => $registerDate];
        if ($assigned > 0) {
            $leadTouch['addedfrom']    = $assigned;
            $leadTouch['dateassigned'] = $registerDate;
        }
        $this->ci->db->where('id', $leadId);
        $this->ci->db->update(db_prefix() . 'leads', $leadTouch);

        siba_leads_didar_set_meta($leadId, SIBA_LEADS_DIDAR_META_DEAL, $dealId);

        $taskId = $this->create_import_task($leadId, $deal, $assigned, $registerDate);
        siba_leads_didar_set_meta($leadId, SIBA_LEADS_DIDAR_META_TASK, (string) $taskId);

        // Core logs create/assign/task as the importing admin — rewrite to Didar assignee.
        if ($assigned > 0 && function_exists('siba_leads_didar_attribute_lead_to_staff')) {
            siba_leads_didar_attribute_lead_to_staff($leadId, $assigned);
        }

        $reports = $this->import_deal_activities($dealId, $taskId, $assigned);

        return ['lead' => 1, 'task' => $taskId > 0 ? 1 : 0, 'reports' => $reports, 'skipped' => 0];
    }

    /**
     * @param array<string,mixed> $deal
     */
    protected function create_import_task(int $leadId, array $deal, int $assigned, string $startDate): int
    {
        $dealTitle = trim((string) ($deal['Title'] ?? ''));
        $taskName  = $dealTitle !== ''
            ? _l('siba_leads_didar_task_prefix') . ' — ' . $dealTitle
            : _l('siba_leads_didar_task_default');

        $status = 4;
        if (in_array(strtolower((string) ($deal['Status'] ?? '')), ['won', 'lost'], true)) {
            $status = 5;
        }

        $taskData = [
            'name'        => $taskName,
            'startdate'   => date('Y-m-d', strtotime($startDate) ?: time()),
            'duedate'     => date('Y-m-d', strtotime($startDate . ' +7 days') ?: time()),
            'rel_type'    => 'lead',
            'rel_id'      => $leadId,
            'description' => _l('siba_leads_didar_task_desc'),
            'priority'    => 2,
        ];

        $taskId = (int) $this->ci->tasks_model->add($taskData);
        if ($taskId < 1) {
            return 0;
        }

        $this->ci->db->where('id', $taskId);
        $this->ci->db->update(db_prefix() . 'tasks', [
            'dateadded' => $startDate,
            'addedfrom' => $assigned > 0 ? $assigned : 1,
            'status'    => $status,
        ]);

        if ($assigned > 0) {
            $exists = total_rows(db_prefix() . 'task_assigned', [
                'taskid'  => $taskId,
                'staffid' => $assigned,
            ]);
            if ($exists === 0) {
                $this->ci->db->insert(db_prefix() . 'task_assigned', [
                    'taskid'        => $taskId,
                    'staffid'       => $assigned,
                    'assigned_from' => $assigned,
                ]);
            }
        }

        return $taskId;
    }

    protected function import_deal_activities(string $dealId, int $taskId, int $leadAssigned = 0): int
    {
        if ($taskId < 1) {
            return 0;
        }

        $count = 0;
        $from  = 0;
        $limit = 100;
        $maxReports = 80; // hard cap per deal — avoids 120s+ timeouts on chatty deals

        do {
            $activities = $this->client->search_deal_activities($dealId, $from, $limit);
            if ($activities === []) {
                break;
            }

            foreach ($activities as $activity) {
                if (!is_array($activity) || !empty($activity['IsDeleted'])) {
                    continue;
                }
                if ($this->insert_activity_as_task_report($taskId, $activity, $leadAssigned)) {
                    $count++;
                }
                if ($count >= $maxReports) {
                    break 2;
                }
            }

            $from += $limit;
        } while (count($activities) === $limit);

        return $count;
    }

    /**
     * @param array<string,mixed> $activity
     */
    protected function insert_activity_as_task_report(int $taskId, array $activity, int $leadAssigned = 0): bool
    {
        $title = trim((string) ($activity['Title'] ?? ''));
        $note  = trim((string) ($activity['Note'] ?? ''));
        $result = siba_leads_didar_plain_text((string) ($activity['ResultNote'] ?? ''));

        $parts = [];
        if ($title !== '') {
            $parts[] = $title;
        }
        if ($note !== '') {
            $parts[] = $note;
        }
        if ($result !== '') {
            $parts[] = $result;
        }
        if ($parts === []) {
            return false;
        }

        $content = implode("\n\n", $parts);
        $typeId  = 0;
        $atypeId = trim((string) ($activity['ActivityTypeId'] ?? ''));
        if ($atypeId !== '' && isset($this->activityTypeMap[$atypeId])) {
            $typeId = (int) $this->activityTypeMap[$atypeId];
        }

        $staffId = function_exists('siba_leads_didar_resolve_deal_owner_staff_id')
            ? siba_leads_didar_resolve_deal_owner_staff_id($activity, $this->staffMap)
            : siba_leads_didar_resolve_staff_id($activity['OwnerId'] ?? '', $this->staffMap, 0);
        if ($staffId < 1 && $leadAssigned > 0) {
            $staffId = $leadAssigned;
        }
        if ($staffId < 1) {
            $staffId = 1;
        }

        $date = siba_leads_didar_utc_to_mysql(
            $activity['DoneDate'] ?? $activity['RegisterDate'] ?? $activity['DueDate'] ?? ''
        );

        $insert = [
            'taskid'     => $taskId,
            'content'    => nl2br($content),
            'staffid'    => $staffId,
            'contact_id' => 0,
            'dateadded'  => $date,
        ];

        static $hasTypeCol = null;
        if ($hasTypeCol === null) {
            $hasTypeCol = $this->ci->db->field_exists('task_comment_type_id', db_prefix() . 'task_comments');
        }
        if ($hasTypeCol) {
            $insert['task_comment_type_id'] = $typeId;
        }

        $this->ci->db->insert(db_prefix() . 'task_comments', $insert);

        return (int) $this->ci->db->insert_id() > 0;
    }
}
