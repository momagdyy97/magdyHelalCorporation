<?php
/**
 * Egyptian accounting / economy news: curated posts, optional RSS, carousel helpers.
 *
 * @package Magdi_Hilal_Adco
 */

if (!defined('ABSPATH')) {
    exit;
}

if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

define('MHA_NEWS_VERSION', 2);
define('MHA_NEWS_THUMB_MAX', 2097152);

function mha_news_url()
{
    $id = (int) get_option('page_for_posts');
    return $id ? get_permalink($id) : mha_page_url('news');
}

function mha_news_category_id()
{
    $slug = 'akhbar';
    $term = get_term_by('slug', $slug, 'category');
    if ($term && !is_wp_error($term)) {
        return (int) $term->term_id;
    }

    $by_name = get_term_by('name', 'الأخبار', 'category');
    if ($by_name && !is_wp_error($by_name)) {
        return (int) $by_name->term_id;
    }

    $inserted = wp_insert_term('الأخبار', 'category', [
        'slug'        => $slug,
        'description' => 'أخبار مهنية في المحاسبة والضرائب والاقتصاد المصري.',
    ]);

    if (is_wp_error($inserted)) {
        $exists = term_exists('الأخبار', 'category');
        if (is_array($exists)) {
            return (int) $exists['term_id'];
        }
        return (int) get_option('default_category');
    }

    return (int) $inserted['term_id'];
}

function mha_maybe_seed_news()
{
    if (!is_admin() && php_sapi_name() !== 'cli') {
        return;
    }

    if ((int) get_option('mha_news_version') < MHA_NEWS_VERSION) {
        try {
            mha_seed_news(false);
        } catch (Throwable $e) {
            error_log('mha_seed_news: ' . $e->getMessage());
        }
    }
}
add_action('init', 'mha_maybe_seed_news', 40);

/**
 * Upsert curated news and optionally import public RSS headlines.
 *
 * @param bool $try_rss Attempt live RSS (setup / wp-cli). Failures fall back to curated posts.
 */
function mha_seed_news($try_rss = true)
{
    if (!function_exists('wp_insert_post')) {
        return false;
    }

    mha_news_require_media();

    $cat_id = mha_news_category_id();
    mha_replace_stale_news();

    foreach (mha_curated_news() as $item) {
        mha_upsert_news_post($item, $cat_id);
    }

    if ($try_rss) {
        try {
            mha_import_rss_news($cat_id);
        } catch (Throwable $e) {
            error_log('mha_import_rss_news: ' . $e->getMessage());
        }
    }

    try {
        mha_news_backfill_thumbs();
    } catch (Throwable $e) {
        error_log('mha_news_backfill_thumbs: ' . $e->getMessage());
    }
    update_option('mha_news_version', MHA_NEWS_VERSION);
    update_option('mha_news_thumbs_version', 2);
    return true;
}

function mha_stale_news_titles()
{
    return [
        'ما الذي تغيّر في الفاتورة الإلكترونية للشركات الصغيرة؟',
        'قبل موسم الإقرارات: قائمة مراجعة لمحاسب الشركة',
        'المراجعة ليست ورقة في نهاية السنة',
    ];
}

function mha_replace_stale_news()
{
    $titles = mha_stale_news_titles();
    $keys   = array_column(mha_curated_news(), 'key');

    foreach ($titles as $i => $title) {
        $found = get_posts([
            'post_type'              => 'post',
            'post_status'            => 'any',
            'title'                  => $title,
            'posts_per_page'         => 1,
            'suppress_filters'       => true,
        ]);

        if (!$found) {
            continue;
        }

        $post = $found[0];
        $key  = $keys[$i] ?? ('legacy-' . $i);
        update_post_meta($post->ID, '_mha_news_key', $key);
    }
}

function mha_find_news_by_key($key)
{
    $found = get_posts([
        'post_type'      => 'post',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'meta_key'       => '_mha_news_key',
        'meta_value'     => $key,
        'fields'         => 'ids',
    ]);

    return $found ? (int) $found[0] : 0;
}

function mha_upsert_news_post(array $item, $cat_id)
{
    $existing = mha_find_news_by_key($item['key']);
    $body     = mha_news_format_body($item);

    $payload = [
        'post_title'   => $item['title'],
        'post_content' => $body,
        'post_excerpt' => $item['excerpt'],
        'post_status'  => 'publish',
        'post_type'    => 'post',
        'post_name'    => $item['slug'],
        'post_date'    => $item['date'],
        'post_category'=> [$cat_id],
    ];

    if ($existing) {
        $payload['ID'] = $existing;
        $id = wp_update_post($payload, true);
    } else {
        $id = wp_insert_post($payload, true);
    }

    if (!$id || is_wp_error($id)) {
        return 0;
    }

    update_post_meta($id, '_mha_news_key', $item['key']);
    update_post_meta($id, '_mha_news_source', $item['source']);
    update_post_meta($id, '_mha_news_image', $item['image']);
    if (!empty($item['origin'])) {
        update_post_meta($id, '_mha_news_origin', esc_url_raw($item['origin']));
    }
    if (!empty($item['outlet'])) {
        update_post_meta($id, '_mha_news_outlet', $item['outlet']);
    }
    wp_set_post_categories($id, [$cat_id], false);
    mha_news_sync_thumb((int) $id, $item['image'] ?? '', $item['origin'] ?? '');

    return (int) $id;
}

function mha_news_format_body(array $item)
{
    $source = esc_html($item['source']);
    $when   = esc_html($item['source_date']);
    $note   = 'هذا النص ملخص مهني أصلي أعدّه مكتب مجدي هلال — M.H CORP استناداً إلى أطر عامة معلنة. ليس نسخاً حرفياً من جهة رسمية، ولا يغني عن مراجعة الملف داخل المكتب.';

    return wpautop($item['content']) . "\n<p class=\"mha-news-source\"><strong>المصدر:</strong> {$source} — {$when}.</p>\n<p class=\"mha-news-disclaimer\">{$note}</p>";
}

