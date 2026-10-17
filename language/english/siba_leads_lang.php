<?php

# Version 1.0.0

$lang['siba_leads'] = 'Siba Leads';
$lang['siba_leads_maintenance_title'] = 'Leads under maintenance';
$lang['siba_leads_maintenance_desc'] = 'Our crew is upgrading the leads workspace. Please check back shortly.';
$lang['siba_leads_workspace'] = 'Siba Leads Kanban';
$lang['siba_leads_workspace_desc'] = 'This module is the extension point for all advanced lead customizations without editing Perfex core files.';
$lang['siba_leads_placeholder'] = 'Add your lead workflows, custom fields logic, Kanban changes, automations, and integrations here.';
$lang['siba_leads_repo'] = 'Repository';
$lang['siba_leads_kanban'] = 'Siba Leads Kanban';
$lang['siba_leads_kanban_desc'] = 'Drag leads between statuses. Search, sort, and open lead details without leaving the board.';
$lang['siba_leads_pipeline_board'] = 'Pipeline board';
$lang['siba_leads_pipeline_board_select'] = 'Select a Didar pipeline';
$lang['siba_leads_pipeline_board_help'] = 'Each Didar pipeline has its own columns. Switch boards to see that pipeline’s stages.';
$lang['siba_leads_lead_pipeline'] = 'Pipeline (kanban)';
$lang['siba_leads_drag_to_move'] = 'Drag to move between columns';
$lang['siba_leads_move_failed'] = 'Could not save the lead move. The card was restored.';
$lang['siba_leads_move_saving'] = 'Saving…';
$lang['siba_leads_list'] = 'Siba Leads List';
$lang['siba_leads_list_desc'] = 'View open leads as table rows. Switch back to Kanban anytime from the toolbar.';
$lang['siba_leads_open_core_list'] = 'Open leads list';
$lang['siba_leads_card_details'] = 'Show details';
$lang['siba_leads_unassigned'] = 'Unassigned';
$lang['siba_leads_permission_view_own'] = 'Own assigned leads only';
$lang['siba_leads_permission_deleting_leads'] = 'Deleting leads';
$lang['siba_leads_permission_restore_failed'] = 'Restoring failed leads';
$lang['siba_leads_permission_view_reports'] = 'View all lead reports';
$lang['siba_leads_permission_report_prefix'] = 'Report:';
$lang['siba_leads_permission_manage_teams'] = 'Managing teams';
$lang['siba_leads_viewing_own_only'] = 'You can only see leads assigned to you.';
$lang['siba_leads_restored_from_failed'] = 'Lead restored to the open pipeline successfully';
$lang['siba_leads_activity_restored_from_failed'] = 'Restored from failed/lost by %s';
$lang['siba_leads_teams'] = 'Teams';
$lang['siba_leads_teams_desc'] = 'Map Sales / Support / Tech to Siba org units. Lead auto-assign uses org-unit members (including child teams). Role mapping is only a fallback.';
$lang['siba_leads_teams_howto'] = 'Build the hierarchy under Siba Organization, add members there, then map each team to an org unit here.';
$lang['siba_leads_team_org_unit'] = 'Org unit';
$lang['siba_leads_team_role'] = 'Perfex role';
$lang['siba_leads_team_role_fallback'] = 'Fallback Perfex role (if no org unit)';
$lang['siba_leads_team_source_org'] = 'From org';
$lang['siba_leads_team_source_role'] = 'From role';
$lang['siba_leads_team_members'] = 'Active members';
$lang['siba_leads_team_no_members'] = 'No active members on this org unit (or fallback role) yet.';
$lang['siba_leads_team_sales'] = 'Sales';
$lang['siba_leads_team_support'] = 'Support';
$lang['siba_leads_team_tech'] = 'Tech';
$lang['siba_leads_teams_usage_title'] = 'Usage in code';
$lang['siba_leads_org_link'] = 'Siba Organization';
$lang['siba_leads_backlog'] = 'Backlog';
$lang['siba_leads_auto_assign_sales'] = 'Auto-assign new leads to Sales team (round-robin)';
$lang['siba_leads_auto_assign_help'] = 'New leads (manual, CSV import, Siba API, web forms, email) are assigned round-robin to members of the Sales org unit (and its child units).';
$lang['siba_leads_auto_assign_no_sales'] = 'No active Sales members found — new leads go to the Backlog column until Sales org unit members are set.';
$lang['siba_leads_auto_assign_sales_count'] = 'Active Sales members: %s';
$lang['siba_leads_activity_auto_assigned'] = 'Auto-assigned to sales member %s';
$lang['siba_leads_activity_assigned_from_duplicate'] = 'Assigned to %s (same responsible as duplicate lead #%s)';
$lang['siba_leads_activity_sent_to_backlog'] = 'Moved to Backlog (no Sales team members available)';
$lang['siba_leads_import_desc'] = 'Import leads from CSV. Status/source fallbacks apply when a row value is missing. Sales auto-assign still runs after a real (non-simulate) import.';
$lang['siba_leads_card_my_active_tasks'] = 'Active task: %s';
$lang['siba_leads_card_my_active_tasks_tooltip'] = 'You have %s active task(s) on this lead';
$lang['siba_leads_card_reminder_today'] = 'Reminder today: %s';
$lang['siba_leads_card_reminder_overdue'] = 'Overdue reminder: %s';
$lang['siba_leads_card_reminder_today_tooltip'] = '%s reminder(s) due today on this lead or its tasks.';
$lang['siba_leads_card_reminder_overdue_tooltip'] = '%s overdue reminder(s) on this lead or its tasks.';
$lang['siba_leads_card_reminder_source_lead'] = 'On the lead';
$lang['siba_leads_card_reminder_source_task'] = 'On a related task';
$lang['siba_leads_card_reminder_source_both'] = 'On the lead and a task';
$lang['siba_leads_card_task_today'] = 'Task due today: %s';
$lang['siba_leads_card_task_overdue'] = 'Overdue task: %s';
$lang['siba_leads_card_task_today_tooltip'] = '%s incomplete task(s) due today on this lead.';
$lang['siba_leads_card_task_overdue_tooltip'] = '%s incomplete overdue task(s) on this lead.';
$lang['siba_leads_card_follow_lead'] = 'Follow lead';
$lang['siba_leads_card_add_order'] = 'Add order';
$lang['siba_leads_card_complete_lead'] = 'Complete lead profile';
$lang['siba_leads_order_incomplete'] = 'Complete these lead fields before financial approval: %s';
$lang['siba_leads_order_incomplete_short'] = 'Lead profile is incomplete. Complete the lead before requesting financial approval.';
$lang['siba_leads_finance_incomplete'] = 'Complete these lead fields before financial approval: %s';
$lang['siba_leads_finance_incomplete_short'] = 'Lead profile is incomplete. Complete the lead before requesting financial approval.';
$lang['siba_leads_order_required_star'] = 'Required for financial approval';
$lang['siba_leads_card_has_order'] = 'Has order';
$lang['siba_leads_card_awaiting_payment'] = 'Awaiting payment';
$lang['siba_leads_card_mark_paid'] = 'Record payment';
$lang['siba_leads_card_mark_paid_confirm'] = 'Record manual payment for this order?';
$lang['siba_leads_card_invoice_expired'] = 'Invoice expired';
$lang['siba_leads_duplicate_phone'] = 'A lead with this phone number already exists.';
$lang['siba_leads_duplicate_phone_title'] = 'Duplicate phone';
$lang['siba_leads_duplicate_phone_warning'] = 'Warning: another lead already uses this phone number. You can still save.';
$lang['siba_leads_card_duplicate_phone'] = 'Duplicate';
$lang['siba_leads_card_duplicate_phone_tooltip'] = 'Same phone as lead #%s — insert is allowed, review carefully.';
$lang['siba_leads_card_related_customer'] = 'Customer';
$lang['siba_leads_card_related_customer_tooltip'] = 'Phone matches existing customer #%s — add order will use that customer (no duplicate client).';
$lang['siba_leads_import_duplicate_phone_info'] = 'Duplicate phone numbers are allowed. Matching 0912… / +98… numbers will still import; review Duplicate badges on the kanban.';
$lang['siba_leads_province'] = 'Province';
$lang['siba_leads_city'] = 'City';
$lang['siba_leads_city_loading'] = 'Loading...';
$lang['siba_leads_pick_province_first'] = 'Select province first';
$lang['siba_leads_national_code'] = 'National ID';
$lang['siba_leads_position'] = 'Position';
$lang['siba_leads_job_group'] = 'Job group';
$lang['birth_date'] = 'Birthday';
$lang['form_identifier'] = 'Form ID';
$lang['form_source_link'] = 'Form source link';
$lang['form_source_page_title'] = 'Form source page title';
$lang['sms_verification'] = 'SMS verification';
$lang['demo_request_tracking_id'] = 'Demo request tracking ID';
$lang['user_package_number'] = 'User package number';
$lang['request_type'] = 'Request type';
$lang['form_title'] = 'Form title';
$lang['job_id'] = 'Job ID';
$lang['ref_code'] = 'Ref code';
$lang['cta_location'] = 'CTA location';
$lang['industry'] = 'Industry';
$lang['traffic_source'] = 'Traffic source';
$lang['utm_source'] = 'UTM source';
$lang['utm_medium'] = 'UTM medium';
$lang['utm_campaign'] = 'UTM campaign';
$lang['utm_content'] = 'UTM content';
$lang['utm_term'] = 'UTM term';
$lang['gclid'] = 'Google Click ID (gclid)';
$lang['gbraid'] = 'Google gbraid';
$lang['wbraid'] = 'Google wbraid';
$lang['device_type'] = 'Device type';
$lang['siba_leads_sms_verified'] = 'SMS verified';
$lang['siba_leads_sms_unverified'] = 'SMS pending';
$lang['siba_leads_website_form_info'] = 'Website form info';
$lang['siba_leads_form_description_map'] = 'More details';
$lang['siba_leads_form_extra_meta'] = 'Additional form fields';

