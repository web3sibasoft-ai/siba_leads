<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (!function_exists('siba_webkit_get_team_definitions')) {
    $webkitHelper = APP_MODULES_PATH . 'siba_webkit/helpers/siba_webkit_org_helper.php';
    if (file_exists($webkitHelper)) {
        require_once $webkitHelper;
    }
}

/**
 * Logical company teams mapped onto Perfex Setup → Roles.
 * Extend with filter: hooks()->add_filter('siba_leads_team_definitions', ...)
 *
 * @return array<string, array{label:string,role_names:string[]}>
 */
function siba_leads_team_definitions(): array
{
    $teams = [
        'sales' => [
            'label'      => _l('siba_leads_team_sales'),
            'role_names' => ['sales', 'Sales', 'فروش', 'تیم فروش'],
        ],
        'support' => [
            'label'      => _l('siba_leads_team_support'),
            'role_names' => ['support', 'Support', 'پشتیبانی', 'تیم پشتیبانی'],
        ],
        'tech' => [
            'label'      => _l('siba_leads_team_tech'),
            'role_names' => ['tech', 'Tech', 'technical', 'Technical', 'فنی', 'تیم فنی'],
        ],
    ];

    return hooks()->apply_filters('siba_leads_team_definitions', $teams);
}

/**
 * Saved map: team_key => roleid (0 = not set).
 *
 * @return array<string,int>
 */
function siba_leads_get_team_roles_map(): array
{
    $defs = siba_leads_team_definitions();
    if (function_exists('siba_webkit_get_team_roles_map')) {
        return siba_webkit_get_team_roles_map('siba_leads_team_roles', $defs);
    }

    $raw = get_option('siba_leads_team_roles');
    $map = is_string($raw) ? json_decode($raw, true) : [];
    $map = is_array($map) ? $map : [];
    $normalized = [];
    foreach ($defs as $key => $_def) {
        $normalized[$key] = isset($map[$key]) ? (int) $map[$key] : 0;
    }
    return $normalized;
}

/**
 * Persist team → role mapping.
 *
 * @param array<string,int|string> $map
 */
function siba_leads_save_team_roles(array $map): void
{
    $defs = siba_leads_team_definitions();
    if (function_exists('siba_webkit_save_team_roles')) {
        siba_webkit_save_team_roles($map, 'siba_leads_team_roles', $defs);
        return;
    }

    $clean = [];
    foreach ($defs as $key => $_def) {
        $clean[$key] = isset($map[$key]) ? (int) $map[$key] : 0;
    }
    update_option('siba_leads_team_roles', json_encode($clean));
}

/**
 * Saved map: team_key => org_unit_id (0 = not set).
 * Option: siba_leads_team_org_units
 *
 * @return array<string,int>
 */
function siba_leads_get_team_org_units_map(): array
{
    $raw = get_option('siba_leads_team_org_units');
    $map = is_string($raw) ? json_decode($raw, true) : [];
    $map = is_array($map) ? $map : [];

    $normalized = [];
    foreach (array_keys(siba_leads_team_definitions()) as $key) {
        $normalized[$key] = isset($map[$key]) ? (int) $map[$key] : 0;
    }

    return $normalized;
}

/**
 * @param array<string,int|string> $map
 */
function siba_leads_save_team_org_units(array $map): void
{
    $clean = [];
    foreach (array_keys(siba_leads_team_definitions()) as $key) {
        $clean[$key] = isset($map[$key]) ? (int) $map[$key] : 0;
    }
    update_option('siba_leads_team_org_units', json_encode($clean));
}

/**
 * Org unit id mapped to a logical team (sales/support/tech/...).
 */
function siba_leads_get_team_org_unit_id(string $team): ?int
{
    $team = strtolower(trim($team));
    $map  = siba_leads_get_team_org_units_map();

    return !empty($map[$team]) ? (int) $map[$team] : null;
}

/**
 * Resolve Perfex role id for a team key (sales/support/tech/...).
 */
function siba_leads_get_team_role_id(string $team): ?int
{
    $definitions = siba_leads_team_definitions();
    if (function_exists('siba_webkit_get_team_role_id')) {
        return siba_webkit_get_team_role_id($team, $definitions, 'siba_leads_team_roles');
    }

    $team = strtolower(trim($team));
    $map  = siba_leads_get_team_roles_map();
    return !empty($map[$team]) ? (int) $map[$team] : null;
}

/**
 * Find a role by id or exact/case-insensitive name.
 *
 * @param int|string $idOrName
 * @return object|null
 */
