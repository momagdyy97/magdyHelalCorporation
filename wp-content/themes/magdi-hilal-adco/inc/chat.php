<?php
/**
 * HELAL CORP consultation chatbot — MySQL persistence, retrieval router, REST API.
 *
 * Works fully offline. Optional OpenAI only if MHA_OPENAI_API_KEY / OPENAI_API_KEY is set.
 *
 * @package Magdi_Hilal_Adco
 */

if (!defined('ABSPATH')) {
    exit;
}

if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

define('MHA_CHAT_DB_VERSION', 8);
define('MHA_CHAT_MAX_LEN', 1000);
define('MHA_CHAT_RATE', 20);

function mha_chat_table($which)
{
    global $wpdb;
    $map = [
        'sessions'  => 'chat_sessions',
        'messages'  => 'chat_messages',
        'knowledge' => 'chat_knowledge',
    ];
    if (!isset($map[$which])) {
        return $wpdb->prefix . 'chat_sessions';
    }
    return $wpdb->prefix . $map[$which];
}

function mha_maybe_install_chat()
{
    if ((int) get_option('mha_chat_db_version') < MHA_CHAT_DB_VERSION) {
        mha_chat_install(true);
    }
}
add_action('after_setup_theme', 'mha_maybe_install_chat', 20);
add_action('init', 'mha_maybe_install_chat', 5);

function mha_chat_install($force_knowledge = false)
{
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset = $wpdb->get_charset_collate();
    $sessions = mha_chat_table('sessions');
    $messages = mha_chat_table('messages');
    $knowledge = mha_chat_table('knowledge');

    dbDelta("CREATE TABLE {$sessions} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        session_token varchar(64) NOT NULL,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        lang varchar(8) NOT NULL DEFAULT 'ar',
        ip_hash varchar(64) NOT NULL DEFAULT '',
        PRIMARY KEY  (id),
        UNIQUE KEY session_token (session_token),
        KEY updated_at (updated_at)
    ) {$charset};");

    dbDelta("CREATE TABLE {$messages} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        session_id bigint(20) unsigned NOT NULL,
        role varchar(16) NOT NULL,
        agent varchar(32) NOT NULL DEFAULT '',
        content longtext NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY session_id (session_id),
        KEY created_at (created_at)
    ) {$charset};");

    dbDelta("CREATE TABLE {$knowledge} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        slug varchar(64) NOT NULL,
        title varchar(255) NOT NULL,
        body longtext NOT NULL,
        tags varchar(500) NOT NULL DEFAULT '',
        source varchar(255) NOT NULL DEFAULT '',
        PRIMARY KEY  (id),
        UNIQUE KEY slug (slug)
    ) {$charset};");

    mha_chat_seed_knowledge($force_knowledge);
    mha_chat_purge_dev_hosts_from_knowledge();
    update_option('mha_chat_db_version', MHA_CHAT_DB_VERSION);
    return true;
}

function mha_chat_seed_knowledge($force = false)
{
    global $wpdb;
    $table = mha_chat_table('knowledge');

    foreach (mha_chat_knowledge_corpus() as $item) {
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s", $item['slug']));
        $row = [
            'slug'   => $item['slug'],
            'title'  => $item['title'],
            'body'   => $item['body'],
            'tags'   => $item['tags'],
            'source' => $item['source'],
        ];
        if ($existing) {
            if ($force || (int) get_option('mha_chat_db_version') < MHA_CHAT_DB_VERSION) {
                $wpdb->update($table, $row, ['id' => (int) $existing]);
            }
            continue;
        }
        $wpdb->insert($table, $row);
    }
}

function mha_chat_purge_dev_hosts_from_knowledge()
{
    global $wpdb;
    $table  = mha_chat_table('knowledge');
    $fields = ['body', 'source', 'title', 'tags'];
    $home   = untrailingslashit(home_url('/'));
    $rewrites = [
        'http://localhost:8088'                    => $home,
        'https://localhost:8088'                   => $home,
        'http://127.0.0.1:8088'                    => $home,
        'https://127.0.0.1:8088'                   => $home,
        'http://localhost'                         => $home,
        'https://localhost'                        => $home,
        'http://magdy.modevops.fun'                 => $home,
        'https://magdy.modevops.fun'                => $home,
        'http://magdyhelal.modevops.fun'            => $home,
        'https://magdyhelal.modevops.fun'           => $home,
        'http://www.helal.co'                       => $home,
        'https://www.helal.co'                      => $home,
        'http://magdyhelalcorp.infinityfree.io'     => $home,
        'https://magdyhelalcorp.infinityfree.io'    => $home,
    ];
    $bare = [
        'localhost:8088'                 => wp_parse_url($home, PHP_URL_HOST),
        '127.0.0.1:8088'                 => wp_parse_url($home, PHP_URL_HOST),
        'magdy.modevops.fun'              => wp_parse_url($home, PHP_URL_HOST),
        'magdyhelal.modevops.fun'         => wp_parse_url($home, PHP_URL_HOST),
        'www.helal.co'                    => wp_parse_url($home, PHP_URL_HOST),
        'magdyhelalcorp.infinityfree.io'  => wp_parse_url($home, PHP_URL_HOST),
    ];
    $erase = [
        '+201000354045',
        '201000354045',
        '01000354045',
        'magdy.hilal@co',
        'momagdyy97@gmail.com',
    ];
    foreach ($fields as $field) {
        foreach ($rewrites as $from => $to) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET {$field} = REPLACE({$field}, %s, %s)",
                $from,
                $to
            ));
        }
        foreach ($bare as $from => $to) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET {$field} = REPLACE({$field}, %s, %s)",
                $from,
                (string) $to
            ));
        }
        foreach ($erase as $from) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET {$field} = REPLACE({$field}, %s, '')",
                $from
            ));
        }
    }
}

