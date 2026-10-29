<?php defined('BASEPATH') or exit('No direct script access allowed');
$target=(float)$summary['target'];$achievement=$summary['achievement'];$progress=$achievement===null?0:min(100,max(0,(float)$achievement));
$ahead=(float)$summary['sales']-(float)$chart['pace_expected'];
?>
<section class="sst-performance" aria-label="<?= $access['can_view_all']?'خلاصه عملکرد تیم فروش':'خلاصه عملکرد فروش من' ?>">
  <div class="sst-overview sst-card">
    <div class="sst-ring" style="--progress:<?= $progress ?>" role="img" aria-label="<?= $achievement===null?'هدف ثبت نشده':e(number_format($achievement,1).' درصد از هدف محقق شده') ?>">
      <div><strong><?= $achievement===null?'—':number_format($achievement,1).'٪' ?></strong><span>تحقق هدف ماهانه</span></div>
    </div>
    <div class="sst-overview-copy">
      <span class="sst-eyebrow"><?= $access['can_view_all']?'نمای کلی تیم شما':'مسیر فروش شما' ?></span>
      <h3><?= $target<=0?'عملکرد فروش این ماه':($summary['remaining']<=0?'هدف این ماه محقق شد':($period['remaining_days']>0?'برنامهٔ فروش تا پایان ماه':'نتیجه نهایی این ماه')) ?></h3>
      <?php if($target<=0): ?><p>خلاصهٔ فروش خالص در ماه انتخاب‌شده.</p>
      <?php elseif($summary['remaining']<=0): ?><p>فروش خالص به هدف ماهانه رسیده است.</p>
      <?php elseif($period['remaining_days']>0): ?><p>در <?= (int)$period['remaining_days'] ?> روز باقی‌مانده، روزانه <strong><?= number_format($summary['daily']) ?> ریال</strong> فروش لازم است.</p>
      <?php else: ?><p>ماندهٔ هدف در پایان ماه: <?= number_format($summary['remaining']) ?> ریال.</p><?php endif; ?>
      <div class="sst-pace"><span>فروش خالص تأییدشده</span><b><?= number_format($summary['sales']) ?> <small>ریال</small></b></div>
      <?php if($target>0): ?><span class="sst-pace-status <?= $ahead>=0?'is-ahead':'is-behind' ?>"><?= number_format(abs($ahead)) ?> ریال <?= $ahead>=0?'بالاتر از':'پایین‌تر از' ?> روند هدف<?= $period['month']===$period['current']?' تا امروز':'' ?></span><?php endif; ?>
      <?php if($access['can_view_all']&&$summary['target_count']<$summary['staff_count']): ?><p class="sst-muted" style="margin:12px 0 0">پوشش هدف‌گذاری: <?= (int)$summary['target_count'] ?> از <?= (int)$summary['staff_count'] ?> کارشناس</p><?php endif; ?>
    </div>
  </div>
  <div class="sst-kpis">
    <article><span>هدف ماهانه</span><strong><?= $target>0?number_format($target):'—' ?></strong><small><?= $target>0?'ریال':'هدف ثبت نشده' ?></small></article>
    <article><span>باقی‌مانده تا هدف</span><strong><?= $target>0?number_format($summary['remaining']):'—' ?></strong><small>ریال</small></article>
    <article class="sst-daily-kpi"><span>فروش روزانهٔ لازم</span><strong><?= $target<=0?'—':($summary['daily']===null?'ماه پایان یافته':number_format($summary['daily'])) ?></strong><small><?= $target<=0?'هدف ثبت نشده':($summary['daily']===null?'بازهٔ گزارش پایان یافته':'ریال در روز') ?></small></article>
    <article><span>فاکتورهای تأییدشده</span><strong><?= number_format($summary['count']) ?></strong><small>فاکتور</small></article>
  </div>
  <div class="sst-chart-grid">
    <section class="sst-card sst-trend"><div class="sst-chart-head"><div><h4>روند رسیدن به هدف</h4><p class="sst-muted">فروش تجمعی از ابتدای ماه</p></div><div class="sst-legend"><span><i></i>فروش واقعی</span><span><i class="is-plan"></i>مسیر هدف</span></div></div>
      <div class="sst-chart-scale"><span><?= $summary['sales']<=0&&$target<=0?'—':number_format($chart['max']).' ریال' ?></span><span>۰</span></div>
      <div class="sst-chart-frame"><svg class="sst-trend-svg" viewBox="0 0 680 225" role="img" aria-label="نمودار فروش تجمعی و مسیر هدف ماهانه" preserveAspectRatio="none">
        <title>فروش واقعی و هدف از روز اول تا پایان ماه</title>
        <defs><linearGradient id="sst-sales-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#15947e" stop-opacity=".22"/><stop offset="100%" stop-color="#15947e" stop-opacity=".02"/></linearGradient></defs>
        <path class="sst-gridline" d="M24 30H656 M24 85H656 M24 140H656 M24 194H656"/>
        <?php if($target>0): ?><path class="sst-target-line" d="<?= e($chart['planned']) ?>"/><?php endif; ?>
        <path fill="url(#sst-sales-fill)" d="<?= e($chart['area']) ?>"/><path class="sst-actual-line" d="<?= e($chart['actual']) ?>"/>
      </svg><?php if($summary['sales']<=0): ?><p class="sst-chart-empty">فروش تأییدشده‌ای در این ماه ثبت نشده است</p><?php endif; ?></div>
      <div class="sst-chart-days"><span>روز ۱</span><span>روز <?= (int)ceil($period['days']/2) ?></span><span>روز <?= (int)$period['days'] ?></span></div>
    </section>
    <section class="sst-card sst-source"><h4>ترکیب فروش</h4>
      <?php $leadShare=$summary['sales']>0?round($summary['lead_sales']/$summary['sales']*100):0; ?>
      <div class="sst-source-row"><div><span><i></i>از سرنخ‌ها</span><strong><?= number_format($summary['lead_sales']) ?> <small>ریال</small></strong></div><span class="sst-source-bar"><i style="width:<?= $leadShare ?>%"></i></span></div>
      <div class="sst-source-row is-customer"><div><span><i></i>از مشتریان فعلی</span><strong><?= number_format($summary['customer_sales']) ?> <small>ریال</small></strong></div><span class="sst-source-bar"><i style="width:<?= $summary['sales']>0?100-$leadShare:0 ?>%"></i></span></div>
      <p class="sst-muted">مبالغ پس از تخفیف و برگشت و بدون مالیات محاسبه می‌شوند.</p>
    </section>
  </div>
</section>
