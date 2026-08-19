# مكتب مجدي هلال — MAGDY HELAL CORP

موقع ووردبريس عربي (RTL) لمكتب محاسبة ومراجعة في مدينة نصر / جامعة الإمام. القالب مخصص على Bootstrap 4.

الاسم الظاهر: **مكتب مجدي هلال — MAGDY HELAL CORP**  
المدير: المحاسب القانوني والمستشار الضريبي **مجدي هلال**، مع فريق نحو 20–30 محاسباً.

لا يوجد دومين بعد. التشغيل المحلي: `http://localhost:8088`.

## بيانات التواصل

| | |
| --- | --- |
| البريد | `magdy.hilal@co` |
| الهاتف | `+201000354045` |
| واتساب (دولي بدون +) | `201000354045` |
| العنوان | مدينة نصر، جامعة الإمام، القاهرة |

عدّلها لاحقاً من: **المظهر → تخصيص → مكتب مجدي هلال**

## المطلوب على جهازك

- Docker و Docker Compose
- منفذ `8088` فارغ (الموقع مضبوط على 8088 لأن 8080 غالباً يكون مشغولاً)

لا تحتاج تثبيت PHP أو MySQL أو ووردبريس يدوياً.

## التشغيل المحلي (المشروع كاملاً)

من مجلد المشروع:

```bash
docker compose up -d --build
```

انتظر حتى تصبح حاوية `wordpress` جاهزة (أول مرة تنسخ ملفات ووردبريس). ثم ثبّت الموقع والصفحات:

```bash
docker compose --profile tools run --rm --entrypoint sh wpcli /scripts/setup.sh
```

افتح:

- الموقع: http://localhost:8088
- لوحة التحكم: http://localhost:8088/wp-admin
- المستخدم: `admin`
- كلمة المرور: `admin`

**غيّر كلمة مرور الأدمن قبل أي نشر.** لا تضع كلمة الأدمن داخل صورة Docker Hub العامة.

إيقاف:

```bash
docker compose down
```

حذف قاعدة البيانات أيضاً (بداية من صفر):

```bash
docker compose down -v
```

## معاينة للعميل / Screenshots

الموقع يعمل محلياً فقط. لعرضه على العميل أو الزملاء، صوّر الصفحات أو أرسل مجلد اللقطات:

`screenshots/`

يمكن ضغط المجلد وإرساله:

```bash
zip -r magdy-helal-corp-screenshots.zip screenshots
```

الملفات المتوقعة:

| ملف | المحتوى |
| --- | --- |
| `00-logo.png` | شعار MAGDY HELAL CORP |
| `00b-logo-header.png` | الشعار في رأس الصفحة |
| `00c-logo-footer.png` | الشعار في التذييل |
| `01-home.png` | الرئيسية |
| `01b-home-services.png` | الخدمات في الرئيسية |
| `01c-home-footer.png` | تذييل الرئيسية |
| `02-about.png` | من نحن |
| `03-services.png` | خدماتنا |
| `04-team.png` | فريق العمل |
| `05-clients.png` | عملاؤنا |
| `06-projects.png` | مشاريعنا |
| `07-news.png` | الأخبار |
| `08-contact.png` | تواصل معنا |
| `09-header-phone-desktop.png` | الرأس على سطح المكتب — الهاتف `+201000354045` |
| `10-home-mobile-375.png` | الرئيسية على الجوال (375) |
| `10b-home-mobile-menu.png` | قائمة الجوال المفتوحة |
| `11-home-tablet-768.png` | الرئيسية على الجهاز اللوحي (768) |
| `12-home-laptop-1024.png` | الرئيسية على لابتوب (1024) — قائمة همبرغر |

الشعار الأصلي أيضاً في `wp-content/themes/magdi-hilal-adco/assets/img/logo.png` (و`logo-white.png` للتذييل). يمكن رفع شعار آخر من **المظهر → تخصيص → هوية الموقع**.

## ماذا يوجد في الموقع

| الصفحة | الغرض |
| --- | --- |
| الرئيسية | شريحة صور، قيم المكتب، نبذة، أرقام، خدمات، أقسام، عملاء، أخبار، تواصل |
| من نحن | مكتب مجدي هلال في مدينة نصر / جامعة الإمام |
| خدماتنا | ضرائب، مراجعة، أنظمة محاسبية، استشارات |
| فريق العمل | مجدي هلال مديراً للمكتب، وبطاقات لفريق 20–30 محاسباً |
| عملاؤنا | مكان شعارات الشركات |
| مشاريعنا | نماذج أعمال |
| الأخبار | مقالات ووردبريس العادية |
| تواصل معنا | هاتف وبريد ونموذج: اسم، بريد، رسالة |

النشرة البريدية في التذييل تحفظ الإيميلات داخل لوحة التحكم تحت **النشرة البريدية**. رسائل النموذج تظهر تحت **رسائل التواصل**.

## المعمارية