function mha_chat_knowledge_corpus()
{
    $disclaimer = ' هذه معلومات عامة للتعريف وليست استشارة قانونية أو ضريبية مرخّصة، ولا تغني عن فحص ملف المنشأة داخل المكتب.';

    return [
        [
            'slug'   => 'site-map',
            'title'  => 'خريطة موقع مكتب مجدي هلال — HELAL CORP',
            'tags'   => 'موقع صفحات خدمات تواصل عن فريق عملاء مشاريع أخبار استشارة quote about /about/ services /services/ team /team/ clients /clients/ projects /projects/ news /news/ contact /contact/ home',
            'source' => 'موقع HELAL CORP',
            'body'   => 'مكتب مجدي هلال للمحاسبة والمراجعة (HELAL CORP) في مدينة نصر، القاهرة. المدير: المحاسب القانوني والمستشار الضريبي مجدي هلال، مع فريق نحو 20 إلى 30 محاسباً. '
                . 'صفحات الموقع: الرئيسية، من نحن، خدماتنا (ضرائب ومراجعة وأنظمة محاسبية واستشارات)، فريق العمل، عملاؤنا، مشاريعنا، الأخبار، وتواصل معنا. '
                . 'الهاتف: {{phone}} — البريد: {{email}} — العنوان: {{address}} — العمل: {{hours}}.'
                . $disclaimer,
        ],
        [
            'slug'   => 'contact-hours',
            'title'  => 'بيانات التواصل وساعات العمل',
            'tags'   => 'تواصل هاتف بريد عنوان ساعات contact phone email address hours quote موعد اتصال /contact/',
            'source' => 'موقع HELAL CORP',
            'body'   => 'للتواصل مع مكتب مجدي هلال — HELAL CORP: الهاتف {{phone}} (يُكتب من اليسار لليمين)، البريد {{email}}، العنوان {{address}}. ساعات العمل {{hours}}. يمكن طلب استشارة من صفحة «تواصل معنا». لا يوجد واتساب على أرقام المكتب الأرضية، ولا يوجد حساب فيسبوك للمكتب على هذا الموقع.',
        ],
        [
            'slug'   => 'income-tax-91-2005',
            'title'  => 'قانون الضريبة على الدخل رقم 91 لسنة 2005',
            'tags'   => 'ضريبة الدخل قانون 91 إقرار وعاء شركات شخص طبيعي income tax withholding كسب عمل',
            'source' => 'ملخص عام — قانون 91/2005',
            'body'   => 'قانون الضريبة على الدخل رقم 91 لسنة 2005 هو الإطار الرئيس لضريبة الدخل في مصر على الأشخاص الطبيعيين والاعتباريين، مع تعديلات لاحقة يجب الرجوع إليها لسنة الفحص. الوعاء الضريبي لا يُؤخذ من صافي الربح المحاسبي خامًا: تُجرى تسويات (مصروفات غير معتمدة، إيرادات معفاة أو مؤجلة، إهلاك ضريبي إن وُجد). الإقرار السنوي يُقدَّم وفق المواعيد والنماذج التي تعلنها مصلحة الضرائب. كسب العمل وخصم المنبع التزامات دورية مستقلة عن الإقرار السنوي. المنشأة مسؤولة عن الدفاتر والمستندات المؤيدة. المكتب يساعد على ترتيب الملف والإقرار؛ النتيجة النهائية لكل شركة تتوقف على وقائعها.' . $disclaimer,
        ],
        [
            'slug'   => 'vat-67-2016',
            'title'  => 'قانون الضريبة على القيمة المضافة رقم 67 لسنة 2016',
            'tags'   => 'قيمة مضافة ضريبة vat قانون 67 تسجيل إقرار خصم مدخلات مخرجات',
            'source' => 'ملخص عام — قانون 67/2016',
            'body'   => 'قانون الضريبة على القيمة المضافة رقم 67 لسنة 2016 ينظّم فرض الضريبة على بيع السلع وأداء الخدمات في مصر، مع جداول إعفاءات ومعاملة خاصة لبعض البنود تتغير بتشريعات لاحقة. المسجّل يصدر مستنداً معتمداً، ويحسب ضريبة المخرجات، ويخصم ضريبة المدخلات عند استيفاء الشروط (مستند صحيح، التخصيص للنشاط الخاضع، التوقيت). يُقدَّم إقرار دوري ويُسدَّد الفرق. الخصم على فاتورة ناقصة أو غير مؤهلة من أكثر أسباب رفض المصلحة. الربط مع منظومة الفاتورة الإلكترونية أو الإيصال — حيث يكون الإلزام قائماً — جزء من إثبات الخصم لا بديلاً عن صحة التكييف. السعر العام والقوائم التفصيلية تُراجع من النص الساري لا من ذاكرة قديمة.' . $disclaimer,
        ],
        [
            'slug'   => 'e-invoice-eta',
            'title'  => 'منظومة الفاتورة الإلكترونية (مصلحة الضرائب المصرية)',
            'tags'   => 'فاتورة إلكترونية منظومة eta مصلحة الضرائب إصدار تكامل توقيع إلكتروني einvoice e-invoice',
            'source' => 'ملخص عام — منظومة الفاتورة الإلكترونية',
            'body'   => 'منظومة الفاتورة الإلكترونية لدى مصلحة الضرائب المصرية تُلزم المنشآت المشمولة بالمراحل المقررة بإصدار فواتير البيع بين المسجّلين عبر المنظومة واعتمادها. عملياً تحتاج الشركة: تسجيلاً على المنظومة، توقيعاً إلكترونياً، ووسيلة إصدار (تكامل مع نظام الحسابات أو بوابة المصلحة). بيانات المشتري — رقم التسجيل والعنوان — يجب أن تطابق ما لدى المصلحة وإلا رُفضت الوثيقة. الرقم الداخلي للفاتورة يُربط بالقيد المحاسبي. التأخير أو الرفض لا يُسوَّى بإيصال ورقي في آخر الشهر. الفاتورة الإلكترونية قناة إثبات؛ صحة الوعاء والإقرار تبقى مسؤولية مستقلة. خدمة الأعمال الضريبية في المكتب تشمل متابعة المنظومة مع الدورة المستندية من صفحة خدماتنا.',
        ],
        [
            'slug'   => 'e-receipt-eta',
            'title'  => 'منظومة الإيصال الإلكتروني',
            'tags'   => 'إيصال إلكتروني مستهلك تجزئة كاشير ereceipt e-receipt نقطة بيع',
            'source' => 'ملخص عام — منظومة الإيصال الإلكتروني',
            'body'   => 'الإيصال الإلكتروني يغطي البيع للمستهلك النهائي غير المسجّل ضريبياً، بخلاف الفاتورة الإلكترونية التي تربط مشترياً مسجّلاً. الشركات المشمولة تهيّئ نقاط البيع أو برامج الكاشير للربط مع منظومة المصلحة وفق مراحل الإلزام. الإيصال غير المُرسل يفتح فجوة بين يومية الصندوق وإقرار القيمة المضافة. من يبيع للمسجّلين وللجمهور يفصل المسارين في النظام المحاسبي حتى لا يختلط النوعان عند الفحص.',
        ],
        [
            'slug'   => 'withholding-payroll',
            'title'  => 'خصم المنبع وكسب العمل ومسير الرواتب',
            'tags'   => 'خصم منبع كسب عمل رواتب أجور payroll withholding مرتبات مسير',
            'source' => 'ملخص عام — الالتزامات الدورية',
            'body'   => 'خصم الضريبة من المنبع عند سداد أتعاب أو توريدات معيّنة، وضريبة كسب العمل على الأجور، التزامان شهريان (أو وفق الدوريات السارية) لا يُرحَّلان إلى الإقرار السنوي. مسير الرواتب يجب أن يميّز العناصر الخاضعة والمعفاة والمزايا العينية وفق النص الساري. الدفع دون مستند كافٍ أو إخفاء المورد تحت «نثريات» يضعف الملف. يُطابق السداد مع نماذج المصلحة وكشوف البنك. التأمينات الاجتماعية أساسها قد يختلف عن أساس كسب العمل؛ الاختلاف يُوثَّق ولا يُطمس.',
        ],
        [
            'slug'   => 'social-insurance-148-2019',
            'title'  => 'قانون التأمينات الاجتماعية والمعاشات رقم 148 لسنة 2019',
            'tags'   => 'تأمينات اجتماعية قانون 148 معاشات اشتراك أجر هيئة',
            'source' => 'ملخص عام — قانون 148/2019',
            'body'   => 'قانون التأمينات الاجتماعية والمعاشات رقم 148 لسنة 2019 ينظّم اشتراك صاحب العمل والعامل وحدود أجر الاشتراك والحماية. تسجيل الملتحق الجديد في المواعيد، وسداد الاشتراكات، ومطابقة الأسماء مع العقود الفعلية، بنود تظهر سريعاً عند التفتيش أو إنهاء الخدمة. الحد الأدنى والأقصى لأجر الاشتراك يُعلنان دورياً فيجب تحديث المسير. تكييف البدلات وضمّها أو استبعادها يتم وفق النظام لا وفق رغبة الإدارة. الخلط بين العامل التابع والمقاول المستقل يرتّب التزاماً خاطئاً. المكتب يراجع التكامل بين المسير والتأمينات وكسب العمل.',
        ],
        [
            'slug'   => 'companies-159-investment',
            'title'  => 'قانون الشركات 159 لسنة 1981 والاستثمار',
            'tags'   => 'شركات قانون 159 تأسيس استثمار سجل تجاري جمعية عمومية شركاء investment',
            'source' => 'ملخص عام — قانون 159/1981 وإطار الاستثمار',
            'body'   => 'قانون شركات المساهمة وشركات التوصية بالأسهم والشركات ذات المسؤولية المحدودة رقم 159 لسنة 1981، مع تعديلاته، ينظّم التأسيس والحوكمة الداخلية لأشكال شائعة من الشركات في مصر. إلى جانبه توجد قوانين استثمار لاحقة (ومنها قانون الاستثمار رقم 72 لسنة 2017) تمنح حوافز وإجراءات لمن ينطبق عليه النظام. التأسيس يحتاج عقداً ونظاماً، وتسجيلاً، ودفاتر، وفي كثير من الحالات مراقب حسابات وفق الشكل القانوني. الاستشارة المالية في المكتب تساعد على ترتيب الهيكل والدورة المحاسبية بعد التأسيس؛ التراخيص والسجل تبقى لدى الجهات المختصة. التفاصيل في صفحة خدماتنا، وطلب الاستشارة من صفحة تواصل معنا.',
        ],
        [
            'slug'   => 'audit-vs-tax-exam',
            'title'  => 'المراجعة والفحص الضريبي: اختلاف الهدف',
            'tags'   => 'مراجعة تدقيق فحص ضريبي رقابة داخلية قوائم مالية audit review examination',
            'source' => 'ملخص مهني — المراجعة والفحص',
            'body'   => 'المراجعة (الخارجية أو الداخلية) تقيّم ما إذا كانت القوائم تعرض المركز والأداء بعدالة وفق إطار التقرير المالي، وتختبر الرقابة الداخلية والمخاطر. الفحص الضريبي الذي تجريه مصلحة الضرائب يتحقق من صحة الإقرار والوعاء والمستندات النظامية. الدفاتر واحدة؛ السؤال مختلف. الاستعداد للفحص: ملف إقرارات ونماذج خصم ومراسلات المنظومة، وربط الأرقام الجوهرية بمستند، ومذكرة فروق بين الربح المحاسبي والضريبي. تقرير المراجعة لا يُغني عن الرد على طلبات المأمور، وإقرار الضريبة لا يُغني عن رأي المراجع حين يُطلب. خدمة المراجعة في المكتب موضّحة في صفحة خدماتنا.',
        ],
        [
            'slug'   => 'bookkeeping-statements',
            'title'  => 'إمساك الدفاتر والقوائم المالية والإقفال',
            'tags'   => 'دفاتر محاسبة إقفال قوائم ميزان مراجعة مخزون عملاء موردين bookkeeping statements closing',
            'source' => 'ملخص مهني — المحاسبة',
            'body'   => 'إمساك الدفاتر يعني مستنداً لكل قيد، ودليل حسابات ثابت، ومطابقة البنوك والنقدية، ومتابعة العملاء والموردين، ومعالجة المخزون بما يناسب النشاط، وإقفالاً شهرياً للحسابات الوسيطة. القوائم المالية تُشتق من ميزان مراجعة مغلق لا من جداول جانبية. البنوك والشركاء يلاحظون الحساب الجاري غير المفسَّر والحركة المعلّقة. معايير العرض السارية على المنشأة تتطلب إفصاحاً أوضح من «صافي الربح» في سطر. المكتب يبني الدورة من المستند إلى التقرير ضمن خدمة الأنظمة المحاسبية في صفحة خدماتنا.',
        ],
        [
            'slug'   => 'cbe-economy-literacy',
            'title'  => 'البنك المركزي والتضخم وأسعار العائد — تثقيف اقتصادي',
            'tags'   => 'بنك مركزي تضخم فائدة اقتصاد جنيه صرف سياسة نقدية cbe inflation interest economy',
            'source' => 'ملخص تثقيفي — ليس نصيحة استثمار',
            'body'   => 'البنك المركزي المصري يدير السياسة النقدية مستهدفاً استقرار الأسعار. التضخم يغيّر القوة الشرائية للتدفقات؛ أسعار العائد تغيّر تكلفة التمويل؛ سعر الصرف يغيّر قياس الأصول والالتزامات بالعملة الأجنبية. المحاسب يُظهر الأثر في الفوائد وفروق العملة وتقييم المخزون عند تغيّر السوق، دون التنبؤ بقرار اللجنة. هذا النص للتثقيف العام وليس توصية بشراء أو بيع أي أداة مالية ولا بديلاً عن مستشار استثمار مرخّص. لمتابعة السياق يمكن قراءة مقالات المكتب من صفحة الأخبار.',
        ],
        [
            'slug'   => 'services-tax',
            'title'  => 'خدمة الأعمال الضريبية في المكتب',
            'tags'   => 'خدمة ضرائب إقرارات فحص قيمة مضافة فاتورة',
            'source' => 'موقع HELAL CORP',
            'body'   => 'قسم الضرائب في مكتب مجدي هلال يتابع الإقرارات والفحص والقيمة المضافة والمنظومات الإلكترونية بما يناسب حجم الشركة. التفاصيل في صفحة خدماتنا، وللتواصل من صفحة تواصل معنا.',
        ],
        [
            'slug'   => 'services-audit',
            'title'  => 'خدمة المراجعة والتدقيق في المكتب',
            'tags'   => 'خدمة مراجعة تدقيق رقابة قوائم',
            'source' => 'موقع HELAL CORP',
            'body'   => 'المراجعة في المكتب تركز على المخاطر والضوابط لا على الشكل فقط: تخطيط، تقييم رقابة داخلية، وتقارير للإدارة. التفاصيل في صفحة خدماتنا، ومشاريع مماثلة في صفحة مشاريعنا.',
        ],
        [
            'slug'   => 'services-accounting',
            'title'  => 'خدمة الأنظمة المحاسبية في المكتب',
            'tags'   => 'خدمة محاسبة دفاتر تقارير شهرية أنظمة',
            'source' => 'موقع HELAL CORP',
            'body'   => 'الأنظمة المحاسبية تشمل إمساك الدفاتر والدورة المستندية والتقارير الشهرية التي تساعد الإدارة على قراءة المركز المالي. التفاصيل في صفحة خدماتنا.',
        ],
        [
            'slug'   => 'about-firm',
            'title'  => 'عن مكتب مجدي هلال',
            'tags'   => 'من نحن مكتب مجدي هلال فريق مدينة نصر about firm team',
            'source' => 'موقع HELAL CORP',
            'body'   => 'مكتب مهني للمحاسبة والمراجعة والاستشارات الضريبية في {{address}}. يقوده مجدي هلال، ويعمل فيه فريق يضم نحو 20 إلى 30 محاسباً. نبذة أوفى في صفحة من نحن، والفريق في صفحة فريق العمل، والعملاء في صفحة عملاؤنا.',
        ],
    ];
}

