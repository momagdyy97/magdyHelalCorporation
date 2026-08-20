<?php
/**
 * Shared helpers and default copy for MAGDY HELAL CORP.
 *
 * @package Magdi_Hilal_Adco
 */

if (!defined('ABSPATH')) {
    exit;
}

function mha_mod($key, $default = '')
{
    $value = get_theme_mod($key, $default);
    return ($value === '' || $value === null) ? $default : $value;
}

function mha_img($file)
{
    $rel = '/assets/img/' . ltrim($file, '/');
    $uri = MHA_URI . $rel;
    $path = MHA_DIR . $rel;
    if (is_readable($path)) {
        $uri = add_query_arg('v', (string) filemtime($path), $uri);
    }
    return $uri;
}

function mha_page_url($slug)
{
    $page = get_page_by_path($slug);
    return $page ? get_permalink($page) : home_url('/' . $slug . '/');
}

function mha_phone_digits($raw = null)
{
    $raw = $raw ?? mha_mod('mha_phone', mha_defaults()['phone']);
    $digits = preg_replace('/\D+/', '', (string) $raw);
    if ($digits === '') {
        return '201000354045';
    }
    if (strpos($digits, '20') === 0) {
        return $digits;
    }
    if (strpos($digits, '0') === 0) {
        return '2' . $digits;
    }
    return $digits;
}

function mha_phone_display($raw = null)
{
    return '+' . mha_phone_digits($raw);
}

function mha_tel_href($raw = null)
{
    return 'tel:' . mha_phone_display($raw);
}

function mha_phone_html($extra_class = '')
{
    $class = trim('mha-phone ' . $extra_class);
    return sprintf(
        '<bdi class="%s" dir="ltr">%s</bdi>',
        esc_attr($class),
        esc_html(mha_phone_display())
    );
}

function mha_whatsapp_digits()
{
    $raw = mha_mod('mha_whatsapp', mha_defaults()['whatsapp']);
    $digits = preg_replace('/\D+/', '', (string) $raw);
    if ($digits === '') {
        return mha_phone_digits();
    }
    if (strpos($digits, '20') === 0) {
        return $digits;
    }
    if (strpos($digits, '0') === 0) {
        return '2' . $digits;
    }
    return $digits;
}

function mha_whatsapp_link()
{
    $text = rawurlencode('السلام عليكم، أرغب في الاستفسار عن خدمات مكتب مجدي هلال — M.H CORP.');
    return 'https://wa.me/' . mha_whatsapp_digits() . '?text=' . $text;
}

function mha_defaults()
{
    return [
        'firm'        => 'مكتب مجدي هلال',
        'firm_en'     => 'M.H CORP',
        'tagline'     => 'المحاسبة · الضرائب · المراجعة',
        'hours'       => 'السبت — الخميس · 9:00 ص — 5:00 م',
        'phone'       => '+201000354045',
        'phone_alt'   => '',
        'whatsapp'    => '201000354045',
        'email'       => 'magdy.hilal@co',
        'address'     => 'مدينة نصر، القاهرة',
        'hero_kicker' => 'M.H CORP',
        'hero_title'  => 'خبرة محاسبية تقود قرارات أوضح',
        'hero_text'   => 'مكتب مهني في مدينة نصر يخدم الشركات في المحاسبة والضرائب والمراجعة، بقيادة المحاسب القانوني والمستشار الضريبي مجدي هلال وفريق يضم نحو 20 إلى 30 محاسباً.',
        'hero_cta'    => 'اطلب استشارة',
        'about_lead'  => 'مكتب مجدي هلال للمحاسبة والمراجعة (M.H CORP — magdyhelalCORP) مكتب مهني في مدينة نصر بالقاهرة. يقوده المحاسب القانوني والمستشار الضريبي مجدي هلال، ويعمل فيه فريق يضم نحو 20 إلى 30 محاسباً يرافقون الشركات في الدورة المحاسبية والالتزام الضريبي والمراجعة.',
        'stat_years'  => '25',
        'stat_clients'=> '180',
        'stat_team'   => '25',
        'stat_depts'  => '4',
    ];
}