```
docker-compose.yml          تطوير محلي: WordPress + MySQL 8 + Redis 7 + ربط القالب
docker-compose.prod.yml     تشغيل الصورة المنشورة + MySQL + Redis
Dockerfile                  PHP 8.2 + Redis + القالب داخل الصورة
wp-content/themes/magdi-hilal-adco/
  inc/                      إعداد، CPT، تخصيص، نماذج، بيانات أولية
  template-parts/           أقسام الصفحة الرئيسية
  page-templates/           قوالب الصفحات
  assets/                   CSS / JS / صور / الشعار
wp-content/mu-plugins/      إعدادات خفيفة (Redis + السماح ببريد magdy.hilal@co)
scripts/setup.sh            تثبيت عربي + تفعيل القالب + Redis
screenshots/                لقطات للعميل
```

- **WordPress** يخدم الصفحات والقالب.
- **MySQL** يخزن المحتوى. بيانات قاعدة التطوير المحلية ليست حسابات المكتب العامة.
- **Redis** كاش الكائنات عبر إضافة Redis Object Cache بعد `setup.sh`.
- القالب لا يعتمد على Elementor. التعديل إما من الصفحات/أنواع المحتوى أو من ملفات القالب.

أنواع محتوى إضافية في لوحة التحكم: الخدمات، فريق العمل، العملاء، المشاريع.

اسم مجلد القالب يبقى `magdi-hilal-adco` حتى لا تنكسر أحجام Docker. الاسم الظاهر للعلامة هو **MAGDY HELAL CORP**.

## بناء صورة Docker ودفعها إلى Docker Hub

الاسم الذي اختاره المستخدم: `momousa1997/magdyhelalCORP`.

مستودعات Docker Hub تُحوَّل إلى أحرف صغيرة. الاسم الفعلي للصورة يجب أن يكون:

`momousa1997/magdyhelalcorp`

(نفس المستودع؛ الواجهة تعرضه بالأحرف الصغيرة.)

الصورة تحتوي ووردبريس + PHP 8.2 + القالب + mu-plugins. **ليست** المكدس كاملاً: MySQL و Redis يبقيان خدمتين منفصلتين (انظر `docker-compose.prod.yml`).

لا تُخبز كلمات مرور الأدمن أو قاعدة البيانات داخل الصورة. القالب آمن للرفع؛ بيانات MySQL/Redis تُمرَّر من متغيرات البيئة.

### بناء الصورة

```bash
docker build -t momousa1997/magdyhelalcorp:latest .
```

### تسجيل الدخول، الوسم، والدفع

يلزم حساب Docker Hub باسم `momousa1997` ومستودع `magdyhelalcorp`. أنشئ المستودع من https://hub.docker.com إن لم يكن موجوداً (Create Repository → الاسم `magdyhelalcorp`).

```bash
docker login
docker tag momousa1997/magdyhelalcorp:latest momousa1997/magdyhelalcorp:latest
docker push momousa1997/magdyhelalcorp:latest
```

`docker login` تفاعلي (اسم مستخدم وكلمة مرور أو رمز وصول). إذا لم تكن مسجّلاً، الأمر ينتظر الإدخال ولا يكتمل من تلقاء نفسه.

### تشغيل الصورة المنشورة

```bash
docker compose -f docker-compose.prod.yml up -d
```

أو يدوياً (مع MySQL و Redis):

```yaml
services:
  db:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: magdi_hilal
      MYSQL_USER: magdi
      MYSQL_PASSWORD: changeme
      MYSQL_ROOT_PASSWORD: changeme_root
    volumes:
      - db_data:/var/lib/mysql

  redis:
    image: redis:7-alpine

  wordpress:
    image: momousa1997/magdyhelalcorp:latest
    ports:
      - "8088:80"
    environment:
      WORDPRESS_DB_HOST: db
      WORDPRESS_DB_USER: magdi
      WORDPRESS_DB_PASSWORD: changeme
      WORDPRESS_DB_NAME: magdi_hilal
      WORDPRESS_TABLE_PREFIX: mha_
      WORDPRESS_CONFIG_EXTRA: |
        define('WP_REDIS_HOST', 'redis');
        define('WP_REDIS_PORT', 6379);
        define('WP_HOME', 'http://localhost:8088');
        define('WP_SITEURL', 'http://localhost:8088');
        define('FS_METHOD', 'direct');
    depends_on:
      - db
      - redis

volumes:
  db_data:
```

بعد أول تشغيل للصورة المنشورة تحتاج تثبيت ووردبريس (أو تشغيل سكربت الإعداد إن وفّرت wp-cli). التطوير المحلي يستخدم `docker-compose.yml` + `setup.sh` كما فوق.

## بعد أن يتوفر الدومين

1. غيّر `WP_HOME` و `WP_SITEURL` في compose أو من **الإعدادات → عام**.
2. غيّر كلمة مرور `admin`.
3. راجع الهاتف والبريد والعنوان في المخصص إن لزم.
4. ارفع شعارات العملاء الحقيقية وصور الفريق.

## ملاحظات

- الأرقام الإحصائية (سنوات / عملاء) تقديرية للعرض. صحّحها من المخصص.
- واتساب يستخدم الرقم الدولي بدون `+`: `201000354045`. الهاتف المعروض: `+201000354045` (اتجاه LTR حتى لا ينعكس في العربية).
- إذا ظهرت صفحة ووردبريس الافتراضية بدل القالب، أعد تشغيل `setup.sh`.
- إذا فشل Redis، الموقع يعمل بدون كاش. راقب: `docker compose logs redis`.
# magdyHelalCorporation
