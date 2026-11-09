# ARBNT v0.3.0 — security trial

## Implemented in repository
- Native PHP 8.3 + JSON; no SQL, Composer, Node or file-upload endpoint.
- Password hashes, session regeneration, CSRF token, secure-origin validation, HTTP-only SameSite cookies.
- Public member registration with email verification (30-minute one-time link).
- Password recovery via 30-minute one-time email link, invalidating existing sessions.
- Login limits by IP and username, signup/recovery throttling, post/reply write throttling.
- Admin: member bans, deletion of posts/replies, counts, recent audit events.
- API HTTPS requirement, JSON content-type and body-size limits, text-only rendering, security log stored outside web root.
- JSON storage outside the site's DocumentRoot, file locking and atomic updates.

## Required before live launch
- **PHP mail() must be configured and tested** with SPF/DKIM/DMARC. Email delivery is not verified.
- **DDoS cannot be solved by PHP.** Configure web-server request limits, firewall/CSF, reverse proxy/CDN or provider network mitigation separately. None of these server settings were changed.
- Add and test automatic backups, security regression tests, account recovery edge cases, abuse protection, and full installer hardening.
- Current application is a trial. Do not publish publicly or distribute to customers until penetration testing and mail verification succeed.
- No file-upload feature exists. Do not add uploads without an allowlist, isolated non-executable storage, MIME validation, and file size limits.

## Trial installation
Use a dedicated HTTPS domain DocumentRoot, PHP 8.3+, and writable parent outside **all** publicly reachable directories. Upload project files there; open `install.php`; create the administrator; remove `install.php` immediately; open `community.html`. JSON private storage is created at `../arbnt-private` relative to the DocumentRoot. Ensure the directory is not reachable through any Apache alias, other domain or symlink. Back up private data before upgrades.

## نقل التخزين من لوحة الإدارة
بعد التثبيت افتح `community.html` وسجل دخول المدير، ثم قسم «الأمان ومكان التخزين». إذا كان التخزين داخل `storage/` يظهر زر النقل إلى المجلد الخاص `../arbnt-private`. يتطلب تأكيد كلمة مرور المدير. يقوم النظام بنسخ ملفات JSON وسجلاتها والتحقق من SHA-256 قبل تغيير مسار التخزين. يظل المصدر القديم محفوظاً؛ لا تحذفه حتى تتأكد من عمل الموقع وتوفر نسخة احتياطية. إذا كانت الاستضافة تمنع إنشاء مجلد خارج الموقع، يظهر خطأ ولا يتغير المسار.

**ملاحظة:** لا يزال يلزم اختبار تكاملي على الاستضافة قبل استخدام الميزة على بيانات مهمة. يفضل إيقاف الكتابة أثناء نقل البيانات الحساسة أو أخذ نسخة احتياطية أولاً.

## التثبيت داخل new ثم النقل إلى الجذر (نسخة تجريبية)
1. فك ملفات ZIP داخل `public_html/new/` وافتح `https://example.com/new/install.php` عبر HTTPS.
2. ينشئ المثبت `arbnt-app/` لمحرك PHP و`arbnt-private/` للبيانات بجانب مجلد `public_html` عندما تسمح الصلاحيات. إذا تعذر إنشاء مجلد المحرك الخارجي يتوقف التثبيت؛ لا تنقل المحرك إلى مجلد عام كحل بديل.
3. بعد التثبيت جرّب تسجيل الدخول والمنشورات والبريد. **احتفظ بنسخة احتياطية** قبل النقل.
4. انقل محتويات `public_html/new/` إلى `public_html/` عبر مدير الملفات، بما فيها الملفات المخفية `.arbnt-app.php` و`.arbnt-storage.php` و`.arbnt-migration.lock`. **لا تنقل أو تحذف** `arbnt-app/` و`arbnt-private/` خارج الموقع. تحقق من مسار الدومين قبل النقل.
5. تحقق من تسجيل الدخول والبيانات بعد النقل، واحذف `install.php` من المجلد العام.

تنبيه: في حال كان التخزين الداخلي `storage/` مستخدماً، انقله أولاً من لوحة الإدارة إلى التخزين الخارجي وتأكد من اكتماله **قبل** نقل مجلد الموقع. يمنع الوصول العام إلى محرك PHP بفصله عن DocumentRoot؛ لا يغني ذلك عن الحماية من البوتات أو حدود الطلبات.
