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
async function refreshCommunities(){try{communities=(await api('communities')).items||[];const select=$('postCommunity');const keep=communityFilter||select.value||'';select.replaceChildren(new Option('عام',''),...communities.map(x=>new Option(x.name,x.id)));if([...select.options].some(o=>o.value===keep))select.value=keep;const area=$('communitiesList');area.replaceChildren();for(const cm of communities){const b=element('button',cm.name+' — '+(cm.description||''));b.type='button';b.onclick=()=>{communityFilter=cm.id;view='all';const select=$('postCommunity');if(select)select.value=cm.id;switchView('all')};area.append(b)}}catch(e){message(e.message)}}
async function refreshFavorites(){if(!account){favorites=new Set();return}try{favorites=new Set((await api('favorites')).ids||[])}catch(e){message(e.message)}}
function switchView(next,profileName=''){
 if(next==='admin'){location.href='admin.html';return}
 if((['favorites','publish'].includes(next)||(next==='profile'&&!profileName))&&!account){message('سجّل الدخول أولاً للمشاركة');next='auth'}
 view=next;
 document.querySelectorAll('[data-view]').forEach(b=>b.classList.toggle('active',b.dataset.view===next));
 $('loginPanel').classList.toggle('hidden',!!account||next!=='auth');
 $('profilePanel').classList.toggle('hidden',next!=='profile');
 $('communitiesPanel').classList.toggle('hidden',next!=='communities');
 $('feedWrap').classList.toggle('hidden',!['all','favorites'].includes(next));
 $('feedTitle').textContent=next==='favorites'?'المفضلة':communityFilter?'منشورات المجتمع':'أحدث المنشورات';
 $('editor').classList.toggle('hidden',!account||next!=='publish');
 if(next==='profile'&&(account||profileName))showProfile(profileName||(account?.username||''));
 if(next==='all'||next==='favorites')load();
 if(next==='communities')refreshCommunities();
}
async function showProfile(name){try{const v=await api('profile&username='+encodeURIComponent(name));$('profileSummary').textContent=v.profile.username+' · '+ago(v.profile.created_at)+' · '+v.profile.posts+' منشور · '+(v.profile.bio||'');$('profileForm').classList.toggle('hidden',!account||account.username!==name);$('profileForm').elements.bio.value=v.profile.bio||'';$('profilePosts').replaceChildren(...v.items.map(p=>element('div',p.title+' · '+ago(p.created_at))))}catch(e){message(e.message)}}
function renderUser(){
 $('authNav').classList.toggle('hidden',!!account);
 $('adminLink').classList.toggle('hidden',account?.role!=='admin');
 $('loginPanel').classList.toggle('hidden',!!account||view!=='auth');
 $('editor').classList.toggle('hidden',!account||view!=='publish');
 $('communityForm').classList.toggle('hidden',!account);
 if(account)$('welcome').textContent='مرحباً '+account.username;
}
async function loadAdmin(){}
async function session(){try{const s=await api('session');csrf=s.csrf;account=s.user;renderUser();await refreshFavorites();await refreshCommunities();try{const info=await api('site-info');if(info.site_name)$('siteTitle').textContent=info.site_name;if(info.site_description)$('siteDescription').textContent=info.site_description}catch(e){}}catch(e){message(e.message)}}
async function load(){
 const feed=$('feed');feed.replaceChildren(element('p','جارٍ التحميل…'));
 try{
  const data=view==='favorites'?await api('favorites'):await api('posts&page='+page+(communityFilter?'&community='+encodeURIComponent(communityFilter):''));total=data.total??data.items.length;
  feed.replaceChildren();let visible=0;const q=($('query')?.value||'').trim().toLocaleLowerCase();
  if(!data.items.length)feed.append(element('p','لا توجد منشورات بعد.'));
  for(const p of data.items){
   const hay=[p.title,p.text,p.author].filter(Boolean).join(' ').toLocaleLowerCase();if(q&& !hay.includes(q))continue;visible++;
   const a=element('article');
   const row=element('div',undefined,'post-row'),body=element('div',undefined,'post-body');
   if(p.image){const img=element('img');img.src=p.image;img.alt='صورة الموضوع';img.loading='lazy';img.referrerPolicy='no-referrer';img.className='post-thumb';body.append(img)}
   const title=element('h2',p.title);
   const cm=communities.find(x=>x.id===p.community_id);
   const meta=element('div',undefined,'meta post-toolbar');
   const author=element('button','👤 '+p.author,'meta-action author-action');author.type='button';author.title='عرض ملف الكاتب';author.onclick=()=>switchView('profile',p.author);meta.append(author);
   if(cm){const communityBtn=element('button','▣ '+cm.name,'meta-action community-action');communityBtn.type='button';communityBtn.onclick=()=>{communityFilter=cm.id;switchView('all')};meta.append(communityBtn)}
   meta.append(element('span','◷ '+ago(p.created_at),'meta-time'));
   const actions=element('div',undefined,'post-actions post-toolbar-actions');
   meta.append(actions);body.append(title,element('p',p.text),meta);
   if(account){const fav=element('button',favorites.has(p.id)?'♥ محفوظ':'♡ مفضلة','tool-action favorite-action');fav.type='button';fav.onclick=async()=>{try{const v=await api('favorite',{id:p.id});if(v.saved)favorites.add(p.id);else favorites.delete(p.id);fav.textContent=v.saved?'♥ محفوظ':'♡ حفظ';if(view==='favorites')load()}catch(e){message(e.message)}};actions.append(fav)}
   const toggle=element('button','💬 '+String(p.reply_count??0)+' رد','tool-action replies-action');toggle.type='button';actions.append(toggle);row.append(body);a.append(row);
   const replies=element('div');replies.hidden=true;replies.style.gridColumn='2';
   toggle.onclick=async()=>{
    replies.hidden=!replies.hidden;if(replies.hidden)return;
    replies.replaceChildren(element('p','جارٍ التحميل…'));
    try{
     const data=await api('comments&post='+encodeURIComponent(p.id));replies.replaceChildren();
     for(const cmt of data.items){const div=element('div',undefined,'comment');div.append(element('strong',cmt.author),element('p',cmt.text));if(account?.role==='admin'){const del=element('button','حذف الرد');del.onclick=async()=>{if(!confirm('حذف الرد؟'))return;try{await api('admin-delete',{kind:'comments',id:cmt.id});replies.hidden=true;toggle.click()}catch(e){message(e.message)}};div.append(del)}replies.append(div)}
     if(!data.items.length)replies.append(element('p','لا توجد ردود.'));
     if(account){const form=element('form'),input=element('textarea'),send=element('button','إرسال الرد');input.required=true;input.maxLength=3000;input.placeholder='اكتب ردك';form.append(input,send);form.onsubmit=async ev=>{ev.preventDefault();send.disabled=true;try{await api('comment',{post_id:p.id,text:input.value});p.reply_count=Number(p.reply_count||0)+1;toggle.textContent='💬 '+String(p.reply_count)+' رد';replies.hidden=true;toggle.click()}catch(e){message(e.message)}finally{send.disabled=false}};replies.append(form)}
    }catch(e){replies.replaceChildren(element('p',e.message))}
   };
   if(account?.role==='admin'){actions.append(actionDeletePost(p.id))}
   a.append(replies);feed.append(a);
  }
  if(data.items.length&&!visible)feed.append(element('p','لا توجد منشورات مطابقة للبحث.'));
  $('pages').textContent=page+' / '+Math.max(1,Math.ceil(total/20));
  $('previous').disabled=page===1||view==='favorites';$('next').disabled=page*20>=total||view==='favorites';
 }catch(e){feed.replaceChildren(element('p',e.message))}
}
function actionDeletePost(id){const del=element('button','🗑 حذف','tool-action delete-action');del.type='button';del.onclick=async()=>{if(!confirm('حذف الموضوع وردوده نهائياً؟'))return;try{await api('admin-delete',{kind:'posts',id});await load()}catch(e){message(e.message)}};return del}
$('registerForm').onsubmit=async e=>{e.preventDefault();const form=e.currentTarget;const b=new FormData(form);try{const v=await api('register',{username:b.get('username'),email:b.get('email'),password:b.get('password')});form.reset();message(v.message||'تم إنشاء العضوية')}catch(err){message(err.message)}};
$('forgotForm').onsubmit=async e=>{e.preventDefault();const form=e.currentTarget;const b=new FormData(form);try{await api('forgot',{email:b.get('email')});message('إذا كان البريد مسجلاً، فسيصلك رابط الاستعادة.')}catch(err){message(err.message)}};
$('resetForm').onsubmit=async e=>{e.preventDefault();const form=e.currentTarget;const b=new FormData(form);try{await api('reset',{token:b.get('token'),password:b.get('password')});history.replaceState({},'',location.pathname);$('resetForm').classList.add('hidden');message('تم تحديث كلمة المرور')}catch(err){message(err.message)}};
$('loginForm').onsubmit=async e=>{e.preventDefault();const form=e.currentTarget;const b=new FormData(form);try{const v=await api('login',{username:b.get('username'),password:b.get('password')});account=v.user;csrf=v.csrf;renderUser();form.reset();message('تم تسجيل الدخول');switchView('all')}catch(err){message(err.message)}};
$('logout').onclick=async()=>{try{await api('logout',{});account=null;await session();message('تم تسجيل الخروج');switchView('all')}catch(e){message(e.message)}};
$('postForm').onsubmit=async e=>{e.preventDefault();const form=e.currentTarget;const b=new FormData(form);const selected=String(b.get('community_id')||'');try{const v=await api('post',{title:b.get('title'),text:b.get('text'),image:b.get('image'),community_id:selected});form.reset();page=1;communityFilter=selected;const select=$('postCommunity');if(select)select.value=selected;message('تم نشر الموضوع'+(selected?' داخل المجتمع':' في العام'));switchView('all');return v}catch(err){message(err.message)}};
$('communityForm').onsubmit=async e=>{e.preventDefault();const form=e.currentTarget,b=new FormData(form);try{await api('community-create',{name:b.get('name'),slug:b.get('slug'),description:b.get('description')});form.reset();message('تم إنشاء المجتمع');await refreshCommunities()}catch(err){message(err.message)}};
$('profileForm').onsubmit=async e=>{e.preventDefault();const form=e.currentTarget,b=new FormData(form);try{await api('profile-update',{bio:b.get('bio')});message('تم حفظ الملف');await showProfile(account.username)}catch(err){message(err.message)}};
document.querySelectorAll('[data-view]').forEach(btn=>btn.onclick=()=>{if(btn.dataset.view==='all')communityFilter='';if(btn.dataset.view==='publish'){const select=$('postCommunity');if(select&&communityFilter)select.value=communityFilter}switchView(btn.dataset.view)});
$('previous').onclick=()=>{if(page>1){page--;load()}};
$('next').onclick=()=>{if(page*20<total){page++;load()}};
const theme=$('theme'),lang=$('lang'),menu=$('menu'),query=$('query'),newPost=$('newPost');
try{document.documentElement.classList.toggle('dark',localStorage.getItem('arbnt-theme')==='dark'||(!localStorage.getItem('arbnt-theme')&&matchMedia('(prefers-color-scheme:dark)').matches))}catch(e){}
if(theme)theme.onclick=()=>{document.documentElement.classList.toggle('dark');try{localStorage.setItem('arbnt-theme',document.documentElement.classList.contains('dark')?'dark':'light')}catch(e){}};
if(lang)lang.onclick=()=>message('واجهة المجتمع العربية حالياً، الترجمة قيد التطوير');
if(menu)menu.onclick=()=>{const mobile=matchMedia('(max-width:800px)').matches;if(mobile){document.body.classList.toggle('mobile-open');menu.setAttribute('aria-expanded',String(document.body.classList.contains('mobile-open')))}else{document.body.classList.toggle('collapsed');menu.setAttribute('aria-expanded',String(!document.body.classList.contains('collapsed')))}};
if($('sidebar'))$('sidebar').addEventListener('click',e=>{if(matchMedia('(max-width:800px)').matches&&(e.target.closest('button,a')))document.body.classList.remove('mobile-open')});
if(query)query.oninput=()=>{if(['all','favorites'].includes(view))load()};
if(newPost)newPost.onclick=()=>{const select=$('postCommunity');if(select&&communityFilter)select.value=communityFilter;switchView('publish')};
session().then(async()=>{const q=new URLSearchParams(location.search);let next=q.get('view')==='auth'?'auth':'all';if(q.has('verify')){try{await api('verify',{token:q.get('verify')});message('تم تفعيل بريدك. يمكنك الدخول الآن.')}catch(e){message(e.message)}next='auth';history.replaceState({},'',location.pathname)}if(q.has('reset')){$('resetForm').classList.remove('hidden');$('resetForm').elements.token.value=q.get('reset');next='auth'}switchView(next)});
})();