$lang['siba_leads_failure_reasons'] = 'Failure reasons';
$lang['siba_leads_failure_reasons_desc'] = 'Manage why a lead failed or was lost. Each reason has a title and color.';
$lang['siba_leads_failure_reasons_empty'] = 'No failure reasons yet.';
$lang['siba_leads_failure_reason'] = 'Failure reason';
$lang['siba_leads_failure_reason_add'] = 'Add failure reason';
$lang['siba_leads_failure_reason_edit'] = 'Edit failure reason';
$lang['siba_leads_failure_reason_title'] = 'Title';
$lang['siba_leads_failure_reason_color'] = 'Color';
$lang['siba_leads_failure_reason_title_required'] = 'Title is required.';

$lang['siba_leads_tags'] = 'Lead tags';
$lang['siba_leads_tags_desc'] = 'Create and manage tags used on Siba leads. Assign tags from the lead profile.';
$lang['siba_leads_tags_empty'] = 'No tags yet.';
$lang['siba_leads_tag'] = 'Tag';
$lang['siba_leads_tag_add'] = 'Add tag';
$lang['siba_leads_tag_edit'] = 'Edit tag';
$lang['siba_leads_tag_name'] = 'Tag name';
$lang['siba_leads_tag_usage'] = 'Used on leads';
$lang['siba_leads_tag_name_required'] = 'Tag name is required.';
$lang['siba_leads_tag_must_exist'] = 'Only existing tags can be used. Create new tags from Lead tags first.';
$lang['siba_leads_filter_tags'] = 'Filter by tags';
$lang['siba_leads_filter_all_tags'] = 'All tags';
$lang['siba_leads_filter_untagged'] = 'Untagged only';