function mha_curated_news()
{
    return [
        [
            'key'          => 'eta-einvoice-2026',
            'slug'         => 'manzumat-al-fatura-al-elektroniya',
            'date'         => '2026-08-12 09:00:00',
            'image'        => 'topic-einvoice',
            'outlet'       => 'مصلحة الضرائب المصرية',
            'origin'       => 'https://www.eta.gov.eg/ar',
            'source'       => 'مصلحة الضرائب المصرية — منظومة الفاتورة الإلكترونية (ملخص مكتبي)',
            'source_date'  => 'أغسطس 2026',
            'title'        => 'منظومة الفاتورة الإلكترونية: ما الذي تتابعه الشركات مع مصلحة الضرائب؟',
            'excerpt'      => 'التسجيل على المنظومة، إصدار الفاتورة المعتمدة، وربط الدورة المستندية حتى لا يتوقف البيع أو يتأخر الإقرار.',
            'content'      => <<<'TXT'
منظومة الفاتورة الإلكترونية التي تشغّلها مصلحة الضرائب المصرية أصبحت جزءاً من الالتزام اليومي للشركات الخاضعة للمراحل الإلزامية، لا مجرد «مشروع تقني» على الهامش. الفكرة العملية: كل فاتورة بيع بين المسجّلين تمر عبر منظومة المصلحة قبل أن تُعتدّ بها في الخصم والإقرار.

ما يهم المحاسب والإدارة معاً أربعة أمور. أولاً التسجيل والحصول على التوقيع الإلكتروني والملف الرقمي للمنشأة. ثانياً اختيار طريقة الإصدار — تكامل برمجي مع نظام الحسابات أو منصة المصلحة — بما يناسب حجم الفواتير. ثالثاً مطابقة بيانات المشتري (التسجيل الضريبي والعنوان) حتى لا تُرفض الوثيقة. رابعاً أرشفة الرقم الداخلي للفاتورة وربطها بالقيد المحاسبي، لأن الفحص اللاحق يقارن المنظومة بالدفاتر لا بالورق وحده.

المكتب يذكّر عملاءه أن التأخير في الإصدار أو الاعتماد لا يُعالَج بتسوية شكلية في آخر الشهر. إذا توقّف النظام أو رُفضت فاتورة، يُوثَّق السبب ويُعاد الإرسال وفق إجراءات المصلحة، مع مراجعة أثر ذلك على ضريبة القيمة المضافة وضريبة الدخل. الفاتورة الإلكترونية ليست بديلاً عن صحة الوعاء؛ هي قناة إثبات أمام المصلحة.
TXT,
        ],
        [
            'key'          => 'eta-ereceipt-2026',
            'slug'         => 'al-isal-al-elektroni',
            'date'         => '2026-07-28 09:00:00',
            'image'        => 'topic-ereceipt',
            'outlet'       => 'مصلحة الضرائب المصرية',
            'origin'       => 'https://www.eta.gov.eg/ar',
            'source'       => 'مصلحة الضرائب المصرية — منظومة الإيصال الإلكتروني (ملخص مكتبي)',
            'source_date'  => 'يوليو 2026',
            'title'        => 'الإيصال الإلكتروني: من فاتورة الأعمال إلى التعامل مع المستهلك النهائي',
            'excerpt'      => 'الإيصال يغطي البيع للمستهلك غير المسجّل. الشركات مطالبة بتهيئة نقاط البيع والربط مع منظومة المصلحة وفق مراحل الإلزام.',
            'content'      => <<<'TXT'
بعد استقرار الفاتورة الإلكترونية في التعامل بين المسجّلين (B2B)، جاءت منظومة الإيصال الإلكتروني لتغطي البيع للمستهلك النهائي غير المسجّل ضريبياً. الفرق المحاسبي واضح: الفاتورة تربط مشترياً له رقم تسجيل، أما الإيصال فيوثّق عملية بيع تجزئة أو خدمة للجمهور، مع بيانات السلعة أو الخدمة والقيمة والضريبة متى وُجدت.

ما تتابعه الإدارة المالية عملياً: جاهزية نقاط البيع أو برامج الكاشير، تمرير الإيصال إلى المنظومة في الزمن الذي تحدده المصلحة، وعدم الاكتفاء بنسخة ورقية داخل الدرج. الإيصال المرفوض أو غير المُرسل يخلق فجوة بين يومية الصندوق وإقرار القيمة المضافة.

للشركات التي تبيع للمسجّلين وللجمهور معاً، يلزم فصل المسارين داخل النظام المحاسبي: فاتورة إلكترونية حيث يجب، وإيصال حيث يجب. الخلط بينهما يُظهر لاحقاً في تقارير المصلحة كاختلاف يصعب شرحه عند الفحص. المكتب يساعد على رسم الدورة المستندية قبل ربط الأجهزة، لا بعده.
TXT,
        ],
        [
            'key'          => 'vat-67-2016-2026',
            'slug'         => 'daribat-al-qima-al-mudafa',
            'date'         => '2026-07-10 09:00:00',
            'image'        => 'topic-vat',
            'outlet'       => 'مصلحة الضرائب المصرية',
            'origin'       => 'https://www.eta.gov.eg/ar',
            'source'       => 'قانون الضريبة على القيمة المضافة رقم 67 لسنة 2016 (ملخص عام)',
            'source_date'  => 'يوليو 2026',
            'title'        => 'ضريبة القيمة المضافة: التسجيل والإقرار والخصم في إطار القانون 67 لسنة 2016',
            'excerpt'      => 'الإقرار الدوري، خصم الضريبة على المدخلات، والتعامل مع الفواتير المعتمدة — ثلاثة محاور تُراجع قبل أي موسم إقرارات.',
            'content'      => <<<'TXT'
قانون الضريبة على القيمة المضافة رقم 67 لسنة 2016 هو الإطار الحاكم للضريبة غير المباشرة على بيع السلع وأداء الخدمات في مصر، مع ما لحقه من تعديلات وقرارات تنفيذية. الالتزام الأساسي للمنشأة المسجّلة: إصدار مستند معتمد، تجميع الضريبة على المخرجات، وخصم الضريبة على المدخلات وفق الشروط، ثم تقديم إقرار دوري وسداد الفرق.

أخطاء تتكرر في الملفات التي يراجعها المكتب: خصم على فواتير غير مكتملة البيانات أو غير صادرة على المنظومة حيث يُشترط ذلك؛ خلط بين المبيعات المعفاة والخاضعة دون تتبع في الدليل المحاسبي؛ وتأخير تسوية المرتجعات. الخصم حق منظّم لا تلقائي: المستند، والتخصيص للنشاط الخاضع، والتوقيت، كلها تُفحص.

المعدل العام والقوائم التفصيلية للإعفاءات تتغير بقرارات وقوانين لاحقة؛ لذلك لا يُستبدل نص القانون بقائمة حفظية قديمة. قبل إغلاق الفترة يُراجع كشف الموردين والمبيعات مع منظومة الفاتورة أو الإيصال، ثم يُعدّ الإقرار. أي تقدير جزافي داخل الشركة لا يحمي الملف أمام المصلحة.
TXT,
        ],
        [
            'key'          => 'income-tax-91-2005-2026',
            'slug'         => 'qanun-daribat-al-dakhl-91-2005',
            'date'         => '2026-06-22 09:00:00',
            'image'        => 'topic-income',
            'outlet'       => 'مصلحة الضرائب المصرية',
            'origin'       => 'https://www.eta.gov.eg/ar',
            'source'       => 'قانون الضريبة على الدخل رقم 91 لسنة 2005 (ملخص عام)',
            'source_date'  => 'يونيو 2026',
            'title'        => 'قانون الضريبة على الدخل 91 لسنة 2005: الإقرار السنوي وضبط الوعاء',
            'excerpt'      => 'الوعاء من القوائم المعدّلة ضريبياً لا من صافي الربح المحاسبي وحده. الاستعداد يبدأ من القيود الشهرية لا من أبريل.',
            'content'      => <<<'TXT'
قانون الضريبة على الدخل رقم 91 لسنة 2005، وتعديلاته، ينظّم ضريبة الأشخاص الطبيعيين والاعتباريين في مصر: متى ينشأ الالتزام، وكيف يُحدَّد الوعاء، وما المستندات والإقرارات المرتبطة به. للشركات، نقطة البداية ليست «نموذج الإقرار» بل جودة الدفاتر على مدار السنة: إيراد مكتمل، تكلفة قابلة للتحقق، ومصروف يُميَّز بين ما يُعتمد ضريبياً وما يُضاف للوعاء.

المحاسب الذي ينتظر نهاية السنة ليكتشف فروقاً كبيرة بين الربح المحاسبي والربح الضريبي يضع الإدارة تحت ضغط بلا وقت للمراجعة. الجدول الزمني المهني: تسويات شهرية، جرد وحسابات وسيطة، ثم مذكرة فروق ضريبية قبل إعداد الإقرار. الفحص اللاحق يسأل عن المستند لا عن النية.

التعديلات على الشرائح والمعاملة الخاصة ببعض الأنشطة تصدر بقوانين وقرارات مستقلة؛ الملخص أدناه لا يغني عن قراءة النص الساري على سنة الفحص. مكتب مجدي هلال يراجع الملف مع العميل على هذا الأساس: الالتزام بالنص، وتوثيق التقدير حيث يسمح القانون، وتجنّب الحلول التي لا تصمد أمام المأمور.
TXT,
        ],
        [
            'key'          => 'social-insurance-148-2019',
            'slug'         => 'al-taminat-al-ijtimaeiya-148-2019',
            'date'         => '2026-06-05 09:00:00',
            'image'        => 'topic-insurance',
            'outlet'       => 'الهيئة القومية للتأمينات',
            'origin'       => 'https://www.nosi.gov.eg/',
            'source'       => 'قانون التأمينات الاجتماعية والمعاشات رقم 148 لسنة 2019 (ملخص عام)',
            'source_date'  => 'يونيو 2026',
            'title'        => 'التأمينات الاجتماعية وفق القانون 148 لسنة 2019: ما يهم صاحب العمل والمحاسب',
            'excerpt'      => 'أجر الاشتراك، المواعيد، والتكامل مع مسير الرواتب — ثلاثة بنود تظهر سريعاً عند التفتيش أو عند إنهاء خدمة عامل.',
            'content'      => <<<'TXT'
قانون التأمينات الاجتماعية والمعاشات رقم 148 لسنة 2019 أعاد تنظيم علاقة المنشأة بالعاملين من زاوية الاشتراك والحماية. بالنسبة للمحاسب، التأمينات ليست «قيداً شهرياً» فقط: أجر الاشتراك قد لا يطابق كل بنود مسير الرواتب، والتأخير في السداد أو في تسجيل ملتحق جديد يرتّب أعباءً تظهر لاحقاً عند التفتيش أو عند تسوية نهاية الخدمة.

ما يُراجع داخل المكتب مع مسير الأجور: أسماء المسجّلين مقابل العقود الفعلية، الحد الأدنى والأقصى لأجر الاشتراك وفق ما يُعلن دورياً، واستبعاد أو ضم البدلات حسب التكييف النظامي لا حسب رغبة الإدارة. أي اتفاق شفهي مع العامل لا يُصلح الملف أمام الهيئة.

الربط مع ضريبة كسب العمل وخصم المنبع مهم: اختلاف الأساس بين التأمينات والضريبة شائع، ويُوثَّق لا يُخفى. المنشأة التي تخلط بين المقاول والعامل التابع تخلق التزاماً مزدوجاً. الاستشارة هنا عملية: تصنيف العلاقة أولاً، ثم القيد والدفع.
TXT,
        ],
        [
            'key'          => 'cbe-inflation-rates-2026',
            'slug'         => 'al-bank-al-markazi-al-tadakhum-al-faida',
            'date'         => '2026-05-18 09:00:00',
            'image'        => 'topic-fx',
            'outlet'       => 'البنك المركزي المصري',
            'origin'       => 'https://www.cbe.org.eg/ar',
            'source'       => 'البنك المركزي المصري — سياق السياسة النقدية (ملخص للتثقيف الاقتصادي)',
            'source_date'  => 'مايو 2026',
            'title'        => 'البنك المركزي والتضخم وأسعار العائد: ماذا يعني ذلك لمحاسب الشركة؟',
            'excerpt'      => 'قرارات العائد وسعر الصرف تغيّر تكلفة التمويل وتقييم المخزون والالتزامات الأجنبية — دون أن يكون ذلك توصية استثمار.',
            'content'      => <<<'TXT'
البنك المركزي المصري يدير السياسة النقدية مستهدفاً استقرار الأسعار في المدى المتوسط. التضخم وأسعار العائد ليسا «خبراً للبورصة فقط»: هما مدخلان لتقدير تكلفة الاقتراض، واضمحلال القوة الشرائية للتدفقات، وإعادة قياس الالتزامات والأصول المرتبطة بالعملة الأجنبية.

المحاسب غير مطالب بالتنبؤ بقرار لجنة السياسة النقدية. هو مطالب بأن تُظهر القوائم أثر الواقع: فوائد تمويلية، فروق عملة، وتقدير المخزون وفق التكلفة أو صافي القيمة القابلة للتحقق أيّهما أقل عندما يتغيّر السوق. الإدارة التي تتجاهل تكلفة التمويل عند تسعير العقود تكتشف الهامش بعد فوات الأوان.

هذا المقال للتثقيف الاقتصادي العام، وليس توصية بشراء أو بيع أداة مالية، ولا بديلاً عن مستشار استثمار مرخّص. دور المكتب: مساعدة الشركة على قراءة أثر المتغيرات الكلية على الدورة المحاسبية والسيولة، وعلى الإفصاح الواضح للدائنين والشركاء.
TXT,
        ],
        [
            'key'          => 'fra-governance-2026',
            'slug'         => 'al-hayaa-al-amma-lil-raqaba-al-maliya',
            'date'         => '2026-04-30 09:00:00',
            'image'        => 'topic-fra',
            'outlet'       => 'الهيئة العامة للرقابة المالية',
            'origin'       => 'https://fra.gov.eg/',
            'source'       => 'الهيئة العامة للرقابة المالية — إطار الإفصاح والحوكمة (ملخص عام)',
            'source_date'  => 'أبريل 2026',
            'title'        => 'الهيئة العامة للرقابة المالية: الإفصاح والحوكمة خارج نطاق مصلحة الضرائب',
            'excerpt'      => 'الشركات الخاضعة للهيئة تلتزم بإفصاح وقوائم مختلفة عن الالتزام الضريبي. الخلط بين المسارين يربك الإدارة والمراجع.',
            'content'      => <<<'TXT'
الهيئة العامة للرقابة المالية تشرف على أسواق وأدوات غير مصرفية وعلى جهات إفصاح محددة. هذا المسار مستقل عن علاقة الشركة بمصلحة الضرائب، وإن اشتركا أحياناً في القوائم المالية ذاتها. من يخضع لقواعد الهيئة يلتزم بمواعيد إفصاح، وحوكمة، ومراجعين، ومعايير عرض قد تكون أشد تفصيلاً من حاجة الإقرار الضريبي وحده.

الخطأ الشائع: إعداد «قائمة واحدة» تُرسل لكل جهة دون مذكرة سياسات. المراجع الخارجي، والمأمور الضريبي، والهيئة، يقرأون نفس الأرقام بأسئلة مختلفة. الحوكمة الجيدة تعني دفاتر واحدة صادقة، ثم تقارير مشتقة موثّقة، لا ثلاثة إصدارات متناقضة.

حتى الشركات غير المدرجة تستفيد من انضباط الإفصاح الداخلي: محاضر، صلاحيات توقيع، وتتبع الأطراف ذات العلاقة. ذلك يقلّل مخاطر المراجعة والفحص معاً. المكتب يفصل في النقاش بين الالتزام الضريبي والالتزام الرقابي حتى لا يُعالَج الملف بأداة واحدة.
TXT,
        ],
        [
            'key'          => 'withholding-payroll-2026',
            'slug'         => 'khasm-al-manba-wa-kasb-al-amal',
            'date'         => '2026-04-12 09:00:00',
            'image'        => 'topic-payroll',
            'outlet'       => 'مصلحة الضرائب المصرية',
            'origin'       => 'https://www.eta.gov.eg/ar',
            'source'       => 'مصلحة الضرائب المصرية — خصم المنبع وضريبة كسب العمل (ملخص مكتبي)',
            'source_date'  => 'أبريل 2026',
            'title'        => 'خصم المنبع وكسب العمل: الالتزامات الشهرية التي لا تنتظر الإقرار السنوي',
            'excerpt'      => 'الموردون والأجور لهما جداول ومواعيد مستقلة. التأخير هنا يكلّف غرامة قبل أن يصل الملف إلى الفحص السنوي.',
            'content'      => <<<'TXT'
خصم الضريبة من المنبع وضريبة كسب العمل التزامان دوريان، لا يُرحَّلان إلى «موسم الإقرار». عند سداد أتعاب أو توريدات معيّنة يُخصم نسبة نظامية ويُورَّد للمصلحة وفق النماذج والمواعيد السارية. عند صرف الأجور تُحسب الضريبة على ما خضع من عناصر الأجر بعد الإعفاءات، وتُسدَّد مع النموذج المخصص.

ما يفسد الملف عملياً: الدفع النقدي دون مستند كافٍ، أو تسجيل المورد كـ«مصروف نثري» لتفادي الخصم، أو تجاهل المزايا العينية في كسب العمل. المصلحة تقارن كشوف البنوك والموردين بمسير الرواتب. الاتساق أهم من الحيلة.

دورة مهنية قصيرة: قائمة موردين مع حالة الخصم، مسير أجور مغلق شهرياً، ومطابقة السداد مع إشعارات المصلحة. مكتب مجدي هلال يضبط هذه الدورة مع النظام المحاسبي حتى لا تتراكم الفروق صامتة حتى الفحص.
TXT,
        ],
        [
            'key'          => 'tax-examination-2026',
            'slug'         => 'al-fahs-al-daribi-wal-mustanadat',
            'date'         => '2026-03-20 09:00:00',
            'image'        => 'topic-exam',
            'outlet'       => 'M.H CORP',
            'origin'       => '',
            'source'       => 'ممارسة مهنية — الفحص الضريبي والمستندات (ملخص مكتبي)',
            'source_date'  => 'مارس 2026',
            'title'        => 'الفحص الضريبي: الاستعداد بالمستندات يختلف عن المراجعة المالية',
            'excerpt'      => 'المأمور يسأل عن الوعاء والمستند النظامي. المراجع يسأل عن العرض العادل. الخلط بين المهمتين يُضعف الرد على الطلبات.',
            'content'      => <<<'TXT'
الفحص الضريبي الذي تجريه مصلحة الضرائب المصرية يهدف إلى التحقق من صحة الإقرار والوعاء والمستندات المؤيدة، وفق صلاحيات القانون. المراجعة المالية للقوائم — داخلية أو خارجية — تهدف إلى تقييم ما إذا كانت القوائم تعرض بعدالة المركز والأداء وفق إطار التقرير المالي المعمول به. النتيجتان قد تلتقيان في الدفاتر، لكن السؤال مختلف.

الاستعداد للفحص: ملف ضريبي مرتّب (إقرارات، نماذج خصم، مراسلات المنظومة)، ربط كل رقم جوهري بمستند، وتفسير الفروق بين الربح المحاسبي والضريبي بمذكرة مكتوبة قبل جلسة الفحص لا أثناءها. الارتجال يُفهم كضعف في الرقابة لا كمرونة.

المكتب يفصل الأدوار أمام العميل: ماذا نجهّز للفحص، وماذا يبقى في ملف المراجعة. طلبات المأمور تُجاب بما يثبت الالتزام، دون إغراق لا يخدم السؤال. هذه منهجية عمل، وليست وعداً بنتيجة معيّنة لكل ملف.
TXT,
        ],
        [
            'key'          => 'bookkeeping-statements-2026',
            'slug'         => 'imsak-al-dafatir-wal-qawaem-al-maliya',
            'date'         => '2026-02-15 09:00:00',
            'image'        => 'topic-books',
            'outlet'       => 'M.H CORP',
            'origin'       => '',
            'source'       => 'ممارسة مهنية — إمساك الدفاتر والقوائم المالية (ملخص مكتبي)',
            'source_date'  => 'فبراير 2026',
            'title'        => 'إمساك الدفاتر والقوائم المالية: الإقفال الشهري قبل أي تقرير للبنك أو الشريك',
            'excerpt'      => 'قائمة لا تُغلق حساباتها الوسيطة لا تُقرأ. البنوك والشركاء يلاحظون الفجوات أسرع من الإدارة أحياناً.',
            'content'      => <<<'TXT'
القوائم المالية الجيدة تبدأ من قيود يومية منضبطة، لا من نموذج في نهاية السنة. إمساك الدفاتر يعني: مستند لكل قيد، دليل حسابات ثابت، مطابقة البنوك والنقدية، جرد أو متابعة مخزون وفق طبيعة النشاط، وإقفال شهري للحسابات الوسيطة (عهد، موردون، عملاء).

ما يطلبه البنك أو الشريك في الواقع: أرقام حديثة يمكن تتبعها. ميزان مراجعة غير متوازن أو حساب جارٍ للشركاء بلا حركة مفسَّرة يضعف الثقة قبل أن يُفتح نقاش الربحية. معايير العرض — ومنها الإطار المصري المستند إلى المعايير الدولية وفق ما هو سارٍ على المنشأة — تتطلب إفصاحاً لا يكفي معه «صافي الربح» في سطر واحد.

المكتب يبني الدورة مع العميل: من المستند إلى التقرير، مع فصل واضح بين تقرير الإدارة الداخلي والقوائم التي تُقدَّم لجهة خارجية. الوقت الذي يُوفَّر في الإقفال الشهري يظهر عند الضرائب والمراجعة والتمويل معاً.
TXT,
        ],
    ];
}