function mha_nav_fallback()
{
    $items = [
        'home'     => ['الرئيسية', home_url('/')],
        'about'    => ['من نحن', mha_page_url('about')],
        'services' => ['خدماتنا', mha_page_url('services')],
        'team'     => ['فريق العمل', mha_page_url('team')],
        'clients'  => ['عملاؤنا', mha_page_url('clients')],
        'projects' => ['مشاريعنا', mha_page_url('projects')],
        'news'     => ['الأخبار', get_permalink(get_option('page_for_posts')) ?: mha_page_url('news')],
        'contact'  => ['تواصل معنا', mha_page_url('contact')],
    ];

    echo '<ul class="navbar-nav mr-auto mha-nav">';
    foreach ($items as $key => $item) {
        $active = mha_is_section($key) ? ' active' : '';
        printf(
            '<li class="nav-item%s"><a class="nav-link" href="%s">%s</a></li>',
            esc_attr($active),
            esc_url($item[1]),
            esc_html($item[0])
        );
    }
    echo '</ul>';
}

function mha_is_section($key)
{
    if ($key === 'home') {
        return is_front_page();
    }
    if ($key === 'news') {
        return is_home() || is_singular('post') || is_category() || is_archive();
    }
    return is_page($key);
}

function mha_icon($name)
{
    $icons = [
        'star' => '<svg viewBox="0 0 48 48" aria-hidden="true"><path fill="currentColor" d="M24 6l4.6 11.2H40l-9.3 6.8 3.6 11L24 28.8 13.7 35l3.6-11L8 17.2h11.4z"/></svg>',
        'handshake' => '<svg viewBox="0 0 48 48" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2.2" d="M8 24l8-8 6 6 4-4 6 6 8-8M14 30l6 6 6-6 6 6"/></svg>',
        'team' => '<svg viewBox="0 0 48 48" aria-hidden="true"><circle cx="24" cy="16" r="5" fill="none" stroke="currentColor" stroke-width="2.2"/><circle cx="12" cy="18" r="4" fill="none" stroke="currentColor" stroke-width="2.2"/><circle cx="36" cy="18" r="4" fill="none" stroke="currentColor" stroke-width="2.2"/><path fill="none" stroke="currentColor" stroke-width="2.2" d="M10 36c0-5 5-8 14-8s14 3 14 8M6 34c0-3.5 3-6 8-6M42 34c0-3.5-3-6-8-6"/></svg>',
        'tax' => '<svg viewBox="0 0 48 48" aria-hidden="true"><rect x="10" y="8" width="28" height="32" rx="3" fill="none" stroke="currentColor" stroke-width="2.2"/><path stroke="currentColor" stroke-width="2.2" d="M16 18h16M16 24h16M16 30h10"/></svg>',
        'audit' => '<svg viewBox="0 0 48 48" aria-hidden="true"><circle cx="22" cy="22" r="10" fill="none" stroke="currentColor" stroke-width="2.2"/><path stroke="currentColor" stroke-width="2.2" d="M30 30l8 8M18 22h8M22 18v8"/></svg>',
        'books' => '<svg viewBox="0 0 48 48" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2.2" d="M12 10h18a4 4 0 014 4v24H16a4 4 0 01-4-4V10z"/><path fill="none" stroke="currentColor" stroke-width="2.2" d="M12 34a4 4 0 014-4h22"/></svg>',
        'consult' => '<svg viewBox="0 0 48 48" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2.2" d="M10 34V14h20l8 8v12H10z"/><path fill="none" stroke="currentColor" stroke-width="2.2" d="M30 14v8h8M16 24h12M16 29h8"/></svg>',
        'phone' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M6.6 10.8a15 15 0 006.6 6.6l2.2-2.2a1 1 0 011-.25 11 11 0 003.5.55 1 1 0 011 1V20a1 1 0 01-1 1A17 17 0 013 4a1 1 0 011-1h3.5a1 1 0 011 1 11 11 0 00.55 3.5 1 1 0 01-.25 1z"/></svg>',
        'mail' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M20 4H4a2 2 0 00-2 2v12a2 2 0 002 2h16a2 2 0 002-2V6a2 2 0 00-2-2zm0 4l-8 5L4 8V6l8 5 8-5z"/></svg>',
        'clock' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 11H11V7h2v4h3v2z"/></svg>',
        'pin' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a7 7 0 00-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 00-7-7zm0 9.5A2.5 2.5 0 119.5 9 2.5 2.5 0 0112 11.5z"/></svg>',
        'send' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M2 21l21-9L2 3v7l15 2-15 2z"/></svg>',
        'arrow' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M14 7l-1.4 1.4L15.2 11H3v2h12.2l-2.6 2.6L14 17l5-5z"/></svg>',
        'search' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M15.5 14h-.8l-.3-.3A6.5 6.5 0 1014 15.5l.3.3v.8l5 5 1.5-1.5-5-5zm-6 0A4.5 4.5 0 1114 9.5 4.5 4.5 0 019.5 14z"/></svg>',
        'whatsapp' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 00-8.7 15L2 22l5.2-1.4A10 10 0 1012 2zm5.2 14.2c-.2.6-1.2 1.1-1.7 1.2-.4.1-.9.1-1.5 0a14 14 0 01-6.2-3.9 10 10 0 01-2.2-3.6c-.3-1 .1-1.9.6-2.2.3-.2.8-.4 1.2 0l1.2 1.4c.2.3.3.6.1.9l-.5.8c-.1.2 0 .5.2.7l1.6 1.8c.5.5 1 .9 1.6 1.2.2.1.5.1.7-.1l.8-.6c.3-.2.6-.2.9 0l1.6 1c.5.3.6.8.4 1.4z"/></svg>',
        'chat' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M4 4h16a2 2 0 012 2v9a2 2 0 01-2 2H9l-5 4V6a2 2 0 012-2zm3 5v2h10V9H7zm0 4v2h7v-2H7z"/></svg>',
        'refresh' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M17.65 6.35A8 8 0 104 12h2a6 6 0 111.76 4.24L6 14v6h6l-2.47-2.47A8 8 0 0017.65 6.35z"/></svg>',
        'close' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>',
        'mic' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 14a3 3 0 003-3V6a3 3 0 00-6 0v5a3 3 0 003 3zm5-3a5 5 0 01-10 0H5a7 7 0 0014 0h-2zm-5 9a1 1 0 001-1h2a3 3 0 01-3 3 3 3 0 01-3-3h2a1 1 0 001 1z"/></svg>',
        'image' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M21 19V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2zM8.5 13.5l2.5 3 3.5-4.5 4.5 6H5l3.5-4.5zM8.5 8A1.5 1.5 0 1110 9.5 1.5 1.5 0 018.5 8z"/></svg>',
        'speaker' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M3 10v4h4l5 5V5L7 10H3zm13.5 2a4.5 4.5 0 00-2.5-4v8a4.5 4.5 0 002.5-4zM14 3.2v2.1a7.5 7.5 0 010 13.4v2.1a9.5 9.5 0 000-17.6z"/></svg>',
        'send-chat' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M2 21l21-9L2 3v7l15 2-15 2z"/></svg>',
    ];

    return $icons[$name] ?? '';
}