$lang['siba_leads_reports'] = 'Lead reports';
$lang['siba_leads_reports_desc'] = 'Deal analytics: incoming volume, pipeline stages, conversion, speed, sources, failures, and sales value.';
$lang['siba_leads_reports_registered'] = 'Incoming deals';
$lang['siba_leads_reports_incoming'] = 'Incoming deals';
$lang['siba_leads_reports_incoming_desc'] = 'Leads registered in the period (dateadded), split by current outcome: open / won (siba_outcome=success or date_converted) / lost (lost or siba_outcome=failed). Axis: time or assigned.';
$lang['siba_leads_reports_analysis'] = 'Deal analysis';
$lang['siba_leads_reports_analysis_desc'] = 'Same logic as incoming deals with a fuller summary.';
$lang['siba_leads_reports_pipeline'] = 'Deals in pipeline stages';
$lang['siba_leads_reports_pipeline_desc'] = 'Snapshot of current status (kanban column) plus a separate lost bucket; junk excluded. Date filter uses dateadded.';
$lang['siba_leads_reports_speed'] = 'Conversion speed';
$lang['siba_leads_reports_speed_desc'] = 'Won deals only; days from dateadded to sale date (siba_outcome_at first, else date_converted). Date filter uses sale date.';
$lang['siba_leads_reports_staff'] = 'Staff performance';
$lang['siba_leads_reports_staff_desc'] = 'Per assigned owner: registered/won/lost/open from incoming logic + avg days to sale from speed report.';
$lang['siba_leads_reports_aging'] = 'Open lead aging';
$lang['siba_leads_reports_aging_desc'] = 'Open kanban cards only; days = time in current column from last_status_change (or dateadded) until now — not total lifetime open.';
$lang['siba_leads_reports_response'] = 'First response time';
$lang['siba_leads_reports_response_desc'] = 'Days from dateadded to Perfex lastcontact (last contact, not necessarily first). Date filter uses dateadded.';
$lang['siba_leads_reports_sources'] = 'Incoming deal sources';
$lang['siba_leads_reports_sources_desc'] = 'Registered lead counts by source field, on time or source axis. Junk excluded.';
$lang['siba_leads_reports_sales'] = 'Sales analysis';
$lang['siba_leads_reports_sales_desc'] = 'Won deals by sale date and lead_value.';
$lang['siba_leads_reports_failures'] = 'Incoming failure reasons';
$lang['siba_leads_reports_failures_desc'] = 'lost or siba_outcome=failed leads with siba_failure_reason_id. Date filter uses dateadded; stage axis uses siba_failed_from_status.';
$lang['siba_leads_reports_conversion'] = 'Conversion rate';
$lang['siba_leads_reports_conversion_desc'] = 'Same incoming population: rate = won ÷ all registered × 100. Date filter on dateadded; axis time or assigned.';
$lang['siba_leads_reports_stage_time'] = 'Avg time in columns';
$lang['siba_leads_reports_stage_time_desc'] = 'Avg days per column: current open cards (from last_status_change) + completed stays from status-change activity log.';
$lang['siba_leads_reports_stage_open_cards'] = 'Open cards';
$lang['siba_leads_reports_stage_completed_stays'] = 'Completed stays';
$lang['siba_leads_reports_col_avg_days_in_column'] = 'Avg days in column';
$lang['siba_leads_reports_col_avg_open_days'] = 'Avg days (open)';
$lang['siba_leads_reports_col_avg_done_days'] = 'Avg days (completed)';
$lang['siba_leads_reports_aging_0_2'] = '0–2 days';
$lang['siba_leads_reports_aging_3_7'] = '3–7 days';
$lang['siba_leads_reports_aging_8_14'] = '8–14 days';
$lang['siba_leads_reports_aging_15_30'] = '15–30 days';
$lang['siba_leads_reports_aging_31'] = '31+ days';
$lang['siba_leads_reports_col_contacted'] = 'Has lastcontact';
$lang['siba_leads_reports_col_contacted_rate'] = 'Contacted %';
$lang['siba_leads_reports_col_avg_response_days'] = 'Avg days to lastcontact';
$lang['siba_leads_reports_date_bucket'] = 'Date grouping';
$lang['siba_leads_reports_bucket_day'] = 'By day';
$lang['siba_leads_reports_bucket_week'] = 'By week';
$lang['siba_leads_reports_bucket_month'] = 'By month';
$lang['siba_leads_reports_bucket_quarter'] = 'By quarter (3 months)';
$lang['siba_leads_reports_week_range'] = 'Week %s';
$lang['siba_leads_reports_week_of'] = 'Week of %s';
$lang['siba_leads_reports_week_jalali'] = 'Week %s of %s %s';
$lang['siba_leads_reports_week_ord_1'] = '1st';
$lang['siba_leads_reports_week_ord_2'] = '2nd';
$lang['siba_leads_reports_week_ord_3'] = '3rd';
$lang['siba_leads_reports_week_ord_4'] = '4th';
$lang['siba_leads_reports_week_ord_5'] = '5th';
$lang['siba_leads_reports_quarter_range'] = 'Q%s';
$lang['siba_leads_reports_quarter_n'] = 'Q%s';
$lang['siba_leads_reports_group_by'] = 'Group by';
$lang['siba_leads_reports_group_by_hint'] = 'Select any combination (or none for a single total). Pipeline always groups by stage.';
$lang['siba_leads_reports_dim_date'] = 'Date';
$lang['siba_leads_reports_dim_status'] = 'Pipeline stage';
$lang['siba_leads_reports_status_none'] = 'No stage';
$lang['siba_leads_reports_total_leads'] = 'Total leads';
$lang['siba_leads_reports_total_sales'] = 'Total sales';
$lang['siba_leads_reports_total_failures'] = 'Total failures';
$lang['siba_leads_reports_total_open'] = 'Open deals';
$lang['siba_leads_reports_total_value'] = 'Sales value';
$lang['siba_leads_reports_avg_days'] = 'Avg. days to convert';
$lang['siba_leads_reports_min_days'] = 'Fastest (days)';
$lang['siba_leads_reports_max_days'] = 'Slowest (days)';
$lang['siba_leads_reports_avg_value'] = 'Avg. deal value';
$lang['siba_leads_reports_conversion_rate'] = 'Conversion rate';
$lang['siba_leads_reports_col_leads'] = 'Leads';
$lang['siba_leads_reports_col_sales'] = 'Sales';
$lang['siba_leads_reports_col_failures'] = 'Count';
$lang['siba_leads_reports_col_open'] = 'Open';
$lang['siba_leads_reports_col_sale_value'] = 'Value';
$lang['siba_leads_reports_col_lead_value'] = 'Pipeline value';
$lang['siba_leads_reports_col_avg_days'] = 'Avg. days';
$lang['siba_leads_reports_col_min_days'] = 'Min days';
$lang['siba_leads_reports_col_max_days'] = 'Max days';
$lang['siba_leads_reports_col_avg_value'] = 'Avg. value';
$lang['siba_leads_reports_empty'] = 'No data for the selected filters.';
$lang['siba_leads_reports_source_none'] = 'No source';
$lang['siba_leads_reports_reason_unknown'] = 'No reason';
$lang['siba_leads_reports_date_from'] = 'From date';
$lang['siba_leads_reports_date_to'] = 'To date';
$lang['siba_leads_reports_chart'] = 'Chart';
$lang['siba_leads_reports_chart_total'] = 'Total';
$lang['siba_leads_reports_axis_time'] = 'By time';
$lang['siba_leads_reports_axis_users'] = 'By assigned owner';
$lang['siba_leads_reports_axis_source'] = 'By acquisition source';
$lang['siba_leads_reports_axis_reason'] = 'By reasons';
$lang['siba_leads_reports_axis_pipeline'] = 'By pipeline stages';
$lang['siba_leads_reports_axis_product'] = 'By product';
$lang['siba_leads_reports_dim_product'] = 'Product';
$lang['siba_leads_reports_product_none'] = 'No product';
$lang['siba_leads_reports_axis_location'] = 'By location';
$lang['siba_leads_reports_axis_location_title'] = 'Location (province)';
$lang['siba_leads_reports_date_axis_title'] = 'Deal creation date';
$lang['siba_leads_reports_dim_location'] = 'Location';
$lang['siba_leads_reports_location_none'] = 'No location';
$lang['siba_leads_reports_stack_open'] = 'Open';
$lang['siba_leads_reports_stack_success'] = 'Won';
$lang['siba_leads_reports_stack_failed'] = 'Lost';
$lang['siba_leads_reports_all_users'] = 'All assigned owners';
$lang['siba_leads_reports_fx_rate'] = 'Exchange rate';
$lang['siba_leads_reports_guide'] = 'Guide';
$lang['siba_leads_reports_guide_title'] = 'Chart guide for this page';
$lang['siba_leads_reports_guide_close'] = 'Close';
$lang['siba_leads_reports_guide_example'] = 'Example';
$lang['siba_leads_reports_print'] = 'Print';
$lang['siba_leads_reports_all_sources'] = 'All acquisition sources';
$lang['siba_leads_reports_all_tags'] = 'All tags';
$lang['siba_leads_reports_no_tag'] = 'Untagged';
$lang['siba_leads_reports_guide_body'] = 'Each report maps to real Siba/Perfex fields and actions. The “owner” filter is assigned — not creator or last editor. Open Guide on each page for the exact software behavior used.';
$lang['siba_leads_reports_menu'] = 'Reports';
$lang['siba_leads_reports_metric_count'] = 'Count';
$lang['siba_leads_reports_metric_amount'] = 'Amount';
$lang['siba_leads_reports_select_all'] = 'Select all';
$lang['siba_leads_reports_deselect_all'] = 'Deselect all';
$lang['siba_leads_reports_all_groups'] = 'All groups';
$lang['siba_leads_reports_all_units'] = 'All units';
$lang['siba_leads_reports_filter_all'] = 'All';