function mha_chat_page_url($slug)
{
    $slug = trim((string) $slug, '/');
    if ($slug === '' || $slug === 'home') {
        return home_url('/');
    }
    return home_url('/' . $slug . '/');
}

function mha_chat_canonical_host()
{
    return 'helal.co';
}

function mha_chat_public_origin()
{
    $origin = untrailingslashit(home_url());
    $host   = strtolower((string) wp_parse_url($origin, PHP_URL_HOST));
    $leftover = [
        '',
        'localhost',
        '127.0.0.1',
        'www.helal.co',
        'magdy.modevops.fun',
        'magdyhelal.modevops.fun',
        'magdyhelalcorp.infinityfree.io',
    ];
    if (in_array($host, $leftover, true)) {
        return 'https://' . mha_chat_canonical_host();
    }
    return $origin;
}

function mha_chat_rewrite_dev_hosts($text)
{
    $origin = mha_chat_public_origin();
    $text   = (string) $text;
    $text   = preg_replace(
        [
            '#https?://localhost:8088#i',
            '#https?://127\.0\.0\.1:8088#i',
            '#https?://localhost(?!:)#i',
            '#https?://127\.0\.0\.1(?!:)#i',
            '#https?://www\.helal\.co#i',
            '#https?://magdyhelal\.modevops\.fun#i',
            '#https?://magdy\.modevops\.fun#i',
            '#https?://magdyhelalcorp\.infinityfree\.io#i',
        ],
        $origin,
        $text
    );
    $bare = preg_replace('#^https?://#i', '', $origin);
    foreach (['localhost:8088', '127.0.0.1:8088', 'www.helal.co', 'magdyhelal.modevops.fun', 'magdy.modevops.fun', 'magdyhelalcorp.infinityfree.io'] as $old) {
        if (strcasecmp($bare, $old) !== 0) {
            $text = str_ireplace($old, $bare, $text);
        }
    }
    return $text;
}