function mha_rss_news_feeds()
{
    return [
        [
            'name'   => 'اليوم السابع',
            'outlet' => 'اليوم السابع',
            'url'    => 'https://www.youm7.com/rss/SectionRss?SectionID=297',
        ],
        [
            'name'   => 'اليوم السابع',
            'outlet' => 'اليوم السابع',
            'url'    => 'https://www.youm7.com/rss/SectionRss?SectionID=97',
        ],
    ];
}

function mha_rss_topic_regex()
{
    return '/ضريب|محاس|فاتور|إيصال|تضخم|فائدة|بنك|تأمين|قيمة مضافة|اقتصاد|بورص|مالي|الجنيه|دولار|استثمار|شركات|موازنة|جمارك|رقابة مالية|مركزي/u';
}

function mha_news_is_offtopic($title, $hay)
{
    if (preg_match('/سياحة|آثار|طقس|رياضة|مباراة|تليفزيون|دراما|فنون|أهداف|الأهلي|الزمالك/u', $title)
        && !preg_match('/ضريب|جنيه|دولار|بورص|بنك|تضخم|فاتور|اقتصاد/u', $hay)) {
        return true;
    }
    return false;
}

function mha_import_rss_news($cat_id)
{
    $imported = 0;
    $limit    = 5;
    $used_thumbs = [];

    $feeds = mha_rss_news_feeds();
    $queue = [];

    foreach ($feeds as $feed) {
        $items = mha_fetch_rss_items($feed['url']);
        foreach ($items as $item) {
            $item['feed_name']   = $feed['name'];
            $item['feed_outlet'] = $feed['outlet'] ?? $feed['name'];
            $queue[] = $item;
        }
    }

    if (!$queue) {
        foreach (mha_youm7_homepage_items() as $item) {
            $queue[] = $item;
        }
    }

    foreach ($queue as $item) {
        if ($imported >= $limit) {
            break;
        }

        $title = isset($item['title']) ? wp_strip_all_tags($item['title']) : '';
        $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($title === '' || mb_strlen($title) < 12) {
            continue;
        }

        $hay = $title . ' ' . ($item['excerpt'] ?? '');
        if (!preg_match(mha_rss_topic_regex(), $hay)) {
            continue;
        }
        if (mha_news_is_offtopic($title, $hay)) {
            continue;
        }

        $link = mha_news_iri($item['link'] ?? '');
        if ($link === '') {
            continue;
        }

        $key = 'rss:' . md5($link);
        if (mha_find_news_by_key($key) || mha_news_title_exists($title)) {
            continue;
        }

        $excerpt = isset($item['excerpt']) ? wp_strip_all_tags($item['excerpt']) : '';
        $excerpt = html_entity_decode($excerpt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $excerpt = mb_substr(trim(preg_replace('/\s+/u', ' ', $excerpt)), 0, 240);
        if ($excerpt === '') {
            $excerpt = 'عنوان من المصدر الصحفي مع رابط الأصل. التفاصيل الكاملة لدى جهة النشر.';
        }

        $date = $item['date'] ?? current_time('mysql');
        if ($date && strtotime($date)) {
            $date = wp_date('Y-m-d H:i:s', strtotime($date));
        } else {
            $date = current_time('mysql');
        }

        $source_name = $item['feed_name'] ?? 'اليوم السابع';
        $outlet      = $item['feed_outlet'] ?? 'اليوم السابع';
        $content     = '<p>' . esc_html($excerpt) . '</p>';
        $content    .= '<p>هذا الخبر مأخوذ كعنوان ومقتطف عام من تغذية RSS عامة، وليس نسخاً لنص المقال الكامل. اقرأ التغطية الأصلية من المصدر.</p>';
        $content    .= '<p class="mha-news-source"><strong>المصدر:</strong> <a href="' . esc_url($link) . '" rel="nofollow noopener" target="_blank">' . esc_html($source_name) . '</a></p>';

        $id = wp_insert_post([
            'post_title'    => $title,
            'post_content'  => $content,
            'post_excerpt'  => $excerpt,
            'post_status'   => 'publish',
            'post_type'     => 'post',
            'post_date'     => $date,
            'post_category' => [$cat_id],
        ], true);

        if (!$id || is_wp_error($id)) {
            continue;
        }

        $rss_img = mha_news_sanitize_image_url($item['image'] ?? '');
        update_post_meta($id, '_mha_news_key', $key);
        update_post_meta($id, '_mha_news_source', $source_name);
        update_post_meta($id, '_mha_news_outlet', $outlet);
        update_post_meta($id, '_mha_news_origin', $link);
        if ($rss_img) {
            update_post_meta($id, '_mha_news_remote_thumb', $rss_img);
        }
        wp_set_post_categories($id, [$cat_id], false);

        $thumb = mha_news_sync_thumb((int) $id, '', $link, $rss_img, $used_thumbs);
        if ($thumb) {
            $used_thumbs[] = $thumb;
        }
        $imported++;
    }

    return $imported;
}

function mha_news_title_exists($title)
{
    $found = get_posts([
        'post_type'      => 'post',
        'post_status'    => 'any',
        'title'          => $title,
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ]);

    return !empty($found);
}

function mha_fetch_rss_items($url)
{
    $body = mha_news_http_get($url, 400000, [
        'Accept' => 'application/rss+xml, application/xml, text/xml, */*',
    ], 0);

    if ((!is_string($body) || strpos($body, '<rss') === false) && function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_HTTP_VERSION   => defined('CURL_HTTP_VERSION_2TLS') ? CURL_HTTP_VERSION_2TLS : CURL_HTTP_VERSION_1_1,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; M.H CORP NewsSeed/1.5)',
            CURLOPT_HTTPHEADER     => ['Accept: application/rss+xml, application/xml, text/xml, */*'],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $curl_body = curl_exec($ch);
        curl_close($ch);
        if (is_string($curl_body) && strpos($curl_body, '<rss') !== false) {
            $body = $curl_body;
        }
    }

    if (!is_string($body) || $body === '' || strpos($body, '<rss') === false && strpos($body, '<feed') === false) {
        return [];
    }

    $prev = libxml_use_internal_errors(true);
    $xml  = simplexml_load_string($body);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    if (!$xml) {
        return [];
    }

    $out = [];

    if (isset($xml->channel->item)) {
        foreach ($xml->channel->item as $item) {
            $out[] = [
                'title'   => (string) $item->title,
                'link'    => (string) $item->link,
                'excerpt' => wp_strip_all_tags((string) ($item->description ?? '')),
                'date'    => (string) ($item->pubDate ?? ''),
                'image'   => mha_rss_item_image($item),
            ];
        }
    } elseif (isset($xml->entry)) {
        foreach ($xml->entry as $entry) {
            $link = '';
            if (isset($entry->link)) {
                foreach ($entry->link as $l) {
                    $href = (string) $l['href'];
                    if ($href !== '') {
                        $link = $href;
                        break;
                    }
                }
            }
            $out[] = [
                'title'   => (string) $entry->title,
                'link'    => $link,
                'excerpt' => wp_strip_all_tags((string) ($entry->summary ?? $entry->content ?? '')),
                'date'    => (string) ($entry->updated ?? $entry->published ?? ''),
                'image'   => mha_rss_item_image($entry),
            ];
        }
    }

    return array_slice($out, 0, 15);
}