/* Per-report help: software process + formula + example */
$lang['siba_leads_reports_help_incoming'] = ''
. '<p><strong>1) Software behavior</strong><br>'
. 'Creating a lead (manual, CSV, API, website, email) sets <code>dateadded</code>. Owner is <code>assigned</code>. Mark success → <code>siba_outcome=success</code> (and/or Perfex convert with <code>date_converted</code>). Mark failed → <code>siba_outcome=failed</code> and <code>lost=1</code>.</p>'
. '<p><strong>2) Report rule</strong><br>'
. 'Population = non-junk leads with <code>dateadded</code> in range. Each lead is currently: won (<code>siba_outcome=success</code> or valid <code>date_converted</code>), lost (<code>lost=1</code> or <code>siba_outcome=failed</code>), or open (neither). Time axis buckets <code>dateadded</code>; owner axis groups by <code>assigned</code>.</p>'
. '<p><strong>3) Example</strong><br>'
. '20 leads created last week; now 10 won, 2 failed, 8 still on kanban → that week’s column height is 20 with three colors.</p>';

$lang['siba_leads_reports_help_sources'] = ''
. '<p><strong>1) Software behavior</strong><br>'
. 'On create (or edit), the lead <code>source</code> field stores acquisition channel.</p>'
. '<p><strong>2) Report rule</strong><br>'
. 'Counts non-junk leads by <code>dateadded</code> × <code>source</code>. Time axis stacks sources per period; source axis is one column per source.</p>'
. '<p><strong>3) Example</strong><br>'
. '40 website vs 10 Instagram → website column is 4× taller.</p>';