function mha_chat_expand_facts($text)
{
    $d = mha_defaults();
    $map = [
        '{{url:home}}'     => mha_chat_page_url('home'),
        '{{url:about}}'    => mha_chat_page_url('about'),
        '{{url:services}}' => mha_chat_page_url('services'),
        '{{url:team}}'     => mha_chat_page_url('team'),
        '{{url:clients}}'  => mha_chat_page_url('clients'),
        '{{url:projects}}' => mha_chat_page_url('projects'),
        '{{url:news}}'     => mha_chat_page_url('news'),
        '{{url:contact}}'  => mha_chat_page_url('contact'),
        '{{url:quote}}'    => mha_chat_page_url('contact'),
        '{{phone}}'        => mha_phones_display(' — '),
        '{{email}}'        => mha_public_email(),
        '{{address}}'      => mha_mod('mha_address', $d['address']),
        '{{hours}}'        => mha_mod('mha_hours', $d['hours']),
        '{{firm}}'         => $d['firm'],
    ];
    return str_replace(array_keys($map), array_values($map), (string) $text);
}

function mha_chat_scrub_reply($text)
{
    $text = mha_chat_expand_facts($text);
    $text = mha_chat_rewrite_dev_hosts($text);
    $old_hosts = 'localhost|127\.0\.0\.1';
    $text = preg_replace('#\[([^\]]+)\]\(\s*https?://(?:' . $old_hosts . ')(?::\d+)?[^)]*\)#i', '$1', $text);
    $text = preg_replace('#https?://(?:' . $old_hosts . ')(?::\d+)?#i', '', $text);
    $text = preg_replace('#\b(?:localhost|127\.0\.0\.1):\d+\b#i', '', $text);
    $text = preg_replace('#\b(?:localhost|127\.0\.0\.1)\b#i', '', $text);
    $text = preg_replace('/:8088\b/', '', $text);
    $text = str_ireplace(['magdy.hilal@co', 'momagdyy97@gmail.com'], mha_public_email(), $text);
    $text = str_replace(['+201000354045', '201000354045', '01000354045', '0100 035 045'], '', $text);
    $text = preg_replace('/:\s*(?=\n|$)/u', '', $text);
    $text = preg_replace('/[ \t]{2,}/u', ' ', $text);
    return trim((string) $text);
}

function mha_chat_sanitize_links(array $links)
{
    $origin = mha_chat_public_origin();
    $out    = [];
    foreach ($links as $item) {
        $title = trim((string) ($item['title'] ?? ''));
        $url   = trim((string) ($item['url'] ?? ''));
        if ($title === '' || $url === '') {
            continue;
        }
        if (stripos($url, $origin . '/') !== 0 && strcasecmp($url, $origin) !== 0) {
            $url = mha_chat_rewrite_dev_hosts($url);
        }
        $out[] = ['title' => $title, 'url' => $url];
    }
    return $out;
}

function mha_chat_is_contact_query($message)
{
    $n = mha_chat_norm($message);
    if ($n === '') {
        return false;
    }
    $needles = [
        'تواصل معنا', 'تواصل', 'اتصل', 'اتصال', 'هاتف', 'موبايل',
        'عنوان', 'وين المكتب', 'فين المكتب', 'موقعكم', 'ساعات', 'واتساب',
        'ايميل', 'بريد', 'موعد', 'طلب استشاره', 'اطلب استشاره',
        'contact', 'phone', 'email', 'address', 'whatsapp', 'hours',
        'how to contact', 'call us',
    ];
    foreach ($needles as $needle) {
        if ($needle !== '' && mb_strpos($n, mha_chat_norm($needle)) !== false) {
            return true;
        }
    }
    return false;
}

function mha_chat_norm($text)
{
    $text = (string) $text;
    if (function_exists('mb_strtolower')) {
        $text = mb_strtolower($text, 'UTF-8');
    } else {
        $text = strtolower($text);
    }
    $from = ['أ', 'إ', 'آ', 'ى', 'ة', 'ؤ', 'ئ', 'ً', 'ٌ', 'ٍ', 'َ', 'ُ', 'ِ', 'ْ', 'ّ'];
    $to   = ['ا', 'ا', 'ا', 'ي', 'ه', '', '', '', '', '', '', '', '', '', ''];
    return str_replace($from, $to, $text);
}

function mha_chat_len($text)
{
    return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
}

function mha_chat_cut($text, $max)
{
    $text = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags((string) $text)));
    if (mha_chat_len($text) <= $max) {
        return $text;
    }
    $cut = function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
    return rtrim($cut, " \t.,،؛:") . '…';
}