function mha_rss_item_image($item)
{
    $ns = [];
    if ($item instanceof SimpleXMLElement) {
        $ns = $item->getNamespaces(true);
        if (isset($ns['media'])) {
            $media = $item->children($ns['media']);
            if (isset($media->thumbnail['url']) && (string) $media->thumbnail['url'] !== '') {
                return mha_news_sanitize_image_url((string) $media->thumbnail['url']);
            }
            if (isset($media->content)) {
                foreach ($media->content as $content) {
                    $url  = (string) ($content['url'] ?? '');
                    $type = (string) ($content['type'] ?? '');
                    $medium = (string) ($content['medium'] ?? '');
                    if ($url !== '' && ($medium === 'image' || strpos($type, 'image') !== false || preg_match('/\.(jpe?g|png|webp|gif)(\?|$)/i', $url))) {
                        return mha_news_sanitize_image_url($url);
                    }
                }
            }
        }
        if (isset($item->enclosure['url'])) {
            $url  = (string) $item->enclosure['url'];
            $type = (string) ($item->enclosure['type'] ?? '');
            if ($url !== '' && ($type === '' || strpos($type, 'image') !== false || preg_match('/\.(jpe?g|png|webp|gif)(\?|$)/i', $url))) {
                return mha_news_sanitize_image_url($url);
            }
        }
        $desc = (string) ($item->description ?? $item->summary ?? '');
        if (preg_match('/<img[^>]+src=["\']([^"\']+)/i', html_entity_decode($desc, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $m)) {
            return mha_news_sanitize_image_url($m[1]);
        }
    }
    return '';
}

function mha_youm7_homepage_items()
{
    $html = mha_news_http_get('https://www.youm7.com/', 250000, [
        'Accept' => 'text/html,application/xhtml+xml',
    ], 2);
    if (!is_string($html) || $html === '') {
        return [];
    }

    if (!preg_match_all('#/story/(\d{4})/(\d{1,2})/(\d{1,2})/([^/"\'?\s]+)/(\d+)#u', $html, $matches, PREG_SET_ORDER)) {
        return [];
    }

    $seen = [];
    $out  = [];
    foreach ($matches as $m) {
        $id = $m[5];
        if (isset($seen[$id])) {
            continue;
        }
        $seen[$id] = true;
        $slug  = rawurldecode($m[4]);
        $title = trim(str_replace('-', ' ', $slug));
        $link  = sprintf('https://www.youm7.com/story/%s/%s/%s/%s/%s', $m[1], $m[2], $m[3], $m[4], $id);
        $hay   = $title;
        if (!preg_match(mha_rss_topic_regex(), $hay) || mha_news_is_offtopic($title, $hay)) {
            continue;
        }
        $out[] = [
            'title'        => $title,
            'link'         => $link,
            'excerpt'      => '',
            'date'         => sprintf('%s-%02d-%02d 09:00:00', $m[1], (int) $m[2], (int) $m[3]),
            'image'        => '',
            'feed_name'    => 'اليوم السابع',
            'feed_outlet'  => 'اليوم السابع',
        ];
        if (count($out) >= 12) {
            break;
        }
    }
    return $out;
}

function mha_get_news_posts($count = 8)
{
    $cat_id = mha_news_category_id();
    $q      = [
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'posts_per_page'      => (int) $count,
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
    ];

    if ($cat_id) {
        $q['cat'] = $cat_id;
    }

    $posts = get_posts($q);
    if ($posts) {
        return $posts;
    }

    unset($q['cat']);
    return get_posts($q);
}

function mha_news_image_url($post, $size = 'mha-news-thumb')
{
    if (has_post_thumbnail($post)) {
        $src = get_the_post_thumbnail_url($post, $size);
        if (!$src) {
            $src = get_the_post_thumbnail_url($post, 'mha-card');
        }
        if ($src) {
            return $src;
        }
    }

    $remote = get_post_meta($post->ID, '_mha_news_remote_thumb', true);
    if (is_string($remote) && $remote !== '') {
        $safe = mha_news_sanitize_image_url($remote);
        if ($safe) {
            return $safe;
        }
    }

    $file = get_post_meta($post->ID, '_mha_news_image', true);
    if (is_string($file) && $file !== '' && strpos($file, '..') === false && substr($file, -4) === '.png' && strpos($file, 'topic-') !== 0) {
        $path = MHA_DIR . '/assets/img/' . ltrim($file, '/');
        if (is_readable($path) && !mha_news_is_generic_office($file)) {
            return mha_img($file);
        }
    }

    $key = (string) get_post_meta($post->ID, '_mha_news_key', true);
    return mha_news_topic_placeholder_url($key !== '' ? $key : ('post-' . (int) $post->ID));
}

function mha_news_card_alt($post)
{
    return get_the_title($post);
}

function mha_news_is_generic_office($file)
{
    return in_array($file, ['hero-1.png', 'hero-2.png', 'about-office.png'], true);
}

function mha_news_outlet_name($post)
{
    $outlet = get_post_meta($post->ID, '_mha_news_outlet', true);
    if (is_string($outlet) && $outlet !== '') {
        return $outlet;
    }
    $origin = (string) get_post_meta($post->ID, '_mha_news_origin', true);
    $host   = $origin ? (string) wp_parse_url($origin, PHP_URL_HOST) : '';
    $host   = preg_replace('/^www\./', '', strtolower($host));
    $map    = [
        'youm7.com'   => 'اليوم السابع',
        'm.youm7.com' => 'اليوم السابع',
        'eta.gov.eg'  => 'مصلحة الضرائب المصرية',
        'cbe.org.eg'  => 'البنك المركزي المصري',
        'fra.gov.eg'  => 'الهيئة العامة للرقابة المالية',
        'nosi.gov.eg' => 'الهيئة القومية للتأمينات',
    ];
    if ($host && isset($map[$host])) {
        return $map[$host];
    }
    $source = (string) get_post_meta($post->ID, '_mha_news_source', true);
    if ($source !== '') {
        $parts = preg_split('/\s*[—–]\s*/u', $source);
        if (!empty($parts[0])) {
            return trim($parts[0]);
        }
    }
    return 'M.H CORP';
}

function mha_news_favicon_url($post)
{
    $origin = (string) get_post_meta($post->ID, '_mha_news_origin', true);
    $host   = $origin ? (string) wp_parse_url($origin, PHP_URL_HOST) : '';
    $host   = preg_replace('/^www\./', '', strtolower((string) $host));
    $key    = (string) get_post_meta($post->ID, '_mha_news_key', true);
    $inhouse = $key !== '' && strpos($key, 'rss:') !== 0;
    if ($inhouse || $host === '' || strpos($host, 'localhost') !== false) {
        return mha_img('logo-mark.png');
    }
    if ($host === 'm.youm7.com') {
        $host = 'youm7.com';
    }
    return 'https://www.google.com/s2/favicons?sz=64&domain=' . rawurlencode($host);
}

function mha_news_relative_time($post)
{
    $ts = get_post_time('U', true, $post);
    if (!$ts) {
        return get_the_date('', $post);
    }
    $diff = human_time_diff($ts, time());
    return sprintf('منذ %s', $diff);
}

function mha_render_news_card($post)
{
    $permalink = get_permalink($post);
    $title     = get_the_title($post);
    $excerpt   = wp_trim_words($post->post_excerpt ?: wp_strip_all_tags($post->post_content), 18);
    $img       = mha_news_image_url($post, 'mha-news-thumb');
    $alt       = mha_news_card_alt($post);
    $outlet    = mha_news_outlet_name($post);
    $favicon   = mha_news_favicon_url($post);
    $rel       = mha_news_relative_time($post);
    ?>
    <article class="mha-news-slide" role="listitem">
        <div class="mha-news-card">
            <div class="mha-news-source-row">
                <img class="mha-news-favicon" src="<?php echo esc_url($favicon); ?>" alt="" width="18" height="18" loading="lazy" decoding="async" referrerpolicy="no-referrer">
                <span class="mha-news-outlet"><?php echo esc_html($outlet); ?></span>
            </div>
            <div class="mha-news-main">
                <div class="mha-news-copy">
                    <h3><a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($title); ?></a></h3>
                    <p><?php echo esc_html($excerpt); ?></p>
                    <div class="mha-news-foot">
                        <time datetime="<?php echo esc_attr(get_the_date('c', $post)); ?>"><?php echo esc_html($rel); ?></time>
                        <a class="mha-news-more" href="<?php echo esc_url($permalink); ?>">اقرأ المزيد</a>
                    </div>
                </div>
                <a class="mha-news-thumb" href="<?php echo esc_url($permalink); ?>">
                    <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($alt); ?>" width="200" height="200" loading="lazy" decoding="async">
                </a>
            </div>
        </div>
    </article>
    <?php
}

function mha_news_require_media()
{
    if (!function_exists('wp_tempnam')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    if (!function_exists('media_handle_sideload')) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
    }
    if (!function_exists('wp_read_image_metadata')) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }
}