$lang['siba_leads_reports_help_failures'] = ''
. '<p><strong>1) Software behavior</strong><br>'
. '“Mark failed” stores <code>siba_failure_reason_id</code>, keeps the column in <code>siba_failed_from_status</code>, then sets <code>lost=1</code> and <code>status=0</code> (leaves kanban).</p>'
. '<p><strong>2) Report rule</strong><br>'
. 'Only <code>lost=1</code> or <code>siba_outcome=failed</code>. Grouped by reason. Date filter uses <code>dateadded</code> (not failure time). Stage axis uses <code>siba_failed_from_status</code>, not current status.</p>'
. '<p><strong>3) Example</strong><br>'
. '18 of 30 failures = “price” → ~60% of the pie.</p>';

$lang['siba_leads_reports_help_conversion'] = ''
. '<p><strong>1) Software behavior</strong><br>'
. 'Same as incoming: create sets <code>dateadded</code>; win via Siba success or Perfex convert.</p>'
. '<p><strong>2) Report rule</strong><br>'
. 'Rate = (won ÷ all registered in that bucket) × 100. Date filter on <code>dateadded</code>. Axis: time or <code>assigned</code>. Chart is a % line.</p>'
. '<p><strong>3) Example</strong><br>'
. 'Ali: 50 registered, 10 won → 20%.</p>';

$lang['siba_leads_reports_help_pipeline'] = ''
. '<p><strong>1) Software behavior</strong><br>'
. 'Dragging a kanban card changes <code>status</code> and usually <code>last_status_change</code>. Mark failed removes the card (<code>lost=1</code>).</p>'
. '<p><strong>2) Report rule</strong><br>'
. 'Snapshot by current <code>status</code> plus a lost bucket; junk excluded. Date filter uses <code>dateadded</code> to include leads, but the chart axis is current column — not a time trend.</p>'
. '<p><strong>3) Example</strong><br>'
. 'Half the pie on “Proposal” → most open cards sit there now.</p>';

$lang['siba_leads_reports_help_stage_time'] = ''
. '<p><strong>1) Software behavior</strong><br>'
. 'Moving cards between columns writes status-update activity and sets <code>last_status_change</code>.</p>'
. '<p><strong>2) Report rule</strong><br>'
. 'Per column: open cards = days from <code>last_status_change</code> (or <code>dateadded</code>) to now; completed stays = enter→leave from activity log. Chart average mixes both samples.</p>'
. '<p><strong>3) Example</strong><br>'
. 'Negotiation avg 12 days vs first contact 2 → bottleneck is negotiation.</p>';

$lang['siba_leads_reports_help_speed'] = ''
. '<p><strong>1) Software behavior</strong><br>'
. 'A win is recorded when the user marks success (<code>siba_outcome_at</code>) or converts to customer (<code>date_converted</code>).</p>'
. '<p><strong>2) Report rule</strong><br>'
. 'Only leads with a sale date. Days = DATEDIFF(sale_date, dateadded) where sale_date prefers <code>siba_outcome_at</code> then <code>date_converted</code>. Date filter uses <u>sale date</u>. Axis: time / assigned / source.</p>'
. '<p><strong>3) Example</strong><br>'
. 'Created day 1, marked success day 8 → 7 days.</p>';

