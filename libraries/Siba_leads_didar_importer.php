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

    /** @var array<string,string> Didar PipelineId → Perfex tag name */
    protected $pipelineTagMap = [];

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
        siba_leads_didar_ensure_mapping_tables();

        $scopedIds = array_fill_keys(siba_leads_didar_scoped_pipeline_ids(), true);
        $allPipelines = $this->client->list_deal_pipelines();
        if ($allPipelines === []) {
            return $this->finish(false, _l('siba_leads_didar_no_pipelines'), true);
        }

        $pipelines = [];
        foreach ($allPipelines as $p) {
            if (!is_array($p)) {
                continue;
            }
            $pid = trim((string) ($p['Id'] ?? ''));
            if ($pid !== '' && isset($scopedIds[$pid])) {
                $pipelines[] = $p;
            }
        }
        if ($pipelines === []) {
            return $this->finish(false, _l('siba_leads_didar_scoped_pipelines_missing'), true);
        }

        $pipelineTitles = [];
        foreach ($pipelines as $p) {
            $title = trim((string) ($p['Title'] ?? $p['Id'] ?? ''));
            if ($title !== '') {
                $pipelineTitles[] = $title;
            }
        }
        $this->info(sprintf(
            _l('siba_leads_didar_log_pipelines_scoped'),
            (string) count($pipelines),
            implode(' | ', $pipelineTitles)
        ));

        $this->info(_l('siba_leads_didar_log_loading_users'));
        $didarUsers     = $this->client->list_users();
        $staffBuilt     = siba_leads_didar_build_staff_map($didarUsers, true);
        $this->staffMap = is_array($staffBuilt['map'] ?? null) ? $staffBuilt['map'] : [];
        foreach ($didarUsers as $u) {
            if (!is_array($u)) {
                continue;
            }
            $uid = trim((string) ($u['UserId'] ?? $u['Id'] ?? ''));
            $staffId = $uid !== '' ? (int) ($this->staffMap[$uid] ?? 0) : 0;
            if ($staffId < 1 && !empty($u['Code'])) {
                $staffId = (int) ($this->staffMap[(string) $u['Code']] ?? 0);
            }
            siba_leads_didar_upsert_user_map($u, $staffId);
        }
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

        // Each Didar pipeline → tag + its own Perfex status columns (separate boards).
        $tagNames      = [];
        $pipelineQueue = [];
        foreach ($pipelines as $pipeline) {
            if (!is_array($pipeline)) {
                continue;
            }

            $mapped = siba_leads_didar_upsert_pipeline_map($pipeline);
            if ($mapped === null) {
                $this->error(sprintf(
                    _l('siba_leads_didar_log_tag_failed'),
                    (string) ($pipeline['Title'] ?? $pipeline['Id'] ?? '')
                ));
                continue;
            }
            $this->pipelineTagMap[$mapped['didar_pipeline_id']] = $mapped['tag_name'];
            $tagNames[] = $mapped['tag_name'];

            $stageCount = $this->import_pipeline_stages($pipeline, $mapped);
            $this->info(sprintf(
                _l('siba_leads_didar_log_pipeline_stages'),
                $mapped['title'],
                (string) $stageCount
            ));

            $pendingCount = $this->client->count_pending_deals($mapped['didar_pipeline_id']);
            $pipelineQueue[] = [
                'id'      => $mapped['didar_pipeline_id'],
                'title'   => $mapped['title'],
                'tag'     => $mapped['tag_name'],
                'pending' => $pendingCount,
            ];
            $this->info(sprintf(
                _l('siba_leads_didar_log_pipeline_pending'),
                $mapped['title'],
                (string) $pendingCount
            ));
        }
        $this->info(sprintf(_l('siba_leads_didar_log_statuses_created'), (string) count(array_unique(array_values($this->stageStatusMap)))));
        $this->info(sprintf(
            _l('siba_leads_didar_log_pipeline_tags'),
            (string) count($this->pipelineTagMap),
            implode(' | ', $tagNames)
        ));

        if ($pipelineQueue === []) {
            return $this->finish(false, _l('siba_leads_didar_no_pipelines'), true);
        }

        siba_leads_ensure_backlog_status();

        $firstPipeline = $pipelineQueue[0];
        $this->info(sprintf(
            _l('siba_leads_didar_log_pipeline_start'),
            $firstPipeline['title'],
            $firstPipeline['tag']
        ));

        $run = [
            // Pending deals only; one scoped Didar pipeline at a time.
            // Each pipeline has its own Perfex status columns (board).
            'criteria'           => [
                'Status'     => 'Pending',
                'PipelineId' => $firstPipeline['id'],
            ],
            'pipeline_queue'     => $pipelineQueue,
            'pipeline_index'     => 0,
            'active_pipeline_id' => $firstPipeline['id'],
            'stage_status_map'   => $this->stageStatusMap,
            'pipeline_tag_map'   => $this->pipelineTagMap,
            'staff_map'          => $this->staffMap,
            'activity_type_map'  => $this->activityTypeMap,
            'source_id'          => $this->sourceId,
            'source_map'         => $this->sourceMap,
            'deal_from'          => 0,
            'stats'              => [
                'statuses'  => count(array_unique(array_values($this->stageStatusMap))),
                'tags'      => count($this->pipelineTagMap),
                'leads'     => 0,
                'updated'   => 0,
                'tasks'     => 0,
                'reports'   => 0,
                'reminders' => 0,
                'skipped'   => 0,
                'errors'    => 0,
                'unmapped'  => 0,
            ],
            'pipeline_title' => implode(' | ', $pipelineTitles),
        ];
        siba_leads_didar_import_run_set($run);

        $this->info(_l('siba_leads_didar_log_prepare_done'));

        return $this->finish(true, _l('siba_leads_didar_log_ready_for_deals'), false, 0, $run['stats']);
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

        $queue = is_array($run['pipeline_queue'] ?? null) ? $run['pipeline_queue'] : [];
        $index = (int) ($run['pipeline_index'] ?? 0);
        if ($queue === [] || !isset($queue[$index])) {
            siba_leads_didar_import_run_clear();
            unset($GLOBALS['siba_leads_didar_import']);

            return $this->finish(true, _l('siba_leads_didar_import_done'), true, $from, $run['stats'] ?? []);
        }

        $activePipelineId = trim((string) ($run['active_pipeline_id'] ?? $queue[$index]['id'] ?? ''));
        $activeTitle      = (string) ($queue[$index]['title'] ?? $activePipelineId);

        $this->info(sprintf(
            _l('siba_leads_didar_log_fetch_deals_pipeline'),
            $activeTitle,
            (string) $from,
            (string) $limit
        ));
        $deals = $this->client->search_deals($run['criteria'] ?? [], $from, $limit);

        if ($deals === []) {
            return $this->advance_pipeline_or_finish($run, $index);
        }

        foreach ($deals as $deal) {
            if (!is_array($deal)) {
                continue;
            }
            // Ensure tagging works even when search payload omits PipelineId.
            if ($activePipelineId !== '' && trim((string) ($deal['PipelineId'] ?? '')) === '') {
                $deal['PipelineId'] = $activePipelineId;
            }

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
                    } elseif (($rowStats['reason'] ?? '') === 'inactive') {
                        $this->info(sprintf(_l('siba_leads_didar_log_deal_skip_inactive'), $title));
                    } else {
                        $this->info(sprintf(_l('siba_leads_didar_log_deal_skip'), $title));
                    }
                } else {
                    $run['stats']['leads']     = (int) ($run['stats']['leads'] ?? 0) + (int) ($rowStats['lead'] ?? 0);
                    $run['stats']['updated']   = (int) ($run['stats']['updated'] ?? 0) + (int) ($rowStats['updated'] ?? 0);
                    $run['stats']['tasks']     = (int) ($run['stats']['tasks'] ?? 0) + (int) ($rowStats['task'] ?? 0);
                    $run['stats']['reports']   = (int) ($run['stats']['reports'] ?? 0) + (int) ($rowStats['reports'] ?? 0);
                    $run['stats']['reminders'] = (int) ($run['stats']['reminders'] ?? 0) + (int) ($rowStats['reminders'] ?? 0);
                    $run['stats']['unmapped']  = (int) ($run['stats']['unmapped'] ?? 0) + (int) ($rowStats['unmapped'] ?? 0);
                    if (!empty($rowStats['partial_error'])) {
                        $run['stats']['errors'] = (int) ($run['stats']['errors'] ?? 0) + 1;
                    }
                    $this->success(sprintf(
                        _l('siba_leads_didar_log_deal_ok'),
                        $title,
                        (string) ($rowStats['reports'] ?? 0),
                        (string) ($rowStats['reminders'] ?? 0)
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

        // Short page => current Didar pipeline exhausted; move to the next one.
        if (count($deals) < $limit) {
            siba_leads_didar_import_run_set($run);

            return $this->advance_pipeline_or_finish($run, $index);
        }

        siba_leads_didar_import_run_set($run);
        unset($GLOBALS['siba_leads_didar_import']);

        return $this->finish(
            true,
            _l('siba_leads_didar_log_batch_done'),
            false,
            $nextFrom,
            $run['stats'] ?? []
        );
    }

    /**
     * Move to the next Didar pipeline in the queue, or finish when none remain.
     *
     * @param array<string,mixed> $run
     * @return array<string,mixed>
     */
    protected function advance_pipeline_or_finish(array $run, int $index): array
    {
        $queue    = is_array($run['pipeline_queue'] ?? null) ? $run['pipeline_queue'] : [];
        $nextIndex = $index + 1;

        if (!isset($queue[$nextIndex])) {
            siba_leads_didar_import_run_clear();
            unset($GLOBALS['siba_leads_didar_import']);
            $this->info(_l('siba_leads_didar_log_import_complete'));

            return $this->finish(true, _l('siba_leads_didar_import_done'), true, 0, $run['stats'] ?? []);
        }

        $next = $queue[$nextIndex];
        $run['pipeline_index']     = $nextIndex;
        $run['active_pipeline_id'] = (string) ($next['id'] ?? '');
        $run['criteria']           = [
            'Status'     => 'Pending',
            'PipelineId' => $run['active_pipeline_id'],
        ];
        $run['deal_from']          = 0;
        siba_leads_didar_import_run_set($run);

        $this->info(sprintf(
            _l('siba_leads_didar_log_pipeline_start'),
            (string) ($next['title'] ?? $next['id'] ?? ''),
            (string) ($next['tag'] ?? '')
        ));

        unset($GLOBALS['siba_leads_didar_import']);

        return $this->finish(
            true,
            _l('siba_leads_didar_log_batch_done'),
            false,
            0,
            $run['stats'] ?? []
        );
    }

    /**
     * @param array<string,mixed> $run
     */
    protected function hydrate_from_run(array $run): void
    {
        $this->stageStatusMap  = is_array($run['stage_status_map'] ?? null) ? $run['stage_status_map'] : [];
        if ($this->stageStatusMap === [] && function_exists('siba_leads_didar_load_stage_status_map')) {
            $this->stageStatusMap = siba_leads_didar_load_stage_status_map();
        }
        $this->pipelineTagMap  = is_array($run['pipeline_tag_map'] ?? null) ? $run['pipeline_tag_map'] : [];
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
     * Create/reuse Perfex lead statuses for every stage of one Didar pipeline.
     * Status rows are pipeline-scoped (never shared by title across pipelines).
     *
     * @param array<string,mixed> $pipeline
     * @param array{didar_pipeline_id:string,title:string,tag_id:int,tag_name:string}|null $mapped
     */
    protected function import_pipeline_stages(array $pipeline, ?array $mapped = null): int
    {
        $pipelineId = trim((string) ($mapped['didar_pipeline_id'] ?? $pipeline['Id'] ?? ''));
        $stages     = $pipeline['Stages'] ?? [];
        if ($pipelineId === '' || !is_array($stages)) {
            return 0;
        }

        usort($stages, static function ($a, $b) {
            return ((int) ($a['Index'] ?? 0)) <=> ((int) ($b['Index'] ?? 0));
        });

        $count          = 0;
        $importedTitles = [];
        foreach ($stages as $index => $stage) {
            if (!is_array($stage)) {
                continue;
            }
            $stageId = trim((string) ($stage['Id'] ?? ''));
            $title   = trim((string) ($stage['Title'] ?? ''));
            if ($stageId === '' || $title === '' || !siba_leads_didar_is_allowed_stage_title($title)) {
                continue;
            }

            $color = trim((string) ($stage['Color'] ?? '#757575'));
            if ($color === '' || $color[0] !== '#') {
                $color = '#757575';
            }

            $order    = 1 + (int) ($stage['Index'] ?? $index);
            $statusId = 0;
            $existing = siba_leads_didar_get_stage_map($stageId);
            if ($existing) {
                $statusId = (int) ($existing['status_id'] ?? 0);
                if ($statusId > 0 && total_rows(db_prefix() . 'leads_status', ['id' => $statusId]) > 0) {
                    $this->ci->db->where('id', $statusId);
                    $this->ci->db->update(db_prefix() . 'leads_status', [
                        'name'        => $title,
                        'color'       => $color,
                        'statusorder' => $order,
                        'isdefault'   => 0,
                    ]);
                } else {
                    $statusId = 0;
                }
            }

            if ($statusId < 1) {
                // Always create a dedicated status row for this Didar stage
                // (duplicate titles across pipelines are intentional).
                $statusId = (int) $this->ci->leads_model->add_status([
                    'name'        => $title,
                    'color'       => $color,
                    'statusorder' => $order,
                    'isdefault'   => 0,
                ]);
            }

            if ($statusId < 1) {
                $this->error(sprintf(_l('siba_leads_didar_log_stage_status_failed'), $title));
                continue;
            }

            siba_leads_didar_upsert_stage_map($pipelineId, $stageId, $title, $statusId, $order);
            $this->stageStatusMap[$stageId] = $statusId;
            $importedTitles[] = $title;
            $count++;
        }

        if ($importedTitles !== []) {
            $this->info(_l('siba_leads_didar_log_stages_imported') . ': ' . implode(' | ', $importedTitles));
        }

        if ($count > 0 && empty(get_option('leads_default_status'))) {
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
     * @return array<string,int|string>
     */
    protected function import_one_deal(array $deal): array
    {
        $empty = [
            'lead' => 0, 'updated' => 0, 'task' => 0, 'reports' => 0,
            'reminders' => 0, 'skipped' => 1, 'unmapped' => 0,
        ];

        $dealId = trim((string) ($deal['Id'] ?? ''));
        if ($dealId === '') {
            return $empty;
        }

        if (!siba_leads_didar_is_deal_pending($deal)) {
            $map = siba_leads_didar_get_deal_map($dealId);
            if ($map && (int) ($map['lead_id'] ?? 0) > 0) {
                siba_leads_didar_upsert_deal_map($dealId, array_merge($map, [
                    'sync_status' => 'inactive_in_didar',
                ]));
            }

            return array_merge($empty, ['reason' => 'inactive']);
        }

        // Only import deals whose Didar stage was mapped to a Perfex status for this pipeline.
        $stageId = trim((string) ($deal['PipelineStageId'] ?? ''));
        if ($stageId === '' || !isset($this->stageStatusMap[$stageId])) {
            $stageTitle = '';
            if (!empty($deal['PipelineStage']['Title'])) {
                $stageTitle = trim((string) $deal['PipelineStage']['Title']);
            } elseif (!empty($deal['Stage']['Title'])) {
                $stageTitle = trim((string) $deal['Stage']['Title']);
            }

            return array_merge($empty, [
                'reason' => 'stage',
                'stage'  => $stageTitle !== '' ? $stageTitle : $stageId,
            ]);
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
            return array_merge($empty, ['reason' => 'stage', 'stage' => $stageId]);
        }

        $ownerDidar = trim((string) ($deal['OwnerId'] ?? ''));
        $assigned = function_exists('siba_leads_didar_resolve_deal_owner_staff_id')
            ? siba_leads_didar_resolve_deal_owner_staff_id($deal, $this->staffMap)
            : siba_leads_didar_resolve_staff_id($ownerDidar, $this->staffMap, 0);
        $unmapped = 0;
        if ($assigned < 1) {
            $unmapped = 1;
            $this->info(sprintf(
                _l('siba_leads_didar_log_owner_unmapped'),
                $ownerDidar,
                (string) ($deal['Title'] ?? $deal['Id'] ?? '')
            ));
        }

        $descriptionParts = [];
        $descriptionParts[] = 'DidarDealId: ' . $dealId;
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
            'description' => implode("\n", $descriptionParts),
            'source'      => siba_leads_didar_resolve_deal_source_id($deal, $this->sourceMap, $this->sourceId),
            'status'      => $status,
            'assigned'    => $assigned,
            'is_public'   => 0,
            'lost'        => 0,
            'junk'        => 0,
        ];

        $existingLead = siba_leads_didar_find_lead_by_deal_id($dealId);
        $isUpdate     = $existingLead > 0;
        $leadId       = $existingLead;
        $taskCreated  = 0;

        $registerDate = siba_leads_didar_utc_to_mysql($deal['RegisterTime'] ?? '');

        if ($isUpdate) {
            $update = $leadData;
            // Avoid wiping address fields on sync.
            unset($update['is_public']);
            $this->ci->db->where('id', $leadId);
            $this->ci->db->update(db_prefix() . 'leads', $update);
            if ($assigned > 0) {
                $this->ci->db->where('id', $leadId);
                $this->ci->db->update(db_prefix() . 'leads', [
                    'assigned'     => $assigned,
                    'dateassigned' => date('Y-m-d H:i:s'),
                ]);
            }
        } else {
            $leadData['address'] = '';
            $leadData['city']    = '';
            $leadData['state']   = '';
            $leadData['zip']     = '';
            $leadData['website'] = '';
            $leadData['country'] = 0;
            $leadId = (int) $this->ci->leads_model->add($leadData);
            if ($leadId < 1) {
                throw new RuntimeException(_l('siba_leads_didar_lead_create_failed'));
            }

            $leadTouch = ['dateadded' => $registerDate];
            if ($assigned > 0) {
                $leadTouch['addedfrom']    = $assigned;
                $leadTouch['dateassigned'] = $registerDate;
            }
            $this->ci->db->where('id', $leadId);
            $this->ci->db->update(db_prefix() . 'leads', $leadTouch);
        }

        siba_leads_didar_set_meta($leadId, SIBA_LEADS_DIDAR_META_DEAL, $dealId);
        $this->assign_pipeline_tag($leadId, $deal);

        $dealMap = siba_leads_didar_get_deal_map($dealId);
        $taskId  = (int) ($dealMap['task_id'] ?? 0);
        if ($taskId < 1) {
            $taskId = siba_leads_didar_import_task_id($leadId);
        }
        if ($taskId < 1 || total_rows(db_prefix() . 'tasks', ['id' => $taskId]) === 0) {
            $taskId = $this->create_import_task($leadId, $deal, $assigned, $registerDate);
            $taskCreated = $taskId > 0 ? 1 : 0;
        } else {
            $this->sync_import_task($taskId, $deal, $assigned);
        }
        siba_leads_didar_set_meta($leadId, SIBA_LEADS_DIDAR_META_TASK, (string) $taskId);

        if ($assigned > 0 && function_exists('siba_leads_didar_attribute_lead_to_staff') && !$isUpdate) {
            siba_leads_didar_attribute_lead_to_staff($leadId, $assigned);
        }

        $activityStats = $this->import_deal_activities($dealId, $taskId, $leadId, $assigned);
        $pipelineId = siba_leads_didar_deal_pipeline_id($deal);

        siba_leads_didar_upsert_deal_map($dealId, [
            'lead_id'        => $leadId,
            'task_id'        => $taskId,
            'pipeline_id'    => $pipelineId,
            'owner_didar_id' => $ownerDidar,
            'staff_id'       => $assigned,
            'stage_id'       => $stageId,
            'sync_status'    => 'active',
        ]);

        return [
            'lead'          => $isUpdate ? 0 : 1,
            'updated'       => $isUpdate ? 1 : 0,
            'task'          => $taskCreated,
            'reports'       => (int) ($activityStats['reports'] ?? 0),
            'reminders'     => (int) ($activityStats['reminders'] ?? 0),
            'skipped'       => 0,
            'unmapped'      => $unmapped,
            'partial_error' => !empty($activityStats['errors']) ? 1 : 0,
        ];
    }

    /**
     * Tag the lead with its Didar pipeline (kanban) name.
     *
     * @param array<string,mixed> $deal
     */
    protected function assign_pipeline_tag(int $leadId, array $deal): void
    {
        if ($leadId < 1 || $this->pipelineTagMap === []) {
            return;
        }

        $pipelineId = function_exists('siba_leads_didar_deal_pipeline_id')
            ? siba_leads_didar_deal_pipeline_id($deal)
            : trim((string) ($deal['PipelineId'] ?? ''));
        if ($pipelineId === '' || !isset($this->pipelineTagMap[$pipelineId])) {
            return;
        }

        $tagName = trim((string) $this->pipelineTagMap[$pipelineId]);
        if ($tagName === '') {
            return;
        }

        if (!function_exists('handle_tags_save')) {
            return;
        }

        // Merge pipeline tag into existing tags (handle_tags_save replaces the full set).
        $existing = function_exists('get_tags_in') ? get_tags_in($leadId, 'lead') : [];
        if (!is_array($existing)) {
            $existing = [];
        }
        $merged = [];
        foreach ($existing as $name) {
            $name = trim((string) $name);
            if ($name !== '') {
                $merged[] = $name;
            }
        }
        if (!in_array($tagName, $merged, true)) {
            $merged[] = $tagName;
        }

        // Drop other scoped Didar pipeline tags so a deal keeps one pipeline tag.
        $pipelineTags = array_values(array_filter(array_map('trim', array_values($this->pipelineTagMap))));
        $merged = array_values(array_filter($merged, static function ($name) use ($tagName, $pipelineTags) {
            if ($name === $tagName) {
                return true;
            }

            return !in_array($name, $pipelineTags, true);
        }));

        handle_tags_save(implode(',', $merged), $leadId, 'lead');
    }

    /**
     * @param array<string,mixed> $deal
     */
    protected function create_import_task(int $leadId, array $deal, int $assigned, string $startDate): int
    {
        $dealTitle = trim((string) ($deal['Title'] ?? ''));
        $dealId    = trim((string) ($deal['Id'] ?? ''));
        $taskName  = $dealTitle !== ''
            ? '[Didar Migration] Follow-up — ' . $dealTitle
            : '[Didar Migration] Follow-up';

        $desc = _l('siba_leads_didar_task_desc');
        if ($dealId !== '') {
            $desc .= "\nDidarDealId: " . $dealId;
        }
        if ($dealTitle !== '') {
            $desc .= "\n" . _l('siba_leads_didar_deal_title') . ': ' . $dealTitle;
        }

        $taskData = [
            'name'        => $taskName,
            'startdate'   => date('Y-m-d', strtotime($startDate) ?: time()),
            'duedate'     => '',
            'rel_type'    => 'lead',
            'rel_id'      => $leadId,
            'description' => $desc,
            'priority'    => 2,
        ];

        $taskId = (int) $this->ci->tasks_model->add($taskData);
        if ($taskId < 1) {
            return 0;
        }

        $this->ci->db->where('id', $taskId);
        $this->ci->db->update(db_prefix() . 'tasks', [
            'dateadded' => $startDate,
            'addedfrom' => $assigned > 0 ? $assigned : (int) (get_staff_user_id() ?: 1),
            'status'    => 1,
            'duedate'   => null,
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

    /**
     * @param array<string,mixed> $deal
     */
    protected function sync_import_task(int $taskId, array $deal, int $assigned): void
    {
        if ($taskId < 1) {
            return;
        }
        $dealTitle = trim((string) ($deal['Title'] ?? ''));
        $dealId    = trim((string) ($deal['Id'] ?? ''));
        $taskName  = $dealTitle !== ''
            ? '[Didar Migration] Follow-up — ' . $dealTitle
            : '[Didar Migration] Follow-up';
        $desc = _l('siba_leads_didar_task_desc');
        if ($dealId !== '') {
            $desc .= "\nDidarDealId: " . $dealId;
        }
        if ($dealTitle !== '') {
            $desc .= "\n" . _l('siba_leads_didar_deal_title') . ': ' . $dealTitle;
        }
        $this->ci->db->where('id', $taskId);
        $this->ci->db->update(db_prefix() . 'tasks', [
            'name'        => $taskName,
            'description' => $desc,
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
    }

    /**
     * Past Didar activities → task comments (reports).
     * Future undoned activities → reminders on the same import task.
     *
     * @return array{reports:int,reminders:int,errors:int}
     */
    protected function import_deal_activities(string $dealId, int $taskId, int $leadId, int $leadAssigned = 0): array
    {
        $reports   = 0;
        $reminders = 0;
        $errors    = 0;
        if ($taskId < 1) {
            return ['reports' => 0, 'reminders' => 0, 'errors' => 0];
        }

        $from          = 0;
        $limit         = 100;
        $processed     = 0;
        $maxActivities = 500;
        $earliestDue   = null;

        do {
            $activities = method_exists($this->client, 'search_deal_activities_filtered')
                ? $this->client->search_deal_activities_filtered($dealId, $from, $limit, null)
                : $this->client->search_deal_activities($dealId, $from, $limit);
            if ($activities === []) {
                break;
            }

            foreach ($activities as $activity) {
                if (!is_array($activity) || !empty($activity['IsDeleted'])) {
                    continue;
                }

                $activityId = trim((string) ($activity['Id'] ?? ''));
                $isFuture   = siba_leads_didar_activity_is_future_reminder($activity);

                try {
                    if ($isFuture) {
                        $relId = $this->upsert_activity_as_task_reminder(
                            $taskId,
                            $activity,
                            $leadAssigned,
                            $dealId,
                            $leadId
                        );
                        if ($relId > 0) {
                            $reminders++;
                            $due = siba_leads_didar_parse_utc_datetime($activity['DueDate'] ?? '');
                            if ($due !== null && ($earliestDue === null || strcmp($due, $earliestDue) < 0)) {
                                $earliestDue = $due;
                            }
                        }
                    } else {
                        $relId = $this->upsert_activity_as_task_report(
                            $taskId,
                            $activity,
                            $leadAssigned,
                            $dealId,
                            $leadId
                        );
                        if ($relId > 0) {
                            $reports++;
                        }
                        if ($activityId !== '') {
                            $this->retire_stale_reminder($activityId, $activity);
                        }
                    }
                } catch (Throwable $e) {
                    $errors++;
                    $this->error(($activityId !== '' ? $activityId : '?') . ': ' . $e->getMessage());
                }

                $processed++;
                if ($processed >= $maxActivities) {
                    $this->info(sprintf(
                        _l('siba_leads_didar_log_activities_capped'),
                        (string) $maxActivities,
                        $dealId
                    ));
                    break 2;
                }
            }

            $from += $limit;
        } while (count($activities) === $limit);

        if ($earliestDue !== null) {
            $dueDateOnly = date('Y-m-d', strtotime($earliestDue) ?: time());
            $this->ci->db->where('id', $taskId);
            $this->ci->db->update(db_prefix() . 'tasks', ['duedate' => $dueDateOnly]);
        }

        return ['reports' => $reports, 'reminders' => $reminders, 'errors' => $errors];
    }

    /**
     * Build report/reminder body from a Didar activity.
     *
     * @param array<string,mixed> $activity
     * @return string[]
     */
    protected function activity_text_parts(array $activity, bool $allowFallback): array
    {
        $title  = trim((string) ($activity['Title'] ?? ''));
        $note   = trim((string) ($activity['Note'] ?? ''));
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
        if ($parts === [] && $allowFallback) {
            $parts[] = _l('siba_leads_didar_reminder_fallback');
        }

        return $parts;
    }

    /**
     * @param array<string,mixed> $activity
     */
    protected function resolve_activity_staff_id(array $activity, int $leadAssigned): int
    {
        $staffId = function_exists('siba_leads_didar_resolve_deal_owner_staff_id')
            ? siba_leads_didar_resolve_deal_owner_staff_id($activity, $this->staffMap)
            : siba_leads_didar_resolve_staff_id($activity['OwnerId'] ?? '', $this->staffMap, 0);
        if ($staffId < 1 && $leadAssigned > 0) {
            $staffId = $leadAssigned;
        }

        return $staffId;
    }

    /**
     * @param array<string,mixed> $activity
     * @return int comment id (0 if skipped)
     */
    protected function upsert_activity_as_task_report(
        int $taskId,
        array $activity,
        int $leadAssigned,
        string $dealId,
        int $leadId
    ): int {
        $activityId = trim((string) ($activity['Id'] ?? ''));
        $parts      = $this->activity_text_parts($activity, false);
        if ($parts === []) {
            return 0;
        }

        $ownerName = trim((string) ($activity['Owner']['DisplayName'] ?? $activity['Creator']['DisplayName'] ?? ''));
        if ($ownerName !== '') {
            $parts[] = 'Didar: ' . $ownerName;
        }
        if ($activityId !== '') {
            $parts[] = 'DidarActivityId: ' . $activityId;
        }

        $content = nl2br(implode("\n\n", $parts));
        $typeId  = 0;
        $atypeId = trim((string) ($activity['ActivityTypeId'] ?? ''));
        if ($atypeId !== '' && isset($this->activityTypeMap[$atypeId])) {
            $typeId = (int) $this->activityTypeMap[$atypeId];
        }

        $staffId = $this->resolve_activity_staff_id($activity, $leadAssigned);
        if ($staffId < 1) {
            $staffId = (int) (get_staff_user_id() ?: 1);
        }

        $date = siba_leads_didar_utc_to_mysql(
            $activity['DoneDate'] ?? $activity['RegisterDate'] ?? $activity['DueDate'] ?? ''
        );

        static $hasTypeCol = null;
        if ($hasTypeCol === null) {
            $hasTypeCol = $this->ci->db->field_exists('task_comment_type_id', db_prefix() . 'task_comments');
        }

        if ($activityId !== '') {
            $existing = siba_leads_didar_get_activity_map($activityId);
            if ($existing && ($existing['kind'] ?? '') === 'history' && (int) ($existing['rel_id'] ?? 0) > 0) {
                $commentId = (int) $existing['rel_id'];
                if (total_rows(db_prefix() . 'task_comments', ['id' => $commentId, 'taskid' => $taskId]) > 0) {
                    $update = [
                        'content'   => $content,
                        'staffid'   => $staffId,
                        'dateadded' => $date,
                    ];
                    if ($hasTypeCol) {
                        $update['task_comment_type_id'] = $typeId;
                    }
                    $this->ci->db->where('id', $commentId);
                    $this->ci->db->where('taskid', $taskId);
                    $this->ci->db->update(db_prefix() . 'task_comments', $update);
                    siba_leads_didar_upsert_activity_map($activityId, $dealId, $leadId, 'history', $commentId);

                    return $commentId;
                }
                // Comment missing or moved — recreate below.
            }
        }

        $insert = [
            'taskid'     => $taskId,
            'content'    => $content,
            'staffid'    => $staffId,
            'contact_id' => 0,
            'dateadded'  => $date,
        ];
        if ($hasTypeCol) {
            $insert['task_comment_type_id'] = $typeId;
        }

        $this->ci->db->insert(db_prefix() . 'task_comments', $insert);
        $commentId = (int) $this->ci->db->insert_id();
        if ($commentId > 0 && $activityId !== '') {
            siba_leads_didar_upsert_activity_map($activityId, $dealId, $leadId, 'history', $commentId);
        }

        return $commentId;
    }

    /**
     * Open (undoned) Didar activities with DueDate → Perfex reminders on the import task.
     * Also converts a previously mis-imported task comment (history) into a reminder.
     *
     * @param array<string,mixed> $activity
     * @return int reminder id (0 if skipped)
     */
    protected function upsert_activity_as_task_reminder(
        int $taskId,
        array $activity,
        int $leadAssigned,
        string $dealId,
        int $leadId
    ): int {
        if ($taskId < 1 || !siba_leads_didar_activity_is_future_reminder($activity)) {
            return 0;
        }

        $dueDate = siba_leads_didar_parse_utc_datetime($activity['DueDate'] ?? '');
        if ($dueDate === null) {
            return 0;
        }

        $activityId = trim((string) ($activity['Id'] ?? ''));
        $parts      = $this->activity_text_parts($activity, true);
        if ($activityId !== '') {
            $parts[] = 'DidarActivityId: ' . $activityId;
        }

        $staffId = $this->resolve_activity_staff_id($activity, $leadAssigned);
        if ($staffId < 1) {
            // Do not invent an assignee; skip reminder until owner is mapped.
            return 0;
        }

        // Earlier imports wrongly saved open activities as task comments because DoneDate was set.
        if ($activityId !== '') {
            $existing = siba_leads_didar_get_activity_map($activityId);
            if ($existing && ($existing['kind'] ?? '') === 'history') {
                $commentId = (int) ($existing['rel_id'] ?? 0);
                if ($commentId > 0) {
                    $this->ci->db->where('id', $commentId);
                    $this->ci->db->where('taskid', $taskId);
                    $this->ci->db->delete(db_prefix() . 'task_comments');
                }
            }
        }

        $description = nl2br(implode("\n\n", $parts));
        $payload     = [
            'description' => $description,
            'date'        => $dueDate,
            'staff'       => $staffId,
            'isnotified'  => 0,
            'rel_id'      => $taskId,
            'rel_type'    => 'task',
        ];

        if ($activityId !== '') {
            $existing = siba_leads_didar_get_activity_map($activityId);
            if ($existing && ($existing['kind'] ?? '') === 'reminder' && (int) ($existing['rel_id'] ?? 0) > 0) {
                $reminderId = (int) $existing['rel_id'];
                if (total_rows(db_prefix() . 'reminders', ['id' => $reminderId]) > 0) {
                    $this->ci->db->where('id', $reminderId);
                    $this->ci->db->update(db_prefix() . 'reminders', $payload);
                    siba_leads_didar_upsert_activity_map($activityId, $dealId, $leadId, 'reminder', $reminderId);

                    return $reminderId;
                }
            }
        }

        $payload['notify_by_email'] = 0;
        $payload['creator']         = $staffId;
        $this->ci->db->insert(db_prefix() . 'reminders', $payload);
        $reminderId = (int) $this->ci->db->insert_id();
        if ($reminderId > 0 && $activityId !== '') {
            siba_leads_didar_upsert_activity_map($activityId, $dealId, $leadId, 'reminder', $reminderId);
        }

        return $reminderId;
    }

    /**
     * @param array<string,mixed> $activity
     */
    protected function retire_stale_reminder(string $activityId, array $activity): void
    {
        $map = siba_leads_didar_get_activity_map($activityId);
        if (!$map || ($map['kind'] ?? '') !== 'reminder') {
            return;
        }
        $reminderId = (int) ($map['rel_id'] ?? 0);
        if ($reminderId < 1) {
            return;
        }
        // Done, deleted, or past → stop notifying.
        $this->ci->db->where('id', $reminderId);
        $this->ci->db->update(db_prefix() . 'reminders', ['isnotified' => 1]);
    }
}
