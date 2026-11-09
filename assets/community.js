/* ARBNT community: native browser APIs only */
(()=>{'use strict';
const $=id=>document.getElementById(id);
let csrf='',account=null,page=1,total=0,view='all',communityFilter='',favorites=new Set(),communities=[];
const endpoint='api/community.php?action=';
function message(t){$('notice').textContent=t}
async function api(action,body){
 const options={credentials:'same-origin'};
 if(body!==undefined){options.method='POST';options.headers={'Content-Type':'application/json','X-CSRF-Token':csrf};options.body=JSON.stringify(body)}
 const r=await fetch(endpoint+action,options);
 const v=await r.json();if(!r.ok)throw Error(v.error||'خطأ في الاتصال');return v;
}
function element(tag,text,cls){const n=document.createElement(tag);if(text!==undefined)n.textContent=text;if(cls)n.className=cls;return n}
function ago(value){const n=Math.max(0,Math.floor((Date.now()-new Date(value).getTime())/1000));if(!Number.isFinite(n))return '';if(n<60)return 'الآن';if(n<3600)return 'منذ '+Math.floor(n/60)+' دقيقة';if(n<86400)return 'منذ '+Math.floor(n/3600)+' ساعة';if(n<2592000)return 'منذ '+Math.floor(n/86400)+' يوم';return new Date(value).toLocaleDateString('ar-SA')}
async function refreshCommunities(){try{communities=(await api('communities')).items||[];const select=$('postCommunity');select.replaceChildren(new Option('عام',''),...communities.map(x=>new Option(x.name,x.id)));const area=$('communitiesList');area.replaceChildren();for(const cm of communities){const b=element('button',cm.name+' — '+(cm.description||''));b.type='button';b.onclick=()=>{communityFilter=cm.id;view='all';switchView('all')};area.append(b)}}catch(e){message(e.message)}}
async function refreshFavorites(){if(!account){favorites=new Set();return}try{favorites=new Set((await api('favorites')).ids||[])}catch(e){message(e.message)}}
function switchView(next){view=next;$('profilePanel').classList.toggle('hidden',next!=='profile');$('communitiesPanel').classList.toggle('hidden',next!=='communities');$('feed').parentElement.querySelector('#feedTitle').textContent=next==='favorites'?'المفضلة':communityFilter?'منشورات المجتمع':'المنشورات';$('feed').classList.toggle('hidden',!['all','favorites'].includes(next));$('previous').parentElement.classList.toggle('hidden',!['all','favorites'].includes(next));$('editor').classList.toggle('hidden',!account||next!=='publish');$('adminPanel').classList.toggle('hidden',account?.role!=='admin'||next!=='admin');if(next==='profile'&&account)showProfile(account.username);if(next==='all'||next==='favorites')load();if(next==='admin'&&account?.role==='admin')loadAdmin();if(next==='communities')refreshCommunities();if(next==='publish'&&!account)message('سجل الدخول أولاً')}
async function showProfile(name){try{const v=await api('profile&username='+encodeURIComponent(name));$('profileSummary').textContent=v.profile.username+' · '+ago(v.profile.created_at)+' · '+v.profile.posts+' منشور · '+(v.profile.bio||'');$('profileForm').classList.toggle('hidden',!account||account.username!==name);$('profileForm').elements.bio.value=v.profile.bio||'';$('profilePosts').replaceChildren(...v.items.map(p=>element('div',p.title+' · '+ago(p.created_at))))}catch(e){message(e.message)}}
function renderUser(){
 $('loginPanel').classList.toggle('hidden',!!account);
 $('editor').classList.toggle('hidden',!account||view!=='publish');
 $('communityForm').classList.toggle('hidden',!account);
 if(account)$('welcome').textContent='مرحباً '+account.username;
 $('adminPanel').classList.toggle('hidden',account?.role!=='admin'||view!=='admin');
 if(account?.role==='admin')loadAdmin();
}
async function loadAdmin(){
 try{
  const [s,u,logs,storage]=await Promise.all([api('admin-stats'),api('admin-users'),api('admin-security'),api('admin-storage')]);
  $('storageStatus').textContent='موقع التخزين: '+storage.path_label+' — '+(storage.mode==='outside'?'حماية أعلى':'حماية قياسية');
  $('storageMigrationForm').classList.toggle('hidden',!storage.can_migrate);
  $('securityEvents').replaceChildren(...logs.items.filter(Boolean).slice(0,20).map(v=>element('div',v.at+' · '+v.event+' · '+(v.subject||''))));
  $('stats').textContent='الأعضاء: '+s.users+' | المواضيع: '+s.posts+' | الردود: '+s.comments;
  const area=$('members');area.replaceChildren();
  for(const member of u.items){
   const line=element('div',undefined,'row');
   line.append(element('span',member.username+' — '+member.role+(member.banned?' (محظور)':'')));
   if(member.role!=='admin'){
    const b=element('button',member.banned?'رفع الحظر':'حظر');
    b.onclick=async()=>{if(!confirm('تأكيد تغيير حالة العضو؟'))return;try{await api('admin-ban',{id:member.id,banned:!member.banned});await loadAdmin()}catch(e){message(e.message)}};
    line.append(b);
   }area.append(line);
  }
 }catch(e){message(e.message)}
}
async function session(){try{const s=await api('session');csrf=s.csrf;account=s.user;renderUser();await refreshFavorites();await refreshCommunities()}catch(e){message(e.message)}}
async function load(){
 const feed=$('feed');feed.replaceChildren(element('p','جارٍ التحميل…'));
 try{
  const data=view==='favorites'?await api('favorites'):await api('posts&page='+page+(communityFilter?'&community='+encodeURIComponent(communityFilter):''));total=data.total??data.items.length;
  feed.replaceChildren();
  if(!data.items.length)feed.append(element('p','لا توجد منشورات بعد.'));
  for(const p of data.items){
   const a=element('article'),row=element('div',undefined,'post-row'),body=element('div',undefined,'post-body');
   if(p.image){const img=element('img');img.src=p.image;img.alt='صورة الموضوع';img.loading='lazy';img.referrerPolicy='no-referrer';row.append(img)}
   const author=element('button',p.author);author.type='button';author.onclick=()=>{view='profile';switchView('profile');showProfile(p.author)};
   const meta=element('div',undefined,'row');meta.append(author,element('small',ago(p.created_at)));
   const cm=communities.find(x=>x.id===p.community_id);if(cm)meta.append(element('span',cm.name,'tag'));
   body.append(element('h2',p.title),meta,element('p',p.text));
   const actions=element('div',undefined,'post-actions');
   if(account){const fav=element('button',favorites.has(p.id)?'♥ محفوظ':'♡ حفظ');fav.type='button';fav.onclick=async()=>{try{const v=await api('favorite',{id:p.id});if(v.saved)favorites.add(p.id);else favorites.delete(p.id);fav.textContent=v.saved?'♥ محفوظ':'♡ حفظ';if(view==='favorites')load()}catch(e){message(e.message)}};actions.append(fav)}
   body.append(actions);row.append(body);a.append(row);
   const toggle=element('button','عرض الردود');toggle.type='button';
   const replies=element('div');replies.hidden=true;
   toggle.onclick=async()=>{
    replies.hidden=!replies.hidden;if(replies.hidden)return;
    replies.replaceChildren(element('p','جارٍ التحميل…'));
    try{
     const data=await api('comments&post='+encodeURIComponent(p.id));replies.replaceChildren();
     for(const c of data.items){const div=element('div',undefined,'comment');div.append(element('strong',c.author),element('p',c.text));if(account?.role==='admin'){const del=element('button','حذف الرد');del.onclick=async()=>{if(!confirm('حذف الرد؟'))return;try{await api('admin-delete',{kind:'comments',id:c.id});toggle.click();toggle.click();await loadAdmin()}catch(e){message(e.message)}};div.append(del)}replies.append(div)}
     if(!data.items.length)replies.append(element('p','لا توجد ردود.'));
     if(account){
      const form=element('form');const input=element('textarea');input.required=true;input.maxLength=3000;input.placeholder='اكتب ردك';
      const send=element('button','إرسال الرد');form.append(input,send);
      form.onsubmit=async ev=>{ev.preventDefault();send.disabled=true;try{await api('comment',{post_id:p.id,text:input.value});toggle.click();toggle.click()}catch(e){message(e.message)}finally{send.disabled=false}};
      replies.append(form);
     }
    }catch(e){replies.replaceChildren(element('p',e.message))}
   };
   a.append(toggle,replies);
   if(account?.role==='admin'){
    const del=element('button','حذف الموضوع');
    del.onclick=async()=>{if(!confirm('حذف الموضوع وردوده نهائياً؟'))return;try{await api('admin-delete',{kind:'posts',id:p.id});await load();await loadAdmin()}catch(e){message(e.message)}};
    a.append(del);
   }feed.append(a);
  }
  $('pages').textContent=page+' / '+Math.max(1,Math.ceil(total/20));
  $('previous').disabled=page===1||view==='favorites';$('next').disabled=page*20>=total||view==='favorites';
 }catch(e){feed.replaceChildren(element('p',e.message))}
}
$('registerForm').onsubmit=async e=>{e.preventDefault();const form=e.currentTarget;const b=new FormData(form);try{await api('register',{username:b.get('username'),email:b.get('email'),password:b.get('password')});form.reset();message('تم إنشاء العضوية. راجع بريدك لتفعيلها قبل تسجيل الدخول.')}catch(err){message(err.message)}};
$('storageMigrationForm').onsubmit=async e=>{e.preventDefault();if(!confirm('هل توافق على نسخ البيانات والتحقق منها ثم التحويل إلى التخزين الخارجي؟'))return;const form=e.currentTarget;const btn=form.querySelector('button');btn.disabled=true;try{const b=new FormData(e.currentTarget);const v=await api('admin-storage-migrate',{password:b.get('password')});message(v.notice||'تم نقل البيانات');form.reset();await loadAdmin()}catch(err){message(err.message)}finally{btn.disabled=false}};
$('forgotForm').onsubmit=async e=>{e.preventDefault();const form=e.currentTarget;const b=new FormData(form);try{await api('forgot',{email:b.get('email')});message('إذا كان البريد مسجلاً، فسيصلك رابط الاستعادة.')}catch(err){message(err.message)}};
$('resetForm').onsubmit=async e=>{e.preventDefault();const form=e.currentTarget;const b=new FormData(form);try{await api('reset',{token:b.get('token'),password:b.get('password')});history.replaceState({},'',location.pathname);$('resetForm').classList.add('hidden');message('تم تحديث كلمة المرور')}catch(err){message(err.message)}};
$('loginForm').onsubmit=async e=>{e.preventDefault();const form=e.currentTarget;const b=new FormData(form);try{const v=await api('login',{username:b.get('username'),password:b.get('password')});account=v.user;csrf=v.csrf;renderUser();form.reset();message('تم تسجيل الدخول');await load()}catch(err){message(err.message)}};
$('logout').onclick=async()=>{try{await api('logout',{});account=null;await session();message('تم تسجيل الخروج');await load()}catch(e){message(e.message)}};
$('postForm').onsubmit=async e=>{e.preventDefault();const form=e.currentTarget;const b=new FormData(form);try{await api('post',{title:b.get('title'),text:b.get('text'),image:b.get('image'),community_id:b.get('community_id')});form.reset();page=1;message('تم نشر الموضوع');await load()}catch(err){message(err.message)}};
$('communityForm').onsubmit=async e=>{e.preventDefault();const form=e.currentTarget,b=new FormData(form);try{await api('community-create',{name:b.get('name'),slug:b.get('slug'),description:b.get('description')});form.reset();message('تم إنشاء المجتمع');await refreshCommunities()}catch(err){message(err.message)}};
$('profileForm').onsubmit=async e=>{e.preventDefault();const form=e.currentTarget,b=new FormData(form);try{await api('profile-update',{bio:b.get('bio')});message('تم حفظ الملف');await showProfile(account.username)}catch(err){message(err.message)}};
document.querySelectorAll('[data-view]').forEach(btn=>btn.onclick=()=>{if(btn.dataset.view==='all')communityFilter='';switchView(btn.dataset.view)});
$('previous').onclick=()=>{if(page>1){page--;load()}};
$('next').onclick=()=>{if(page*20<total){page++;load()}};
const theme=$('theme'),lang=$('lang');
try{document.documentElement.dataset.theme=localStorage.getItem('arbnt-theme')||'light'}catch(e){}
if(theme)theme.onclick=()=>{const v=document.documentElement.dataset.theme==='dark'?'light':'dark';document.documentElement.dataset.theme=v;try{localStorage.setItem('arbnt-theme',v)}catch(e){}};
if(lang)lang.onclick=()=>{message('واجهة المجتمع العربية حالياً، الترجمة قيد التطوير')};
session().then(async()=>{const q=new URLSearchParams(location.search);if(q.has('verify')){try{await api('verify',{token:q.get('verify')});message('تم تفعيل بريدك. يمكنك الدخول الآن.')}catch(e){message(e.message)}history.replaceState({},'',location.pathname)}if(q.has('reset')){$('resetForm').classList.remove('hidden');$('resetForm').elements.token.value=q.get('reset')}switchView('all')});
})();