$lang['siba_leads_reports_help_staff'] = ''
. '<p><strong>1) Software behavior</strong><br>'
. 'Owner is the lead <code>assigned</code> field (manual or auto-assign).</p>'
. '<p><strong>2) Report rule</strong><br>'
. 'Per owner: counts from incoming logic (date filter on <code>dateadded</code>); avg days to sale from speed (that part filters on sale date). Those two date bases can differ in one range.</p>'
. '<p><strong>3) Example</strong><br>'
. 'Reza 100 leads / 10 wins (10%); Sara 40 / 12 (30%) → Sara converts better.</p>';

$lang['siba_leads_reports_help_aging'] = ''
. '<p><strong>1) Software behavior</strong><br>'
. 'A card stays on kanban while not lost/junk and not converted. Column moves update <code>last_status_change</code>.</p>'
. '<p><strong>2) Report rule</strong><br>'
. 'Open kanban leads only. Days = time in <u>current column</u> from <code>last_status_change</code> (else <code>dateadded</code>) until now, then bucketed 0–2 / 3–7 / 8–14 / 15–30 / 31+. Not total lifetime since create.</p>'
. '<p><strong>3) Example</strong><br>'
. 'Lead created 20 days ago, moved to Proposal 3 days ago → ages in Proposal’s 3–7 bucket.</p>';

$lang['siba_leads_reports_help_response'] = ''
. '<p><strong>1) Software behavior</strong><br>'
. 'Perfex updates <code>lastcontact</code> when a contact date is saved on the lead (Last Contact field).</p>'
. '<p><strong>2) Report rule</strong><br>'
. 'Days = DATEDIFF(lastcontact, dateadded). Empty lastcontact = not contacted. Date filter on <code>dateadded</code>. Owner axis = <code>assigned</code>. A later contact moves lastcontact forward — not strictly “first” contact.</p>'
. '<p><strong>3) Example</strong><br>'
. 'Created Sat, contacted Wed → 3 days; contacted again Fri → distance to Friday is used.</p>';

$lang['siba_leads_failed'] = 'Failed leads';
$lang['siba_leads_failed_desc'] = 'Leads marked as failed (with reason). They no longer appear on the kanban.';
$lang['siba_leads_failed_empty'] = 'No failed leads match these filters.';
$lang['siba_leads_failed_date_from'] = 'Failed from';
$lang['siba_leads_failed_date_to'] = 'Failed to';
$lang['siba_leads_failed_search_hint'] = 'Searchable fields: name, phone, email, company.';

$lang['siba_leads_mark_failed'] = 'Mark as failed';
$lang['siba_leads_mark_failed_hint'] = 'Select why this lead failed. The lead will leave the kanban.';
$lang['siba_leads_mark_failed_confirm'] = 'Mark failed';
$lang['siba_leads_mark_failed_reason_required'] = 'Please select a failure reason.';
$lang['siba_leads_mark_failed_no_reasons'] = 'No failure reasons configured. Ask an admin to add them under Failure reasons.';
$lang['siba_leads_mark_failed_invalid'] = 'Could not mark this lead as failed.';
$lang['siba_leads_mark_failed_already_converted'] = 'This lead is already converted to a customer.';
$lang['siba_leads_mark_failed_success'] = 'Lead marked as failed.';
$lang['siba_leads_failure_description'] = 'Description of failing';
$lang['siba_leads_failure_description_placeholder'] = 'Explain why this lead failed (optional details, customer feedback, etc.)...';

$lang['siba_leads_outcome_success'] = 'Succeeded';
$lang['siba_leads_outcome_failed'] = 'Failed';
$lang['siba_leads_outcome_by'] = 'By';
$lang['siba_leads_outcome_at'] = 'When';
$lang['siba_leads_outcome_how'] = 'How';
$lang['siba_leads_outcome_how_financial_approval'] = 'Order financial approval';
$lang['siba_leads_outcome_how_manual_fail'] = 'Marked failed manually';
$lang['siba_leads_outcome_how_manual_success'] = 'Marked succeeded manually';

$lang['siba_leads_activity_marked_failed'] = 'Marked as failed by %s — reason: %s (%s)';
$lang['siba_leads_activity_marked_failed_with_desc'] = 'Marked as failed by %s — reason: %s (%s) — details: %s';
$lang['siba_leads_activity_marked_success'] = 'Marked as succeeded by %s (%s)';

$lang['siba_leads_timeline_tab'] = 'Timeline';
$lang['siba_leads_timeline_loading'] = 'Loading timeline...';
$lang['siba_leads_timeline_empty'] = 'Nothing has been recorded on this lead timeline yet.';
$lang['siba_leads_timeline_note'] = 'Note';
$lang['siba_leads_timeline_activity'] = 'Activity';
$lang['siba_leads_timeline_task'] = 'Task';
$lang['siba_leads_timeline_task_comment'] = 'Task comment';
$lang['siba_leads_timeline_task_report'] = 'Task report';
$lang['siba_leads_timeline_filter_all'] = 'All';
$lang['siba_leads_timeline_filter_note'] = 'Notes';
$lang['siba_leads_timeline_filter_activity'] = 'Activities';
$lang['siba_leads_timeline_filter_task'] = 'Tasks';
$lang['siba_leads_timeline_filter_comment'] = 'Comments';
$lang['siba_leads_timeline_filter_report'] = 'Reports';
$lang['siba_leads_timeline_load_error'] = 'Failed to load timeline.';

