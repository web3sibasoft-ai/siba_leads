(function () {
  'use strict';
  function digits(value) { return String(value).replace(/[۰-۹٠-٩]/g,function(c){var n=c.charCodeAt(0);return String(n-(n>=1776?1776:1632));}); }
  function formatMoney(input) {
    var start=input.selectionStart,old=input.value;
    // Preserve invalid signs/letters for native/server validation rather than turning them into another amount.
    if(!/^[0-9۰-۹٠-٩,٬\s]*$/.test(old))return;
    var before=digits(old.slice(0,start===null?old.length:start)).replace(/[^0-9]/g,'').length;
    var raw=digits(old).replace(/[^0-9]/g,'');
    input.value=raw.replace(/\B(?=(\d{3})+(?!\d))/g,',');
    if(document.activeElement===input&&start!==null){var pos=0,count=0;while(pos<input.value.length&&count<before){if(/[0-9]/.test(input.value[pos]))count++;pos++;}input.setSelectionRange(pos,pos);}
  }
  document.querySelectorAll('.sst-money').forEach(function(input){formatMoney(input);input.addEventListener('input',function(){formatMoney(input);});});
  var month=document.getElementById('sales-month'),open=document.getElementById('sales-month-open');
  if(!month||!open)return;
  month.addEventListener('change',function(){month.value=digits(month.value);});
  if(window.jQuery&&typeof jQuery.fn.persianDatepicker==='function'){
    function placeCalendar(){
      var plot=Array.from(document.querySelectorAll('.datepicker-plot-area')).find(function(p){return p.getBoundingClientRect().height>0&&getComputedStyle(p).display!=='none';});
      if(!plot)return;
      var box=month.getBoundingClientRect(),r=plot.getBoundingClientRect(),width=document.documentElement.clientWidth;
      plot.style.position='fixed';plot.style.left=Math.max(8,Math.min(box.right-r.width,width-r.width-8))+'px';plot.style.right='auto';
      plot.style.top=Math.max(8,box.bottom+r.height+8>innerHeight?box.top-r.height-4:box.bottom+4)+'px';
    }
    // Seed from the same server-calculated month boundary used by the report query.
    // Parsing a YYYY/MM value as a full Persian date silently falls back to today's month.
    var picker=jQuery(month).persianDatepicker({format:'YYYY/MM',initialValue:false,calendarType:'persian',persianDigit:false,observer:false,viewMode:'month',onlySelectOnDate:false,autoClose:false,onShow:function(){requestAnimationFrame(placeCalendar);},dayPicker:{enabled:false},monthPicker:{enabled:true,onSelect:function(){picker.hide();month.dispatchEvent(new Event('change',{bubbles:true}));}},yearPicker:{enabled:true},timePicker:{enabled:false},toolbox:{calendarSwitch:{enabled:false},todayButton:{enabled:true,text:{fa:'ماه جاری',en:'Current month'}}}});
    picker.setDate(new Date(month.getAttribute('data-first-day')+'T12:00:00+03:30').getTime());
    open.addEventListener('click',function(){picker.show();});
  }else{
    // Text entry remains usable on sites without the shared calendar assets.
    open.addEventListener('click',function(){month.focus();});
  }
})();
