/* ARBNT unified shell */
(function(){'use strict';
const script=document.currentScript;const base=script?script.src.replace(/assets\/shell\.js(?:\?.*)?$/,''):'';
const href=p=>base+p;
const header=document.querySelector('header.top')||document.querySelector('header');
if(header){
 header.innerHTML='<div class="arb-shell"><a class="arb-brand" href="'+href('index.html')+'" aria-label="ARBNT"><span class="arb-mark">AR</span><span class="arb-brand-copy"><strong>ARBNT</strong><small>شبكة العرب</small></span></a><nav class="arb-nav" aria-label="التنقل الرئيسي"><a href="'+href('index.html')+'">الرئيسية</a><a href="'+href('community.html')+'">النقاشات</a><a href="'+href('communities.html')+'">المجتمعات</a><a href="'+href('profile.html')+'">الملف الشخصي</a></nav><div class="arb-controls"><a id="accountLink" class="arb-account" href="'+href('login.html')+'">دخول</a><button id="theme" type="button" aria-label="الوضع الليلي">☾</button></div></div>';
}
const footer=document.querySelector('footer');if(footer)footer.innerHTML='<div class="arb-footer"><span>© 2026 <b>ARBNT</b></span><span><a href="https://github.com/abud3d/arbnt/blob/main/VERSION" target="_blank" rel="noopener noreferrer">ARBNT v0.5.4</a></span></div>';
const theme=document.getElementById('theme');try{document.documentElement.classList.toggle('dark',localStorage.getItem('arbnt-theme')==='dark'||(!localStorage.getItem('arbnt-theme')&&matchMedia('(prefers-color-scheme:dark)').matches))}catch(e){}
if(theme)theme.onclick=()=>{document.documentElement.classList.toggle('dark');try{localStorage.setItem('arbnt-theme',document.documentElement.classList.contains('dark')?'dark':'light')}catch(e){}};
const account=document.getElementById('accountLink');
if(account){fetch(href('api/community.php?action=session'),{credentials:'same-origin'}).then(r=>r.json()).then(s=>{if(!s.user)return;account.textContent='خروج';account.href='#logout';account.onclick=async e=>{e.preventDefault();try{await fetch(href('api/community.php?action=logout'),{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':s.csrf},body:'{}'});location.href=href('community.html')}catch(err){location.href=href('community.html')}}}).catch(()=>{})}
})();