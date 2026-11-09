/* ARBNT community: native browser APIs only */
(()=>{'use strict';
const $=id=>document.getElementById(id);
let csrf='',account=null,page=1,total=0;
const endpoint='api/community.php?action=';
function message(t){$('notice').textContent=t}
async function api(action,body){
 const options={credentials:'same-origin'};
 if(body!==undefined){options.method='POST';options.headers={'Content-Type':'application/json','X-CSRF-Token':csrf};options.body=JSON.stringify(body)}
 const r=await fetch(endpoint+action,options);
 const v=await r.json();if(!r.ok)throw Error(v.error||'خطأ في الاتصال');return v;
}
function element(tag,text,cls){const n=document.createElement(tag);if(text!==undefined)n.textContent=text;if(cls)n.className=cls;return n}
function renderUser(){
 $('loginPanel').classList.toggle('hidden',!!account);
 $('editor').classList.toggle('hidden',!account);
 if(account)$('welcome').textContent='مرحباً '+account.username;
 $('adminPanel').classList.toggle('hidden',account?.role!=='admin');
 if(account?.role==='admin')loadAdmin();
}
async function loadAdmin(){
 try{
  const [s,u]=await Promise.all([api('admin-stats'),api('admin-users')]);
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
async function session(){try{const s=await api('session');csrf=s.csrf;account=s.user;renderUser()}catch(e){message(e.message)}}
async function load(){
 const feed=$('feed');feed.replaceChildren(element('p','جارٍ التحميل…'));
 try{
  const data=await api('posts&page='+page);total=data.total;
  feed.replaceChildren();
  if(!data.items.length)feed.append(element('p','لا توجد منشورات بعد.'));
  for(const p of data.items){
   const a=element('article');a.append(element('h2',p.title),element('small','بواسطة '+p.author+' · '+new Date(p.created_at).toLocaleString('ar-SA')),element('p',p.text));
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
  $('previous').disabled=page===1;$('next').disabled=page*20>=total;
 }catch(e){feed.replaceChildren(element('p',e.message))}
}
$('registerForm').onsubmit=async e=>{e.preventDefault();const b=new FormData(e.currentTarget);try{await api('register',{username:b.get('username'),password:b.get('password')});e.currentTarget.reset();message('تم إنشاء العضوية. يمكنك تسجيل الدخول الآن.')}catch(err){message(err.message)}};
$('loginForm').onsubmit=async e=>{e.preventDefault();const b=new FormData(e.currentTarget);try{const v=await api('login',{username:b.get('username'),password:b.get('password')});account=v.user;csrf=v.csrf;renderUser();e.currentTarget.reset();message('تم تسجيل الدخول');await load()}catch(err){message(err.message)}};
$('logout').onclick=async()=>{try{await api('logout',{});account=null;await session();message('تم تسجيل الخروج');await load()}catch(e){message(e.message)}};
$('postForm').onsubmit=async e=>{e.preventDefault();const b=new FormData(e.currentTarget);try{await api('post',{title:b.get('title'),text:b.get('text')});e.currentTarget.reset();page=1;message('تم نشر الموضوع');await load()}catch(err){message(err.message)}};
$('previous').onclick=()=>{if(page>1){page--;load()}};
$('next').onclick=()=>{if(page*20<total){page++;load()}};
const theme=$('theme'),lang=$('lang');
try{document.documentElement.dataset.theme=localStorage.getItem('arbnt-theme')||'light'}catch(e){}
if(theme)theme.onclick=()=>{const v=document.documentElement.dataset.theme==='dark'?'light':'dark';document.documentElement.dataset.theme=v;try{localStorage.setItem('arbnt-theme',v)}catch(e){}};
if(lang)lang.onclick=()=>{message('واجهة المجتمع العربية حالياً، الترجمة قيد التطوير')};
session().then(load);
})();