function mha_news_tmp($prefix = 'mha')
{
    mha_news_require_media();
    if (function_exists('wp_tempnam')) {
        $tmp = wp_tempnam($prefix);
        if ($tmp) {
            return $tmp;
        }
    }
    return tempnam(sys_get_temp_dir(), $prefix);
}

function mha_news_iri($url)
{
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }
    $parts = wp_parse_url($url);
    if (empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }
    if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
        return '';
    }
    $path = $parts['path'] ?? '';
    $segs = explode('/', $path);
    $enc  = [];
    foreach ($segs as $seg) {
        $enc[] = rawurlencode(rawurldecode($seg));
    }
    $uri = strtolower($parts['scheme']) . '://' . $parts['host'];
    if (!empty($parts['port'])) {
        $uri .= ':' . (int) $parts['port'];
    }
    $uri .= implode('/', $enc);
    if (!empty($parts['query'])) {
        $uri .= '?' . $parts['query'];
    }
    return $uri;
}

function mha_news_sanitize_image_url($url)
{
    $url = trim(html_entity_decode((string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($url === '') {
        return '';
    }
    if (strpos($url, '//') === 0) {
        $url = 'https:' . $url;
    }
    $url = mha_news_iri($url);
    if ($url === '') {
        return '';
    }
    $host   = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
    $path   = (string) wp_parse_url($url, PHP_URL_PATH);
    $ext    = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($host === '' || strpos($host, '.') === false) {
        return '';
    }
    if (preg_match('/^(localhost|127\.|10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/', $host)) {
        return '';
    }
    if (in_array($ext, ['svg', 'html', 'htm', 'php', 'js', 'xml', 'pdf'], true)) {
        return '';
    }
    if ($ext !== '' && !in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'jfif'], true)) {
        return '';
    }
    return $url;
}

function mha_news_ua()
{
    return 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
}

function mha_news_http_get($url, $max_bytes = 262144, $headers = [], $redirection = 2)
{
    $url = mha_news_iri($url);
    if ($url === '') {
        return '';
    }
    $args = [
        'timeout'             => 8,
        'redirection'         => (int) $redirection,
        'sslverify'           => true,
        'limit_response_size' => (int) $max_bytes,
        'user-agent'          => mha_news_ua(),
        'headers'             => array_merge([
            'Accept' => '*/*',
        ], $headers),
    ];
    $response = wp_remote_get($url, $args);
    if (is_wp_error($response)) {
        return '';
    }
    $code = (int) wp_remote_retrieve_response_code($response);
    if ($code < 200 || $code >= 400) {
        return '';
    }
    $body = wp_remote_retrieve_body($response);
    return is_string($body) ? $body : '';
}

function mha_news_og_image($page_url)
{
    $html = mha_news_http_get($page_url, 280000, [
        'Accept' => 'text/html,application/xhtml+xml',
    ], 3);
    if ($html === '') {
        return '';
    }
    $patterns = [
        '/<meta[^>]+property=["\']og:image(?::secure_url)?["\'][^>]+content=["\']([^"\']+)/i',
        '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image/i',
        '/<meta[^>]+name=["\']twitter:image(?::src)?["\'][^>]+content=["\']([^"\']+)/i',
        '/<link[^>]+rel=["\']image_src["\'][^>]+href=["\']([^"\']+)/i',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $html, $m)) {
            $img = mha_news_sanitize_image_url($m[1]);
            if ($img) {
                return $img;
            }
        }
    }
    if (preg_match('/https?:\/\/img\.youm7\.com\/large\/[^"\'\s>]+/i', $html, $m)) {
        return mha_news_sanitize_image_url($m[0]);
    }
    return '';
}

