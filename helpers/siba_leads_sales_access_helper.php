<?php
defined('BASEPATH') or exit('No direct script access allowed');

/** Report-only access: deliberately independent of view_all/view_reports for leads. */
function siba_leads_sales_department(): int {
    $id=(int)get_option('siba_leads_sales_department');if($id>0)return $id;
    $CI=&get_instance();foreach($CI->db->select('departmentid,name')->order_by('departmentid')->get(db_prefix().'departments')->result_array() as $d){
        if(mb_stripos($d['name'],'فروش')!==false||stripos($d['name'],'sales')!==false)return (int)$d['departmentid'];
    }return 0;
}
function siba_leads_sales_supervisor_ids(): array {
    $raw=get_option('siba_leads_sales_supervisors');
    if($raw!==false&&$raw!==''){$ids=json_decode($raw,true);return is_array($ids)?array_values(array_unique(array_filter(array_map('intval',$ids),static fn($v)=>$v>0))):[];}
    // Only exact existing supervisor roles are a safe initial default; never infer from generic permissions.
    $CI=&get_instance();return array_map('intval',array_column($CI->db->select('s.staffid')->from(db_prefix().'staff s')->join(db_prefix().'roles r','r.roleid=s.role')->where('s.active',1)->where('s.is_not_staff',0)->where_in('r.name',['سرپرست فروش','سرپرست فروش و پشتیبانی'])->get()->result_array(),'staffid'));
}
function siba_leads_sales_access_schema(): void {
    if(get_option('siba_leads_sales_supervisors')===false||get_option('siba_leads_sales_supervisors')==='')add_option('siba_leads_sales_supervisors',json_encode(siba_leads_sales_supervisor_ids()));
}
function siba_leads_sales_access(int $staffId=0): array {
    $staffId=$staffId?:((int)get_staff_user_id());$CI=&get_instance();
    $s=$CI->db->select('staffid,admin,active,is_not_staff')->where('staffid',$staffId)->get(db_prefix().'staff')->row_array();
    $active=$s&&(int)$s['active']===1&&(int)$s['is_not_staff']===0;
    $supervisor=$active&&in_array($staffId,siba_leads_sales_supervisor_ids(),true);
    $member=$active&&$CI->db->where('staffid',$staffId)->where('departmentid',siba_leads_sales_department())->count_all_results(db_prefix().'staff_departments')>0;
    $admin=$active&&(int)$s['admin']===1;
    return ['staff_id'=>$staffId,'can_view'=>$member||$supervisor||$admin,'can_view_all'=>$supervisor,'can_manage'=>$supervisor,'can_configure'=>$admin];
}
function siba_leads_sales_scope(array $staff,array $access): array {
    if(empty($access['can_view']))return [];
    return !empty($access['can_view_all'])?$staff:array_values(array_filter($staff,static fn($s)=>(int)$s['staffid']===(int)$access['staff_id']));
}

/** Pure presentation data: cumulative actual sales and a linear monthly target, with no extra DB reads. */
function siba_leads_sales_chart(array $period,array $row): array {
    $days=(int)$period['days'];$actual=[];$planned=[];$sum=0.0;$start=new DateTimeImmutable($period['start']);
    for($i=0;$i<$days;$i++){$date=$start->modify('+'.$i.' days')->format('Y-m-d');$sum+=(float)($row['daily_sales'][$date]??0);$actual[]=$sum;$planned[]=(float)$row['target']*($i+1)/$days;}
    $max=max(1,(float)$row['target'],...$actual);$path=static function(array $values)use($max,$days){$parts=[];foreach($values as $i=>$v)$parts[]=($i===0?'M':'L').number_format(24+$i*632/max(1,$days-1),2,'.','').' '.number_format(194-$v/$max*164,2,'.','');return implode(' ',$parts);};
    $elapsed=$period['month']<$period['current']?$days:($period['month']>$period['current']?0:min($days,$days-(int)$period['remaining_days']+1));
    $visible=array_slice($actual,0,max(1,$elapsed));
    return ['actual'=>$path($visible),'area'=>$path($visible).' L'.number_format(24+(count($visible)-1)*632/max(1,$days-1),2,'.','').' 194 L24 194 Z','planned'=>$path($planned),'max'=>$max,'elapsed_days'=>$elapsed,'pace_expected'=>(float)$row['target']*$elapsed/$days];
}
function siba_leads_sales_summary(array $rows,array $period): array {
    $summary=['sales'=>0,'lead_sales'=>0,'customer_sales'=>0,'count'=>0,'target'=>0,'daily_sales'=>[],'staff_count'=>count($rows),'target_count'=>0];
    foreach($rows as $r){if($r['target']>0)$summary['target_count']++;foreach(['sales','lead_sales','customer_sales','count','target'] as $k)$summary[$k]+=$r[$k];foreach($r['daily_sales']??[] as $date=>$amount)$summary['daily_sales'][$date]=($summary['daily_sales'][$date]??0)+$amount;}
    return $summary+siba_leads_sales_progress($summary['sales'],$summary['target'],(int)$period['remaining_days']);
}
