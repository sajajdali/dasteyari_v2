/* دست یاری — منبع واحد تنظیمات (گروه‌های کمک و منوها).
   همه صفحات از اینجا می‌خوانند؛ مدیر در «تنظیمات» تغییر می‌دهد و در localStorage ذخیره می‌شود. */
(function () {
  var KEY = 'dy-settings-v1';

  var ICONS = [
    { cat: 'سلامت و درمان', items: ['⚕', '✚', '♡', '☤', '⚚', '☩', '✜', '❤', '⌘', '☘', '✛', '⚘', '❦', '✤', '☙', '✿'] },
    { cat: 'خانه و مسکن', items: ['⌂', '⌗', '⛨', '⌸', '▤', '▥', '◫', '⬓', '⬒', '⌷', '⍁', '⌺', '⎔', '⏢', '◰', '◱'] },
    { cat: 'مردم و خانواده', items: ['☷', '☰', '☱', '☲', '⚭', '⚮', '☗', '❋', '❉', '✾', '❀', '☺', '☻', '⚯', '⚬', '✧'] },
    { cat: 'حقوقی و آزادی', items: ['⛓', '⚖', '⚔', '⛔', '⊘', '⌦', '⏻', '☡', '⚠', '✇', '⌸', '⏚', '⌁', '⎋', '⏏', '⎘'] },
    { cat: 'آموزش', items: ['✎', '✐', '✑', '✒', '✏', '☑', '❏', '❐', '❑', '❒', '▦', '▩', '◈', '◉', '◎', '◍'] },
    { cat: 'مالی و پرداخت', items: ['⇄', '⇅', '⇆', '⇋', '⊞', '⊟', '⊠', '⊡', '％', '№', '☰', '▧', '▨', '◊', '◇', '◆'] },
    { cat: 'زمان و وضعیت', items: ['◷', '◶', '◵', '◴', '⏱', '⏳', '⌛', '⏸', '⏵', '⏹', '⚡', '✓', '✔', '✕', '✖', '↻'] },
    { cat: 'ارتباط', items: ['✉', '✆', '☎', '✍', '⌨', '☏', '✂', '✈', '⌲', '⍾', '☞', '☜', '☝', '☟', '⌯', '⌖'] },
    { cat: 'نشان و ستاره', items: ['★', '☆', '✦', '✩', '✪', '✫', '✬', '✭', '✮', '✯', '❂', '❃', '❄', '❅', '❆', '✷'] },
    { cat: 'جهت و ترتیب', items: ['←', '→', '↑', '↓', '↔', '↕', '⇐', '⇒', '⇑', '⇓', '⇔', '⌃', '⌄', '⋮', '⋯', '≡'] }
  ];

  var DEFAULT_GROUPS = [
    { id: 'درمان', title: 'درمان و دارو', icon: '⚕', desc: 'جراحی، شیمی‌درمانی، دیالیز و داروهای خارج از پوشش بیمه', active: true },
    { id: 'زندانی', title: 'آزادی زندانیان', icon: '⛓', desc: 'جرائم غیرعمد، دیه، مهریه و وثیقه', active: true },
    { id: 'جهیزیه', title: 'جهیزیه و ازدواج', icon: '❋', desc: 'تأمین جهیزیه و هزینه ازدواج زوج‌های کم‌درآمد', active: true },
    { id: 'مسکن', title: 'مسکن و ودیعه', icon: '⌂', desc: 'اجاره عقب‌افتاده، ودیعه مسکن و جلوگیری از تخلیه', active: true },
    { id: 'معیشت', title: 'معیشت خانواده', icon: '◍', desc: 'سبد غذایی ماهانه و حمایت مستمر خانوار', active: true },
    { id: 'تحصیل', title: 'تحصیل کودکان', icon: '✎', desc: 'شهریه، لوازم‌التحریر و تجهیزات آموزشی', active: true },
    { id: 'معلولیت', title: 'معلولیت و سالمندی', icon: '⚘', desc: 'ویلچر، تجهیزات پزشکی خانگی و نگهداری سالمند', active: true }
  ];

  var BASE_QUESTIONS = [
    { id: 'q-story', label: 'شرح وضعیت و علت درخواست', type: 'textarea', required: true, hint: 'در چند خط توضیح دهید چه اتفاقی افتاده و چه کمکی لازم دارید.' },
    { id: 'q-income', label: 'درآمد ماهانه خانوار (تومان)', type: 'number', required: true, unit: 'تومان' },
    { id: 'q-members', label: 'تعداد افراد خانوار', type: 'number', required: true },
    { id: 'q-house', label: 'وضعیت مسکن', type: 'select', required: true, options: ['استیجاری', 'ملکی', 'مسکن سازمانی', 'بدون سرپناه ثابت'] }
  ];

  var BASE_DOCS = [
    { id: 'd-nid', label: 'تصویر کارت ملی سرپرست', type: 'image', max: 1, required: true, hint: 'واضح و بدون انعکاس نور' },
    { id: 'd-book', label: 'صفحه اول شناسنامه اعضای خانوار', type: 'image', max: 4, required: true }
  ];

  function q(id, label, type, required, extra) {
    return Object.assign({ id: id, label: label, type: type, required: !!required }, extra || {});
  }

  var DEFAULT_FORMS = {
    'درمان': {
      note: 'مدارک درمانی باید حداکثر ۳ ماه اخیر باشد.',
      questions: BASE_QUESTIONS.concat([
        q('q-disease', 'نام بیماری یا نوع جراحی', 'text', true),
        q('q-hospital', 'بیمارستان یا مرکز درمانی', 'text', true),
        q('q-cost', 'برآورد هزینه درمان (تومان)', 'number', true, { unit: 'تومان' }),
        q('q-insurance', 'وضعیت بیمه', 'select', true, { options: ['تأمین اجتماعی', 'سلامت', 'نیروهای مسلح', 'بدون بیمه'] }),
        q('q-urgency', 'فوریت درمان', 'radio', true, { options: ['فوری (کمتر از ۱۰ روز)', 'در ۱ ماه آینده', 'قابل زمان‌بندی'] })
      ]),
      docs: BASE_DOCS.concat([
        q('d-order', 'دستور پزشک یا برگه بستری', 'file', true, { max: 3 }),
        q('d-quote', 'پیش‌فاکتور هزینه درمان', 'file', true, { max: 2 }),
        q('d-tests', 'آزمایش‌ها و تصاویر پزشکی', 'file', false, { max: 6 }),
        q('d-insurance', 'کارت بیمه یا نامه عدم پوشش', 'image', false, { max: 2 })
      ])
    },
    'زندانی': {
      note: 'مبلغ باید با حکم دادگاه یا نامه زندان قابل راستی‌آزمایی باشد.',
      questions: BASE_QUESTIONS.concat([
        q('q-crime', 'نوع محکومیت', 'select', true, { options: ['جرائم غیرعمد (تصادف)', 'دیه', 'مهریه و نفقه', 'چک و بدهی مالی'] }),
        q('q-amount', 'مبلغ لازم برای آزادی (تومان)', 'number', true, { unit: 'تومان' }),
        q('q-paid', 'مبلغ تأمین‌شده تا امروز (تومان)', 'number', false, { unit: 'تومان' }),
        q('q-prison', 'زندان محل نگهداری', 'text', true),
        q('q-since', 'تاریخ شروع حبس', 'date', true)
      ]),
      docs: BASE_DOCS.concat([
        q('d-verdict', 'حکم دادگاه', 'file', true, { max: 3 }),
        q('d-prison', 'نامه رسمی زندان یا ستاد دیه', 'file', true, { max: 2 }),
        q('d-consent', 'رضایت شاکی (در صورت وجود)', 'file', false, { max: 2 })
      ])
    },
    'جهیزیه': {
      note: 'عقدنامه الزامی است؛ فهرست اقلام به تشخیص کارشناس بازبینی می‌شود.',
      questions: BASE_QUESTIONS.concat([
        q('q-wed', 'تاریخ عقد یا مراسم', 'date', true),
        q('q-items', 'اقلام مورد نیاز', 'checkbox', true, { options: ['یخچال', 'ماشین لباسشویی', 'اجاق گاز', 'سرویس خواب', 'فرش', 'ظروف'] }),
        q('q-support', 'حمایت خانواده دو طرف', 'radio', false, { options: ['هیچ', 'جزئی', 'قابل توجه'] })
      ]),
      docs: BASE_DOCS.concat([
        q('d-marriage', 'عقدنامه یا گواهی ازدواج', 'file', true, { max: 2 }),
        q('d-quote2', 'پیش‌فاکتور اقلام', 'file', false, { max: 4 })
      ])
    },
    'مسکن': {
      note: 'اجاره‌نامه رسمی یا کدرهگیری الزامی است.',
      questions: BASE_QUESTIONS.concat([
        q('q-rent', 'اجاره ماهانه (تومان)', 'number', true, { unit: 'تومان' }),
        q('q-debt', 'ماه‌های عقب‌افتاده', 'number', true),
        q('q-deposit', 'ودیعه مورد نیاز (تومان)', 'number', false, { unit: 'تومان' }),
        q('q-evict', 'حکم تخلیه دارید؟', 'radio', true, { options: ['بله، صادر شده', 'در جریان است', 'خیر'] })
      ]),
      docs: BASE_DOCS.concat([
        q('d-lease', 'اجاره‌نامه یا کد رهگیری', 'file', true, { max: 2 }),
        q('d-evict', 'اظهارنامه یا حکم تخلیه', 'file', false, { max: 2 }),
        q('d-home', 'تصاویر محل سکونت', 'image', true, { max: 4, hint: 'نمای کلی اتاق‌ها و آشپزخانه' })
      ])
    },
    'معیشت': {
      note: 'برای حمایت مستمر، بازدید میدانی الزامی است.',
      questions: BASE_QUESTIONS.concat([
        q('q-jobless', 'وضعیت شغلی سرپرست', 'select', true, { options: ['بیکار', 'کارگر روزمزد', 'شاغل با درآمد ناکافی', 'ازکارافتاده'] }),
        q('q-need', 'نوع حمایت مورد نیاز', 'checkbox', true, { options: ['سبد غذایی ماهانه', 'کمک نقدی ماهانه', 'شارژ کالابرگ'] }),
        q('q-other', 'حمایت از نهاد دیگری دریافت می‌کنید؟', 'radio', true, { options: ['خیر', 'کمیته امداد', 'بهزیستی', 'خیریه دیگر'] })
      ]),
      docs: BASE_DOCS.concat([
        q('d-income', 'فیش حقوقی یا گواهی بیکاری', 'file', false, { max: 2 }),
        q('d-bank', 'گردش حساب سه ماه اخیر', 'file', false, { max: 3 })
      ])
    },
    'تحصیل': {
      note: 'گواهی اشتغال به تحصیل هر سال باید تازه‌سازی شود.',
      questions: BASE_QUESTIONS.concat([
        q('q-kids', 'تعداد فرزندان محصل', 'number', true),
        q('q-grade', 'مقطع تحصیلی', 'checkbox', true, { options: ['ابتدایی', 'متوسطه اول', 'متوسطه دوم', 'دانشگاه'] }),
        q('q-tuition', 'شهریه یا هزینه لازم (تومان)', 'number', true, { unit: 'تومان' })
      ]),
      docs: BASE_DOCS.concat([
        q('d-school', 'گواهی اشتغال به تحصیل', 'file', true, { max: 4 }),
        q('d-tuition', 'برگه شهریه یا فهرست لوازم', 'file', false, { max: 3 })
      ])
    },
    'معلولیت': {
      note: 'کارت معلولیت یا نظر پزشک متخصص الزامی است.',
      questions: BASE_QUESTIONS.concat([
        q('q-kind', 'نوع معلولیت / وضعیت سالمند', 'select', true, { options: ['حرکتی', 'ذهنی', 'نابینایی', 'ناشنوایی', 'سالمند نیازمند مراقبت'] }),
        q('q-equip', 'تجهیزات مورد نیاز', 'checkbox', false, { options: ['ویلچر', 'تخت بیمار', 'اکسیژن‌ساز', 'سمعک', 'عصا و واکر'] }),
        q('q-care', 'نیاز به مراقب دارد؟', 'radio', true, { options: ['بله، تمام‌وقت', 'بله، پاره‌وقت', 'خیر'] })
      ]),
      docs: BASE_DOCS.concat([
        q('d-card', 'کارت معلولیت بهزیستی', 'image', false, { max: 2 }),
        q('d-doctor', 'نظر پزشک متخصص', 'file', true, { max: 3 }),
        q('d-equip', 'پیش‌فاکتور تجهیزات', 'file', false, { max: 3 })
      ])
    }
  };

  var FIELD_TYPES = [
    { id: 'text', label: 'متن کوتاه' },
    { id: 'textarea', label: 'متن بلند / توضیحات' },
    { id: 'number', label: 'عدد' },
    { id: 'date', label: 'تاریخ' },
    { id: 'select', label: 'کمبوباکس (یک انتخاب)' },
    { id: 'radio', label: 'دکمه‌ای (یک انتخاب)' },
    { id: 'checkbox', label: 'چندانتخابی' },
    { id: 'phone', label: 'شماره تماس' }
  ];

  var DOC_TYPES = [
    { id: 'image', label: 'عکس' },
    { id: 'file', label: 'فایل (PDF/عکس)' },
    { id: 'pdf', label: 'فقط PDF' },
    { id: 'video', label: 'ویدئو' },
    { id: 'audio', label: 'صدا' }
  ];

  var DEFAULT_MENUS = {
    site: [
      { id: 'home', label: 'صفحه اصلی', icon: '⌂', link: 'سایت دست یاری.dc.html', active: true, children: [] },
      { id: 'cases', label: 'پرونده‌ها', icon: '♡', link: 'پروندهها.dc.html', active: true, fromGroups: true, children: [
        { id: 'cases-all', label: 'همه پرونده‌ها', link: 'پروندهها.dc.html', active: true }
      ] },
      { id: 'groups', label: 'گروه‌های کمک', icon: '❫', link: 'پرونده های گروه.dc.html', active: true, children: [] },
      { id: 'campaigns', label: 'کمپین‌ها', icon: '◎', link: 'کمپین ها.dc.html', active: true, children: [] },
      { id: 'about', label: 'درباره ما', icon: '◈', link: 'درباره ما.dc.html', active: true, children: [
        { id: 'about-finance', label: 'شفافیت مالی', link: 'شفافیت مالی.dc.html', active: true },
        { id: 'about-terms', label: 'قوانین و حریم خصوصی', link: 'قوانین و حریم خصوصی.dc.html', active: true }
      ] },
      { id: 'request', label: 'ثبت درخواست کمک', icon: '✎', link: 'ثبت درخواست کمک.dc.html', active: true, children: [] },
      { id: 'panels', label: 'ورود و پنل‌ها', icon: '☷', link: 'ورود خیرین دست یاری.dc.html', active: true, children: [
        { id: 'panels-login', label: 'ورود خیرین', link: 'ورود خیرین دست یاری.dc.html', active: true },
        { id: 'panels-donor', label: 'پنل خیرین', link: 'پنل خیرین دست یاری.dc.html', active: true },
        { id: 'panels-needy', label: 'پنل نیازمندان', link: 'پنل نیازمندان.dc.html', active: true },
        { id: 'panels-admin', label: 'پنل مدیریت', link: 'پنل مدیریت دست یاری.dc.html', active: true }
      ] }
    ],
    admin: [
      { id: 'dash', label: 'داشبورد', icon: '▣', link: '#dash', active: true, children: [] },
      { id: 'mytasks', label: 'وظایف من', icon: '☑', link: '#mytasks', active: true, children: [] },
      { id: 'intake', label: 'درخواست‌های کاربران', icon: '⇥', link: '#intake', active: true, children: [] },
      { id: 'list', label: 'نیازمندان', icon: '♡', link: '#list', active: true, children: [] },
      { id: 'queue', label: 'صف فعال‌سازی', icon: '◷', link: '#queue', active: true, children: [] },
      { id: 'reassign', label: 'تعیین تکلیف و جایگزینی', icon: '⇄', link: '#reassign', active: true, children: [] },
      { id: 'donors', label: 'کمک‌کنندگان (خیرین)', icon: '◍', link: '#donors', active: true, children: [] },
      { id: 'supporters', label: 'پشتیبانان / بلاگرها', icon: '★', link: '#supporters', active: true, children: [] },
      { id: 'overdue', label: 'پیگیری معوقات', icon: '⚑', link: '#overdue', active: true, children: [] },
      { id: 'payments', label: 'پرداخت‌ها و تراکنش‌ها', icon: '⇄', link: '#payments', active: true, children: [] },
      { id: 'campaigns', label: 'کمپین‌ها', icon: '◈', link: '#campaigns', active: true, children: [] },
      { id: 'news', label: 'اخبار و مقالات', icon: '❐', link: '#news', active: true, children: [] },
      { id: 'broadcast', label: 'اطلاع‌رسانی گروهی', icon: '⌾', link: '#broadcast', active: true, children: [] },
      { id: 'fund', label: 'صندوق هزینه‌های جاری', icon: '⛁', link: '#fund', active: true, children: [] },
      { id: 'reports', label: 'گزارش‌ها', icon: '▤', link: '#reports', active: true, children: [] },
      { id: 'tickets', label: 'پیام‌ها / تیکت', icon: '✉', link: '#tickets', active: true, children: [] },
      { id: 'users', label: 'کاربران پنل', icon: '☷', link: '#users', active: true, children: [] },
      { id: 'tasks', label: 'وظایف کارمندان', icon: '⌗', link: '#tasks', active: true, children: [] },
      { id: 'sms', label: 'آرشیو پیامک‌ها', icon: '✆', link: '#sms', active: true, children: [] },
      { id: 'logs', label: 'لاگ فعالیت‌ها', icon: '⧖', link: '#logs', active: true, children: [] },
      { id: 'settings', label: 'تنظیمات', icon: '⚙', link: '#settings', active: true, children: [] }
    ],
    donor: [
      { id: 'dash', label: 'داشبورد', icon: '▣', link: '#dash', active: true, children: [] },
      { id: 'pledges', label: 'تعهدهای من', icon: '↻', link: '#pledges', active: true, children: [] },
      { id: 'people', label: 'افراد من', icon: '♡', link: '#people', active: true, children: [] },
      { id: 'payments', label: 'پرداخت‌ها', icon: '⇄', link: '#payments', active: true, children: [] },
      { id: 'fund', label: 'صندوق اداره مجمع', icon: '⛁', link: '#fund', active: true, children: [] },
      { id: 'tickets', label: 'پیام‌ها', icon: '✉', link: '#tickets', active: true, children: [] },
      { id: 'profile', label: 'اطلاعات من', icon: '☷', link: '#profile', active: true, children: [] }
    ],
    needy: [
      { id: 'خانه', label: 'خانه', icon: '⌂', link: '#خانه', active: true, children: [] },
      { id: 'درخواست‌ها', label: 'درخواست‌های من', icon: '❏', link: '#درخواست‌ها', active: true, children: [] },
      { id: 'کمک‌ها', label: 'کمک‌های دریافتی', icon: '♡', link: '#کمک‌ها', active: true, children: [] },
      { id: 'تیکت', label: 'تیکت‌ها', icon: '✉', link: '#تیکت', active: true, children: [] },
      { id: 'پروفایل', label: 'پروفایل و مدارک', icon: '☷', link: '#پروفایل', active: true, children: [] }
    ]
  };

  var DEFAULT_PAGES = [
    { id: 'home', name: 'صفحه اصلی', file: 'سایت دست یاری.dc.html', active: true,
      title: 'دست یاری — سامانه کمک هدفمند به نیازمندان',
      slug: '/', desc: 'هر پرونده بازدید میدانی شده، هر ریال هدفمند و هر پرداخت دارای رسید.',
      keywords: 'خیریه, کمک هدفمند, پرونده نیازمند, دست یاری',
      h1: 'کمک شما به دست همان کسی می‌رسد که انتخاب کرده‌اید',
      sub: 'پرونده‌های تأییدشده، مبلغ باقی‌مانده شفاف و رسید پرداخت برای هر کمک.',
      cta: 'دیدن پرونده‌های باز', ctaLink: 'پروندهها.dc.html',
      noindex: false, showInSitemap: true },
    { id: 'cases', name: 'پرونده‌ها', file: 'پروندهها.dc.html', active: true,
      title: 'پرونده‌های باز — دست یاری', slug: '/cases',
      desc: 'فهرست پرونده‌های تأییدشده با فیلتر نوع نیاز، شهر، فوریت و مبلغ باقی‌مانده.',
      keywords: 'پرونده باز, فیلتر پرونده, کمک مالی',
      h1: 'پرونده‌های در انتظار کمک', sub: 'با فیلترها پرونده‌ای را پیدا کنید که به آن نزدیک‌ترید.',
      cta: 'راهنمای انتخاب پرونده', ctaLink: '#wizard', noindex: false, showInSitemap: true },
    { id: 'groups', name: 'گروه‌های کمک', file: 'پرونده های گروه.dc.html', active: true,
      title: 'گروه‌های کمک — دست یاری', slug: '/groups',
      desc: 'درمان، آزادی زندانیان، جهیزیه، مسکن، معیشت، تحصیل و معلولیت.',
      keywords: 'گروه کمک, درمان, زندانی, جهیزیه',
      h1: 'می‌خواهید به کدام گروه کمک کنید؟', sub: 'یک گروه را انتخاب کنید تا پرونده‌های همان گروه نمایش داده شود.',
      cta: 'پرداخت کمک به گروه', ctaLink: '#donate', noindex: false, showInSitemap: true },
    { id: 'case', name: 'جزئیات پرونده', file: 'جزئیات پرونده.dc.html', active: true,
      title: 'جزئیات پرونده — دست یاری', slug: '/case/{code}',
      desc: 'شرح وضعیت، مدارک تأییدشده، مبلغ جمع‌شده و باقی‌مانده هر پرونده.',
      keywords: 'جزئیات پرونده, مدارک, رسید پرداخت',
      h1: '', sub: '', cta: 'کمک به این پرونده', ctaLink: '#donate', noindex: false, showInSitemap: true },
    { id: 'about', name: 'درباره ما', file: 'درباره ما.dc.html', active: true,
      title: 'درباره مجمع — دست یاری', slug: '/about',
      desc: 'مأموریت، تاریخچه، ارکان و راه‌های تماس با مجمع.',
      keywords: 'درباره ما, مجمع خیرین, تماس',
      h1: 'ما واسطه‌ای شفاف بین خیر و نیازمند هستیم', sub: 'از سال ۱۳۹۴ تا امروز.',
      cta: 'ارسال پیام به مجمع', ctaLink: '#contact', noindex: false, showInSitemap: true },
    { id: 'finance', name: 'شفافیت مالی', file: 'شفافیت مالی.dc.html', active: true,
      title: 'شفافیت مالی — دست یاری', slug: '/finance',
      desc: 'گردش مالی، سهم هزینه‌های اداری و گزارش حسابرسی.',
      keywords: 'شفافیت مالی, گزارش, حسابرسی',
      h1: 'هر ریال کجا رفت؟', sub: 'گزارش ماهانه و سالانه به‌همراه فایل حسابرسی.',
      cta: 'دانلود گزارش سالانه', ctaLink: '#report', noindex: false, showInSitemap: true },
    { id: 'terms', name: 'قوانین و حریم خصوصی', file: 'قوانین و حریم خصوصی.dc.html', active: true,
      title: 'قوانین و حریم خصوصی — دست یاری', slug: '/terms',
      desc: 'شرایط استفاده، نگهداری داده‌ها و حقوق کاربران.',
      keywords: 'قوانین, حریم خصوصی, شرایط استفاده',
      h1: 'قوانین و حریم خصوصی', sub: 'آخرین بازنگری: مرداد ۱۴۰۴.',
      cta: '', ctaLink: '', noindex: false, showInSitemap: true },
    { id: 'request', name: 'ثبت درخواست کمک', file: 'ثبت درخواست کمک.dc.html', active: true,
      title: 'ثبت درخواست کمک — دست یاری', slug: '/request',
      desc: 'فرم ثبت درخواست برای نیازمندان؛ بررسی حداکثر تا ۷ روز کاری.',
      keywords: 'درخواست کمک, ثبت پرونده',
      h1: 'درخواست کمک ثبت کنید', sub: 'مدارک لازم را آماده داشته باشید.',
      cta: 'ارسال درخواست', ctaLink: '#submit', noindex: false, showInSitemap: true },
    { id: 'result', name: 'نتیجه پرداخت', file: 'نتیجه پرداخت.dc.html', active: true,
      title: 'نتیجه پرداخت — دست یاری', slug: '/payment/result',
      desc: 'وضعیت تراکنش و رسید پرداخت.', keywords: 'رسید پرداخت, نتیجه تراکنش',
      h1: '', sub: '', cta: 'بازگشت به پرونده‌ها', ctaLink: 'پروندهها.dc.html',
      noindex: true, showInSitemap: false },
    { id: 'login', name: 'ورود خیرین', file: 'ورود خیرین دست یاری.dc.html', active: true,
      title: 'ورود خیرین — دست یاری', slug: '/login',
      desc: 'ورود با شماره موبایل و کد یک‌بارمصرف.', keywords: 'ورود, ثبت‌نام خیر',
      h1: 'ورود به پنل خیرین', sub: 'با شماره موبایل وارد شوید.',
      cta: 'دریافت کد ورود', ctaLink: '#otp', noindex: true, showInSitemap: false }
  ];

  var DEFAULT_SEO = {
    siteName: 'دست یاری',
    tagline: 'سامانه کمک هدفمند به نیازمندان',
    titleSuffix: ' — دست یاری',
    baseUrl: 'https://dastyari.example',
    defaultDesc: 'هر پرونده بازدید میدانی شده، هر ریال هدفمند و هر پرداخت دارای رسید.',
    phone: '۰۲۱-۹۱۰۰۰۰۰۰',
    email: 'info@dastyari.example',
    address: 'تهران، خیابان ولیعصر، پلاک ۱۲۰۰، طبقه ۳',
    instagram: 'https://instagram.com/dastyari',
    telegram: 'https://t.me/dastyari',
    footerNote: 'دست یاری — مجمع خیرین. تمام حقوق محفوظ است.'
  };

  var DEFAULT_BRANDING = {
    logo: 'assets/logo-t.png',
    logoLight: 'assets/logo-t.png',
    favicon: 'assets/logo-t.png',
    logoAlt: 'دست یاری',
    logoHeightDesktop: '76',
    logoHeightMobile: '52',
    heroImage: 'assets/site-hero.png',
    ogImage: 'assets/site-hero.png',
    themeColor: '#F4511E'
  };

  var DEFAULT_WITHDRAW = {
    title: 'دلیل انصراف از حمایت',
    note: 'برای اینکه بتوانیم سریع‌تر خیر جدیدی برای این خانواده پیدا کنیم، لطفاً دلیل را انتخاب کنید.',
    requireReason: true,
    reasons: [
      { id: 'w1', label: 'توان مالی من فعلاً کم شده است', active: true, followUp: 'پیشنهاد کاهش مبلغ' },
      { id: 'w2', label: 'می‌خواهم به پرونده دیگری کمک کنم', active: true, followUp: 'پیشنهاد تعویض پرونده' },
      { id: 'w3', label: 'مدت حمایت من به پایان رسیده است', active: true, followUp: '' },
      { id: 'w4', label: 'از روند گزارش‌دهی پرونده راضی نیستم', active: true, followUp: 'ارجاع به پشتیبانی' },
      { id: 'w5', label: 'مشکل شخصی یا خانوادگی پیش آمده است', active: true, followUp: 'پیشنهاد توقف موقت' },
      { id: 'w6', label: 'دلیل دیگری دارم', active: true, followUp: '' }
    ]
  };

  var DEFAULT_NOTICE = {
    active: false,
    mode: 'bar',
    tone: 'warn',
    title: 'به‌روزرسانی سامانه',
    text: 'سامانه امروز از ساعت ۲۳:۰۰ تا ۰۲:۰۰ برای به‌روزرسانی در دسترس نخواهد بود.',
    from: '۲۳:۰۰',
    to: '۰۲:۰۰',
    dateNote: 'امشب، ۵ شهریور',
    cta: '', ctaLink: '',
    dismissible: true,
    showCountdown: true
  };

  var DEFAULT_HEADER = {
    topPhone: '۰۲۱-۹۱۰۰۲۲۳۳',
    topPhoneHref: 'tel:02191002233',
    topNote: 'پاسخگویی هر روز ۸ تا ۲۰',
    topLink1: 'درخواست کمک', topLink1Href: 'ثبت درخواست کمک.dc.html',
    topLink2: 'ورود خیرین', topLink2Href: 'ورود خیرین دست یاری.dc.html',
    cta1: 'پنل خیرین', cta1Href: 'پنل خیرین دست یاری.dc.html',
    cta2: 'مشارکت در کمک', cta2Href: 'پروندهها.dc.html',
    homeHref: 'سایت دست یاری.dc.html'
  };

  var DEFAULT_FOOTER = {
    intro: 'مجمع خیریه دست یاری — واسطه‌ای امانت‌دار میان خیر و نیازمند؛ صد درصد کمک شما به پرونده می‌رسد.',
    legal: 'شماره ثبت ۴۸۲۱۹ — مجوز سازمان بهزیستی',
    copyright: '© ۱۴۰۵ مجمع خیریه دست یاری — همه حقوق محفوظ است',
    address: 'تهران، خیابان ولیعصر، کوچه یاسمن، پلاک ۱۴، طبقه دوم',
    columns: [
      { title: 'دست یاری', active: true, links: [
        { label: 'صفحه اصلی', link: 'سایت دست یاری.dc.html', active: true },
        { label: 'درباره ما', link: 'درباره ما.dc.html', active: true },
        { label: 'شفافیت مالی', link: 'شفافیت مالی.dc.html', active: true },
        { label: 'قوانین و حریم خصوصی', link: 'قوانین و حریم خصوصی.dc.html', active: true }
      ] },
      { title: 'مشارکت', active: true, links: [
        { label: 'همه پرونده‌ها', link: 'پروندهها.dc.html', active: true },
        { label: 'گروه‌های کمک', link: 'پرونده های گروه.dc.html', active: true },
        { label: 'حمایت ماهانه', link: 'ورود خیرین دست یاری.dc.html', active: true },
        { label: 'پنل خیرین', link: 'پنل خیرین دست یاری.dc.html', active: true }
      ] },
      { title: 'نیازمندان و پشتیبانی', active: true, links: [
        { label: 'ثبت درخواست کمک', link: 'ثبت درخواست کمک.dc.html', active: true },
        { label: 'پیگیری پرونده من', link: 'پنل نیازمندان.dc.html', active: true },
        { label: 'تماس با ما', link: 'درباره ما.dc.html#contact', active: true },
        { label: '۰۲۱-۹۱۰۰۲۲۳۳ — هر روز ۸ تا ۲۰', link: 'tel:02191002233', active: true }
      ] }
    ]
  };

  /* اسکیمای محتوای هر صفحه — منبع فرم ویرایش در تنظیمات */
  var CONTENT_SCHEMA = {
    home: [
      { section: 'بخش قهرمان (Hero)', fields: [
        ['heroBadge', 'برچسب بالای تیتر', 'text'],
        ['heroTitle', 'تیتر اصلی (هر خط یک سطر)', 'area'],
        ['heroHighlight', 'کلمه برجسته تیتر', 'text'],
        ['heroText', 'متن زیر تیتر', 'area'],
        ['heroCta1', 'دکمه اول', 'text'],
        ['heroCta1Link', 'لینک دکمه اول', 'link'],
        ['heroCta2', 'دکمه دوم', 'text'],
        ['heroCta2Link', 'لینک دکمه دوم', 'link'],
        ['heroSlideTitle', 'برچسب اسلایدر پرونده‌ها', 'text'],
        ['heroSlideCta', 'دکمه اسلایدر', 'text'],
        ['heroCountCard', 'متن کارت تعداد پرونده', 'text'],
        ['heroCountNote', 'زیرنویس کارت تعداد', 'text']
      ] },
      { section: 'بخش پرونده‌های در جریان', fields: [
        ['casesEyebrow', 'برچسب بخش', 'text'],
        ['casesTitle', 'تیتر بخش', 'text'],
        ['casesText', 'توضیح بخش', 'area'],
        ['casesCta', 'دکمه همه پرونده‌ها', 'text'],
        ['casesCtaLink', 'لینک دکمه', 'link']
      ] },
      { section: 'بخش مسیر کمک', fields: [
        ['howEyebrow', 'برچسب بخش', 'text'],
        ['howTitle', 'تیتر بخش', 'text'],
        ['howText', 'توضیح بخش', 'area']
      ] },
      { section: 'بخش حمایت ماهانه', fields: [
        ['monthlyEyebrow', 'برچسب بخش', 'text'],
        ['monthlyTitle', 'تیتر بخش', 'text'],
        ['monthlyText', 'توضیح بخش', 'area']
      ] },
      { section: 'بخش دسته‌بندی نیازها', fields: [
        ['catsEyebrow', 'برچسب بخش', 'text'],
        ['catsTitle', 'تیتر بخش', 'text']
      ] },
      { section: 'بخش شفافیت مالی', fields: [
        ['transEyebrow', 'برچسب بخش', 'text'],
        ['transTitle', 'تیتر بخش', 'text'],
        ['transText', 'توضیح بخش', 'area']
      ] },
      { section: 'بخش کمپین‌ها', fields: [
        ['campEyebrow', 'برچسب بخش', 'text'],
        ['campTitle', 'تیتر بخش', 'text'],
        ['campText', 'توضیح بخش', 'area']
      ] },
      { section: 'بخش روایت‌ها', fields: [
        ['storiesEyebrow', 'برچسب بخش', 'text'],
        ['storiesTitle', 'تیتر بخش', 'text']
      ] },
      { section: 'بخش پرسش‌های پرتکرار', fields: [
        ['faqEyebrow', 'برچسب بخش', 'text'],
        ['faqTitle', 'تیتر بخش', 'text']
      ] },
      { section: 'بخش معرفی‌کنندگان', fields: [
        ['famousEyebrow', 'برچسب بخش', 'text'],
        ['famousTitle', 'تیتر بخش', 'text'],
        ['famousText', 'توضیح بخش', 'area']
      ] },
      { section: 'بخش اخبار', fields: [
        ['newsEyebrow', 'برچسب بخش', 'text'],
        ['newsTitle', 'تیتر بخش', 'text'],
        ['newsCta', 'دکمه آرشیو', 'text'],
        ['newsCtaLink', 'لینک آرشیو', 'link']
      ] },
      { section: 'فراخوان پایانی', fields: [
        ['ctaTitle', 'تیتر', 'text'],
        ['ctaText', 'متن', 'area'],
        ['ctaButton', 'دکمه اصلی', 'text'],
        ['ctaSecondary', 'دکمه دوم', 'text'],
        ['ctaSecondaryLink', 'لینک دکمه دوم', 'link']
      ] }
    ],
    cases: [
      { section: 'سرصفحه', fields: [
        ['eyebrow', 'برچسب بالای تیتر', 'text'],
        ['title', 'تیتر', 'text'],
        ['text', 'توضیح', 'area']
      ] },
      { section: 'فیلترها', fields: [
        ['filterTitle', 'عنوان جعبه فیلتر', 'text'],
        ['searchPlaceholder', 'متن جست‌وجو', 'text'],
        ['wizardTitle', 'عنوان راهنمای انتخاب', 'text'],
        ['emptyTitle', 'عنوان حالت خالی', 'text'],
        ['emptyText', 'متن حالت خالی', 'area']
      ] }
    ],
    groups: [
      { section: 'سرصفحه', fields: [
        ['askTitle', 'سوال ابتدای موبایل', 'text'],
        ['askText', 'توضیح سوال', 'area'],
        ['askSkip', 'گزینه رد کردن', 'text'],
        ['chooserTitle', 'تیتر انتخاب گروه (دسکتاپ)', 'text'],
        ['chooserText', 'توضیح انتخاب گروه', 'area']
      ] },
      { section: 'بخش پرداخت گروهی', fields: [
        ['donateTitle', 'تیتر جعبه پرداخت', 'text'],
        ['donateText', 'توضیح جعبه پرداخت', 'area'],
        ['donateButton', 'دکمه پرداخت', 'text']
      ] }
    ],
    about: [
      { section: 'سرصفحه', fields: [
        ['heroTitle', 'تیتر صفحه', 'area'],
        ['heroText', 'متن زیر تیتر', 'area']
      ] },
      { section: 'داستان شکل‌گیری', fields: [['storyTitle', 'تیتر بخش', 'text']] },
      { section: 'مسیر یک پرونده', fields: [
        ['journeyTitle', 'تیتر بخش', 'text'],
        ['journeyText', 'توضیح بخش', 'area']
      ] },
      { section: 'ارکان مجمع', fields: [
        ['teamTitle', 'تیتر بخش', 'text'],
        ['teamText', 'توضیح بخش', 'area']
      ] },
      { section: 'شفافیت مالی', fields: [
        ['financeTitle', 'تیتر بخش', 'text'],
        ['financeText', 'توضیح بخش', 'area']
      ] },
      { section: 'تماس و نشانی', fields: [
        ['contactHeading', 'تیتر بخش', 'text'],
        ['contactHeadingText', 'توضیح بخش', 'area'],
        ['contactTitle', 'تیتر فرم پیام', 'text'],
        ['contactNote', 'زیرنویس فرم', 'text'],
        ['contactSuccess', 'عنوان پیام موفقیت', 'text'],
        ['contactSuccessText', 'متن پیام موفقیت', 'area']
      ] },
      { section: 'فراخوان پایانی', fields: [
        ['ctaTitle', 'تیتر', 'text'],
        ['ctaText', 'متن', 'area']
      ] }
    ],
    finance: [
      { section: 'سرصفحه', fields: [
        ['heroTitle', 'تیتر صفحه', 'text'],
        ['heroText', 'متن زیر تیتر', 'area']
      ] },
      { section: 'حسابرسی مستقل', fields: [
        ['auditTitle', 'تیتر بخش', 'text'],
        ['auditText', 'توضیح بخش', 'area']
      ] },
      { section: 'مسیر کمک', fields: [
        ['flowTitle', 'تیتر بخش', 'text'],
        ['flowText', 'توضیح بخش', 'area']
      ] }
    ],
    terms: [
      { section: 'سرصفحه', fields: [
        ['heroTitle', 'تیتر صفحه', 'text'],
        ['heroText', 'متن معرفی سند', 'area'],
        ['badge', 'برچسب بالای تیتر', 'text']
      ] },
      { section: 'پذیرش قوانین', fields: [
        ['acceptTitle', 'تیتر بخش', 'text'],
        ['acceptText', 'متن بخش', 'area']
      ] }
    ],
    request: [
      { section: 'سرصفحه', fields: [
        ['heroTitle', 'تیتر صفحه', 'text'],
        ['heroText', 'متن زیر تیتر', 'area'],
        ['eyebrow', 'برچسب بالای تیتر', 'text']
      ] },
      { section: 'پیام موفقیت', fields: [
        ['successTitle', 'عنوان پیام موفقیت', 'text'],
        ['successStatus', 'برچسب وضعیت', 'text']
      ] }
    ],
    case: [
      { section: 'برچسب‌ها', fields: [
        ['donateCta', 'دکمه کمک', 'text'],
        ['docsTitle', 'عنوان مدارک', 'text'],
        ['storyTitle', 'عنوان شرح وضعیت', 'text'],
        ['updatesTitle', 'عنوان گزارش‌ها', 'text']
      ] }
    ],
    result: [
      { section: 'پیام‌ها', fields: [
        ['successTitle', 'عنوان پرداخت موفق', 'text'],
        ['successText', 'متن پرداخت موفق', 'area'],
        ['failTitle', 'عنوان پرداخت ناموفق', 'text'],
        ['failText', 'متن پرداخت ناموفق', 'area'],
        ['backCta', 'دکمه بازگشت', 'text']
      ] }
    ],
    login: [
      { section: 'ورود', fields: [
        ['title', 'تیتر', 'text'],
        ['text', 'توضیح', 'area'],
        ['otpTitle', 'عنوان مرحله کد', 'text'],
        ['otpText', 'توضیح مرحله کد', 'area'],
        ['signupTitle', 'عنوان تکمیل ثبت‌نام', 'text']
      ] }
    ]
  };

  var DEFAULT_CONTENT = {
    home: {
      heroBadge: 'هر پرونده بازدید میدانی شده است',
      heroTitle: 'دست تو،\nفاصله‌ی یک زندگی\nتا نجات است',
      heroHighlight: 'نجات',
      heroText: 'هر پرونده در دست یاری یک انسان واقعی است: بیمار، زندانیِ بدهکار، عروسی بی‌جهیزیه، سالمندی تنها. مدارک همه بررسی شده و رسید هر ریال کمک شما ثبت می‌شود.',
      heroCta1: 'دیدن پرونده‌های فوری', heroCta1Link: 'پروندهها.dc.html',
      heroCta2: 'چطور کار می‌کند؟', heroCta2Link: '#how',
      heroSlideTitle: 'فوری‌ترین پرونده‌های امروز', heroSlideCta: 'کمک به این پرونده',
      heroCountCard: '۱٫۲۴۰ پرونده تاییدشده', heroCountNote: 'خودت انتخاب کن کمکت کجا برود',
      casesEyebrow: 'پرونده‌های در جریان', casesTitle: 'اینها همین امروز منتظرند',
      casesText: 'هر پرونده مبلغ مشخص، مهلت مشخص و مدارک تاییدشده دارد. می‌توانید کل مبلغ را تامین کنید یا بخشی از آن را.',
      casesCta: 'همه پرونده‌ها ←', casesCtaLink: 'پروندهها.dc.html',
      howEyebrow: 'مسیر کمک', howTitle: 'از تایید مدارک تا رسید پرداخت',
      howText: 'هیچ پرونده‌ای بدون بازدید میدانی مددکار و بررسی مدارک منتشر نمی‌شود؛ و هیچ کمکی بدون رسید و تخصیص مشخص نمی‌ماند.',
      monthlyEyebrow: 'حمایت ماهانه', monthlyTitle: 'با ماهی ۵۰۰ هزار تومان، یک خانواده را سرپا نگه دار',
      monthlyText: 'کمک ماهانه چیزی است که پرونده‌ها را واقعاً می‌بندد: خانواده روی آن حساب می‌کند و ما می‌توانیم برنامه‌ریزی کنیم. موعد پرداخت را خودت انتخاب می‌کنی و ۲۴ ساعت قبل یادآوری می‌گیری.',
      catsEyebrow: 'دسته‌بندی نیازها', catsTitle: 'کمک تو کجا لازم‌تر است؟',
      transEyebrow: 'شفافیت مالی', transTitle: 'هر ریال، یک ردپای قابل پیگیری',
      transText: 'گزارش مالی ماهانه منتشر می‌شود، حساب‌ها سالانه حسابرسی مستقل می‌شوند و سقف هزینه اداری موسسه ۵ درصد است — سال گذشته ۴٫۶ درصد بود.',
      campEyebrow: 'کمپین‌های جاری', campTitle: 'پرونده‌هایی که با هم می‌بندیم',
      campText: 'کمپین، مجموعه‌ای از پرونده‌های هم‌موضوع با یک مهلت مشترک است؛ مشارکت در آن بین همه پرونده‌های کمپین تقسیم می‌شود.',
      storiesEyebrow: 'روایت‌ها', storiesTitle: 'پرونده‌هایی که بسته شد',
      faqEyebrow: 'پرسش‌های پرتکرار', faqTitle: 'آنچه بیشتر می‌پرسند',
      famousEyebrow: 'آن‌ها ما را معرفی می‌کنند', famousTitle: 'چهره‌هایی که دست یاری را به مخاطبانشان رساندند',
      famousText: 'هیچ‌کدام هزینه‌ای دریافت نمی‌کنند؛ فقط پرونده‌های تاییدشده را معرفی می‌کنند و گزارش نتیجه را با مخاطبان خود به اشتراک می‌گذارند.',
      newsEyebrow: 'اخبار و مقالات', newsTitle: 'تازه‌ترین گزارش‌ها و نوشته‌ها',
      newsCta: 'آرشیو کامل ←', newsCtaLink: '#allnews',
      ctaTitle: 'امروز یک پرونده منتظر شماست',
      ctaText: 'با ورود به پنل خیرین، تعهدها، موعدها و رسیدهای خود را یکجا مدیریت کنید.',
      ctaButton: 'کمک می‌کنم',
      ctaSecondary: 'ورود / عضویت', ctaSecondaryLink: 'ورود خیرین دست یاری.dc.html'
    },
    cases: {
      eyebrow: 'پرونده‌های باز', title: 'پرونده‌های در انتظار کمک',
      text: 'با فیلترها پرونده‌ای را پیدا کنید که به آن نزدیک‌ترید.',
      filterTitle: 'فیلترها', searchPlaceholder: 'عنوان، کد یا شهر…',
      wizardTitle: 'راهنمای انتخاب پرونده',
      emptyTitle: 'پرونده‌ای با این فیلترها پیدا نشد',
      emptyText: 'یکی از فیلترها را بردارید یا فیلترها را پاک کنید.'
    },
    groups: {
      askTitle: 'می‌خواهید به کدام گروه کمک کنید؟',
      askText: 'یکی را انتخاب کنید؛ بعد پرونده‌های تأییدشده همان گروه را با فیلتر شهر و فوریت می‌بینید.',
      askSkip: 'فرقی نمی‌کند، فوری‌ترین‌ها را نشانم بده',
      chooserTitle: 'می‌خواهید به کدام گروه کمک کنید؟',
      chooserText: 'یک گروه را انتخاب کنید تا پرونده‌های تأییدشده همان گروه نمایش داده شود.',
      donateTitle: 'کمک به این گروه', donateText: 'مبلغ کمک شما بین فوری‌ترین پرونده‌های همین گروه تقسیم می‌شود.',
      donateButton: 'پرداخت کمک به گروه'
    },
    about: {
      heroTitle: 'ما واسطه‌ی یک دست خالی و یک دست بازیم',
      heroText: 'مجمع خیریه دست یاری از سال ۱۳۹۴ با یک اصل ساده کار می‌کند: هیچ کمکی نباید بی‌نشان بماند. هر پرونده راستی‌آزمایی می‌شود، هر ریال گزارش می‌شود و هر خیر می‌داند کمکش دقیقاً به چه کسی رسیده است.',
      storyTitle: 'از یک دفترچه دست‌نویس تا سامانه‌ای با هزاران پرونده',
      journeyTitle: 'از درخواست تا رسید تحویل، شش مرحله',
      journeyText: 'هیچ پرونده‌ای بدون بازدید میدانی و مدارک، روی سایت منتشر نمی‌شود. میانگین زمان بررسی یک پرونده ۹ روز کاری است.',
      teamTitle: 'کسانی که پشت این پرونده‌ها ایستاده‌اند',
      teamText: 'هیئت امنا و مدیران اجرایی مجمع، همگی داوطلبانه فعالیت می‌کنند. تیم اجرایی شامل ۱۴ کارشناس تمام‌وقت و ۲۳۰ داوطلب فعال در ۹ استان است.',
      financeTitle: 'هر ریال، یک ردیف در گزارش',
      financeText: 'صورت‌های مالی مجمع هر شش ماه توسط مؤسسه حسابرسی مستقل بررسی و روی سایت منتشر می‌شود. خیرین می‌توانند در پنل خود گزارش تفصیلی مصرف کمکشان را همراه با رسید و تصویر تحویل ببینند.',
      contactHeading: 'سـوالی دارید؟ درِ مجمع باز است',
      contactHeadingText: 'برای بازدید حضوری از دفتر مجمع، مشاهده اسناد پرونده‌ها یا همکاری سازمانی، هر روز از ساعت ۸ تا ۲۰ پاسخگو هستیم.',
      contactTitle: 'پیام به مجمع', contactNote: 'پاسخ حداکثر تا ۲ روز کاری',
      contactSuccess: 'پیغام شما ارسال شد',
      contactSuccessText: 'پیام شما ثبت شد و کارشناس مجمع حداکثر تا ۲ روز کاری پاسخ می‌دهد. کد پیگیری را نگه دارید.',
      ctaTitle: 'یک پرونده منتظر شماست',
      ctaText: 'می‌توانید یک‌بار کمک کنید یا حامی ماهانه یک خانواده شوید. در هر دو حالت، گزارش تحویل به دست شما می‌رسد.'
    },
    finance: {
      heroTitle: 'هر ریال، یک ردیف قابل پیگیری',
      heroText: 'این صفحه چهار چیز را نشان می‌دهد: گزارش مالی هر ماه، نتیجه حسابرسی مستقل، هزینه‌های اداری مجمع و اینکه کمک شما دقیقاً از چه مسیری به دست نیازمند می‌رسد. هیچ عددی در این صفحه دستی وارد نمی‌شود؛ همه از سامانه پرونده‌ها و حساب‌های بانکی مجمع خوانده می‌شود.',
      auditTitle: 'حساب‌های ما را کسی بررسی می‌کند که به ما پاسخگو نیست',
      auditText: 'هر شش ماه یک مؤسسه حسابرسی مستقل، بدون اطلاع قبلی از نمونه‌ها، دفاتر و گردش حساب‌های مجمع را بررسی می‌کند. انتخاب مؤسسه با هیئت امنا و تمدید قرارداد آن حداکثر برای دو دوره متوالی مجاز است تا رابطه بلندمدت شکل نگیرد.',
      flowTitle: 'مسیر کمک شما، مرحله به مرحله',
      flowText: 'از لحظه‌ای که مبلغی می‌پردازید تا لحظه‌ای که رسید تحویل در پنل شما ثبت می‌شود، هفت مرحله طی می‌شود. در هر مرحله مشخص است پول کجاست، چه کسی مسئول است و چه مستندی تولید می‌شود.'
    },
    terms: {
      badge: 'سند رسمی موسسه خیریه دست یاری',
      heroTitle: 'قوانین استفاده و حریم خصوصی',
      heroText: 'این سند شرایط استفاده از سامانه دست یاری، حقوق و تعهدات خیرین و نیازمندان، نحوه جمع‌آوری و نگهداری اطلاعات و مسئولیت‌های موسسه را مشخص می‌کند. ثبت‌نام در سامانه به معنای پذیرش کامل مفاد این سند است.',
      acceptTitle: 'پذیرش قوانین',
      acceptText: 'با زدن دکمه ثبت‌نام در سامانه، تایید می‌کنید که این سند را خوانده و پذیرفته‌اید.'
    },
    request: {
      eyebrow: 'درخواست کمک',
      heroTitle: 'درخواست خود را ثبت کنید؛ بقیه مسیر با ماست',
      heroText: 'برای ثبت درخواست ابتدا با شماره موبایل خود عضو می‌شوید تا بتوانید وضعیت پرونده‌تان را پیگیری کنید. سپس شرایط را می‌خوانید و فرم را پر می‌کنید. بررسی درخواست‌ها به‌ترتیب فوریت و حداکثر در ۹ روز کاری انجام می‌شود.',
      successTitle: 'درخواست شما ثبت شد',
      successStatus: 'وضعیت: در نوبت بازدید میدانی'
    },
    case: {
      donateCta: 'کمک به این پرونده', docsTitle: 'مدارک تأییدشده',
      storyTitle: 'شرح وضعیت', updatesTitle: 'گزارش‌های پرونده'
    },
    result: {
      successTitle: 'پرداخت شما با موفقیت انجام شد',
      successText: 'رسید پرداخت در پنل خیرین شما ثبت شد و به پرونده اختصاص یافت.',
      failTitle: 'پرداخت انجام نشد',
      failText: 'مبلغی از حساب شما کسر نشده است. می‌توانید دوباره تلاش کنید.',
      backCta: 'بازگشت به پرونده‌ها'
    },
    login: {
      title: 'ورود به پنل خیرین', text: 'با شماره موبایل وارد شوید؛ کد یک‌بارمصرف پیامک می‌شود.',
      otpTitle: 'کد ورود را وارد کنید', otpText: 'کد چهار رقمی ارسال‌شده به شماره شما را وارد کنید.',
      signupTitle: 'تکمیل اطلاعات'
    }
  };

  function clone(v) { return JSON.parse(JSON.stringify(v)); }

  function mergeContent(v) {
    var out = clone(DEFAULT_CONTENT);
    if (!v) return out;
    Object.keys(out).forEach(function (k) { if (v[k]) out[k] = Object.assign(out[k], v[k]); });
    Object.keys(v).forEach(function (k) { if (!out[k]) out[k] = v[k]; });
    return out;
  }

  function migrateMenus(m) {
    /* افزودن آیتم‌های جدید به منوی ذخیره‌شده کاربر */
    var adds = [['admin', 'reassign', 'queue'], ['site', 'campaigns', 'groups']];
    adds.forEach(function (a) {
      var pane = m[a[0]];
      if (!pane) return;
      var has = pane.some(function (x) { return x.id === a[1]; });
      if (has) return;
      var def = (DEFAULT_MENUS[a[0]] || []).filter(function (x) { return x.id === a[1]; })[0];
      if (!def) return;
      var at = pane.findIndex(function (x) { return x.id === a[2]; });
      pane.splice(at >= 0 ? at + 1 : pane.length, 0, clone(def));
    });
    return m;
  }

  function mergeForms(v) {
    var out = clone(DEFAULT_FORMS);
    if (v && typeof v === 'object') {
      Object.keys(v).forEach(function (k) {
        var f = v[k] || {};
        out[k] = {
          note: typeof f.note === 'string' ? f.note : (out[k] ? out[k].note : ''),
          questions: Array.isArray(f.questions) ? f.questions : (out[k] ? out[k].questions : []),
          docs: Array.isArray(f.docs) ? f.docs : (out[k] ? out[k].docs : [])
        };
      });
    }
    return out;
  }

  function read() {
    try {
      var raw = localStorage.getItem(KEY);
      if (!raw) return { groups: clone(DEFAULT_GROUPS), forms: clone(DEFAULT_FORMS), menus: clone(DEFAULT_MENUS), pages: clone(DEFAULT_PAGES), seo: clone(DEFAULT_SEO), branding: clone(DEFAULT_BRANDING), header: clone(DEFAULT_HEADER), notice: clone(DEFAULT_NOTICE), footer: clone(DEFAULT_FOOTER), content: clone(DEFAULT_CONTENT) };
      var v = JSON.parse(raw);
      return {
        groups: Array.isArray(v.groups) && v.groups.length ? v.groups : clone(DEFAULT_GROUPS),
        forms: mergeForms(v.forms),
        menus: migrateMenus((v.menus && v.menus.admin && v.menus.site) ? v.menus : clone(DEFAULT_MENUS)),
        pages: Array.isArray(v.pages) && v.pages.length ? v.pages : clone(DEFAULT_PAGES),
        seo: v.seo ? Object.assign(clone(DEFAULT_SEO), v.seo) : clone(DEFAULT_SEO),
        branding: v.branding ? Object.assign(clone(DEFAULT_BRANDING), v.branding) : clone(DEFAULT_BRANDING),
        header: v.header ? Object.assign(clone(DEFAULT_HEADER), v.header) : clone(DEFAULT_HEADER),
        notice: v.notice ? Object.assign(clone(DEFAULT_NOTICE), v.notice) : clone(DEFAULT_NOTICE),
        withdraw: v.withdraw ? Object.assign(clone(DEFAULT_WITHDRAW), v.withdraw) : clone(DEFAULT_WITHDRAW),
        footer: v.footer ? Object.assign(clone(DEFAULT_FOOTER), v.footer) : clone(DEFAULT_FOOTER),
        content: mergeContent(v.content)
      };
    } catch (e) { return { groups: clone(DEFAULT_GROUPS), menus: clone(DEFAULT_MENUS), pages: clone(DEFAULT_PAGES), seo: clone(DEFAULT_SEO), branding: clone(DEFAULT_BRANDING), header: clone(DEFAULT_HEADER), footer: clone(DEFAULT_FOOTER), content: clone(DEFAULT_CONTENT) }; }
  }

  var previewNoticeOverride = null;
  var listeners = [];
  function emit() { listeners.forEach(function (f) { try { f(DY.state()); } catch (e) {} }); }

  function write(next) {
    try { localStorage.setItem(KEY, JSON.stringify(next)); } catch (e) {}
    emit();
  }

  var DY = {
    ICONS: ICONS,
    USER_KEY: 'dy-user',
    user: function () {
      try { var raw = localStorage.getItem('dy-user'); return raw ? JSON.parse(raw) : null; } catch (e) { return null; }
    },
    setUser: function (u) {
      try { u ? localStorage.setItem('dy-user', JSON.stringify(u)) : localStorage.removeItem('dy-user'); } catch (e) {}
      emit();
    },
    allIcons: function () { return ICONS.reduce(function (a, c) { return a.concat(c.items); }, []); },
    DEFAULT_GROUPS: DEFAULT_GROUPS,
    DEFAULT_FORMS: DEFAULT_FORMS,
    FIELD_TYPES: FIELD_TYPES,
    DOC_TYPES: DOC_TYPES,
    forms: function () { return read().forms; },
    form: function (groupId) {
      var f = read().forms[groupId];
      return f || { note: '', questions: clone(BASE_QUESTIONS), docs: clone(BASE_DOCS) };
    },
    setForm: function (groupId, form) {
      var s = read(); s.forms = s.forms || {}; s.forms[groupId] = form; write(s);
    },
    resetForm: function (groupId) {
      var s = read(); s.forms = s.forms || {};
      s.forms[groupId] = DEFAULT_FORMS[groupId]
        ? clone(DEFAULT_FORMS[groupId])
        : { note: '', questions: clone(BASE_QUESTIONS), docs: clone(BASE_DOCS) };
      write(s);
    },
    DEFAULT_MENUS: DEFAULT_MENUS,
    state: read,    groups: function () { return read().groups; },
    activeGroups: function () { return read().groups.filter(function (g) { return g.active !== false; }); },
    menus: function () { return read().menus; },
    menu: function (panel) {
      var s = read();
      var m = s.menus[panel] || [];
      var groups = s.groups.filter(function (g) { return g.active !== false; });
      return m.filter(function (i) { return i.active !== false; }).map(function (i) {
        var c = clone(i);
        c.children = (i.children || []).filter(function (s2) { return s2.active !== false; });
        if (i.fromGroups) {
          c.children = c.children.concat(groups.map(function (g) {
            return { id: 'g-' + g.id, label: g.title, icon: g.icon, link: 'پروندهها.dc.html#g=' + encodeURIComponent(g.id), active: true, fromGroup: true };
          }));
        }
        return c;
      });
    },
    PAGES: [
      { label: 'صفحه اصلی', link: 'سایت دست یاری.dc.html' },
      { label: 'پرونده‌ها', link: 'پروندهها.dc.html' },
      { label: 'گروه‌های کمک', link: 'پرونده های گروه.dc.html' },
      { label: 'جزئیات پرونده', link: 'جزئیات پرونده.dc.html' },
      { label: 'درباره ما', link: 'درباره ما.dc.html' },
      { label: 'شفافیت مالی', link: 'شفافیت مالی.dc.html' },
      { label: 'قوانین و حریم خصوصی', link: 'قوانین و حریم خصوصی.dc.html' },
      { label: 'ثبت درخواست کمک', link: 'ثبت درخواست کمک.dc.html' },
      { label: 'نتیجه پرداخت', link: 'نتیجه پرداخت.dc.html' },
      { label: 'ورود خیرین', link: 'ورود خیرین دست یاری.dc.html' },
      { label: 'پنل خیرین', link: 'پنل خیرین دست یاری.dc.html' },
      { label: 'پنل نیازمندان', link: 'پنل نیازمندان.dc.html' },
      { label: 'پنل مدیریت', link: 'پنل مدیریت دست یاری.dc.html' }
    ],
    CONTENT_SCHEMA: CONTENT_SCHEMA,
    DEFAULT_BRANDING: DEFAULT_BRANDING,
    DEFAULT_FOOTER: DEFAULT_FOOTER,
    DEFAULT_CONTENT: DEFAULT_CONTENT,
    DEFAULT_WITHDRAW: DEFAULT_WITHDRAW,
    withdraw: function () { return read().withdraw || clone(DEFAULT_WITHDRAW); },
    setWithdraw: function (w) { var s = read(); s.withdraw = Object.assign({}, s.withdraw || clone(DEFAULT_WITHDRAW), w); write(s); },
    resetWithdraw: function () { var s = read(); s.withdraw = clone(DEFAULT_WITHDRAW); write(s); },
    DEFAULT_NOTICE: DEFAULT_NOTICE,
    previewNotice: function (patch) {
      if (JSON.stringify(patch || null) === JSON.stringify(previewNoticeOverride)) return;
      previewNoticeOverride = patch || null;
      emit();
    },
    notice: function () {
      var n = read().notice || clone(DEFAULT_NOTICE);
      return previewNoticeOverride ? Object.assign({}, n, previewNoticeOverride) : n;
    },
    setNotice: function (n) { var s = read(); s.notice = Object.assign({}, s.notice || clone(DEFAULT_NOTICE), n); write(s); },
    resetNotice: function () { var s = read(); s.notice = clone(DEFAULT_NOTICE); write(s); },
    DEFAULT_HEADER: DEFAULT_HEADER,
    header: function () { return read().header; },
    setHeader: function (h) { var s = read(); s.header = Object.assign({}, s.header, h); write(s); },
    resetHeader: function () { var s = read(); s.header = clone(DEFAULT_HEADER); write(s); },
    branding: function () { return read().branding; },
    footer: function () { return read().footer; },
    content: function (pageId) { return (read().content || {})[pageId] || {}; },
    txt: function (pageId, key, fallback) {
      var c = (read().content || {})[pageId] || {};
      var v = c[key];
      return (v === undefined || v === null || v === '') ? (fallback === undefined ? '' : fallback) : v;
    },
    setBranding: function (b) { var s = read(); s.branding = Object.assign({}, s.branding, b); write(s); },
    setFooter: function (f) { var s = read(); s.footer = Object.assign({}, s.footer, f); write(s); },
    setContent: function (pageId, patch) {
      var s = read();
      s.content = s.content || {};
      s.content[pageId] = Object.assign({}, s.content[pageId], patch);
      write(s);
    },
    resetContent: function (pageId) {
      var s = read();
      s.content = s.content || {};
      if (pageId) s.content[pageId] = clone(DEFAULT_CONTENT[pageId] || {});
      else s.content = clone(DEFAULT_CONTENT);
      write(s);
    },
    resetBranding: function () { var s = read(); s.branding = clone(DEFAULT_BRANDING); write(s); },
    resetFooter: function () { var s = read(); s.footer = clone(DEFAULT_FOOTER); write(s); },
    DEFAULT_PAGES: DEFAULT_PAGES,
    DEFAULT_SEO: DEFAULT_SEO,
    pages: function () { return read().pages; },
    page: function (idOrFile) {
      var list = read().pages;
      for (var i = 0; i < list.length; i++) {
        if (list[i].id === idOrFile || list[i].file === idOrFile) return list[i];
      }
      return null;
    },
    seo: function () { return read().seo; },
    setPages: function (pages) { var s = read(); s.pages = pages; write(s); },
    setSeo: function (seo) { var s = read(); s.seo = Object.assign({}, s.seo, seo); write(s); },
    resetPages: function () { var s = read(); s.pages = clone(DEFAULT_PAGES); s.seo = clone(DEFAULT_SEO); write(s); },
    setGroups: function (groups) { var s = read(); s.groups = groups; write(s); },    setMenus: function (menus) { var s = read(); s.menus = menus; write(s); },
    resetGroups: function () { var s = read(); s.groups = clone(DEFAULT_GROUPS); write(s); },
    resetMenus: function () { var s = read(); s.menus = clone(DEFAULT_MENUS); write(s); },
    onChange: function (cb) {
      listeners.push(cb);
      window.addEventListener('storage', function (e) { if (e.key === KEY) cb(read()); });
      return function () { listeners = listeners.filter(function (f) { return f !== cb; }); };
    }
  };

  window.DY = DY;
})();