function mha_news_attach_remote($post_id, $image_url)
{
    mha_news_require_media();
    $image_url = mha_news_sanitize_image_url($image_url);
    if ($image_url === '') {
        return false;
    }

    $existing_src = (string) get_post_meta($post_id, '_mha_news_thumb_src', true);
    if ($existing_src === $image_url && has_post_thumbnail($post_id)) {
        return $image_url;
    }

    $body = mha_news_http_get($image_url, MHA_NEWS_THUMB_MAX, [
        'Accept'  => 'image/jpeg,image/png,image/webp,image/gif,*/*',
        'Referer' => 'https://www.youm7.com/',
    ], 3);
    if ($body === '' || strlen($body) < 400) {
        return false;
    }

    $tmp = mha_news_tmp('mha-news');
    if (!$tmp) {
        return false;
    }
    if (file_put_contents($tmp, $body) === false) {
        @unlink($tmp);
        return false;
    }

    $mime = function_exists('wp_get_image_mime') ? wp_get_image_mime($tmp) : '';
    $ok   = in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true);
    if (!$ok) {
        $info = @getimagesize($tmp);
        $ok   = is_array($info) && !empty($info['mime']) && strpos($info['mime'], 'image/') === 0 && $info['mime'] !== 'image/svg+xml';
        $mime = $ok ? $info['mime'] : $mime;
    }
    if (!$ok) {
        @unlink($tmp);
        return false;
    }

    $map = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    $ext  = $map[$mime] ?? 'jpg';
    $name = 'mha-news-' . (int) $post_id . '-' . substr(md5($image_url), 0, 8) . '.' . $ext;

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $id = media_handle_sideload([
        'name'     => $name,
        'tmp_name' => $tmp,
        'type'     => $mime,
    ], $post_id, get_the_title($post_id));

    if (is_wp_error($id)) {
        @unlink($tmp);
        return false;
    }

    $old = (int) get_post_thumbnail_id($post_id);
    set_post_thumbnail($post_id, (int) $id);
    update_post_meta($post_id, '_mha_news_thumb_src', $image_url);
    update_post_meta($post_id, '_mha_news_remote_thumb', $image_url);
    if ($old && $old !== (int) $id) {
        $old_src = get_post_meta($post_id, '_mha_news_generated', true);
        if ($old_src) {
            wp_delete_attachment($old, true);
        }
    }
    return $image_url;
}