function siba_leads_get_role($idOrName)
{
    if (function_exists('siba_webkit_get_role')) {
        return siba_webkit_get_role($idOrName);
    }

    $CI = &get_instance();
    $CI->load->model('roles_model');

    if (is_numeric($idOrName)) {
        $role = $CI->roles_model->get((int) $idOrName);

        return $role ?: null;
    }

    $name = trim((string) $idOrName);
    if ($name === '') {
        return null;
    }

    $CI->db->where('name', $name);
    $role = $CI->db->get(db_prefix() . 'roles')->row();
    if ($role) {
        return $role;
    }

    return siba_leads_find_role_by_names([$name]);
}

/**
 * @param string[] $names
 * @return object|null
 */
function siba_leads_find_role_by_names(array $names)
{
    $CI = &get_instance();
    $CI->load->model('roles_model');
    $roles = $CI->roles_model->get();

    if (!is_array($roles)) {
        return null;
    }

    $wanted = array_map(static function ($n) {
        return mb_strtolower(trim((string) $n));
    }, $names);

    foreach ($roles as $role) {
        $roleName = mb_strtolower(trim((string) ($role['name'] ?? '')));
        if ($roleName !== '' && in_array($roleName, $wanted, true)) {
            return (object) $role;
        }
    }

    return null;
}

/**
 * Staff members assigned to a Perfex role (by role id or role name).
 *
 * @param int|string $idOrName
 * @return array<int,array>
 */
function siba_leads_get_members_by_role($idOrName, bool $activeOnly = true): array
{
    if (function_exists('siba_webkit_get_members_by_role')) {
        return siba_webkit_get_members_by_role($idOrName, $activeOnly);
    }

    $role = siba_leads_get_role($idOrName);
    if (!$role) {
        return [];
    }

    $CI = &get_instance();
    $CI->load->model('staff_model');

    $where = ['role' => (int) $role->roleid];
    if ($activeOnly) {
        $where['active'] = 1;
    }

    $members = $CI->staff_model->get('', $where);

    return is_array($members) ? $members : [];
}

/**
 * Staff members belonging to a logical team (sales/support/tech/...).
 * Prefers mapped org-unit members (including child units); falls back to Perfex role.
 *
 * @return array<int,array>
 */
function siba_leads_get_team_members(string $team, bool $activeOnly = true): array
{
    $unitId = siba_leads_get_team_org_unit_id($team);
    if ($unitId && function_exists('siba_webkit_org_get_unit_members')) {
        $members = siba_webkit_org_get_unit_members($unitId, true, $activeOnly);
        if (!empty($members)) {
            return $members;
        }
    }

    if (function_exists('siba_webkit_get_team_members')) {
        $members = siba_webkit_get_team_members(
            $team,
            siba_leads_team_definitions(),
            'siba_leads_team_roles',
            $activeOnly
        );
        if (!empty($members)) {
            return $members;
        }
    }

    $roleId = siba_leads_get_team_role_id($team);
    if (!$roleId) {
        return [];
    }

    return siba_leads_get_members_by_role($roleId, $activeOnly);
}

/**
 * @return int[]
 */
function siba_leads_get_team_member_ids(string $team, bool $activeOnly = true): array
{
    $ids = [];
    foreach (siba_leads_get_team_members($team, $activeOnly) as $member) {
        if (isset($member['staffid'])) {
            $ids[] = (int) $member['staffid'];
        }
    }

    return $ids;
}

/**
 * Whether a staff member is on a logical team.
 */
function siba_leads_staff_in_team(string $team, $staffId = null): bool
{
    $staffId = (int) ($staffId ?: get_staff_user_id());
    if ($staffId <= 0) {
        return false;
    }

    return in_array($staffId, siba_leads_get_team_member_ids($team, false), true);
}

/**
 * Team keys the staff member belongs to.
 *
 * @return string[]
 */
function siba_leads_get_staff_teams($staffId = null): array
{
    $staffId = (int) ($staffId ?: get_staff_user_id());
    $teams   = [];

    foreach (array_keys(siba_leads_team_definitions()) as $key) {
        if (siba_leads_staff_in_team($key, $staffId)) {
            $teams[] = $key;
        }
    }

    return $teams;
}

/**
 * All roles for selects (roleid, name).
 *
 * @return array<int,array{roleid:int,name:string}>
 */
function siba_leads_get_all_roles(): array
{
    if (function_exists('siba_webkit_get_all_roles')) {
        return siba_webkit_get_all_roles();
    }

    $CI = &get_instance();
    $CI->load->model('roles_model');
    $roles = $CI->roles_model->get();

    return is_array($roles) ? $roles : [];
}