function mha_chat_tokens($text)
{
    $norm  = mha_chat_norm($text);
    $parts = preg_split('/[^\p{L}\p{N}]+/u', $norm, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $out   = [];
    foreach ($parts as $p) {
        if (mha_chat_len($p) >= 3) {
            $out[] = $p;
        }
    }
    return array_values(array_unique($out));
}

function mha_chat_detect_lang($text)
{
    return preg_match('/\p{Arabic}/u', (string) $text) ? 'ar' : 'en';
}

function mha_chat_agent_labels()
{
    return [
        'guide'      => 'مرشد الموقع',
        'tax'        => 'مستشار الضرائب',
        'audit'      => 'مستشار المراجعة',
        'accounting' => 'مستشار المحاسبة',
        'economy'    => 'السياق الاقتصادي',
    ];
}

function mha_chat_route_agent($message)
{
    $n = mha_chat_norm($message);
    $buckets = [
        'guide' => [
            'تواصل', 'اتصل', 'هاتف', 'موبايل', 'عنوان', 'وين', 'فين', 'موقعكم', 'ساعات', 'واتساب', 'ايميل', 'بريد',
            'صفحه', 'صفحات', 'خدماتكم', 'من انتم', 'عن المكتب', 'طلب استشاره', 'موعد',
            'contact', 'phone', 'email', 'address', 'whatsapp', 'hours', 'where', 'location',
            'reach', 'call', 'quote', 'appointment', 'office', 'about you', 'who are', 'how to contact',
        ],
        'tax' => [
            'ضريب', 'فاتور', 'ايصال', 'قيمه مضافه', 'vat', 'eta', 'منظومه', 'خصم المنبع', 'كسب العمل',
            'اقرار', 'مصلحه', 'دخل', 'withhold', 'einvoice', 'e-invoice', 'e-receipt', 'tax',
            'invoice', 'receipt',
        ],
        'audit' => [
            'مراجع', 'تدقيق', 'فحص حساب', 'رقابه داخليه', 'قوائم ماليه', 'auditor', 'audit', 'review',
            'internal control', 'financial statement',
        ],
        'accounting' => [
            'دفاتر', 'مسك', 'امساك', 'رواتب', 'مسير', 'اقفال', 'قيود', 'ميزان', 'محاسبه',
            'bookkeep', 'payroll', 'closing', 'ledger', 'journal', 'accounting',
        ],
        'economy' => [
            'تضخم', 'فائده', 'بنك مركزي', 'سياسه نقد', 'سعر صرف', 'الجنيه', 'اقتصاد',
            'inflation', 'interest', 'cbe', 'central bank', 'economy', 'fx', 'exchange',
        ],
    ];

    $scores = [];
    foreach ($buckets as $agent => $needles) {
        $scores[$agent] = 0;
        foreach ($needles as $needle) {
            if ($needle !== '' && mb_strpos($n, $needle) !== false) {
                $scores[$agent] += mha_chat_len($needle) >= 6 ? 4 : 2;
            }
        }
    }

    arsort($scores);
    $best = (string) array_key_first($scores);
    if ($scores[$best] <= 0) {
        return 'guide';
    }
    return $best;
}

function mha_chat_retrieve($message, $limit = 4)
{
    global $wpdb;
    $table = mha_chat_table('knowledge');
    $rows  = $wpdb->get_results("SELECT slug, title, body, tags, source FROM {$table}", ARRAY_A);
    if (!is_array($rows) || !$rows) {
        $rows = [];
        foreach (mha_chat_knowledge_corpus() as $item) {
            $rows[] = $item;
        }
    }

    $tokens = mha_chat_tokens($message);
    $norm   = mha_chat_norm($message);
    $scored = [];

    foreach ($rows as $row) {
        $title_n = mha_chat_norm((string) ($row['title'] ?? ''));
        $tags_n  = mha_chat_norm((string) ($row['tags'] ?? ''));
        $body_n  = mha_chat_norm((string) ($row['body'] ?? ''));
        $hay     = trim($title_n . ' ' . $tags_n . ' ' . $body_n);
        $score   = 0;
        if ($norm !== '' && mb_strpos($hay, $norm) !== false) {
            $score += 40;
        }
        foreach ($tokens as $tok) {
            if (mb_strpos($title_n, $tok) !== false) {
                $score += 16 + min(8, mha_chat_len($tok));
            } elseif (mb_strpos($tags_n, $tok) !== false) {
                $score += 12 + min(6, mha_chat_len($tok));
            } elseif (mb_strpos($body_n, $tok) !== false) {
                $score += 5 + min(6, mha_chat_len($tok));
            }
        }
        $slug = (string) ($row['slug'] ?? '');
        if ($slug === 'e-invoice-eta' && (mb_strpos($norm, 'فاتور') !== false || mb_strpos($norm, 'einvoice') !== false || mb_strpos($norm, 'e-invoice') !== false)) {
            $score += 24;
        }
        if ($slug === 'contact-hours' && (mb_strpos($norm, 'contact') !== false || mb_strpos($norm, 'تواصل') !== false || mb_strpos($norm, 'هاتف') !== false || mb_strpos($norm, 'phone') !== false)) {
            $score += 20;
        }
        if ($score <= 0) {
            continue;
        }
        $row['score'] = $score;
        $scored[] = $row;
    }

    usort($scored, static function ($a, $b) {
        return ($b['score'] <=> $a['score']);
    });

    if (!$scored) {
        foreach ($rows as $row) {
            if (($row['slug'] ?? '') === 'site-map') {
                $row['score'] = 1;
                return [$row];
            }
        }
    }

    return array_slice($scored, 0, $limit);
}

function mha_chat_links_for($agent, array $chunks, $message = '')
{
    if (mha_chat_is_contact_query($message)) {
        return [
            ['title' => 'تواصل معنا', 'url' => mha_chat_page_url('contact')],
        ];
    }

    $catalog = [
        ['title' => 'من نحن', 'url' => mha_chat_page_url('about'), 'keys' => ['about', 'من نحن', 'مكتب']],
        ['title' => 'خدماتنا', 'url' => mha_chat_page_url('services'), 'keys' => ['service', 'خدم', 'ضريب', 'مراجع', 'محاسب']],
        ['title' => 'فريق العمل', 'url' => mha_chat_page_url('team'), 'keys' => ['team', 'فريق']],
        ['title' => 'عملاؤنا', 'url' => mha_chat_page_url('clients'), 'keys' => ['client', 'عملاء']],
        ['title' => 'مشاريعنا', 'url' => mha_chat_page_url('projects'), 'keys' => ['project', 'مشاريع']],
        ['title' => 'الأخبار', 'url' => mha_chat_page_url('news'), 'keys' => ['news', 'أخبار', 'اقتصاد']],
        ['title' => 'تواصل معنا', 'url' => mha_chat_page_url('contact'), 'keys' => ['contact', 'تواصل', 'هاتف']],
        ['title' => 'اطلب استشارة', 'url' => mha_chat_page_url('contact'), 'keys' => ['quote', 'استشار']],
    ];

    $want = [
        'guide'      => ['تواصل معنا', 'خدماتنا', 'من نحن'],
        'tax'        => ['خدماتنا', 'تواصل معنا', 'الأخبار'],
        'audit'      => ['خدماتنا', 'مشاريعنا', 'تواصل معنا'],
        'accounting' => ['خدماتنا', 'تواصل معنا'],
        'economy'    => ['الأخبار', 'تواصل معنا'],
    ];

    $titles = $want[$agent] ?? $want['guide'];
    $out    = [];
    foreach ($catalog as $item) {
        if (in_array($item['title'], $titles, true)) {
            $out[] = ['title' => $item['title'], 'url' => $item['url']];
        }
    }

    $blob = mha_chat_norm(wp_json_encode($chunks, JSON_UNESCAPED_UNICODE) . ' ' . $message);
    foreach ($catalog as $item) {
        if (count($out) >= 4) {
            break;
        }
        foreach ($out as $have) {
            if ($have['url'] === $item['url']) {
                continue 2;
            }
        }
        foreach ($item['keys'] as $k) {
            if (mb_strpos($blob, mha_chat_norm($k)) !== false) {
                $out[] = ['title' => $item['title'], 'url' => $item['url']];
                break;
            }
        }
    }

    return mha_chat_sanitize_links($out);
}

function mha_chat_english_line($agent)
{
    switch ($agent) {
        case 'tax':
            return 'In short: we can review your ETA e-invoice/VAT file at the office — this chat is general information, not licensed advice.';
        case 'audit':
            return 'Audit and tax examination answer different questions; we can scope a review from the services page.';
        case 'accounting':
            return 'Bookkeeping quality starts with monthly closing — ask us via the contact page if you need the team on-site.';
        case 'economy':
            return 'This is economic literacy, not investment advice.';
        default:
            return sprintf(
                'You can reach HELAL CORP on %s or %s — Nasr City, Cairo.',
                mha_phones_display(' and '),
                mha_public_email()
            );
    }
}

function mha_chat_is_greeting($message)
{
    $n = trim(mha_chat_norm($message));
    if ($n === '') {
        return false;
    }
    if (mha_chat_len($n) > 40) {
        return false;
    }
    return (bool) preg_match('/^((ال)?سلام( عليكم)?|مرحبا( بك)?|اهلا( وسهلا)?|صباح الخير|مساء الخير|hi+|hello|hey|good (morning|evening|afternoon))\b/u', $n);
}

function mha_chat_compose($agent, $message, array $chunks, $lang)
{
    $d = mha_defaults();
    $parts = [];

    if (mha_chat_is_greeting($message)) {
        $parts[] = 'أهلاً بكم في مستشار مكتب مجدي هلال — HELAL CORP. يمكن السؤال عن الضرائب، المراجعة، الفاتورة الإلكترونية، أو خدمات المكتب.';
        $parts[] = sprintf(
            'للتواصل المباشر: %s — %s — %s. ساعات العمل: %s.',
            mha_phones_display(' و '),
            mha_public_email(),
            mha_mod('mha_address', $d['address']),
            mha_mod('mha_hours', $d['hours'])
        );
        if ($lang === 'en') {
            $parts[] = mha_chat_english_line('guide');
        }
        return implode("\n\n", $parts);
    }

    if (mha_chat_is_contact_query($message)) {
        $parts[] = 'يسعد مكتب مجدي هلال — HELAL CORP استقبال استفساركم.';
        $parts[] = sprintf(
            'الهاتف: %s — البريد: %s — العنوان: %s. ساعات العمل: %s. يمكنكم الكتابة من صفحة «تواصل معنا».',
            mha_phones_display(' و '),
            mha_public_email(),
            mha_mod('mha_address', $d['address']),
            mha_mod('mha_hours', $d['hours'])
        );
        $parts[] = 'المعلومات أعلاه عامة. ملف كل منشأة يُراجع داخل المكتب قبل أي إجراء.';
        if ($lang === 'en') {
            $parts[] = mha_chat_english_line('guide');
        }
        return implode("\n\n", $parts);
    }

    $intros = [
        'guide'      => 'يسعد مكتب مجدي هلال — HELAL CORP توجيهكم إلى الخدمة أو الصفحة المناسبة.',
        'tax'        => 'بخصوص الاستفسار الضريبي، نقدّم إطاراً عاماً من الأنظمة المصرية، دون أن يُعد ذلك رأياً ملزماً لملف معيّن.',
        'audit'      => 'من زاوية المراجعة والرقابة، هذا تمييز مهني عام بين أعمال المكتب والفحص الحكومي.',
        'accounting' => 'من زاوية المحاسبة وإمساك الدفاتر، هذا ترتيب عملي تعتمد عليه التقارير والضرائب لاحقاً.',
        'economy'    => 'من زاوية السياق الاقتصادي المصري (تثقيف عام، وليس توصية استثمار):',
    ];
    $parts[] = $intros[$agent] ?? $intros['guide'];

    $used = 0;
    foreach ($chunks as $chunk) {
        if ($used >= 2) {
            break;
        }
        $slug = (string) ($chunk['slug'] ?? '');
        if ($slug === 'site-map') {
            $body = 'صفحات: من نحن، خدماتنا، فريق العمل، عملاؤنا، مشاريعنا، الأخبار، وتواصل معنا.';
        } else {
            $body = mha_chat_cut(mha_chat_expand_facts($chunk['body'] ?? ''), 420);
        }
        $body = mha_chat_scrub_reply($body);
        if ($body === '') {
            continue;
        }
        $title = trim((string) ($chunk['title'] ?? ''));
        $parts[] = $title !== '' ? $title . ': ' . $body : $body;
        $used++;
    }

    if ($agent === 'guide') {
        $parts[] = sprintf(
            'العنوان: %s. الهاتف: %s. البريد: %s. ساعات العمل: %s.',
            mha_mod('mha_address', $d['address']),
            mha_phones_display(' و '),
            mha_public_email(),
            mha_mod('mha_hours', $d['hours'])
        );
    }

    $parts[] = 'المعلومات أعلاه عامة. ملف كل منشأة يُراجع داخل المكتب قبل أي إجراء.';

    if ($lang === 'en') {
        $parts[] = mha_chat_english_line($agent);
    }

    return implode("\n\n", $parts);
}

function mha_chat_openai_key()
{
    foreach (['MHA_OPENAI_API_KEY', 'OPENAI_API_KEY'] as $name) {
        if (defined($name) && constant($name)) {
            return (string) constant($name);
        }
        $env = getenv($name);
        if (is_string($env) && $env !== '') {
            return $env;
        }
        if (!empty($_ENV[$name])) {
            return (string) $_ENV[$name];
        }
    }
    return '';
}

function mha_chat_openai_reply($message, array $chunks, $agent, $lang)
{
    $key = mha_chat_openai_key();
    if ($key === '') {
        return null;
    }

    $context = '';
    foreach ($chunks as $chunk) {
        $context .= '# ' . ($chunk['title'] ?? '') . "\n" . mha_chat_expand_facts($chunk['body'] ?? '') . "\n\n";
    }

    $system = sprintf(
        'أنت مستشار مهني لمكتب مجدي هلال — HELAL CORP في مدينة نصر، القاهرة. أجب بالعربية الفصحى المهنية أساساً. إذا كان سؤال المستخدم بالإنجليزية أضف سطراً إنجليزياً قصيراً في النهاية. لا تختلق مواد قانونية. اعتمد فقط على السياق المعطى وبيانات المكتب. أوضح أن الكلام معلومات عامة وليست استشارة قانونية مرخّصة. لا تذكر فيسبوك ولا إبراهيم هلال. لا تدرج روابط URL في نص الرد ولا تذكر localhost أو 127.0.0.1 أو أرقام المنافذ. الموقع العام helal.co؛ الأزرار تُبنى من عنوان الموقع. اذكر أسماء الصفحات بالعربية فقط (من نحن، خدماتنا، تواصل معنا)؛ الأزرار تظهر منفصلة. بيانات التواصل: الهاتف %s — البريد %s — العنوان %s. لا تذكر واتساب ولا بريداً شخصياً على Gmail.',
        mha_phones_display(' و '),
        mha_public_email(),
        mha_mod('mha_address', mha_defaults()['address'])
    );

    $payload = [
        'model'       => 'gpt-4o-mini',
        'temperature' => 0.2,
        'max_tokens'  => 700,
        'messages'    => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => "الوكيل: {$agent}\nلغة المستخدم: {$lang}\n\nالسياق:\n{$context}\nسؤال العميل:\n{$message}"],
        ],
    ];

    $res = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'timeout' => 8,
        'headers' => [
            'Authorization' => 'Bearer ' . $key,
            'Content-Type'  => 'application/json',
        ],
        'body'    => wp_json_encode($payload),
    ]);

    if (is_wp_error($res) || (int) wp_remote_retrieve_response_code($res) >= 400) {
        return null;
    }

    $data = json_decode(wp_remote_retrieve_body($res), true);
    $text = $data['choices'][0]['message']['content'] ?? '';
    $text = is_string($text) ? trim($text) : '';
    return $text !== '' ? $text : null;
}