function mha_news_topic_colors($key)
{
    $presets = [
        'topic-einvoice'  => [[11, 61, 92], [196, 163, 90], 'invoice'],
        'eta-einvoice-2026' => [[11, 61, 92], [196, 163, 90], 'invoice'],
        'topic-ereceipt'  => [[26, 107, 115], [228, 210, 163], 'receipt'],
        'eta-ereceipt-2026' => [[26, 107, 115], [228, 210, 163], 'receipt'],
        'topic-vat'       => [[90, 42, 30], [196, 163, 90], 'percent'],
        'vat-67-2016-2026'=> [[90, 42, 30], [196, 163, 90], 'percent'],
        'topic-income'    => [[18, 48, 72], [232, 214, 168], 'chart'],
        'income-tax-91-2005-2026' => [[18, 48, 72], [232, 214, 168], 'chart'],
        'topic-insurance' => [[40, 70, 58], [196, 163, 90], 'shield'],
        'social-insurance-148-2019' => [[40, 70, 58], [196, 163, 90], 'shield'],
        'topic-fx'        => [[20, 70, 48], [212, 175, 55], 'dollar'],
        'cbe-inflation-rates-2026' => [[20, 70, 48], [212, 175, 55], 'dollar'],
        'topic-fra'       => [[48, 32, 72], [196, 163, 90], 'columns'],
        'fra-governance-2026' => [[48, 32, 72], [196, 163, 90], 'columns'],
        'topic-payroll'   => [[72, 28, 40], [228, 210, 163], 'payroll'],
        'withholding-payroll-2026' => [[72, 28, 40], [228, 210, 163], 'payroll'],
        'topic-exam'      => [[32, 44, 64], [196, 163, 90], 'glass'],
        'tax-examination-2026' => [[32, 44, 64], [196, 163, 90], 'glass'],
        'topic-books'     => [[28, 52, 44], [196, 163, 90], 'books'],
        'bookkeeping-statements-2026' => [[28, 52, 44], [196, 163, 90], 'books'],
    ];
    if (isset($presets[$key])) {
        return $presets[$key];
    }
    $hash = md5((string) $key);
    $bg = [
        8 + hexdec(substr($hash, 0, 2)) % 50,
        28 + hexdec(substr($hash, 2, 2)) % 50,
        40 + hexdec(substr($hash, 4, 2)) % 50,
    ];
    $icons = ['invoice', 'receipt', 'percent', 'chart', 'shield', 'dollar', 'columns', 'payroll', 'glass', 'books'];
    $icon  = $icons[hexdec(substr($hash, 6, 2)) % count($icons)];
    return [$bg, [196, 163, 90], $icon];
}

function mha_news_draw_icon($im, $icon, $gold, $white, $cx, $cy)
{
    if ($icon === 'invoice' || $icon === 'receipt') {
        imagefilledrectangle($im, $cx - 70, $cy - 90, $cx + 70, $cy + 90, $white);
        imagefilledrectangle($im, $cx - 50, $cy - 60, $cx + 50, $cy - 50, $gold);
        imagefilledrectangle($im, $cx - 50, $cy - 30, $cx + 30, $cy - 22, $gold);
        imagefilledrectangle($im, $cx - 50, $cy - 5, $cx + 40, $cy + 3, $gold);
        imagefilledrectangle($im, $cx - 50, $cy + 20, $cx + 10, $cy + 28, $gold);
        if ($icon === 'receipt') {
            imagefilledrectangle($im, $cx - 70, $cy + 80, $cx - 50, $cy + 100, $white);
            imagefilledrectangle($im, $cx - 30, $cy + 80, $cx - 10, $cy + 100, $white);
            imagefilledrectangle($im, $cx + 10, $cy + 80, $cx + 30, $cy + 100, $white);
            imagefilledrectangle($im, $cx + 50, $cy + 80, $cx + 70, $cy + 100, $white);
        }
        return;
    }
    if ($icon === 'percent') {
        imagefilledellipse($im, $cx - 40, $cy - 40, 36, 36, $gold);
        imagefilledellipse($im, $cx + 40, $cy + 40, 36, 36, $gold);
        imagesetthickness($im, 10);
        imageline($im, $cx + 50, $cy - 70, $cx - 50, $cy + 70, $white);
        imagesetthickness($im, 1);
        return;
    }
    if ($icon === 'chart') {
        imagefilledrectangle($im, $cx - 70, $cy + 20, $cx - 35, $cy + 90, $gold);
        imagefilledrectangle($im, $cx - 20, $cy - 20, $cx + 15, $cy + 90, $white);
        imagefilledrectangle($im, $cx + 30, $cy - 70, $cx + 65, $cy + 90, $gold);
        return;
    }
    if ($icon === 'shield') {
        $pts = [$cx, $cy - 90, $cx + 70, $cy - 50, $cx + 55, $cy + 40, $cx, $cy + 90, $cx - 55, $cy + 40, $cx - 70, $cy - 50];
        imagefilledpolygon($im, $pts, $gold);
        imagefilledellipse($im, $cx, $cy - 10, 28, 28, $white);
        return;
    }
    if ($icon === 'dollar') {
        imagefilledellipse($im, $cx, $cy, 160, 160, $gold);
        imagefilledellipse($im, $cx, $cy, 120, 120, $white);
        imagefilledrectangle($im, $cx - 8, $cy - 50, $cx + 8, $cy + 50, $gold);
        return;
    }
    if ($icon === 'columns') {
        imagefilledrectangle($im, $cx - 80, $cy + 70, $cx + 80, $cy + 90, $gold);
        imagefilledrectangle($im, $cx - 70, $cy - 70, $cx - 50, $cy + 70, $white);
        imagefilledrectangle($im, $cx - 10, $cy - 70, $cx + 10, $cy + 70, $white);
        imagefilledrectangle($im, $cx + 50, $cy - 70, $cx + 70, $cy + 70, $white);
        imagefilledrectangle($im, $cx - 80, $cy - 90, $cx + 80, $cy - 70, $gold);
        return;
    }
    if ($icon === 'payroll') {
        imagefilledrectangle($im, $cx - 80, $cy - 40, $cx + 80, $cy + 50, $white);
        imagefilledrectangle($im, $cx - 60, $cy - 20, $cx + 20, $cy - 8, $gold);
        imagefilledrectangle($im, $cx + 40, $cy - 10, $cx + 70, $cy + 20, $gold);
        return;
    }
    if ($icon === 'glass') {
        imagefilledellipse($im, $cx - 10, $cy - 20, 90, 90, $white);
        imagefilledellipse($im, $cx - 10, $cy - 20, 60, 60, imagecolorallocate($im, 11, 61, 92));
        imagesetthickness($im, 12);
        imageline($im, $cx + 28, $cy + 18, $cx + 70, $cy + 70, $gold);
        imagesetthickness($im, 1);
        return;
    }
    imagefilledrectangle($im, $cx - 70, $cy - 50, $cx - 20, $cy + 70, $gold);
    imagefilledrectangle($im, $cx - 10, $cy - 70, $cx + 50, $cy + 70, $white);
}

