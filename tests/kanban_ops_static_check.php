<?php
/**
 * Static wiring check for Siba Leads Kanban operations.
 * Run: php modules/siba_leads/tests/kanban_ops_static_check.php
 */

$root = dirname(__DIR__);
$fail = 0;
$pass = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $fail, $pass;
    if ($ok) {
        $pass++;
        echo "PASS  {$name}" . ($detail ? " — {$detail}" : '') . PHP_EOL;
    } else {
        $fail++;
        echo "FAIL  {$name}" . ($detail ? " — {$detail}" : '') . PHP_EOL;
    }
}

function file_has(string $path, string $needle): bool
{
    if (!is_file($path)) {
        return false;
    }

    return strpos(file_get_contents($path), $needle) !== false;
}

$manage   = $root . '/views/manage.php';
$kanban   = $root . '/views/kan-ban.php';
$card     = $root . '/views/_kan_ban_card.php';
$js       = $root . '/assets/js/siba_leads.js';
$ctrl     = $root . '/controllers/Siba_leads.php';

echo "=== Siba Leads Kanban — static operation wiring ===" . PHP_EOL;

// Toolbar
check('New lead button', file_has($manage, 'init_lead();'));
check('Import leads link', file_has($manage, "admin_url('siba_leads/import')"));
check('Core leads list link', file_has($manage, "admin_url('leads')"));
check('Teams link (admin)', file_has($manage, "admin_url('siba_leads/teams')"));
check('Search input refreshes board', file_has($manage, 'siba_leads_kanban();'));

// Sort
check('Sort by dateadded', file_has($manage, "siba_leads_kanban_sort('dateadded')"));
check('Sort by leadorder', file_has($manage, "siba_leads_kanban_sort('leadorder')"));
check('Sort by lastcontact', file_has($manage, "siba_leads_kanban_sort('lastcontact')"));

// Columns
check('Column reorder sortable init', file_has($js, 'siba_leads_init_status_sortable'));
check('Column order POST', file_has($js, 'siba_leads/update_status_order'));
check('Edit status click', file_has($kanban, 'edit_status(this,'));
check('New lead from status popover', file_has($kanban, 'new-lead-from-status'));
check('Status color picker', file_has($kanban, 'kanban-cpicker'));
check('Load more leads', file_has($kanban, 'siba_leads/kanban_load_more'));
check('Backlog column marker', file_has($kanban, 'siba-backlog-col'));

// Card drag / open
check('Lead status update on drop', file_has($js, 'siba_leads/update_lead_status'));
check('Open lead modal from card', file_has($card, 'init_lead('));
check('Expand card details', file_has($card, 'slideToggle(\'#kan-ban-expand-'));
check('Phone tel: link', file_has($card, 'href="tel:'));
check('Email mailto: link', file_has($card, 'href="mailto:'));
check('Assignee profile link', file_has($card, "admin_url('profile/'"));
check('Notes / files counts', file_has($card, 'total_notes') && file_has($card, 'total_files'));

// Order / license card actions
check('Add order button', file_has($card, 'siba_leads_card_add_order'));
check('Has order button', file_has($card, 'siba_leads_card_has_order'));
check('Awaiting payment button', file_has($card, 'siba_leads_card_awaiting_payment'));
check('Invoice expired state', file_has($card, 'siba_leads_card_invoice_expired'));
check('Finance approval request', file_has($card, 'siba_leads_add_fin_approve_req'));

// Controller endpoints
check('Controller kanban()', file_has($ctrl, 'function kanban()'));
check('Controller kanban_load_more()', file_has($ctrl, 'function kanban_load_more()'));
check('Controller update_lead_status()', file_has($ctrl, 'function update_lead_status()'));
check('Controller update_status_order()', file_has($ctrl, 'function update_status_order()'));
check('Controller teams()', file_has($ctrl, 'function teams()'));
check('Controller import()', file_has($ctrl, 'function import()'));
check('Controller lead_assignee()', file_has($ctrl, 'function lead_assignee('));
check('Controller validate_phone()', file_has($ctrl, 'function validate_phone()'));

// Modal enhancements
check('Hide lead fields in modal', file_has($js, 'hideLeadProfileFields'));
check('Phone uniqueness validation', file_has($js, 'siba_leads/validate_phone'));
check('Default task assignee from lead', file_has($js, 'applyLeadAssigneeToNewTask'));
check('Disable billable on lead tasks', file_has($js, 'disableTaskBillableForLeads'));

echo PHP_EOL . "Result: {$pass} passed, {$fail} failed" . PHP_EOL;
exit($fail > 0 ? 1 : 0);
