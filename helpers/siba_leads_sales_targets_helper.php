<?php
defined('BASEPATH') or exit('No direct script access allowed');
function siba_leads_sales_targets_schema(): void {
    $CI=&get_instance();$t=db_prefix().'siba_leads_sales_targets';
    if(!$CI->db->table_exists($t))$CI->db->query('CREATE TABLE `'.$t.'` (staff_id INT NOT NULL, jalali_month CHAR(7) NOT NULL, target_rial DECIMAL(18,0) NOT NULL DEFAULT 0, updated_by INT NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (jalali_month,staff_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}
function siba_leads_sales_month(string $month='',?string $today=null): array {
    siba_leads_ensure_jalali_helpers();siba_license_ensure_jdf_helper();
    $tz=new DateTimeZone('Asia/Tehran');$now=new DateTimeImmutable($today??'now',$tz);
    $current=jdate('Y/m',$now->getTimestamp(),'','Asia/Tehran','en');
    $month=strtr($month,['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
    if($month==='')$month=$current;
    if(!preg_match('~^(1[34]\d{2})/(0[1-9]|1[0-2])$~D',$month,$m))throw new DomainException('ماه را به صورت ۱۴۰۵/۰۸ وارد کنید.');
    $y=(int)$m[1];$n=(int)$m[2];$a=jalali_to_gregorian($y,$n,1);$b=jalali_to_gregorian($n===12?$y+1:$y,$n===12?1:$n+1,1);
    $start=new DateTimeImmutable(vsprintf('%04d-%02d-%02d',$a),$tz);$end=new DateTimeImmutable(vsprintf('%04d-%02d-%02d',$b),$tz);
    $days=(int)$start->diff($end)->days;
    $remaining=$now<$start?$days:($now>=$end?0:(int)$now->setTime(0,0)->diff($end)->days);
    return ['month'=>$month,'current'=>$current,'start'=>$start->format('Y-m-d'),'end'=>$end->format('Y-m-d'),'days'=>$days,'remaining_days'=>$remaining];
}
function siba_leads_sales_progress(float $sale,float $target,int $remainingDays): array {
    $left=max(0,$target-$sale);
    return ['remaining'=>$left,'achievement'=>$target>0?round($sale/$target*100,1):null,'daily'=>$remainingDays>0?ceil($left/$remainingDays):null];
}
function siba_leads_sales_staff(int $department): array {
    $CI=&get_instance();$p=db_prefix();
    return $CI->db->query("SELECT DISTINCT s.staffid,s.firstname,s.lastname,s.active FROM {$p}staff s INNER JOIN {$p}staff_departments d ON d.staffid=s.staffid WHERE d.departmentid=? AND s.is_not_staff=0 ORDER BY s.firstname,s.lastname",[$department])->result_array();
}
/** One monthly batch, no per-staff/per-invoice database requests. Native lead conversion reports are unchanged. */
function siba_leads_sales_target_report(array $period,array $staff): array {
    $CI=&get_instance();$p=db_prefix();$ids=array_map('intval',array_column($staff,'staffid'));if(!$ids)return [];
    $targets=$CI->db->where('jalali_month',$period['month'])->where_in('staff_id',$ids)->get($p.'siba_leads_sales_targets')->result_array();$targets=array_column($targets,'target_rial','staff_id');
    $rows=[];foreach($staff as $s)$rows[(int)$s['staffid']]=['staff_id'=>(int)$s['staffid'],'name'=>trim($s['firstname'].' '.$s['lastname']),'active'=>(int)$s['active'],'sales'=>0,'lead_sales'=>0,'customer_sales'=>0,'count'=>0,'target'=>(float)($targets[$s['staffid']]??0),'daily_sales'=>[]];
    $has=$CI->db->table_exists($p.'siba_sales_attribution');
    $join=$has?" LEFT JOIN {$p}siba_sales_attribution a ON a.order_id=o.order_id ":'';
    $owner=$has?'COALESCE(NULLIF(a.sales_staff_id,0),i.sale_agent)':'i.sale_agent';
    $clock=$has?'COALESCE(a.approved_at,ans.answer_date,i.date)':'COALESCE(ans.answer_date,i.date)';
    $returns='0';$returnJoin='';
    if($CI->db->table_exists($p.'siba_commerce_returns')){
        $returnJoin=" LEFT JOIN (SELECT r.invoice_id,SUM(c.total-c.total_tax) net_return FROM (SELECT DISTINCT invoice_id,operation_id FROM {$p}siba_commerce_returns) r INNER JOIN {$p}siba_commerce_operations op ON op.id=r.operation_id INNER JOIN {$p}creditnotes c ON c.id=op.credit_note_id GROUP BY r.invoice_id) rt ON rt.invoice_id=i.id ";$returns='COALESCE(rt.net_return,0)';
    }
    // Other native credit allocations are separate from Commerce return credit notes.
    $exclude=$CI->db->table_exists($p.'siba_commerce_operations')?" AND NOT EXISTS(SELECT 1 FROM {$p}siba_commerce_operations op WHERE op.credit_note_id=c.id)":'';
    $returnJoin.=" LEFT JOIN (SELECT cr.invoice_id,SUM(cr.amount*CASE WHEN c.total>0 THEN (c.total-c.total_tax)/c.total ELSE 0 END) native_return FROM {$p}credits cr INNER JOIN {$p}creditnotes c ON c.id=cr.credit_id WHERE 1=1 {$exclude} GROUP BY cr.invoice_id) nr ON nr.invoice_id=i.id ";
    $sql="SELECT MIN({$owner}) staff_id,COUNT(DISTINCT {$owner}) owner_count,MAX(o.order_lead_id) lead_id,i.id,MIN({$clock}) sold_at,MAX(GREATEST(0,i.total-i.total_tax-{$returns}-COALESCE(nr.native_return,0))) net,curr.name currency FROM {$p}siba_orders o INNER JOIN {$p}invoices i ON i.id=o.order_invoice_id INNER JOIN {$p}currencies curr ON curr.id=i.currency {$join} LEFT JOIN (SELECT fin_approve_order,MAX(fin_approve_answer_date) answer_date FROM {$p}siba_fin_approve_req WHERE fin_approve_response=1 GROUP BY fin_approve_order) ans ON ans.fin_approve_order=o.order_id {$returnJoin} WHERE o.order_financial_approval=1 AND i.status NOT IN(5,6) AND {$clock}>=? AND {$clock}<? GROUP BY i.id,curr.name HAVING SUM(({$owner}) IN (".implode(',',$ids)."))>0";
    $ambiguous=0;
    foreach($CI->db->query($sql,[$period['start'],$period['end']])->result_array() as $r){
        if((int)$r['owner_count']>1){$ambiguous++;continue;}
        $id=(int)$r['staff_id'];if(!isset($rows[$id])||!in_array(strtoupper($r['currency']),['IRR','IRT','TOMAN'],true))continue;
        $amount=(float)$r['net']*(strtoupper($r['currency'])==='IRR'?1:10);$rows[$id]['sales']+=$amount;$rows[$id][(int)$r['lead_id']>0?'lead_sales':'customer_sales']+=$amount;$rows[$id]['count']++;$day=substr((string)$r['sold_at'],0,10);$rows[$id]['daily_sales'][$day]=($rows[$id]['daily_sales'][$day]??0)+$amount;
    }
    foreach($rows as &$row){$row['ambiguous_invoices']=$ambiguous;$row+=siba_leads_sales_progress($row['sales'],$row['target'],$period['remaining_days']);}unset($row);
    return array_values($rows);
}