function mha_values()
{
    return [
        [
            'icon'  => 'star',
            'title' => 'التميز',
            'text'  => 'نراجع الأرقام مرتين قبل أن تصل إلى الإدارة أو مصلحة الضرائب، ونبني تقارير واضحة تساعد الشركة على القرار لا على ملء الملفات فقط.',
        ],
        [
            'icon'  => 'handshake',
            'title' => 'الأمانة',
            'text'  => 'الالتزام الضريبي والمحاسبي أمانة مهنية. نوضح المخاطر كما هي، ونقترح المسار النظامي دون اختصار يضر العميل لاحقاً.',
        ],
        [
            'icon'  => 'team',
            'title' => 'العمل بروح الفريق',
            'text'  => 'المكتب يعمل كوحدة واحدة: محاسب العميل، مراجع الملف، والمستشار الضريبي يتبادلون الملاحظات حتى يخرج العمل متسقاً.',
        ],
    ];
}

function mha_services()
{
    return [
        [
            'slug'  => 'tax',
            'title' => 'الأعمال الضريبية',
            'text'  => 'إقرارات، فحص، قيمة مضافة، وفاتورة إلكترونية — بمتابعة عملية تناسب حجم الشركة.',
            'image' => 'service-tax.png',
            'icon'  => 'tax',
        ],
        [
            'slug'  => 'audit',
            'title' => 'المراجعة والتدقيق',
            'text'  => 'مراجعة خارجية وفحص حسابات يركّزان على المخاطر والضوابط لا على الشكل فقط.',
            'image' => 'service-audit.png',
            'icon'  => 'audit',
        ],
        [
            'slug'  => 'accounting',
            'title' => 'الأنظمة المحاسبية',
            'text'  => 'إمساك دفاتر، دورة مستندية، وتقارير شهرية تساعد الإدارة على قراءة المركز المالي.',
            'image' => 'about-office.png',
            'icon'  => 'books',
        ],
        [
            'slug'  => 'consulting',
            'title' => 'الاستشارات المالية',
            'text'  => 'هيكلة، تأسيس شركات، وقراءة للقوائم تساعد الشركاء على التخطيط قبل نهاية السنة.',
            'image' => 'hero-2.png',
            'icon'  => 'consult',
        ],
    ];
}