function mha_chat_ip_hash()
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $salt = defined('AUTH_SALT') ? AUTH_SALT : 'mha-chat';
    return hash_hmac('sha256', (string) $ip, $salt);
}

function mha_chat_rate_limited()
{
    $key = 'mha_chat_rl_' . mha_chat_ip_hash();
    $n   = (int) get_transient($key);
    if ($n >= MHA_CHAT_RATE) {
        return true;
    }
    set_transient($key, $n + 1, MINUTE_IN_SECONDS);
    return false;
}

function mha_chat_host_allowed($host)
{
    $host = strtolower((string) $host);
    $home = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
    $ok   = array_filter([$home, 'helal.co', 'www.helal.co']);
    return $host !== '' && in_array($host, $ok, true);
}

function mha_chat_same_site()
{
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? (string) $_SERVER['HTTP_ORIGIN'] : '';
    if ($origin !== '') {
        return mha_chat_host_allowed(wp_parse_url($origin, PHP_URL_HOST));
    }
    $ref = isset($_SERVER['HTTP_REFERER']) ? (string) $_SERVER['HTTP_REFERER'] : '';
    if ($ref === '') {
        return true;
    }
    return mha_chat_host_allowed(wp_parse_url($ref, PHP_URL_HOST));
}

function mha_chat_get_session($token, $lang)
{
    global $wpdb;
    $table = mha_chat_table('sessions');
    $now   = current_time('mysql');
    $token = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $token);

    if ($token !== '' && mha_chat_len($token) >= 16) {
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE session_token = %s", $token), ARRAY_A);
        if ($row) {
            $wpdb->update($table, ['updated_at' => $now, 'lang' => $lang], ['id' => (int) $row['id']]);
            $row['lang'] = $lang;
            $row['updated_at'] = $now;
            return $row;
        }
    }

    $token = wp_generate_password(32, false, false);
    $wpdb->insert($table, [
        'session_token' => $token,
        'created_at'    => $now,
        'updated_at'    => $now,
        'lang'          => $lang,
        'ip_hash'       => mha_chat_ip_hash(),
    ]);

    return [
        'id'            => (int) $wpdb->insert_id,
        'session_token' => $token,
        'lang'          => $lang,
        'created_at'    => $now,
        'updated_at'    => $now,
    ];
}