$lang['siba_leads_didar_migration'] = 'Didar migration';
$lang['siba_leads_didar_migration_desc'] = 'Wipe the Siba leads workspace or sync Pending Didar deals from the four scoped pipelines. Each pipeline becomes a tag and its own kanban board (own columns), with tasks, history, and reminders.';
$lang['siba_leads_didar_base_url'] = 'Didar base URL';
$lang['siba_leads_didar_api_key'] = 'Didar API key';
$lang['siba_leads_didar_pipeline'] = 'Deal pipeline (unused — import uses the four scoped IDs)';
$lang['siba_leads_didar_pipeline_auto'] = 'Scoped pipelines (automatic)';
$lang['siba_leads_didar_pipeline_help'] = 'Import uses four fixed Didar pipeline IDs. Each becomes a Perfex lead tag and a separate board with that pipeline’s stages as columns. Only Pending deals are synced.';
$lang['siba_leads_didar_scoped_pipelines_missing'] = 'None of the four scoped Didar pipeline IDs were found via the API. Check the API key and pipeline list.';
$lang['siba_leads_didar_pipelines_panel'] = 'Scoped pipelines → tags + boards';
$lang['siba_leads_didar_preview_button'] = 'Preview Pending counts';
$lang['siba_leads_didar_preview_done'] = 'Preview complete.';
$lang['siba_leads_didar_col_pipeline'] = 'Didar pipeline';
$lang['siba_leads_didar_col_tag'] = 'Perfex tag';
$lang['siba_leads_didar_col_pending'] = 'Pending deals';
$lang['siba_leads_didar_test_connection'] = 'Test connection';
$lang['siba_leads_didar_ssl_verify'] = 'Verify SSL (TLS) certificate';
$lang['siba_leads_didar_ssl_verify_help'] = 'On Windows or behind a corporate SSL proxy, turn this off if you see “self signed certificate in certificate chain”, save, and test again. Keep enabled on production when possible.';
$lang['siba_leads_didar_api_key_required'] = 'Didar API key is required.';
$lang['siba_leads_didar_connection_ok'] = 'Connection to Didar succeeded.';
$lang['siba_leads_didar_invalid_response'] = 'Invalid response from Didar';
$lang['siba_leads_didar_no_pipelines'] = 'No deal pipelines were returned from Didar.';
$lang['siba_leads_didar_wipe_title'] = 'Wipe Siba leads workspace';
$lang['siba_leads_didar_wipe_desc'] = 'Deletes all leads, lead statuses, related tasks, notes, activity logs, and Siba lead meta. This cannot be undone.';
$lang['siba_leads_didar_wipe_confirm_label'] = 'Type WIPE to confirm';
$lang['siba_leads_didar_wipe_button'] = 'Delete all leads and statuses';
$lang['siba_leads_didar_wipe_confirm_dialog'] = 'This will permanently delete all leads and statuses in Siba. Continue?';
$lang['siba_leads_didar_wipe_confirm_invalid'] = 'Confirmation text must be exactly WIPE.';
$lang['siba_leads_didar_wipe_done'] = 'Workspace wiped successfully.';
$lang['siba_leads_didar_import_title'] = 'Import from Didar';
$lang['siba_leads_didar_import_desc'] = 'Syncs Pending deals from the four scoped Didar pipelines. Each pipeline → tag + its own board columns; شیوه آشنایی → lead source; one follow-up task per lead. Completed activities → task reports; open scheduled activities → reminders on that task.';
$lang['siba_leads_didar_import_button'] = 'Sync Pending deals from Didar';
$lang['siba_leads_didar_import_confirm_dialog'] = 'Start Didar sync? Pending deals in the four scoped pipelines will be created or updated (no duplicates).';
$lang['siba_leads_didar_import_running'] = 'Import running… this may take several minutes.';
$lang['siba_leads_didar_import_done'] = 'Didar import finished.';
$lang['siba_leads_didar_lead_create_failed'] = 'Could not create lead.';
$lang['siba_leads_didar_deal_title'] = 'Didar deal';
$lang['siba_leads_didar_deal_price'] = 'Amount';
$lang['siba_leads_didar_task_prefix'] = 'Didar follow-up';
$lang['siba_leads_didar_task_default'] = 'Didar follow-up task';
$lang['siba_leads_didar_task_desc'] = 'Task created automatically during Didar import. Completed Didar activities are reports; open scheduled activities are reminders on this task.';
$lang['siba_leads_didar_live_log'] = 'Live log';
$lang['siba_leads_didar_log_stats_prefix'] = 'Created · Updated · Tasks · Reports · Reminders · Skipped · Unmapped · Errors: ';
$lang['siba_leads_didar_reminder_fallback'] = 'Didar activity reminder';
$lang['siba_leads_didar_log_activities_capped'] = 'Activity import capped at %s for deal %s (older items skipped).';
$lang['siba_leads_didar_log_connecting'] = 'Connecting to Didar API…';
$lang['siba_leads_didar_log_pipeline'] = 'Pipeline: %s';
$lang['siba_leads_didar_log_pipelines_all'] = 'Didar deal pipelines (%s): %s';
$lang['siba_leads_didar_log_pipelines_scoped'] = 'Scoped Didar pipelines (%s): %s';
$lang['siba_leads_didar_log_pipeline_pending'] = 'Pending deals in «%s»: %s';
$lang['siba_leads_didar_log_pipeline_tags'] = 'Pipeline tags created/mapped (%s): %s';
$lang['siba_leads_didar_log_tag_failed'] = 'Could not create tag for Didar pipeline: %s';
$lang['siba_leads_didar_log_pipeline_start'] = 'Syncing Didar pipeline «%s» (tag: %s)…';
$lang['siba_leads_didar_log_fetch_deals_pipeline'] = 'Fetching Pending deals for «%s» offset=%s limit=%s…';
$lang['siba_leads_didar_log_deal_skip_inactive'] = 'Skipped (not Pending): %s';
$lang['siba_leads_didar_log_loading_users'] = 'Loading Didar users and mapping to staff…';
$lang['siba_leads_didar_log_staff_map'] = 'Staff mapped: %s';
$lang['siba_leads_didar_log_staff_map_sources'] = 'Map keys by source — staff.didar_id: %s, custom field: %s, manual JSON: %s, m_user/Code: %s, Didar email: %s';
$lang['siba_leads_didar_log_owner_unmapped'] = 'Unmapped Didar owner %s on deal «%s» — lead left unassigned (fix Manual JSON / sync).';
$lang['siba_leads_didar_log_staff_didar_id_sync'] = 'staff.didar_id sync — updated: %s, skipped: %s, unmatched: %s';
$lang['siba_leads_didar_log_loading_sources'] = 'Loading Didar acquaintance sources (شیوه آشنایی)…';
$lang['siba_leads_didar_log_sources_map'] = 'Acquaintance sources mapped: %s';
$lang['siba_leads_didar_staff_didar_id'] = 'Didar user id (temporary)';
$lang['siba_leads_didar_staff_didar_id_help'] = 'Prefer the numeric Didar user Code (not the UUID). Or leave empty and use Sync / email / m_user matching. Used to assign leads, tasks, and reports.';
$lang['siba_leads_didar_sync_staff_button'] = 'Sync Didar ids onto staff';
$lang['siba_leads_didar_sync_staff_help'] = 'No UUID needed: matches Didar users to staff by email or m_user = Didar Code, then fills didar_id when empty. You can also paste the numeric Code on each staff profile.';
$lang['siba_leads_didar_sync_staff_done'] = 'Staff Didar ids synced.';
$lang['siba_leads_didar_sync_sources_button'] = 'Sync Didar sources into Perfex';
$lang['siba_leads_didar_sync_sources_help'] = 'Copies every Didar «شیوه آشنایی» into Perfex Setup → Leads → Sources with the same titles. Import also does this automatically; use this button to preview/create them before migrating.';
$lang['siba_leads_didar_sync_sources_done'] = 'Didar sources synced into Perfex lead sources.';
$lang['siba_leads_didar_sync_sources_empty'] = 'Didar returned no sources. Check API key / Source.GetList access.';
$lang['siba_leads_didar_log_sources_sync'] = 'Lead sources sync — created: %s, already existed: %s, skipped: %s, mapped ids: %s';
$lang['siba_leads_didar_log_sources_titles'] = 'Perfex source titles';
$lang['siba_leads_didar_staff_field_slug'] = 'Staff custom field slug (Didar user id)';
$lang['siba_leads_didar_staff_field_slug_help'] = 'On each staff profile, set a custom field (name/slug containing this text, default “didar”) to the numeric Didar user Code. UUID works too if you have it. Import expands Code ↔ OwnerId via the Didar user list.';
$lang['siba_leads_didar_staff_map_json'] = 'Manual Didar user → staff map (JSON)';
$lang['siba_leads_didar_staff_map_json_help'] = 'Highest priority. Use Didar Code → Perfex staffid, e.g. {"12": 5, "17": 12}. This overrides email/m_user matches. Leave out users you do not want to map; do not use 0.';
$lang['siba_leads_didar_log_activity_types'] = 'Syncing activity / report types…';
$lang['siba_leads_didar_log_activity_map'] = 'Report types: %s';
$lang['siba_leads_didar_log_statuses_created'] = 'Kanban statuses created: %s';
$lang['siba_leads_didar_log_pipeline_stages'] = 'Stages imported for «%s»: %s columns';
$lang['siba_leads_didar_log_stages_imported'] = 'Didar stages → board columns';
$lang['siba_leads_didar_log_stages_allowed'] = 'Importing Didar stages';
$lang['siba_leads_didar_log_stage_ignored'] = 'Ignored Didar stage: %s';
$lang['siba_leads_didar_log_stage_status_failed'] = 'Could not create Perfex status for Didar stage: %s';
$lang['siba_leads_didar_log_prepare_done'] = 'Prepare step finished.';
$lang['siba_leads_didar_log_ready_for_deals'] = 'Starting deal import…';
$lang['siba_leads_didar_log_run_missing'] = 'Import session missing. Start again from the beginning.';
$lang['siba_leads_didar_log_fetch_deals'] = 'Fetching deals offset=%s limit=%s…';
$lang['siba_leads_didar_log_deal_skip'] = 'Skipped (already imported): %s';
$lang['siba_leads_didar_log_deal_skip_stage'] = 'Skipped (stage not imported): %s — %s';
$lang['siba_leads_didar_log_deal_ok'] = 'OK: %s — %s activity reports, %s reminders';
$lang['siba_leads_didar_log_no_more_deals'] = 'No more deals.';
$lang['siba_leads_didar_log_batch_done'] = 'Batch processed; continuing…';
$lang['siba_leads_didar_log_import_complete'] = 'Import complete.';