function mha_departments()
{
    return [
        [
            'title' => 'قسم المراجعة',
            'text'  => 'تخطيط عملية المراجعة، تقييم الرقابة الداخلية، وإصدار تقارير مهنية للإدارة والجمعيات.',
            'image' => 'service-audit.png',
        ],
        [
            'title' => 'قسم الضرائب',
            'text'  => 'ملفات ضريبية للشركات، التعامل مع الفحص، ومتابعة المنظومات الإلكترونية المصرية.',
            'image' => 'service-tax.png',
        ],
        [
            'title' => 'قسم الاستشارات',
            'text'  => 'دعم القرارات المالية وتأسيس الشركات والقراءة الإدارية للقوائم والتقارير.',
            'image' => 'hero-1.png',
        ],
    ];
}

function mha_placeholder_clients()
{
    return [
        ['النور للتجارة', 'شركة النور'],
        ['أفق الصناعية', 'مجموعة الأفق'],
        ['وادي النيل', 'صناعات وادي النيل'],
        ['خدمات المدن', 'المدن للتطوير'],
        ['تقنية المشرق', 'المشرق لتقنية المعلومات'],
        ['رخام القاهرة', 'رخام القاهرة'],
    ];
}

function mha_placeholder_team()
{
    return [
        ['مجدي هلال', 'مدير المكتب — مستشار ضريبي ومحاسب قانوني'],
        ['فريق المحاسبة', 'محاسبو الشركات والدورة المستندية'],
        ['فريق الضرائب', 'إقرارات وفحص ومنظومات إلكترونية'],
        ['فريق المراجعة', 'تخطيط المراجعة وتقارير الفحص'],
        ['الدعم الإداري', 'تنسيق الملفات ومواعيد العملاء'],
    ];
}

function mha_placeholder_projects()
{
    return [
        ['إعادة ترتيب الدورة المحاسبية لشركة تجارية', 'مدينة نصر', 'محاسبة'],
        ['تأهيل ملف ضريبي قبل الفحص', 'القاهرة', 'ضرائب'],
        ['مراجعة قوائم مالية لشركة خدمات', 'القاهرة الكبرى', 'مراجعة'],
    ];
}

function mha_placeholder_news()
{
    if (function_exists('mha_curated_news')) {
        $out = [];
        foreach (array_slice(mha_curated_news(), 0, 3) as $item) {
            $out[] = [
                'title'   => $item['title'],
                'excerpt' => $item['excerpt'],
            ];
        }
        return $out;
    }

    return [
        [
            'title'   => 'منظومة الفاتورة الإلكترونية: ما الذي تتابعه الشركات مع مصلحة الضرائب؟',
            'excerpt' => 'التسجيل على المنظومة، إصدار الفاتورة المعتمدة، وربط الدورة المستندية حتى لا يتوقف البيع أو يتأخر الإقرار.',
        ],
        [
            'title'   => 'الإيصال الإلكتروني: من فاتورة الأعمال إلى التعامل مع المستهلك النهائي',
            'excerpt' => 'الإيصال يغطي البيع للمستهلك غير المسجّل. الشركات مطالبة بتهيئة نقاط البيع والربط مع منظومة المصلحة وفق مراحل الإلزام.',
        ],
        [
            'title'   => 'ضريبة القيمة المضافة: التسجيل والإقرار والخصم في إطار القانون 67 لسنة 2016',
            'excerpt' => 'الإقرار الدوري، خصم الضريبة على المدخلات، والتعامل مع الفواتير المعتمدة — ثلاثة محاور تُراجع قبل أي موسم إقرارات.',
        ],
    ];
}