function mha_chat_add_message($session_id, $role, $agent, $content)
{
    global $wpdb;
    $wpdb->insert(mha_chat_table('messages'), [
        'session_id' => (int) $session_id,
        'role'       => $role,
        'agent'      => $agent,
        'content'    => $content,
        'created_at' => current_time('mysql'),
    ]);
}

/**
 * Core responder used by REST and wp-cli tests.
 *
 * @return array{session:string,agent:string,reply:string,links:array<int,array{title:string,url:string}>}
 */
function mha_chat_respond($session_token, $message)
{
    if (function_exists('mb_internal_encoding')) {
        mb_internal_encoding('UTF-8');
    }

    $message = trim((string) $message);
    if (function_exists('mb_substr')) {
        $message = mb_substr($message, 0, MHA_CHAT_MAX_LEN, 'UTF-8');
    } elseif (strlen($message) > MHA_CHAT_MAX_LEN) {
        $message = substr($message, 0, MHA_CHAT_MAX_LEN);
    }

    $lang    = mha_chat_detect_lang($message);
    $agent   = mha_chat_route_agent($message);
    $chunks  = mha_chat_retrieve($message);
    $links   = mha_chat_sanitize_links(mha_chat_links_for($agent, $chunks, $message));
    $use_ai  = !mha_chat_is_contact_query($message) && !mha_chat_is_greeting($message);
    $openai  = $use_ai ? mha_chat_openai_reply($message, $chunks, $agent, $lang) : null;
    $reply   = is_string($openai) && $openai !== '' ? $openai : mha_chat_compose($agent, $message, $chunks, $lang);
    $reply   = mha_chat_scrub_reply($reply);
    $session = mha_chat_get_session($session_token, $lang);

    mha_chat_add_message((int) $session['id'], 'user', '', $message);
    mha_chat_add_message((int) $session['id'], 'bot', $agent, $reply);

    return [
        'session' => (string) $session['session_token'],
        'agent'   => $agent,
        'agent_label' => mha_chat_agent_labels()[$agent] ?? $agent,
        'reply'   => $reply,
        'links'   => $links,
    ];
}

function mha_chat_register_rest()
{
    register_rest_route('mha/v1', '/chat', [
        'methods'             => 'POST',
        'callback'            => 'mha_rest_chat',
        'permission_callback' => static function (WP_REST_Request $request) {
            $nonce = $request->get_header('X-WP-Nonce');
            if (!is_string($nonce) || $nonce === '' || !wp_verify_nonce($nonce, 'wp_rest')) {
                return new WP_Error('mha_nonce', 'تعذر التحقق من الطلب. حدّث الصفحة ثم أعد المحاولة.', ['status' => 403]);
            }
            if (!mha_chat_same_site()) {
                return new WP_Error('mha_origin', 'الطلب غير مسموح من هذا المصدر.', ['status' => 403]);
            }
            return true;
        },
        'args'                => [
            'message' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => static function ($value) {
                    $value = sanitize_textarea_field((string) $value);
                    return function_exists('mb_substr') ? mb_substr($value, 0, MHA_CHAT_MAX_LEN, 'UTF-8') : substr($value, 0, MHA_CHAT_MAX_LEN);
                },
            ],
            'session' => [
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ],
    ]);
}
add_action('rest_api_init', 'mha_chat_register_rest');

function mha_rest_chat(WP_REST_Request $request)
{
    if (mha_chat_rate_limited()) {
        return new WP_Error('mha_rate', 'تجاوزتم حد الرسائل لهذه الدقيقة. انتظروا قليلاً ثم أعيدوا المحاولة.', ['status' => 429]);
    }

    $message = trim((string) $request->get_param('message'));
    if ($message === '') {
        $json = $request->get_json_params();
        $message = isset($json['message']) ? sanitize_textarea_field((string) $json['message']) : '';
    }
    if ($message === '') {
        return new WP_Error('mha_empty', 'اكتبوا رسالة قبل الإرسال.', ['status' => 400]);
    }

    $session = (string) $request->get_param('session');
    if ($session === '') {
        $json = $request->get_json_params();
        $session = isset($json['session']) ? sanitize_text_field((string) $json['session']) : '';
    }

    $data = mha_chat_respond($session, $message);
    return rest_ensure_response($data);
}