function mha_news_generate_topic_png($key)
{
    if (!function_exists('imagecreatetruecolor')) {
        return '';
    }
    [$bg, $accent, $icon] = mha_news_topic_colors($key);
    $im = imagecreatetruecolor(800, 560);
    if (!$im) {
        return '';
    }
    $bg_c    = imagecolorallocate($im, $bg[0], $bg[1], $bg[2]);
    $gold    = imagecolorallocate($im, $accent[0], $accent[1], $accent[2]);
    $white   = imagecolorallocate($im, 246, 243, 236);
    $stripe  = imagecolorallocate($im, max(0, $bg[0] - 12), max(0, $bg[1] - 12), max(0, $bg[2] - 12));
    imagefilledrectangle($im, 0, 0, 800, 560, $bg_c);
    imagefilledrectangle($im, 0, 0, 18, 560, $gold);
    imagefilledellipse($im, 720, -40, 280, 280, $stripe);
    imagefilledellipse($im, 80, 600, 220, 220, $stripe);
    mha_news_draw_icon($im, $icon, $gold, $white, 400, 280);
    $tmp = mha_news_tmp('mha-topic');
    if (!$tmp) {
        imagedestroy($im);
        return '';
    }
    imagepng($im, $tmp, 6);
    imagedestroy($im);
    return $tmp;
}

function mha_news_attach_generated($post_id, $key)
{
    mha_news_require_media();
    $option = 'mha_topic_att_' . md5((string) $key);
    $existing = (int) get_option($option);
    if ($existing && get_post($existing) && wp_attachment_is_image($existing)) {
        set_post_thumbnail($post_id, $existing);
        update_post_meta($post_id, '_mha_news_thumb_src', 'generated:' . $key);
        update_post_meta($post_id, '_mha_news_generated', 1);
        return 'generated:' . $key;
    }

    $tmp = mha_news_generate_topic_png($key);
    if ($tmp === '' || !is_readable($tmp)) {
        return false;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $id = media_handle_sideload([
        'name'     => 'mha-' . sanitize_file_name($key) . '.png',
        'tmp_name' => $tmp,
        'type'     => 'image/png',
    ], $post_id, get_the_title($post_id));

    if (is_wp_error($id)) {
        @unlink($tmp);
        return false;
    }

    set_post_thumbnail($post_id, (int) $id);
    update_option($option, (int) $id);
    update_post_meta($post_id, '_mha_news_thumb_src', 'generated:' . $key);
    update_post_meta($post_id, '_mha_news_generated', 1);
    return 'generated:' . $key;
}

function mha_news_topic_placeholder_url($key)
{
    $option = 'mha_topic_att_' . md5((string) $key);
    $id = (int) get_option($option);
    if ($id) {
        $src = wp_get_attachment_image_url($id, 'mha-news-thumb');
        if ($src) {
            return $src;
        }
    }
    return mha_img('logo-mark.png');
}

/**
 * Attach the article's own thumbnail, else a distinct topic image.
 *
 * @param int    $post_id
 * @param string $topic_key
 * @param string $origin
 * @param string $rss_image
 * @param array  $used_thumbs Remote URLs already used on nearby cards.
 * @return string|false Thumb identity (remote URL or generated:key)
 */
function mha_news_sync_thumb($post_id, $topic_key = '', $origin = '', $rss_image = '', array $used_thumbs = [])
{
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return false;
    }

    if ($topic_key === '') {
        $topic_key = (string) get_post_meta($post_id, '_mha_news_image', true);
    }
    if ($origin === '') {
        $origin = (string) get_post_meta($post_id, '_mha_news_origin', true);
    }
    if ($rss_image === '') {
        $rss_image = (string) get_post_meta($post_id, '_mha_news_remote_thumb', true);
    }
    $news_key = (string) get_post_meta($post_id, '_mha_news_key', true);
    $have_src = (string) get_post_meta($post_id, '_mha_news_thumb_src', true);
    $is_rss   = strpos($news_key, 'rss:') === 0;

    if ($have_src !== '' && has_post_thumbnail($post_id)) {
        if (strpos($have_src, 'generated:') === 0 && $is_rss) {
            // RSS posts should prefer the publisher photo if we can still fetch it.
        } else {
            return $have_src;
        }
    }

    $candidates = [];
    if ($rss_image) {
        $candidates[] = $rss_image;
    }
    $origin_path = $origin ? (string) wp_parse_url($origin, PHP_URL_PATH) : '';
    $origin_is_article = $origin_path !== '' && preg_match('#/story/|/news/|/article/#i', $origin_path);
    if ($origin && ($is_rss || $origin_is_article)) {
        $og = mha_news_og_image($origin);
        if ($og) {
            $candidates[] = $og;
        }
    }

    foreach ($candidates as $url) {
        $url = mha_news_sanitize_image_url($url);
        if ($url === '') {
            continue;
        }
        if (in_array($url, $used_thumbs, true) && count($candidates) > 1) {
            continue;
        }
        $attached = mha_news_attach_remote($post_id, $url);
        if ($attached) {
            return $attached;
        }
        update_post_meta($post_id, '_mha_news_remote_thumb', $url);
    }

    $gen_key = $topic_key !== '' ? $topic_key : ($news_key !== '' ? $news_key : ('post-' . $post_id));
    return mha_news_attach_generated($post_id, $gen_key);
}

function mha_news_backfill_thumbs()
{
    $posts = get_posts([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => 40,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);
    $used = [];
    foreach ($posts as $post) {
        $src = mha_news_sync_thumb((int) $post->ID, '', '', '', $used);
        if (is_string($src) && strpos($src, 'generated:') !== 0) {
            $used[] = $src;
        }
        $used = array_slice($used, -3);
    }
    return count($posts);
}

function mha_news_maybe_schedule_backfill()
{
    if ((int) get_option('mha_news_thumbs_version') >= 2) {
        return;
    }
    if (!wp_next_scheduled('mha_news_backfill')) {
        wp_schedule_single_event(time() + 20, 'mha_news_backfill');
    }
}
add_action('init', 'mha_news_maybe_schedule_backfill', 50);
add_action('mha_news_backfill', 'mha_news_backfill_thumbs');