function mha_chat_admin_menu()
{
    add_menu_page(
        'محادثات الاستشارة',
        'محادثات الاستشارة',
        'edit_pages',
        'mha-chat-sessions',
        'mha_chat_admin_page',
        'dashicons-format-chat',
        26
    );
}
add_action('admin_menu', 'mha_chat_admin_menu');

function mha_chat_admin_page()
{
    if (!current_user_can('edit_pages')) {
        wp_die(esc_html__('لا صلاحية.', 'magdi-hilal-adco'));
    }

    global $wpdb;
    mha_chat_install(false);

    $sessions_t = mha_chat_table('sessions');
    $messages_t = mha_chat_table('messages');
    $sid        = isset($_GET['session']) ? absint($_GET['session']) : 0;

    echo '<div class="wrap"><h1>محادثات الاستشارة</h1>';
    echo '<p>الجلسات مخزّنة في MySQL (الجداول <code>chat_sessions</code> و<code>chat_messages</code> بالبادئة المعتمدة). الرد الآلي معلومات عامة وليس استشارة قانونية.</p>';

    if ($sid) {
        $session = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$sessions_t} WHERE id = %d", $sid), ARRAY_A);
        $msgs    = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$messages_t} WHERE session_id = %d ORDER BY id ASC",
            $sid
        ), ARRAY_A);
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=mha-chat-sessions')) . '">← كل الجلسات</a></p>';
        if (!$session) {
            echo '<p>الجلسة غير موجودة.</p></div>';
            return;
        }
        echo '<table class="widefat striped"><tbody>';
        echo '<tr><th>الرمز</th><td dir="ltr">' . esc_html($session['session_token']) . '</td></tr>';
        echo '<tr><th>اللغة</th><td>' . esc_html($session['lang']) . '</td></tr>';
        echo '<tr><th>أُنشئت</th><td>' . esc_html($session['created_at']) . '</td></tr>';
        echo '</tbody></table><h2>الرسائل</h2>';
        echo '<table class="widefat striped"><thead><tr><th>الوقت</th><th>الدور</th><th>الوكيل</th><th>النص</th></tr></thead><tbody>';
        foreach ($msgs as $m) {
            echo '<tr>';
            echo '<td>' . esc_html($m['created_at']) . '</td>';
            echo '<td>' . esc_html($m['role']) . '</td>';
            echo '<td>' . esc_html($m['agent']) . '</td>';
            echo '<td style="white-space:pre-wrap">' . esc_html($m['content']) . '</td>';
            echo '</tr>';
        }
        if (!$msgs) {
            echo '<tr><td colspan="4">لا رسائل.</td></tr>';
        }
        echo '</tbody></table></div>';
        return;
    }

    $rows = $wpdb->get_results(
        "SELECT s.*, (SELECT COUNT(*) FROM {$messages_t} m WHERE m.session_id = s.id) AS msg_count,
                (SELECT m2.content FROM {$messages_t} m2 WHERE m2.session_id = s.id AND m2.role = 'user' ORDER BY m2.id DESC LIMIT 1) AS last_user
         FROM {$sessions_t} s
         ORDER BY s.updated_at DESC
         LIMIT 80",
        ARRAY_A
    );

    echo '<table class="widefat striped"><thead><tr><th>المعرّف</th><th>آخر تحديث</th><th>اللغة</th><th>الرسائل</th><th>آخر سؤال</th><th></th></tr></thead><tbody>';
    if (!$rows) {
        echo '<tr><td colspan="6">لا جلسات بعد. تظهر هنا بعد استخدام المستشار في الموقع.</td></tr>';
    }
    foreach ($rows as $row) {
        $view = admin_url('admin.php?page=mha-chat-sessions&session=' . (int) $row['id']);
        echo '<tr>';
        echo '<td>' . (int) $row['id'] . '</td>';
        echo '<td>' . esc_html($row['updated_at']) . '</td>';
        echo '<td>' . esc_html($row['lang']) . '</td>';
        echo '<td>' . (int) $row['msg_count'] . '</td>';
        echo '<td>' . esc_html(mha_chat_cut((string) $row['last_user'], 120)) . '</td>';
        echo '<td><a href="' . esc_url($view) . '">عرض</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

function mha_chat_render_widget()
{
    if (is_admin()) {
        return;
    }
    $labels = mha_chat_agent_labels();
    $avatar = mha_img('logo-mark.png');
    ?>
    <div id="mhaChatRoot" class="mha-chat-root">
        <div class="mha-chat-panel" id="mhaChatPanel" role="dialog" aria-modal="true" aria-labelledby="mhaChatTitle" aria-hidden="true">
            <header class="mha-chat-head">
                <div class="mha-chat-head-identity">
                    <img class="mha-chat-head-avatar" src="<?php echo esc_url($avatar); ?>" alt="" width="36" height="36">
                    <div class="mha-chat-head-copy">
                        <h2 id="mhaChatTitle">مستشار HELAL CORP</h2>
                        <p class="mha-chat-agent" id="mhaChatAgent"><?php echo esc_html($labels['guide']); ?></p>
                    </div>
                </div>
                <div class="mha-chat-head-actions">
                    <button type="button" class="mha-chat-iconbtn" id="mhaChatRefresh" aria-label="جلسة جديدة">
                        <?php echo mha_icon('refresh'); ?>
                    </button>
                    <button type="button" class="mha-chat-iconbtn" id="mhaChatClose" aria-label="إغلاق المستشار">
                        <?php echo mha_icon('close'); ?>
                    </button>
                </div>
            </header>
            <div class="mha-chat-log" id="mhaChatLog" aria-live="polite"></div>
            <div class="mha-chat-chips" id="mhaChatChips">
                <button type="button" data-chip="الضرائب">الضرائب</button>
                <button type="button" data-chip="المراجعة">المراجعة</button>
                <button type="button" data-chip="الفاتورة الإلكترونية">الفاتورة الإلكترونية</button>
                <button type="button" data-chip="تواصل معنا">تواصل معنا</button>
            </div>
            <div class="mha-chat-composer">
                <form class="mha-chat-form" id="mhaChatForm" action="#" method="post">
                    <label class="sr-only" for="mhaChatInput">رسالتكم</label>
                    <input id="mhaChatInput" type="text" name="message" maxlength="<?php echo (int) MHA_CHAT_MAX_LEN; ?>" dir="rtl" autocomplete="off" placeholder="اكتب هنا...">
                    <button type="submit" class="mha-chat-send" id="mhaChatSend" aria-label="إرسال"><?php echo mha_icon('send'); ?></button>
                </form>
                <p class="mha-chat-note">معلومات عامة وليست استشارة قانونية. تُحفظ المحادثة في قاعدة بيانات المكتب.</p>
            </div>
        </div>
        <div class="mha-chat-toast" id="mhaChatToast" role="alert" hidden></div>
    </div>
    <?php
}
add_action('wp_footer', 'mha_chat_render_widget', 20);
